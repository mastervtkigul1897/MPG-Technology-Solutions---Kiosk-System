<?php
/** @var bool $premium_trial_browse_lock */
if (empty($premium_trial_browse_lock)) {
    return;
}
$plansUrl = url('/tenant/plans');
?>
<div id="mpgPremiumTrialBanner" class="alert alert-warning border border-warning shadow-sm mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3" role="status">
    <div class="small mb-0">
        <strong>Premium</strong> — You can browse this area on the Free version, but actions are disabled. Upgrade to use the full module.
    </div>
    <a href="<?= e($plansUrl) ?>" class="btn btn-warning btn-sm fw-semibold text-nowrap"><i class="fa-solid fa-tags me-1"></i>View plans &amp; pricing</a>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var area = document.querySelector('.app-content-area');
    var banner = document.getElementById('mpgPremiumTrialBanner');
    if (!area || !banner) {
        return;
    }
    var wrap = document.createElement('div');
    wrap.className = 'mpg-premium-trial-inert-wrap';
    wrap.setAttribute('inert', '');
    var el = banner.nextElementSibling;
    while (el) {
        var next = el.nextElementSibling;
        wrap.appendChild(el);
        el = next;
    }
    area.appendChild(wrap);
});
</script>
