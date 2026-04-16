<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\StaffModules;
use PDO;

/**
 * Store owners (tenant_admin) manage cashiers for their tenant only.
 */
final class StaffController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        $hasModsCol = Auth::hasModulePermissionsColumn();
        $sql = $hasModsCol
            ? 'SELECT id, name, email, role, module_permissions, created_at FROM users WHERE tenant_id = ?
             ORDER BY CASE WHEN role = \'tenant_admin\' THEN 0 ELSE 1 END, name'
            : 'SELECT id, name, email, role, created_at FROM users WHERE tenant_id = ?
             ORDER BY CASE WHEN role = \'tenant_admin\' THEN 0 ELSE 1 END, name';
        $st = $pdo->prepare($sql);
        $st->execute([$tenantId]);
        $staff = $st->fetchAll(PDO::FETCH_ASSOC);
        $currentBranchExpiry = null;
        try {
            $stExpiry = $pdo->prepare('SELECT license_expires_at FROM tenants WHERE id = ? LIMIT 1');
            $stExpiry->execute([$tenantId]);
            $currentBranchExpiry = $stExpiry->fetchColumn();
        } catch (\Throwable) {
            $currentBranchExpiry = null;
        }
        $hasOwnerInCurrentList = false;
        foreach ($staff as $i => $row) {
            if (($row['role'] ?? '') === 'tenant_admin') {
                $hasOwnerInCurrentList = true;
            }
            if (($row['role'] ?? '') === 'cashier') {
                $raw = $hasModsCol ? ($row['module_permissions'] ?? null) : null;
                $staff[$i]['modules'] = StaffModules::normalizeCashierModules(is_string($raw) ? $raw : null);
            } else {
                $staff[$i]['modules'] = [];
            }
            $staff[$i]['owner_shared'] = false;
            $staff[$i]['subscription_expired'] = (($row['role'] ?? '') === 'tenant_admin')
                ? $this->isExpiredDate($currentBranchExpiry)
                : false;
            unset($staff[$i]['module_permissions']);
        }

        // Branch setup can use one shared owner login for all branches.
        // If this branch has no local tenant_admin row, show the group owner in the staff list.
        if (! $hasOwnerInCurrentList) {
            $owner = null;
            if (Auth::hasTenantBranchColumns()) {
                $stOwner = $pdo->prepare(
                    'SELECT u.id, u.name, u.email, u.role, u.created_at, tu.license_expires_at
                     FROM users u
                     INNER JOIN tenants tu ON tu.id = u.tenant_id
                     INNER JOIN tenants tc ON tc.id = ?
                     WHERE u.role = ? AND tu.branch_group_id = tc.branch_group_id
                     ORDER BY tu.is_main_branch DESC, u.id ASC
                     LIMIT 1'
                );
                $stOwner->execute([$tenantId, 'tenant_admin']);
                $owner = $stOwner->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                $stOwner = $pdo->prepare(
                    'SELECT id, name, email, role, created_at
                     FROM users
                     WHERE tenant_id = ? AND role = ?
                     ORDER BY id ASC
                     LIMIT 1'
                );
                $stOwner->execute([$tenantId, 'tenant_admin']);
                $owner = $stOwner->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if (is_array($owner)) {
                $owner['modules'] = [];
                $owner['owner_shared'] = true;
                $owner['subscription_expired'] = $this->isExpiredDate($owner['license_expires_at'] ?? null);
                unset($owner['license_expires_at']);
                array_unshift($staff, $owner);
            }
        }

        return view_page('Staff', 'tenant.staff.index', [
            'staff' => $staff,
            'module_labels' => StaffModules::LABELS,
            'optional_module_keys' => StaffModules::optionalModuleKeys(),
            'required_baseline_labels' => array_map(
                static fn (string $k): string => StaffModules::LABELS[$k],
                StaffModules::REQUIRED_BASELINE
            ),
            'module_permissions_available' => $hasModsCol,
            'premium_trial_browse_lock' => Auth::isTenantFreeTrial($user),
        ]);
    }

    private function isExpiredDate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        $ts = strtotime((string) $value);
        if ($ts === false) {
            return false;
        }

        return date('Y-m-d', $ts) < date('Y-m-d');
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        if (Auth::isTenantFreeTrial($user)) {
            session_flash('errors', ['Premium feature: adding staff accounts is not available for Free Trial plans.']);
            return redirect(url('/tenant/staff'));
        }
        $tenantId = (int) $user['tenant_id'];

        $name = trim((string) $request->input('name'));
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');
        $modulesRaw = $request->input('modules', []);
        $optionalPicked = is_array($modulesRaw) ? StaffModules::sanitizeRequested($modulesRaw) : [];
        $modules = StaffModules::mergeRequiredBaseline($optionalPicked);

        $errors = [];
        if ($name === '' || strlen($name) > 255) {
            $errors[] = 'Name is required.';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        if ($st->fetch()) {
            $errors[] = 'That email is already registered.';
        }

        if ($errors !== []) {
            session_flash('errors', $errors);

            return redirect(url('/tenant/staff'));
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');
        if (Auth::hasModulePermissionsColumn()) {
            $modulesJson = json_encode($modules, JSON_UNESCAPED_UNICODE);
            $pdo->prepare(
                'INSERT INTO users (name, email, password, role, tenant_id, module_permissions, email_verified_at, created_at, updated_at)
                 VALUES (?, ?, ?, \'cashier\', ?, ?, ?, ?, ?)'
            )->execute([$name, $email, $hash, $tenantId, $modulesJson, $now, $now, $now]);
        } else {
            $pdo->prepare(
                'INSERT INTO users (name, email, password, role, tenant_id, email_verified_at, created_at, updated_at)
                 VALUES (?, ?, ?, \'cashier\', ?, ?, ?, ?)'
            )->execute([$name, $email, $hash, $tenantId, $now, $now, $now]);
        }

        $newId = (int) $pdo->lastInsertId();
        ActivityLogger::log(
            $tenantId,
            (int) $user['id'],
            (string) $user['role'],
            'staff',
            'create_cashier',
            $request,
            sprintf('Created cashier: %s (%s)', $name, $email),
            ['new_user_id' => $newId, 'email' => $email, 'modules' => Auth::hasModulePermissionsColumn() ? $modules : []]
        );

        session_flash('status', 'Cashier account created.');

        return redirect(url('/tenant/staff'));
    }

    public function updateModules(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $targetId = (int) $id;

        if (! Auth::hasModulePermissionsColumn()) {
            session_flash('errors', ['Per-staff modules require the database column module_permissions. Run the SQL in database/add_user_module_permissions.sql on your database, then refresh.']);

            return redirect(url('/tenant/staff'));
        }
        if (Auth::isTenantFreeTrial($user)) {
            session_flash('errors', ['Premium: updating staff module access is not available on a Free Trial.']);

            return redirect(url('/tenant/staff'));
        }

        $modulesRaw = $request->input('modules', []);
        $optionalPicked = is_array($modulesRaw) ? StaffModules::sanitizeRequested($modulesRaw) : [];
        $modules = StaffModules::mergeRequiredBaseline($optionalPicked);

        $pdo = App::db();
        $st = $pdo->prepare('SELECT id, role, name, email FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$targetId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row || ($row['role'] ?? '') !== 'cashier') {
            session_flash('errors', ['Cashier not found.']);

            return redirect(url('/tenant/staff'));
        }

        $cashierName = trim((string) ($row['name'] ?? ''));
        $cashierEmail = strtolower(trim((string) ($row['email'] ?? '')));

        $json = json_encode($modules, JSON_UNESCAPED_UNICODE);
        $pdo->prepare('UPDATE users SET module_permissions = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ? AND role = ?')
            ->execute([$json, $targetId, $tenantId, 'cashier']);

        if ($cashierEmail !== '') {
            $desc = $cashierName !== ''
                ? sprintf('Updated modules for cashier %s (%s)', $cashierName, $cashierEmail)
                : sprintf('Updated modules for cashier %s', $cashierEmail);
        } else {
            $desc = sprintf('Updated modules for cashier user #%d', $targetId);
        }

        ActivityLogger::log(
            $tenantId,
            (int) $user['id'],
            (string) $user['role'],
            'staff',
            'update_cashier_modules',
            $request,
            $desc,
            [
                'cashier_user_id' => $targetId,
                'cashier_email' => $cashierEmail !== '' ? $cashierEmail : null,
                'cashier_name' => $cashierName !== '' ? $cashierName : null,
                'modules' => $modules,
            ]
        );

        session_flash('status', 'Staff modules updated.');

        return redirect(url('/tenant/staff'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        if (Auth::isTenantFreeTrial($user)) {
            session_flash('errors', ['Premium: removing staff accounts is not available on a Free Trial.']);

            return redirect(url('/tenant/staff'));
        }
        $tenantId = (int) $user['tenant_id'];
        $targetId = (int) $id;

        if ($targetId === (int) $user['id']) {
            session_flash('errors', ['You cannot remove your own account.']);

            return redirect(url('/tenant/staff'));
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT id, role, name, email FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$targetId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            session_flash('errors', ['User not found.']);

            return redirect(url('/tenant/staff'));
        }
        if (($row['role'] ?? '') !== 'cashier') {
            session_flash('errors', ['You can only remove cashier accounts.']);

            return redirect(url('/tenant/staff'));
        }

        $pdo->prepare('DELETE FROM users WHERE id = ? AND tenant_id = ? AND role = ?')
            ->execute([$targetId, $tenantId, 'cashier']);

        ActivityLogger::log(
            $tenantId,
            (int) $user['id'],
            (string) $user['role'],
            'staff',
            'remove_cashier',
            $request,
            sprintf('Removed cashier: %s (%s)', (string) ($row['name'] ?? ''), (string) ($row['email'] ?? '')),
            ['removed_user_id' => $targetId]
        );

        session_flash('status', 'Cashier removed.');

        return redirect(url('/tenant/staff'));
    }
}
