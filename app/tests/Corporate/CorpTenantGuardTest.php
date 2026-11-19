<?php

declare(strict_types=1);

namespace Tests\Corporate;

use PHPUnit\Framework\TestCase;

/**
 * Static guard for company isolation (the corp_* twin of TenantScopeGuardTest).
 *
 * Scans every SQL string literal in the company-portal code that touches a
 * company-owned corp_* table and fails unless it is scoped by one of:
 *   company_id            explicit company scope
 *   a key that already identifies one company's row: id, user_id, jti,
 *   token_hash, public_ref, email (unique login), role_id + company scope.
 * A brand-new unscoped query ("SELECT * FROM corp_users") fails the build.
 * Deliberate platform-wide admin queries carry the SQL comment
 * cross-company: admin so they are explicit and greppable.
 * Master tables (corp_permissions, corp_industries, corp_roles system rows)
 * are not company-owned and are skipped.
 */
final class CorpTenantGuardTest extends TestCase
{
    private const DIRS = [
        __DIR__ . '/../../app/Services/Corporate',
        __DIR__ . '/../../app/Controllers/Corporate',
        __DIR__ . '/../../app/Middleware/CorpAuthMiddleware.php',
        __DIR__ . '/../../app/Controllers/CorporateAdminController.php',
    ];

    private const COMPANY_TABLES = [
        'corp_companies', 'corp_company_documents', 'corp_users', 'corp_user_roles', 'corp_invitations',
        'corp_password_resets', 'corp_sessions', 'corp_audit_log',
        // Phase 1A
        'corp_branches', 'corp_departments', 'corp_job_roles', 'corp_employments', 'corp_employment_events',
        'corp_employee_imports', 'corp_employee_import_rows',
        // Phase 1B
        'corp_programmes', 'corp_programme_items', 'corp_programme_assignments', 'corp_screening_cases', 'corp_case_events',
        // Phase 1C
        'corp_provider_links', 'corp_rate_items', 'corp_bookings', 'corp_fitness_certificates',
    ];

    private const SCOPE_MARKERS = [
        'company_id', 'cid', 'id = :id', 'user_id', 'jti', 'token_hash', 'public_ref', 'email = :email', 'INSERT INTO',
        'cross-company: admin',   // deliberate platform-level query (super admin screens only)
    ];

    public function testEveryCompanyTableQueryIsScoped(): void
    {
        $checked = 0;
        foreach ($this->files() as $file) {
            $code = (string) file_get_contents($file);
            // Single- and double-quoted string literals that look like SQL.
            preg_match_all("/'((?:SELECT|UPDATE|DELETE|INSERT)\b[^']*)'|\"((?:SELECT|UPDATE|DELETE|INSERT)\b[^\"]*)\"/s", $code, $m);
            foreach (array_merge($m[1], $m[2]) as $sql) {
                if ($sql === '') {
                    continue;
                }
                $tables = array_filter(self::COMPANY_TABLES, static fn (string $t): bool => preg_match('/\b' . $t . '\b/', $sql) === 1);
                if ($tables === []) {
                    continue;
                }
                $checked++;
                $scoped = false;
                foreach (self::SCOPE_MARKERS as $marker) {
                    if (str_contains($sql, $marker)) {
                        $scoped = true;
                        break;
                    }
                }
                $this->assertTrue($scoped, 'Unscoped company query in ' . basename($file) . ': ' . preg_replace('/\s+/', ' ', $sql));
            }
        }
        $this->assertGreaterThan(10, $checked, 'Guard found suspiciously few queries — did the scan break?');
    }

    /** @return list<string> */
    private function files(): array
    {
        $out = [];
        foreach (self::DIRS as $path) {
            if (is_file($path)) {
                $out[] = $path;
                continue;
            }
            foreach (glob($path . '/*.php') ?: [] as $f) {
                $out[] = $f;
            }
        }

        return $out;
    }
}
