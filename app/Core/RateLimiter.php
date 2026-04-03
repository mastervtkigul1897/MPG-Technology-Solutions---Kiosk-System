<?php

declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    public static function hit(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $dir = dirname(__DIR__, 2) . '/storage/rate_limit';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . sha1($key) . '.json';
        $now = time();
        $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];
        if (is_readable($file)) {
            $raw = json_decode((string) file_get_contents($file), true);
            if (is_array($raw) && isset($raw['reset_at'], $raw['count'])) {
                if ($now > (int) $raw['reset_at']) {
                    $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];
                } else {
                    $data = $raw;
                }
            }
        }
        $data['count'] = (int) $data['count'] + 1;
        file_put_contents($file, json_encode($data));

        return $data['count'] <= $maxAttempts;
    }
}
