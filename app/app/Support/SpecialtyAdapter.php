<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\RequestContext;

final class SpecialtyAdapter
{
    public static function current(): string
    {
        $clinic = RequestContext::clinic();

        return (string) ($clinic['specialty'] ?? 'gp');
    }

    public static function prescriptionMode(): string
    {
        return SpecialtyCatalog::prescriptionMode(self::current());
    }

    /** @return list<array<string, mixed>> */
    public static function vitalsFields(): array
    {
        $standard = [
            ['key' => 'bp_systolic', 'label' => 'BP systolic', 'unit' => 'mmHg', 'type' => 'number'],
            ['key' => 'bp_diastolic', 'label' => 'BP diastolic', 'unit' => 'mmHg', 'type' => 'number'],
            ['key' => 'blood_sugar', 'label' => 'Blood sugar', 'unit' => 'mg/dL', 'type' => 'number'],
            ['key' => 'sugar_type', 'label' => 'Sugar type', 'type' => 'select', 'options' => ['fasting', 'pp', 'random']],
            ['key' => 'weight_kg', 'label' => 'Weight', 'unit' => 'kg', 'type' => 'number'],
            ['key' => 'height_cm', 'label' => 'Height', 'unit' => 'cm', 'type' => 'number'],
            ['key' => 'temperature', 'label' => 'Temperature', 'unit' => '°F', 'type' => 'number'],
            ['key' => 'spo2', 'label' => 'SpO₂', 'unit' => '%', 'type' => 'number'],
            ['key' => 'pulse_rate', 'label' => 'Pulse', 'unit' => 'bpm', 'type' => 'number'],
        ];

        $extras = match (self::current()) {
            'gp' => [
                ['key' => 'extra.hba1c', 'label' => 'HbA1c', 'unit' => '%', 'type' => 'number', 'extra' => true],
            ],
            'homeopathy' => [
                ['key' => 'tsh', 'label' => 'TSH', 'unit' => 'mIU/L', 'type' => 'number'],
                ['key' => 't3', 'label' => 'T3', 'type' => 'number'],
                ['key' => 't4', 'label' => 'T4', 'type' => 'number'],
                ['key' => 'skin_score', 'label' => 'Skin score', 'type' => 'number'],
            ],
            'derma' => [
                ['key' => 'extra.bsa_percent', 'label' => 'BSA affected', 'unit' => '%', 'type' => 'number', 'extra' => true],
            ],
            'peds' => [
                ['key' => 'extra.head_circ_cm', 'label' => 'Head circumference', 'unit' => 'cm', 'type' => 'number', 'extra' => true],
                ['key' => 'extra.percentile', 'label' => 'Growth percentile', 'type' => 'number', 'extra' => true],
            ],
            'physio' => [
                ['key' => 'extra.pain_score', 'label' => 'Pain score', 'unit' => '0-10', 'type' => 'number', 'extra' => true],
                ['key' => 'extra.rom_notes', 'label' => 'ROM notes', 'type' => 'text', 'extra' => true],
            ],
            default => [],
        };

        return array_merge($standard, $extras);
    }

    public static function caseTakingPartial(): string
    {
        return match (self::current()) {
            'homeopathy' => 'case_homeopathy',
            'dental' => 'case_dental',
            'derma' => 'case_derma',
            'physio' => 'case_physio',
            default => 'case_gp',
        };
    }

    /** @return array<string, array{min?: float, max?: float, label?: string}> */
    public static function normalRanges(): array
    {
        return [
            'bp_systolic' => ['min' => 90, 'max' => 140, 'label' => 'Systolic BP'],
            'bp_diastolic' => ['min' => 60, 'max' => 90, 'label' => 'Diastolic BP'],
            'blood_sugar' => ['min' => 70, 'max' => 140, 'label' => 'Blood sugar (fasting ref.)'],
            'spo2' => ['min' => 95, 'max' => 100, 'label' => 'SpO₂'],
            'pulse_rate' => ['min' => 60, 'max' => 100, 'label' => 'Pulse'],
            'temperature' => ['min' => 97, 'max' => 99.5, 'label' => 'Temperature °F'],
        ];
    }

    public static function usesHomeopathicRx(): bool
    {
        $mode = self::prescriptionMode();

        return $mode === 'homeopathic' || ($mode === 'both' && self::current() === 'homeopathy');
    }
}
