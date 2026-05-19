<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\Database;
use App\Core\RequestContext;

final class EventBus
{
    /** @param array<string, mixed> $payload */
    public static function fire(string $eventName, array $payload, ?string $entityType = null, ?int $entityId = null): int
    {
        $clinicId = RequestContext::clinicId();
        $user = RequestContext::user();

        $sql = 'INSERT INTO events (clinic_id, event_name, entity_type, entity_id, payload, fired_by)
                VALUES (:clinic_id, :event_name, :entity_type, :entity_id, :payload, :fired_by)';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'clinic_id' => $clinicId,
            'event_name' => $eventName,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'fired_by' => $user['id'] ?? null,
        ]);

        $eventId = (int) Database::connection()->lastInsertId();
        self::dispatchSubscribers($eventName, $payload, $eventId);

        return $eventId;
    }

    /** @param array<string, mixed> $payload */
    private static function dispatchSubscribers(string $eventName, array $payload, int $eventId): void
    {
        $subscribers = require Application::basePath() . '/config/events.php';
        $handlers = $subscribers[$eventName] ?? [];

        foreach ($handlers as $handler) {
            if (is_callable($handler)) {
                $handler($payload, $eventId);
            }
        }
    }
}
