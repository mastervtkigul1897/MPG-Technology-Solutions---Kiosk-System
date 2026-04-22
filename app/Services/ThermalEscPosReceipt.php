<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Minimal ESC/POS for common 55–58mm narrow thermal rolls (raw TCP port 9100, or BLE chunk write).
 * ASCII-only output for widest printer compatibility.
 */
final class ThermalEscPosReceipt
{
    private const DEFAULT_LINE_WIDTH = 32;
    private const DEFAULT_RIGHT_COL_WIDTH = 10;
    private const DEFAULT_EXTRA_FEEDS = 8;

    /** @param  array<string, mixed>  $r  Same shape as POS receipt JSON */
    public static function build(array $r): string
    {
        $cfg = self::settings($r);
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

        $appendWrap = static function (string $text, bool $center = false) use (&$append, &$cfg): void {
            $w = self::lineWidth($cfg);
            foreach (self::wrapText($text, $w) as $ln) {
                $append($ln, $center);
            }
        };

        $appendLabelValue = static function (string $label, string $value, bool $center = false) use (&$append, &$cfg): void {
            $w = self::lineWidth($cfg);
            $label = trim(self::ascii($label));
            $value = trim(self::ascii($value));
            if ($label === '' && $value === '') {
                $append('', $center);
                return;
            }
            if ($label === '') {
                foreach (self::wrapText($value, $w) as $ln) {
                    $append($ln, $center);
                }
                return;
            }
            $prefix = $label.': ';
            $indent = str_repeat(' ', min(8, strlen($prefix)));
            $firstWidth = max(8, $w - strlen($prefix));
            $restWidth = max(8, $w - strlen($indent));
            $chunks = self::wrapText($value, $firstWidth);
            if ($chunks === []) {
                $append($prefix, $center);
                return;
            }
            $append($prefix.($chunks[0] ?? ''), $center);
            for ($i = 1; $i < count($chunks); $i += 1) {
                $rest = $chunks[$i] ?? '';
                foreach (self::wrapText($rest, $restWidth) as $ln) {
                    $append($indent.$ln, $center);
                }
            }
        };

        $sep = static function () use (&$b, &$cfg): void {
            $b .= "\x1B\x61\x00";
            $b .= str_repeat('-', self::lineWidth($cfg))."\n";
        };

        $unpaidPrep = ! empty($r['unpaid_prep_receipt']) || ! empty($r['kitchen_slip']) || ! empty($r['unpaid_watermark']);

        if ($unpaidPrep) {
            $forName = trim((string) ($r['pending_customer_name'] ?? ''));
            if ($forName !== '') {
                $appendWrap('FOR: '.$forName, true);
                $sep();
            }
            $forContact = trim((string) ($r['pending_customer_contact'] ?? ''));
            if ($forContact !== '') {
                $appendWrap('Contact: '.$forContact);
                $sep();
            }
        }

        $displayName = trim((string) ($r['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($r['store_name'] ?? 'Store'));
        }
        $appendWrap($displayName !== '' ? $displayName : 'Store', true);

        $bs = trim((string) ($r['business_style'] ?? ''));
        if ($bs !== '' && ! $unpaidPrep) {
            $appendWrap($bs, true);
        }
        $taxId = trim((string) ($r['tax_id'] ?? ''));
        $isBirRegistered = ! empty($r['is_bir_registered']);
        $serial = trim((string) ($r['serial_number'] ?? ''));
        $dtiNumber = trim((string) ($r['dti_number'] ?? ''));
        $birAccreditationNo = trim((string) ($r['bir_accreditation_no'] ?? ''));
        $minNo = trim((string) ($r['min'] ?? ''));
        $permitNo = trim((string) ($r['permit_no'] ?? ''));
        if (! $unpaidPrep) {
            if ($isBirRegistered) {
                if ($birAccreditationNo !== '') {
                    $appendWrap('BIR Accreditation No: '.$birAccreditationNo, true);
                }
                if ($taxId !== '') {
                    $appendWrap('TIN: '.$taxId, true);
                }
                if ($serial !== '') {
                    $appendWrap('Serial No: '.$serial, true);
                }
                if ($minNo !== '') {
                    $appendWrap('MIN: '.$minNo, true);
                }
                if ($permitNo !== '') {
                    $appendWrap('Permit No: '.$permitNo, true);
                }
            } elseif ($taxId !== '') {
                $appendWrap('TIN: '.$taxId, true);
            }
            if ($dtiNumber !== '') {
                $appendWrap('DTI No: '.$dtiNumber, true);
            }
        }
        $taxType = strtolower(trim((string) ($r['tax_type'] ?? 'non_vat')));
        $taxTypeLabel = $taxType === 'vat' ? 'VAT Registered' : 'Non-VAT Registered';
        if (! $unpaidPrep) {
            $appendWrap('Tax Type: '.$taxTypeLabel, true);
        }

        $sep();
        $c = is_array($r['contact'] ?? null) ? $r['contact'] : [];
        $phone = trim((string) ($c['phone'] ?? ''));
        $addr = trim((string) ($c['address'] ?? ''));
        $email = trim((string) ($c['email'] ?? ''));
        if (! $unpaidPrep) {
            if ($phone !== '') {
                $appendLabelValue('Phone', $phone);
            }
            if ($addr !== '') {
                foreach (preg_split("/\r\n|\n|\r/", $addr) ?: [] as $ln) {
                    $ln = trim((string) $ln);
                    if ($ln !== '') {
                        $appendLabelValue('Address', $ln);
                    }
                }
            }
            if ($email !== '') {
                $appendLabelValue('Email', $email);
            }
            if ($phone === '' && $addr === '' && $email === '') {
                $appendWrap('No store contact on file.', true);
            }
            $sep();
        }

        $append(self::lrPad('Item', 'Amount', $cfg));
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
                    foreach (self::wrapText($name, self::lineWidth($cfg)) as $nameLine) {
                    $append($nameLine);
                }
            }
            if ($flavorName !== '') {
                    foreach (self::wrapText(' - '.$flavorName, self::lineWidth($cfg)) as $flavorLine) {
                    $append($flavorLine);
                }
            }
            $priceQtyLeft = self::money($unit).' x '.self::qtyStr($qty);
            $append(self::lrPad($priceQtyLeft, self::money($lineTot), $cfg));
        }

        $sep();
        $total = (float) ($r['grand_total'] ?? 0);
        $append(self::lrPad('TOTAL', self::money($total), $cfg));

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
            $vatApplicable = ! array_key_exists('vat_applicable', $r) || (bool) $r['vat_applicable'];
            if ($vatApplicable) {
                $vatAmount = $total > 0 ? $total * (12 / 112) : 0.0;
                $vatable = max(0.0, $total - $vatAmount);
                $append(self::lrPad('VATABLE SALES', self::money($vatable), $cfg));
                $append(self::lrPad('VAT (12%)', self::money($vatAmount), $cfg));
            }

            $pm = strtolower(trim((string) ($r['payment_method'] ?? '')));
            $pmLabel = $pm !== '' ? strtoupper(str_replace('_', ' ', $pm)) : '';
            if ($pmLabel !== '') {
                $append(self::lrPad('PAYMENT', $pmLabel, $cfg));
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
                $append(self::lrPad('NET TO ORDER', self::money($basePaid), $cfg));
                if ($tendered !== null && abs($tendered - $basePaid) > money_epsilon()) {
                    $append(self::lrPad('Cash tendered', self::money($tendered), $cfg));
                }
            } elseif ($basePaid !== null) {
                $append(self::lrPad('AMOUNT PAID', self::money($baseAfterRefund ?? $basePaid), $cfg));
            }

            if ($refunded > money_epsilon()) {
                $append(self::lrPad('REFUND', '-'.self::money($refunded), $cfg));
            }
            if ($addedAfterRefund > money_epsilon()) {
                $append(self::lrPad('ADDITIONAL PAID', self::money($addedAfterRefund), $cfg));
            }
            if ($netPaid !== null) {
                $append(self::lrPad('NET PAID', self::money($netPaid), $cfg));
            }

            $hasAdjust = ($refunded > money_epsilon()) || ($added > money_epsilon());
            $finalChange = $hasAdjust ? 0.0 : $change;
            if ($finalChange !== null && is_finite($finalChange)) {
                $append(self::lrPad('CHANGE', self::money((float) $finalChange), $cfg));
            }

            $sep();
            if ($isBirRegistered) {
                $append('THIS SERVES AS AN OFFICIAL RECEIPT', true);
                if ($tid > 0) {
                    $append(self::lrPad('OR No', (string) $tid, $cfg));
                }
                if ($when !== '') {
                    $append(self::lrPad('Date/Time', $when, $cfg));
                }
                $cashierName = trim((string) ($r['cashier_name'] ?? ''));
                if ($cashierName !== '') {
                    $append(self::lrPad('Cashier Name', $cashierName, $cfg));
                }
                if ($tid > 0) {
                    $append(self::lrPad('Transaction No', (string) $tid, $cfg));
                }
            } else {
                $append('THIS IS NOT AN OFFICIAL RECEIPT', true);
                $append('FOR INTERNAL / REFERENCE PURPOSES ONLY', true);
            }
            if (! $isBirRegistered && $meta !== '') {
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

        // Extra feeds so footer/cut does not clip on printers that cut early.
        $b .= str_repeat("\n", max(2, (int) ($cfg['extra_feeds'] ?? self::DEFAULT_EXTRA_FEEDS)));
        $b .= "\x1B\x32"; // Default line spacing (next job)
        $b .= "\x1B\x4D\x00"; // Font A (next job)
        $cutMode = (string) ($cfg['cut_mode'] ?? 'none');
        if ($cutMode === 'partial') {
            $b .= "\x1D\x56\x42\x00";
        } elseif ($cutMode === 'full') {
            $b .= "\x1D\x56\x00";
        }

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

    /** @param array{line_width:int,right_col_width:int,extra_feeds:int,cut_mode:string} $cfg */
    private static function lrPad(string $left, string $right, array $cfg): string
    {
        $w = self::lineWidth($cfg);
        $rw = self::rightColWidth($cfg);
        if ($rw >= $w - 2) {
            $rw = max(8, $w - 4);
        }
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

    /** @return array{line_width:int,right_col_width:int,extra_feeds:int,cut_mode:string} */
    private static function settings(array $r): array
    {
        $escpos = is_array($r['escpos'] ?? null) ? $r['escpos'] : [];
        $lineWidth = max(24, min(48, (int) ($escpos['line_width'] ?? self::DEFAULT_LINE_WIDTH)));
        $rightColWidth = max(8, min(16, (int) ($escpos['right_col_width'] ?? self::DEFAULT_RIGHT_COL_WIDTH)));
        $extraFeeds = max(2, min(16, (int) ($escpos['extra_feeds'] ?? self::DEFAULT_EXTRA_FEEDS)));
        $cutMode = strtolower(trim((string) ($escpos['cut_mode'] ?? 'none')));
        if (! in_array($cutMode, ['none', 'partial', 'full'], true)) {
            $cutMode = 'none';
        }

        return [
            'line_width' => $lineWidth,
            'right_col_width' => $rightColWidth,
            'extra_feeds' => $extraFeeds,
            'cut_mode' => $cutMode,
        ];
    }

    /** @param array{line_width:int,right_col_width:int,extra_feeds:int,cut_mode:string} $cfg */
    private static function lineWidth(array $cfg): int
    {
        return (int) $cfg['line_width'];
    }

    /** @param array{line_width:int,right_col_width:int,extra_feeds:int,cut_mode:string} $cfg */
    private static function rightColWidth(array $cfg): int
    {
        return (int) $cfg['right_col_width'];
    }
}
