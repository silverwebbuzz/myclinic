<?php

declare(strict_types=1);

namespace App\Support;

final class SpecialtyOptionsParser
{
    /** @param array<string, mixed> $post @return array<string, mixed> */
    public static function fromPost(string $specialty, array $post): array
    {
        $base = ['slot_duration' => (int) ($post['slot_duration'] ?? 15)];

        return match ($specialty) {
            'gp' => array_merge($base, [
                'icd10_enabled' => !empty($post['icd10_enabled']),
                'drug_db' => $post['drug_db'] ?? 'global',
            ]),
            'homeopathy' => array_merge($base, [
                'case_fields' => [
                    'mental_generals' => !empty($post['mental_generals']),
                    'physical_generals' => !empty($post['physical_generals']),
                    'peculiar_symptoms' => !empty($post['peculiar_symptoms']),
                    'modalities' => !empty($post['modalities']),
                    'miasmatic_analysis' => !empty($post['miasmatic_analysis']),
                ],
                'potency_system' => $post['potency_system'] ?? 'centesimal',
                'dietary_antidote_warnings' => !empty($post['dietary_antidote_warnings']),
            ]),
            'dental' => array_merge($base, [
                'tooth_numbering' => $post['tooth_numbering'] ?? 'FDI',
                'procedures' => array_filter(array_map('trim', explode(',', $post['procedures'] ?? ''))),
            ]),
            'derma' => array_merge($base, [
                'skin_score_enabled' => !empty($post['skin_score_enabled']),
                'photo_tracking' => !empty($post['photo_tracking']),
                'body_map' => !empty($post['body_map']),
            ]),
            'peds' => array_merge($base, [
                'growth_chart_region' => $post['growth_chart_region'] ?? 'global',
                'vaccine_schedule' => $post['vaccine_schedule'] ?? 'iap',
            ]),
            'physio' => array_merge($base, [
                'rom_joints' => !empty($post['rom_joints']),
                'pain_scale' => $post['pain_scale'] ?? 'nrs',
                'default_session_duration' => (int) ($post['default_session_duration'] ?? 45),
            ]),
            default => $base,
        };
    }
}
