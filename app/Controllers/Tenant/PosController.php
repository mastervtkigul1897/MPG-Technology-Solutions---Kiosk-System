<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\App;
use App\Core\Auth;
use App\Core\FlavorSchema;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantReceiptFields;
use App\Services\CheckoutService;
use App\Services\ThermalEscPosReceipt;
use App\Services\ThermalPrinterClient;
use App\Services\TransactionReceiptPayload;
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
        FlavorSchema::ensure($pdo);
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
        $sql = "SELECT id, name, price, has_flavor_options{$imageSelect} FROM products WHERE $where ORDER BY name LIMIT $perPage OFFSET $offset";
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
            $stFl = $pdo->prepare(
                "SELECT i.id, i.name, i.unit, i.stock_quantity, i.low_stock_threshold, pfi.quantity_required
                 FROM product_flavor_ingredients pfi
                 INNER JOIN ingredients i ON i.id = pfi.ingredient_id AND i.tenant_id = pfi.tenant_id
                 WHERE pfi.tenant_id = ? AND pfi.product_id = ? AND LOWER(COALESCE(i.category, 'general')) = 'flavor'
                 ORDER BY i.name ASC"
            );
            $stFl->execute([$tenantId, $pid]);
            $flavors = [];
            foreach ($stFl->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $flavors[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'unit' => (string) ($row['unit'] ?? ''),
                    'stock_quantity' => (float) ($row['stock_quantity'] ?? 0),
                    'low_stock_threshold' => (float) ($row['low_stock_threshold'] ?? 0),
                    'qty_required' => (float) ($row['quantity_required'] ?? 1),
                ];
            }
            $productPayload[] = [
                'id' => $pid,
                'name' => $p['name'],
                'price' => (float) $p['price'],
                'has_flavor_options' => (int) ($p['has_flavor_options'] ?? 0) === 1 && $flavors !== [],
                'flavors' => $flavors,
                'image_path' => $hasImagePath ? (string) ($p['image_path'] ?? '') : '',
                'category_key' => $categoryKey,
                'ingredients' => $ingredients,
            ];
        }

        $lastPage = max(1, (int) ceil($total / $perPage));

        return view_page('Create Transaction', 'tenant.pos.index', array_merge([
            'products' => $products,
            'productPayload' => $productPayload,
            'filters' => ['search' => $search],
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
                'per_page' => $perPage,
            ],
        ], thermal_receipt_client_config('pos')));
    }

    public function receiptEscpos(Request $request): Response
    {
        Auth::user();
        $receipt = self::receiptArrayFromRequest($request);
        if ($receipt === null) {
            return json_response(['success' => false, 'message' => 'Missing or invalid receipt data.'], 422);
        }
        try {
            $bytes = ThermalEscPosReceipt::build($receipt);
        } catch (\Throwable) {
            return json_response(['success' => false, 'message' => 'Could not build receipt.'], 422);
        }

        return json_response([
            'success' => true,
            'escpos_base64' => base64_encode($bytes),
        ]);
    }

    public function receiptPrintNetwork(Request $request): Response
    {
        Auth::user();
        $receipt = self::receiptArrayFromRequest($request);
        if ($receipt === null) {
            return json_response(['success' => false, 'message' => 'Missing or invalid receipt data.'], 422);
        }
        $tp = App::config('thermal_printer');
        if (! is_array($tp)) {
            $tp = [];
        }
        $host = trim((string) ($tp['host'] ?? ''));
        if ($host === '') {
            return json_response([
                'success' => false,
                'message' => 'Wi-Fi/Ethernet printer not configured. Set THERMAL_PRINTER_HOST in .env (PHP server must reach the printer, usually port 9100).',
            ], 422);
        }
        $port = (int) ($tp['port'] ?? 9100);
        $timeout = (float) ($tp['timeout'] ?? 3);

        $user = Auth::user();
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return json_response(['success' => false, 'message' => 'Could not resolve store.'], 403);
        }
        $pdo = App::db();
        TenantReceiptFields::ensure($pdo);
        $st = $pdo->prepare('SELECT receipt_lan_print_copies FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $copies = (int) ($row['receipt_lan_print_copies'] ?? 1);
        if ($copies < 1) {
            $copies = 1;
        }
        if ($copies > 10) {
            $copies = 10;
        }

        try {
            $bytes = ThermalEscPosReceipt::build($receipt);
            for ($i = 0; $i < $copies; $i++) {
                ThermalPrinterClient::sendRaw($host, $port, $timeout, $bytes);
            }
        } catch (\Throwable $e) {
            return json_response(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return json_response(['success' => true, 'copies' => $copies]);
    }

    /** @return array<string, mixed>|null */
    private static function receiptArrayFromRequest(Request $request): ?array
    {
        $rj = $request->input('receipt_json');
        if (is_string($rj) && $rj !== '') {
            $decoded = json_decode($rj, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $r = $request->input('receipt');
        if (is_array($r)) {
            return $r;
        }

        return null;
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
                'flavor_ingredient_id' => max(0, (int) ($i['flavor_ingredient_id'] ?? 0)),
            ];
        }

        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        $paidRaw = $request->input('amount_tendered');
        $amountTendered = is_numeric($paidRaw) ? (float) $paidRaw : null;
        $paymentMethod = strtolower(trim((string) $request->input('payment_method', 'cash')));
        $allowedMethods = ['cash', 'card', 'gcash', 'paymaya', 'online_banking', 'free'];
        if (! in_array($paymentMethod, $allowedMethods, true)) {
            $paymentMethod = 'cash';
        }

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

        // Store payment method + paid + change.
        // For FREE: keep amounts at 0 and zero out item totals.
        if ($paymentMethod === 'free') {
            try {
                $pdo->prepare('UPDATE transaction_items SET unit_price = 0, line_total = 0, updated_at = NOW() WHERE tenant_id = ? AND transaction_id = ?')
                    ->execute([$tenantId, $transactionId]);
                $pdo->prepare("UPDATE transactions SET payment_method = 'free', amount_paid = 0, amount_tendered = 0, change_amount = 0, refunded_amount = 0, added_paid_amount = 0, original_total_amount = 0, total_amount = 0, expense_total = 0, profit_total = 0, updated_at = NOW() WHERE tenant_id = ? AND id = ?")
                    ->execute([$tenantId, $transactionId]);
            } catch (\Throwable) {
            }
        } elseif ($amountTendered !== null) {
            try {
                $st = $pdo->prepare('SELECT total_amount FROM transactions WHERE tenant_id = ? AND id = ? LIMIT 1');
                $st->execute([$tenantId, $transactionId]);
                $total = (float) $st->fetchColumn();
                if ($paymentMethod !== 'cash') {
                    // Non-cash: paid equals exact total, no change.
                    $pdo->prepare('UPDATE transactions SET payment_method = ?, amount_paid = ?, amount_tendered = ?, change_amount = 0, original_total_amount = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?')
                        ->execute([$paymentMethod, $total, $total, $total, $tenantId, $transactionId]);
                } else {
                    if ($amountTendered < $total) {
                        throw new RuntimeException('Amount received is less than total.');
                    }
                    $change = $amountTendered - $total;
                    // amount_paid stores the original total (what customer paid for, net of change).
                    $pdo->prepare('UPDATE transactions SET payment_method = ?, amount_paid = ?, amount_tendered = ?, change_amount = ?, original_total_amount = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?')
                        ->execute([$paymentMethod, $total, $amountTendered, $change, $total, $tenantId, $transactionId]);
                }
            } catch (\Throwable) {
                // Do not fail checkout if these optional fields cannot be saved.
            }
        }
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

    public function pendingIndex(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        $st = $pdo->prepare(
            "SELECT t.id, t.total_amount, t.created_at, t.pending_name, t.pending_contact,
                    (SELECT COALESCE(SUM(quantity),0) FROM transaction_items ti WHERE ti.tenant_id = t.tenant_id AND ti.transaction_id = t.id) AS qty_sum
             FROM transactions t
             WHERE t.tenant_id = ? AND t.status = 'pending'
             ORDER BY t.created_at DESC, t.id DESC
             LIMIT 100"
        );
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return json_response(['success' => true, 'data' => $rows]);
    }

    public function storePending(Request $request): Response
    {
        $user = Auth::user();
        $pendingName = trim((string) $request->input('pending_name', ''));
        $pendingContact = trim((string) $request->input('pending_contact', ''));
        if ($pendingName === '') {
            return json_response(['success' => false, 'message' => 'Name is required.'], 422);
        }
        if ($pendingContact === '') {
            $pendingContact = null;
        }
        $items = $request->input('items');
        if (! is_array($items) || $items === []) {
            return json_response(['success' => false, 'message' => 'Cart is empty.'], 422);
        }
        $parsed = [];
        foreach ($items as $i) {
            if (! is_array($i)) {
                continue;
            }
            $parsed[] = [
                'product_id' => (int) ($i['product_id'] ?? 0),
                'quantity' => max(1, (int) ($i['quantity'] ?? 0)),
                'flavor_ingredient_id' => max(0, (int) ($i['flavor_ingredient_id'] ?? 0)),
            ];
        }
        if ($parsed === []) {
            return json_response(['success' => false, 'message' => 'Cart is empty.'], 422);
        }

        $tenantId = (int) $user['tenant_id'];
        try {
            $pendingId = (new CheckoutService())->createPending($tenantId, (int) $user['id'], $parsed, $pendingName, $pendingContact);
        } catch (RuntimeException $e) {
            return json_response(['success' => false, 'message' => $e->getMessage()], 422);
        }

        TenantReceiptFields::ensure(App::db());
        $prepReceipt = TransactionReceiptPayload::build(App::db(), $tenantId, $pendingId);

        return json_response([
            'success' => true,
            'pending_id' => $pendingId,
            'unpaid_prep_receipt' => $prepReceipt,
        ]);
    }

    public function payPending(Request $request, string $id): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $pendingId = (int) $id;
        if ($pendingId < 1) {
            return json_response(['success' => false, 'message' => 'Invalid request.'], 422);
        }
        $paidRaw = $request->input('amount_tendered');
        $amountTendered = is_numeric($paidRaw) ? (float) $paidRaw : -1;
        $paymentMethod = strtolower(trim((string) $request->input('payment_method', 'cash')));
        $allowedMethods = ['cash', 'card', 'gcash', 'paymaya', 'online_banking'];
        if (! in_array($paymentMethod, $allowedMethods, true)) {
            $paymentMethod = 'cash';
        }
        if ($amountTendered < 0) {
            return json_response(['success' => false, 'message' => 'Amount received is required.'], 422);
        }

        $pdo = App::db();
        try {
            $txId = (new CheckoutService())->payPending($tenantId, (int) $user['id'], $pendingId, $amountTendered);
            // Store payment method/paid/change similarly to checkout.
            try {
                $st = $pdo->prepare('SELECT total_amount FROM transactions WHERE tenant_id = ? AND id = ? LIMIT 1');
                $st->execute([$tenantId, $txId]);
                $total = (float) $st->fetchColumn();
                if ($paymentMethod !== 'cash') {
                    $pdo->prepare('UPDATE transactions SET payment_method = ?, amount_paid = ?, amount_tendered = ?, change_amount = 0, original_total_amount = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?')
                        ->execute([$paymentMethod, $total, $total, $total, $tenantId, $txId]);
                } else {
                    $change = max(0, $amountTendered - $total);
                    $pdo->prepare('UPDATE transactions SET payment_method = ?, amount_paid = ?, amount_tendered = ?, change_amount = ?, original_total_amount = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?')
                        ->execute([$paymentMethod, $total, $amountTendered, $change, $total, $tenantId, $txId]);
                }
            } catch (\Throwable) {
            }
        } catch (RuntimeException $e) {
            return json_response(['success' => false, 'message' => $e->getMessage()], 422);
        }

        TenantReceiptFields::ensure($pdo);
        $receipt = self::buildReceiptPayload($pdo, $tenantId, $txId);

        return json_response(['success' => true, 'receipt' => $receipt]);
    }

    /** @return array<string, mixed> */
    private static function buildReceiptPayload(PDO $pdo, int $tenantId, int $transactionId): array
    {
        return TransactionReceiptPayload::build($pdo, $tenantId, $transactionId);
    }
}
