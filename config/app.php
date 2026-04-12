<?php

declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';
$env = [];
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
}

$get = static fn (string $key, ?string $default = null): ?string => $env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;

return [
    'name' => $get('APP_NAME', 'Kiosk System'),
    /** Shown in the browser tab after the app name (not in the sidebar). */
    'brand_suffix' => $get('APP_BRAND_SUFFIX', 'MPG Technology Solutions'),
    'debug' => filter_var($get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => rtrim($get('APP_URL', ''), '/'),
    'timezone' => $get('APP_TIMEZONE', 'Asia/Manila'),
    'app_owner_email' => $get('APP_OWNER_EMAIL', ''),
    'db' => [
        'host' => $get('DB_HOST', '127.0.0.1'),
        'port' => (int) ($get('DB_PORT', '3306') ?? 3306),
        'database' => $get('DB_DATABASE', 'forge'),
        'username' => $get('DB_USERNAME', 'forge'),
        'password' => $get('DB_PASSWORD', ''),
        'charset' => $get('DB_CHARSET', 'utf8mb4'),
        'timezone' => $get('DB_TIMEZONE', '+08:00'),
    ],
    'session' => [
        'lifetime' => 120,
    ],
    'security' => [
        'rate_limit_login' => [10, 60],
        'rate_limit_general' => [120, 60],
        'rate_limit_checkout' => [20, 60],
    ],
    /** Raw ESC/POS over TCP (Wi-Fi/Ethernet thermal, usually port 9100). Server must reach printer IP. */
    'thermal_printer' => [
        'host' => trim((string) $get('THERMAL_PRINTER_HOST', '')),
        'port' => max(1, min(65535, (int) ($get('THERMAL_PRINTER_PORT', '9100') ?: 9100))),
        'timeout' => max(0.5, min(30.0, (float) ($get('THERMAL_PRINTER_TIMEOUT', '3') ?: 3))),
    ],
    /**
     * Show the Web Bluetooth thermal button (Chrome/HTTPS). Code stays loaded; false = hide UI only.
     * Default off so receipt modals stay Print + Wi‑Fi/LAN only. Set true here to show Bluetooth again.
     */
    'thermal_receipt_show_bluetooth' => false,
];
