<?php

declare(strict_types=1);

namespace Tests\Locale;

use App\Support\Locale\CountryRepo;
use App\Support\Locale\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    protected function setUp(): void
    {
        LocaleFixture::prime();
    }

    protected function tearDown(): void
    {
        CountryRepo::reset();
    }

    public function testIndianRupeeUsesLakhGrouping(): void
    {
        $this->assertSame('₹1,234.50', Money::format('1234.5', 'INR'));
        $this->assertSame('₹12,34,567.00', Money::format('1234567', 'INR'));
        $this->assertSame('₹12,34,56,789.00', Money::format(123456789, 'INR'));
        $this->assertSame('₹999.00', Money::format('999', 'INR'));
    }

    public function testLetterSymbolsGetASpace(): void
    {
        $this->assertSame('AED 1,234.50', Money::format('1234.5', 'AED'));
        $this->assertSame('KSh 1,000.00', Money::format('1000', 'KES'));
    }

    public function testThreeDecimalCurrency(): void
    {
        $this->assertSame('KWD 1,234.568', Money::format('1234.5678', 'KWD'));
        $this->assertSame('KWD 0.500', Money::format('0.5', 'KWD'));
        $this->assertSame(3, Money::decimals('KWD'));
    }

    public function testSignSymbolsAndNegatives(): void
    {
        $this->assertSame('£1,234,567.00', Money::format('1234567', 'GBP'));
        $this->assertSame('-€20.00', Money::format('-20', 'EUR'));
        $this->assertSame('₹0.00', Money::format('-0.001', 'INR'));
    }

    public function testUnknownCurrencyFallsBackToCodeAndTwoDecimals(): void
    {
        $this->assertSame('XYZ 10.00', Money::format('10', 'XYZ'));
    }

    public function testEmptyAndInvalidShowDash(): void
    {
        $this->assertSame('—', Money::format(null, 'INR'));
        $this->assertSame('—', Money::format('', 'INR'));
        $this->assertSame('—', Money::format('abc', 'INR'));
    }

    public function testWithoutSymbol(): void
    {
        $this->assertSame('1,234.50', Money::format('1234.5', 'AED', false));
    }

    public function testNormaliseParsesUserInput(): void
    {
        $this->assertSame('1234.57', Money::normalise('1,234.567', 'AED'));
        $this->assertSame('1234.00', Money::normalise('AED 1,234', 'AED'));
        $this->assertSame('123456.00', Money::normalise('₹1,23,456', 'INR'));
        $this->assertSame('5.125', Money::normalise(' 5.125 ', 'KWD'));
        $this->assertSame('-10.00', Money::normalise('-10', 'GBP'));
        $this->assertNull(Money::normalise('12abc', 'INR'));
        $this->assertNull(Money::normalise('', 'INR'));
        $this->assertNull(Money::normalise(null, 'INR'));
    }
}
