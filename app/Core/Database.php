<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    /**
     * @param  array{host:string,port:int,database:string,username:string,password:string,charset:string,timezone?:string}  $cfg
     */
    public static function connect(array $cfg, bool $debug = false): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );

        try {
            $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Force DB session timezone to Manila offset for NOW(), DATE(), and other date functions.
            $tz = (string) ($cfg['timezone'] ?? '+08:00');
            $st = $pdo->prepare('SET time_zone = ?');
            $st->execute([$tz]);
        } catch (PDOException $e) {
            http_response_code(500);
            if ($debug) {
                echo '<pre>Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
            } else {
                echo 'Database connection failed.';
            }
            exit;
        }

        return $pdo;
    }
}
