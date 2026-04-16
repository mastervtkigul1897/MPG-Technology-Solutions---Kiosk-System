<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantReceiptFields;
use PDO;

final class ReceiptSettingsController
{
    public function edit(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || ($user['role'] ?? '') !== 'tenant_admin' || empty($user['tenant_id'])) {
            return new Response('Forbidden.', 403);
        }

        $pdo = App::db();
        TenantReceiptFields::ensure($pdo);
        $st = $pdo->prepare(
            'SELECT name, receipt_display_name, receipt_business_style, receipt_tax_id, receipt_phone, receipt_address, receipt_email, receipt_footer_note, receipt_lan_print_copies,
                    receipt_escpos_line_width, receipt_escpos_right_col_width, receipt_escpos_extra_feeds, receipt_escpos_cut_mode,
                    receipt_serial_number, receipt_vat_applicable, receipt_dti_number, receipt_tax_type,
                    receipt_is_bir_registered, receipt_bir_accreditation_no, receipt_min, receipt_permit_no
             FROM tenants
             WHERE id = ?
             LIMIT 1'
        );
        $st->execute([(int) $user['tenant_id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return view_page('Receipt Config', 'tenant.receipt-settings.index', [
            'receipt' => [
                'store_name' => (string) ($row['name'] ?? ''),
                'receipt_display_name' => (string) ($row['receipt_display_name'] ?? ''),
                'receipt_business_style' => (string) ($row['receipt_business_style'] ?? ''),
                'receipt_tax_id' => (string) ($row['receipt_tax_id'] ?? ''),
                'receipt_phone' => (string) ($row['receipt_phone'] ?? ''),
                'receipt_address' => (string) ($row['receipt_address'] ?? ''),
                'receipt_email' => (string) ($row['receipt_email'] ?? ''),
                'receipt_footer_note' => (string) ($row['receipt_footer_note'] ?? ''),
                'receipt_lan_print_copies' => (int) ($row['receipt_lan_print_copies'] ?? 1),
                'receipt_escpos_line_width' => (int) ($row['receipt_escpos_line_width'] ?? 32),
                'receipt_escpos_right_col_width' => (int) ($row['receipt_escpos_right_col_width'] ?? 10),
                'receipt_escpos_extra_feeds' => (int) ($row['receipt_escpos_extra_feeds'] ?? 8),
                'receipt_escpos_cut_mode' => (string) ($row['receipt_escpos_cut_mode'] ?? 'none'),
                'receipt_serial_number' => (string) ($row['receipt_serial_number'] ?? ''),
                'receipt_vat_applicable' => (int) ($row['receipt_vat_applicable'] ?? 1) === 1,
                'receipt_dti_number' => (string) ($row['receipt_dti_number'] ?? ''),
                'receipt_tax_type' => (string) ($row['receipt_tax_type'] ?? 'non_vat'),
                'receipt_is_bir_registered' => (int) ($row['receipt_is_bir_registered'] ?? 0) === 1,
                'receipt_bir_accreditation_no' => (string) ($row['receipt_bir_accreditation_no'] ?? ''),
                'receipt_min' => (string) ($row['receipt_min'] ?? ''),
                'receipt_permit_no' => (string) ($row['receipt_permit_no'] ?? ''),
            ],
            'premium_trial_browse_lock' => Auth::isTenantFreeTrial($user),
        ]);
    }

    public function update(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || ($user['role'] ?? '') !== 'tenant_admin' || empty($user['tenant_id'])) {
            return new Response('Forbidden.', 403);
        }
        if (Auth::isTenantFreeTrial($user)) {
            session_flash('errors', ['Premium: saving receipt config is not available on a Free Trial.']);

            return redirect(route('tenant.receipt-settings.edit'));
        }

        $displayName = trim((string) $request->input('receipt_display_name'));
        $businessStyle = trim((string) $request->input('receipt_business_style'));
        $taxId = trim((string) $request->input('receipt_tax_id'));
        $phone = trim((string) $request->input('receipt_phone'));
        $address = trim((string) $request->input('receipt_address'));
        $email = trim((string) $request->input('receipt_email'));
        $footerNote = trim((string) $request->input('receipt_footer_note'));
        $lanCopies = (int) $request->input('receipt_lan_print_copies', 1);
        $escposLineWidth = (int) $request->input('receipt_escpos_line_width', 32);
        $escposRightColWidth = (int) $request->input('receipt_escpos_right_col_width', 10);
        $escposExtraFeeds = (int) $request->input('receipt_escpos_extra_feeds', 8);
        $escposCutMode = strtolower(trim((string) $request->input('receipt_escpos_cut_mode', 'none')));
        $receiptSerialNumber = trim((string) $request->input('receipt_serial_number', ''));
        $receiptTaxType = strtolower(trim((string) $request->input('receipt_tax_type', 'non_vat')));
        if (! in_array($receiptTaxType, ['vat', 'non_vat'], true)) {
            $receiptTaxType = 'non_vat';
        }
        $isBirRegistered = $request->boolean('receipt_is_bir_registered');
        $vatApplicable = $isBirRegistered && $receiptTaxType === 'vat';
        $receiptDtiNumber = trim((string) $request->input('receipt_dti_number', ''));
        $birAccreditationNo = trim((string) $request->input('receipt_bir_accreditation_no', ''));
        $receiptMin = trim((string) $request->input('receipt_min', ''));
        $receiptPermitNo = trim((string) $request->input('receipt_permit_no', ''));
        if (! $isBirRegistered) {
            $receiptTaxType = 'non_vat';
        }
        if ($lanCopies < 1) {
            $lanCopies = 1;
        }
        if ($lanCopies > 10) {
            $lanCopies = 10;
        }
        if ($escposLineWidth < 24) {
            $escposLineWidth = 24;
        }
        if ($escposLineWidth > 48) {
            $escposLineWidth = 48;
        }
        if ($escposRightColWidth < 8) {
            $escposRightColWidth = 8;
        }
        if ($escposRightColWidth > 16) {
            $escposRightColWidth = 16;
        }
        if ($escposExtraFeeds < 2) {
            $escposExtraFeeds = 2;
        }
        if ($escposExtraFeeds > 16) {
            $escposExtraFeeds = 16;
        }
        if (! in_array($escposCutMode, ['none', 'partial', 'full'], true)) {
            $escposCutMode = 'none';
        }

        $errors = [];
        if (strlen($displayName) > 255) {
            $errors[] = 'Receipt store name is too long.';
        }
        if (strlen($businessStyle) > 255) {
            $errors[] = 'Business style is too long.';
        }
        if (strlen($taxId) > 100) {
            $errors[] = 'Tax ID/TIN is too long.';
        }
        if (strlen($phone) > 255) {
            $errors[] = 'Receipt phone is too long.';
        }
        if (strlen($email) > 255) {
            $errors[] = 'Receipt email is too long.';
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Receipt email must be a valid email address.';
        }
        if (strlen($receiptSerialNumber) > 100) {
            $errors[] = 'Receipt serial number is too long.';
        }
        if (strlen($receiptDtiNumber) > 100) {
            $errors[] = 'DTI/Business registration number is too long.';
        }
        if (strlen($birAccreditationNo) > 120) {
            $errors[] = 'BIR accreditation number is too long.';
        }
        if (strlen($receiptMin) > 120) {
            $errors[] = 'MIN is too long.';
        }
        if (strlen($receiptPermitNo) > 120) {
            $errors[] = 'Permit number is too long.';
        }
        if ($isBirRegistered && $receiptTaxType === 'vat' && $receiptSerialNumber === '') {
            $errors[] = 'BIR serial number is required when Tax Type is VAT Registered.';
        }
        if (strlen($footerNote) > 1000) {
            $errors[] = 'Footer note is too long (max 1000 characters).';
        }

        if ($errors !== []) {
            session_flash('errors', $errors);

            return redirect(route('tenant.receipt-settings.edit'));
        }

        $pdo = App::db();
        TenantReceiptFields::ensure($pdo);
        $pdo->prepare(
            'UPDATE tenants
             SET receipt_display_name = ?, receipt_business_style = ?, receipt_tax_id = ?, receipt_phone = ?,
                 receipt_address = ?, receipt_email = ?, receipt_footer_note = ?, receipt_lan_print_copies = ?,
                 receipt_escpos_line_width = ?, receipt_escpos_right_col_width = ?, receipt_escpos_extra_feeds = ?, receipt_escpos_cut_mode = ?,
                 receipt_serial_number = ?, receipt_vat_applicable = ?, receipt_dti_number = ?, receipt_tax_type = ?,
                 receipt_is_bir_registered = ?, receipt_bir_accreditation_no = ?, receipt_min = ?, receipt_permit_no = ?,
                 updated_at = NOW()
             WHERE id = ?'
        )->execute([
            $displayName !== '' ? $displayName : null,
            $businessStyle !== '' ? $businessStyle : null,
            $taxId !== '' ? $taxId : null,
            $phone !== '' ? $phone : null,
            $address !== '' ? $address : null,
            $email !== '' ? $email : null,
            $footerNote !== '' ? $footerNote : null,
            $lanCopies,
            $escposLineWidth,
            $escposRightColWidth,
            $escposExtraFeeds,
            $escposCutMode,
            $receiptSerialNumber !== '' ? $receiptSerialNumber : null,
            $vatApplicable ? 1 : 0,
            $receiptDtiNumber !== '' ? $receiptDtiNumber : null,
            $receiptTaxType,
            $isBirRegistered ? 1 : 0,
            $birAccreditationNo !== '' ? $birAccreditationNo : null,
            $receiptMin !== '' ? $receiptMin : null,
            $receiptPermitNo !== '' ? $receiptPermitNo : null,
            (int) $user['tenant_id'],
        ]);

        session_flash('status', 'Receipt config updated.');

        return redirect(route('tenant.receipt-settings.edit'));
    }
}
