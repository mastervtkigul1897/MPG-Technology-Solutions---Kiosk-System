<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

/** @var array $config */
$config = require BASE_PATH . '/config/app.php';

// Force all PHP date/time operations to Manila timezone (GMT+8) unless explicitly overridden.
date_default_timezone_set((string) ($config['timezone'] ?? 'Asia/Manila'));

\App\Core\App::boot($config);
\App\Core\AppSettings::apply();
