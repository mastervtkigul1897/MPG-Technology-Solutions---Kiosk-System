<?php
/** @var int $free_count */
/** @var int $premium_count */
/** @var array<int,array<string,mixed>> $recipient_options */
/** @var array<int,array<string,mixed>> $campaign_logs */
$freeCount = (int) ($free_count ?? 0);
$premiumCount = (int) ($premium_count ?? 0);
$recipientOptions = is_array($recipient_options ?? null) ? $recipient_options : [];
$campaignLogs = is_array($campaign_logs ?? null) ? $campaign_logs : [];
$selectedMode = (string) old('recipient_mode', 'segment');
$selectedSegment = (string) old('segment');
$subjectVal = (string) old('subject');
$htmlBodyVal = (string) old('html_body');
$selectedRecipientIdsRaw = old('selected_recipient_ids', []);
$selectedRecipientIds = [];
if (is_array($selectedRecipientIdsRaw)) {
    foreach ($selectedRecipientIdsRaw as $idRaw) {
        $id = (int) $idRaw;
        if ($id > 0) {
            $selectedRecipientIds[$id] = true;
        }
    }
}
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4">
        <p class="small text-muted mb-4">
            Send campaign or reminder emails to store owner accounts by plan segment. Available placeholders:
            <code>{{owner_name}}</code> and <code>{{store_name}}</code>.
        </p>

        <form method="POST" action="<?= e(route('super-admin.email-campaign.send')) ?>" class="vstack gap-3">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="campaign_recipient_mode">Recipient mode</label>
                    <select class="form-select" id="campaign_recipient_mode" name="recipient_mode" required>
                        <option value="segment" <?= $selectedMode === 'segment' ? 'selected' : '' ?>>By segment</option>
                        <option value="selected_emails" <?= $selectedMode === 'selected_emails' ? 'selected' : '' ?>>By selected email addresses</option>
                    </select>
                </div>
                <div class="col-md-6" id="campaign_segment_wrap">
                    <label class="form-label" for="campaign_segment">Recipient segment</label>
                    <select class="form-select" id="campaign_segment" name="segment">
                        <option value="">Select recipients</option>
                        <option value="premium" <?= $selectedSegment === 'premium' ? 'selected' : '' ?>>
                            Premium users (plan is not free) — <?= $premiumCount ?>
                        </option>
                        <option value="free" <?= $selectedSegment === 'free' ? 'selected' : '' ?>>
                            Free users (plan is free) — <?= $freeCount ?>
                        </option>
                    </select>
                </div>
                <div class="col-12" id="campaign_selected_emails_wrap">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <label class="form-label mb-0" for="campaign_email_filter">Choose specific recipient emails</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="campaign_select_all_btn">Select all (filtered)</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="campaign_clear_all_btn">Clear all</button>
                        </div>
                    </div>
                    <input type="text" class="form-control mb-2" id="campaign_email_filter" placeholder="Search email, owner name, or store name">
                    <div class="border rounded p-2" style="max-height: 280px; overflow: auto;" id="campaign_email_list">
                        <?php if ($recipientOptions === []): ?>
                            <div class="small text-muted px-2 py-1">No tenant owner emails found.</div>
                        <?php else: ?>
                            <?php foreach ($recipientOptions as $opt): ?>
                                <?php
                                $uid = (int) ($opt['id'] ?? 0);
                                $email = trim((string) ($opt['email'] ?? ''));
                                $ownerName = trim((string) ($opt['name'] ?? 'Store Owner'));
                                $storeName = trim((string) ($opt['store_name'] ?? 'Store'));
                                $plan = strtolower(trim((string) ($opt['plan'] ?? '')));
                                $planLabel = in_array($plan, ['trial', 'free', 'free_trial', 'free_access'], true) ? 'Free' : 'Premium';
                                ?>
                                <?php if ($uid > 0 && $email !== ''): ?>
                                    <label
                                        class="form-check d-block px-2 py-2 rounded campaign-email-item border-bottom border-light-subtle"
                                        data-search="<?= e(strtolower($email.' '.$ownerName.' '.$storeName.' '.$planLabel)) ?>"
                                    >
                                        <input
                                            class="form-check-input me-2 campaign-email-checkbox"
                                            type="checkbox"
                                            name="selected_recipient_ids[]"
                                            value="<?= $uid ?>"
                                            <?= isset($selectedRecipientIds[$uid]) ? 'checked' : '' ?>
                                            style="width:1.1rem;height:1.1rem;"
                                        >
                                        <span class="small">
                                            <strong><?= e($email) ?></strong>
                                            <span class="text-muted">— <?= e($ownerName) ?> · <?= e($storeName) ?> · <?= e($planLabel) ?></span>
                                        </span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-text">You can select multiple email addresses.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="campaign_subject">Email subject</label>
                    <input
                        type="text"
                        class="form-control"
                        id="campaign_subject"
                        name="subject"
                        maxlength="255"
                        required
                        value="<?= e($subjectVal) ?>"
                        placeholder="Example: Subscription reminder"
                    >
                </div>
                <div class="col-12">
                    <label class="form-label" for="campaign_html_body">HTML template body</label>
                    <textarea
                        class="form-control font-monospace"
                        id="campaign_html_body"
                        name="html_body"
                        rows="14"
                        required
                        placeholder="<h2>Hello {{owner_name}}</h2><p>Your store {{store_name}} has an update.</p>"
                    ><?= e($htmlBodyVal) ?></textarea>
                    <div class="form-text">
                        Full HTML is allowed. This will be sent to all recipients in the selected segment.
                    </div>
                </div>
            </div>
            <div class="pt-1">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i>Send campaign email
                </button>
            </div>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm mt-3">
    <div class="card-body p-3 p-md-4">
        <h6 class="mb-2">Recent campaign logs</h6>
        <?php if ($campaignLogs === []): ?>
            <p class="small text-muted mb-0">No campaign logs yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Sender</th>
                        <th>Mode</th>
                        <th>Segment</th>
                        <th>Subject</th>
                        <th class="text-end">Total</th>
                        <th class="text-end text-success">Sent</th>
                        <th class="text-end text-danger">Failed</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($campaignLogs as $log): ?>
                        <?php
                        $senderName = trim((string) ($log['sender_name'] ?? ''));
                        $senderEmail = trim((string) ($log['sender_email'] ?? ''));
                        $senderLabel = $senderName !== '' ? $senderName : ($senderEmail !== '' ? $senderEmail : 'Super Admin');
                        $mode = trim((string) ($log['recipient_mode'] ?? 'segment'));
                        $segment = trim((string) ($log['recipient_segment'] ?? ''));
                        $createdAtRaw = trim((string) ($log['created_at'] ?? ''));
                        $createdAt = $createdAtRaw !== '' ? date('M j, Y g:i A', strtotime($createdAtRaw)) : '—';
                        ?>
                        <tr>
                            <td class="text-nowrap"><?= e($createdAt) ?></td>
                            <td><?= e($senderLabel) ?></td>
                            <td><?= e($mode) ?></td>
                            <td><?= e($segment !== '' ? $segment : '—') ?></td>
                            <td><?= e((string) ($log['subject'] ?? '')) ?></td>
                            <td class="text-end"><?= (int) ($log['recipients_total'] ?? 0) ?></td>
                            <td class="text-end text-success"><?= (int) ($log['sent_count'] ?? 0) ?></td>
                            <td class="text-end text-danger"><?= (int) ($log['failed_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<style>
#campaign_email_list .campaign-email-item {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    cursor: pointer;
}
#campaign_email_list .campaign-email-item .campaign-email-checkbox {
    margin-left: 0 !important;
    margin-top: 0.2rem;
    float: none !important;
    flex: 0 0 auto;
}
#campaign_email_list .campaign-email-item span.small {
    display: inline-block;
    flex: 1 1 auto;
    min-width: 0;
}
</style>
<script>
(() => {
    const modeEl = document.getElementById('campaign_recipient_mode');
    const segmentEl = document.getElementById('campaign_segment');
    const segmentWrapEl = document.getElementById('campaign_segment_wrap');
    const selectedWrapEl = document.getElementById('campaign_selected_emails_wrap');
    const filterEl = document.getElementById('campaign_email_filter');
    const emailListEl = document.getElementById('campaign_email_list');
    const selectAllBtn = document.getElementById('campaign_select_all_btn');
    const clearAllBtn = document.getElementById('campaign_clear_all_btn');

    const syncMode = () => {
        if (!modeEl || !segmentEl || !selectedWrapEl || !segmentWrapEl) return;
        const isSegment = modeEl.value === 'segment';
        segmentEl.required = isSegment;
        segmentWrapEl.classList.toggle('d-none', !isSegment);
        selectedWrapEl.classList.toggle('d-none', isSegment);
    };

    const applyFilter = () => {
        if (!filterEl || !emailListEl) return;
        const keyword = (filterEl.value || '').trim().toLowerCase();
        emailListEl.querySelectorAll('.campaign-email-item').forEach((el) => {
            const searchText = (el.getAttribute('data-search') || '').toLowerCase();
            el.classList.toggle('d-none', keyword !== '' && !searchText.includes(keyword));
        });
    };

    if (modeEl) {
        modeEl.addEventListener('change', syncMode);
        syncMode();
    }
    if (filterEl) {
        filterEl.addEventListener('input', applyFilter);
    }
    if (selectAllBtn && emailListEl) {
        selectAllBtn.addEventListener('click', () => {
            emailListEl.querySelectorAll('.campaign-email-item:not(.d-none) .campaign-email-checkbox').forEach((el) => {
                el.checked = true;
            });
        });
    }
    if (clearAllBtn && emailListEl) {
        clearAllBtn.addEventListener('click', () => {
            emailListEl.querySelectorAll('.campaign-email-checkbox').forEach((el) => {
                el.checked = false;
            });
        });
    }
})();
</script>
