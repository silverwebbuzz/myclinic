<?php

declare(strict_types=1);

namespace Tests\Locale;

use App\Support\Locale\CountryRepo;
use App\Support\Locale\Phone;
use PHPUnit\Framework\TestCase;

final class PhoneTest extends TestCase
{
    protected function setUp(): void
    {
        LocaleFixture::prime();
    }

    protected function tearDown(): void
    {
        CountryRepo::reset();
    }

    public function testIndia(): void
    {
        $this->assertSame('+919876543210', Phone::normalise('98765 43210', 'IN'));
        $this->assertSame('+919876543210', Phone::normalise('098765-43210', 'IN'));
        $this->assertSame('+919876543210', Phone::normalise('919876543210', 'IN'));
        $this->assertSame('+919876543210', Phone::normalise('+91 98765 43210', 'IN'));
        $this->assertNull(Phone::normalise('12345', 'IN'));
    }

    public function testUae(): void
    {
        $this->assertSame('+971501234567', Phone::normalise('050 123 4567', 'AE'));
        $this->assertSame('+971501234567', Phone::normalise('0097150 1234567', 'AE'));
        $this->assertSame('+971501234567', Phone::normalise('971501234567', 'AE'));
        $this->assertSame('+97141234567', Phone::normalise('04 123 4567', 'AE'));   // Dubai landline
    }

    public function testUkAndKenya(): void
    {
        $this->assertSame('+447911123456', Phone::normalise('07911 123456', 'GB'));
        $this->assertSame('+254712345678', Phone::normalise('0712 345678', 'KE'));
    }

    public function testInternationalInputIgnoresDefaultCountry(): void
    {
        // A UAE number typed by someone whose company is in India.
        $this->assertSame('+971501234567', Phone::normalise('+971 50 123 4567', 'IN'));
    }

    public function testEmptyUnknownAndTooLong(): void
    {
        $this->assertNull(Phone::normalise('', 'IN'));
        $this->assertNull(Phone::normalise(null, 'IN'));
        $this->assertNull(Phone::normalise('0501234567', 'ZZ'));
        $this->assertNull(Phone::normalise('+1234567890123456', 'IN'));    // 16 digits
    }

    public function testValidityAndDisplay(): void
    {
        $this->assertTrue(Phone::isValid('+971501234567'));
        $this->assertFalse(Phone::isValid('971501234567'));
        $this->assertFalse(Phone::isValid('+97150'));
        $this->assertSame('+971 501234567', Phone::display('+971501234567'));
        $this->assertSame('+91 9876543210', Phone::display('+919876543210'));
        $this->assertSame('+44 7911123456', Phone::display('+447911123456'));
        $this->assertSame('', Phone::display(null));
    }
}
