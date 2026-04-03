<div class="card mb-3">
    <div class="card-body">
        <?php $imgColAvailable = (bool) ($image_path_column_available ?? false); ?>
        <?php
        $existingImageMap = [];
        foreach (($existing_image_paths ?? []) as $imgPath) {
            $p = (string) $imgPath;
            if ($p !== '') {
                $existingImageMap[$p] = url($p);
            }
        }
        ?>
        <?php if (! $imgColAvailable): ?>
            <div class="alert alert-warning small mb-3">
                Product image upload is not active yet. Run SQL file <code>database/add_products_image_path.sql</code> on your database, then reload this page.
            </div>
        <?php endif; ?>
        <form method="POST" action="<?= e(route('tenant.products.store')) ?>" id="createProductForm" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-3">
                <h6 class="mb-2 text-body">Product details</h6>
                <div class="row g-2">
                    <div class="col-12 col-lg-4">
                        <label class="form-label mb-1" for="product_name">Product name</label>
                        <input class="form-control" id="product_name" name="name" required maxlength="255" autocomplete="off">
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label mb-1" for="product_price">Price</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="product_price" name="price" inputmode="decimal" required>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label mb-1" for="product_existing_image_path">Use existing image (optional)</label>
                        <select class="form-select js-existing-image-select" id="product_existing_image_path" name="existing_image_path" <?= $imgColAvailable ? '' : 'disabled' ?>>
                            <option value="">None (use upload or SVG fallback)</option>
                            <?php foreach (($existing_image_paths ?? []) as $i => $imgPath): ?>
                                <?php $pathStr = (string) $imgPath; ?>
                                <option value="<?= e($pathStr) ?>" data-image="<?= e(url($pathStr)) ?>">Image #<?= (int) $i + 1 ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-2 d-none" id="product_existing_image_preview_wrap">
                            <img id="product_existing_image_preview" src="" alt="Selected existing image" class="img-fluid rounded border" style="max-height:72px; object-fit:cover;">
                        </div>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label mb-1" for="product_image">Product image (optional)</label>
                        <input class="form-control" id="product_image" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" <?= $imgColAvailable ? '' : 'disabled' ?>>
                    </div>
                </div>
            </div>
            <hr class="text-secondary opacity-25 my-3">
            <div class="mb-2">
                <h6 class="mb-1 text-body">Resource requirements</h6>
                <p class="small text-muted mb-0">Optional: assign inventory requirements only if this product/service consumes stock per unit sold.</p>
            </div>
            <div id="recipeRows" class="vstack gap-3 mb-3">
                <div class="row g-2 recipe-row align-items-end">
                    <div class="col-12 col-md-8">
                        <label class="form-label mb-1">Inventory item</label>
                        <select class="form-select js-ingredient-select" name="recipe[0][ingredient_id]">
                            <option value="">Select item</option>
                            <?php foreach ($ingredients as $ingredient): ?><option value="<?= (int) $ingredient['id'] ?>"><?= e((string) $ingredient['name']) ?> (<?= e((string) $ingredient['unit']) ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1">Quantity required</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="recipe[0][quantity_required]" inputmode="decimal">
                    </div>
                    <div class="col-12 col-md-1">
                        <label class="form-label mb-1 small text-muted">Remove</label>
                        <button type="button" class="btn btn-danger w-100 remove-row" title="Remove row" aria-label="Remove recipe row"><i class="fa fa-trash"></i></button>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <button type="button" class="btn btn-outline-primary py-2" id="addRecipeRow"><i class="fa fa-plus"></i> Add requirement row</button>
                <button type="submit" class="btn btn-primary py-2"><i class="fa fa-plus"></i> Create product</button>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-striped w-100" id="productsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Requirements</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="editProductModalLabel">Edit product & requirements</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editProductForm" method="POST" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <?= method_field('PUT') ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_product_name">Product name</label>
                        <input class="form-control" id="edit_product_name" name="name" required maxlength="255" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_product_price">Price</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="edit_product_price" name="price" inputmode="decimal" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" id="edit_product_is_active" name="is_active" value="1">
                            <label class="form-check-label" for="edit_product_is_active">Active</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_existing_image_path">Use existing image (optional)</label>
                        <select class="form-select js-existing-image-select" id="edit_existing_image_path" name="existing_image_path" <?= $imgColAvailable ? '' : 'disabled' ?>>
                            <option value="">Keep current / use upload</option>
                            <?php foreach (($existing_image_paths ?? []) as $i => $imgPath): ?>
                                <?php $pathStr = (string) $imgPath; ?>
                                <option value="<?= e($pathStr) ?>" data-image="<?= e(url($pathStr)) ?>">Image #<?= (int) $i + 1 ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-2 d-none" id="edit_existing_image_preview_wrap">
                            <img id="edit_existing_image_preview" src="" alt="Selected existing image" class="img-fluid rounded border" style="max-height:92px; object-fit:cover;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_product_image">Product image (optional)</label>
                        <input class="form-control" id="edit_product_image" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" <?= $imgColAvailable ? '' : 'disabled' ?>>
                        <div class="form-text">Leave blank to keep current image.</div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="edit_remove_image" name="remove_image" value="1" <?= $imgColAvailable ? '' : 'disabled' ?>>
                            <label class="form-check-label" for="edit_remove_image">Remove current image (use SVG fallback)</label>
                        </div>
                    </div>

                    <hr class="text-secondary opacity-25">

                    <div class="mb-2">
                        <h6 class="mb-1">Assign resource requirements</h6>
                        <p class="small text-muted mb-0">Optional: set inventory requirements only for products/services that consume stock.</p>
                    </div>

                    <div id="editRecipeRows" class="vstack gap-3"></div>

                    <button type="button" class="btn btn-outline-primary w-100 mt-2" id="editAddRecipeRow">
                        <i class="fa fa-plus me-1"></i> Add requirement row
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-floppy-disk me-1"></i> Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
/* Keep edit modal usable even with many ingredient rows */
#editProductModal .modal-body {
    max-height: calc(100vh - 220px);
    overflow-y: auto;
}
#editProductModal .modal-footer {
    position: sticky;
    bottom: 0;
    background: #fff;
    border-top: 1px solid #dee2e6;
    z-index: 2;
}

.products-table-thumb {
    width: 52px;
    height: 52px;
    object-fit: cover;
    border-radius: .5rem;
    border: 1px solid #dee2e6;
    background: #f8f9fa;
}
</style>
<?php
$ingredientOptionsHtml = '';
foreach ($ingredients as $i) {
    $ingredientOptionsHtml .= '<option value="'.(int) $i['id'].'">'.e((string) $i['name']).' ('.e((string) $i['unit']).')</option>';
}
?>
<script>
(() => {
    const rows = document.getElementById('recipeRows');
    const addBtn = document.getElementById('addRecipeRow');
    const ingredientOptions = <?= json_embed($ingredientOptionsHtml) ?>;
    const ingredientList = <?= json_embed($ingredients) ?>;
    const existingImageMap = <?= json_embed($existingImageMap) ?>;
    let idx = 1;
    addBtn?.addEventListener('click', () => {
        const div = document.createElement('div');
        div.className = 'row g-2 recipe-row align-items-end';
        div.innerHTML = `<div class="col-12 col-md-8">
                <label class="form-label mb-1">Inventory item</label>
                <select class="form-select js-ingredient-select" name="recipe[${idx}][ingredient_id]"><option value="">Select item</option>${ingredientOptions}</select></div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Quantity required</label>
                <input type="number" step="0.01" min="0.01" class="form-control" name="recipe[${idx}][quantity_required]" inputmode="decimal"></div>
            <div class="col-12 col-md-1">
                <label class="form-label mb-1 small text-muted">Remove</label>
                <button type="button" class="btn btn-danger w-100 remove-row" title="Remove row" aria-label="Remove recipe row"><i class="fa fa-trash"></i></button></div>`;
        rows.appendChild(div);
        $(div).find('.js-ingredient-select').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Select item',
        });
        idx++;
    });
    rows?.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-row');
        if (!btn) return;
        if (rows.querySelectorAll('.recipe-row').length > 1) {
            btn.closest('.recipe-row')?.remove();
        }
    });

    $('.js-ingredient-select').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Select item',
    });

    const buildFallbackSvg = (name) => {
        const clean = String(name || '').trim();
        const letter = (clean.replace(/[^A-Za-z0-9]/g, '').charAt(0) || '?').toUpperCase();
        const safeLetter = letter.replace(/[<>&'"]/g, '');
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">
            <defs>
                <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#0d6efd"/>
                    <stop offset="1" stop-color="#198754"/>
                </linearGradient>
            </defs>
            <rect width="96" height="96" rx="16" fill="url(#g)"/>
            <text x="50%" y="56%" text-anchor="middle" font-family="Arial" font-size="38" font-weight="700" fill="#ffffff">${safeLetter}</text>
        </svg>`;
        return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
    };

    const productImageSrc = (row) => {
        const path = String(row?.image_path || '').trim();
        if (path !== '') {
            return `<?= e(url('/')) ?>/${path}`.replace(/([^:]\/)\/+/g, '$1');
        }
        return buildFallbackSvg(row?.name || '');
    };

    const table = initServerDataTable('#productsTable', {
        ajax: {
            url: '<?= e(route('tenant.products.index')) ?>',
            data: { datatable: 1 }
        },
        columnDefs: [
            { targets: 0, responsivePriority: 100 },
            { targets: 1, className: 'text-center align-middle', orderable: false, searchable: false, responsivePriority: 1 },
            { targets: 2, responsivePriority: 2 },
            { targets: 3, responsivePriority: 3 },
            { targets: 4, responsivePriority: 50 },
            { targets: 5, responsivePriority: 40 },
            { targets: 6, orderable: false, searchable: false, responsivePriority: 4 },
        ],
        columns: [
            { data: 'id' },
            {
                data: null,
                render: (data, type, row) => {
                    if (type !== 'display') return '';
                    const name = String(row?.name || 'Product');
                    const src = productImageSrc(row);
                    return `<img src="${src}" alt="${name}" class="products-table-thumb">`;
                },
            },
            { data: 'name' },
            { data: 'price' },
            { data: 'status' },
            { data: 'ingredients' },
            { data: 'actions' },
        ],
    });

    // ----- Edit product modal (name/price + recipe ingredients) -----
    const editModalEl = document.getElementById('editProductModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    const editForm = document.getElementById('editProductForm');
    const editName = document.getElementById('edit_product_name');
    const editPrice = document.getElementById('edit_product_price');
    const editActive = document.getElementById('edit_product_is_active');
    const editExistingImage = document.getElementById('edit_existing_image_path');
    const editImage = document.getElementById('edit_product_image');
    const editRemoveImage = document.getElementById('edit_remove_image');
    const createExistingImage = document.getElementById('product_existing_image_path');
    const createPreviewWrap = document.getElementById('product_existing_image_preview_wrap');
    const createPreviewImg = document.getElementById('product_existing_image_preview');
    const editPreviewWrap = document.getElementById('edit_existing_image_preview_wrap');
    const editPreviewImg = document.getElementById('edit_existing_image_preview');
    const createUploadInput = document.getElementById('product_image');
    const editRecipeRows = document.getElementById('editRecipeRows');
    const editAddBtn = document.getElementById('editAddRecipeRow');
    const editModalBody = editModalEl?.querySelector('.modal-body') || null;
    const productsBaseUrl = <?= json_encode(url('/tenant/products')) ?>;
    let editIdx = 0;

    const renderExistingImagePreview = (selectEl, wrapEl, imgEl) => {
        if (!selectEl || !wrapEl || !imgEl) return;
        const path = String(selectEl.value || '').trim();
        const src = path !== '' ? (existingImageMap[path] || '') : '';
        if (!src) {
            wrapEl.classList.add('d-none');
            imgEl.removeAttribute('src');
            return;
        }
        imgEl.setAttribute('src', src);
        wrapEl.classList.remove('d-none');
    };

    const formatExistingImageOption = (state) => {
        if (!state.id) return state.text;
        const imgSrc = state.element?.dataset?.image || '';
        if (!imgSrc) return state.text;
        return $(`
            <span class="d-flex align-items-center gap-2">
                <img src="${imgSrc}" alt="" style="width:28px;height:28px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                <span>${state.text}</span>
            </span>
        `);
    };

    const initExistingImageSelect = (selector, dropdownParent = null) => {
        const $el = $(selector);
        if (!$el.length) return;
        $el.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Select existing image',
            allowClear: true,
            dropdownParent: dropdownParent ? $(dropdownParent) : undefined,
            templateResult: formatExistingImageOption,
            templateSelection: formatExistingImageOption,
            escapeMarkup: (m) => m,
        });
    };

    initExistingImageSelect('#product_existing_image_path');
    initExistingImageSelect('#edit_existing_image_path', '#editProductModal');

    createExistingImage?.addEventListener('change', () => {
        if (createExistingImage?.value && createUploadInput) {
            createUploadInput.value = '';
        }
        renderExistingImagePreview(createExistingImage, createPreviewWrap, createPreviewImg);
    });

    createUploadInput?.addEventListener('change', () => {
        if ((createUploadInput.files?.length || 0) > 0 && createExistingImage) {
            createExistingImage.value = '';
        }
        renderExistingImagePreview(createExistingImage, createPreviewWrap, createPreviewImg);
    });

    const renderIngredientOptions = (selectedId) => {
        const escapeHtml = (s) => String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        return ingredientList.map((i) => {
            const sid = String(i.id ?? '');
            const val = String(selectedId ?? '');
            const selected = sid !== '' && sid === val ? 'selected' : '';
            return `<option value=\"${i.id}\" ${selected}>${escapeHtml(i.name ?? '')} (${escapeHtml(i.unit ?? '')})</option>`;
        }).join('');
    };

    const addEditRecipeRow = (item = {}, autoScroll = false) => {
        if (!editRecipeRows) return;
        const selectedIngredientId = item.ingredient_id ?? '';
        const qty = item.quantity_required ?? '0.01';
        const rowHtml = `
            <div class="edit-recipe-row card border-0 shadow-sm">
                <div class="card-body p-2 p-sm-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-8">
                            <label class="form-label mb-1">Inventory item</label>
                            <select class="form-select" name="recipe[${editIdx}][ingredient_id]">
                                <option value="">Select item</option>
                                ${renderIngredientOptions(selectedIngredientId)}
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-1">Quantity required</label>
                            <input type="number" step="0.01" min="0.01" inputmode="decimal" class="form-control"
                                   name="recipe[${editIdx}][quantity_required]" value="${Number(qty).toFixed(2)}">
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-sm btn-danger remove-edit-recipe-row w-100">
                                <i class="fa fa-trash me-1"></i> Remove row
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        editRecipeRows.insertAdjacentHTML('beforeend', rowHtml);
        const addedRow = editRecipeRows.lastElementChild;
        editIdx++;

        // When user adds rows near the bottom, keep the latest row and actions visible.
        if (autoScroll && addedRow) {
            requestAnimationFrame(() => {
                addedRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                if (editModalBody) {
                    editModalBody.scrollTop = editModalBody.scrollHeight;
                }
            });
        }
    };

    const renderEditRecipe = (recipe = []) => {
        if (!editRecipeRows) return;
        editRecipeRows.innerHTML = '';
        editIdx = 0;

        const list = Array.isArray(recipe) ? recipe : [];
        if (!list.length) {
            addEditRecipeRow({});
            return;
        }

        list.forEach((item) => addEditRecipeRow(item));
    };

    if (editAddBtn) {
        editAddBtn.addEventListener('click', () => addEditRecipeRow({}, true));
    }

    editRecipeRows?.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-edit-recipe-row');
        if (!btn) return;
        const row = btn.closest('.edit-recipe-row');
        row?.remove();
    });

    $('#productsTable tbody').on('click', '.js-edit-product', function (ev) {
        ev.preventDefault();
        const tr = this.closest('tr');
        if (!tr) return;
        if (!table) return;
        const rowData = table.row(tr).data();
        if (!rowData) return;

        const id = rowData.id;
        if (!id) return;

        if (editForm) {
            editForm.action = `${productsBaseUrl}/${id}`;
        }
        if (editName) editName.value = rowData.name ?? '';
        if (editPrice) editPrice.value = rowData.price ?? '0.00';
        if (editActive) editActive.checked = !!rowData.is_active;
        if (editExistingImage) {
            const imgPath = rowData.image_path ?? '';
            if ($(editExistingImage).data('select2')) {
                $(editExistingImage).val(imgPath).trigger('change');
            } else {
                editExistingImage.value = imgPath;
            }
        }
        if (editImage) editImage.value = '';
        if (editRemoveImage) editRemoveImage.checked = false;
        renderExistingImagePreview(editExistingImage, editPreviewWrap, editPreviewImg);

        renderEditRecipe(rowData.recipe ?? []);

        editModal?.show();
    });

    editExistingImage?.addEventListener('change', () => {
        if (editExistingImage?.value && editImage) {
            editImage.value = '';
        }
        if (editExistingImage?.value && editRemoveImage) {
            editRemoveImage.checked = false;
        }
        renderExistingImagePreview(editExistingImage, editPreviewWrap, editPreviewImg);
    });

    editImage?.addEventListener('change', () => {
        if ((editImage.files?.length || 0) > 0 && editExistingImage) {
            editExistingImage.value = '';
        }
        renderExistingImagePreview(editExistingImage, editPreviewWrap, editPreviewImg);
    });

})();
</script>
