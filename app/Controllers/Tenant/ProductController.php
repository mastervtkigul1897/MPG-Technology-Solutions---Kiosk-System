<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\App;
use App\Core\Auth;
use App\Core\FlavorSchema;
use App\Core\Request;
use App\Core\Response;
use PDO;
use RuntimeException;

final class ProductController
{
    private static ?bool $hasImagePathColumn = null;

    private static function hasImagePathColumn(PDO $pdo): bool
    {
        if (self::$hasImagePathColumn !== null) {
            return self::$hasImagePathColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'image_path'");
            self::$hasImagePathColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasImagePathColumn = false;
        }

        return self::$hasImagePathColumn;
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        $hasImagePath = self::hasImagePathColumn($pdo);

        if ($request->ajax() || $request->boolean('datatable')) {
            $search = trim((string) data_get($request->all(), 'search.value', ''));
            $where = 'p.tenant_id = ?';
            $params = [$tenantId];
            if ($search !== '') {
                $where .= ' AND (p.name LIKE ? OR CAST(p.id AS CHAR) LIKE ?)';
                $like = '%'.$search.'%';
                $params[] = $like;
                $params[] = $like;
            }

            $total = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE tenant_id = '.$tenantId)->fetchColumn();
            $st = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where");
            $st->execute($params);
            $filtered = (int) $st->fetchColumn();

            $columns = ['p.id', 'p.name', 'p.price', 'p.is_active'];
            $orderIdx = (int) data_get($request->all(), 'order.0.column', 0);
            $orderDir = strtolower((string) data_get($request->all(), 'order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $orderBy = $columns[$orderIdx] ?? 'p.id';
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 25)));

            $sql = "SELECT p.* FROM products p WHERE $where ORDER BY $orderBy $orderDir LIMIT $length OFFSET $start";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($rows as $product) {
                $pid = (int) $product['id'];
                $stIng = $pdo->prepare(
                    'SELECT i.id AS ingredient_id, i.name, i.unit, pi.quantity_required FROM product_ingredients pi
                     INNER JOIN ingredients i ON i.id = pi.ingredient_id AND i.tenant_id = pi.tenant_id
                     WHERE pi.tenant_id = ? AND pi.product_id = ?'
                );
                $stIng->execute([$tenantId, $pid]);
                $recipeRows = $stIng->fetchAll(PDO::FETCH_ASSOC);
                $recipe = [];
                $ingHtml = '<ul class="mb-0">';
                foreach ($recipeRows as $row) {
                    $ingredientId = (int) ($row['ingredient_id'] ?? 0);
                    $qty = (float) ($row['quantity_required'] ?? 0);
                    $recipe[] = [
                        'ingredient_id' => $ingredientId,
                        'quantity_required' => $qty,
                    ];
                    $ingHtml .= '<li>'.e($row['name']).' - '.format_stock((float) $row['quantity_required']).' '.e($row['unit']).'</li>';
                }
                $ingHtml .= '</ul>';
                $stFl = $pdo->prepare(
                    "SELECT i.id, i.name, pfi.quantity_required
                     FROM product_flavor_ingredients pfi
                     INNER JOIN ingredients i ON i.id = pfi.ingredient_id AND i.tenant_id = pfi.tenant_id
                     WHERE pfi.tenant_id = ? AND pfi.product_id = ? AND LOWER(COALESCE(i.category, 'general')) = 'flavor'
                     ORDER BY i.name ASC"
                );
                $stFl->execute([$tenantId, $pid]);
                $flavors = [];
                foreach ($stFl->fetchAll(PDO::FETCH_ASSOC) as $fr) {
                    $flavors[] = [
                        'id' => (int) ($fr['id'] ?? 0),
                        'name' => (string) ($fr['name'] ?? ''),
                        'qty_required' => (float) ($fr['quantity_required'] ?? 1),
                    ];
                }
                $hasFlavors = (int) ($product['has_flavor_options'] ?? 0) === 1 && $flavors !== [];

                $actions = '';
                if (Auth::tenantMayManage($user, 'products')) {
                    $actions = '<div class="d-flex gap-1 flex-wrap">'
                        .'<button type="button" class="btn btn-sm btn-outline-primary js-edit-product" data-id="'.$pid.'" title="Edit"><i class="fa-solid fa-pen"></i></button>'
                        .'<form method="POST" action="'.e(url('/tenant/products/'.$pid.'/toggle-status')).'">'
                        .csrf_field().method_field('PATCH')
                        .'<button class="btn btn-sm '.($product['is_active'] ? 'btn-warning' : 'btn-success').'" title="Toggle status"><i class="fa-solid '.($product['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off').'"></i></button>'
                        .'</form>'
                        .'<form method="POST" action="'.e(url('/tenant/products/'.$pid)).'" onsubmit="return confirm(\'Remove product?\');">'
                        .csrf_field().method_field('DELETE')
                        .'<button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>'
                        .'</form></div>';
                }

                $data[] = [
                    'id' => $pid,
                    'name' => e((string) $product['name']),
                    'price' => format_money((float) $product['price']),
                    'image_path' => (string) ($product['image_path'] ?? ''),
                    'status' => '<span class="badge '.($product['is_active'] ? 'text-bg-success' : 'text-bg-danger').'">'
                        .($product['is_active'] ? 'Active' : 'Inactive').'</span>',
                    'is_active' => (bool) $product['is_active'],
                    'has_flavor_options' => $hasFlavors,
                    'flavors' => $flavors,
                    'recipe' => $recipe,
                    'ingredients' => $ingHtml,
                    'actions' => $actions,
                ];
            }

            return json_response([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $data,
            ]);
        }

        $st = $pdo->prepare('SELECT * FROM ingredients WHERE tenant_id = ? ORDER BY name');
        $st->execute([$tenantId]);
        $ingredients = $st->fetchAll(PDO::FETCH_ASSOC);

        $existingImagePaths = [];
        if ($hasImagePath) {
            $st = $pdo->prepare(
                "SELECT DISTINCT image_path
                 FROM products
                 WHERE tenant_id = ? AND image_path IS NOT NULL AND image_path <> ''
                 ORDER BY image_path"
            );
            $st->execute([$tenantId]);
            $existingImagePaths = array_map(
                static fn ($row): string => (string) ($row['image_path'] ?? ''),
                $st->fetchAll(PDO::FETCH_ASSOC)
            );
        }

        return view_page('Products', 'tenant.products.index', [
            'ingredients' => $ingredients,
            'existing_image_paths' => $existingImagePaths,
            'image_path_column_available' => $hasImagePath,
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'products')) {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $name = trim((string) $request->input('name'));
        $price = round_money((float) $request->input('price'));
        $existingImagePath = trim((string) $request->input('existing_image_path', ''));
        $recipe = $request->input('recipe') ?? [];
        $hasFlavorOptions = $request->boolean('has_flavor_options');
        $flavorRecipe = $request->input('flavor_recipe') ?? [];
        if (! is_array($recipe)) {
            $recipe = [];
        }
        if (! is_array($flavorRecipe)) {
            $flavorRecipe = [];
        }
        $flavorRows = [];
        foreach ($flavorRecipe as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fid = (int) ($row['ingredient_id'] ?? 0);
            $qty = round_stock((float) ($row['quantity_required'] ?? 0));
            if ($fid > 0 && $qty >= stock_min_positive()) {
                $flavorRows[$fid] = $qty;
            }
        }

        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        $hasImagePath = self::hasImagePathColumn($pdo);
        if ($hasImagePath) {
            $imagePath = '';
            if ($existingImagePath !== '') {
                $imagePath = $this->resolveExistingImagePath($existingImagePath);
                if ($imagePath === '') {
                    session_flash('errors', ['Selected existing image is invalid or missing.']);
                    return redirect(url('/tenant/products'));
                }
            } else {
                try {
                    $imagePath = $this->handleImageUpload($request->files['image'] ?? null, $tenantId, $name);
                } catch (\Throwable $e) {
                    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Image upload failed on server. Please check hosting upload settings and folder permissions.';
                    session_flash('errors', [$msg]);
                    return redirect(url('/tenant/products'));
                }
            }
            $pdo->prepare(
                'INSERT INTO products (tenant_id, category_id, name, price, image_path, is_active, created_at, updated_at)
                 VALUES (?, NULL, ?, ?, ?, 1, NOW(), NOW())'
            )->execute([$tenantId, $name, $price, $imagePath]);
        } else {
            $pdo->prepare(
                'INSERT INTO products (tenant_id, category_id, name, price, is_active, created_at, updated_at)
                 VALUES (?, NULL, ?, ?, 1, NOW(), NOW())'
            )->execute([$tenantId, $name, $price]);
        }
        $productId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE products SET has_flavor_options = ? WHERE tenant_id = ? AND id = ?')
            ->execute([$hasFlavorOptions ? 1 : 0, $tenantId, $productId]);

        foreach ($recipe as $row) {
            if (! is_array($row)) {
                continue;
            }
            $iid = (int) ($row['ingredient_id'] ?? 0);
            $qty = round_stock((float) ($row['quantity_required'] ?? 0));
            if ($iid < 1 || $qty < stock_min_positive()) {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO product_ingredients (tenant_id, product_id, ingredient_id, quantity_required, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NOW(), NOW())'
            )->execute([$tenantId, $productId, $iid, $qty]);
        }
        if ($hasFlavorOptions && $flavorRows !== []) {
            $flavorIds = array_keys($flavorRows);
            $place = implode(',', array_fill(0, count($flavorIds), '?'));
            $stFl = $pdo->prepare(
                "SELECT id FROM ingredients
                 WHERE tenant_id = ? AND id IN ($place) AND LOWER(COALESCE(category, 'general')) = 'flavor'"
            );
            $stFl->execute(array_merge([$tenantId], $flavorIds));
            $valid = array_map(static fn ($r): int => (int) ($r['id'] ?? 0), $stFl->fetchAll(PDO::FETCH_ASSOC));
            foreach ($valid as $fid) {
                if ($fid < 1) {
                    continue;
                }
                $pdo->prepare(
                    'INSERT INTO product_flavor_ingredients (tenant_id, product_id, ingredient_id, quantity_required, created_at, updated_at)
                     VALUES (?, ?, ?, ?, NOW(), NOW())'
                )->execute([$tenantId, $productId, $fid, round_stock((float) ($flavorRows[$fid] ?? 1))]);
            }
        }

        return redirect(url('/tenant/products'));
    }

    public function update(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'products')) {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        $hasImagePath = self::hasImagePathColumn($pdo);

        $st = $pdo->prepare('SELECT * FROM products WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, (int) $id]);
        $product = $st->fetch(PDO::FETCH_ASSOC);
        if (! $product) {
            return new Response('Not found', 404);
        }

        $name = trim((string) $request->input('name'));
        $price = round_money((float) $request->input('price'));
        $isActive = $request->boolean('is_active');
        $existingImagePath = trim((string) $request->input('existing_image_path', ''));
        $recipe = $request->input('recipe') ?? [];
        $hasFlavorOptions = $request->boolean('has_flavor_options');
        $flavorRecipe = $request->input('flavor_recipe') ?? [];
        if (! is_array($recipe)) {
            $recipe = [];
        }
        if (! is_array($flavorRecipe)) {
            $flavorRecipe = [];
        }
        $flavorRows = [];
        foreach ($flavorRecipe as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fid = (int) ($row['ingredient_id'] ?? 0);
            $qty = round_stock((float) ($row['quantity_required'] ?? 0));
            if ($fid > 0 && $qty >= stock_min_positive()) {
                $flavorRows[$fid] = $qty;
            }
        }

        if ($hasImagePath) {
            $imagePath = (string) ($product['image_path'] ?? '');
            $removeImage = $request->boolean('remove_image');
            $newImagePath = '';
            if ($existingImagePath !== '') {
                $newImagePath = $this->resolveExistingImagePath($existingImagePath);
                if ($newImagePath === '') {
                    session_flash('errors', ['Selected existing image is invalid or missing.']);
                    return redirect(url('/tenant/products'));
                }
            } else {
                try {
                    $newImagePath = $this->handleImageUpload($request->files['image'] ?? null, $tenantId, $name);
                } catch (\Throwable $e) {
                    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Image upload failed on server. Please check hosting upload settings and folder permissions.';
                    session_flash('errors', [$msg]);
                    return redirect(url('/tenant/products'));
                }
            }

            if ($newImagePath !== '') {
                if ($newImagePath !== $imagePath) {
                    $this->deleteUploadedImage($imagePath, $tenantId, (int) $id);
                }
                $imagePath = $newImagePath;
            } elseif ($removeImage) {
                $this->deleteUploadedImage($imagePath, $tenantId, (int) $id);
                $imagePath = '';
            }

            $pdo->prepare(
                'UPDATE products SET name = ?, price = ?, image_path = ?, is_active = ?, has_flavor_options = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
            )->execute([$name, $price, $imagePath !== '' ? $imagePath : null, $isActive ? 1 : 0, $hasFlavorOptions ? 1 : 0, (int) $id, $tenantId]);
        } else {
            $pdo->prepare(
                'UPDATE products SET name = ?, price = ?, is_active = ?, has_flavor_options = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
            )->execute([$name, $price, $isActive ? 1 : 0, $hasFlavorOptions ? 1 : 0, (int) $id, $tenantId]);
        }

        $pdo->prepare('DELETE FROM product_ingredients WHERE tenant_id = ? AND product_id = ?')->execute([$tenantId, (int) $id]);
        foreach ($recipe as $row) {
            if (! is_array($row)) {
                continue;
            }
            $iid = (int) ($row['ingredient_id'] ?? 0);
            $qty = round_stock((float) ($row['quantity_required'] ?? 0));
            if ($iid < 1 || $qty < stock_min_positive()) {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO product_ingredients (tenant_id, product_id, ingredient_id, quantity_required, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NOW(), NOW())'
            )->execute([$tenantId, (int) $id, $iid, $qty]);
        }
        $pdo->prepare('DELETE FROM product_flavor_ingredients WHERE tenant_id = ? AND product_id = ?')->execute([$tenantId, (int) $id]);
        if ($hasFlavorOptions && $flavorRows !== []) {
            $flavorIds = array_keys($flavorRows);
            $place = implode(',', array_fill(0, count($flavorIds), '?'));
            $stFl = $pdo->prepare(
                "SELECT id FROM ingredients
                 WHERE tenant_id = ? AND id IN ($place) AND LOWER(COALESCE(category, 'general')) = 'flavor'"
            );
            $stFl->execute(array_merge([$tenantId], $flavorIds));
            $valid = array_map(static fn ($r): int => (int) ($r['id'] ?? 0), $stFl->fetchAll(PDO::FETCH_ASSOC));
            foreach ($valid as $fid) {
                if ($fid < 1) {
                    continue;
                }
                $pdo->prepare(
                    'INSERT INTO product_flavor_ingredients (tenant_id, product_id, ingredient_id, quantity_required, created_at, updated_at)
                     VALUES (?, ?, ?, ?, NOW(), NOW())'
                )->execute([$tenantId, (int) $id, $fid, round_stock((float) ($flavorRows[$fid] ?? 1))]);
            }
        }

        return redirect(url('/tenant/products'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'products')) {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        $pid = (int) $id;

        $cnt = (int) $pdo->query(
            'SELECT COUNT(*) FROM transaction_items WHERE tenant_id = '.$tenantId.' AND product_id = '.$pid
        )->fetchColumn();

        if ($cnt > 0) {
            $pdo->prepare('UPDATE products SET is_active = 0, updated_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$pid, $tenantId]);
            session_flash('success', 'Product has existing transactions and was set to inactive instead of being deleted.');
        } else {
            $imgPath = '';
            if (self::hasImagePathColumn($pdo)) {
                $imgSt = $pdo->prepare('SELECT image_path FROM products WHERE tenant_id = ? AND id = ? LIMIT 1');
                $imgSt->execute([$tenantId, $pid]);
                $imgPath = (string) ($imgSt->fetchColumn() ?: '');
            }

            $pdo->prepare('DELETE FROM product_ingredients WHERE tenant_id = ? AND product_id = ?')->execute([$tenantId, $pid]);
            $pdo->prepare('DELETE FROM product_flavor_ingredients WHERE tenant_id = ? AND product_id = ?')->execute([$tenantId, $pid]);
            $pdo->prepare('DELETE FROM products WHERE tenant_id = ? AND id = ?')->execute([$tenantId, $pid]);
            if ($imgPath !== '') {
                $this->deleteUploadedImage($imgPath, $tenantId);
            }
        }

        return redirect(url('/tenant/products'));
    }

    public function toggleStatus(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'products')) {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        $st = $pdo->prepare('SELECT is_active FROM products WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, (int) $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            return new Response('Not found', 404);
        }
        $new = ! (bool) $row['is_active'];
        $pdo->prepare('UPDATE products SET is_active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$new ? 1 : 0, (int) $id, $tenantId]);

        session_flash('success', 'Product status has been updated.');

        return redirect(url('/tenant/products'));
    }

    /**
     * @param array<string,mixed>|null $file
     */
    private function handleImageUpload(?array $file, int $tenantId, string $productName): string
    {
        if (! is_array($file)) {
            return '';
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || ! is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid uploaded image.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > (5 * 1024 * 1024)) {
            throw new RuntimeException('Image must be up to 5MB only.');
        }

        $mime = '';
        if (function_exists('mime_content_type')) {
            try {
                $mime = (string) (mime_content_type($tmp) ?: '');
            } catch (\Throwable) {
                $mime = '';
            }
        }
        if ($mime === '') {
            // Fallback that works on more shared hosts
            $img = @getimagesize($tmp);
            if (is_array($img) && ! empty($img['mime'])) {
                $mime = (string) $img['mime'];
            }
        }
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $ext = $map[$mime] ?? '';
        if ($ext === '') {
            throw new RuntimeException('Unsupported image type. Use JPG, PNG, WEBP, or GIF.');
        }

        // Optimize large images for faster loading + smaller storage.
        // If GD is not available on the hosting, keep the original upload (no crash).
        [$tmp, $ext] = $this->maybeOptimizeUploadedImage($tmp, $ext);

        $relativeDir = 'uploads/products';
        $absDir = $this->publicRootPath().'/'.$relativeDir;
        if (! is_dir($absDir) && ! mkdir($absDir, 0775, true) && ! is_dir($absDir)) {
            throw new RuntimeException('Cannot create image upload directory.');
        }

        // Content-hash enables file reuse across products.
        $hash = hash_file('sha256', $tmp) ?: '';
        if ($hash === '') {
            throw new RuntimeException('Unable to process uploaded image.');
        }

        // Reuse an already saved file with same content hash (legacy + new naming).
        $legacyName = $hash.'.'.$ext;
        $legacyPath = $absDir.'/'.$legacyName;
        if (is_file($legacyPath)) {
            return $relativeDir.'/'.$legacyName;
        }
        $matches = glob($absDir.'/*_'.$hash.'.'.$ext) ?: [];
        if ($matches !== []) {
            $existing = basename((string) ($matches[0] ?? ''));
            if ($existing !== '') {
                return $relativeDir.'/'.$existing;
            }
        }

        // Name by first product that uploads this unique image.
        $slug = $this->slugifyName($productName);
        $filename = $slug.'_'.$hash.'.'.$ext;
        $absPath = $absDir.'/'.$filename;
        if (! is_writable($absDir)) {
            throw new RuntimeException('Upload directory is not writable. Please set permissions for public/uploads/products.');
        }
        // If we generated an optimized temp file, it is not an "uploaded file" anymore.
        // Use rename/copy as needed.
        $moved = false;
        if (is_uploaded_file($tmp)) {
            $moved = move_uploaded_file($tmp, $absPath);
        } else {
            $moved = @rename($tmp, $absPath);
            if (! $moved) {
                $moved = @copy($tmp, $absPath);
                if ($moved) {
                    @unlink($tmp);
                }
            }
        }
        if (! $moved) {
            throw new RuntimeException('Failed to save uploaded image.');
        }

        return $relativeDir.'/'.$filename;
    }

    /**
     * @return array{0:string,1:string} [$path, $ext]
     */
    private function maybeOptimizeUploadedImage(string $tmpPath, string $ext): array
    {
        $tmpPath = (string) $tmpPath;
        $ext = strtolower(trim($ext));
        if ($tmpPath === '' || ! is_file($tmpPath)) {
            return [$tmpPath, $ext];
        }

        // GD is the most common shared-host image extension.
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagecopyresampled')) {
            return [$tmpPath, $ext];
        }

        // Basic metadata for sizing
        $info = @getimagesize($tmpPath);
        if (! is_array($info) || empty($info[0]) || empty($info[1])) {
            return [$tmpPath, $ext];
        }
        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w < 1 || $h < 1) {
            return [$tmpPath, $ext];
        }

        // Max dimension rule (good balance for kiosk thumbnails/cards).
        $maxDim = 1024;
        $scale = min(1.0, $maxDim / max($w, $h));
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        // If already small and file size is small, keep original.
        $bytes = (int) (@filesize($tmpPath) ?: 0);
        if ($scale >= 0.999 && $bytes > 0 && $bytes <= 350 * 1024) {
            return [$tmpPath, $ext];
        }

        $src = null;
        try {
            $src = match ($ext) {
                'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmpPath) : null,
                'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmpPath) : null,
                'gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($tmpPath) : null,
                'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : null,
                default => null,
            };
        } catch (\Throwable) {
            $src = null;
        }
        if (! is_resource($src) && ! ($src instanceof \GdImage)) {
            return [$tmpPath, $ext];
        }

        $dst = imagecreatetruecolor($newW, $newH);
        if (! $dst) {
            return [$tmpPath, $ext];
        }

        // Preserve alpha for PNG/WEBP, otherwise white background (for JPG).
        $hasAlpha = in_array($ext, ['png', 'webp', 'gif'], true);
        if ($hasAlpha) {
            if (function_exists('imagealphablending')) {
                imagealphablending($dst, false);
            }
            if (function_exists('imagesavealpha')) {
                imagesavealpha($dst, true);
            }
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
            }
        } else {
            $white = imagecolorallocate($dst, 255, 255, 255);
            if ($white !== false) {
                imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);
            }
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $outPath = tempnam(sys_get_temp_dir(), 'kiosk_img_');
        if ($outPath === false) {
            return [$tmpPath, $ext];
        }

        // Prefer WEBP when supported; otherwise JPEG.
        $useWebp = function_exists('imagewebp');
        $targetExt = $useWebp ? 'webp' : 'jpg';
        $ok = false;
        try {
            if ($useWebp) {
                $ok = @imagewebp($dst, $outPath, 80);
            } else {
                $ok = function_exists('imagejpeg') ? @imagejpeg($dst, $outPath, 82) : false;
            }
        } catch (\Throwable) {
            $ok = false;
        }

        if (is_resource($src) || ($src instanceof \GdImage)) {
            @imagedestroy($src);
        }
        if (is_resource($dst) || ($dst instanceof \GdImage)) {
            @imagedestroy($dst);
        }

        if (! $ok || ! is_file($outPath)) {
            @unlink($outPath);
            return [$tmpPath, $ext];
        }

        $outBytes = (int) (@filesize($outPath) ?: 0);
        if ($outBytes > 0 && $bytes > 0 && $outBytes >= $bytes) {
            // If optimization didn't help, keep original.
            @unlink($outPath);
            return [$tmpPath, $ext];
        }

        return [$outPath, $targetExt];
    }

    private function slugifyName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'product-image';
        }
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? '';
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            return 'product-image';
        }

        return (string) substr($slug, 0, 80);
    }

    private function deleteUploadedImage(string $imagePath, int $tenantId, ?int $excludeProductId = null): void
    {
        $imagePath = trim($imagePath);
        if ($imagePath === '') {
            return;
        }
        // Safety guard: only allow deletions inside uploads/products.
        if (! str_starts_with($imagePath, 'uploads/products/')) {
            return;
        }

        // Keep shared image files: only delete when no other products reference this path.
        $pdo = App::db();
        $sql = 'SELECT COUNT(*) FROM products WHERE tenant_id = ? AND image_path = ?';
        $params = [$tenantId, $imagePath];
        if ($excludeProductId !== null && $excludeProductId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeProductId;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $usage = (int) $st->fetchColumn();
        if ($usage > 0) {
            return;
        }

        $full = $this->publicRootPath().'/'.$imagePath;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    private function resolveExistingImagePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (! str_starts_with($path, 'uploads/products/')) {
            return '';
        }
        $full = $this->publicRootPath().'/'.$path;
        if (! is_file($full)) {
            return '';
        }

        return $path;
    }

    /**
     * Returns absolute filesystem path to the web-public root.
     * Supports both deployments:
     * - project-root contains /public (common)
     * - project-root IS the public root (some shared host setups)
     */
    private function publicRootPath(): string
    {
        $base = dirname(__DIR__, 3);
        $candidate = $base.'/public';
        if (is_dir($candidate)) {
            return $candidate;
        }

        return $base;
    }
}
