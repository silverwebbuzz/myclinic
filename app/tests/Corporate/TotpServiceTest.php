<?php

declare(strict_types=1);

namespace Tests\Corporate;

use App\Controllers\Corporate\CorpSecurityController;
use App\Services\Corporate\CorpMfaService;
use App\Services\Corporate\TotpService;
use PHPUnit\Framework\TestCase;

final class TotpServiceTest extends TestCase
{
    /** RFC 6238 Appendix B secret ("12345678901234567890"), base32. */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function testBase32MatchesRfc4648(): void
    {
        $this->assertSame(self::RFC_SECRET, TotpService::base32Encode('12345678901234567890'));
        $this->assertSame('12345678901234567890', TotpService::base32Decode(self::RFC_SECRET));
        $this->assertSame('12345678901234567890', TotpService::base32Decode(strtolower(chunk_split(self::RFC_SECRET, 4, ' '))));
        $random = random_bytes(20);
        $this->assertSame($random, TotpService::base32Decode(TotpService::base32Encode($random)));
    }

    /** @return array<string, array{int, string, string}> time => 8-digit RFC value, 6-digit value */
    public static function rfcVectors(): array
    {
        return [
            't=59' => [59, '94287082', '287082'],
            't=1111111109' => [1111111109, '07081804', '081804'],
            't=1111111111' => [1111111111, '14050471', '050471'],
            't=1234567890' => [1234567890, '89005924', '005924'],
            't=2000000000' => [2000000000, '69279037', '279037'],
            't=20000000000' => [20000000000, '65353130', '353130'],
        ];
    }

    /** @dataProvider rfcVectors */
    public function testRfc6238Vectors(int $time, string $eight, string $six): void
    {
        $step = TotpService::stepAt($time);
        $this->assertSame($eight, TotpService::codeAt(self::RFC_SECRET, $step, 8));
        $this->assertSame($six, TotpService::codeAt(self::RFC_SECRET, $step));
    }

    public function testVerifyWindowAndReplay(): void
    {
        $t = 1111111111;
        $step = TotpService::stepAt($t);
        $code = TotpService::codeAt(self::RFC_SECRET, $step);

        $this->assertSame($step, TotpService::verify(self::RFC_SECRET, $code, null, $t));
        $this->assertSame($step, TotpService::verify(self::RFC_SECRET, substr($code, 0, 3) . ' ' . substr($code, 3), null, $t));
        $this->assertSame($step, TotpService::verify(self::RFC_SECRET, $code, null, $t + 30), 'previous step accepted (clock drift)');
        $this->assertNull(TotpService::verify(self::RFC_SECRET, $code, null, $t + 90), 'too old');
        $this->assertNull(TotpService::verify(self::RFC_SECRET, $code, $step, $t), 'replay of an already-used step');
        $this->assertNull(TotpService::verify(self::RFC_SECRET, '12345', null, $t));
        $this->assertNull(TotpService::verify(self::RFC_SECRET, 'abcdef', null, $t));
    }

    public function testSecretAndUri(): void
    {
        $secret = TotpService::generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $uri = TotpService::uri($secret, 'priya@acme.ae', 'eClinicPro Workforce');
        $this->assertStringStartsWith('otpauth://totp/eClinicPro%20Workforce:priya%40acme.ae?secret=' . $secret, $uri);
        $this->assertStringContainsString('&digits=6&period=30', $uri);
        $this->assertSame('GEZD GNBV GY3T QOJQ GEZD GNBV GY3T QOJQ', TotpService::groupForDisplay(self::RFC_SECRET));
    }

    public function testRecoveryCodes(): void
    {
        $codes = CorpMfaService::newRecoveryCodes();
        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));
        foreach ($codes as $c) {
            $this->assertMatchesRegularExpression('/^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/', $c);
            $this->assertSame(str_replace('-', '', $c), CorpMfaService::normaliseRecovery(strtolower(' ' . $c . ' ')));
        }
        $this->assertNull(CorpMfaService::normaliseRecovery('ABCD-EFG'));
        $this->assertNull(CorpMfaService::normaliseRecovery('ABCD-EFG0'), '0 is not in the alphabet');
        $this->assertNull(CorpMfaService::normaliseRecovery('123456'));
    }

    public function testDeviceLabel(): void
    {
        $this->assertSame('Chrome on Windows', CorpSecurityController::deviceLabel('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'));
        $this->assertSame('Safari on iPhone', CorpSecurityController::deviceLabel('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Edge on Mac', CorpSecurityController::deviceLabel('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Edg/130.0'));
        $this->assertSame('Browser on unknown device', CorpSecurityController::deviceLabel(''));
    }
}
