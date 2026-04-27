<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\Mailer;
use PDO;

final class EmailCampaignController
{
    /** @var list<string> */
    private const FREE_PLAN_CODES = ['trial', 'free', 'free_trial', 'free_access'];

    public function index(Request $request): Response
    {
        $pdo = App::db();
        [$freeCount, $premiumCount] = $this->recipientCounts($pdo);
        $recipientOptions = $this->fetchAllRecipientOptions($pdo);
        $campaignLogs = $this->fetchRecentCampaignLogs($pdo);

        return view_page('Email Campaign', 'super-admin.email-campaign.index', [
            'free_count' => $freeCount,
            'premium_count' => $premiumCount,
            'recipient_options' => $recipientOptions,
            'campaign_logs' => $campaignLogs,
        ]);
    }

    public function send(Request $request): Response
    {
        $recipientMode = strtolower(trim((string) $request->input('recipient_mode', 'segment')));
        $segment = strtolower(trim((string) $request->input('segment')));
        $subject = trim((string) $request->input('subject'));
        $htmlBody = (string) $request->input('html_body');
        $selectedRecipientIdsRaw = $request->input('selected_recipient_ids', []);
        $selectedRecipientIds = [];
        if (is_array($selectedRecipientIdsRaw)) {
            foreach ($selectedRecipientIdsRaw as $idRaw) {
                $id = (int) $idRaw;
                if ($id > 0) {
                    $selectedRecipientIds[$id] = $id;
                }
            }
        }
        $selectedRecipientIds = array_values($selectedRecipientIds);

        $errors = [];
        if (! in_array($recipientMode, ['segment', 'selected_emails'], true)) {
            $errors[] = 'Recipient mode is required.';
        }
        if ($recipientMode === 'segment' && ! in_array($segment, ['free', 'premium'], true)) {
            $errors[] = 'Recipient segment is required.';
        }
        if ($recipientMode === 'selected_emails' && $selectedRecipientIds === []) {
            $errors[] = 'Select at least one recipient email.';
        }
        if ($subject === '') {
            $errors[] = 'Email subject is required.';
        }
        if (trim($htmlBody) === '') {
            $errors[] = 'Email HTML body is required.';
        }
        if ($errors !== []) {
            session_flash('errors', $errors);
            return redirect(route('super-admin.email-campaign.index'));
        }

        $pdo = App::db();
        if ($recipientMode === 'selected_emails') {
            $recipients = $this->fetchRecipientsByIds($pdo, $selectedRecipientIds);
        } else {
            $recipients = $this->fetchRecipients($pdo, $segment);
        }
        if ($recipients === []) {
            session_flash('errors', ['No recipients matched the selected segment.']);
            return redirect(route('super-admin.email-campaign.index'));
        }

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed++;
                continue;
            }
            $name = trim((string) ($recipient['name'] ?? 'Store Owner'));
            $storeName = trim((string) ($recipient['store_name'] ?? 'Store'));
            $personalizedHtml = str_replace(
                ['{{owner_name}}', '{{store_name}}'],
                [$name, $storeName],
                $htmlBody
            );
            $textBody = trim((string) preg_replace('/\s+/', ' ', strip_tags($personalizedHtml)));
            try {
                $ok = Mailer::send($email, $subject, $personalizedHtml, $textBody !== '' ? $textBody : null);
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        $actor = Auth::user();
        ActivityLogger::log(
            null,
            (int) ($actor['id'] ?? 0),
            (string) ($actor['role'] ?? 'super_admin'),
            'email_campaign',
            'send',
            $request,
            'Super admin sent an email campaign.',
            [
                'recipient_mode' => $recipientMode,
                'segment' => $segment,
                'subject' => $subject,
                'total_recipients' => count($recipients),
                'sent' => $sent,
                'failed' => $failed,
            ]
        );
        $this->insertCampaignLog(
            $pdo,
            (int) ($actor['id'] ?? 0),
            $recipientMode,
            $recipientMode === 'segment' ? $segment : null,
            $subject,
            count($recipients),
            $sent,
            $failed
        );

        session_flash('status', "Campaign finished. Sent: {$sent}. Failed: {$failed}.");
        return redirect(route('super-admin.email-campaign.index'));
    }

    /** @return array{0:int,1:int} */
    private function recipientCounts(PDO $pdo): array
    {
        $freeCount = 0;
        $premiumCount = 0;
        $st = $pdo->query(
            "SELECT
                SUM(CASE WHEN LOWER(TRIM(t.plan)) IN ('trial','free','free_trial','free_access') THEN 1 ELSE 0 END) AS free_count,
                SUM(CASE WHEN LOWER(TRIM(t.plan)) NOT IN ('trial','free','free_trial','free_access') THEN 1 ELSE 0 END) AS premium_count
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE u.role = 'tenant_admin'
               AND u.email IS NOT NULL
               AND TRIM(u.email) <> ''"
        );
        if ($st !== false) {
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $freeCount = (int) ($row['free_count'] ?? 0);
            $premiumCount = (int) ($row['premium_count'] ?? 0);
        }

        return [$freeCount, $premiumCount];
    }

    /**
     * @return list<array{id:int,email:string,name:string,store_name:string}>
     */
    private function fetchRecipients(PDO $pdo, string $segment): array
    {
        $isFreeSegment = $segment === 'free';
        $wherePlan = $isFreeSegment
            ? "LOWER(TRIM(t.plan)) IN ('trial','free','free_trial','free_access')"
            : "LOWER(TRIM(t.plan)) NOT IN ('trial','free','free_trial','free_access')";

        $sql = "SELECT u.id, u.email, u.name, t.name AS store_name
                FROM users u
                INNER JOIN tenants t ON t.id = u.tenant_id
                WHERE u.role = 'tenant_admin'
                  AND u.email IS NOT NULL
                  AND TRIM(u.email) <> ''
                  AND {$wherePlan}
                ORDER BY u.id ASC";
        $st = $pdo->query($sql);
        if ($st === false) {
            return [];
        }
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array{id:int,email:string,name:string,store_name:string,plan:string}>
     */
    private function fetchAllRecipientOptions(PDO $pdo): array
    {
        $st = $pdo->query(
            "SELECT u.id, u.email, u.name, t.name AS store_name, t.plan
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email IS NOT NULL
               AND TRIM(u.email) <> ''
             ORDER BY u.email ASC"
        );
        if ($st === false) {
            return [];
        }
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param list<int> $recipientIds
     * @return list<array{id:int,email:string,name:string,store_name:string}>
     */
    private function fetchRecipientsByIds(PDO $pdo, array $recipientIds): array
    {
        if ($recipientIds === []) {
            return [];
        }
        $ids = array_values(array_unique(array_map(static fn (int $v): int => max(0, $v), $recipientIds)));
        $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT u.id, u.email, u.name, t.name AS store_name
                FROM users u
                INNER JOIN tenants t ON t.id = u.tenant_id
                WHERE u.email IS NOT NULL
                  AND TRIM(u.email) <> ''
                  AND u.id IN ({$placeholders})
                ORDER BY u.id ASC";
        $st = $pdo->prepare($sql);
        $st->execute($ids);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchRecentCampaignLogs(PDO $pdo): array
    {
        if (! $this->hasTable($pdo, 'super_admin_email_campaign_logs')) {
            return [];
        }
        $st = $pdo->query(
            "SELECT
                l.id,
                l.super_admin_user_id,
                l.recipient_mode,
                l.recipient_segment,
                l.subject,
                l.recipients_total,
                l.sent_count,
                l.failed_count,
                l.created_at,
                u.name AS sender_name,
                u.email AS sender_email
             FROM super_admin_email_campaign_logs l
             LEFT JOIN users u ON u.id = l.super_admin_user_id
             ORDER BY l.id DESC
             LIMIT 50"
        );
        if ($st === false) {
            return [];
        }
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function insertCampaignLog(
        PDO $pdo,
        int $superAdminUserId,
        string $recipientMode,
        ?string $recipientSegment,
        string $subject,
        int $recipientsTotal,
        int $sentCount,
        int $failedCount
    ): void {
        if (! $this->hasTable($pdo, 'super_admin_email_campaign_logs')) {
            return;
        }
        try {
            $pdo->prepare(
                'INSERT INTO super_admin_email_campaign_logs
                (super_admin_user_id, recipient_mode, recipient_segment, subject, recipients_total, sent_count, failed_count, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $superAdminUserId > 0 ? $superAdminUserId : null,
                $recipientMode,
                $recipientSegment,
                $subject,
                max(0, $recipientsTotal),
                max(0, $sentCount),
                max(0, $failedCount),
            ]);
        } catch (\Throwable) {
            // Do not block campaign flow when logger table is unavailable.
        }
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        if ($table === '') {
            return false;
        }
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?'
            );
            $st->execute([$table]);
            return (int) $st->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
