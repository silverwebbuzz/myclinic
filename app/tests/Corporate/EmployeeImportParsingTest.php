<?php

declare(strict_types=1);

namespace Tests\Corporate;

use App\Services\Corporate\EmployeeImportService as Imp;
use PHPUnit\Framework\TestCase;

/** How the importer reads spreadsheet cells and headers (no database). */
final class EmployeeImportParsingTest extends TestCase
{
    public function testDates(): void
    {
        $this->assertSame('', Imp::date(''));
        $this->assertSame('1990-04-03', Imp::date('03/04/1990'));      // DD/MM, as stated on screen
        $this->assertSame('1990-04-03', Imp::date('3-4-1990'));
        $this->assertSame('1990-04-03', Imp::date('03.04.1990'));
        $this->assertSame('1990-04-03', Imp::date('1990-04-03'));
        $this->assertSame('1990-04-03', Imp::date('1990-04-03 00:00:00'));
        $this->assertSame('2024-03-01', Imp::date('01-Mar-2024'));
        $this->assertSame('2024-03-01', Imp::date('1 March 2024'));
        $this->assertSame('2024-03-01', Imp::date('45352'));            // Excel serial
        $this->assertFalse(Imp::date('1990'));                          // a year alone is not a date
        $this->assertFalse(Imp::date('31/02/1990'));
        $this->assertFalse(Imp::date('next week'));
    }

    public function testGenderAndType(): void
    {
        $this->assertSame('M', Imp::gender('Male'));
        $this->assertSame('F', Imp::gender(' f '));
        $this->assertSame('Other', Imp::gender('Other'));
        $this->assertFalse(Imp::gender('unknown'));
        $this->assertSame('contractor', Imp::employmentType('Contract'));
        $this->assertSame('contractor', Imp::employmentType('Third party'));
        $this->assertSame('employee', Imp::employmentType('Permanent'));
        $this->assertSame('intern', Imp::employmentType('Trainee'));
        $this->assertSame('', Imp::employmentType(''));
        $this->assertFalse(Imp::employmentType('freelance-ish'));
    }

    public function testNationalityAliases(): void
    {
        $this->assertSame('AE', Imp::country('UAE'));
        $this->assertSame('IN', Imp::country('Indian'));
        $this->assertSame('', Imp::country(' '));
    }

    public function testIdTypesNeverAcceptAadhaar(): void
    {
        $this->assertNull(Imp::idType('Aadhaar', 'IN'));
        $this->assertNull(Imp::idType('aadhar card', 'IN'));
        $this->assertSame('passport', Imp::idType('Passport', 'IN'));
        $this->assertSame('pan', Imp::idType('PAN card', 'IN'));
        $this->assertSame('emirates_id', Imp::idType('Emirates ID', 'AE'));
        $this->assertSame('emirates_id', Imp::idType('EID', 'AE'));
        $this->assertNull(Imp::idType('Emirates ID', 'IN'));   // not offered for Indian branches
    }

    public function testIdTypeFromColumnHeader(): void
    {
        $this->assertSame('passport', Imp::idTypeFromHeader('Passport No'));
        $this->assertSame('emirates_id', Imp::idTypeFromHeader('Emirates ID Number'));
        $this->assertSame('pan', Imp::idTypeFromHeader('PAN'));
        $this->assertSame('', Imp::idTypeFromHeader('Aadhaar No'));
        $this->assertSame('', Imp::idTypeFromHeader('ID number'));
    }

    public function testHeaderGuessing(): void
    {
        $map = Imp::guessMapping(['Emp ID', 'Employee Name', 'Mobile No.', 'Location', 'Designation', 'DOJ', 'Passport No', 'Notes']);
        $this->assertSame(0, $map['employee_no']);
        $this->assertSame(1, $map['full_name']);
        $this->assertSame(2, $map['mobile']);
        $this->assertSame(3, $map['branch']);
        $this->assertSame(4, $map['job_role']);
        $this->assertSame(5, $map['joined_on']);
        $this->assertSame(6, $map['id_number']);
        $this->assertNotContains(7, $map);
    }

    public function testOurOwnTemplateMapsOneToOne(): void
    {
        $header = str_getcsv(trim(substr(Imp::templateCsv(), 3)));
        $map = Imp::guessMapping($header);
        $this->assertSame(array_keys(Imp::FIELDS), array_keys($map));
        $this->assertSame(range(0, count($header) - 1), array_values($map));
    }
}
