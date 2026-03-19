<?php

declare(strict_types=1);

namespace Tests\Form;

use PHPUnit\Framework\TestCase;
use RLSQ\Form\Validation\Constraint\Email;
use RLSQ\Form\Validation\Constraint\Length;
use RLSQ\Form\Validation\Constraint\NotBlank;
use RLSQ\Form\Validation\Constraint\Range;
use RLSQ\Form\Validation\Constraint\Regex;

class ConstraintTest extends TestCase
{
    // --- NotBlank ---

    public function testNotBlankValid(): void
    {
        $c = new NotBlank();
        $this->assertNull($c->validate('hello'));
        $this->assertNull($c->validate(0));
    }

    public function testNotBlankInvalid(): void
    {
        $c = new NotBlank();
        $this->assertNotNull($c->validate(''));
        $this->assertNotNull($c->validate(null));
        $this->assertNotNull($c->validate([]));
    }

    public function testNotBlankCustomMessage(): void
    {
        $c = new NotBlank('Requis !');
        $this->assertSame('Requis !', $c->validate(''));
    }

    // --- Length ---

    public function testLengthMin(): void
    {
        $c = new Length(min: 3);
        $this->assertNull($c->validate('abc'));
        $this->assertNull($c->validate('abcdef'));
        $this->assertNotNull($c->validate('ab'));
    }

    public function testLengthMax(): void
    {
        $c = new Length(max: 5);
        $this->assertNull($c->validate('abc'));
        $this->assertNotNull($c->validate('abcdef'));
    }

    public function testLengthSkipsEmpty(): void
    {
        $c = new Length(min: 3);
        $this->assertNull($c->validate(''));
        $this->assertNull($c->validate(null));
    }

    // --- Email ---

    public function testEmailValid(): void
    {
        $c = new Email();
        $this->assertNull($c->validate('user@example.com'));
        $this->assertNull($c->validate(''));
    }

    public function testEmailInvalid(): void
    {
        $c = new Email();
        $this->assertNotNull($c->validate('not-an-email'));
        $this->assertNotNull($c->validate('missing@'));
    }

    // --- Range ---

    public function testRangeValid(): void
    {
        $c = new Range(min: 1, max: 100);
        $this->assertNull($c->validate(50));
        $this->assertNull($c->validate(1));
        $this->assertNull($c->validate(100));
    }

    public function testRangeInvalid(): void
    {
        $c = new Range(min: 1, max: 100);
        $this->assertNotNull($c->validate(0));
        $this->assertNotNull($c->validate(101));
    }

    public function testRangeNonNumeric(): void
    {
        $c = new Range(min: 1);
        $this->assertNotNull($c->validate('abc'));
    }

    // --- Regex ---

    public function testRegexValid(): void
    {
        $c = new Regex('/^\d{5}$/');
        $this->assertNull($c->validate('75001'));
    }

    public function testRegexInvalid(): void
    {
        $c = new Regex('/^\d{5}$/', 'Code postal invalide.');
        $this->assertSame('Code postal invalide.', $c->validate('ABC'));
    }
}
