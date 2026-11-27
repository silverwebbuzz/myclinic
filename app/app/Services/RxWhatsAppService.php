<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Support\MessagingSettings;
use PDO;

/**
 * Prescription on WhatsApp — the per-clinic allowance, secure links and send.
 *
 * Allowance (per clinic):
 *   - FREE: N per calendar month (platform default rx_wa_free_monthly = 100,
 *     admin can override per clinic). Resets on the 1st; unused don't carry over.
 *   - PAID: purchased packs (rx_wa_accounts.paid_balance). Never expire.
 *   Free is always used first, then paid.
 *
 * A message is RESERVED when it is queued (so the doctor sees the count drop
 * at once and we can refuse when nothing is left) and RELEASED (refunded) if
 * the send is skipped or fails — the clinic only pays for messages Meta accepts.
 *
 * Only the 'rx_whatsapp_doc' template is counted here. It never touches the
 * general messaging_quota (reminders etc.) and never falls back to SMS.
 *
 * The patient receives the prescription PDF as a WhatsApp document. Meta
 * fetches it from our secure link (/rx/{token}); the same link goes in the
 * email and in the doctor's own-WhatsApp fallback. Links expire after 30 days.
 */
final class RxWhatsAppService
{
    public const TEMPLATE = 'rx_whatsapp_doc';
    private const LINK_DAYS = 30;

    // =====================================================================
    // Settings
    // =====================================================================

    public static function defaultFreeMonthly(): int
    {
        return max(0, (int) MessagingSettings::get('rx_wa_free_monthly', '100'));
    }

    /** @return list<array{qty: int, price: float}> Prices in rupees before GST. */
    public static function packs(): array
    {
        $raw = json_decode((string) MessagingSettings::get('rx_wa_packs', '[]'), true);
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $p) {
            $qty = (int) ($p['qty'] ?? 0);
            $price = round((float) ($p['price'] ?? 0), 2);
            if ($qty > 0 && $price > 0) {
                $out[] = ['qty' => $qty, 'price' => $price];
            }
        }

        return $out;
    }

    /**
     * Attach the prescription PDF as the message's Document header. Only turn
     * on when the approved Meta template HAS a Document header: Meta rejects
     * a header the template doesn't have (and requires one when it does).
     * Admin → Rx WhatsApp → "Attach prescription PDF".
     */
    public static function attachPdf(): bool
    {
        return MessagingSettings::get('rx_wa_attach_pdf', '0') === '1';
    }

    /** WhatsApp connected AND the template approved. Otherwise nothing is reserved. */
    public static function channelReady(): bool
    {
        return MessagingSettings::whatsappConfigured() && WaTemplateService::isApproved(self::TEMPLATE);
    }

    private static function period(): string
    {
        return date('Y-m');
    }

    // =====================================================================
    // Allowance
    // =====================================================================

    /** @return array{clinic_id: int, paid_balance: int, monthly_free_override: ?int, is_enabled: bool} */
    public static function account(int $clinicId): array
    {
        $row = null;
        try {
            $stmt = Database::connection()->prepare('SELECT * FROM rx_wa_accounts WHERE clinic_id = ? LIMIT 1');
            $stmt->execute([$clinicId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            // Patch not run yet — behave as a default account.
        }

        return [
            'clinic_id' => $clinicId,
            'paid_balance' => (int) ($row['paid_balance'] ?? 0),
            'monthly_free_override' => isset($row['monthly_free_override']) ? (int) $row['monthly_free_override'] : null,
            'is_enabled' => $row === null ? true : (int) $row['is_enabled'] === 1,
        ];
    }

    private static function ensureAccount(int $clinicId): void
    {
        Database::connection()
            ->prepare('INSERT IGNORE INTO rx_wa_accounts (clinic_id) VALUES (?)')
            ->execute([$clinicId]);
    }

    public static function freeUsedThisMonth(int $clinicId): int
    {
        try {
            $stmt = Database::connection()->prepare('SELECT free_used FROM rx_wa_monthly WHERE clinic_id = ? AND period_ym = ?');
            $stmt->execute([$clinicId, self::period()]);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Everything the doctor panel shows about the allowance.
     *
     * @return array<string, mixed>
     */
    public static function status(int $clinicId): array
    {
        $acc = self::account($clinicId);
        $limit = $acc['monthly_free_override'] ?? self::defaultFreeMonthly();
        $used = self::freeUsedThisMonth($clinicId);
        $freeLeft = max(0, $limit - $used);
        $enabled = $acc['is_enabled'];

        return [
            'enabled' => $enabled,
            'ready' => self::channelReady(),
            'free_limit' => $limit,
            'free_used' => $used,
            'free_left' => $enabled ? $freeLeft : 0,
            'paid_balance' => $acc['paid_balance'],
            'total_left' => $enabled ? $freeLeft + $acc['paid_balance'] : 0,
            'period_label' => date('F Y'),
            'resets_on' => date('d M Y', strtotime('first day of next month')),
        ];
    }

    /**
     * Take one message from the allowance: free first, then paid. Atomic
     * (conditional UPDATEs), so two doctors sending at once can't overspend.
     *
     * @return array{charge: string, period: string, ledger_id: int}|null  null = nothing left
     */
    public static function reserve(int $clinicId, ?int $visitId, ?int $userId): ?array
    {
        $acc = self::account($clinicId);
        if (!$acc['is_enabled']) {
            return null;
        }
        $pdo = Database::connection();
        $period = self::period();
        $limit = $acc['monthly_free_override'] ?? self::defaultFreeMonthly();

        $pdo->prepare('INSERT IGNORE INTO rx_wa_monthly (clinic_id, period_ym, free_used) VALUES (?, ?, 0)')
            ->execute([$clinicId, $period]);
        $free = $pdo->prepare('UPDATE rx_wa_monthly SET free_used = free_used + 1 WHERE clinic_id = ? AND period_ym = ? AND free_used < ?');
        $free->execute([$clinicId, $period, $limit]);

        if ($free->rowCount() === 1) {
            $charge = 'free';
        } else {
            self::ensureAccount($clinicId);
            $paid = $pdo->prepare('UPDATE rx_wa_accounts SET paid_balance = paid_balance - 1 WHERE clinic_id = ? AND paid_balance > 0');
            $paid->execute([$clinicId]);
            if ($paid->rowCount() !== 1) {
                return null;
            }
            $charge = 'paid';
        }

        $ledgerId = self::ledger($clinicId, $charge === 'free' ? 'free_use' : 'paid_use', -1, $period, $visitId, null, null, null, $userId);

        return ['charge' => $charge, 'period' => $period, 'ledger_id' => $ledgerId];
    }

    /**
     * Give a reserved message back (send skipped/failed). Idempotent per
     * notification, so a retried failure path can't refund twice.
     */
    public static function release(int $clinicId, string $charge, string $period, ?int $visitId, ?int $notificationId, string $reason): void
    {
        try {
            $pdo = Database::connection();
            $entry = $charge === 'paid' ? 'paid_refund' : 'free_refund';
            if ($notificationId !== null) {
                $dup = $pdo->prepare("SELECT 1 FROM rx_wa_ledger WHERE notification_id = ? AND entry IN ('free_refund','paid_refund') LIMIT 1");
                $dup->execute([$notificationId]);
                if ($dup->fetchColumn()) {
                    return;
                }
            }
            if ($charge === 'paid') {
                self::ensureAccount($clinicId);
                $pdo->prepare('UPDATE rx_wa_accounts SET paid_balance = paid_balance + 1 WHERE clinic_id = ?')
                    ->execute([$clinicId]);
            } else {
                $pdo->prepare('UPDATE rx_wa_monthly SET free_used = free_used - 1 WHERE clinic_id = ? AND period_ym = ? AND free_used > 0')
                    ->execute([$clinicId, $period]);
            }
            self::ledger($clinicId, $entry, 1, $period, $visitId, $notificationId, null, mb_substr($reason, 0, 250), null);
        } catch (\Throwable $e) {
            error_log('[RxWhatsApp] release failed: ' . $e->getMessage());
        }
    }

    /**
     * Refund from a notification row's payload (used by NotificationProcessor).
     *
     * @param array<string, mixed> $payload
     */
    public static function releaseForNotification(int $clinicId, array $payload, int $notificationId, string $reason): void
    {
        if (empty($payload['rx_charge']) || empty($payload['rx_period'])) {
            return;
        }
        self::release(
            $clinicId,
            (string) $payload['rx_charge'],
            (string) $payload['rx_period'],
            isset($payload['visit_id']) ? (int) $payload['visit_id'] : null,
            $notificationId,
            $reason,
        );
    }

    /** Add purchased / granted messages to the paid balance. */
    public static function credit(int $clinicId, int $qty, string $entry, ?int $orderId, ?int $userId, string $note): void
    {
        if ($qty <= 0) {
            return;
        }
        self::ensureAccount($clinicId);
        Database::connection()
            ->prepare('UPDATE rx_wa_accounts SET paid_balance = paid_balance + ? WHERE clinic_id = ?')
            ->execute([$qty, $clinicId]);
        self::ledger($clinicId, $entry, $qty, self::period(), null, null, $orderId, mb_substr($note, 0, 250), $userId);
    }

    /** Admin correction: remove up to $qty from the paid balance. Returns how many were removed. */
    public static function deduct(int $clinicId, int $qty, string $note): int
    {
        $have = self::account($clinicId)['paid_balance'];
        $take = min($have, max(0, $qty));
        if ($take <= 0) {
            return 0;
        }
        Database::connection()
            ->prepare('UPDATE rx_wa_accounts SET paid_balance = paid_balance - ? WHERE clinic_id = ? AND paid_balance >= ?')
            ->execute([$take, $clinicId, $take]);
        self::ledger($clinicId, 'admin_deduct', -$take, self::period(), null, null, null, mb_substr($note, 0, 250), null);

        return $take;
    }

    private static function ledger(int $clinicId, string $entry, int $qty, string $period, ?int $visitId, ?int $notificationId, ?int $orderId, ?string $note, ?int $userId): int
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO rx_wa_ledger (clinic_id, entry, qty, period_ym, visit_id, notification_id, order_id, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$clinicId, $entry, $qty, $period, $visitId, $notificationId, $orderId, $note, $userId]);

        return (int) $pdo->lastInsertId();
    }

    // =====================================================================
    // Secure links
    // =====================================================================

    private static function secret(): string
    {
        $s = (string) ($_ENV['JWT_SECRET'] ?? '');
        if ($s === '' || str_starts_with($s, 'change-me')) {
            throw new \RuntimeException('JWT_SECRET is not set — cannot create secure prescription links.');
        }

        return $s;
    }

    private static function tokenFor(int $id, string $nonce): string
    {
        return $id . '-' . substr(hash_hmac('sha256', $id . '|' . $nonce, self::secret()), 0, 40);
    }

    public static function publicUrl(string $token): string
    {
        return rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/') . '/rx/' . $token;
    }

    /**
     * Render the visit's prescription and (re)publish it behind the visit's
     * secure link. One link per visit: re-sharing refreshes the PDF and the
     * 30-day expiry but keeps the same URL.
     *
     * @return array{url: string, token: string, expires_at: string}
     */
    public static function shareLink(int $clinicId, int $visitId): array
    {
        $visit = VisitService::findDetailed($clinicId, $visitId);
        if ($visit === null) {
            throw new \RuntimeException('Visit not found.');
        }
        $patient = PatientService::find($clinicId, (int) $visit['patient_id']) ?? [];
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];
        $lines = PrescriptionService::forVisit($clinicId, $visitId);

        $rendered = PrescriptionPdfService::generate($visit, $patient, $clinic, $lines, 'A5');
        $relDir = 'storage/prescriptions/' . $clinicId . '/shared';
        $absDir = dirname(__DIR__, 2) . '/' . $relDir;
        if (!is_dir($absDir) && !@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Cannot create shared prescription directory.');
        }
        $relFile = $relDir . '/visit-' . $visitId . '.pdf';
        if (!@copy($rendered, dirname(__DIR__, 2) . '/' . $relFile)) {
            throw new \RuntimeException('Could not publish the prescription PDF.');
        }

        $expires = date('Y-m-d H:i:s', time() + self::LINK_DAYS * 86400);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, token_nonce FROM rx_share_links WHERE clinic_id = ? AND visit_id = ? LIMIT 1');
        $stmt->execute([$clinicId, $visitId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $id = (int) $row['id'];
            $nonce = (string) $row['token_nonce'];
            $pdo->prepare('UPDATE rx_share_links SET file_path = ?, expires_at = ?, revoked_at = NULL WHERE id = ?')
                ->execute([$relFile, $expires, $id]);
        } else {
            $nonce = bin2hex(random_bytes(16));
            $pdo->prepare(
                'INSERT INTO rx_share_links (clinic_id, visit_id, patient_id, token_nonce, file_path, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$clinicId, $visitId, (int) $visit['patient_id'], $nonce, $relFile, $expires]);
            $id = (int) $pdo->lastInsertId();
        }

        $token = self::tokenFor($id, $nonce);

        return ['url' => self::publicUrl($token), 'token' => $token, 'expires_at' => $expires];
    }

    /**
     * Resolve a public token to the PDF on disk. Null when malformed, forged,
     * expired, revoked or the file is gone. Counts the view.
     *
     * @return array{abs_path: string, visit_id: int}|null
     */
    public static function resolveToken(string $token): ?array
    {
        if (!preg_match('/^(\d{1,19})-([a-f0-9]{40})$/', $token, $m)) {
            return null;
        }
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT * FROM rx_share_links WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $m[1]]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !hash_equals(self::tokenFor((int) $row['id'], (string) $row['token_nonce']), $token)) {
                return null;
            }
            if (!empty($row['revoked_at']) || strtotime((string) $row['expires_at']) < time()) {
                return null;
            }
            $abs = dirname(__DIR__, 2) . '/' . ltrim((string) $row['file_path'], '/');
            if (!is_file($abs)) {
                return null;
            }
            $pdo->prepare('UPDATE rx_share_links SET view_count = view_count + 1, last_viewed_at = NOW() WHERE id = ?')
                ->execute([(int) $row['id']]);

            return ['abs_path' => $abs, 'visit_id' => (int) $row['visit_id']];
        } catch (\Throwable $e) {
            error_log('[RxWhatsApp] resolveToken: ' . $e->getMessage());

            return null;
        }
    }

    // =====================================================================
    // Send
    // =====================================================================

    /**
     * Queue the prescription to the patient's WhatsApp, charged to the
     * clinic's allowance. Also emails the secure link (free) when the patient
     * has an email address.
     *
     * $source: 'auto' (on visit completion) or 'manual' (doctor clicked Send —
     * the button text states the patient agreed, so it records an opt-in).
     *
     * @return array{ok: bool, code: string, message: string}
     *   code: queued | not_ready | disabled | no_allowance | no_phone | no_medicines | opted_out | error
     */
    public static function sendForVisit(int $clinicId, int $visitId, string $source, ?int $userId, int $delaySeconds = 0): array
    {
        try {
            $visit = VisitService::findDetailed($clinicId, $visitId);
            if ($visit === null) {
                return self::result(false, 'error', 'Visit not found.');
            }
            $patient = PatientService::find($clinicId, (int) $visit['patient_id']);
            if ($patient === null) {
                return self::result(false, 'error', 'Patient not found.');
            }
            if (PrescriptionService::forVisit($clinicId, $visitId) === []) {
                return self::result(false, 'no_medicines', 'No medicines on this visit yet.');
            }
            $phone = (string) ($patient['phone'] ?? '');
            if (strlen(preg_replace('/\D/', '', $phone) ?? '') < 10) {
                return self::result(false, 'no_phone', 'This patient has no mobile number on file.');
            }

            $status = self::status($clinicId);
            if (!$status['ready']) {
                return self::result(false, 'not_ready', 'WhatsApp prescription sending is not switched on yet.');
            }
            if (!$status['enabled']) {
                return self::result(false, 'disabled', 'WhatsApp prescriptions are turned off for this clinic.');
            }
            if (MessagingConsent::isOptedOut($phone, 'whatsapp')) {
                return self::result(false, 'opted_out', 'This patient replied STOP, so WhatsApp messages to them are blocked.');
            }

            $link = self::shareLink($clinicId, $visitId);

            $reserved = self::reserve($clinicId, $visitId, $userId);
            if ($reserved === null) {
                return self::result(false, 'no_allowance', 'No WhatsApp prescriptions left this month.');
            }

            $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];
            $visitedAt = !empty($visit['visited_at']) ? strtotime((string) $visit['visited_at']) : time();
            $payload = [
                'patient_name' => trim((string) ($patient['name'] ?? '')) ?: 'there',
                'doctor_name' => self::doctorName((string) ($visit['doctor_name'] ?? '')),
                'clinic_name' => trim((string) ($clinic['name'] ?? '')) ?: 'the clinic',
                'visit_date' => date('d M Y', $visitedAt ?: time()),
                'clinic_phone' => self::clinicPhone($clinicId, $clinic),
                // Internal only (never sent to Meta): refund on a failed send.
                'rx_url' => $link['url'],
                'visit_id' => $visitId,
                'rx_charge' => $reserved['charge'],
                'rx_period' => $reserved['period'],
                'rx_source' => $source,
            ];

            // PDF as the Document header — only when the Meta template has one
            // (see attachPdf()). Meta downloads it from the secure link.
            if (self::attachPdf()) {
                $payload['header_document_url'] = $link['url'];
                $payload['header_document_filename'] = 'Prescription-' . date('d-M-Y', $visitedAt ?: time()) . '.pdf';
            }

            $when = date('Y-m-d H:i:s', time() + max(0, $delaySeconds));
            $notificationId = NotificationService::queueWhatsApp($clinicId, (int) $patient['id'], $phone, self::TEMPLATE, $payload, $when);
            if ($notificationId === null) {
                self::release($clinicId, $reserved['charge'], $reserved['period'], $visitId, null, 'queue insert failed');

                return self::result(false, 'error', 'Could not queue the WhatsApp message.');
            }
            Database::connection()
                ->prepare('UPDATE rx_wa_ledger SET notification_id = ? WHERE id = ?')
                ->execute([$notificationId, $reserved['ledger_id']]);

            // Email copy with the same secure link (not counted).
            self::queueEmail($clinicId, $patient, $clinic, $link['url'], $when);

            if ($source === 'manual') {
                MessagingConsent::recordOptIn($phone, 'clinic_rx_send', null, $_SERVER['REMOTE_ADDR'] ?? null);
            }

            return self::result(true, 'queued', 'Prescription queued for the patient\'s WhatsApp.');
        } catch (\Throwable $e) {
            error_log('[RxWhatsApp] sendForVisit ' . $visitId . ': ' . $e->getMessage());

            return self::result(false, 'error', 'Could not send the prescription. Please try again.');
        }
    }

    /**
     * Email the secure link only (no WhatsApp, not counted) — used when the
     * WhatsApp send didn't go ahead. Silent no-op without an email address.
     */
    public static function emailOnly(int $clinicId, int $visitId, int $delaySeconds = 0): void
    {
        try {
            $visit = VisitService::findDetailed($clinicId, $visitId);
            $patient = $visit !== null ? PatientService::find($clinicId, (int) $visit['patient_id']) : null;
            $email = trim((string) ($patient['email'] ?? ''));
            if ($patient === null || $email === '' || PrescriptionService::forVisit($clinicId, $visitId) === []) {
                return;
            }
            $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];
            $link = self::shareLink($clinicId, $visitId);
            self::queueEmail($clinicId, $patient, $clinic, $link['url'], date('Y-m-d H:i:s', time() + max(0, $delaySeconds)));
        } catch (\Throwable $e) {
            error_log('[RxWhatsApp] emailOnly ' . $visitId . ': ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $clinic
     */
    private static function queueEmail(int $clinicId, array $patient, array $clinic, string $url, string $when): void
    {
        NotificationService::queueEmail($clinicId, (int) $patient['id'], $patient['email'] ?? null, 'rx_delivery', [
            'patient_name' => $patient['name'] ?? '',
            'clinic_name' => $clinic['name'] ?? '',
            'rx_url' => $url,
        ], $when);
    }

    /**
     * Latest WhatsApp send for a visit, with its delivery status.
     *
     * @return array<string, mixed>|null
     */
    public static function lastSendForVisit(int $clinicId, int $visitId): ?array
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT l.id, l.entry, l.created_at, n.status, n.delivery_status, n.sent_at, n.error_log
                   FROM rx_wa_ledger l
                   LEFT JOIN notifications n ON n.id = l.notification_id
                  WHERE l.clinic_id = ? AND l.visit_id = ? AND l.entry IN ('free_use','paid_use')
                  ORDER BY l.id DESC LIMIT 1"
            );
            $stmt->execute([$clinicId, $visitId]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Recent sends for the clinic's usage page.
     *
     * @return list<array<string, mixed>>
     */
    public static function recentSends(int $clinicId, int $limit = 30): array
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT l.id, l.entry, l.visit_id, l.created_at, n.status, n.delivery_status, n.error_log,
                        p.name AS patient_name
                   FROM rx_wa_ledger l
                   LEFT JOIN notifications n ON n.id = l.notification_id
                   LEFT JOIN visits v ON v.id = l.visit_id AND v.clinic_id = l.clinic_id
                   LEFT JOIN patients p ON p.id = v.patient_id
                  WHERE l.clinic_id = ? AND l.entry IN ('free_use','paid_use')
                  ORDER BY l.id DESC LIMIT " . max(1, min(200, $limit))
            );
            $stmt->execute([$clinicId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Purchases and admin credits for the usage page.
     *
     * @return list<array<string, mixed>>
     */
    public static function credits(int $clinicId, int $limit = 20): array
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT l.entry, l.qty, l.note, l.created_at, o.total_amount,
                        si.id AS invoice_id, si.invoice_no
                   FROM rx_wa_ledger l
                   LEFT JOIN rx_wa_orders o ON o.id = l.order_id
                   LEFT JOIN saas_invoices si ON si.gateway_order_id = o.razorpay_order_id AND si.clinic_id = l.clinic_id
                  WHERE l.clinic_id = ? AND l.entry IN ('purchase','admin_grant','admin_deduct')
                  ORDER BY l.id DESC LIMIT " . max(1, min(100, $limit))
            );
            $stmt->execute([$clinicId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /** The template already says "Dr. {{2}}" — strip a leading Dr/Dr. from the name. */
    private static function doctorName(string $name): string
    {
        $name = trim((string) preg_replace('/^dr\.?\s+/i', '', trim($name)));

        return $name !== '' ? $name : 'your doctor';
    }

    /** Meta rejects empty parameters: clinic phone → owner's phone → generic. */
    private static function clinicPhone(int $clinicId, array $clinic): string
    {
        $phone = trim((string) ($clinic['phone'] ?? ''));
        if ($phone === '') {
            try {
                $stmt = Database::connection()->prepare('SELECT phone FROM users WHERE clinic_id = ? AND is_owner = 1 AND phone IS NOT NULL LIMIT 1');
                $stmt->execute([$clinicId]);
                $phone = trim((string) $stmt->fetchColumn());
            } catch (\Throwable $e) {
                $phone = '';
            }
        }

        return $phone !== '' ? $phone : 'the clinic reception';
    }

    /** @return array{ok: bool, code: string, message: string} */
    private static function result(bool $ok, string $code, string $message): array
    {
        return ['ok' => $ok, 'code' => $code, 'message' => $message];
    }
}
