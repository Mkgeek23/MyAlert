<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Validation;

use MyAlert\Validation\IntervalConverter;
use PHPUnit\Framework\TestCase;

class IntervalConverterTest extends TestCase
{
    // --- toMinutes() tests ---

    public function testToMinutesWithMinutesUnit(): void
    {
        $result = IntervalConverter::toMinutes('60', 'minutes');

        $this->assertTrue($result['valid']);
        $this->assertSame(60, $result['minutes']);
        $this->assertEmpty($result['errors']);
    }

    public function testToMinutesWithHoursUnit(): void
    {
        $result = IntervalConverter::toMinutes('2', 'hours');

        $this->assertTrue($result['valid']);
        $this->assertSame(120, $result['minutes']);
        $this->assertEmpty($result['errors']);
    }

    public function testToMinutesWithFractionalHours(): void
    {
        $result = IntervalConverter::toMinutes('1.5', 'hours');

        $this->assertTrue($result['valid']);
        $this->assertSame(90, $result['minutes']);
        $this->assertEmpty($result['errors']);
    }

    public function testToMinutesRoundsToNearestInteger(): void
    {
        // 0.084 hours = 5.04 minutes, rounds to 5
        $result = IntervalConverter::toMinutes('0.084', 'hours');

        $this->assertTrue($result['valid']);
        $this->assertSame(5, $result['minutes']);
        $this->assertEmpty($result['errors']);
    }

    public function testToMinutesRejectsInvalidUnit(): void
    {
        $result = IntervalConverter::toMinutes('60', 'days');

        $this->assertFalse($result['valid']);
        $this->assertNull($result['minutes']);
        $this->assertContains('Invalid interval unit.', $result['errors']);
    }

    public function testToMinutesRejectsNonNumericValue(): void
    {
        $result = IntervalConverter::toMinutes('abc', 'minutes');

        $this->assertFalse($result['valid']);
        $this->assertNull($result['minutes']);
        $this->assertContains('Repeat interval must be a valid number.', $result['errors']);
    }

    public function testToMinutesRejectsEmptyString(): void
    {
        $result = IntervalConverter::toMinutes('', 'minutes');

        $this->assertFalse($result['valid']);
        $this->assertNull($result['minutes']);
        $this->assertContains('Repeat interval must be a valid number.', $result['errors']);
    }

    // --- toDisplayUnit() tests ---

    public function testToDisplayUnitReturnsHoursWhenDivisibleBy60(): void
    {
        $result = IntervalConverter::toDisplayUnit(120);

        $this->assertSame('2', $result['value']);
        $this->assertSame('hours', $result['unit']);
    }

    public function testToDisplayUnitReturnsMinutesWhenNotDivisibleBy60(): void
    {
        $result = IntervalConverter::toDisplayUnit(45);

        $this->assertSame('45', $result['value']);
        $this->assertSame('minutes', $result['unit']);
    }

    public function testToDisplayUnitReturnsMinutesForZero(): void
    {
        $result = IntervalConverter::toDisplayUnit(0);

        $this->assertSame('0', $result['value']);
        $this->assertSame('minutes', $result['unit']);
    }

    public function testToDisplayUnitReturnsHoursFor60(): void
    {
        $result = IntervalConverter::toDisplayUnit(60);

        $this->assertSame('1', $result['value']);
        $this->assertSame('hours', $result['unit']);
    }

    public function testToDisplayUnitReturnsMinutesFor5(): void
    {
        $result = IntervalConverter::toDisplayUnit(5);

        $this->assertSame('5', $result['value']);
        $this->assertSame('minutes', $result['unit']);
    }
}
