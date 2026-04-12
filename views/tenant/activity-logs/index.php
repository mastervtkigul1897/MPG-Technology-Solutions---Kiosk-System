<?php $u = auth_user(); ?>
<?php if (($u['role'] ?? '') === 'tenant_admin'): ?>
<div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
    <form method="POST" action="<?= e(url('/tenant/activity-logs')) ?>" class="d-inline" onsubmit="return confirm('Delete all activity logs for this store? This cannot be undone.');">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-sm btn-outline-danger">Clear all logs</button>
    </form>
</div>
<?php endif; ?>
<div class="card border-0 shadow-sm">
    <div class="card-body table-responsive p-3 p-md-4">
        <table class="table table-striped w-100" id="activityLogsTable">
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Method</th>
                    <th>Description</th>
                    <th class="text-end"><?= (($u['role'] ?? '') === 'tenant_admin') ? 'Actions' : '' ?></th>
                </tr>
            </thead>
        </table>
    </div>
</div>
<script>
(() => {
    initServerDataTable('#activityLogsTable', {
        printButton: true,
        ajax: {
            url: '<?= e(route('tenant.activity-logs.index')) ?>',
            data: { datatable: 1 }
        },
        columnDefs: [
            { targets: 0, className: 'dtr-control', orderable: false, searchable: false, defaultContent: '', responsivePriority: 1 },
            { targets: 1, responsivePriority: 100 },
            { targets: 2, responsivePriority: 2 },
            { targets: 3, responsivePriority: 40 },
            { targets: 4, responsivePriority: 35 },
            { targets: 5, responsivePriority: 30 },
            { targets: 6, responsivePriority: 60 },
            { targets: 7, responsivePriority: 65 },
            { targets: 8, responsivePriority: 70 },
            { targets: 9, responsivePriority: 25 },
            { targets: 10, orderable: false, searchable: false, defaultContent: '', responsivePriority: 5 },
        ],
        columns: [
            { data: null },
            { data: 'id' },
            { data: 'date' },
            { data: 'module' },
            { data: 'action' },
            { data: 'user' },
            { data: 'email' },
            { data: 'role' },
            { data: 'method' },
            { data: 'description' },
            { data: 'actions', defaultContent: '' },
        ],
    });
})();
</script>
