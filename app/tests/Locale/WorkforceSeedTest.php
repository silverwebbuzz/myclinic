<?php

declare(strict_types=1);

namespace Tests\Locale;

use PHPUnit\Framework\TestCase;

/**
 * Static checks on the Phase 0 SQL (no DB needed), so mistakes are caught
 * before the files are imported on the server.
 */
final class WorkforceSeedTest extends TestCase
{
    private const SCHEMA = __DIR__ . '/../../database/patches/2026_11_18_workforce_phase0.sql';
    private const SEED = __DIR__ . '/../../database/patches/2026_11_18_workforce_phase0_seed.sql';

    private function seed(): string
    {
        return (string) file_get_contents(self::SEED);
    }

    /** Rows of one INSERT ... VALUES block, as raw "(...)" strings. */
    private function rows(string $table): array
    {
        $sql = $this->seed();
        $this->assertSame(1, preg_match('/INSERT IGNORE INTO `' . $table . '`[^;]*?VALUES(.*?);/s', $sql, $m), "INSERT for $table not found");
        preg_match_all("/^\s*\((.*)\),?\s*$/m", $m[1], $rows);

        return $rows[1];
    }

    public function testEveryCountryCurrencyExists(): void
    {
        $currencies = [];
        foreach ($this->rows('geo_currencies') as $r) {
            preg_match("/^'([A-Z]{3})'/", $r, $m);
            $currencies[] = $m[1];
        }
        $this->assertCount(14, $currencies);

        $countries = $this->rows('geo_countries');
        $this->assertCount(16, $countries);
        foreach ($countries as $r) {
            $this->assertSame(1, preg_match("/^'([A-Z]{2})',.*?'(\d+)',\s*'([\d,]+)',\s*'([A-Z]{3})',\s*'([A-Za-z_\/]+)'/", $r, $m), "Bad row: $r");
            $this->assertContains($m[4], $currencies, "Country {$m[1]} uses unknown currency {$m[4]}");
            $this->assertContains($m[5], \DateTimeZone::listIdentifiers(), "Country {$m[1]} has bad timezone {$m[5]}");
        }
    }

    public function testOnlyIndiaAndUaeAreActive(): void
    {
        $active = [];
        foreach ($this->rows('geo_countries') as $r) {
            // second-last value is is_active
            preg_match('/,\s*([01]),\s*\d+$/', $r, $m);
            if (($m[1] ?? '0') === '1') {
                $active[] = substr($r, 1, 2);
            }
        }
        $this->assertSame(['IN', 'AE'], $active);
    }

    public function testRolePermissionsReferenceRealPermissions(): void
    {
        $perms = [];
        $clinical = [];
        foreach ($this->rows('corp_permissions') as $r) {
            preg_match("/^'([a-z_.]+)',\s*'[a-z_]+',\s*'.*?',\s*([01]),/", $r, $m);
            $perms[] = $m[1];
            if ($m[2] === '1') {
                $clinical[] = $m[1];
            }
        }
        $this->assertCount(24, $perms);
        $this->assertSame(['medical.view_clinical'], $clinical);

        preg_match_all("/r\.code = '([a-z_]+)' AND p\.code IN \((.*?)\);/s", $this->seed(), $blocks, PREG_SET_ORDER);
        $this->assertCount(6, $blocks, 'Expected explicit permission lists for 6 roles (company_admin uses the non-clinical join)');
        foreach ($blocks as [$all, $role, $list]) {
            preg_match_all("/'([a-z_.]+)'/", $list, $codes);
            foreach ($codes[1] as $code) {
                $this->assertContains($code, $perms, "Role $role grants unknown permission $code");
                if (in_array($code, $clinical, true)) {
                    $this->assertSame('oh_physician', $role, "Non-clinical role $role must not get $code");
                }
            }
        }
        $this->assertStringContainsString("JOIN `corp_permissions` p ON p.is_clinical = 0\nWHERE r.company_id = 0 AND r.code = 'company_admin'", $this->seed());
    }

    public function testCorpTimestampsAreNotServerLocal(): void
    {
        $schema = (string) file_get_contents(self::SCHEMA);
        preg_match_all('/CREATE TABLE IF NOT EXISTS `corp_[a-z_]+` \((.*?)\n\) ENGINE/s', $schema, $tables);
        $this->assertGreaterThanOrEqual(12, count($tables[1]));
        foreach ($tables[1] as $body) {
            $this->assertStringNotContainsString('CURRENT_TIMESTAMP', $body, 'corp_* DATETIMEs are UTC, written by PHP');
        }
        $this->assertStringNotContainsString('NOW()', $this->seed());
    }
}
