<?php

declare(strict_types=1);

namespace Tests\Corporate;

use PHPUnit\Framework\TestCase;

/**
 * Medical confidentiality firewall (requirements C-X1, design §4 C14).
 *
 * The employer side (company portal services, controllers and views) may
 * read fitness OUTCOMES, restrictions, validity and attendance, never
 * clinical data. This static test fails the build if company code:
 *   1. queries a clinical table (visits, prescriptions, vitals, charts,
 *      test reports (occ_case_reports), …);
 *   2. reads corp_fitness_certificates with * (which would pull the
 *      clinic-side visit link) or mentions visit_id at all.
 * Clinic-side code lives in Services/OccHealth and is not scanned.
 */
final class CorpFirewallGuardTest extends TestCase
{
    private const DIRS = [
        __DIR__ . '/../../app/Services/Corporate',
        __DIR__ . '/../../app/Controllers/Corporate',
        __DIR__ . '/../../views/corporate',
    ];

    private const CLINICAL_TABLES = [
        'visits', 'prescriptions', 'vitals', 'patients', 'patient_documents', 'patient_prescriptions', 'patient_lab_reports',
        'patient_immunizations', 'lab_orders', 'lab_order_items', 'invoices', 'invoice_items', 'diet_plans', 'follow_ups',
        'visit_symptoms', 'occ_doctor_credentials', 'occ_case_reports',
    ];

    public function testCompanyCodeNeverQueriesClinicalTables(): void
    {
        $pattern = '/\b(?:FROM|JOIN|UPDATE|INTO)\s+`?(' . implode('|', self::CLINICAL_TABLES) . ')`?\b/i';
        $scanned = 0;
        foreach ($this->files() as $file) {
            foreach ($this->literals($file) as $s) {
                $scanned++;
                $this->assertDoesNotMatchRegularExpression($pattern, $s,
                    'Firewall: company code queries a clinical table in ' . basename($file) . ': ' . preg_replace('/\s+/', ' ', $s));
            }
        }
        $this->assertGreaterThan(100, $scanned, 'Firewall scan found suspiciously few strings: did it break?');
    }

    public function testCertificatesAreReadColumnByColumn(): void
    {
        foreach ($this->files() as $file) {
            $code = (string) file_get_contents($file);
            $this->assertStringNotContainsString('visit_id', $code, 'Firewall: ' . basename($file) . ' mentions visit_id (clinic-side link).');
            foreach ($this->literals($file) as $s) {
                if (!str_contains($s, 'corp_fitness_certificates')) {
                    continue;
                }
                $this->assertDoesNotMatchRegularExpression('/SELECT\s+\*|\b[a-z_][a-z0-9_]*\.\*/i', $s,
                    'Firewall: ' . basename($file) . ' selects * from corp_fitness_certificates; list the allowed columns.');
            }
        }
    }

    /** @return list<string> every single- or double-quoted string in the file */
    private function literals(string $file): array
    {
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'|\"((?:[^\"\\\\]|\\\\.)*)\"/s", (string) file_get_contents($file), $m);

        return array_values(array_filter(array_merge($m[1], $m[2]), static fn (string $s): bool => $s !== ''));
    }

    /** @return list<string> */
    private function files(): array
    {
        $out = [];
        foreach (self::DIRS as $dir) {
            foreach (glob($dir . '/*.php') ?: [] as $f) {
                $out[] = $f;
            }
        }

        return $out;
    }
}
