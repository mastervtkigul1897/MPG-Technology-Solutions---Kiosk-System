<?php

declare(strict_types=1);

namespace App\Core;

final class SecurityHeaders
{
    public static function apply(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('X-Permitted-Cross-Domain-Policies: none');
        if (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header('X-Powered-By: ');
    }
}
