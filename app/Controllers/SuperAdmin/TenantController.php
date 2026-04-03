<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class TenantController
{
    /** Stored in DB `plan` column; not shown in UI (subscription-only product). */
    private const INTERNAL_PLAN_LABEL = 'subscription';

    private static function ensurePaidAmountColumn(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'paid_amount'");
            if ($chk !== false && $chk->fetch()) {
                return;
            }
            $pdo->exec('ALTER TABLE tenants ADD COLUMN paid_amount DECIMAL(12,2) NULL DEFAULT NULL');
        } catch (\Throwable) {
            // Column may already exist or no ALTER privilege
        }
    }

    private static function ensureBranchColumns(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $checks = [
                'parent_tenant_id' => 'ALTER TABLE tenants ADD COLUMN parent_tenant_id BIGINT UNSIGNED NULL AFTER id',
                'branch_group_id' => 'ALTER TABLE tenants ADD COLUMN branch_group_id BIGINT UNSIGNED NULL AFTER parent_tenant_id',
                'is_main_branch' => 'ALTER TABLE tenants ADD COLUMN is_main_branch TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
                'max_branches' => 'ALTER TABLE tenants ADD COLUMN max_branches INT UNSIGNED NULL DEFAULT NULL AFTER paid_amount',
            ];
            foreach ($checks as $col => $sql) {
                $chk = $pdo->query("SHOW COLUMNS FROM tenants LIKE '{$col}'");
                if ($chk !== false && $chk->fetch()) {
                    continue;
                }
                $pdo->exec($sql);
            }
            $pdo->exec('UPDATE tenants SET parent_tenant_id = COALESCE(parent_tenant_id, id), branch_group_id = COALESCE(branch_group_id, id), max_branches = COALESCE(max_branches, 1)');
            // Do not override user-selected main branch.
            // Only set a fallback when a group has no main branch yet.
            $pdo->exec(
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
        } catch (\Throwable) {
            // ignore no alter privilege
        }
    }

    public function index(Request $request): Response
    {
        $pdo = App::db();

        if ($request->ajax() || $request->boolean('datatable')) {
            self::ensurePaidAmountColumn($pdo);
            self::ensureBranchColumns($pdo);
            $search = trim((string) ($request->input('search')['value'] ?? ''));
            $join = ' FROM tenants t
                      LEFT JOIN users u ON u.tenant_id = t.id AND u.role = \'tenant_admin\'
                      LEFT JOIN tenants mb ON mb.branch_group_id = t.branch_group_id AND mb.is_main_branch = 1 ';
            $where = '';
            $params = [];
            if ($search !== '') {
                $where = ' WHERE (CAST(t.id AS CHAR) LIKE ? OR t.name LIKE ? OR t.slug LIKE ? OR mb.name LIKE ? OR u.email LIKE ? OR CAST(IFNULL(t.paid_amount, 0) AS CHAR) LIKE ?)';
                $like = '%'.$search.'%';
                $params = [$like, $like, $like, $like, $like, $like];
            }

            $total = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
            $st = $pdo->prepare('SELECT COUNT(DISTINCT t.id)'.$join.$where);
            $st->execute($params);
            $filtered = (int) $st->fetchColumn();

            $orderIdx = (int) ($request->input('order')[0]['column'] ?? 1);
            $orderDir = strtolower((string) ($request->input('order')[0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $orderColumnMap = [
                1 => 't.id',
                2 => 't.name',
                3 => 't.slug',
                4 => 'mb.name',
                5 => 't.paid_amount',
                6 => 't.license_starts_at',
                7 => 't.license_expires_at',
                8 => 'u.email',
                9 => 't.is_active',
            ];
            $orderBy = $orderColumnMap[$orderIdx] ?? 't.id';

            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 25)));

            $sql = 'SELECT t.*, u.email AS owner_email, mb.name AS main_branch_name'.$join.$where." ORDER BY $orderBy $orderDir LIMIT $length OFFSET $start";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($rows as $t) {
                $id = (int) $t['id'];
                $isActive = ! empty($t['is_active']);
                $toggleUrl = url('/super-admin/tenants/'.$id.'/toggle-active');
                $toggleTitle = $isActive ? 'Deactivate store' : 'Activate store';
                $toggleIcon = $isActive ? 'fa-toggle-on' : 'fa-toggle-off';
                $toggleBtnClass = $isActive ? 'btn-outline-success' : 'btn-outline-secondary';

                $formId = 'tenant-sub-exp-'.$id;
                $patchUrl = url('/super-admin/tenants/'.$id);

                $startsRaw = $t['license_starts_at'] ?? null;
                $expiresRaw = $t['license_expires_at'] ?? null;
                $startsDisplay = $startsRaw
                    ? e(date('M j, Y', strtotime((string) $startsRaw)))
                    : '<span class="text-muted">—</span>';
                $expiresVal = $expiresRaw ? date('Y-m-d', strtotime((string) $expiresRaw)) : '';
                $isExpired = false;
                if ($expiresRaw) {
                    $expTs = strtotime((string) $expiresRaw);
                    $isExpired = $expTs !== false && date('Y-m-d', $expTs) < date('Y-m-d');
                }
                $expiresDisplay = $expiresRaw
                    ? e(date('M j, Y', strtotime((string) $expiresRaw)))
                    : '<span class="text-muted">—</span>';
                if ($isExpired) {
                    $expiresDisplay .= ' <span class="badge text-bg-danger ms-1">Expired</span>';
                }

                $expiresField = '<span class="tenant-sub-exp-readonly">'.$expiresDisplay.'</span>';

                $ownerEmail = trim((string) ($t['owner_email'] ?? ''));
                $ownerCell = $ownerEmail !== ''
                    ? '<span class="text-break">'.e($ownerEmail).'</span>'
                    : '<span class="text-muted">—</span>';

                $subExpBlock = '<div class="tenant-sub-exp-wrap" data-tenant-id="'.$id.'">'
                    .'<div class="tenant-sub-exp-view d-inline-flex flex-wrap align-items-center gap-1">'
                    .'<button type="button" class="btn btn-sm btn-outline-secondary btn-edit-sub-exp px-2" title="Edit subscription end date" aria-label="Edit subscription end date">'
                    .'<i class="fa-solid fa-pen" aria-hidden="true"></i></button>'
                    .'</div>'
                    .'<form id="'.e($formId).'" method="POST" action="'.e($patchUrl).'" class="tenant-sub-exp-edit d-none" autocomplete="off">'
                    .csrf_field()
                    .method_field('PATCH')
                    .'<div class="d-flex flex-wrap align-items-center gap-1">'
                    .'<input type="date" id="tenant-expires-'.$id.'" name="license_expires_at" class="form-control form-control-sm" value="'.e($expiresVal).'" aria-label="Subscription ends" style="width:auto;min-width:10.5rem">'
                    .'<button type="submit" class="btn btn-sm btn-primary px-2" title="Save subscription end date" aria-label="Save subscription end date"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></button>'
                    .'<button type="button" class="btn btn-sm btn-outline-secondary btn-cancel-sub-exp px-2" title="Cancel" aria-label="Cancel"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>'
                    .'</div>'
                    .'</form>'
                    .'</div>';

                $actions = '<div class="d-flex flex-wrap gap-1 justify-content-end align-items-center">'
                    .'<a href="'.e(route('super-admin.tenants.branches.index', ['id' => $id])).'" class="btn btn-sm btn-outline-info px-2" title="Manage branches" aria-label="Manage branches"><i class="fa-solid fa-code-branch"></i></a>'
                    .'<a href="'.e(route('super-admin.tenants.backups.index', ['id' => $id])).'" class="btn btn-sm btn-outline-primary px-2" title="Backups and restore" aria-label="Backups and restore"><i class="fa-solid fa-database"></i></a>'
                    .'<button type="button" class="btn btn-sm btn-outline-warning px-2 btn-reset-owner-pwd" data-tenant-id="'.$id.'" data-bs-toggle="modal" data-bs-target="#resetOwnerPasswordModal" title="Reset store owner password" aria-label="Reset store owner password"><i class="fa-solid fa-key"></i></button>'
                    .'<form method="POST" action="'.e($toggleUrl).'" class="d-inline">'
                    .csrf_field()
                    .'<button type="submit" class="btn btn-sm '.$toggleBtnClass.' px-2" title="'.e($toggleTitle).'" aria-label="'.e($toggleTitle).'"><i class="fa-solid '.$toggleIcon.'"></i></button></form>'
                    .$subExpBlock
                    .'</div>';

                $status = $isActive
                    ? '<span class="badge text-bg-success">Active</span>'
                    : '<span class="badge text-bg-secondary">Inactive</span>';

                $paidRaw = $t['paid_amount'] ?? null;
                $paidCell = ($paidRaw !== null && $paidRaw !== '')
                    ? '<span class="text-nowrap">'.e(number_format((float) $paidRaw, 2)).'</span>'
                    : '<span class="text-muted">—</span>';
                $mainBranchName = trim((string) ($t['main_branch_name'] ?? ''));
                $branchDetails = (bool) ($t['is_main_branch'] ?? false)
                    ? '<span class="badge text-bg-primary">Main branch</span>'
                    : '<span class="small">Belongs to: <strong>'.e($mainBranchName !== '' ? $mainBranchName : 'Main branch').'</strong></span>';

                $data[] = [
                    'id' => $id,
                    'name' => e((string) $t['name']),
                    'slug' => '<span class="text-body-secondary">'.e((string) $t['slug']).'</span>',
                    'branch_details' => $branchDetails,
                    'paid_amount' => $paidCell,
                    'starts' => $startsDisplay,
                    'expires' => $expiresField,
                    'owner_email' => $ownerCell,
                    'status' => $status,
                    'actions' => $actions,
                ];
            }

            return json_response([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $data,
            ]);
        }

        return view_page('Tenants', 'super-admin.tenants.index');
    }

    public function store(Request $request): Response
    {
        $name = trim((string) $request->input('name'));
        $slug = strtolower(trim((string) $request->input('slug')));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');
        $expires = trim((string) $request->input('license_expires_at'));
        $paidRaw = trim((string) $request->input('paid_amount'));
        $ownerName = trim((string) $request->input('owner_name'));
        $ownerEmail = strtolower(trim((string) $request->input('owner_email')));
        $ownerPassword = (string) $request->input('owner_password');

        $errors = [];
        if ($name === '' || strlen($name) > 255) {
            $errors[] = 'Store name is required.';
        }
        if ($slug === '' || strlen($slug) > 100) {
            $errors[] = 'URL slug is required (use letters, numbers, and hyphens).';
        }
        if ($ownerName === '' || strlen($ownerName) > 255) {
            $errors[] = 'Store owner name is required.';
        }
        if (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid store owner email is required.';
        }
        if (strlen($ownerPassword) < 8) {
            $errors[] = 'Store owner password must be at least 8 characters.';
        }

        $paidAmount = null;
        if ($paidRaw !== '') {
            if (! is_numeric($paidRaw) || (float) $paidRaw < 0) {
                $errors[] = 'Paid amount must be a valid number zero or greater.';
            } else {
                $paidAmount = round((float) $paidRaw, 2);
            }
        }

        $pdo = App::db();
        self::ensurePaidAmountColumn($pdo);
        self::ensureBranchColumns($pdo);
        $st = $pdo->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        if ($st->fetch()) {
            $errors[] = 'Slug already exists.';
        }
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $st->execute([$ownerEmail]);
        if ($st->fetch()) {
            $errors[] = 'That owner email is already registered.';
        }

        if ($errors !== []) {
            session_flash('errors', $errors);

            return redirect(url('/super-admin/tenants'));
        }

        $now = date('Y-m-d H:i:s');
        $hash = password_hash($ownerPassword, PASSWORD_BCRYPT);

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO tenants (parent_tenant_id, branch_group_id, name, slug, plan, is_active, is_main_branch, license_starts_at, license_expires_at, paid_amount, max_branches, created_at, updated_at)
                 VALUES (NULL, NULL, ?, ?, ?, 1, 1, NOW(), ?, ?, 1, NOW(), NOW())'
            )->execute([$name, $slug, self::INTERNAL_PLAN_LABEL, $expires !== '' ? $expires : null, $paidAmount]);

            $tenantId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE tenants SET parent_tenant_id = ?, branch_group_id = ? WHERE id = ?')
                ->execute([$tenantId, $tenantId, $tenantId]);
            if (Auth::hasModulePermissionsColumn()) {
                $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, tenant_id, module_permissions, email_verified_at, created_at, updated_at)
                     VALUES (?, ?, ?, \'tenant_admin\', ?, NULL, ?, ?, ?)'
                )->execute([$ownerName, $ownerEmail, $hash, $tenantId, $now, $now, $now]);
            } else {
                $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, tenant_id, email_verified_at, created_at, updated_at)
                     VALUES (?, ?, ?, \'tenant_admin\', ?, ?, ?, ?)'
                )->execute([$ownerName, $ownerEmail, $hash, $tenantId, $now, $now, $now]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            session_flash('errors', ['Could not create tenant and owner.']);

            return redirect(url('/super-admin/tenants'));
        }

        session_flash('status', 'Tenant created with store owner account.');

        return redirect(url('/super-admin/tenants'));
    }

    public function toggleActive(Request $request, string $id): Response
    {
        $tid = (int) $id;
        $pdo = App::db();
        $st = $pdo->prepare('SELECT is_active FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$tid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            session_flash('errors', ['Tenant not found.']);

            return redirect(url('/super-admin/tenants'));
        }
        $new = ! (bool) $row['is_active'];
        $pdo->prepare('UPDATE tenants SET is_active = ?, updated_at = NOW() WHERE id = ?')->execute([$new ? 1 : 0, $tid]);
        session_flash('status', $new ? 'Store activated.' : 'Store deactivated.');

        return redirect(url('/super-admin/tenants'));
    }

    public function update(Request $request, string $id): Response
    {
        $tid = (int) $id;
        $expires = trim((string) $request->input('license_expires_at'));

        $errors = [];
        if ($expires !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) {
            $errors[] = 'Invalid subscription end date.';
        }

        if ($errors !== []) {
            session_flash('errors', $errors);

            return redirect(url('/super-admin/tenants'));
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT id FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$tid]);
        if (! $st->fetch()) {
            session_flash('errors', ['Store not found.']);

            return redirect(url('/super-admin/tenants'));
        }

        $pdo->prepare(
            'UPDATE tenants SET license_expires_at = ?, updated_at = NOW() WHERE id = ?'
        )->execute([
            $expires !== '' ? $expires : null,
            $tid,
        ]);

        session_flash('status', 'Subscription end date updated.');

        return redirect(url('/super-admin/tenants'));
    }

    public function resetOwnerPassword(Request $request, string $id): Response
    {
        $tid = (int) $id;
        $pass = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');

        $errors = [];
        if (strlen($pass) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($pass !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            session_flash('errors', $errors);

            return redirect(url('/super-admin/tenants'));
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND role = ? LIMIT 1');
        $st->execute([$tid, 'tenant_admin']);
        $owner = $st->fetch(PDO::FETCH_ASSOC);
        if (! $owner) {
            session_flash('errors', ['No store owner account found for this tenant.']);

            return redirect(url('/super-admin/tenants'));
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?')->execute([$hash, (int) $owner['id']]);

        session_flash('status', 'Store owner password has been reset. Share the new password with them securely; they can change it under Profile after login.');

        return redirect(url('/super-admin/tenants'));
    }
}
