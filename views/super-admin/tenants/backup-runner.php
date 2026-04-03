<?php
/** @var array<int,array<string,mixed>> $results */
$rows = $results ?? [];
$retentionDays = (int) ($retention_days ?? 7);
$runTime = (string) ($run_time ?? date('Y-m-d H:i:s'));
$triggered = (bool) ($triggered ?? false);
$slot = (string) ($slot ?? '');
$forceMode = (bool) ($force_mode ?? false);
$scheduleHours = (array) ($schedule_hours ?? [17, 21]);
$created = 0;
$skipped = 0;
$failed = 0;
foreach ($rows as $r) {
    $status = (string) ($r['status'] ?? '');
    if ($status === 'created') {
        $created++;
    } elseif ($status === 'skipped') {
        $skipped++;
    } elseif ($status === 'failed') {
        $failed++;
    }
}
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3 p-md-4">
        <div class="row g-2 mb-3">
            <div class="col-12 col-md-3">
                <div class="border rounded p-2 bg-light">
                    <div class="small text-muted">Current time</div>
                    <div id="runnerNow" class="fw-semibold">--:--:--</div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="border rounded p-2 bg-light">
                    <div class="small text-muted">Next scheduled backup</div>
                    <div id="runnerNext" class="fw-semibold">--</div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="border rounded p-2 bg-light">
                    <div class="small text-muted">Countdown</div>
                    <div id="runnerCountdown" class="fw-semibold">--:--:--</div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="border rounded p-2 bg-light">
                    <div class="small text-muted">Heartbeat</div>
                    <div id="runnerHeartbeat" class="fw-semibold text-secondary">Idle</div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <h6 class="mb-1">Backup Runner Executed</h6>
                <div class="small text-muted">Run time: <?= e($runTime) ?></div>
                <div class="small text-muted">Retention policy: delete backups older than <?= (int) $retentionDays ?> days.</div>
                <div class="small text-muted">Schedule: <?= e(implode(', ', array_map(static fn ($h): string => sprintf('%02d:00', (int) $h), $scheduleHours))) ?> only.</div>
                <?php if ($forceMode): ?>
                    <div class="small text-primary mt-1">Manual force mode: backups were triggered for all active stores regardless of schedule.</div>
                <?php elseif (! $triggered): ?>
                    <div class="small text-warning mt-1">No backup triggered. This page only runs backups at the scheduled hours above.</div>
                <?php else: ?>
                    <div class="small text-success mt-1">Triggered slot: <?= e($slot) ?></div>
                <?php endif; ?>
                <div id="runnerAutoStatus" class="small text-muted mt-1">Auto-check active while this page is open.</div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e(route('super-admin.tenants.index')) ?>" class="btn btn-outline-secondary btn-sm">Back to stores</a>
                <a href="<?= e(route('super-admin.backups.runner')) ?>" class="btn btn-primary btn-sm">Run again</a>
                <form id="forceBackupForm" method="POST" action="<?= e(route('super-admin.backups.runner.force')) ?>" onsubmit="return confirm('Force backup now for all active stores?');">
                    <?= csrf_field() ?>
                    <button type="submit" id="forceBackupBtn" class="btn btn-danger btn-sm">Force Backup Now</button>
                </form>
                <button type="button" id="wakeLockBtn" class="btn btn-outline-dark btn-sm">Enable Keep Awake</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const scheduleHours = <?= json_embed(array_values(array_map(static fn ($h): int => (int) $h, $scheduleHours))) ?>;
    const checkUrl = <?= json_embed(route('super-admin.backups.runner.check')) ?>;
    const forceMode = <?= json_embed($forceMode) ?>;
    const forceCreated = <?= json_embed($created) ?>;
    const forceSkipped = <?= json_embed($skipped) ?>;
    const forceFailed = <?= json_embed($failed) ?>;
    const nowEl = document.getElementById('runnerNow');
    const nextEl = document.getElementById('runnerNext');
    const countdownEl = document.getElementById('runnerCountdown');
    const heartbeatEl = document.getElementById('runnerHeartbeat');
    const statusEl = document.getElementById('runnerAutoStatus');
    const wakeLockBtn = document.getElementById('wakeLockBtn');
    const forceForm = document.getElementById('forceBackupForm');
    const forceBtn = document.getElementById('forceBackupBtn');
    if (!nowEl || !nextEl || !countdownEl || !heartbeatEl || !statusEl || !wakeLockBtn || !forceForm || !forceBtn) return;

    const pad = (n) => String(n).padStart(2, '0');
    const formatDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;

    const nextTarget = (now) => {
        const today = new Date(now);
        for (const hour of scheduleHours) {
            const t = new Date(today.getFullYear(), today.getMonth(), today.getDate(), Number(hour), 0, 0, 0);
            if (t.getTime() > now.getTime()) return t;
        }
        return new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1, Number(scheduleHours[0] || 17), 0, 0, 0);
    };

    const renderClock = () => {
        const now = new Date();
        const target = nextTarget(now);
        const diffMs = Math.max(0, target.getTime() - now.getTime());
        const totalSec = Math.floor(diffMs / 1000);
        const hh = Math.floor(totalSec / 3600);
        const mm = Math.floor((totalSec % 3600) / 60);
        const ss = totalSec % 60;
        nowEl.textContent = formatDate(now);
        nextEl.textContent = formatDate(target);
        countdownEl.textContent = `${pad(hh)}:${pad(mm)}:${pad(ss)}`;
    };

    let wakeLock = null;
    const canWakeLock = typeof navigator !== 'undefined' && 'wakeLock' in navigator;
    const updateWakeLockBtn = (enabled) => {
        wakeLockBtn.textContent = enabled ? 'Disable Keep Awake' : 'Enable Keep Awake';
        wakeLockBtn.classList.toggle('btn-success', enabled);
        wakeLockBtn.classList.toggle('btn-outline-dark', !enabled);
    };
    const requestWakeLock = async () => {
        if (!canWakeLock) {
            statusEl.textContent = 'Wake Lock is not supported on this browser.';
            statusEl.className = 'small text-warning mt-1';
            return;
        }
        try {
            wakeLock = await navigator.wakeLock.request('screen');
            updateWakeLockBtn(true);
            statusEl.textContent = 'Keep Awake enabled. Screen sleep is prevented while page is open.';
            statusEl.className = 'small text-success mt-1';
            wakeLock.addEventListener('release', () => {
                updateWakeLockBtn(false);
            });
        } catch {
            statusEl.textContent = 'Could not enable Keep Awake. Check browser permissions.';
            statusEl.className = 'small text-danger mt-1';
        }
    };
    const releaseWakeLock = async () => {
        try {
            if (wakeLock) {
                await wakeLock.release();
                wakeLock = null;
            }
        } catch {
            // no-op
        } finally {
            updateWakeLockBtn(false);
        }
    };

    wakeLockBtn.addEventListener('click', async () => {
        if (wakeLock) {
            await releaseWakeLock();
            statusEl.textContent = 'Keep Awake disabled.';
            statusEl.className = 'small text-muted mt-1';
            return;
        }
        await requestWakeLock();
    });

    forceForm.addEventListener('submit', () => {
        forceBtn.disabled = true;
        forceBtn.textContent = 'Running backups...';
    });

    document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState === 'visible' && wakeLock) {
            await requestWakeLock();
        }
    });

    let running = false;
    const autoCheck = async () => {
        if (running) return;
        running = true;
        const startedAt = performance.now();
        heartbeatEl.textContent = 'Checking...';
        heartbeatEl.className = 'fw-semibold text-primary';
        try {
            const res = await fetch(checkUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.success) {
                statusEl.textContent = 'Auto-check failed. Will retry...';
                statusEl.className = 'small text-danger mt-1';
                heartbeatEl.textContent = `Fail @ ${new Date().toLocaleTimeString()}`;
                heartbeatEl.className = 'fw-semibold text-danger';
                return;
            }
            const latency = Math.max(1, Math.round(performance.now() - startedAt));
            heartbeatEl.textContent = `OK @ ${new Date().toLocaleTimeString()} (${latency}ms)`;
            heartbeatEl.className = 'fw-semibold text-success';
            const created = Number(body.created || 0);
            const skipped = Number(body.skipped || 0);
            const failed = Number(body.failed || 0);
            const triggered = !!body.triggered;
            if (triggered) {
                statusEl.textContent = `Triggered (${body.slot || 'scheduled'}): created=${created}, skipped=${skipped}, failed=${failed}. Refreshing...`;
                statusEl.className = failed > 0 ? 'small text-danger mt-1' : 'small text-success mt-1';
                setTimeout(() => window.location.reload(), 1800);
            } else {
                statusEl.textContent = 'Auto-check active. Waiting for scheduled time...';
                statusEl.className = 'small text-muted mt-1';
            }
        } catch {
            statusEl.textContent = 'Auto-check network error. Will retry...';
            statusEl.className = 'small text-danger mt-1';
            heartbeatEl.textContent = `Network error @ ${new Date().toLocaleTimeString()}`;
            heartbeatEl.className = 'fw-semibold text-danger';
        } finally {
            running = false;
        }
    };

    renderClock();
    updateWakeLockBtn(false);
    setInterval(renderClock, 1000);
    autoCheck();
    setInterval(autoCheck, 60000);

    // Clear completion indicator after first display to avoid repeated popup on reload.
    if (forceMode && typeof Swal !== 'undefined') {
        const msg = `Created: ${Number(forceCreated || 0)} | Skipped: ${Number(forceSkipped || 0)} | Failed: ${Number(forceFailed || 0)}`;
        Swal.fire({
            icon: Number(forceFailed || 0) > 0 ? 'warning' : 'success',
            title: 'Force Backup Completed',
            text: msg,
            confirmButtonColor: Number(forceFailed || 0) > 0 ? '#f59f00' : '#198754',
        });
    }
})();
</script>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Created today</div><div class="fs-5 fw-semibold text-success"><?= (int) $created ?></div></div></div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Skipped (already has today backup)</div><div class="fs-5 fw-semibold text-secondary"><?= (int) $skipped ?></div></div></div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Failed</div><div class="fs-5 fw-semibold text-danger"><?= (int) $failed ?></div></div></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4 table-responsive">
        <table class="table table-striped table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>Store ID</th>
                <th class="d-none d-md-table-cell">Slug</th>
                <th>Status</th>
                <th class="d-none d-lg-table-cell">Backup ID</th>
                <th class="d-none d-lg-table-cell">Pruned old backups</th>
                <th class="d-none d-md-table-cell">Error</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No active stores found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $status = (string) ($row['status'] ?? '');
                    $badge = match ($status) {
                        'created' => 'text-bg-success',
                        'skipped' => 'text-bg-secondary',
                        'failed' => 'text-bg-danger',
                        default => 'text-bg-light',
                    };
                    ?>
                    <tr>
                        <td><?= (int) ($row['tenant_id'] ?? 0) ?></td>
                        <td class="d-none d-md-table-cell"><?= e((string) ($row['slug'] ?? '')) ?></td>
                        <td><span class="badge <?= e($badge) ?>"><?= e($status) ?></span></td>
                        <td class="d-none d-lg-table-cell"><?= (int) ($row['backup_id'] ?? 0) ?></td>
                        <td class="d-none d-lg-table-cell"><?= (int) ($row['pruned'] ?? 0) ?></td>
                        <td class="small text-danger d-none d-md-table-cell"><?= e((string) ($row['error'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
