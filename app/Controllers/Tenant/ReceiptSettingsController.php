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
            'SELECT name, receipt_display_name, receipt_business_style, receipt_tax_id, receipt_phone, receipt_address, receipt_email, receipt_footer_note
             FROM tenants
             WHERE id = ?
             LIMIT 1'
        );
        $st->execute([(int) $user['tenant_id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return view_page('Receipt Data', 'tenant.receipt-settings.index', [
            'receipt' => [
                'store_name' => (string) ($row['name'] ?? ''),
                'receipt_display_name' => (string) ($row['receipt_display_name'] ?? ''),
                'receipt_business_style' => (string) ($row['receipt_business_style'] ?? ''),
                'receipt_tax_id' => (string) ($row['receipt_tax_id'] ?? ''),
                'receipt_phone' => (string) ($row['receipt_phone'] ?? ''),
                'receipt_address' => (string) ($row['receipt_address'] ?? ''),
                'receipt_email' => (string) ($row['receipt_email'] ?? ''),
                'receipt_footer_note' => (string) ($row['receipt_footer_note'] ?? ''),
            ],
        ]);
    }

    public function update(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || ($user['role'] ?? '') !== 'tenant_admin' || empty($user['tenant_id'])) {
            return new Response('Forbidden.', 403);
        }

        $displayName = trim((string) $request->input('receipt_display_name'));
        $businessStyle = trim((string) $request->input('receipt_business_style'));
        $taxId = trim((string) $request->input('receipt_tax_id'));
        $phone = trim((string) $request->input('receipt_phone'));
        $address = trim((string) $request->input('receipt_address'));
        $email = trim((string) $request->input('receipt_email'));
        $footerNote = trim((string) $request->input('receipt_footer_note'));

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
                 receipt_address = ?, receipt_email = ?, receipt_footer_note = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([
            $displayName !== '' ? $displayName : null,
            $businessStyle !== '' ? $businessStyle : null,
            $taxId !== '' ? $taxId : null,
            $phone !== '' ? $phone : null,
            $address !== '' ? $address : null,
            $email !== '' ? $email : null,
            $footerNote !== '' ? $footerNote : null,
            (int) $user['tenant_id'],
        ]);

        session_flash('status', 'Receipt data updated.');

        return redirect(route('tenant.receipt-settings.edit'));
    }
}
