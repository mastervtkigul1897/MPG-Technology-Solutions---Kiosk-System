<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use PDO;

final class EmailVerificationService
{
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaReady) {
            return;
        }

        self::ensureColumn($pdo, 'email_verification_token_hash', 'VARCHAR(64) NULL AFTER `email_verified_at`');
        self::ensureColumn($pdo, 'email_verification_sent_at', 'TIMESTAMP NULL DEFAULT NULL AFTER `email_verification_token_hash`');
        self::$schemaReady = true;
    }

    public static function sendVerificationForUserId(PDO $pdo, int $userId): bool
    {
        self::ensureSchema($pdo);
        $st = $pdo->prepare('SELECT id, name, email, email_verified_at FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($user) || ! empty($user['email_verified_at'])) {
            return true;
        }

        return self::sendVerification($pdo, $user);
    }

    public static function sendVerificationByEmail(PDO $pdo, string $email): bool
    {
        self::ensureSchema($pdo);
        $st = $pdo->prepare('SELECT id, name, email, email_verified_at FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $st->execute([$email]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($user) || ! empty($user['email_verified_at'])) {
            return true;
        }

        return self::sendVerification($pdo, $user);
    }

    public static function verify(PDO $pdo, string $email, string $token): bool
    {
        self::ensureSchema($pdo);
        $email = strtolower(trim($email));
        $token = trim($token);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '') {
            return false;
        }

        $hash = hash('sha256', $token);
        $st = $pdo->prepare(
            'UPDATE users
             SET email_verified_at = COALESCE(email_verified_at, NOW()),
                 email_verification_token_hash = NULL,
                 email_verification_sent_at = NULL,
                 updated_at = NOW()
             WHERE LOWER(email) = LOWER(?)
               AND email_verification_token_hash = ?
               AND email_verification_sent_at IS NOT NULL
               AND email_verification_sent_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)
             LIMIT 1'
        );
        $st->execute([$email, $hash]);

        return $st->rowCount() > 0;
    }

    /** @param array<string,mixed> $user */
    private static function sendVerification(PDO $pdo, array $user): bool
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $name = trim((string) ($user['name'] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $st = $pdo->prepare(
            'UPDATE users
             SET email_verification_token_hash = ?,
                 email_verification_sent_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?
             LIMIT 1'
        );
        $st->execute([$hash, (int) $user['id']]);

        $link = url('/verify-email?email='.rawurlencode($email).'&token='.rawurlencode($token));
        $appName = (string) (App::config('name') ?? 'Laundry System');
        $displayName = $name !== '' ? $name : 'there';
        $subject = 'Verify your '.$appName.' account';
        $html = '<p>Hello '.htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').',</p>'
            .'<p>Please verify your email address to activate your '.$appName.' account.</p>'
            .'<p><a href="'.htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" style="display:inline-block;padding:10px 14px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;">Verify email address</a></p>'
            .'<p>If the button does not work, open this link:<br>'.htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        $text = "Hello {$displayName},\n\nPlease verify your email address to activate your {$appName} account:\n{$link}\n";

        return Mailer::send($email, $subject, $html, $text);
    }

    private static function ensureColumn(PDO $pdo, string $column, string $definition): void
    {
        $st = $pdo->prepare(
            'SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1'
        );
        $st->execute(['users', $column]);
        if ($st->fetch(PDO::FETCH_ASSOC) !== false) {
            return;
        }
        $pdo->exec('ALTER TABLE `users` ADD COLUMN `'.$column.'` '.$definition);
    }
}
