<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class CrmLeadService
{
    public const STATUSES = ['new', 'contacted', 'follow_up', 'converted', 'lost'];

    /** @return list<array<string, mixed>> */
    public static function list(int $clinicId, ?string $status = null): array
    {
        $q = QueryBuilder::table('crm_leads')->forClinic($clinicId)->orderBy('created_at', 'DESC');
        if ($status !== null && $status !== '') {
            $q->where('status', '=', $status);
        }

        return $q->limit(200)->get();
    }

    /** @return array<string, int> */
    public static function kanbanCounts(int $clinicId): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach (QueryBuilder::table('crm_leads')->forClinic($clinicId)->get() as $row) {
            $st = $row['status'] ?? 'new';
            if (isset($counts[$st])) {
                $counts[$st]++;
            }
        }

        return $counts;
    }

    public static function find(int $clinicId, int $id): ?array
    {
        return QueryBuilder::table('crm_leads')->forClinic($clinicId)->where('id', '=', $id)->first() ?: null;
    }

    public static function save(int $clinicId, ?int $id, array $data): int
    {
        $payload = [
            'name' => $data['name'] ?? 'Lead',
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'inquiry_about' => $data['inquiry_about'] ?? null,
            'source' => $data['source'] ?? 'walk_in',
            'status' => $data['status'] ?? 'new',
            'follow_up_date' => $data['follow_up_date'] ?: null,
            'notes' => $data['notes'] ?? null,
            'assigned_to' => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
        ];

        if ($id !== null) {
            QueryBuilder::table('crm_leads')->forClinic($clinicId)->where('id', '=', $id)->update($payload);

            return $id;
        }

        $payload['clinic_id'] = $clinicId;

        return QueryBuilder::table('crm_leads')->insert($payload);
    }

    public static function convertToPatient(int $clinicId, int $leadId): array
    {
        $lead = self::find($clinicId, $leadId);
        if ($lead === null) {
            throw new \RuntimeException('Lead not found');
        }
        if (!empty($lead['converted_patient_id'])) {
            $patient = PatientService::find($clinicId, (int) $lead['converted_patient_id']);

            return $patient ?? [];
        }

        $phone = PatientService::normalizePhone((string) ($lead['phone'] ?? ''));
        $existing = $phone !== '' ? PatientService::findByPhone($clinicId, $phone) : null;
        if ($existing !== null) {
            $patient = $existing;
        } else {
            $patient = PatientService::create($clinicId, [
                'name' => $lead['name'],
                'phone' => $lead['phone'] ?? '',
                'source' => 'online',
            ]);
        }

        QueryBuilder::table('crm_leads')
            ->forClinic($clinicId)
            ->where('id', '=', $leadId)
            ->update([
                'status' => 'converted',
                'converted_patient_id' => (int) $patient['id'],
            ]);

        return $patient;
    }

    /** @return array{labels: list<string>, values: list<int>} */
    public static function sourceConversion(int $clinicId): array
    {
        $labels = [];
        $values = [];
        $rows = QueryBuilder::table('crm_leads')->forClinic($clinicId)->get();
        $bySource = [];
        foreach ($rows as $row) {
            $src = $row['source'] ?? 'other';
            $bySource[$src] = ($bySource[$src] ?? 0) + 1;
        }
        foreach ($bySource as $src => $cnt) {
            $labels[] = $src;
            $values[] = $cnt;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return int Number of reminders queued */
    public static function queueFollowUpReminders(): int
    {
        $today = date('Y-m-d');
        $pdo = \App\Core\Database::connection();
        $stmt = $pdo->prepare(
            "SELECT l.*, t.name AS clinic_name, u.phone AS staff_phone
             FROM crm_leads l
             INNER JOIN tenants t ON t.id = l.clinic_id
             LEFT JOIN users u ON u.id = l.assigned_to
             WHERE l.follow_up_date = ? AND l.status IN ('new','contacted','follow_up')",
        );
        $stmt->execute([$today]);
        $leads = $stmt->fetchAll() ?: [];

        $count = 0;
        foreach ($leads as $lead) {
            $phone = $lead['staff_phone'] ?? null;
            if ($phone === null || $phone === '') {
                $config = OnboardingService::specialtyConfig((int) $lead['clinic_id']) ?? [];
                $phone = $config['whatsapp_number'] ?? null;
            }
            if ($phone === null || $phone === '') {
                continue;
            }
            NotificationService::queueWhatsApp(
                (int) $lead['clinic_id'],
                null,
                (string) $phone,
                'follow_up_reminder',
                [
                    'patient_name' => 'CRM: ' . ($lead['name'] ?? 'Lead'),
                    'clinic_name' => $lead['clinic_name'] ?? '',
                ],
                date('Y-m-d') . ' 09:00:00',
            );
            $count++;
        }

        return $count;
    }
}
