<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * MessagingConsent — the recipient-driven opt-out gate.
 *
 * WhatsApp/Meta's Acceptable Use policy (and India's SMS DLT rules) require an
 * easy, honoured opt-out. This service is the single authority on whether a
 * number has opted out, backed by the messaging_optout ledger.
 *
 *   - isOptedOut($phone, $channel) — checked by NotificationProcessor BEFORE
 *     every business-initiated WhatsApp/SMS send.
 *   - recordStop($from, $channel, $keyword) — called by the webhook when an
 *     inbound STOP/UNSUBSCRIBE arrives.
 *   - isStopKeyword($text) — recognises the opt-out words.
 *
 * Matching uses the last-10-digit tail (canonical India match) so +91 prefix
 * variance never lets a blocked number slip through. Fails OPEN only on schema
 * errors (pre-migration) — never silently swallow a real opt-out.
 */
final class MessagingConsent
{
    /** Words that, sent inbound, mean "stop messaging me". */
    private const STOP_WORDS = ['stop', 'unsubscribe', 'unsub', 'cancel', 'end', 'quit', 'optout', 'opt-out', 'opt out'];

    /** Is this number opted out of the given channel ('whatsapp'|'sms')? */
    public static function isOptedOut(string $phone, string $channel): bool
    {
        $tail = self::tail($phone);
        if ($tail === '') {
            return false;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT 1 FROM messaging_optout
                  WHERE phone_tail = :t AND channel IN ('all', :c)
                  LIMIT 1"
            );
            $stmt->execute([':t' => $tail, ':c' => $channel]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            // Table missing pre-migration — don't block sends on infra gaps.
            return false;
        }
    }

    /**
     * Record a positive opt-in. Idempotent — keeps the FIRST opt-in timestamp
     * (that's the auditable "when consent was given" moment). Called at OTP
     * verification and at booking, where the person proves control of the number
     * and chooses to use the service.
     */
    public static function recordOptIn(
        string $phone,
        string $source,
        ?int $identityId = null,
        ?string $ip = null
    ): bool {
        $tail = self::tail($phone);
        if ($tail === '') {
            return false;
        }
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO messaging_consent (phone_tail, source, patient_identity_id, raw_phone, ip)
                 VALUES (:t, :s, :iid, :r, :ip)
                 ON DUPLICATE KEY UPDATE created_at = created_at'
            );
            return $stmt->execute([
                ':t' => $tail,
                ':s' => mb_substr($source, 0, 32),
                ':iid' => $identityId,
                ':r' => mb_substr($phone, 0, 32),
                ':ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Has this number a recorded opt-in? (For audit/appeal evidence.) */
    public static function hasOptedIn(string $phone): bool
    {
        $tail = self::tail($phone);
        if ($tail === '') {
            return false;
        }
        try {
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM messaging_consent WHERE phone_tail = :t LIMIT 1'
            );
            $stmt->execute([':t' => $tail]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Does this inbound text mean the sender wants to opt out? */
    public static function isStopKeyword(string $text): bool
    {
        $t = strtolower(trim($text));
        // Match the whole message being a stop word (avoid false-positive on
        // "please don't stop my reminders"). Exact match keeps it safe + simple.
        return in_array($t, self::STOP_WORDS, true);
    }

    /**
     * Record an opt-out. Idempotent (unique on phone_tail+channel).
     * $channel 'all' blocks both WhatsApp + SMS.
     */
    public static function recordStop(
        string $from,
        string $channel = 'all',
        ?string $keyword = null,
        string $source = 'inbound_stop'
    ): bool {
        $tail = self::tail($from);
        if ($tail === '') {
            return false;
        }
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO messaging_optout (phone_tail, channel, source, keyword, raw_from)
                 VALUES (:t, :c, :s, :k, :r)
                 ON DUPLICATE KEY UPDATE created_at = created_at'
            );
            return $stmt->execute([
                ':t' => $tail,
                ':c' => in_array($channel, ['all', 'whatsapp', 'sms'], true) ? $channel : 'all',
                ':s' => $source,
                ':k' => $keyword !== null ? mb_substr($keyword, 0, 64) : null,
                ':r' => mb_substr($from, 0, 32),
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Last 10 digits — canonical match, tolerant of +91 / spaces. */
    private static function tail(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        return strlen($digits) >= 10 ? substr($digits, -10) : '';
    }
}
