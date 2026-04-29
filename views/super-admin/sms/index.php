<?php
$smsQueueRows = (array) ($sms_queue_rows ?? []);
$smsTenants = (array) ($sms_tenants ?? []);
?>
<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-2">SMS Credits</h5>
        <p class="small text-muted mb-3">Each shop gets 30 SMS credits daily (auto reset). Use this form to add extra credits when needed.</p>
        <form method="POST" action="<?= e(route('super-admin.sms.credits.assign')) ?>" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-7">
                <label class="form-label small mb-1" for="smsCreditsTenant">Shop</label>
                <select class="form-select" id="smsCreditsTenant" name="tenant_id" required>
                    <option value="">Select shop</option>
                    <?php foreach ($smsTenants as $tenant): ?>
                        <option value="<?= (int) ($tenant['id'] ?? 0) ?>">
                            #<?= (int) ($tenant['id'] ?? 0) ?> — <?= e((string) ($tenant['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="smsCreditsAmount">Extra credits</label>
                <input class="form-control" id="smsCreditsAmount" type="number" name="credits" min="1" step="1" value="30" required>
            </div>
            <div class="col-md-2 d-grid align-self-end">
                <button type="submit" class="btn btn-primary">Assign</button>
            </div>
        </form>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <h5 class="mb-0">SMS Queue (Manual Create)</h5>
            <span class="small text-muted">For Android gateway polling</span>
        </div>
        <form method="POST" action="<?= e(route('super-admin.sms-queue.store')) ?>" class="row g-2 mb-3">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="superSmsDeviceId">Device ID</label>
                <input type="text" class="form-control" id="superSmsDeviceId" name="device_id" placeholder="PHONE_01" maxlength="100" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="superSmsPhone">Phone</label>
                <input type="text" class="form-control" id="superSmsPhone" name="phone" placeholder="+639171234567" maxlength="30" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1" for="superSmsMessage">Message</label>
                <input type="text" class="form-control" id="superSmsMessage" name="message" placeholder="Your laundry is ready." maxlength="1000" required>
            </div>
            <div class="col-md-2 d-grid align-self-end">
                <button type="submit" class="btn btn-primary">Create SMS Record</button>
            </div>
        </form>
        <?php if ($smsQueueRows === []): ?>
            <p class="text-muted mb-0">No SMS queue records yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Device</th>
                        <th>Phone</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Retry</th>
                        <th>Created</th>
                        <th>Sent At</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($smsQueueRows as $smsRow): ?>
                        <tr>
                            <td><?= (int) ($smsRow['id'] ?? 0) ?></td>
                            <td><?= e((string) ($smsRow['device_id'] ?? '')) ?></td>
                            <td><?= e((string) ($smsRow['phone'] ?? '')) ?></td>
                            <td><?= e((string) ($smsRow['message'] ?? '')) ?></td>
                            <td><span class="badge text-bg-secondary"><?= e((string) ($smsRow['status'] ?? 'pending')) ?></span></td>
                            <td><?= (int) ($smsRow['retry_count'] ?? 0) ?></td>
                            <td><?= e((string) ($smsRow['created_at'] ?? '')) ?></td>
                            <td><?= e((string) ($smsRow['sent_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
