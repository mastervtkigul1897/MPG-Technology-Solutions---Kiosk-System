<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\ActivityLogger;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\TenantBackupService;

final class TenantBackupController
{
    public function index(Request $request, string $id): Response
    {
        $tenantId = (int) $id;
        $service = new TenantBackupService();
        $tenant = $service->getTenant($tenantId);
        if ($tenant === null) {
            session_flash('errors', ['Store not found.']);

            return redirect(url('/super-admin/tenants'));
        }

        return view_page('Tenant Backups', 'super-admin.tenants.backups', [
            'tenant' => $tenant,
            'backups' => $service->listBackups($tenantId),
            'retention_days' => TenantBackupService::RETENTION_DAYS,
        ]);
    }

    public function store(Request $request, string $id): Response
    {
        $tenantId = (int) $id;
        $actor = Auth::user();
        $actorId = isset($actor['id']) ? (int) $actor['id'] : null;
        $service = new TenantBackupService();

        try {
            $snapshot = $service->createTenantSnapshot($tenantId, 'manual', $actorId);
            $tenant = $service->getTenant($tenantId);
            if ($actorId !== null && $tenant !== null) {
                ActivityLogger::log(
                    null,
                    $actorId,
                    (string) ($actor['role'] ?? 'super_admin'),
                    'backups',
                    'create_snapshot',
                    $request,
                    'Created tenant snapshot for store #'.$tenantId,
                    [
                        'tenant_id' => $tenantId,
                        'tenant_slug' => (string) ($tenant['slug'] ?? ''),
                        'backup_id' => (int) ($snapshot['id'] ?? 0),
                        'backup_key' => (string) ($snapshot['backup_key'] ?? ''),
                    ]
                );
            }
            session_flash('status', 'Backup created successfully.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('super-admin.tenants.backups.index', ['id' => $tenantId]));
    }

    public function restore(Request $request, string $id, string $backupId): Response
    {
        $tenantId = (int) $id;
        $targetBackupId = (int) $backupId;
        $service = new TenantBackupService();
        $tenant = $service->getTenant($tenantId);
        if ($tenant === null) {
            session_flash('errors', ['Store not found.']);

            return redirect(url('/super-admin/tenants'));
        }

        $expected = 'RESTORE '.strtoupper((string) ($tenant['slug'] ?? ''));
        $confirm = strtoupper(trim((string) $request->input('restore_confirmation')));
        if ($confirm !== $expected) {
            session_flash('errors', ['Restore confirmation text does not match.']);

            return redirect(route('super-admin.tenants.backups.index', ['id' => $tenantId]));
        }

        $actor = Auth::user();
        $actorId = isset($actor['id']) ? (int) $actor['id'] : null;
        try {
            $service->restoreTenantFromBackup($tenantId, $targetBackupId, $actorId, true);
            if ($actorId !== null) {
                ActivityLogger::log(
                    null,
                    $actorId,
                    (string) ($actor['role'] ?? 'super_admin'),
                    'backups',
                    'restore_snapshot',
                    $request,
                    'Restored tenant data from backup for store #'.$tenantId,
                    [
                        'tenant_id' => $tenantId,
                        'tenant_slug' => (string) ($tenant['slug'] ?? ''),
                        'backup_id' => $targetBackupId,
                        'with_pre_restore_backup' => true,
                    ]
                );
            }
            session_flash('status', 'Store data restored successfully.');
        } catch (\Throwable $e) {
            if ($actorId !== null) {
                ActivityLogger::log(
                    null,
                    $actorId,
                    (string) ($actor['role'] ?? 'super_admin'),
                    'backups',
                    'restore_failed',
                    $request,
                    'Failed restore attempt for store #'.$tenantId,
                    [
                        'tenant_id' => $tenantId,
                        'tenant_slug' => (string) ($tenant['slug'] ?? ''),
                        'backup_id' => $targetBackupId,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ]
                );
            }
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('super-admin.tenants.backups.index', ['id' => $tenantId]));
    }

    public function runner(Request $request): Response
    {
        $service = new TenantBackupService();
        $actor = Auth::user();
        $actorId = isset($actor['id']) ? (int) $actor['id'] : null;
        $run = $service->runScheduledBackupsForAll($actorId);
        $triggered = (bool) ($run['triggered'] ?? false);
        $slot = (string) ($run['slot'] ?? '');
        $results = (array) ($run['results'] ?? []);

        if ($actorId !== null && $triggered) {
            $created = 0;
            $skipped = 0;
            $failed = 0;
            foreach ($results as $row) {
                if (($row['status'] ?? '') === 'created') {
                    $created++;
                } elseif (($row['status'] ?? '') === 'skipped') {
                    $skipped++;
                } elseif (($row['status'] ?? '') === 'failed') {
                    $failed++;
                }
            }
            ActivityLogger::log(
                null,
                $actorId,
                (string) ($actor['role'] ?? 'super_admin'),
                'backups',
                'run_daily_backups',
                $request,
                'Triggered daily backup runner for all active stores.',
                [
                    'created' => $created,
                    'skipped' => $skipped,
                    'failed' => $failed,
                ]
            );
        }

        return view_page('Backup Runner', 'super-admin.tenants.backup-runner', [
            'results' => $results,
            'retention_days' => TenantBackupService::RETENTION_DAYS,
            'triggered' => $triggered,
            'slot' => $slot,
            'force_mode' => false,
            'schedule_hours' => TenantBackupService::DAILY_SCHEDULE_HOURS,
            'run_time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function runnerForce(Request $request): Response
    {
        $service = new TenantBackupService();
        $actor = Auth::user();
        $actorId = isset($actor['id']) ? (int) $actor['id'] : null;
        $results = $service->runForcedBackupsForAll($actorId);

        if ($actorId !== null) {
            $created = 0;
            $failed = 0;
            foreach ($results as $row) {
                if (($row['status'] ?? '') === 'created') {
                    $created++;
                } elseif (($row['status'] ?? '') === 'failed') {
                    $failed++;
                }
            }
            ActivityLogger::log(
                null,
                $actorId,
                (string) ($actor['role'] ?? 'super_admin'),
                'backups',
                'run_forced_backups',
                $request,
                'Force-triggered backups for all active stores.',
                [
                    'created' => $created,
                    'failed' => $failed,
                ]
            );
        }

        return view_page('Backup Runner', 'super-admin.tenants.backup-runner', [
            'results' => $results,
            'retention_days' => TenantBackupService::RETENTION_DAYS,
            'triggered' => true,
            'slot' => 'manual_forced',
            'force_mode' => true,
            'schedule_hours' => TenantBackupService::DAILY_SCHEDULE_HOURS,
            'run_time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function runnerCheck(Request $request): Response
    {
        $service = new TenantBackupService();
        $actor = Auth::user();
        $actorId = isset($actor['id']) ? (int) $actor['id'] : null;
        $run = $service->runScheduledBackupsForAll($actorId);
        $triggered = (bool) ($run['triggered'] ?? false);
        $slot = (string) ($run['slot'] ?? '');
        $results = (array) ($run['results'] ?? []);

        $created = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($results as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'created') {
                $created++;
            } elseif ($status === 'skipped') {
                $skipped++;
            } elseif ($status === 'failed') {
                $failed++;
            }
        }

        if ($actorId !== null && $triggered && ($created > 0 || $failed > 0)) {
            ActivityLogger::log(
                null,
                $actorId,
                (string) ($actor['role'] ?? 'super_admin'),
                'backups',
                'run_scheduled_backups_check',
                $request,
                'Auto-check triggered scheduled backup run.',
                [
                    'slot' => $slot,
                    'created' => $created,
                    'skipped' => $skipped,
                    'failed' => $failed,
                ]
            );
        }

        return json_response([
            'success' => true,
            'now' => date('Y-m-d H:i:s'),
            'triggered' => $triggered,
            'slot' => $slot,
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }
}
