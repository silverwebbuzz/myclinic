<?php

declare(strict_types=1);

namespace Tests\Corporate;

use App\Services\Corporate\ScreeningPlanner as P;
use PHPUnit\Framework\TestCase;

/** Due-date rules (design §3.1 S5–S10). Pure: no database. */
final class ScreeningPlannerTest extends TestCase
{
    private const SPIRO = 1;
    private const VISION = 2;

    /** Welding programme, applied to job role 7 on 2026-11-20, first round 60 days. */
    private function programme(int $months = 12, int $id = 100, string $on = '2026-11-20', array $assign = [['type' => 'job_role', 'target' => 7]]): array
    {
        return [
            'id' => $id, 'first_round_days' => 60,
            'assignments' => array_map(static fn (array $a): array => $a + ['on' => $on], $assign),
            'items' => [
                ['id' => $id + 11, 'exam' => self::SPIRO, 'category' => 'pre_employment', 'months' => null],
                ['id' => $id + 12, 'exam' => self::SPIRO, 'category' => 'periodic', 'months' => $months],
                ['id' => $id + 13, 'exam' => self::SPIRO, 'category' => 'exit', 'months' => null],
            ],
        ];
    }

    private function emp(array $over = []): array
    {
        return $over + ['id' => 5, 'status' => 'active', 'joined_on' => '2018-01-01', 'left_on' => null, 'created_on' => '2026-11-01',
            'job_role_id' => 7, 'department_id' => 3, 'branch_id' => 2];
    }

    private function case(array $over): array
    {
        return $over + ['id' => 900, 'exam' => self::SPIRO, 'category' => 'periodic', 'status' => 'not_scheduled', 'due_on' => '2027-01-19',
            'completed_on' => null, 'programme_item_id' => 112];
    }

    public function testExistingStaffGetFirstRoundWindowNoPreEmployment(): void
    {
        $a = P::plan($this->emp(), [$this->programme()], []);
        $this->assertSame([['action' => 'create', 'exam' => self::SPIRO, 'category' => 'periodic', 'due_on' => '2027-01-19', 'item_id' => 112]], $a);
    }

    public function testNewJoinerGetsPreEmploymentThenPeriodicAfterIt(): void
    {
        $a = P::plan($this->emp(['joined_on' => '2026-12-01']), [$this->programme()], []);
        $this->assertSame('pre_employment', $a[0]['category']);
        $this->assertSame('2026-12-01', $a[0]['due_on']);
        $this->assertSame('2027-12-01', $a[1]['due_on']);
        $this->assertCount(2, $a);
    }

    public function testNoJoiningDateUsesTheDayTheRecordWasCreated(): void
    {
        $a = P::plan($this->emp(['joined_on' => null, 'created_on' => '2026-11-25']), [$this->programme()], []);
        $this->assertSame('2026-11-25', $a[0]['due_on']);
    }

    public function testNextCycleFromLastCompleted(): void
    {
        $done = $this->case(['id' => 1, 'status' => 'completed', 'completed_on' => '2026-03-15', 'due_on' => '2026-03-01']);
        $a = P::plan($this->emp(), [$this->programme()], [$done]);
        $this->assertSame('2027-03-15', $a[0]['due_on']);
    }

    public function testWaivedCycleCountsAsTheReference(): void
    {
        $waived = $this->case(['id' => 1, 'status' => 'waived', 'due_on' => '2026-06-01']);
        $this->assertSame('2027-06-01', P::plan($this->emp(), [$this->programme()], [$waived])[0]['due_on']);
    }

    public function testRunningAgainChangesNothing(): void
    {
        $this->assertSame([], P::plan($this->emp(), [$this->programme()], [$this->case([])]));
    }

    public function testFrequencyChangeRedatesOpenCase(): void
    {
        $done = $this->case(['id' => 1, 'status' => 'completed', 'completed_on' => '2026-03-15']);
        $open = $this->case(['id' => 2, 'due_on' => '2027-03-15']);
        $a = P::plan($this->emp(), [$this->programme(6)], [$done, $open]);
        $this->assertSame('redate', $a[0]['action']);
        $this->assertSame('2026-09-15', $a[0]['due_on']);
    }

    public function testScheduledCasesAreNeverTouched(): void
    {
        $this->assertSame([], P::plan($this->emp(), [$this->programme(6)], [$this->case(['status' => 'scheduled', 'due_on' => '2030-01-01'])]));
        $this->assertSame([], P::plan($this->emp(['job_role_id' => 8]), [$this->programme()], [$this->case(['status' => 'scheduled'])]));
    }

    public function testMovingOutOfTheProgrammeCancelsUnscheduled(): void
    {
        $a = P::plan($this->emp(['job_role_id' => 8]), [$this->programme()], [$this->case([])]);
        $this->assertSame([['action' => 'cancel', 'case_id' => 900, 'reason' => 'No longer required for this person']], $a);
    }

    public function testLeavingCancelsAndCreatesExitOnce(): void
    {
        $emp = $this->emp(['status' => 'terminated', 'left_on' => '2026-12-10']);
        $a = P::plan($emp, [$this->programme()], [$this->case([])]);
        $this->assertSame('cancel', $a[0]['action']);
        $this->assertSame(['action' => 'create', 'exam' => self::SPIRO, 'category' => 'exit', 'due_on' => '2026-12-10', 'item_id' => 113], $a[1]);

        $after = [$this->case(['status' => 'cancelled']), $this->case(['id' => 901, 'category' => 'exit', 'due_on' => '2026-12-10', 'programme_item_id' => 113])];
        $this->assertSame([], P::plan($emp, [$this->programme()], $after));
    }

    public function testRehireCancelsUnscheduledExit(): void
    {
        $exit = $this->case(['id' => 901, 'category' => 'exit', 'due_on' => '2026-12-10']);
        $open = $this->case([]);
        $a = P::plan($this->emp(), [$this->programme()], [$exit, $open]);
        $this->assertSame([['action' => 'cancel', 'case_id' => 901, 'reason' => 'Back at work']], $a);
    }

    public function testSameExamInTwoProgrammesUsesTheMostFrequent(): void
    {
        $everyone = $this->programme(24, 200, '2026-11-01', [['type' => 'all', 'target' => 0]]);
        $req = P::required($this->emp(), [$everyone, $this->programme(12)]);
        $this->assertSame(12, $req[self::SPIRO . '|periodic']['months']);
        $this->assertSame(112, $req[self::SPIRO . '|periodic']['item_id']);
        $this->assertSame(211, $req[self::SPIRO . '|pre_employment']['item_id']);   // earliest applied wins for one-off checks
    }

    public function testAssignmentByBranchDepartmentAndPerson(): void
    {
        foreach ([['branch', 2], ['department', 3], ['employee', 5], ['all', 0]] as [$type, $id]) {
            $this->assertNotEmpty(P::required($this->emp(), [$this->programme(12, 100, '2026-11-20', [['type' => $type, 'target' => $id]])]), $type);
        }
        $this->assertSame([], P::required($this->emp(), [$this->programme(12, 100, '2026-11-20', [['type' => 'branch', 'target' => 99]])]));
    }

    public function testAddMonthsKeepsEndOfMonth(): void
    {
        $this->assertSame('2027-02-28', P::addMonths('2027-01-31', 1));
        $this->assertSame('2028-02-29', P::addMonths('2028-01-31', 1));
        $this->assertSame('2028-01-15', P::addMonths('2026-11-15', 14));
        $this->assertSame('2027-11-30', P::addMonths('2026-11-30', 12));
    }
}
