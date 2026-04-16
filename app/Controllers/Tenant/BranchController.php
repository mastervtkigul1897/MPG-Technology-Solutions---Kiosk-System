<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\ActivityLogger;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\BranchService;

final class BranchController
{
    private function ensureMainBranchTenantAdmin(array $u): ?Response
    {
        if (($u['role'] ?? '') !== 'tenant_admin') {
            return new Response('Forbidden.', 403);
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return new Response('Forbidden.', 403);
        }
        $svc = new BranchService();
        $tenant = $svc->getTenant($tenantId);
        if (! $tenant || ! (bool) ($tenant['is_main_branch'] ?? false)) {
            return new Response('Forbidden.', 403);
        }

        return null;
    }

    public function index(Request $request): Response
    {
        $u = Auth::user();
        if (! $u) {
            return new Response('Forbidden.', 403);
        }
        $gate = $this->ensureMainBranchTenantAdmin($u);
        if ($gate instanceof Response) {
            return $gate;
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $svc = new BranchService();
        $root = $svc->getTenant($svc->getGroupRootTenantId($tenantId));
        $branches = $svc->listBranches($tenantId);

        return view_page('Branch Management', 'tenant.branches.index', [
            'root_tenant' => $root,
            'current_tenant_id' => $tenantId,
            'branches' => $branches,
            'branch_limit' => $svc->getBranchLimit($tenantId),
            'clone_defaults' => [],
            'premium_trial_browse_lock' => Auth::isTenantFreeTrial($u),
        ]);
    }

    public function store(Request $request): Response
    {
        $u = Auth::user();
        if (! $u) {
            return new Response('Forbidden.', 403);
        }
        $gate = $this->ensureMainBranchTenantAdmin($u);
        if ($gate instanceof Response) {
            return $gate;
        }
        if (Auth::isTenantFreeTrial($u)) {
            session_flash('errors', ['Premium: creating or changing branches is not available on a Free Trial.']);

            return redirect(route('tenant.branches.index'));
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $svc = new BranchService();
        try {
            $newId = $svc->createBranch(
                $tenantId,
                (string) $request->input('name'),
                (string) $request->input('slug'),
                (int) $request->input('source_tenant_id', $tenantId),
                $this->extractCloneOptions($request)
            );
            $this->log($request, 'branch_create_self_service', 'Store owner created branch', [
                'tenant_id' => $tenantId,
                'new_tenant_id' => $newId,
            ]);
            session_flash('status', 'Branch created successfully.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(url('/dashboard'));
    }

    public function toggleActive(Request $request, string $branchId): Response
    {
        $u = Auth::user();
        if (! $u) {
            return new Response('Forbidden.', 403);
        }
        $gate = $this->ensureMainBranchTenantAdmin($u);
        if ($gate instanceof Response) {
            return $gate;
        }
        if (Auth::isTenantFreeTrial($u)) {
            session_flash('errors', ['Premium: changing branch status is not available on a Free Trial.']);

            return redirect(route('tenant.branches.index'));
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $targetId = (int) $branchId;
        $active = $request->boolean('active');
        $svc = new BranchService();
        try {
            $svc->toggleBranchActive($tenantId, $targetId, $active);
            $this->log($request, 'branch_toggle_self_service', 'Store owner toggled branch status', [
                'tenant_id' => $tenantId,
                'branch_id' => $targetId,
                'active' => $active,
            ]);
            session_flash('status', $active ? 'Branch opened.' : 'Branch closed.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('tenant.branches.index'));
    }

    public function setMain(Request $request, string $branchId): Response
    {
        $u = Auth::user();
        if (! $u || ($u['role'] ?? '') !== 'tenant_admin') {
            return new Response('Forbidden.', 403);
        }
        if (Auth::isTenantFreeTrial($u)) {
            session_flash('errors', ['Premium: changing the main branch is not available on a Free Trial.']);

            return redirect(route('tenant.branches.index'));
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $targetId = (int) $branchId;
        $svc = new BranchService();
        try {
            $svc->setMainBranch($tenantId, $targetId);
            // After changing main branch, switch active context to the new main
            // so branch management page remains accessible.
            session_set('active_tenant_id', $targetId);
            $this->log($request, 'branch_set_main_self_service', 'Store owner set main branch', [
                'tenant_id' => $tenantId,
                'branch_id' => $targetId,
            ]);
            session_flash('status', 'Main branch updated.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('tenant.branches.index'));
    }

    public function switch(Request $request): Response
    {
        $u = Auth::user();
        if (! $u || ($u['role'] ?? '') !== 'tenant_admin') {
            return new Response('Forbidden.', 403);
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $targetId = (int) $request->input('branch_id', 0);
        $svc = new BranchService();
        try {
            $target = $svc->getTenant($targetId);
            if (! $target) {
                throw new \RuntimeException('Branch not found.');
            }
            $actorRoot = $svc->getGroupRootTenantId($tenantId);
            $targetRoot = $svc->getGroupRootTenantId($targetId);
            if ($actorRoot !== $targetRoot) {
                throw new \RuntimeException('Branch is outside your account group.');
            }
            if (! (bool) ($target['is_active'] ?? false)) {
                throw new \RuntimeException('Cannot switch to a closed branch.');
            }
            if ($this->isBranchExpired($target)) {
                $name = trim((string) ($target['name'] ?? 'Selected branch'));
                throw new \RuntimeException($name.' subscription is expired. Please renew first.');
            }
            session_set('active_tenant_id', $targetId);
            $this->log($request, 'branch_switch_self_service', 'Store owner switched active branch context', [
                'tenant_id' => $tenantId,
                'target_branch_id' => $targetId,
            ]);
            session_flash('status', 'Active branch switched.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(url('/dashboard'));
    }

    /** @return array{categories:bool,ingredients:bool,products:bool,requirements:bool} */
    private function extractCloneOptions(Request $request): array
    {
        $raw = $request->input('clone', []);
        $list = is_array($raw) ? array_map('strval', $raw) : [];

        return [
            'categories' => in_array('categories', $list, true),
            'ingredients' => in_array('ingredients', $list, true),
            'products' => in_array('products', $list, true),
            'requirements' => in_array('requirements', $list, true),
        ];
    }

    /** @param array<string,mixed> $branch */
    private function isBranchExpired(array $branch): bool
    {
        $value = trim((string) ($branch['license_expires_at'] ?? ''));
        if ($value === '') {
            return false;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return false;
        }

        return date('Y-m-d', $ts) < date('Y-m-d');
    }

    /** @param array<string,mixed> $meta */
    private function log(Request $request, string $action, string $description, array $meta): void
    {
        $u = Auth::user();
        if (! $u) {
            return;
        }
        ActivityLogger::log(
            (int) ($u['tenant_id'] ?? 0),
            (int) $u['id'],
            (string) ($u['role'] ?? 'tenant_admin'),
            'branches',
            $action,
            $request,
            $description,
            $meta
        );
    }
}
