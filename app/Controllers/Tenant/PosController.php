<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantReceiptFields;
use App\Services\CheckoutService;
use PDO;
use RuntimeException;

final class PosController
{
    private static ?bool $hasProductImagePathColumn = null;

    private static function hasProductImagePathColumn(PDO $pdo): bool
    {
        if (self::$hasProductImagePathColumn !== null) {
            return self::$hasProductImagePathColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'image_path'");
            self::$hasProductImagePathColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasProductImagePathColumn = false;
        }

        return self::$hasProductImagePathColumn;
    }

    /**
     * Tokenize product name for POS category grouping.
     * - Lowercase
     * - Normalize punctuation into spaces
     * - Skip tokens containing digits (e.g. "8oz", "12s")
     * - Skip tokens without letters
     *
     * @return list<string> tokens (at least 1 token; fallback: ["other"])
     */
    private static function tokenizeCategoryName(string $name): array
    {
        $s = mb_strtolower($name);
        // Normalize punctuation into spaces; keep only letters/numbers.
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? '';
        $parts = preg_split('/\s+/u', trim($s)) ?: [];

        $tokens = [];
        foreach ($parts as $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;
            }
            // Remove size/pack tokens that contain digits (e.g. "8oz", "12s").
            if (preg_match('/\d/u', $t)) {
                continue;
            }
            // Keep only tokens that have at least one letter.
            if (preg_match('/\p{L}/u', $t) !== 1) {
                continue;
            }
            $tokens[] = $t;
        }

        return $tokens !== [] ? $tokens : ['other'];
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $search = trim((string) $request->input('search'));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 100;
        $offset = ($page - 1) * $perPage;

        $pdo = App::db();
        $where = 'tenant_id = ? AND is_active = 1';
        $params = [$tenantId];
        if ($search !== '') {
            $where .= ' AND name LIKE ?';
            $params[] = '%'.$search.'%';
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $hasImagePath = self::hasProductImagePathColumn($pdo);
        $imageSelect = $hasImagePath ? ', image_path' : '';
        $sql = "SELECT id, name, price{$imageSelect} FROM products WHERE $where ORDER BY name LIMIT $perPage OFFSET $offset";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $products = $st->fetchAll(PDO::FETCH_ASSOC);

        // Build category keys dynamically:
        // - Default: category key = first two tokens (e.g. "mango shake", "french fries")
        // - Variant collapse: for products sharing the same first token, if the "tail"
        //   (tokens after second) is the same across multiple different second tokens,
        //   then treat the second token as a variant and collapse to first token only
        //   (e.g. "paluto boiled egg" + "paluto fried egg" => "paluto").
        $tokensById = [];
        $firstGroups = []; // first_token => list<['id'=>int,'tokens'=>list<string>]>
        foreach ($products as $p) {
            $pid = (int) ($p['id'] ?? 0);
            $tokens = self::tokenizeCategoryName((string) ($p['name'] ?? ''));
            $tokensById[$pid] = $tokens;

            $first = (string) ($tokens[0] ?? 'other');
            $firstGroups[$first][] = ['id' => $pid, 'tokens' => $tokens];
        }

        $ignoreSecondByFirst = [];
        foreach ($firstGroups as $first => $items) {
            // Map tail signature => set of distinct second tokens.
            $tailToSecondTokens = [];
            foreach ($items as $it) {
                $tokens = $it['tokens'] ?? [];
                $second = (string) ($tokens[1] ?? '');
                $tailTokens = array_slice($tokens, 2);
                $tailSig = implode(' ', $tailTokens); // '' is valid

                if (! isset($tailToSecondTokens[$tailSig])) {
                    $tailToSecondTokens[$tailSig] = [];
                }
                $tailToSecondTokens[$tailSig][$second] = true;
            }

            $ignore = false;
            foreach ($tailToSecondTokens as $secondSet) {
                if (count($secondSet) >= 2) {
                    $ignore = true;
                    break;
                }
            }
            $ignoreSecondByFirst[$first] = $ignore;
        }

        foreach ($products as &$p) {
            $pid = (int) ($p['id'] ?? 0);
            $tokens = $tokensById[$pid] ?? ['other'];
            $first = (string) ($tokens[0] ?? 'other');
            $second = (string) ($tokens[1] ?? '');

            if (($ignoreSecondByFirst[$first] ?? false) === true) {
                $p['category_key'] = $first;
            } else {
                $p['category_key'] = $second !== '' ? ($first.' '.$second) : $first;
            }
        }
        unset($p);

        $productPayload = [];
        foreach ($products as $p) {
            $pid = (int) $p['id'];
            $categoryKey = (string) ($p['category_key'] ?? '');
            $stIng = $pdo->prepare(
                'SELECT i.id, i.name, i.unit, i.stock_quantity, i.low_stock_threshold, pi.quantity_required AS qty_required
                 FROM product_ingredients pi
                 INNER JOIN ingredients i ON i.id = pi.ingredient_id AND i.tenant_id = pi.tenant_id
                 WHERE pi.tenant_id = ? AND pi.product_id = ?'
            );
            $stIng->execute([$tenantId, $pid]);
            $ingredients = [];
            foreach ($stIng->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ingredients[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'stock_quantity' => (float) $row['stock_quantity'],
                    'low_stock_threshold' => (float) $row['low_stock_threshold'],
                    'qty_required' => (float) $row['qty_required'],
                ];
            }
            $productPayload[] = [
                'id' => $pid,
                'name' => $p['name'],
                'price' => (float) $p['price'],
                'image_path' => $hasImagePath ? (string) ($p['image_path'] ?? '') : '',
                'category_key' => $categoryKey,
                'ingredients' => $ingredients,
            ];
        }

        $lastPage = max(1, (int) ceil($total / $perPage));

        return view_page('Create Transaction', 'tenant.pos.index', [
            'products' => $products,
            'productPayload' => $productPayload,
            'filters' => ['search' => $search],
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function checkout(Request $request): Response
    {
        $user = Auth::user();
        $items = $request->input('items');
        if (! is_array($items) || $items === []) {
            session_flash('errors', ['Cart is empty.']);

            return redirect(url('/tenant/pos'));
        }
        $parsed = [];
        foreach ($items as $i) {
            if (! is_array($i)) {
                continue;
            }
            $parsed[] = [
                'product_id' => (int) ($i['product_id'] ?? 0),
                'quantity' => max(1, (int) ($i['quantity'] ?? 0)),
            ];
        }

        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        try {
            $transactionId = (new CheckoutService())->checkout($tenantId, (int) $user['id'], $parsed);
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return json_response(['success' => false, 'message' => $e->getMessage()], 422);
            }
            session_flash('errors', [$e->getMessage()]);

            return redirect(url('/tenant/pos'));
        }

        TenantReceiptFields::ensure($pdo);
        $receipt = self::buildReceiptPayload($pdo, $tenantId, $transactionId);

        if ($request->wantsJson()) {
            return json_response([
                'success' => true,
                'receipt' => $receipt,
            ]);
        }

        session_flash('success', 'Checkout completed.');

        return redirect(url('/tenant/pos'));
    }

    /** @return array<string, mixed> */
    private static function buildReceiptPayload(PDO $pdo, int $tenantId, int $transactionId): array
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
            'SELECT total_amount, created_at FROM transactions WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $st->execute([$transactionId, $tenantId]);
        $tx = $st->fetch(PDO::FETCH_ASSOC);

        $st = $pdo->prepare(
            'SELECT ti.quantity, ti.unit_price, ti.line_total, p.name AS product_name
             FROM transaction_items ti
             INNER JOIN products p ON p.id = ti.product_id AND p.tenant_id = ti.tenant_id
             WHERE ti.transaction_id = ? AND ti.tenant_id = ?
             ORDER BY ti.id ASC'
        );
        $st->execute([$transactionId, $tenantId]);
        $lines = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lines[] = [
                'name' => (string) $row['product_name'],
                'quantity' => (int) $row['quantity'],
                'unit_price' => (float) $row['unit_price'],
                'line_total' => (float) $row['line_total'],
            ];
        }

        $phone = trim((string) ($tenant['receipt_phone'] ?? ''));
        $address = trim((string) ($tenant['receipt_address'] ?? ''));
        $email = trim((string) ($tenant['receipt_email'] ?? ''));
        $displayName = trim((string) ($tenant['receipt_display_name'] ?? ''));
        $businessStyle = trim((string) ($tenant['receipt_business_style'] ?? ''));
        $taxId = trim((string) ($tenant['receipt_tax_id'] ?? ''));
        $footerNote = trim((string) ($tenant['receipt_footer_note'] ?? ''));

        return [
            'transaction_id' => $transactionId,
            'store_name' => (string) ($tenant['name'] ?? ''),
            'display_name' => $displayName,
            'business_style' => $businessStyle,
            'tax_id' => $taxId,
            'contact' => [
                'phone' => $phone,
                'address' => $address,
                'email' => $email,
            ],
            'footer_note' => $footerNote,
            'items' => $lines,
            'grand_total' => (float) ($tx['total_amount'] ?? 0),
            'created_at' => $tx['created_at'] ?? null,
        ];
    }
}
