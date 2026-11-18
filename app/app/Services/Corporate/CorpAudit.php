<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * Append-only activity log for the company portal (corp_audit_log).
 * Never throws: a failed audit write is logged to the PHP error log and the
 * user's action carries on. Never pass passwords, tokens or MFA secrets in
 * $details.
 */
final class CorpAudit
{
    /** Keys stripped from $details whatever the caller passes. */
    private const SECRET_KEYS = ['password', 'password_hash', 'token', 'token_hash', 'mfa_secret', 'mfa_secret_enc', 'code', 'recovery'];

    /**
     * @param 'corp_user'|'platform_admin'|'employee'|'system' $actorType
     * @param array<string, mixed>|null $details
     */
    public static function log(
        int $companyId,
        string $actorType,
        ?int $actorId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null,
    ): void {
        try {
            if ($details !== null) {
                foreach (self::SECRET_KEYS as $k) {
                    unset($details[$k]);
                }
            }
            $stmt = Database::connection()->prepare(
                'INSERT INTO corp_audit_log
                    (company_id, actor_type, actor_id, action, entity_type, entity_id, details, ip, user_agent, created_at)
                 VALUES (:company_id, :actor_type, :actor_id, :action, :entity_type, :entity_id, :details, :ip, :ua, :created_at)'
            );
            $stmt->execute([
                'company_id' => $companyId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => mb_substr($action, 0, 60),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                'ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
                'created_at' => LocalDate::nowUtc(),
            ]);
        } catch (\Throwable $e) {
            error_log('[CorpAudit] ' . $action . ': ' . $e->getMessage());
        }
    }

    /** Shortcut for an action by the signed-in company user. */
    public static function byUser(string $action, ?string $entityType = null, ?int $entityId = null, ?array $details = null): void
    {
        $cid = CorpContext::companyId();
        if ($cid === null) {
            return;
        }
        self::log($cid, 'corp_user', CorpContext::userId(), $action, $entityType, $entityId, $details);
    }
}
