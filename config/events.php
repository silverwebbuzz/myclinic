<?php

declare(strict_types=1);

use App\Core\RequestContext;
use App\Services\InvoiceService;

/** Event subscribers */
return [
    'visit.completed' => [
        static function (array $payload, int $eventId): void {
            $clinicId = (int) ($payload['clinic_id'] ?? RequestContext::clinicId() ?? 0);
            if ($clinicId > 0) {
                InvoiceService::createDraftFromVisit($clinicId, $payload);
            }
        },
    ],
    'invoice.paid' => [],
    'patient.created' => [],
    'appointment.booked' => [],
    'appointment.cancelled' => [],
];
