<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\LaundrySchema;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class TenantController
{
    /** Stored in DB `plan` column; not shown in UI (subscription-only product). */
    private const INTERNAL_PLAN_LABEL = 'subscription';
    /** @var list<int> */
    private const SUBSCRIPTION_MONTH_OPTIONS = [1, 3, 6, 12];
    private const FREE_ACCESS_PLAN_CODE = 'free_access';
    private const FREE_PLAN_CODES = ['trial', 'free', 'free_trial', self::FREE_ACCESS_PLAN_CODE];
    private const FREE_TRIAL_DAYS = 7;
    private static ?bool $usersLastLoginColumnExists = null;

    private static function planCodeFromMonths(int $months): string
    {
        if (! in_array($months, self::SUBSCRIPTION_MONTH_OPTIONS, true)) {
            return self::INTERNAL_PLAN_LABEL;
        }

        return 'subscription_'.$months.'m';
    }

    private static function planLabelFromCode(string $planCode): string
    {
        $v = strtolower(trim($planCode));
        if (self::isFreePlanCode($v)) {
            return 'Free';
        }
        $months = self::monthsFromPlanCode($v);
        if ($months !== null) {
            return self::planLabelFromMonths($months);
        }

        return 'Custom';
    }

    private static function monthsFromPlanCode(string $plan): ?int
    {
        if (preg_match('/^subscription_(\d+)m$/', strtolower(trim($plan)), $m)) {
            $months = (int) ($m[1] ?? 0);
            if (in_array($months, self::SUBSCRIPTION_MONTH_OPTIONS, true)) {
                return $months;
            }
        }

        return null;
    }

    private static function isFreePlanCode(string $plan): bool
    {
        return in_array(strtolower(trim($plan)), self::FREE_PLAN_CODES, true);
    }

    private static function planLabelFromMonths(?int $months): string
    {
        if ($months === null) {
            return 'Custom';
        }

        return $months.' month'.($months === 1 ? '' : 's');
    }

    /** @return array{starts_at:string,expires_at:string} */
    private static function computeSubscriptionWindow(int $months): array
    {
        if (! in_array($months, self::SUBSCRIPTION_MONTH_OPTIONS, true)) {
            throw new \InvalidArgumentException('Invalid subscription duration.');
        }
        $startsAt = new \DateTimeImmutable('now');
        $expiresAt = $startsAt->modify('+'.$months.' months');
        if (! $expiresAt instanceof \DateTimeImmutable) {
            throw new \RuntimeException('Could not compute subscription expiry date.');
        }

        return [
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array{starts_at:string,expires_at:string} */
    private static function computeFreeTrialWindow(): array
    {
        $startsAt = new \DateTimeImmutable('now');
        $expiresAt = $startsAt->modify('+'.self::FREE_TRIAL_DAYS.' days');
        if (! $expiresAt instanceof \DateTimeImmutable) {
            throw new \RuntimeException('Could not compute free trial expiry date.');
        }

        return [
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ];
    }

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
            $pdo->exec('ALTER TABLE tenants ADD COLUMN paid_amount DECIMAL(38,16) NULL DEFAULT NULL');
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

    private static function hasUsersLastLoginColumn(PDO $pdo): bool
    {
        if (self::$usersLastLoginColumnExists === true) {
            return true;
        }
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'last_login_at'");
            $exists = $chk !== false && $chk->fetch(PDO::FETCH_ASSOC) !== false;
            if ($exists) {
                self::$usersLastLoginColumnExists = true;
            }

            return $exists;
        } catch (\Throwable) {
            return false;
        }
    }

    public function index(Request $request): Response
    {
        $pdo = App::db();

        if ($request->ajax() || $request->boolean('datatable')) {
            self::ensurePaidAmountColumn($pdo);
            self::ensureBranchColumns($pdo);
            $hasUsersLastLoginColumn = self::hasUsersLastLoginColumn($pdo);
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
                5 => 't.plan',
                6 => 't.paid_amount',
                7 => 't.license_starts_at',
                8 => 't.license_expires_at',
                9 => 'u.email',
                10 => $hasUsersLastLoginColumn ? 'u.last_login_at' : 'u.created_at',
                11 => 't.is_active',
            ];
            $orderBy = $orderColumnMap[$orderIdx] ?? 't.id';

            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 25)));

            $ownerLastLoginSelect = $hasUsersLastLoginColumn ? 'u.last_login_at AS owner_last_login' : 'NULL AS owner_last_login';
            $sql = 'SELECT t.*, u.email AS owner_email, '.$ownerLastLoginSelect.', mb.name AS main_branch_name'.$join.$where." ORDER BY $orderBy $orderDir LIMIT $length OFFSET $start";
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

                $patchUrl = url('/super-admin/tenants/'.$id);
                $deleteUrl = url('/super-admin/tenants/'.$id);

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

                $expiresField = '<span>'.$expiresDisplay.'</span>';
                $planRaw = trim((string) ($t['plan'] ?? ''));
                $planMonths = self::monthsFromPlanCode($planRaw);
                if ($planMonths === null && $startsRaw && $expiresRaw) {
                    $startTs = strtotime((string) $startsRaw);
                    $expTs = strtotime((string) $expiresRaw);
                    if ($startTs !== false && $expTs !== false && $expTs >= $startTs) {
                        $days = (int) floor(($expTs - $startTs) / 86400);
                        if ($days <= 45) {
                            $planMonths = 1;
                        } elseif ($days <= 120) {
                            $planMonths = 3;
                        } elseif ($days <= 240) {
                            $planMonths = 6;
                        } else {
                            $planMonths = 12;
                        }
                    }
                }
                $isFreePlan = self::isFreePlanCode($planRaw);
                $planLabel = self::planLabelFromCode($planRaw);
                $planCell = '<span class="badge text-bg-info">'.e($planLabel).'</span>';

                $ownerEmail = trim((string) ($t['owner_email'] ?? ''));
                $ownerCell = $ownerEmail !== ''
                    ? '<span class="text-break">'.e($ownerEmail).'</span>'
                    : '<span class="text-muted">—</span>';
                $lastLoginRaw = trim((string) ($t['owner_last_login'] ?? ''));
                if ($lastLoginRaw !== '') {
                    $lastLoginTs = strtotime($lastLoginRaw);
                    if ($lastLoginTs !== false) {
                        $daysSinceLastLogin = (int) floor((time() - $lastLoginTs) / 86400);
                        if ($daysSinceLastLogin <= 7) {
                            $activityBadge = '<span class="badge text-bg-success ms-1">Active</span>';
                        } elseif ($daysSinceLastLogin <= 30) {
                            $activityBadge = '<span class="badge text-bg-warning ms-1">Needs follow-up</span>';
                        } else {
                            $activityBadge = '<span class="badge text-bg-danger ms-1">Inactive (30+ days)</span>';
                        }
                        $lastLoginCell = '<span class="text-nowrap">'.e(date('M j, Y g:i A', $lastLoginTs)).'</span>'.$activityBadge;
                    } else {
                        $lastLoginCell = '<span class="text-muted">Never</span>';
                    }
                } else {
                    $lastLoginCell = '<span class="text-muted">Never</span>';
                }

                $editBtn = '<button type="button" class="btn btn-sm btn-outline-secondary px-2 btn-edit-tenant" '
                    .'data-tenant-id="'.$id.'" '
                    .'data-tenant-name="'.e((string) ($t['name'] ?? '')).'" '
                    .'data-tenant-plan-months="'.($planMonths ?? '').'" '
                    .'data-tenant-plan-code="'.e($isFreePlan ? self::FREE_ACCESS_PLAN_CODE : $planRaw).'" '
                    .'data-tenant-expires="'.e($expiresVal).'" '
                    .'title="Edit store" aria-label="Edit store"><i class="fa-solid fa-pen-to-square"></i></button>';
                $deleteForm = '<form method="POST" action="'.e($deleteUrl).'" class="d-inline js-delete-tenant-form" data-tenant-name="'.e((string) ($t['name'] ?? 'Store')).'">'
                    .csrf_field()
                    .method_field('DELETE')
                    .'<input type="hidden" name="delete_confirmation" value="">'
                    .'<button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Delete store" aria-label="Delete store"><i class="fa-solid fa-trash"></i></button>'
                    .'</form>';

                $actions = '<div class="d-flex flex-wrap gap-1 justify-content-end align-items-center">'
                    .'<a href="'.e(route('super-admin.tenants.branches.index', ['id' => $id])).'" class="btn btn-sm btn-outline-info px-2" title="Manage branches" aria-label="Manage branches"><i class="fa-solid fa-code-branch"></i></a>'
                    .'<a href="'.e(route('super-admin.tenants.backups.index', ['id' => $id])).'" class="btn btn-sm btn-outline-primary px-2" title="Backups and restore" aria-label="Backups and restore"><i class="fa-solid fa-database"></i></a>'
                    .'<button type="button" class="btn btn-sm btn-outline-warning px-2 btn-reset-owner-pwd" data-tenant-id="'.$id.'" data-bs-toggle="modal" data-bs-target="#resetOwnerPasswordModal" title="Reset store owner password" aria-label="Reset store owner password"><i class="fa-solid fa-key"></i></button>'
                    .'<form method="POST" action="'.e($toggleUrl).'" class="d-inline">'
                    .csrf_field()
                    .'<button type="submit" class="btn btn-sm '.$toggleBtnClass.' px-2" title="'.e($toggleTitle).'" aria-label="'.e($toggleTitle).'"><i class="fa-solid '.$toggleIcon.'"></i></button></form>'
                    .$editBtn
                    .$deleteForm
                    .'</div>';

                $status = $isActive
                    ? '<span class="badge text-bg-success">Active</span>'
                    : '<span class="badge text-bg-secondary">Inactive</span>';

                $paidRaw = $t['paid_amount'] ?? null;
                $paidCell = ($paidRaw !== null && $paidRaw !== '')
                    ? '<span class="text-nowrap">'.e(format_money((float) $paidRaw)).'</span>'
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
                    'plan' => $planCell,
                    'paid_amount' => $paidCell,
                    'starts' => $startsDisplay,
                    'expires' => $expiresField,
                    'owner_email' => $ownerCell,
                    'last_login' => $lastLoginCell,
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
        $planInput = strtolower(trim((string) $request->input('subscription_plan', '')));
        $months = (int) $request->input('subscription_months', 0);
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
        if ($planInput === self::FREE_ACCESS_PLAN_CODE) {
            $months = 0;
        } elseif (! in_array($months, self::SUBSCRIPTION_MONTH_OPTIONS, true)) {
            $errors[] = 'Subscription duration is required.';
        }

        $paidAmount = null;
        if ($paidRaw !== '') {
            if (! is_numeric($paidRaw) || (float) $paidRaw < 0) {
                $errors[] = 'Paid amount must be a valid number zero or greater.';
            } else {
                $paidAmount = round_money((float) $paidRaw);
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

        try {
            $window = $planInput === self::FREE_ACCESS_PLAN_CODE
                ? self::computeFreeTrialWindow()
                : self::computeSubscriptionWindow($months);
        } catch (\Throwable) {
            session_flash('errors', ['Could not calculate subscription end date.']);

            return redirect(url('/super-admin/tenants'));
        }

        $now = date('Y-m-d H:i:s');
        $hash = password_hash($ownerPassword, PASSWORD_BCRYPT);
        $planCode = $planInput === self::FREE_ACCESS_PLAN_CODE ? self::FREE_ACCESS_PLAN_CODE : self::planCodeFromMonths($months);

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO tenants (parent_tenant_id, branch_group_id, name, slug, plan, is_active, is_main_branch, license_starts_at, license_expires_at, paid_amount, max_branches, created_at, updated_at)
                 VALUES (NULL, NULL, ?, ?, ?, 1, 1, ?, ?, ?, 1, NOW(), NOW())'
            )->execute([$name, $slug, $planCode, $window['starts_at'], $window['expires_at'], $paidAmount]);

            $tenantId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE tenants SET parent_tenant_id = ?, branch_group_id = ? WHERE id = ?')
                ->execute([$tenantId, $tenantId, $tenantId]);
            LaundrySchema::ensureDefaultInventoryForTenant($pdo, $tenantId);
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
        $actor = Auth::user();
        $actorId = (int) ($actor['id'] ?? 0);
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
        ActivityLogger::log(
            null,
            $actorId,
            (string) ($actor['role'] ?? 'super_admin'),
            'tenants',
            'toggle_active',
            $request,
            'Toggled store active status.',
            ['tenant_id' => $tid, 'is_active' => $new ? 1 : 0]
        );
        session_flash('status', $new ? 'Store activated.' : 'Store deactivated.');

        return redirect(url('/super-admin/tenants'));
    }

    public function update(Request $request, string $id): Response
    {
        $tid = (int) $id;
        $name = trim((string) $request->input('name'));
        $monthsRaw = trim((string) $request->input('subscription_months'));
        $months = $monthsRaw !== '' ? (int) $monthsRaw : null;
        $planInput = strtolower(trim((string) $request->input('subscription_plan', '')));
        $expires = trim((string) $request->input('license_expires_at'));
        $originalExpires = trim((string) $request->input('original_license_expires_at'));

        $errors = [];
        if ($expires !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) {
            $errors[] = 'Invalid subscription end date.';
        }
        if ($planInput !== self::FREE_ACCESS_PLAN_CODE && $months !== null && ! in_array($months, self::SUBSCRIPTION_MONTH_OPTIONS, true)) {
            $errors[] = 'Invalid subscription plan duration.';
        }
        if ($name !== '' && strlen($name) > 255) {
            $errors[] = 'Store name must be 255 characters or less.';
        }

        if ($errors !== []) {
            session_flash('errors', $errors);

            return redirect(url('/super-admin/tenants'));
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT id, license_expires_at FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$tid]);
        $tenantRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (! $tenantRow) {
            session_flash('errors', ['Store not found.']);

            return redirect(url('/super-admin/tenants'));
        }
        $existingExpiresRaw = trim((string) ($tenantRow['license_expires_at'] ?? ''));
        $manualEndDateOverride = ($expires !== '' && $expires !== $originalExpires);

        if ($planInput === self::FREE_ACCESS_PLAN_CODE) {
            try {
                $window = self::computeFreeTrialWindow();
            } catch (\Throwable) {
                session_flash('errors', ['Could not calculate free trial end date.']);

                return redirect(url('/super-admin/tenants'));
            }
            $effectiveExpires = $manualEndDateOverride
                ? ($expires.' 23:59:59')
                : $window['expires_at'];
            $pdo->prepare(
                'UPDATE tenants
                 SET name = COALESCE(?, name),
                     plan = ?,
                     license_expires_at = ?,
                     updated_at = NOW()
                 WHERE id = ?'
            )->execute([
                $name !== '' ? $name : null,
                self::FREE_ACCESS_PLAN_CODE,
                $effectiveExpires,
                $tid,
            ]);
            session_flash('status', 'Store details updated.');
        } elseif ($months !== null) {
            $window = self::computeSubscriptionWindow($months);
            $effectiveExpires = $window['expires_at'];
            if ($manualEndDateOverride) {
                $effectiveExpires = $expires.' 23:59:59';
            }
            $params = [
                $name !== '' ? $name : null,
                self::planCodeFromMonths($months),
                $effectiveExpires,
                $tid,
            ];
            $pdo->prepare(
                'UPDATE tenants
                 SET name = COALESCE(?, name),
                     plan = ?,
                     license_expires_at = ?,
                     updated_at = NOW()
                 WHERE id = ?'
            )->execute($params);
            session_flash('status', 'Store details updated.');
        } else {
            $pdo->prepare(
                'UPDATE tenants
                 SET name = COALESCE(?, name),
                     license_expires_at = COALESCE(?, license_expires_at),
                     updated_at = NOW()
                 WHERE id = ?'
            )->execute([
                $name !== '' ? $name : null,
                $expires !== '' ? ($expires.' 23:59:59') : ($existingExpiresRaw !== '' ? $existingExpiresRaw : null),
                $tid,
            ]);
            session_flash('status', 'Subscription end date updated.');
        }

        return redirect(url('/super-admin/tenants'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $tid = (int) $id;
        $actor = Auth::user();
        $actorId = (int) ($actor['id'] ?? 0);
        $pdo = App::db();
        self::ensureBranchColumns($pdo);
        $st = $pdo->prepare('SELECT id, branch_group_id, is_main_branch FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$tid]);
        $tenant = $st->fetch(PDO::FETCH_ASSOC);
        if (! $tenant) {
            session_flash('errors', ['Store not found.']);
            return redirect(url('/super-admin/tenants'));
        }
        $stName = $pdo->prepare('SELECT name FROM tenants WHERE id = ? LIMIT 1');
        $stName->execute([$tid]);
        $tenantName = trim((string) ($stName->fetchColumn() ?: 'STORE'));
        $requiredPhrase = 'DELETE '.strtoupper($tenantName);
        $typedConfirmation = strtoupper(trim((string) $request->input('delete_confirmation', '')));
        if ($typedConfirmation !== $requiredPhrase) {
            session_flash('errors', ["Type {$requiredPhrase} to confirm permanent store deletion."]);
            return redirect(url('/super-admin/tenants'));
        }
        $groupId = (int) ($tenant['branch_group_id'] ?? 0);
        if ($groupId > 0) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE branch_group_id = ?');
            $st->execute([$groupId]);
            $count = (int) $st->fetchColumn();
            if ($count > 1 && (bool) ($tenant['is_main_branch'] ?? false)) {
                session_flash('errors', ['Set another branch as main before deleting this store.']);
                return redirect(url('/super-admin/tenants'));
            }
        }
        try {
            $pdo->beginTransaction();
            // Users use ON DELETE SET NULL on tenant FK, so remove tenant users explicitly for clean deletion.
            $usersSt = $pdo->prepare('DELETE FROM users WHERE tenant_id = ?');
            $usersSt->execute([$tid]);
            $deletedUsers = (int) $usersSt->rowCount();
            $pdo->prepare('DELETE FROM tenants WHERE id = ?')->execute([$tid]);
            $pdo->commit();
            ActivityLogger::log(
                null,
                $actorId,
                (string) ($actor['role'] ?? 'super_admin'),
                'tenants',
                'destroy',
                $request,
                'Store deleted permanently by super admin.',
                ['tenant_id' => $tid, 'deleted_users' => $deletedUsers]
            );
            session_flash('status', sprintf('Store deleted permanently. Removed %d associated user account(s).', $deletedUsers));
        } catch (\Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            session_flash('errors', ['Could not delete store. Remove related records first.']);
        }

        return redirect(url('/super-admin/tenants'));
    }

    public function resetOwnerPassword(Request $request, string $id): Response
    {
        $tid = (int) $id;
        $actor = Auth::user();
        $actorId = (int) ($actor['id'] ?? 0);
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
        ActivityLogger::log(
            null,
            $actorId,
            (string) ($actor['role'] ?? 'super_admin'),
            'tenants',
            'reset_owner_password',
            $request,
            'Store owner password reset.',
            ['tenant_id' => $tid, 'owner_user_id' => (int) $owner['id']]
        );

        session_flash('status', 'Store owner password has been reset. Share the new password with them securely; they can change it under Profile after login.');

        return redirect(url('/super-admin/tenants'));
    }
}
