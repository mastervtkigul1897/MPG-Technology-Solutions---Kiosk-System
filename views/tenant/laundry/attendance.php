<?php
$attendanceLocked = ! empty($attendance_locked) || ! empty($premium_trial_browse_lock);
?>
<?php if ($attendanceLocked): ?>
    <div class="alert alert-warning small py-2">
        Attendance is Premium-only after the 7-day trial. You can view records, but editing and OT approval are disabled.
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <h6 class="mb-2">Attendance</h6>
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">From</label>
                <input class="form-control" type="date" name="from" value="<?= e((string) ($range_from ?? date('Y-m-d'))) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">To</label>
                <input class="form-control" type="date" name="to" value="<?= e((string) ($range_to ?? date('Y-m-d'))) ?>">
            </div>
            <div class="col-12 col-md-3">
                <button class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>Date</th>
                <th>Staff</th>
                <th>Time in</th>
                <th>Time in image</th>
                <th>Time out</th>
                <th>Time out image</th>
                <th>Hours rendered</th>
                <th>Class</th>
                <th>OT</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($rows ?? []) as $row): ?>
                <?php
                $inPhoto = trim((string) ($row['clock_in_photo_path'] ?? ''));
                $outPhoto = trim((string) ($row['clock_out_photo_path'] ?? ''));
                ?>
                <tr>
                    <td><?= e((string) ($row['attendance_date'] ?? '')) ?></td>
                    <td><?= e((string) ($row['staff_name'] ?? '')) ?></td>
                    <td><?= e((string) ($row['time_in'] ?? '—')) ?></td>
                    <td>
                        <?php if ($inPhoto !== ''): ?>
                            <button type="button" class="btn p-0 border-0 bg-transparent js-attendance-photo-preview-trigger" data-photo-src="<?= e(url($inPhoto)) ?>" aria-label="View time-in photo">
                                <img src="<?= e(url($inPhoto)) ?>" alt="Time in" class="rounded border" style="width:44px;height:44px;object-fit:cover;">
                            </button>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) ($row['time_out'] ?? '—')) ?></td>
                    <td>
                        <?php if ($outPhoto !== ''): ?>
                            <button type="button" class="btn p-0 border-0 bg-transparent js-attendance-photo-preview-trigger" data-photo-src="<?= e(url($outPhoto)) ?>" aria-label="View time-out photo">
                                <img src="<?= e(url($outPhoto)) ?>" alt="Time out" class="rounded border" style="width:44px;height:44px;object-fit:cover;">
                            </button>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) ($row['hours_rendered'] ?? '0h 0m')) ?></td>
                    <td><span class="badge text-bg-light text-dark border"><?= e((string) ($row['classification'] ?? 'Not counted')) ?></span></td>
                    <td class="small">
                        <?php if ((int) ($row['overtime_minutes'] ?? 0) >= 30): ?>
                            <?= (int) ($row['overtime_minutes'] ?? 0) ?> min
                            <span class="badge <?= ($row['overtime_status'] ?? '') === 'approved' ? 'text-bg-success' : 'text-bg-warning text-dark' ?>">
                                <?= e((string) ($row['overtime_status'] ?? 'pending')) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?= e((string) (($row['note'] ?? '') !== '' ? $row['note'] : '—')) ?>
                        <?php if (! empty($row['is_edited'])): ?>
                            <div class="text-warning">Edited: <?= e((string) ($row['edit_reason'] ?? '')) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php $rid = (int) ($row['id'] ?? 0); ?>
                        <button type="button" class="btn btn-sm btn-outline-primary js-attendance-edit"
                            data-action="<?= e(route('tenant.attendance.update', ['id' => $rid])) ?>"
                            data-in="<?= e((string) ($row['time_in'] ?? '')) ?>"
                            data-out="<?= e((string) ($row['time_out'] ?? '')) ?>"
                            <?= $attendanceLocked ? 'disabled' : '' ?>>
                            Edit
                        </button>
                        <?php if ((string) ($row['overtime_status'] ?? '') === 'pending'): ?>
                            <?php if ($attendanceLocked): ?>
                                <button type="button" class="btn btn-sm btn-outline-success" disabled>Approve OT</button>
                            <?php else: ?>
                                <form method="POST" action="<?= e(route('tenant.attendance.approve-ot', ['id' => $rid])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-success">Approve OT</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="attendanceEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content" id="attendanceEditForm">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h6 class="modal-title">Edit attendance</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label mb-1" for="attendanceEditIn">Time in</label>
                    <input class="form-control" type="datetime-local" id="attendanceEditIn" name="clock_in_at" required>
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1" for="attendanceEditOut">Time out</label>
                    <input class="form-control" type="datetime-local" id="attendanceEditOut" name="clock_out_at">
                </div>
                <div>
                    <label class="form-label mb-1" for="attendanceEditReason">Reason</label>
                    <textarea class="form-control" id="attendanceEditReason" name="edit_reason" required rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" <?= $attendanceLocked ? 'disabled' : '' ?>>Save override</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="attendancePhotoPreviewModal" tabindex="-1" aria-labelledby="attendancePhotoPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="attendancePhotoPreviewModalLabel">Attendance photo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="attendancePhotoPreviewImage" src="" alt="Attendance preview" class="img-fluid rounded border" style="max-height:72vh;object-fit:contain;">
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const modalEl = document.getElementById('attendancePhotoPreviewModal');
    const imageEl = document.getElementById('attendancePhotoPreviewImage');
    if (!modalEl || !imageEl || typeof bootstrap === 'undefined') {
        return;
    }
    const previewModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.querySelectorAll('.js-attendance-photo-preview-trigger').forEach((btn) => {
        btn.addEventListener('click', () => {
            const src = btn.getAttribute('data-photo-src') || '';
            if (src === '') {
                return;
            }
            imageEl.src = src;
            previewModal.show();
        });
    });
    modalEl.addEventListener('hidden.bs.modal', () => {
        imageEl.src = '';
    });
})();

(() => {
    const modalEl = document.getElementById('attendanceEditModal');
    const form = document.getElementById('attendanceEditForm');
    const inEl = document.getElementById('attendanceEditIn');
    const outEl = document.getElementById('attendanceEditOut');
    const reasonEl = document.getElementById('attendanceEditReason');
    if (!modalEl || !form || !inEl || !outEl || typeof bootstrap === 'undefined') return;
    const toLocal = (raw) => {
        const s = String(raw || '').trim();
        if (!s || s === '—') return '';
        return s.replace(' ', 'T').slice(0, 16);
    };
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.querySelectorAll('.js-attendance-edit').forEach((btn) => {
        btn.addEventListener('click', () => {
            form.action = btn.getAttribute('data-action') || '';
            inEl.value = toLocal(btn.getAttribute('data-in'));
            outEl.value = toLocal(btn.getAttribute('data-out'));
            if (reasonEl) reasonEl.value = '';
            modal.show();
        });
    });
})();
</script>
