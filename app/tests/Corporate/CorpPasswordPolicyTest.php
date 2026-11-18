<?php

declare(strict_types=1);

namespace Tests\Corporate;

use App\Services\Corporate\CorpPasswordPolicy;
use PHPUnit\Framework\TestCase;

final class CorpPasswordPolicyTest extends TestCase
{
    public function testAcceptsAGoodPassword(): void
    {
        $this->assertNull(CorpPasswordPolicy::check('Blue-Harbour-2026', 'Blue-Harbour-2026', 'priya@acme.ae', 'Priya Nair'));
    }

    public function testRules(): void
    {
        $this->assertStringContainsString('at least 10', (string) CorpPasswordPolicy::check('short1!', 'short1!'));
        $this->assertSame('Passwords do not match.', CorpPasswordPolicy::check('Blue-Harbour-2026', 'Blue-Harbour-2027'));
        $this->assertStringContainsString('too common', (string) CorpPasswordPolicy::check('Password123', 'Password123'));
        $this->assertStringContainsString('variety', (string) CorpPasswordPolicy::check('aaaaabbbbbb', 'aaaaabbbbbb'));
        $this->assertStringContainsString('email', (string) CorpPasswordPolicy::check('priyanair2026!', 'priyanair2026!', 'priyanair@acme.ae'));
        $this->assertStringContainsString('your name', (string) CorpPasswordPolicy::check('priyanairxx', 'priyanairxx', '', 'Priya Nairxx'));
        $this->assertStringContainsString('at most', (string) CorpPasswordPolicy::check(str_repeat('ab1', 50), str_repeat('ab1', 50)));
    }

    public function testHashVerifies(): void
    {
        $hash = CorpPasswordPolicy::hash('Blue-Harbour-2026');
        $this->assertTrue(password_verify('Blue-Harbour-2026', $hash));
        $this->assertFalse(password_verify('blue-harbour-2026', $hash));
    }
}
