<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Writes to activity_logs only for security- and audit-relevant events (low volume).
 * Generic request logging was removed to save database storage.
 */
final class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $meta  Stored as JSON; keep small.
     */
    public static function log(
        ?int $tenantId,
        int $userId,
        string $userRole,
        string $module,
        string $action,
        Request $request,
        string $description,
        ?array $meta = null
    ): void {
        try {
            $pdo = App::db();
            $metaJson = $meta !== null ? json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null;
            $st = $pdo->prepare(
                'INSERT INTO activity_logs (tenant_id, user_id, user_role, module, action, method, path, description, ip_address, user_agent, meta, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $st->execute([
                $tenantId,
                $userId,
                $userRole,
                $module,
                $action,
                $request->method,
                $request->path,
                mb_substr($description, 0, 500),
                $request->ip(),
                $request->userAgent(),
                $metaJson,
            ]);
        } catch (\Throwable) {
            // Never break the main request if logging fails
        }
    }
}
