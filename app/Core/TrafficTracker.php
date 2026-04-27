<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class TrafficTracker
{
    public static function track(Request $request): void
    {
        if (! self::shouldTrack($request)) {
            return;
        }

        $user = Auth::user();
        if (is_array($user) && (($user['role'] ?? '') === 'super_admin')) {
            // Exclude super admin visits (owner traffic) from analytics.
            return;
        }

        $pdo = App::db();
        if (! self::hasLogsTable($pdo)) {
            return;
        }

        $ip = trim((string) $request->ip());
        $ip = $ip !== '' ? $ip : '0.0.0.0';
        $path = trim((string) $request->path);
        if ($path === '') {
            $path = '/';
        }

        $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $referrer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));

        try {
            $pdo->prepare(
                'INSERT INTO super_admin_traffic_logs
                (user_id, tenant_id, role, ip_address, user_agent, path, query_string, referrer, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                is_array($user) ? ((int) ($user['id'] ?? 0) ?: null) : null,
                is_array($user) ? ((int) ($user['tenant_id'] ?? 0) ?: null) : null,
                is_array($user) ? ((string) ($user['role'] ?? '')) : null,
                mb_substr($ip, 0, 64),
                $userAgent !== '' ? mb_substr($userAgent, 0, 1024) : null,
                mb_substr($path, 0, 255),
                $queryString !== '' ? mb_substr($queryString, 0, 4000) : null,
                $referrer !== '' ? mb_substr($referrer, 0, 1024) : null,
            ]);
        } catch (\Throwable) {
            // Do not interrupt page loads if tracking insert fails.
        }
    }

    private static function shouldTrack(Request $request): bool
    {
        if ($request->method !== 'GET') {
            return false;
        }
        if ($request->ajax()) {
            return false;
        }
        $path = strtolower(trim((string) $request->path));
        if ($path === '') {
            return true;
        }
        $skipPrefixes = [
            '/vendor/',
            '/css/',
            '/js/',
            '/images/',
            '/uploads/',
            '/fonts/',
        ];
        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }
        $skipExact = [
            '/favicon.ico',
            '/manifest.json',
            '/sw.js',
            '/robots.txt',
        ];

        return ! in_array($path, $skipExact, true);
    }

    private static function hasLogsTable(PDO $pdo): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?'
            );
            $st->execute(['super_admin_traffic_logs']);
            return (int) $st->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
