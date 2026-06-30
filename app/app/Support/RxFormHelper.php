<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Infer medicine form (tablet / syrup / injection …) and expose the right
 * frequency presets for the prescription row UI.
 */
final class RxFormHelper
{
    /** @var list<string> */
    public const FORMS = [
        'tablet', 'capsule', 'syrup', 'injection', 'cream', 'drops', 'inhaler', 'patch', 'other',
    ];

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function presetsByForm(): array
    {
        $tablet = [
            ['value' => '1-0-0', 'label' => '1-0-0 (morning)'],
            ['value' => '0-0-1', 'label' => '0-0-1 (night)'],
            ['value' => '1-0-1', 'label' => '1-0-1 (BD)'],
            ['value' => '1-1-1', 'label' => '1-1-1 (TDS)'],
            ['value' => '1-1-1-1', 'label' => '1-1-1-1 (QID)'],
            ['value' => '0-1-0', 'label' => '0-1-0 (afternoon)'],
            ['value' => 'SOS', 'label' => 'SOS'],
        ];

        return [
            'tablet' => $tablet,
            'capsule' => $tablet,
            'syrup' => [
                ['value' => '5 ml BD', 'label' => '5 ml BD'],
                ['value' => '5 ml TDS', 'label' => '5 ml TDS'],
                ['value' => '10 ml BD', 'label' => '10 ml BD'],
                ['value' => '10 ml TDS', 'label' => '10 ml TDS'],
                ['value' => '1 tsp BD', 'label' => '1 tsp BD'],
                ['value' => '1 tsp TDS', 'label' => '1 tsp TDS'],
                ['value' => 'BD', 'label' => 'BD'],
                ['value' => 'TDS', 'label' => 'TDS'],
                ['value' => 'SOS', 'label' => 'SOS'],
                ['value' => 'HS', 'label' => 'HS (bedtime)'],
            ],
            'injection' => [
                ['value' => 'OD', 'label' => 'OD (once daily)'],
                ['value' => 'BD', 'label' => 'BD'],
                ['value' => 'Stat', 'label' => 'Stat (once now)'],
                ['value' => 'SOS', 'label' => 'SOS'],
                ['value' => 'Weekly', 'label' => 'Weekly'],
            ],
            'drops' => [
                ['value' => '1 drop BD', 'label' => '1 drop BD'],
                ['value' => '1 drop TDS', 'label' => '1 drop TDS'],
                ['value' => '2 drops TDS', 'label' => '2 drops TDS'],
                ['value' => 'BD', 'label' => 'BD'],
                ['value' => 'TDS', 'label' => 'TDS'],
                ['value' => 'SOS', 'label' => 'SOS'],
            ],
            'cream' => [
                ['value' => 'BD', 'label' => 'BD (apply twice)'],
                ['value' => 'TDS', 'label' => 'TDS'],
                ['value' => 'HS', 'label' => 'HS (at bedtime)'],
                ['value' => 'SOS', 'label' => 'SOS'],
            ],
            'inhaler' => [
                ['value' => '1 puff BD', 'label' => '1 puff BD'],
                ['value' => '2 puffs BD', 'label' => '2 puffs BD'],
                ['value' => '1 puff SOS', 'label' => '1 puff SOS'],
                ['value' => '2 puffs SOS', 'label' => '2 puffs SOS'],
                ['value' => 'BD', 'label' => 'BD'],
                ['value' => 'SOS', 'label' => 'SOS'],
            ],
            'patch' => [
                ['value' => 'OD', 'label' => 'OD'],
                ['value' => 'Weekly', 'label' => 'Weekly'],
                ['value' => 'SOS', 'label' => 'SOS'],
            ],
            'other' => [
                ['value' => 'BD', 'label' => 'BD'],
                ['value' => 'TDS', 'label' => 'TDS'],
                ['value' => 'OD', 'label' => 'OD'],
                ['value' => 'SOS', 'label' => 'SOS'],
            ],
        ];
    }

  /**
     * @return list<array{value: string, label: string}>
     */
    public static function frequencyPresets(string $form): array
    {
        $map = self::presetsByForm();
        $group = self::normalizeForm($form);

        return $map[$group] ?? $map['tablet'];
    }

    public static function inferForm(?string $catalogForm, ?string $doseUnit, string $drugName): string
    {
        $catalogForm = strtolower(trim((string) $catalogForm));
        if ($catalogForm !== '' && in_array($catalogForm, self::FORMS, true)) {
            return $catalogForm;
        }

        $unit = strtolower(trim((string) $doseUnit));
        $unitMap = [
            'tablet' => 'tablet',
            'capsule' => 'capsule',
            'ml' => 'syrup',
            'drops' => 'drops',
            'puff' => 'inhaler',
            'sachet' => 'syrup',
            'unit' => 'injection',
        ];
        if ($unit !== '' && isset($unitMap[$unit])) {
            return $unitMap[$unit];
        }

        $n = mb_strtolower(trim($drugName));
        if ($n === '') {
            return 'tablet';
        }

        return match (true) {
            str_contains($n, 'syrup'), str_contains($n, 'suspension'), str_contains($n, ' solution') => 'syrup',
            str_contains($n, 'injection'), preg_match('/\binj\b/', $n) === 1, str_contains($n, 'vial') => 'injection',
            str_contains($n, 'cream'), str_contains($n, 'ointment'), str_contains($n, ' gel'), str_contains($n, 'lotion') => 'cream',
            str_contains($n, 'drop') => 'drops',
            str_contains($n, 'inhaler'), str_contains($n, 'rotacap'), str_contains($n, 'respule') => 'inhaler',
            str_contains($n, 'patch') => 'patch',
            str_contains($n, 'capsule'), preg_match('/\bcap\b/', $n) === 1 => 'capsule',
            str_contains($n, 'suppository'), str_contains($n, 'supp') => 'other',
            str_contains($n, 'tablet'), preg_match('/\btab\b/', $n) === 1 => 'tablet',
            default => 'tablet',
        };
    }

    /** @return array{dose_unit: string, dose_amount: float|int|null, frequency_preset: string} */
    public static function defaultLineDefaults(string $form): array
    {
        $form = self::normalizeForm($form);

        return match ($form) {
            'syrup' => ['dose_unit' => 'ml', 'dose_amount' => 5, 'frequency_preset' => '5 ml TDS'],
            'injection' => ['dose_unit' => 'unit', 'dose_amount' => 1, 'frequency_preset' => 'OD'],
            'drops' => ['dose_unit' => 'drops', 'dose_amount' => 1, 'frequency_preset' => '1 drop TDS'],
            'cream' => ['dose_unit' => '', 'dose_amount' => null, 'frequency_preset' => 'BD'],
            'inhaler' => ['dose_unit' => 'puff', 'dose_amount' => 1, 'frequency_preset' => '1 puff BD'],
            'patch' => ['dose_unit' => '', 'dose_amount' => null, 'frequency_preset' => 'OD'],
            default => ['dose_unit' => 'tablet', 'dose_amount' => 1, 'frequency_preset' => '1-0-1'],
        };
    }

    private static function normalizeForm(string $form): string
    {
        $form = strtolower(trim($form));

        return match ($form) {
            'capsule' => 'capsule',
            'syrup', 'suspension' => 'syrup',
            'injection' => 'injection',
            'cream', 'ointment' => 'cream',
            'drops' => 'drops',
            'inhaler' => 'inhaler',
            'patch' => 'patch',
            'other' => 'other',
            default => 'tablet',
        };
    }

    /**
     * Best-effort reverse of legacyFrequency — used when reloading rows that
     * only have the ENUM column populated (pre-migration data).
     */
    public static function presetFromLegacy(string $legacy, string $form): string
    {
        $form = self::normalizeForm($form);
        $legacy = strtoupper(trim($legacy));

        return match ($legacy) {
            'TDS' => match ($form) {
                'syrup' => '5 ml TDS',
                'tablet', 'capsule' => '1-1-1',
                default => 'TDS',
            },
            'BD' => match ($form) {
                'syrup' => '5 ml BD',
                'cream' => 'BD',
                'inhaler' => '1 puff BD',
                'tablet', 'capsule' => '1-0-1',
                default => 'BD',
            },
            'OD' => match ($form) {
                'syrup' => '5 ml BD',
                'injection', 'patch' => 'OD',
                'tablet', 'capsule' => '1-0-0',
                default => 'OD',
            },
            'QID' => '1-1-1-1',
            'SOS' => 'SOS',
            'PRN' => 'SOS',
            'WEEKLY' => 'Weekly',
            'MONTHLY' => 'Monthly',
            default => self::defaultLineDefaults($form)['frequency_preset'],
        };
    }

    /**
     * Map client / preset notation to the legacy prescriptions.frequency ENUM.
     */
    public static function legacyFrequency(?string $frequency, ?string $preset = null): string
    {
        $allowed = ['OD', 'BD', 'TDS', 'QID', 'weekly', 'monthly', 'SOS', 'PRN'];
        $freq = trim((string) $frequency);
        if ($freq !== '' && in_array($freq, $allowed, true)) {
            return $freq;
        }

        $p = strtoupper(trim((string) $preset));

        return match (true) {
            $p === '1-1-1-1', str_contains($p, 'QID') => 'QID',
            $p === '1-1-1', str_contains($p, 'TDS') => 'TDS',
            $p === '1-0-1', str_contains($p, ' BD') => 'BD',
            $p === '1-0-0', $p === '0-0-1', $p === '0-1-0', $p === 'OD', str_contains($p, ' OD') => 'OD',
            $p === 'SOS', str_contains($p, 'SOS') => 'SOS',
            $p === 'PRN' => 'PRN',
            $p === 'WEEKLY', str_contains($p, 'WEEKLY') => 'weekly',
            $p === 'MONTHLY', str_contains($p, 'MONTHLY') => 'monthly',
            default => 'BD',
        };
    }

    /**
     * Doses per day from a frequency preset or legacy enum (for qty-to-purchase math).
     * Returns null when not calculable (SOS / PRN / unknown).
     */
    public static function dosesPerDay(?string $preset, ?string $legacyFreq = null): ?float
    {
        $preset = trim((string) $preset);
        if (preg_match('/^(\d+(?:\.\d+)?)(?:-(\d+(?:\.\d+)?))(?:-(\d+(?:\.\d+)?))?(?:-(\d+(?:\.\d+)?))?$/', $preset, $m)) {
            $sum = 0.0;
            for ($i = 1, $n = count($m); $i < $n; $i++) {
                if ($m[$i] !== '' && $m[$i] !== null) {
                    $sum += (float) $m[$i];
                }
            }
            if ($sum > 0) {
                return $sum;
            }
        }

        $p = strtoupper($preset);
        $legacy = strtoupper(trim((string) $legacyFreq));

        return match (true) {
            str_contains($p, 'SOS'), str_contains($p, 'PRN'),
            $legacy === 'SOS', $legacy === 'PRN' => null,
            str_contains($p, 'WEEKLY'), $legacy === 'WEEKLY' => 1 / 7,
            str_contains($p, 'MONTHLY'), $legacy === 'MONTHLY' => 1 / 30,
            $p === '1-1-1-1', str_contains($p, 'QID'), $legacy === 'QID' => 4.0,
            $p === '1-1-1', str_contains($p, 'TDS'), $legacy === 'TDS' => 3.0,
            $p === '1-0-1', str_contains($p, ' BD'), $legacy === 'BD' => 2.0,
            $p === '1-0-0', $p === '0-0-1', $p === '0-1-0',
            str_contains($p, ' OD'), $legacy === 'OD' => 1.0,
            str_contains($p, 'TDS') => 3.0,
            str_contains($p, ' BD') => 2.0,
            default => null,
        };
    }
}
