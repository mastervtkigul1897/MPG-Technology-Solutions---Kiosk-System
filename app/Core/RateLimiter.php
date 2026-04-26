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
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return false;
        }
        try {
            if (! flock($fp, LOCK_EX)) {
                fclose($fp);
                return false;
            }
            $raw = stream_get_contents($fp);
            $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['reset_at'], $decoded['count'])) {
                    if ($now <= (int) $decoded['reset_at']) {
                        $data = $decoded;
                    }
                }
            }
            $data['count'] = (int) $data['count'] + 1;
            $data['reset_at'] = isset($data['reset_at']) ? (int) $data['reset_at'] : ($now + $decaySeconds);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            fflush($fp);
            flock($fp, LOCK_UN);

            return $data['count'] <= $maxAttempts;
        } catch (\Throwable) {
            @flock($fp, LOCK_UN);
            return false;
        } finally {
            fclose($fp);
        }
    }
}
