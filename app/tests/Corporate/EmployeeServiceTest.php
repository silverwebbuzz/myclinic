<?php

declare(strict_types=1);

namespace Tests\Corporate;

use App\Services\Corporate\EmployeeService;
use PHPUnit\Framework\TestCase;

/** The parts of EmployeeService that need no database. */
final class EmployeeServiceTest extends TestCase
{
    public function testAadhaarIsNeverAnIdTypeAnywhere(): void
    {
        foreach (EmployeeService::ID_TYPES as $country => $types) {
            foreach (array_merge(array_keys($types), array_values($types)) as $t) {
                $this->assertStringNotContainsStringIgnoringCase('aadhaar', $t, "ID type list for {$country}");
            }
        }
    }

    public function testIdTypesFollowTheBranchCountry(): void
    {
        $this->assertArrayHasKey('emirates_id', EmployeeService::idTypesFor('ae'));
        $this->assertArrayHasKey('pan', EmployeeService::idTypesFor('IN'));
        $this->assertSame(EmployeeService::ID_TYPES['*'], EmployeeService::idTypesFor('KE'));
        $this->assertSame('Emirates ID', EmployeeService::idTypeLabel('emirates_id'));
    }

    public function testBranchScope(): void
    {
        $this->assertTrue(EmployeeService::inScope(5, null));
        $this->assertTrue(EmployeeService::inScope(5, [3, 5]));
        $this->assertFalse(EmployeeService::inScope(4, [3, 5]));
        $this->assertFalse(EmployeeService::inScope(null, [3]));
        $this->assertFalse(EmployeeService::inScope(3, []));
    }

    public function testFilterSqlNeverRepeatsAPlaceholder(): void
    {
        [$where, $params] = EmployeeService::filterSql(7, [
            'q' => '98765', 'branch' => '2', 'department' => '3', 'job_role' => '4', 'status' => 'on_leave', 'type' => 'contractor', 'link' => 'pending',
        ], [2, 9]);
        preg_match_all('/(?<!:):([a-z_][a-z0-9_]*)/', 'e.company_id = :cid AND ' . $where, $m);
        $this->assertSame($m[1], array_values(array_unique($m[1])), 'EMULATE_PREPARES=false: a reused placeholder silently returns nothing');
        $this->assertEqualsCanonicalizing($m[1], array_keys($params));
        $this->assertStringContainsString('e.branch_id IN (2,9)', $where);
    }

    public function testEmptyScopeSeesNothingAndDefaultHidesLeavers(): void
    {
        [$where] = EmployeeService::filterSql(7, ['status' => 'current'], []);
        $this->assertStringContainsString('1 = 0', $where);
        $this->assertStringContainsString("e.status <> 'terminated'", $where);
    }

    public function testCsvCellsCannotRunFormulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", EmployeeService::csvSafe('=HYPERLINK("x")'));
        $this->assertSame("'@SUM(A1)", EmployeeService::csvSafe('@SUM(A1)'));
        $this->assertSame('+971501234567', EmployeeService::csvSafe('+971501234567'));
        $this->assertSame('Welder', EmployeeService::csvSafe('Welder'));
    }

    public function testDates(): void
    {
        $this->assertTrue(EmployeeService::isYmd('2024-02-29'));
        $this->assertFalse(EmployeeService::isYmd('2025-02-29'));
        $this->assertFalse(EmployeeService::isYmd('29/02/2024'));
    }
}
