<?php

declare(strict_types=1);

namespace Tests\Locale;

use App\Support\Locale\CountryRepo;
use App\Support\Locale\LocalDate;
use App\Support\Locale\Locale;
use PHPUnit\Framework\TestCase;

final class LocalDateTest extends TestCase
{
    protected function setUp(): void
    {
        LocaleFixture::prime();
    }

    protected function tearDown(): void
    {
        CountryRepo::reset();
    }

    public function testUtcShownInViewerTimezone(): void
    {
        $utc = '2026-11-18 09:30:00';
        $this->assertSame('18 Nov 2026, 03:00 PM', LocalDate::dateTime($utc, 'Asia/Kolkata'));
        $this->assertSame('18 Nov 2026, 01:30 PM', LocalDate::dateTime($utc, 'Asia/Dubai'));
        $this->assertSame('18 Nov 2026, 09:30', LocalDate::dateTime($utc, 'Europe/London', 'd M Y', 'H:i'));
    }

    public function testCrossesMidnight(): void
    {
        $this->assertSame('19 Nov 2026, 01:30 AM', LocalDate::dateTime('2026-11-18 20:00:00', 'Asia/Kolkata'));
        $this->assertSame('01:30 AM', LocalDate::time('2026-11-18 20:00:00', 'Asia/Kolkata'));
    }

    public function testDateColumnsAreNotShifted(): void
    {
        $this->assertSame('18 Dec 2026', LocalDate::date('2026-12-18'));
        $this->assertSame('18 Dec 2026', LocalDate::date('2026-12-18 23:59:59'));
    }

    public function testEmptyValues(): void
    {
        $this->assertSame('—', LocalDate::date(null));
        $this->assertSame('—', LocalDate::date('0000-00-00'));
        $this->assertSame('—', LocalDate::dateTime('', 'Asia/Dubai'));
        $this->assertSame('—', LocalDate::dateTime('not a date', 'Asia/Dubai'));
    }

    public function testAddDays(): void
    {
        $this->assertSame('2026-12-18', LocalDate::addDays('2026-11-18', 30));
        $this->assertSame('2028-02-29', LocalDate::addDays('2028-02-28', 1));
        $this->assertSame('2026-11-17', LocalDate::addDays('2026-11-18', -1));
    }

    public function testBadTimezoneFallsBackToUtc(): void
    {
        $this->assertSame('UTC', LocalDate::zone('Mars/Base')->getName());
        $this->assertFalse(LocalDate::isValidZone('Mars/Base'));
        $this->assertTrue(LocalDate::isValidZone('Asia/Dubai'));
    }

    public function testNowUtcFormat(): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', LocalDate::nowUtc());
    }

    public function testLocalePrecedence(): void
    {
        $company = ['country_code' => 'AE', 'timezone' => 'Asia/Dubai', 'currency_code' => 'AED'];

        $loc = Locale::forCompany($company);
        $this->assertSame('Asia/Dubai', $loc->timezone);
        $this->assertSame('AED 1,500.00', $loc->money('1500'));
        $this->assertSame('TRN', $loc->label('tax_id_label'));

        // User timezone wins over company; bad user timezone is ignored.
        $this->assertSame('Asia/Kolkata', Locale::forCompany($company, ['timezone' => 'Asia/Kolkata'])->timezone);
        $this->assertSame('Asia/Dubai', Locale::forCompany($company, ['timezone' => 'Nope/Nope'])->timezone);

        // Missing company fields fall back to the country.
        $in = Locale::forCompany(['country_code' => 'IN']);
        $this->assertSame('Asia/Kolkata', $in->timezone);
        $this->assertSame('INR', $in->currency);
        $this->assertSame('GSTIN', $in->label('tax_id_label'));

        // Qatar has no VAT label → fallback.
        $this->assertSame('n/a', Locale::forCountry('QA')->label('tax_id_label', 'n/a'));
    }
}
