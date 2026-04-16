<?php /** @var array<string,string> $receipt */ ?>
<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<div class="row g-3">
    <div class="col-12">
        <div class="alert alert-info mb-0">
            These fields are printed on transaction receipts. Keep them clear so customers can identify and contact your store.
        </div>
    </div>
    <div class="col-12">
        <div class="alert alert-warning mb-0">
            <div class="fw-semibold mb-1">Important compliance disclaimer</div>
            <div class="small mb-0">
                This system generates transaction receipts for internal/business use. Official receipts/invoices must comply with BIR regulations and require proper registration and approval by the business owner.
                Compliance is subject to the National Internal Revenue Code (Tax Code), as amended, including Republic Act No. 10963 (TRAIN Law), and applicable BIR issuances.
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-primary border-opacity-25">
            <div class="card-body">
                <h6 class="mb-3">Receipt Config</h6>
                <form method="POST" action="<?= e(route('tenant.receipt-settings.update')) ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <?= method_field('PATCH') ?>

                    <div class="col-md-6">
                        <label class="form-label" for="store_name_readonly">Store name (from account)</label>
                        <input type="text" id="store_name_readonly" class="form-control" value="<?= e((string) ($receipt['store_name'] ?? '')) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="receipt_display_name">Receipt store name (optional override)</label>
                        <input type="text" class="form-control" id="receipt_display_name" name="receipt_display_name" maxlength="255"
                               value="<?= e((string) ($receipt['receipt_display_name'] ?? '')) ?>" placeholder="e.g. Y3M's Snacks Main Branch">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="receipt_business_style">Business style / line 2</label>
                        <input type="text" class="form-control" id="receipt_business_style" name="receipt_business_style" maxlength="255"
                               value="<?= e((string) ($receipt['receipt_business_style'] ?? '')) ?>" placeholder="e.g. Food Kiosk">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="receipt_tax_id">Tax ID / TIN</label>
                        <input type="text" class="form-control" id="receipt_tax_id" name="receipt_tax_id" maxlength="100"
                               value="<?= e((string) ($receipt['receipt_tax_id'] ?? '')) ?>" placeholder="Optional">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="receipt_serial_number">BIR Serial Number</label>
                        <input type="text" class="form-control" id="receipt_serial_number" name="receipt_serial_number" maxlength="100"
                               value="<?= e((string) ($receipt['receipt_serial_number'] ?? '')) ?>" placeholder="e.g. SN-00012345">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="receipt_dti_number">DTI / Business Registration No.</label>
                        <input type="text" class="form-control" id="receipt_dti_number" name="receipt_dti_number" maxlength="100"
                               value="<?= e((string) ($receipt['receipt_dti_number'] ?? '')) ?>" placeholder="e.g. DTI No. 1234567">
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="receipt_is_bir_registered" name="receipt_is_bir_registered" value="1"
                                   <?= !empty($receipt['receipt_is_bir_registered']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="receipt_is_bir_registered">
                                BIR Registered (Official Receipt mode)
                            </label>
                        </div>
                        <div class="form-text">Enable only if business is properly registered and authorized by BIR.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="receipt_tax_type">Tax Type</label>
                        <?php $taxType = strtolower(trim((string) ($receipt['receipt_tax_type'] ?? 'non_vat'))); ?>
                        <select class="form-select" id="receipt_tax_type" name="receipt_tax_type">
                            <option value="non_vat" <?= $taxType === 'non_vat' ? 'selected' : '' ?>>Non-VAT Registered</option>
                            <option value="vat" <?= $taxType === 'vat' ? 'selected' : '' ?>>VAT Registered</option>
                        </select>
                        <div class="form-text">Enable VAT only if business is registered as VAT with BIR.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="receipt_bir_accreditation_no">BIR Accreditation No.</label>
                        <input type="text" class="form-control" id="receipt_bir_accreditation_no" name="receipt_bir_accreditation_no" maxlength="120"
                               value="<?= e((string) ($receipt['receipt_bir_accreditation_no'] ?? '')) ?>" placeholder="Optional">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="receipt_min">MIN</label>
                        <input type="text" class="form-control" id="receipt_min" name="receipt_min" maxlength="120"
                               value="<?= e((string) ($receipt['receipt_min'] ?? '')) ?>" placeholder="Optional">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="receipt_permit_no">Permit No.</label>
                        <input type="text" class="form-control" id="receipt_permit_no" name="receipt_permit_no" maxlength="120"
                               value="<?= e((string) ($receipt['receipt_permit_no'] ?? '')) ?>" placeholder="Optional">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="receipt_phone">Phone</label>
                        <input type="text" class="form-control" id="receipt_phone" name="receipt_phone" maxlength="255"
                               value="<?= e((string) ($receipt['receipt_phone'] ?? '')) ?>" autocomplete="tel">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="receipt_email">Email (public)</label>
                        <input type="email" class="form-control" id="receipt_email" name="receipt_email" maxlength="255"
                               value="<?= e((string) ($receipt['receipt_email'] ?? '')) ?>" placeholder="Optional" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="receipt_address">Address</label>
                        <textarea class="form-control" id="receipt_address" name="receipt_address" rows="1" maxlength="2000"
                                  placeholder="Store address"><?= e((string) ($receipt['receipt_address'] ?? '')) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="receipt_footer_note">Footer note</label>
                        <textarea class="form-control" id="receipt_footer_note" name="receipt_footer_note" rows="2" maxlength="1000"
                                  placeholder="e.g. Thank you for your purchase!"><?= e((string) ($receipt['receipt_footer_note'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <div class="form-text mt-2">
                            If Tax Type is VAT Registered, BIR Serial Number is required and VAT breakdown will show on receipts.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="receipt_lan_print_copies">Wi‑Fi / LAN / Bluetooth copies per tap</label>
                        <input type="number" class="form-control" id="receipt_lan_print_copies" name="receipt_lan_print_copies"
                               min="1" max="10" step="1" inputmode="numeric"
                               value="<?= e((string) max(1, min(10, (int) ($receipt['receipt_lan_print_copies'] ?? 1)))) ?>">
                        <div class="form-text">How many copies to print each time you tap <strong>Wi‑Fi / LAN</strong> or <strong>Bluetooth print</strong> on POS or Transactions (e.g. 2 for counter + kitchen). Range 1–10. Default 1.</div>
                    </div>
                    <div class="col-12"><hr class="my-2"></div>
                    <div class="col-12">
                        <h6 class="mb-2">Thermal receipt safety config</h6>
                        <div class="small text-muted">These settings are per-tenant and prioritize full receipt printing to avoid clipping.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="receipt_escpos_line_width">Line width (chars)</label>
                        <input type="number" class="form-control" id="receipt_escpos_line_width" name="receipt_escpos_line_width"
                               min="24" max="48" step="1" inputmode="numeric"
                               value="<?= e((string) max(24, min(48, (int) ($receipt['receipt_escpos_line_width'] ?? 32)))) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="receipt_escpos_right_col_width">Right column width</label>
                        <input type="number" class="form-control" id="receipt_escpos_right_col_width" name="receipt_escpos_right_col_width"
                               min="8" max="16" step="1" inputmode="numeric"
                               value="<?= e((string) max(8, min(16, (int) ($receipt['receipt_escpos_right_col_width'] ?? 10)))) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="receipt_escpos_extra_feeds">Extra feeds before cut</label>
                        <input type="number" class="form-control" id="receipt_escpos_extra_feeds" name="receipt_escpos_extra_feeds"
                               min="2" max="16" step="1" inputmode="numeric"
                               value="<?= e((string) max(2, min(16, (int) ($receipt['receipt_escpos_extra_feeds'] ?? 8)))) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="receipt_escpos_cut_mode">Auto-cut mode</label>
                        <?php $cutMode = strtolower(trim((string) ($receipt['receipt_escpos_cut_mode'] ?? 'none'))); ?>
                        <select class="form-select" id="receipt_escpos_cut_mode" name="receipt_escpos_cut_mode">
                            <option value="none" <?= $cutMode === 'none' ? 'selected' : '' ?>>None (safest, no auto-cut)</option>
                            <option value="partial" <?= $cutMode === 'partial' ? 'selected' : '' ?>>Partial cut</option>
                            <option value="full" <?= $cutMode === 'full' ? 'selected' : '' ?>>Full cut</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save Receipt Config</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
