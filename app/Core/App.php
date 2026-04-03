<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class App
{
    private static array $config = [];

    private static ?PDO $pdo = null;

    private static ?Request $request = null;

    public static ?string $routeName = null;

    public static function boot(array $config): void
    {
        self::$config = $config;
        self::$pdo = Database::connect($config['db'], (bool) ($config['debug'] ?? false));
        self::$request = Request::fromGlobals();
    }

    public static function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? $default;
    }

    /** @param array<string, mixed> $patch */
    public static function mergeConfig(array $patch): void
    {
        foreach ($patch as $k => $v) {
            self::$config[$k] = $v;
        }
    }

    public static function db(): PDO
    {
        return self::$pdo;
    }

    public static function request(): Request
    {
        return self::$request ?? Request::fromGlobals();
    }
}
