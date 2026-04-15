<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Minimal ESC/POS for common 55–58mm narrow thermal rolls (raw TCP port 9100, or BLE chunk write).
 * ASCII-only output for widest printer compatibility.
 */
final class ThermalEscPosReceipt
{
    /**
     * Characters per line (Font B). ~60mm printable width; adjust if printer wraps too early/late.
     */
    private const LINE_WIDTH = 36;

    private const RIGHT_COL_WIDTH = 12;

    /** @param  array<string, mixed>  $r  Same shape as POS receipt JSON */
    public static function build(array $r): string
    {
        $b = '';
        $b .= "\x1B\x40"; // Initialize
        $b .= "\x1B\x4D\x01"; // Font B (smaller / denser)
        $b .= "\x1B\x33\x12"; // Tighter line spacing (18 dots; default ~30)

        $append = static function (string $line, bool $center = false) use (&$b): void {
            $line = self::asciiLine($line);
            if ($center) {
                $b .= "\x1B\x61\x01";
            } else {
                $b .= "\x1B\x61\x00";
            }
            $b .= $line."\n";
        };

        $sep = static function () use (&$b): void {
            $b .= "\x1B\x61\x00";
            $b .= str_repeat('-', self::LINE_WIDTH)."\n";
        };

        $unpaidPrep = ! empty($r['unpaid_prep_receipt']) || ! empty($r['kitchen_slip']) || ! empty($r['unpaid_watermark']);

        if ($unpaidPrep) {
            $forName = trim((string) ($r['pending_customer_name'] ?? ''));
            if ($forName !== '') {
                $append('FOR: '.$forName, true);
                $sep();
            }
            $forContact = trim((string) ($r['pending_customer_contact'] ?? ''));
            if ($forContact !== '') {
                $append('Contact: '.$forContact);
                $sep();
            }
        }

        $displayName = trim((string) ($r['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($r['store_name'] ?? 'Store'));
        }
        $append($displayName !== '' ? $displayName : 'Store', true);

        $bs = trim((string) ($r['business_style'] ?? ''));
        if ($bs !== '' && ! $unpaidPrep) {
            $append($bs, true);
        }
        $taxId = trim((string) ($r['tax_id'] ?? ''));
        if ($taxId !== '' && ! $unpaidPrep) {
            $append('TIN: '.$taxId, true);
        }

        $sep();
        $c = is_array($r['contact'] ?? null) ? $r['contact'] : [];
        $phone = trim((string) ($c['phone'] ?? ''));
        $addr = trim((string) ($c['address'] ?? ''));
        $email = trim((string) ($c['email'] ?? ''));
        if (! $unpaidPrep) {
            if ($phone !== '') {
                $append('Phone: '.$phone);
            }
            if ($addr !== '') {
                foreach (preg_split("/\r\n|\n|\r/", $addr) ?: [] as $ln) {
                    $ln = trim((string) $ln);
                    if ($ln !== '') {
                        $append($ln);
                    }
                }
            }
            if ($email !== '') {
                $append('Email: '.$email);
            }
            if ($phone === '' && $addr === '' && $email === '') {
                $append('No store contact on file.', true);
            }
            $sep();
        }

        $append(self::lrPad('Item', 'Amount'));
        $sep();

        $items = is_array($r['items'] ?? null) ? $r['items'] : [];
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $name = (string) ($it['name'] ?? '');
            $flavorName = trim((string) ($it['flavor_name'] ?? ''));
            $qty = (float) ($it['quantity'] ?? 0);
            $lineTot = (float) ($it['line_total'] ?? 0);
            $unit = (float) ($it['unit_price'] ?? 0);
            if ($unit <= money_epsilon() && $qty > money_epsilon()) {
                $unit = $lineTot / $qty;
            }
            if ($name !== '') {
                foreach (self::wrapText($name, self::LINE_WIDTH) as $nameLine) {
                    $append($nameLine);
                }
            }
            if ($flavorName !== '') {
                foreach (self::wrapText(' - '.$flavorName, self::LINE_WIDTH) as $flavorLine) {
                    $append($flavorLine);
                }
            }
            $priceQtyLeft = self::money($unit).' x '.self::qtyStr($qty);
            $append(self::lrPad($priceQtyLeft, self::money($lineTot)));
        }

        $sep();
        $total = (float) ($r['grand_total'] ?? 0);
        $append(self::lrPad('TOTAL', self::money($total)));

        $tid = isset($r['transaction_id']) ? (int) $r['transaction_id'] : 0;
        $when = '';
        if (! empty($r['created_at'])) {
            $ts = strtotime((string) $r['created_at']);
            $when = $ts !== false ? date('M j, Y g:i A', $ts) : (string) $r['created_at'];
        }
        $meta = trim($when.($tid > 0 ? ' #'.$tid : ''));

        if ($unpaidPrep) {
            $sep();
            $append('UNPAID', true);
            $b .= "\n\n";
        } else {
            $vatAmount = $total > 0 ? $total * (12 / 112) : 0.0;
            $vatable = max(0.0, $total - $vatAmount);
            $append(self::lrPad('VATABLE SALES', self::money($vatable)));
            $append(self::lrPad('VAT (12%)', self::money($vatAmount)));

            $pm = strtolower(trim((string) ($r['payment_method'] ?? '')));
            $pmLabel = $pm !== '' ? strtoupper(str_replace('_', ' ', $pm)) : '';
            if ($pmLabel !== '') {
                $append(self::lrPad('PAYMENT', $pmLabel));
            }

            $tendered = isset($r['amount_tendered']) && is_numeric($r['amount_tendered']) ? (float) $r['amount_tendered'] : null;
            $change = isset($r['change_amount']) && is_numeric($r['change_amount']) ? (float) $r['change_amount'] : null;
            $refunded = isset($r['refunded_amount']) && is_numeric($r['refunded_amount']) ? (float) $r['refunded_amount'] : 0.0;
            $added = isset($r['added_paid_amount']) && is_numeric($r['added_paid_amount']) ? (float) $r['added_paid_amount'] : 0.0;
            $ap0 = isset($r['amount_paid']) && is_numeric($r['amount_paid']) ? (float) $r['amount_paid'] : 0.0;
            $ch0 = $change ?? 0.0;

            $basePaid = null;
            if ($pm === 'cash') {
                if ($ap0 > money_epsilon()) {
                    $basePaid = $ap0;
                } elseif ($tendered !== null) {
                    $basePaid = max(0.0, $tendered - $ch0);
                }
            } else {
                if (isset($r['amount_paid']) && is_numeric($r['amount_paid'])) {
                    $basePaid = (float) $r['amount_paid'];
                } elseif ($tendered !== null) {
                    $basePaid = $tendered;
                }
            }

            $baseAfterRefund = $basePaid !== null ? max(0.0, $basePaid - $refunded) : null;
            $remainingRefundAfterBase = $basePaid !== null ? max(0.0, $refunded - $basePaid) : $refunded;
            $addedAfterRefund = max(0.0, $added - $remainingRefundAfterBase);
            $netPaid = $baseAfterRefund !== null ? max(0.0, $baseAfterRefund + $addedAfterRefund) : null;

            if ($pm === 'cash' && $basePaid !== null) {
                $append(self::lrPad('NET TO ORDER', self::money($basePaid)));
                if ($tendered !== null && abs($tendered - $basePaid) > money_epsilon()) {
                    $append(self::lrPad('Cash tendered', self::money($tendered)));
                }
            } elseif ($basePaid !== null) {
                $append(self::lrPad('AMOUNT PAID', self::money($baseAfterRefund ?? $basePaid)));
            }

            if ($refunded > money_epsilon()) {
                $append(self::lrPad('REFUND', '-'.self::money($refunded)));
            }
            if ($addedAfterRefund > money_epsilon()) {
                $append(self::lrPad('ADDITIONAL PAID', self::money($addedAfterRefund)));
            }
            if ($netPaid !== null) {
                $append(self::lrPad('NET PAID', self::money($netPaid)));
            }

            $hasAdjust = ($refunded > money_epsilon()) || ($added > money_epsilon());
            $finalChange = $hasAdjust ? 0.0 : $change;
            if ($finalChange !== null && is_finite($finalChange)) {
                $append(self::lrPad('CHANGE', self::money((float) $finalChange)));
            }

            $sep();
            if ($meta !== '') {
                $append($meta, true);
            }
            $append('Thank you for your purchase!', true);
            $b .= "\n\n";

            $footer = trim((string) ($r['footer_note'] ?? ''));
            if ($footer !== '') {
                foreach (preg_split("/\r\n|\n|\r/", $footer) ?: [] as $ln) {
                    $ln = trim(self::asciiLine((string) $ln));
                    if ($ln !== '') {
                        $append($ln, true);
                    }
                }
            }
        }

        $b .= "\n\n\n\n";
        $b .= "\x1B\x32"; // Default line spacing (next job)
        $b .= "\x1B\x4D\x00"; // Font A (next job)
        $b .= "\x1D\x56\x00"; // Full cut (common)

        return $b;
    }

    private static function qtyStr(float $q): string
    {
        if (abs($q - round($q)) < 1e-9) {
            return (string) (int) round($q);
        }
        $s = rtrim(rtrim(sprintf('%.4f', $q), '0'), '.');

        return $s === '' ? '0' : $s;
    }

    private static function money(float $n): string
    {
        return format_money($n);
    }

    private static function asciiLine(string $s): string
    {
        $s = self::ascii($s);

        return strlen($s) > 200 ? substr($s, 0, 200) : $s;
    }

    private static function ascii(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $t = preg_replace('/[^\x20-\x7E]/', '?', $t) ?? $t;

        return $t;
    }

    private static function truncate(string $s, int $max): string
    {
        $s = self::ascii($s);
        if (strlen($s) <= $max) {
            return $s;
        }

        return substr($s, 0, max(1, $max - 1)).'.';
    }

    private static function lrPad(string $left, string $right): string
    {
        $w = self::LINE_WIDTH;
        $rw = self::RIGHT_COL_WIDTH;
        $right = self::ascii($right);
        if (strlen($right) > $rw) {
            $right = substr($right, 0, $rw);
        }
        $left = self::truncate($left, $w - $rw);
        $rp = str_pad($right, $rw, ' ', STR_PAD_LEFT);

        return str_pad($left, $w - $rw, ' ', STR_PAD_RIGHT).$rp;
    }

    /** @return list<string> */
    private static function wrapText(string $text, int $width): array
    {
        $s = trim(self::ascii($text));
        if ($s === '') {
            return [''];
        }
        $out = [];
        foreach (preg_split('/\s+/', $s) ?: [] as $word) {
            $word = trim((string) $word);
            if ($word === '') {
                continue;
            }
            if ($out === []) {
                $out[] = $word;
                continue;
            }
            $lastIdx = count($out) - 1;
            $candidate = $out[$lastIdx].' '.$word;
            if (strlen($candidate) <= $width) {
                $out[$lastIdx] = $candidate;
            } else {
                if (strlen($word) <= $width) {
                    $out[] = $word;
                } else {
                    $remaining = $word;
                    while (strlen($remaining) > $width) {
                        $out[] = substr($remaining, 0, $width);
                        $remaining = substr($remaining, $width);
                    }
                    if ($remaining !== '') {
                        $out[] = $remaining;
                    }
                }
            }
        }
        return $out === [] ? [''] : $out;
    }
}
