<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use PDO;
use RuntimeException;

final class BranchService
{
    private PDO $pdo;
    private static ?bool $hasProductImagePathColumn = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? App::db();
        $this->ensureSchema();
    }

    /** @return array<string,mixed>|null */
    public function getTenant(int $tenantId): ?array
    {
        $this->ensureSchema();
        $st = $this->pdo->prepare(
            'SELECT id, name, slug, plan, is_active, license_starts_at, license_expires_at, paid_amount,
                    parent_tenant_id, branch_group_id, is_main_branch, max_branches
             FROM tenants
             WHERE id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function getGroupRootTenantId(int $tenantId): int
    {
        $row = $this->getTenant($tenantId);
        if (! $row) {
            throw new RuntimeException('Store not found.');
        }
        $group = (int) ($row['branch_group_id'] ?? 0);
        if ($group > 0) {
            return $group;
        }
        $parent = (int) ($row['parent_tenant_id'] ?? 0);
        if ($parent > 0) {
            return $parent;
        }

        return (int) ($row['id'] ?? $tenantId);
    }

    /** @return list<array<string,mixed>> */
    public function listBranches(int $tenantId): array
    {
        $rootId = $this->getGroupRootTenantId($tenantId);
        $st = $this->pdo->prepare(
            'SELECT id, name, slug, is_active, is_main_branch, parent_tenant_id, branch_group_id,
                    license_expires_at, created_at, updated_at
             FROM tenants
             WHERE branch_group_id = ?
             ORDER BY is_main_branch DESC, id ASC'
        );
        $st->execute([$rootId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getBranchLimit(int $tenantId): int
    {
        $root = $this->getTenant($this->getGroupRootTenantId($tenantId));
        if (! $root) {
            return 1;
        }

        return max(1, (int) ($root['max_branches'] ?? 1));
    }

    public function countBranches(int $tenantId, bool $activeOnly = false): int
    {
        $rootId = $this->getGroupRootTenantId($tenantId);
        $sql = 'SELECT COUNT(*) FROM tenants WHERE branch_group_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $st = $this->pdo->prepare($sql);
        $st->execute([$rootId]);

        return (int) $st->fetchColumn();
    }

    public function updateBranchLimit(int $tenantId, int $limit): void
    {
        $limit = max(1, min(500, $limit));
        $rootId = $this->getGroupRootTenantId($tenantId);
        $activeCount = $this->countBranches($rootId, true);
        if ($activeCount > $limit) {
            throw new RuntimeException('Cannot set branch limit below current active branch count.');
        }
        $this->pdo->prepare('UPDATE tenants SET max_branches = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$limit, $rootId]);
    }

    public function setMainBranch(int $actorTenantId, int $targetTenantId): void
    {
        $actorRoot = $this->getGroupRootTenantId($actorTenantId);
        $target = $this->getTenant($targetTenantId);
        if (! $target) {
            throw new RuntimeException('Target branch not found.');
        }
        $targetRoot = (int) ($target['branch_group_id'] ?? 0);
        if ($targetRoot !== $actorRoot) {
            throw new RuntimeException('Branch is outside your account group.');
        }
        if (! (bool) $target['is_active']) {
            throw new RuntimeException('Only active branch can be set as main.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE tenants SET is_main_branch = 0, updated_at = NOW() WHERE branch_group_id = ?')
                ->execute([$actorRoot]);
            $this->pdo->prepare('UPDATE tenants SET is_main_branch = 1, updated_at = NOW() WHERE id = ?')
                ->execute([$targetTenantId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Failed to set main branch.', 0, $e);
        }
    }

    public function toggleBranchActive(int $actorTenantId, int $targetTenantId, bool $active): void
    {
        $actorRoot = $this->getGroupRootTenantId($actorTenantId);
        $target = $this->getTenant($targetTenantId);
        if (! $target) {
            throw new RuntimeException('Branch not found.');
        }
        $targetRoot = (int) ($target['branch_group_id'] ?? 0);
        if ($targetRoot !== $actorRoot) {
            throw new RuntimeException('Branch is outside your account group.');
        }

        $currentActive = (bool) ($target['is_active'] ?? false);
        if ($currentActive === $active) {
            return;
        }

        if (! $active && (bool) ($target['is_main_branch'] ?? false)) {
            throw new RuntimeException('Main branch cannot be closed. Set another branch as main first.');
        }

        if ($active) {
            $limit = $this->getBranchLimit($actorTenantId);
            $activeCount = $this->countBranches($actorTenantId, true);
            if ($activeCount >= $limit) {
                throw new RuntimeException('Active branch limit reached for this account.');
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE tenants SET is_active = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$active ? 1 : 0, $targetTenantId]);

            if ($active) {
                $st = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM tenants WHERE branch_group_id = ? AND is_active = 1 AND is_main_branch = 1'
                );
                $st->execute([$actorRoot]);
                $activeMain = (int) $st->fetchColumn();
                if ($activeMain < 1) {
                    $this->pdo->prepare('UPDATE tenants SET is_main_branch = 1, updated_at = NOW() WHERE id = ?')
                        ->execute([$targetTenantId]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Failed to update branch status.', 0, $e);
        }
    }

    /**
     * @param array{categories?:bool,ingredients?:bool,products?:bool,requirements?:bool} $cloneOptions
     */
    public function createBranch(
        int $actorTenantId,
        string $branchName,
        string $branchSlug,
        int $sourceTenantId,
        array $cloneOptions
    ): int {
        $branchName = trim($branchName);
        $branchSlug = $this->sanitizeSlug($branchSlug);
        if ($branchName === '' || $branchSlug === '') {
            throw new RuntimeException('Branch name and slug are required.');
        }

        $rootId = $this->getGroupRootTenantId($actorTenantId);
        if ($sourceTenantId > 0) {
            $sourceRoot = $this->getGroupRootTenantId($sourceTenantId);
            if ($sourceRoot !== $rootId) {
                throw new RuntimeException('You can only clone from branches inside your account group.');
            }
        }

        $limit = $this->getBranchLimit($actorTenantId);
        $activeCount = $this->countBranches($actorTenantId, true);
        if ($activeCount >= $limit) {
            throw new RuntimeException('Active branch limit reached. Close a branch or ask super admin to increase your branch quota.');
        }

        $slug = $this->ensureUniqueTenantSlug($branchSlug);

        $rootTenant = $this->getTenant($rootId);
        if (! $rootTenant) {
            throw new RuntimeException('Root account tenant not found.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'INSERT INTO tenants
                 (parent_tenant_id, branch_group_id, name, slug, plan, is_active, is_main_branch,
                  license_starts_at, license_expires_at, paid_amount, max_branches, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, NULL, NULL, NOW(), NOW())'
            )->execute([
                $rootId,
                $rootId,
                $branchName,
                $slug,
                (string) ($rootTenant['plan'] ?? 'subscription'),
                1,
                $rootTenant['license_starts_at'] ?: null,
                $rootTenant['license_expires_at'] ?: null,
            ]);
            $newTenantId = (int) $this->pdo->lastInsertId();

            if ($sourceTenantId > 0) {
                $this->cloneSelectedData($sourceTenantId, $newTenantId, $cloneOptions);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Failed to create branch: '.$e->getMessage(), 0, $e);
        }

        return $newTenantId;
    }

    /**
     * @param array{categories?:bool,ingredients?:bool,products?:bool,requirements?:bool} $cloneOptions
     */
    public function cloneSelectedData(int $sourceTenantId, int $targetTenantId, array $cloneOptions): void
    {
        $cloneCategories = ! empty($cloneOptions['categories']);
        $cloneIngredients = ! empty($cloneOptions['ingredients']);
        $cloneProducts = ! empty($cloneOptions['products']);
        $cloneRequirements = ! empty($cloneOptions['requirements']);

        $categoryMap = [];
        $ingredientMap = [];
        $productMap = [];

        if ($cloneCategories) {
            $categoryMap = $this->cloneCategories($sourceTenantId, $targetTenantId);
        }
        if ($cloneIngredients) {
            $ingredientMap = $this->cloneIngredients($sourceTenantId, $targetTenantId);
        }
        if ($cloneProducts) {
            $productMap = $this->cloneProducts($sourceTenantId, $targetTenantId, $categoryMap);
        }
        if ($cloneProducts && $cloneRequirements) {
            $this->cloneProductRequirements($sourceTenantId, $targetTenantId, $ingredientMap, $productMap);
        }
    }

    /** @return array<int,int> old => new */
    private function cloneCategories(int $sourceTenantId, int $targetTenantId): array
    {
        if (! $this->tableExists('categories')) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT id, name FROM categories WHERE tenant_id = ? ORDER BY id ASC');
        $st->execute([$sourceTenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $oldId = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($oldId < 1 || $name === '') {
                continue;
            }
            $exists = $this->pdo->prepare('SELECT id FROM categories WHERE tenant_id = ? AND name = ? LIMIT 1');
            $exists->execute([$targetTenantId, $name]);
            $newId = (int) ($exists->fetchColumn() ?: 0);
            if ($newId < 1) {
                $this->pdo->prepare(
                    'INSERT INTO categories (tenant_id, name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
                )->execute([$targetTenantId, $name]);
                $newId = (int) $this->pdo->lastInsertId();
            }
            $map[$oldId] = $newId;
        }

        return $map;
    }

    /** @return array<int,int> old => new */
    private function cloneIngredients(int $sourceTenantId, int $targetTenantId): array
    {
        if (! $this->tableExists('ingredients')) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT id, name, unit, unit_cost, stock_quantity, low_stock_threshold
             FROM ingredients
             WHERE tenant_id = ?
             ORDER BY id ASC'
        );
        $st->execute([$sourceTenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $oldId = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($oldId < 1 || $name === '') {
                continue;
            }
            $exists = $this->pdo->prepare('SELECT id FROM ingredients WHERE tenant_id = ? AND name = ? LIMIT 1');
            $exists->execute([$targetTenantId, $name]);
            $newId = (int) ($exists->fetchColumn() ?: 0);
            if ($newId < 1) {
                $this->pdo->prepare(
                    'INSERT INTO ingredients
                     (tenant_id, name, unit, unit_cost, stock_quantity, low_stock_threshold, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
                )->execute([
                    $targetTenantId,
                    $name,
                    (string) ($row['unit'] ?? 'pc'),
                    (float) ($row['unit_cost'] ?? 0),
                    (float) ($row['stock_quantity'] ?? 0),
                    (float) ($row['low_stock_threshold'] ?? 0),
                ]);
                $newId = (int) $this->pdo->lastInsertId();
            }
            $map[$oldId] = $newId;
        }

        return $map;
    }

    /** @param array<int,int> $categoryMap @return array<int,int> */
    private function cloneProducts(int $sourceTenantId, int $targetTenantId, array $categoryMap): array
    {
        if (! $this->tableExists('products')) {
            return [];
        }
        $hasImagePath = $this->hasProductImagePathColumn();
        $selectImage = $hasImagePath ? ', image_path' : '';
        $st = $this->pdo->prepare(
            "SELECT id, category_id, name, price, is_active{$selectImage}
             FROM products
             WHERE tenant_id = ?
             ORDER BY id ASC"
        );
        $st->execute([$sourceTenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $oldId = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($oldId < 1 || $name === '') {
                continue;
            }
            $categoryId = (int) ($row['category_id'] ?? 0);
            $newCategoryId = $categoryId > 0 ? ((int) ($categoryMap[$categoryId] ?? 0) ?: null) : null;
            $finalName = $this->ensureUniqueName('products', $targetTenantId, $name);

            if ($hasImagePath) {
                $this->pdo->prepare(
                    'INSERT INTO products
                     (tenant_id, category_id, name, price, image_path, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
                )->execute([
                    $targetTenantId,
                    $newCategoryId,
                    $finalName,
                    (float) ($row['price'] ?? 0),
                    (string) ($row['image_path'] ?? ''),
                    (int) (! empty($row['is_active']) ? 1 : 0),
                ]);
            } else {
                $this->pdo->prepare(
                    'INSERT INTO products
                     (tenant_id, category_id, name, price, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
                )->execute([
                    $targetTenantId,
                    $newCategoryId,
                    $finalName,
                    (float) ($row['price'] ?? 0),
                    (int) (! empty($row['is_active']) ? 1 : 0),
                ]);
            }
            $map[$oldId] = (int) $this->pdo->lastInsertId();
        }

        return $map;
    }

    /** @param array<int,int> $ingredientMap @param array<int,int> $productMap */
    private function cloneProductRequirements(int $sourceTenantId, int $targetTenantId, array $ingredientMap, array $productMap): void
    {
        if (! $this->tableExists('product_ingredients') || $productMap === []) {
            return;
        }
        $st = $this->pdo->prepare(
            'SELECT product_id, ingredient_id, quantity_required
             FROM product_ingredients
             WHERE tenant_id = ?
             ORDER BY id ASC'
        );
        $st->execute([$sourceTenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $oldProductId = (int) ($row['product_id'] ?? 0);
            $oldIngredientId = (int) ($row['ingredient_id'] ?? 0);
            $newProductId = (int) ($productMap[$oldProductId] ?? 0);
            $newIngredientId = (int) ($ingredientMap[$oldIngredientId] ?? 0);
            if ($newProductId < 1 || $newIngredientId < 1) {
                continue;
            }
            $exists = $this->pdo->prepare(
                'SELECT id FROM product_ingredients WHERE tenant_id = ? AND product_id = ? AND ingredient_id = ? LIMIT 1'
            );
            $exists->execute([$targetTenantId, $newProductId, $newIngredientId]);
            if ($exists->fetch(PDO::FETCH_ASSOC)) {
                continue;
            }
            $this->pdo->prepare(
                'INSERT INTO product_ingredients
                 (tenant_id, product_id, ingredient_id, quantity_required, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NOW(), NOW())'
            )->execute([
                $targetTenantId,
                $newProductId,
                $newIngredientId,
                (float) ($row['quantity_required'] ?? 0),
            ]);
        }
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = (string) preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');

        return substr($slug, 0, 120);
    }

    private function ensureUniqueTenantSlug(string $preferred): string
    {
        $base = $this->sanitizeSlug($preferred);
        if ($base === '') {
            $base = 'branch';
        }
        $slug = $base;
        $i = 1;
        while (true) {
            $st = $this->pdo->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
            $st->execute([$slug]);
            if (! $st->fetch(PDO::FETCH_ASSOC)) {
                return $slug;
            }
            $i++;
            $slug = substr($base, 0, 110).'-'.$i;
        }
    }

    private function ensureUniqueName(string $table, int $tenantId, string $baseName): string
    {
        $name = trim($baseName);
        if ($name === '') {
            $name = 'Item';
        }
        $try = $name;
        $i = 1;
        while (true) {
            $st = $this->pdo->prepare("SELECT id FROM {$table} WHERE tenant_id = ? AND name = ? LIMIT 1");
            $st->execute([$tenantId, $try]);
            if (! $st->fetch(PDO::FETCH_ASSOC)) {
                return $try;
            }
            $i++;
            $try = substr($name, 0, 230).' (Copy '.$i.')';
        }
    }

    private function hasProductImagePathColumn(): bool
    {
        if (self::$hasProductImagePathColumn !== null) {
            return self::$hasProductImagePathColumn;
        }
        try {
            $st = $this->pdo->query("SHOW COLUMNS FROM `products` LIKE 'image_path'");
            self::$hasProductImagePathColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasProductImagePathColumn = false;
        }

        return self::$hasProductImagePathColumn;
    }

    private function ensureSchema(): void
    {
        $this->ensureTenantColumn(
            'parent_tenant_id',
            'ALTER TABLE tenants ADD COLUMN parent_tenant_id BIGINT UNSIGNED NULL AFTER id'
        );
        $this->ensureTenantColumn(
            'branch_group_id',
            'ALTER TABLE tenants ADD COLUMN branch_group_id BIGINT UNSIGNED NULL AFTER parent_tenant_id'
        );
        $this->ensureTenantColumn(
            'is_main_branch',
            'ALTER TABLE tenants ADD COLUMN is_main_branch TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active'
        );
        $this->ensureTenantColumn(
            'max_branches',
            'ALTER TABLE tenants ADD COLUMN max_branches INT UNSIGNED NULL DEFAULT NULL AFTER paid_amount'
        );

        $this->ensureTenantIndex('tenants_parent_tenant_id_index', 'ALTER TABLE tenants ADD INDEX tenants_parent_tenant_id_index (parent_tenant_id)');
        $this->ensureTenantIndex('tenants_branch_group_id_index', 'ALTER TABLE tenants ADD INDEX tenants_branch_group_id_index (branch_group_id)');
        $this->ensureTenantIndex('tenants_branch_group_active_index', 'ALTER TABLE tenants ADD INDEX tenants_branch_group_active_index (branch_group_id, is_active)');
        $this->ensureTenantIndex('tenants_branch_group_main_index', 'ALTER TABLE tenants ADD INDEX tenants_branch_group_main_index (branch_group_id, is_main_branch)');

        $this->ensureTenantForeignKey(
            'tenants_parent_tenant_id_fk',
            'ALTER TABLE tenants ADD CONSTRAINT tenants_parent_tenant_id_fk FOREIGN KEY (parent_tenant_id) REFERENCES tenants(id) ON DELETE SET NULL'
        );
        $this->ensureTenantForeignKey(
            'tenants_branch_group_id_fk',
            'ALTER TABLE tenants ADD CONSTRAINT tenants_branch_group_id_fk FOREIGN KEY (branch_group_id) REFERENCES tenants(id) ON DELETE SET NULL'
        );

        $this->pdo->exec(
            'UPDATE tenants
             SET parent_tenant_id = COALESCE(parent_tenant_id, id),
                 branch_group_id = COALESCE(branch_group_id, id),
                 max_branches = COALESCE(max_branches, 1),
                 updated_at = NOW()
             WHERE parent_tenant_id IS NULL OR branch_group_id IS NULL OR max_branches IS NULL'
        );

        // Keep existing main-branch selection.
        // Only assign a fallback main branch for groups that currently have none.
        $this->pdo->exec(
            'UPDATE tenants t
             INNER JOIN (
               SELECT z.branch_group_id, MIN(z.id) AS min_id
               FROM tenants z
               LEFT JOIN (
                 SELECT branch_group_id, SUM(CASE WHEN is_main_branch = 1 THEN 1 ELSE 0 END) AS main_count
                 FROM tenants
                 WHERE branch_group_id IS NOT NULL
                 GROUP BY branch_group_id
               ) c ON c.branch_group_id = z.branch_group_id
               WHERE z.branch_group_id IS NOT NULL
                 AND COALESCE(c.main_count, 0) = 0
               GROUP BY z.branch_group_id
             ) x ON x.branch_group_id = t.branch_group_id
             SET t.is_main_branch = CASE WHEN t.id = x.min_id THEN 1 ELSE t.is_main_branch END'
        );
    }

    private function ensureTenantColumn(string $column, string $alterSql): void
    {
        try {
            $st = $this->pdo->query("SHOW COLUMNS FROM `tenants` LIKE '{$column}'");
            $exists = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
            if ($exists) {
                return;
            }
            $this->pdo->exec($alterSql);
        } catch (\Throwable) {
            // ignore missing privileges
        }
    }

    private function ensureTenantIndex(string $indexName, string $alterSql): void
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
                 LIMIT 1'
            );
            $st->execute(['tenants', $indexName]);
            if ($st->fetch(PDO::FETCH_ASSOC)) {
                return;
            }
            $this->pdo->exec($alterSql);
        } catch (\Throwable) {
            // ignore missing privileges
        }
    }

    private function ensureTenantForeignKey(string $name, string $alterSql): void
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = \'FOREIGN KEY\'
                 LIMIT 1'
            );
            $st->execute(['tenants', $name]);
            if ($st->fetch(PDO::FETCH_ASSOC)) {
                return;
            }
            $this->pdo->exec($alterSql);
        } catch (\Throwable) {
            // ignore missing privileges
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                 LIMIT 1'
            );
            $st->execute([$table]);

            return $st->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
