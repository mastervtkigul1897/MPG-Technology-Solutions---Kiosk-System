<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class SmsGatewayController
{
    private const MAX_PENDING_BATCH = 20;
    /** @var array<string,bool> */
    private const ALLOWED_UPDATE_STATUSES = [
        'pending' => true,
        'processing' => true,
        'sent' => true,
        'failed' => true,
        'delivered' => true,
    ];

    public function create(Request $request): Response
    {
        if ($auth = $this->authorize($request)) {
            return $auth;
        }
        $body = $this->jsonBody($request);
        if ($body === null) {
            return json_response(['status' => 'error', 'message' => 'Invalid JSON payload.'], 422);
        }
        $deviceId = trim((string) ($body['device_id'] ?? ''));
        $phone = trim((string) ($body['phone'] ?? ''));
        $message = trim((string) ($body['message'] ?? ''));
        if (! $this->isValidDeviceId($deviceId)) {
            return json_response(['status' => 'error', 'message' => 'Invalid device_id.'], 422);
        }
        if (! $this->isValidPhone($phone)) {
            return json_response(['status' => 'error', 'message' => 'Invalid phone format.'], 422);
        }
        if ($message === '' || mb_strlen($message) > 1000) {
            return json_response(['status' => 'error', 'message' => 'Message is required (max 1000 chars).'], 422);
        }

        try {
            $pdo = App::db();
            $st = $pdo->prepare(
                'INSERT INTO sms_queue
                 (device_id, phone, message, status, retry_count, error_message, created_at, sent_at, updated_at)
                 VALUES (?, ?, ?, "pending", 0, NULL, NOW(), NULL, NOW())'
            );
            $st->execute([$deviceId, $phone, $message]);
            $id = (int) $pdo->lastInsertId();
            return json_response(['status' => 'success', 'id' => (string) $id], 201);
        } catch (\Throwable $e) {
            error_log('SMS create queue failed: '.$e->getMessage());
            return json_response(['status' => 'error', 'message' => 'Could not queue SMS.'], 500);
        }
    }

    public function pending(Request $request): Response
    {
        if ($auth = $this->authorize($request)) {
            return $auth;
        }
        $deviceId = trim((string) $request->query('device_id', ''));
        if (! $this->isValidDeviceId($deviceId)) {
            return json_response(['status' => 'error', 'message' => 'Invalid device_id.'], 422);
        }

        try {
            $pdo = App::db();
            $pdo->beginTransaction();
            $st = $pdo->prepare(
                'SELECT id, phone, message
                 FROM sms_queue
                 WHERE device_id = ?
                   AND status = "pending"
                 ORDER BY created_at ASC, id ASC
                 LIMIT '.self::MAX_PENDING_BATCH.'
                 FOR UPDATE'
            );
            $st->execute([$deviceId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows !== []) {
                $ids = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
                $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
                if ($ids !== []) {
                    $marks = implode(', ', array_fill(0, count($ids), '?'));
                    $params = array_merge([$deviceId], $ids);
                    $up = $pdo->prepare(
                        "UPDATE sms_queue
                         SET status = 'processing', updated_at = NOW()
                         WHERE device_id = ?
                           AND status = 'pending'
                           AND id IN ($marks)"
                    );
                    $up->execute($params);
                }
            }
            $pdo->commit();

            $messages = array_map(static function (array $row): array {
                return [
                    'id' => (string) ((int) ($row['id'] ?? 0)),
                    'phone' => (string) ($row['phone'] ?? ''),
                    'message' => (string) ($row['message'] ?? ''),
                ];
            }, $rows);

            return json_response(['status' => 'success', 'messages' => $messages]);
        } catch (\Throwable $e) {
            $pdo = App::db();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('SMS pending fetch failed: '.$e->getMessage());
            return json_response(['status' => 'error', 'message' => 'Could not fetch pending SMS jobs.'], 500);
        }
    }

    public function updateStatus(Request $request): Response
    {
        if ($auth = $this->authorize($request)) {
            return $auth;
        }
        $body = $this->jsonBody($request);
        if ($body === null) {
            return json_response(['status' => 'error', 'message' => 'Invalid JSON payload.'], 422);
        }
        $id = (int) ($body['id'] ?? 0);
        $deviceId = trim((string) ($body['device_id'] ?? ''));
        $status = strtolower(trim((string) ($body['status'] ?? '')));
        $errorMessage = $body['error_message'] ?? null;
        $sentAtRaw = trim((string) ($body['sent_at'] ?? ''));
        if ($id < 1) {
            return json_response(['status' => 'error', 'message' => 'Invalid id.'], 422);
        }
        if (! $this->isValidDeviceId($deviceId)) {
            return json_response(['status' => 'error', 'message' => 'Invalid device_id.'], 422);
        }
        if (! isset(self::ALLOWED_UPDATE_STATUSES[$status])) {
            return json_response(['status' => 'error', 'message' => 'Invalid status.'], 422);
        }
        $sentAt = null;
        if ($sentAtRaw !== '') {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $sentAtRaw);
            if (! $dt || $dt->format('Y-m-d H:i:s') !== $sentAtRaw) {
                return json_response(['status' => 'error', 'message' => 'Invalid sent_at format. Use Y-m-d H:i:s.'], 422);
            }
            $sentAt = $sentAtRaw;
        } elseif (in_array($status, ['sent', 'delivered'], true)) {
            $sentAt = date('Y-m-d H:i:s');
        }
        $safeError = null;
        if ($errorMessage !== null) {
            $safeError = trim((string) $errorMessage);
            if ($safeError === '') {
                $safeError = null;
            } elseif (mb_strlen($safeError) > 2000) {
                $safeError = mb_substr($safeError, 0, 2000);
            }
        }

        try {
            $pdo = App::db();
            $sql = 'UPDATE sms_queue
                    SET status = ?,
                        error_message = ?,
                        sent_at = ?,
                        retry_count = retry_count + ?,
                        updated_at = NOW()
                    WHERE id = ? AND device_id = ?';
            $retryIncrement = $status === 'failed' ? 1 : 0;
            $st = $pdo->prepare($sql);
            $st->execute([$status, $safeError, $sentAt, $retryIncrement, $id, $deviceId]);
            if ($st->rowCount() < 1) {
                return json_response(['status' => 'error', 'message' => 'Message not found for this device.'], 404);
            }
            return json_response(['status' => 'success']);
        } catch (\Throwable $e) {
            error_log('SMS update-status failed: '.$e->getMessage());
            return json_response(['status' => 'error', 'message' => 'Could not update SMS status.'], 500);
        }
    }

    private function authorize(Request $request): ?Response
    {
        if (! $this->isSecureRequest($request)) {
            return json_response(['status' => 'error', 'message' => 'HTTPS is required.'], 403);
        }
        $cfg = App::config('sms_api', []);
        $keys = is_array($cfg['keys'] ?? null) ? $cfg['keys'] : [];
        $keys = array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $keys), static fn (string $v): bool => $v !== ''));
        if ($keys === []) {
            error_log('SMS API unauthorized: no configured API keys.');
            return json_response(['status' => 'error', 'message' => 'Unauthorized.'], 401);
        }
        $authHeader = trim((string) ($request->server['HTTP_AUTHORIZATION'] ?? $request->server['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if (! preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            return json_response(['status' => 'error', 'message' => 'Unauthorized.'], 401);
        }
        $token = trim((string) ($m[1] ?? ''));
        if ($token === '') {
            return json_response(['status' => 'error', 'message' => 'Unauthorized.'], 401);
        }
        foreach ($keys as $allowed) {
            if (hash_equals($allowed, $token)) {
                return null;
            }
        }
        return json_response(['status' => 'error', 'message' => 'Forbidden.'], 403);
    }

    private function isSecureRequest(Request $request): bool
    {
        $cfg = App::config('sms_api', []);
        $requireHttps = (bool) ($cfg['require_https'] ?? true);
        if (! $requireHttps) {
            return true;
        }
        $https = strtolower(trim((string) ($request->server['HTTPS'] ?? '')));
        if ($https === 'on' || $https === '1') {
            return true;
        }
        $forwarded = strtolower(trim((string) ($request->server['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwarded === 'https') {
            return true;
        }
        $host = strtolower(trim((string) ($request->server['HTTP_HOST'] ?? '')));
        return str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
    }

    /** @return array<string,mixed>|null */
    private function jsonBody(Request $request): ?array
    {
        $raw = file_get_contents('php://input');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function isValidDeviceId(string $deviceId): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $deviceId);
    }

    private function isValidPhone(string $phone): bool
    {
        return (bool) preg_match('/^\+?[0-9]{10,15}$/', $phone);
    }
}

