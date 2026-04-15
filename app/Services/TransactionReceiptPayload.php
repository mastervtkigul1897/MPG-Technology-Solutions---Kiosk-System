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
            'SELECT name, receipt_display_name, receipt_business_style, receipt_tax_id, receipt_phone, receipt_address, receipt_email, receipt_footer_note
             FROM tenants
             WHERE id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId]);
        $tenant = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $st = $pdo->prepare(
            'SELECT total_amount, original_total_amount, amount_tendered, change_amount, payment_method, amount_paid, refunded_amount, added_paid_amount, created_at, status, pending_name, pending_contact
             FROM transactions WHERE id = ? AND tenant_id = ? LIMIT 1'
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

        return [
            'transaction_id' => $transactionId,
            'store_name' => (string) ($tenant['name'] ?? ''),
            'display_name' => trim((string) ($tenant['receipt_display_name'] ?? '')),
            'business_style' => trim((string) ($tenant['receipt_business_style'] ?? '')),
            'tax_id' => trim((string) ($tenant['receipt_tax_id'] ?? '')),
            'contact' => [
                'phone' => trim((string) ($tenant['receipt_phone'] ?? '')),
                'address' => trim((string) ($tenant['receipt_address'] ?? '')),
                'email' => trim((string) ($tenant['receipt_email'] ?? '')),
            ],
            'footer_note' => trim((string) ($tenant['receipt_footer_note'] ?? '')),
            'items' => $lines,
            'grand_total' => (float) ($tx['total_amount'] ?? 0),
            'original_total_amount' => array_key_exists('original_total_amount', $tx) && $tx['original_total_amount'] !== null ? (float) $tx['original_total_amount'] : null,
            'amount_tendered' => array_key_exists('amount_tendered', $tx) && $tx['amount_tendered'] !== null ? (float) $tx['amount_tendered'] : null,
            'change_amount' => array_key_exists('change_amount', $tx) && $tx['change_amount'] !== null ? (float) $tx['change_amount'] : null,
            'payment_method' => array_key_exists('payment_method', $tx) && $tx['payment_method'] !== null ? (string) $tx['payment_method'] : null,
            'amount_paid' => array_key_exists('amount_paid', $tx) && $tx['amount_paid'] !== null ? (float) $tx['amount_paid'] : null,
            'refunded_amount' => array_key_exists('refunded_amount', $tx) ? (float) ($tx['refunded_amount'] ?? 0) : 0.0,
            'added_paid_amount' => array_key_exists('added_paid_amount', $tx) ? (float) ($tx['added_paid_amount'] ?? 0) : 0.0,
            'created_at' => $tx['created_at'] ?? null,
            'transaction_status' => $status,
            'pending_customer_name' => $isPending ? trim((string) ($tx['pending_name'] ?? '')) : null,
            'pending_customer_contact' => $isPending ? trim((string) ($tx['pending_contact'] ?? '')) : null,
            /** Pending: prep list + UNPAID watermark (Bluetooth/LAN/print); not the official customer receipt. */
            'unpaid_prep_receipt' => $isPending,
            'customer_receipt_eligible' => $status === 'completed',
        ];
    }
}
