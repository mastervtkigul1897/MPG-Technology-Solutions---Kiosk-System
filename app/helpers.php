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
