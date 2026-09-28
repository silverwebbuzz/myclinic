<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\RequestContext;

/**
 * Append-only store audit trail (store_audit_log). Best-effort: an audit
 * failure must never break the action being audited.
 */
final class StoreAudit
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $before = null,
        ?array $after = null,
    ): void {
        [$actorType, $actorId] = self::actor();
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO store_audit_log
                    (actor_type, actor_id, action, entity_type, entity_id, before_json, after_json, ip, user_agent)
                 VALUES (:at, :aid, :act, :et, :eid, :b, :a, :ip, :ua)'
            );
            $stmt->execute([
                'at' => $actorType,
                'aid' => $actorId,
                'act' => $action,
                'et' => $entityType,
                'eid' => $entityId,
                'b' => $before === null ? null : json_encode(self::scrub($before), JSON_UNESCAPED_UNICODE),
                'a' => $after === null ? null : json_encode(self::scrub($after), JSON_UNESCAPED_UNICODE),
                'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log('[StoreAudit] ' . $e->getMessage());
        }
    }

    /** @return array{0: string, 1: ?int} */
    private static function actor(): array
    {
        $admin = RequestContext::superAdmin();
        if ($admin !== null) {
            return ['admin', (int) $admin['id']];
        }
        $user = RequestContext::vendorUser();
        if ($user !== null) {
            return ['vendor_user', (int) $user['id']];
        }

        return ['system', null];
    }

    /**
     * Never write secrets into the audit log.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function scrub(array $row): array
    {
        foreach (['password_hash', 'account_no_enc', 'pan_enc', 'password'] as $k) {
            if (array_key_exists($k, $row)) {
                $row[$k] = '[redacted]';
            }
        }

        return $row;
    }
}
