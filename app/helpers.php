<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * JSON for embedding in <script> — hex-escapes <, >, &, and quotes to reduce script injection and </script> breakouts.
 */
function json_embed(mixed $value): string
{
    try {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_THROW_ON_ERROR
        );
    } catch (\JsonException) {
        return 'null';
    }
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="'.e(strtoupper($method)).'">';
}

function redirect(string $to, int $code = 302): Response
{
    return new Response('', $code, ['Location' => $to]);
}

function url(string $path = ''): string
{
    $base = rtrim(App::config('url') ?? '', '/');
    $path = ltrim($path, '/');

    return ($base !== '' ? $base : '') . ($path !== '' ? '/'.$path : '');
}

function route(string $name, array $params = []): string
{
    $paths = require dirname(__DIR__) . '/config/route_paths.php';
    if (! isset($paths[$name])) {
        return url('/');
    }
    $path = $paths[$name];
    foreach ($params as $k => $v) {
        $path = str_replace('{'.$k.'}', (string) $v, $path);
    }

    return url($path);
}

function session_flash(string $key, mixed $value = null): mixed
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;

        return null;
    }
    $v = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $v;
}

function session_get(string $key, mixed $default = null): mixed
{
    return $_SESSION[$key] ?? $default;
}

function session_set(string $key, mixed $value): void
{
    $_SESSION[$key] = $value;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function request(): Request
{
    return App::request();
}

function route_is(string $pattern): bool
{
    $n = App::$routeName ?? '';
    if (str_ends_with($pattern, '.*')) {
        $p = substr($pattern, 0, -2);

        return str_starts_with($n, $p);
    }
    if (str_ends_with($pattern, '.') && strlen($pattern) > 1) {
        return str_starts_with($n, $pattern);
    }

    return $n === $pattern;
}

/** @return array<string,mixed>|null */
function auth_user(): ?array
{
    return Auth::user();
}

/** Cashier module check; store owners always pass. */
function user_can_module(string $module): bool
{
    return Auth::canAccessModule(auth_user(), $module);
}

function data_get(mixed $data, string $key, mixed $default = null): mixed
{
    $keys = explode('.', $key);
    foreach ($keys as $k) {
        if (! is_array($data) || ! array_key_exists($k, $data)) {
            return $default;
        }
        $data = $data[$k];
    }

    return $data;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    echo $message !== '' ? e($message) : 'Error '.$code;
    exit;
}

function json_response(array $data, int $code = 200): Response
{
    return new Response(
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $code,
        ['Content-Type' => 'application/json; charset=UTF-8']
    );
}

function view(string $name, array $data = []): string
{
    $path = dirname(__DIR__) . '/views/' . str_replace('.', '/', $name) . '.php';
    if (! is_file($path)) {
        throw new RuntimeException('View not found: '.$name);
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $path;

    return (string) ob_get_clean();
}

function response_view(string $name, array $data = [], int $code = 200): Response
{
    return new Response(view($name, $data), $code, ['Content-Type' => 'text/html; charset=UTF-8']);
}

/**
 * URLs/flags for thermal receipt: Wi-Fi raw TCP (server) + ESC/POS payload for Web Bluetooth (browser).
 *
 * @return array{thermal_receipt_network_url: string, thermal_receipt_escpos_url: string, thermal_receipt_network_enabled: bool, thermal_receipt_show_bluetooth: bool}
 */
function thermal_receipt_client_config(string $context = 'pos'): array
{
    $tp = App::config('thermal_printer');
    if (! is_array($tp)) {
        $tp = [];
    }
    $host = trim((string) ($tp['host'] ?? ''));
    $prefix = $context === 'transactions' ? '/tenant/transactions' : '/tenant/pos';

    return [
        'thermal_receipt_network_url' => url($prefix.'/receipt-print-network'),
        'thermal_receipt_escpos_url' => url($prefix.'/receipt-escpos'),
        'thermal_receipt_network_enabled' => $host !== '',
        'thermal_receipt_show_bluetooth' => (bool) App::config('thermal_receipt_show_bluetooth', false),
    ];
}

/** Currency / receipt / totals display (not stock). */
function money_scale(): int
{
    return 2;
}

/** Inventory: stock, recipe quantities, damaged qty, movements (matches DB fractional precision). */
function stock_scale(): int
{
    return 16;
}

/** Float tolerance for payment / money branches. */
function money_epsilon(): float
{
    return 1e-12;
}

/** Float tolerance for stock comparisons (deduction / audit). */
function stock_epsilon(): float
{
    return 1e-14;
}

function money_min_positive(): float
{
    return 0.01;
}

function stock_min_positive(): float
{
    return 1e-15;
}

function round_money(float $n): float
{
    return round($n, money_scale());
}

function round_stock(float $n): float
{
    return round($n, stock_scale());
}

/** Currency display: always 2 decimal places, thousands comma. */
function format_money(mixed $n): string
{
    $f = (float) $n;
    if (! is_finite($f)) {
        return '0.00';
    }

    return number_format($f, money_scale(), '.', ',');
}

/**
 * Stock / recipe qty display: up to stock_scale() digits, trim trailing zeros, thousands comma.
 */
function format_stock(mixed $n): string
{
    return format_stock_inner($n, stock_scale(), true);
}

/** Stock values in logs / attributes (no thousands separator). */
function format_stock_plain(mixed $n): string
{
    return format_stock_inner($n, stock_scale(), false);
}

function format_stock_inner(mixed $n, int $maxDecimals, bool $thousandsComma): string
{
    $f = (float) $n;
    if (! is_finite($f)) {
        return '0';
    }
    $s = number_format($f, $maxDecimals, '.', $thousandsComma ? ',' : '');
    if (str_contains($s, '.')) {
        $s = rtrim(rtrim($s, '0'), '.');
    }

    return ($s === '-0' || $s === '' || $s === '-.') ? '0' : $s;
}

function view_page(string $title, string $name, array $data = []): Response
{
    $inner = view($name, $data);

    return response_view('layouts.app', array_merge($data, [
        'title' => $title,
        'content' => $inner,
        'user' => auth_user(),
    ]));
}

function view_guest(string $title, string $name, array $data = []): Response
{
    $inner = view($name, $data);

    return response_view('layouts.guest', array_merge($data, [
        'title' => $title,
        'content' => $inner,
    ]));
}

function view_subscription_screen(string $title, string $name, array $data = []): Response
{
    $inner = view($name, $data);

    return response_view('layouts.subscription', array_merge($data, [
        'title' => $title,
        'content' => $inner,
    ]));
}
