<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use PDO;

final class PasswordResetService
{
    private const EXPIRES_MINUTES = 60;
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaReady) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
                `email` varchar(191) NOT NULL,
                `token` varchar(255) NOT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$schemaReady = true;
    }

    public static function sendResetLink(PDO $pdo, string $email): bool
    {
        self::ensureSchema($pdo);
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $st = $pdo->prepare('SELECT id, name, email, email_verified_at FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $st->execute([$email]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($user) || empty($user['email_verified_at'])) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE LOWER(email) = LOWER(?)')->execute([$email]);
        $pdo->prepare('INSERT INTO password_reset_tokens (email, token, created_at) VALUES (?, ?, NOW())')->execute([$email, $hash]);

        $link = url('/reset-password?email='.rawurlencode($email).'&token='.rawurlencode($token));
        $appName = (string) (App::config('name') ?? 'Laundry System');
        $displayName = trim((string) ($user['name'] ?? ''));
        $displayName = $displayName !== '' ? $displayName : 'there';
        $subject = 'Reset your '.$appName.' password';
        $html = '<p>Hello '.htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').',</p>'
            .'<p>Use this link to reset your '.$appName.' password. The link expires in '.self::EXPIRES_MINUTES.' minutes.</p>'
            .'<p><a href="'.htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" style="display:inline-block;padding:10px 14px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;">Reset password</a></p>'
            .'<p>If the button does not work, open this link:<br>'.htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        $text = "Hello {$displayName},\n\nUse this link to reset your {$appName} password. It expires in ".self::EXPIRES_MINUTES." minutes:\n{$link}\n";

        return Mailer::send($email, $subject, $html, $text);
    }

    public static function resetPassword(PDO $pdo, string $email, string $token, string $password): bool
    {
        self::ensureSchema($pdo);
        $email = strtolower(trim($email));
        $token = trim($token);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '' || strlen($password) < 8) {
            return false;
        }

        $st = $pdo->prepare(
            'SELECT prt.token
             FROM password_reset_tokens prt
             INNER JOIN users u ON LOWER(u.email) = LOWER(prt.email)
             WHERE LOWER(prt.email) = LOWER(?)
               AND u.email_verified_at IS NOT NULL
               AND prt.created_at >= DATE_SUB(NOW(), INTERVAL '.self::EXPIRES_MINUTES.' MINUTE)
             LIMIT 1'
        );
        $st->execute([$email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row) || ! hash_equals((string) ($row['token'] ?? ''), hash('sha256', $token))) {
            return false;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE LOWER(email) = LOWER(?) LIMIT 1')->execute([$hash, $email]);
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE LOWER(email) = LOWER(?)')->execute([$email]);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
