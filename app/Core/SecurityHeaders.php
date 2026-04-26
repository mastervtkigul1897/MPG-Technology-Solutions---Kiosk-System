<?php

declare(strict_types=1);

namespace App\Core;

final class SecurityHeaders
{
    public static function apply(): void
    {
        header(
            "Content-Security-Policy: default-src 'self'; "
            ."base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; "
            ."img-src 'self' data: blob:; font-src 'self' data:; "
            ."script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self'"
        );
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        // Allow camera on same-origin pages (attendance photo capture).
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
        header('X-Permitted-Cross-Domain-Policies: none');
        if (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header('X-Powered-By: ');
    }
}
