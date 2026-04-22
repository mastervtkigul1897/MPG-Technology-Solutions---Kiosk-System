<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<div class="card mb-3">
    <div class="card-body">
        <h6 class="mb-1">Rewards mechanics and gift setup</h6>
        <p class="small text-muted mb-0">
            Mechanics: each completed full service (Drop-Off or Wash and Dry) adds 1 count for the selected customer. Customer can redeem once count reaches your required number.
        </p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="<?= e(route('tenant.redeem-config.update')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label mb-1">Full services required</label>
                <input type="number" min="1" class="form-control" name="full_services_required" value="<?= e((string) ($reward_config['reward_points_cost'] ?? '10')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Reward name</label>
                <input class="form-control" name="reward_name" value="<?= e((string) ($reward_config['reward_name'] ?? 'Reward')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Reward description (optional)</label>
                <input class="form-control" name="reward_description" value="<?= e((string) ($reward_config['reward_description'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Reward service</label>
                <select class="form-select" name="reward_order_type_code" required>
                    <option value="">Choose service</option>
                    <?php foreach (($order_types ?? []) as $ot): ?>
                        <?php $code = (string) ($ot['code'] ?? ''); ?>
                        <option value="<?= e($code) ?>" <?= $code !== '' && $code === (string) ($reward_config['reward_order_type_code'] ?? '') ? 'selected' : '' ?>>
                            <?= e((string) ($ot['label'] ?? $code)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Reward qty</label>
                <input type="number" min="1" class="form-control" name="reward_quantity" value="<?= e((string) ($reward_config['reward_quantity'] ?? '1')) ?>" required>
            </div>
            <div class="col-md-3">
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="rewardActive" name="is_active" value="1" <?= (int) ($reward_config['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="rewardActive">Active</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Save config</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-12">
        <div class="card h-100">
            <div class="card-body table-responsive">
                <h6 class="mb-3">Customer reward count</h6>
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <th>Customer</th>
                        <th class="text-end">Full services</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($customer_points ?? []) as $row): ?>
                        <tr>
                            <td><?= e((string) ($row['name'] ?? '')) ?></td>
                            <td class="text-end"><?= e(number_format((float) ($row['rewards_balance'] ?? 0), 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <h6 class="mb-3">Redemption history</h6>
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Reward</th>
                <th class="text-end">Services used</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($redemptions ?? []) as $item): ?>
                <tr>
                    <td><?= e((string) ($item['created_at'] ?? '')) ?></td>
                    <td><?= e((string) ($item['customer_name'] ?? '')) ?></td>
                    <td><?= e((string) ($item['reward_name'] ?? '')) ?></td>
                    <td class="text-end"><?= e(number_format((float) ($item['points_used'] ?? 0), 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
