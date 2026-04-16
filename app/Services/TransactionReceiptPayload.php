<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/** Builds the receipt JSON used by POS, transactions, and thermal printers. */
final class TransactionReceiptPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function build(PDO $pdo, int $tenantId, int $transactionId): array
    {
        $st = $pdo->prepare(
            'SELECT name, receipt_display_name, receipt_business_style, receipt_tax_id, receipt_phone, receipt_address, receipt_email, receipt_footer_note,
                    receipt_escpos_line_width, receipt_escpos_right_col_width, receipt_escpos_extra_feeds, receipt_escpos_cut_mode,
                    receipt_serial_number, receipt_vat_applicable, receipt_dti_number, receipt_tax_type,
                    receipt_is_bir_registered, receipt_bir_accreditation_no, receipt_min, receipt_permit_no
             FROM tenants
             WHERE id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId]);
        $tenant = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $st = $pdo->prepare(
            'SELECT t.total_amount, t.original_total_amount, t.amount_tendered, t.change_amount, t.payment_method, t.amount_paid, t.payment_breakdown_json,
                    t.refunded_amount, t.added_paid_amount, t.created_at, t.status, t.pending_name, t.pending_contact, t.user_id, u.name AS cashier_name
             FROM transactions t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.id = ? AND t.tenant_id = ? LIMIT 1'
        );
        $st->execute([$transactionId, $tenantId]);
        $tx = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $st = $pdo->prepare(
            'SELECT ti.quantity, ti.unit_price, ti.line_total, ti.flavor_name, p.name AS product_name
             FROM transaction_items ti
             INNER JOIN products p ON p.id = ti.product_id AND p.tenant_id = ti.tenant_id
             WHERE ti.transaction_id = ? AND ti.tenant_id = ?
             ORDER BY ti.id ASC'
        );
        $st->execute([$transactionId, $tenantId]);
        $lines = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lines[] = [
                'name' => trim((string) ($row['product_name'] ?? '')),
                'flavor_name' => trim((string) ($row['flavor_name'] ?? '')),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'line_total' => (float) ($row['line_total'] ?? 0),
            ];
        }

        $status = trim((string) ($tx['status'] ?? ''));
        $isPending = $status === 'pending';
        $splitPayments = [];
        $rawBreakdown = (string) ($tx['payment_breakdown_json'] ?? '');
        if ($rawBreakdown !== '') {
            $decoded = json_decode($rawBreakdown, true);
            if (is_array($decoded)) {
                foreach ($decoded as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $method = strtolower(trim((string) ($row['method'] ?? '')));
                    $amount = (float) ($row['amount'] ?? 0);
                    if ($method === '' || $amount <= 0) {
                        continue;
                    }
                    $splitPayments[] = [
                        'method' => $method,
                        'amount' => $amount,
                    ];
                }
            }
        }

        $serialNumber = trim((string) ($tenant['receipt_serial_number'] ?? ''));
        $taxType = strtolower(trim((string) ($tenant['receipt_tax_type'] ?? 'non_vat')));
        if (! in_array($taxType, ['vat', 'non_vat'], true)) {
            $taxType = 'non_vat';
        }
        $isBirRegistered = (int) ($tenant['receipt_is_bir_registered'] ?? 0) === 1;

        return [
            'transaction_id' => $transactionId,
            'store_name' => (string) ($tenant['name'] ?? ''),
            'display_name' => trim((string) ($tenant['receipt_display_name'] ?? '')),
            'business_style' => trim((string) ($tenant['receipt_business_style'] ?? '')),
            'tax_id' => trim((string) ($tenant['receipt_tax_id'] ?? '')),
            'serial_number' => $serialNumber,
            'dti_number' => trim((string) ($tenant['receipt_dti_number'] ?? '')),
            'bir_accreditation_no' => trim((string) ($tenant['receipt_bir_accreditation_no'] ?? '')),
            'min' => trim((string) ($tenant['receipt_min'] ?? '')),
            'permit_no' => trim((string) ($tenant['receipt_permit_no'] ?? '')),
            'is_bir_registered' => $isBirRegistered,
            'tax_type' => $taxType,
            'vat_applicable' => $isBirRegistered && $taxType === 'vat',
            'contact' => [
                'phone' => trim((string) ($tenant['receipt_phone'] ?? '')),
                'address' => trim((string) ($tenant['receipt_address'] ?? '')),
                'email' => trim((string) ($tenant['receipt_email'] ?? '')),
            ],
            'footer_note' => trim((string) ($tenant['receipt_footer_note'] ?? '')),
            'escpos' => [
                'line_width' => (int) ($tenant['receipt_escpos_line_width'] ?? 32),
                'right_col_width' => (int) ($tenant['receipt_escpos_right_col_width'] ?? 10),
                'extra_feeds' => (int) ($tenant['receipt_escpos_extra_feeds'] ?? 8),
                'cut_mode' => (string) ($tenant['receipt_escpos_cut_mode'] ?? 'none'),
            ],
            'items' => $lines,
            'grand_total' => (float) ($tx['total_amount'] ?? 0),
            'original_total_amount' => array_key_exists('original_total_amount', $tx) && $tx['original_total_amount'] !== null ? (float) $tx['original_total_amount'] : null,
            'amount_tendered' => array_key_exists('amount_tendered', $tx) && $tx['amount_tendered'] !== null ? (float) $tx['amount_tendered'] : null,
            'change_amount' => array_key_exists('change_amount', $tx) && $tx['change_amount'] !== null ? (float) $tx['change_amount'] : null,
            'payment_method' => array_key_exists('payment_method', $tx) && $tx['payment_method'] !== null ? (string) $tx['payment_method'] : null,
            'amount_paid' => array_key_exists('amount_paid', $tx) && $tx['amount_paid'] !== null ? (float) $tx['amount_paid'] : null,
            'split_payments' => $splitPayments,
            'refunded_amount' => array_key_exists('refunded_amount', $tx) ? (float) ($tx['refunded_amount'] ?? 0) : 0.0,
            'added_paid_amount' => array_key_exists('added_paid_amount', $tx) ? (float) ($tx['added_paid_amount'] ?? 0) : 0.0,
            'created_at' => $tx['created_at'] ?? null,
            'cashier_name' => trim((string) ($tx['cashier_name'] ?? '')),
            'transaction_status' => $status,
            'pending_customer_name' => $isPending ? trim((string) ($tx['pending_name'] ?? '')) : null,
            'pending_customer_contact' => $isPending ? trim((string) ($tx['pending_contact'] ?? '')) : null,
            /** Pending: prep list + UNPAID watermark (Bluetooth/LAN/print); not the official customer receipt. */
            'unpaid_prep_receipt' => $isPending,
            'customer_receipt_eligible' => $status === 'completed',
        ];
    }
}
