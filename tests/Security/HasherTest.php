<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use RLSQ\Security\Hasher\NativePasswordHasher;

class HasherTest extends TestCase
{
    public function testHashAndVerify(): void
    {
        $hasher = new NativePasswordHasher();

        $hash = $hasher->hash('secret123');

        $this->assertNotSame('secret123', $hash);
        $this->assertTrue($hasher->verify($hash, 'secret123'));
        $this->assertFalse($hasher->verify($hash, 'wrongpassword'));
    }

    public function testNeedsRehash(): void
    {
        $hasher = new NativePasswordHasher();
        $hash = $hasher->hash('test');

        $this->assertFalse($hasher->needsRehash($hash));
    }
}
