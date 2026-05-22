<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Services;

use MyAlert\Services\RenewalDateCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the RenewalDateCalculator service.
 *
 * Tests day-of-month and number-of-days calculation modes,
 * including edge cases and skip-forward logic.
 */
class RenewalDateCalculatorTest extends TestCase
{
    private RenewalDateCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new RenewalDateCalculator();
    }

    // --- Day of Month: same month (day in future) ---

    public function testDayOfMonthSameMonthDayInFuture(): void
    {
        // Reference: March 5, target day: 20 → should return March 20
        $reference = new \DateTime('2024-03-05 10:30:00');
        $now = new \DateTime('2024-03-05 10:30:00');

        $result = $this->calculator->calculateDayOfMonth(20, $reference, $now);

        $this->assertSame('2024-03-20', $result->format('Y-m-d'));
    }

    public function testDayOfMonthSameMonthDayInFutureDay1(): void
    {
        // Reference: Jan 1 00:00:00, target day: 15 → should return Jan 15
        // (day 15 is strictly after Jan 1)
        $reference = new \DateTime('2024-01-01 00:00:00');
        $now = new \DateTime('2024-01-01 00:00:00');

        $result = $this->calculator->calculateDayOfMonth(15, $reference, $now);

        $this->assertSame('2024-01-15', $result->format('Y-m-d'));
    }

    // --- Day of Month: next month (day already passed) ---

    public function testDayOfMonthNextMonthDayAlreadyPassed(): void
    {
        // Reference: March 25, target day: 10 → should return April 10
        $reference = new \DateTime('2024-03-25 14:00:00');
        $now = new \DateTime('2024-03-25 14:00:00');

        $result = $this->calculator->calculateDayOfMonth(10, $reference, $now);

        $this->assertSame('2024-04-10', $result->format('Y-m-d'));
    }

    public function testDayOfMonthNextMonthWhenDayEqualsReferenceDay(): void
    {
        // Reference: March 15 at noon, target day: 15 → candidate March 15 00:00:00 is NOT strictly after reference
        // So it should advance to April 15
        $reference = new \DateTime('2024-03-15 12:00:00');
        $now = new \DateTime('2024-03-15 12:00:00');

        $result = $this->calculator->calculateDayOfMonth(15, $reference, $now);

        $this->assertSame('2024-04-15', $result->format('Y-m-d'));
    }

    // --- Day of Month: year boundary (December → January) ---

    public function testDayOfMonthYearBoundaryDecemberToJanuary(): void
    {
        // Reference: December 20, target day: 5 → should return January 5 next year
        $reference = new \DateTime('2024-12-20 08:00:00');
        $now = new \DateTime('2024-12-20 08:00:00');

        $result = $this->calculator->calculateDayOfMonth(5, $reference, $now);

        $this->assertSame('2025-01-05', $result->format('Y-m-d'));
    }

    public function testDayOfMonthYearBoundaryDecemberDayInFuture(): void
    {
        // Reference: December 1, target day: 25 → should return December 25 same year
        $reference = new \DateTime('2024-12-01 00:00:00');
        $now = new \DateTime('2024-12-01 00:00:00');

        $result = $this->calculator->calculateDayOfMonth(25, $reference, $now);

        $this->assertSame('2024-12-25', $result->format('Y-m-d'));
    }

    // --- Day of Month: day 28 in February ---

    public function testDayOfMonthDay28InFebruary(): void
    {
        // Reference: January 30, target day: 28 → should return February 28
        $reference = new \DateTime('2024-01-30 00:00:00');
        $now = new \DateTime('2024-01-30 00:00:00');

        $result = $this->calculator->calculateDayOfMonth(28, $reference, $now);

        $this->assertSame('2024-02-28', $result->format('Y-m-d'));
    }

    public function testDayOfMonthDay28InFebruaryNonLeapYear(): void
    {
        // Reference: January 30 2025 (non-leap year), target day: 28 → should return February 28
        $reference = new \DateTime('2025-01-30 00:00:00');
        $now = new \DateTime('2025-01-30 00:00:00');

        $result = $this->calculator->calculateDayOfMonth(28, $reference, $now);

        $this->assertSame('2025-02-28', $result->format('Y-m-d'));
    }

    // --- Number of Days: basic addition from reference date ---

    public function testNumberOfDaysBasicAddition(): void
    {
        // Reference: March 1, add 30 days → March 31
        $reference = new \DateTime('2024-03-01 00:00:00');
        $now = new \DateTime('2024-03-01 00:00:00');

        $result = $this->calculator->calculateNumberOfDays(30, $reference, $now);

        $this->assertSame('2024-03-31', $result->format('Y-m-d'));
    }

    public function testNumberOfDaysAddOneDay(): void
    {
        // Reference: March 15, add 1 day → March 16
        $reference = new \DateTime('2024-03-15 00:00:00');
        $now = new \DateTime('2024-03-15 00:00:00');

        $result = $this->calculator->calculateNumberOfDays(1, $reference, $now);

        $this->assertSame('2024-03-16', $result->format('Y-m-d'));
    }

    public function testNumberOfDaysAdd365Days(): void
    {
        // Reference: Jan 1 2024, add 365 days → Dec 31 2024 (leap year)
        $reference = new \DateTime('2024-01-01 00:00:00');
        $now = new \DateTime('2024-01-01 00:00:00');

        $result = $this->calculator->calculateNumberOfDays(365, $reference, $now);

        $this->assertSame('2024-12-31', $result->format('Y-m-d'));
    }

    // --- Number of Days: year boundary crossing ---

    public function testNumberOfDaysYearBoundaryCrossing(): void
    {
        // Reference: December 20 2024, add 30 days → January 19 2025
        $reference = new \DateTime('2024-12-20 00:00:00');
        $now = new \DateTime('2024-12-20 00:00:00');

        $result = $this->calculator->calculateNumberOfDays(30, $reference, $now);

        $this->assertSame('2025-01-19', $result->format('Y-m-d'));
    }

    public function testNumberOfDaysYearBoundaryCrossingFromDecember31(): void
    {
        // Reference: December 31 2024, add 1 day → January 1 2025
        $reference = new \DateTime('2024-12-31 00:00:00');
        $now = new \DateTime('2024-12-31 00:00:00');

        $result = $this->calculator->calculateNumberOfDays(1, $reference, $now);

        $this->assertSame('2025-01-01', $result->format('Y-m-d'));
    }

    // --- Skip-forward: past reference date advances to future for day-of-month ---

    public function testDayOfMonthSkipForwardPastReferenceAdvancesToFuture(): void
    {
        // Reference is far in the past, $now is March 15 2024
        // Target day: 10 → March 10 is still in the past, so skip to April 10
        $reference = new \DateTime('2023-06-01 00:00:00');
        $now = new \DateTime('2024-03-15 12:00:00');

        $result = $this->calculator->calculateDayOfMonth(10, $reference, $now);

        $this->assertSame('2024-04-10', $result->format('Y-m-d'));
        $this->assertGreaterThan($now, $result);
    }

    public function testDayOfMonthSkipForwardMultipleMonths(): void
    {
        // Reference: Jan 2023, now: Nov 20 2024, target day: 25
        // Nov 25 is still in the future relative to Nov 20, so result should be Nov 25
        $reference = new \DateTime('2023-01-01 00:00:00');
        $now = new \DateTime('2024-11-20 10:00:00');

        $result = $this->calculator->calculateDayOfMonth(25, $reference, $now);

        $this->assertSame('2024-11-25', $result->format('Y-m-d'));
        $this->assertGreaterThan($now, $result);
    }

    // --- Skip-forward: past reference date advances to future for number-of-days ---

    public function testNumberOfDaysSkipForwardPastReferenceAdvancesToFuture(): void
    {
        // Reference: Jan 1 2024, add 7 days → Jan 8 2024
        // But $now is March 15 2024, so keep adding 7 until we pass March 15
        $reference = new \DateTime('2024-01-01 00:00:00');
        $now = new \DateTime('2024-03-15 12:00:00');

        $result = $this->calculator->calculateNumberOfDays(7, $reference, $now);

        $this->assertGreaterThan($now, $result);
        // Verify the result is within one interval of $now
        $previousInterval = clone $result;
        $previousInterval->modify('-7 days');
        $this->assertLessThanOrEqual($now, $previousInterval);
    }

    public function testNumberOfDaysSkipForwardLargeInterval(): void
    {
        // Reference: Jan 1 2023, add 90 days → April 1 2023
        // $now is June 15 2024, so keep adding 90 until we pass June 15 2024
        $reference = new \DateTime('2023-01-01 00:00:00');
        $now = new \DateTime('2024-06-15 08:00:00');

        $result = $this->calculator->calculateNumberOfDays(90, $reference, $now);

        $this->assertGreaterThan($now, $result);
        // Verify the previous interval is at or before $now
        $previousInterval = clone $result;
        $previousInterval->modify('-90 days');
        $this->assertLessThanOrEqual($now, $previousInterval);
    }

    // --- Time is always set to 00:00:00 ---

    public function testDayOfMonthTimeIsAlwaysZero(): void
    {
        // Reference has a non-zero time
        $reference = new \DateTime('2024-03-05 15:45:30');
        $now = new \DateTime('2024-03-05 15:45:30');

        $result = $this->calculator->calculateDayOfMonth(20, $reference, $now);

        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }

    public function testNumberOfDaysTimeIsAlwaysZero(): void
    {
        // Reference has a non-zero time
        $reference = new \DateTime('2024-03-05 23:59:59');
        $now = new \DateTime('2024-03-05 23:59:59');

        $result = $this->calculator->calculateNumberOfDays(10, $reference, $now);

        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }

    public function testDayOfMonthSkipForwardTimeIsAlwaysZero(): void
    {
        // Even with skip-forward, time should be 00:00:00
        $reference = new \DateTime('2023-01-01 18:30:00');
        $now = new \DateTime('2024-03-15 22:15:45');

        $result = $this->calculator->calculateDayOfMonth(10, $reference, $now);

        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }

    public function testNumberOfDaysSkipForwardTimeIsAlwaysZero(): void
    {
        // Even with skip-forward, time should be 00:00:00
        $reference = new \DateTime('2023-06-01 09:15:00');
        $now = new \DateTime('2024-03-15 14:30:00');

        $result = $this->calculator->calculateNumberOfDays(30, $reference, $now);

        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }
}
