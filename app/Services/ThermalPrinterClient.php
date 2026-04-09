<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ThermalPrinterClient
{
    /** @throws RuntimeException */
    public static function sendRaw(string $host, int $port, float $timeoutSeconds, string $payload): void
    {
        $host = trim($host);
        if ($host === '') {
            throw new RuntimeException('Thermal printer host is not configured.');
        }
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Invalid thermal printer port.');
        }

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
        if (! is_resource($fp)) {
            throw new RuntimeException('Could not connect to printer: '.$errstr.' ('.$errno.')');
        }

        stream_set_timeout($fp, (int) ceil($timeoutSeconds), (int) (($timeoutSeconds - floor($timeoutSeconds)) * 1_000_000));
        $written = 0;
        $len = strlen($payload);
        while ($written < $len) {
            $n = @fwrite($fp, substr($payload, $written));
            if ($n === false || $n === 0) {
                fclose($fp);
                throw new RuntimeException('Printer write failed.');
            }
            $written += $n;
        }
        fclose($fp);
    }
}
