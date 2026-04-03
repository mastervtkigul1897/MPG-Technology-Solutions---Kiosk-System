<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\ActivityLogger;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\BranchService;

final class BranchController
{
    public function index(Request $request, string $id): Response
    {
        $tenantId = (int) $id;
        $svc = new BranchService();
        $base = $svc->getTenant($tenantId);
        if (! $base) {
            session_flash('errors', ['Store not found.']);

            return redirect(url('/super-admin/tenants'));
        }
        $rootId = $svc->getGroupRootTenantId($tenantId);
        $root = $svc->getTenant($rootId);
        $branches = $svc->listBranches($tenantId);

        return view_page('Branch Management', 'super-admin.tenants.branches', [
            'base_tenant' => $base,
            'root_tenant' => $root,
            'branches' => $branches,
            'branch_limit' => $svc->getBranchLimit($tenantId),
            'clone_defaults' => [],
        ]);
    }

    public function updateLimit(Request $request, string $id): Response
    {
        $tenantId = (int) $id;
        $limit = max(1, (int) $request->input('max_branches', 1));
        $svc = new BranchService();
        try {
            $svc->updateBranchLimit($tenantId, $limit);
            $this->log($request, 'branch_limit_update', 'Updated branch limit', [
                'tenant_id' => $tenantId,
                'max_branches' => $limit,
            ]);
            session_flash('status', 'Branch limit updated.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('super-admin.tenants.branches.index', ['id' => $tenantId]));
    }

    public function store(Request $request, string $id): Response
    {
        $tenantId = (int) $id;
        $svc = new BranchService();
        try {
            $newId = $svc->createBranch(
                $tenantId,
                (string) $request->input('name'),
                (string) $request->input('slug'),
                (int) $request->input('source_tenant_id', $tenantId),
                $this->extractCloneOptions($request)
            );
            $this->log($request, 'branch_create', 'Super admin created branch', [
                'tenant_id' => $tenantId,
                'new_tenant_id' => $newId,
            ]);
            session_flash('status', 'Branch created successfully.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('super-admin.tenants.branches.index', ['id' => $tenantId]));
    }

    public function toggleActive(Request $request, string $id, string $branchId): Response
    {
        $tenantId = (int) $id;
        $targetId = (int) $branchId;
        $active = $request->boolean('active');
        $svc = new BranchService();
        try {
            $svc->toggleBranchActive($tenantId, $targetId, $active);
            $this->log($request, 'branch_toggle', 'Super admin toggled branch status', [
                'tenant_id' => $tenantId,
                'branch_id' => $targetId,
                'active' => $active,
            ]);
            session_flash('status', $active ? 'Branch opened.' : 'Branch closed.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('super-admin.tenants.branches.index', ['id' => $tenantId]));
    }

    public function setMain(Request $request, string $id, string $branchId): Response
    {
        $tenantId = (int) $id;
        $targetId = (int) $branchId;
        $svc = new BranchService();
        try {
            $svc->setMainBranch($tenantId, $targetId);
            $this->log($request, 'branch_set_main', 'Super admin set main branch', [
                'tenant_id' => $tenantId,
                'branch_id' => $targetId,
            ]);
            session_flash('status', 'Main branch updated.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('super-admin.tenants.branches.index', ['id' => $tenantId]));
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

    /** @param array<string,mixed> $meta */
    private function log(Request $request, string $action, string $description, array $meta): void
    {
        $u = Auth::user();
        if (! $u) {
            return;
        }
        ActivityLogger::log(
            null,
            (int) $u['id'],
            (string) ($u['role'] ?? 'super_admin'),
            'branches',
            $action,
            $request,
            $description,
            $meta
        );
    }
}
