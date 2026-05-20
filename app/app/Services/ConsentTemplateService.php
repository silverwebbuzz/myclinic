<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class ConsentTemplateService
{
  public const MERGE_FIELDS = ['{{patient_name}}', '{{uhid}}', '{{clinic_name}}', '{{date}}', '{{procedure}}', '{{doctor_name}}'];

    /** @return list<array<string, mixed>> */
    public static function list(int $clinicId): array
    {
        return QueryBuilder::table('consent_templates')
            ->forClinic($clinicId)
            ->where('is_active', '=', 1)
            ->get();
    }

    public static function find(int $clinicId, int $id): ?array
    {
        $row = QueryBuilder::table('consent_templates')
            ->forClinic($clinicId)
            ->where('id', '=', $id)
            ->first();

        return $row ?: null;
    }

    public static function save(int $clinicId, ?int $id, array $data): int
    {
        $payload = [
            'name' => $data['name'] ?? 'Template',
            'form_type' => $data['form_type'] ?? 'procedure',
            'content' => $data['content'] ?? '',
            'merge_fields' => json_encode(self::MERGE_FIELDS),
            'is_active' => 1,
        ];

        if ($id !== null) {
            QueryBuilder::table('consent_templates')
                ->forClinic($clinicId)
                ->where('id', '=', $id)
                ->update($payload);

            return $id;
        }

        $payload['clinic_id'] = $clinicId;

        return QueryBuilder::table('consent_templates')->insert($payload);
    }

    public static function renderContent(string $content, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }

        return $content;
    }
}
