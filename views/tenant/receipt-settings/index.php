<?php /** @var array<string,string> $receipt */ ?>
<div class="row g-3">
    <div class="col-12">
        <div class="alert alert-info mb-0">
            These fields are printed on transaction receipts. Keep them clear so customers can identify and contact your store.
        </div>
    </div>
    <div class="col-12">
        <div class="card border-primary border-opacity-25">
            <div class="card-body">
                <h6 class="mb-3">Receipt Data</h6>
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
                        <label class="form-label" for="receipt_lan_print_copies">Wi‑Fi / LAN copies per tap</label>
                        <input type="number" class="form-control" id="receipt_lan_print_copies" name="receipt_lan_print_copies"
                               min="1" max="10" step="1" inputmode="numeric"
                               value="<?= e((string) max(1, min(10, (int) ($receipt['receipt_lan_print_copies'] ?? 1)))) ?>">
                        <div class="form-text">How many receipts the server prints to the network thermal printer each time you tap <strong>Wi‑Fi / LAN</strong> on POS or Transactions (e.g. 2 for counter + kitchen). Range 1–10. Default 1.</div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save Receipt Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
