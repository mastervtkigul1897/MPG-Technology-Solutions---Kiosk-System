<?php $freeInventoryLimit = isset($free_inventory_limit) ? (int) $free_inventory_limit : 0; ?>
<?php if ($freeInventoryLimit > 0): ?>
<div class="alert alert-info mb-3 border-0 shadow-sm">
    <strong>Free-limited:</strong> inventory table shows up to <?= $freeInventoryLimit ?> items.
    Items beyond this are restricted until upgrade.
</div>
<?php endif; ?>
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h6>Add inventory item</h6>
                <form method="POST" action="<?= e(route('tenant.laundry-inventory.store')) ?>" class="row g-2" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Item name</label>
                        <input class="form-control" name="name" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Category</label>
                        <select class="form-select" name="category" required>
                            <option value="detergent">Detergent</option>
                            <option value="fabcon">Fabcon</option>
                            <option value="machine_cleaner">Machine Cleaner</option>
                            <option value="bleach">Bleach</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Unit</label>
                        <input class="form-control" name="unit" value="pcs" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Show item in</label>
                        <select class="form-select" name="show_item_in" required>
                            <option value="both" selected>Inclusion + Add-on</option>
                            <option value="inclusion">Inclusion only</option>
                            <option value="addon">Add-on only</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Quantity</label>
                        <input class="form-control" name="stock_quantity" type="number" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Low stock</label>
                        <input class="form-control" name="low_stock_threshold" type="number" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Unit cost</label>
                        <input class="form-control" name="unit_cost" type="number" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Item image</label>
                        <input class="form-control" name="image_file" type="file" accept="image/png,image/jpeg,image/webp">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Save item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h6>Add stock</h6>
                <form method="POST" action="<?= e(route('tenant.laundry-inventory.purchase')) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Item</label>
                        <select class="form-select" name="item_id" id="inventoryStockItemSelect" required>
                            <option value="">Select item</option>
                            <?php foreach (($items ?? []) as $item): ?>
                                <option value="<?= (int) ($item['id'] ?? 0) ?>"><?= e((string) ($item['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Quantity</label>
                        <input class="form-control" name="quantity" id="inventoryStockQty" type="number" min="0.01" step="0.01" value="1" required>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-success" type="submit" name="stock_action" value="add">Add stock</button>
                            <button class="btn btn-danger" type="submit" name="stock_action" value="reduce">Reduce stock</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>Image</th>
                <th>Item</th>
                <th>Category</th>
                <th>Show item in</th>
                <th>Unit</th>
                <th class="text-end">Stock</th>
                <th class="text-end">Low stock threshold</th>
                <th class="text-end">Unit cost</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php $freeRowIndex = 0; ?>
            <?php foreach (($items ?? []) as $item): ?>
                <?php
                $stock = (float) ($item['stock_quantity'] ?? 0);
                $threshold = (float) ($item['low_stock_threshold'] ?? 0);
                $freeRowIndex++;
                ?>
                <tr class="<?= $stock <= $threshold ? 'table-danger' : '' ?>">
                    <td>
                        <?php $imgPath = trim((string) ($item['image_path'] ?? '')); ?>
                        <?php if ($imgPath !== ''): ?>
                            <img src="<?= e(url($imgPath)) ?>" alt="<?= e((string) ($item['name'] ?? 'Item')) ?>" class="rounded border" style="width:44px;height:44px;object-fit:cover;">
                        <?php else: ?>
                            <span class="badge text-bg-light border">No image</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e((string) ($item['name'] ?? '')) ?>
                        <?php if ((int) ($item['is_system_item'] ?? 0) === 1): ?>
                            <button
                                type="button"
                                class="btn btn-link btn-sm p-0 ms-1 align-baseline js-system-item-info"
                                data-item-name="<?= e((string) ($item['name'] ?? 'Item')) ?>"
                                title="System item info"
                                aria-label="System item info"
                            >
                                <i class="fa-solid fa-circle-info"></i>
                            </button>
                        <?php endif; ?>
                        <?php if ($freeInventoryLimit > 0 && $freeRowIndex <= $freeInventoryLimit): ?>
                            <span class="badge text-bg-warning text-dark ms-1">Free-limited</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(ucwords(str_replace('_', ' ', (string) ($item['category'] ?? 'other')))) ?></td>
                    <td><?= e(ucwords(str_replace('_', ' ', (string) ($item['show_item_in'] ?? 'both')))) ?></td>
                    <td><?= e((string) ($item['unit'] ?? '')) ?></td>
                    <td class="text-end"><?= e(format_stock($stock)) ?></td>
                    <td class="text-end"><?= e(format_stock($threshold)) ?></td>
                    <td class="text-end"><?= e(format_money((float) ($item['unit_cost'] ?? 0))) ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary js-edit-item"
                                data-id="<?= (int) ($item['id'] ?? 0) ?>"
                                data-name="<?= e((string) ($item['name'] ?? '')) ?>"
                                data-category="<?= e((string) ($item['category'] ?? 'other')) ?>"
                                data-unit="<?= e((string) ($item['unit'] ?? 'pcs')) ?>"
                                data-show-item-in="<?= e((string) ($item['show_item_in'] ?? 'both')) ?>"
                                data-low-stock-threshold="<?= e((string) ($item['low_stock_threshold'] ?? '0')) ?>"
                                data-unit-cost="<?= e((string) ($item['unit_cost'] ?? '0')) ?>"
                                data-image-path="<?= e((string) ($item['image_path'] ?? '')) ?>"
                                title="Edit item"
                            ><i class="fa fa-pen"></i></button>
                            <?php if ((int) ($item['is_system_item'] ?? 0) === 1): ?>
                                <button class="btn btn-sm btn-outline-secondary" type="button" title="System required item" disabled><i class="fa fa-lock"></i></button>
                            <?php else: ?>
                                <form method="POST" action="<?= e(route('tenant.laundry-inventory.destroy', ['id' => (int) ($item['id'] ?? 0)])) ?>" onsubmit="return confirm('Delete this inventory item?');">
                                    <?= csrf_field() ?>
                                    <?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete item"><i class="fa fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="inventoryEditModal" tabindex="-1" aria-labelledby="inventoryEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="inventoryEditForm" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <?= method_field('PUT') ?>
                <div class="modal-header">
                    <h6 class="modal-title" id="inventoryEditModalLabel">Edit inventory item</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label mb-1" for="inventoryEditName">Item name</label>
                        <input class="form-control" id="inventoryEditName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1" for="inventoryEditCategory">Category</label>
                        <select class="form-select" id="inventoryEditCategory" name="category" required>
                            <option value="detergent">Detergent</option>
                            <option value="fabcon">Fabcon</option>
                            <option value="machine_cleaner">Machine Cleaner</option>
                            <option value="bleach">Bleach</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1" for="inventoryEditUnit">Unit</label>
                        <input class="form-control" id="inventoryEditUnit" name="unit" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1" for="inventoryEditShowItemIn">Show item in</label>
                        <select class="form-select" id="inventoryEditShowItemIn" name="show_item_in" required>
                            <option value="both">Inclusion + Add-on</option>
                            <option value="inclusion">Inclusion only</option>
                            <option value="addon">Add-on only</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label mb-1" for="inventoryEditLowStock">Low stock threshold</label>
                        <input class="form-control" id="inventoryEditLowStock" name="low_stock_threshold" type="number" min="0" step="0.01" value="0">
                    </div>
                    <div class="mt-3">
                        <label class="form-label mb-1" for="inventoryEditUnitCost">Unit cost</label>
                        <input class="form-control" id="inventoryEditUnitCost" name="unit_cost" type="number" min="0" step="0.01" value="0">
                    </div>
                    <div class="mt-3">
                        <img id="inventoryEditPreview" src="" alt="Inventory image preview" class="rounded border d-none" style="width:64px;height:64px;object-fit:cover;">
                    </div>
                    <div class="mt-2">
                        <label class="form-label mb-1" for="inventoryEditImageFile">Replace image</label>
                        <input class="form-control" id="inventoryEditImageFile" name="image_file" type="file" accept="image/png,image/jpeg,image/webp">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(() => {
    const itemSel = document.getElementById('inventoryStockItemSelect');
    const qty = document.getElementById('inventoryStockQty');
    const editModalEl = document.getElementById('inventoryEditModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    const editForm = document.getElementById('inventoryEditForm');
    const editName = document.getElementById('inventoryEditName');
    const editCategory = document.getElementById('inventoryEditCategory');
    const editUnit = document.getElementById('inventoryEditUnit');
    const editShowItemIn = document.getElementById('inventoryEditShowItemIn');
    const editLow = document.getElementById('inventoryEditLowStock');
    const editUnitCost = document.getElementById('inventoryEditUnitCost');
    const editPreview = document.getElementById('inventoryEditPreview');
    const baseUrl = '<?= e(url('/tenant/laundry-inventory/items')) ?>';
    if (itemSel && qty) {
        itemSel.addEventListener('change', () => {
            if (itemSel.value !== '') {
                qty.value = '1';
            }
        });
    }
    document.querySelectorAll('.js-edit-item').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            if (!id || !editForm) return;
            editForm.action = `${baseUrl}/${id}`;
            if (editName) editName.value = btn.getAttribute('data-name') || '';
            if (editCategory) editCategory.value = btn.getAttribute('data-category') || 'other';
            if (editUnit) editUnit.value = btn.getAttribute('data-unit') || 'pcs';
            if (editShowItemIn) editShowItemIn.value = btn.getAttribute('data-show-item-in') || 'both';
            if (editLow) editLow.value = btn.getAttribute('data-low-stock-threshold') || '0';
            if (editUnitCost) editUnitCost.value = btn.getAttribute('data-unit-cost') || '0';
            if (editPreview) {
                const p = btn.getAttribute('data-image-path') || '';
                if (p) {
                    editPreview.src = `<?= e(rtrim(url('/'), '/')) ?>/${p.replace(/^\/+/, '')}`;
                    editPreview.classList.remove('d-none');
                } else {
                    editPreview.src = '';
                    editPreview.classList.add('d-none');
                }
            }
            editModal?.show();
        });
    });
    document.querySelectorAll('.js-system-item-info').forEach((btn) => {
        btn.addEventListener('click', () => {
            const itemName = btn.getAttribute('data-item-name') || 'This item';
            if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
                Swal.fire({
                    icon: 'info',
                    title: 'System item',
                    text: `${itemName} is required by the system. You can edit it, but you cannot delete it.`,
                    confirmButtonText: 'OK',
                });
                return;
            }
            window.alert(`${itemName} is required by the system. You can edit it, but you cannot delete it.`);
        });
    });
})();
</script>
