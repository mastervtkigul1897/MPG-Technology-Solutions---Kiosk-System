<?php
?>

<div class="card mt-3">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h6 class="mb-0">Products out today</h6>
            <div class="d-flex align-items-center gap-2">
                <input type="date" id="dailyOutsDate" class="form-control form-control-sm" value="<?= e(date('Y-m-d')) ?>" style="max-width: 10.5rem;">
                <button type="button" class="btn btn-sm btn-outline-primary" id="dailyOutsRefresh">Load</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-end" style="width: 140px;">Qty sold</th>
                </tr>
                </thead>
                <tbody id="dailyOutsTbody">
                <tr><td colspan="2" class="text-muted text-center py-3">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="small text-muted mt-2">Based on completed transactions for the selected date.</div>
    </div>
</div>

<script>
(() => {
    const urlBase = <?= json_encode(url('/tenant/reports/daily-outs')) ?>;
    const tb = document.getElementById('dailyOutsTbody');
    const dateEl = document.getElementById('dailyOutsDate');
    const btn = document.getElementById('dailyOutsRefresh');
    const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    const load = async () => {
        const d = dateEl?.value || '';
        tb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Loading…</td></tr>';
        try {
            const u = new URL(urlBase, window.location.origin);
            if (d) u.searchParams.set('date', d);
            const res = await fetch(u.toString(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.success) {
                tb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Could not load daily outs.</td></tr>';
                return;
            }
            const rows = Array.isArray(body.data) ? body.data : [];
            if (!rows.length) {
                tb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">No sales for this date.</td></tr>';
                return;
            }
            tb.innerHTML = rows.map(r => `<tr><td>${esc(r.product_name)}</td><td class="text-end fw-semibold">${Number(r.qty||0)}</td></tr>`).join('');
        } catch {
            tb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Could not load daily outs.</td></tr>';
        }
    };

    btn?.addEventListener('click', load);
    dateEl?.addEventListener('change', load);
    load();
})();
</script>
