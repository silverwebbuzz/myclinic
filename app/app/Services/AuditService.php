<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;

final class AuditService
{
    public static function log(Request $request, string $action, ?string $table = null, ?int $recordId = null): void
    {
        if (!Database::ping()) {
            return;
        }

        $sql = 'INSERT INTO audit_log (clinic_id, user_id, table_name, record_id, action, ip_address, user_agent)
                VALUES (:clinic_id, :user_id, :table_name, :record_id, :action, :ip, :ua)';
        $stmt = Database::connection()->prepare($sql);
        $user = RequestContext::user();
        $stmt->execute([
            'clinic_id' => RequestContext::clinicId(),
            'user_id' => $user['id'] ?? null,
            'table_name' => $table ?? 'users',
            'record_id' => $recordId,
            'action' => $action,
            'ip' => $request->ip(),
            'ua' => substr($request->server['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }
}
