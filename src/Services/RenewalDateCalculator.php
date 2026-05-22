<?php

declare(strict_types=1);

namespace MyAlert\Services;

/**
 * Pure service class responsible for computing the next alert date
 * for recurring_renewal alerts.
 *
 * Supports two modes:
 * - Day of Month (1-28): finds the next occurrence of a specific day
 * - Number of Days (1-365): adds N days from a reference date
 *
 * Both methods include skip-forward logic to ensure the result
 * is always strictly in the future relative to $now.
 */
class RenewalDateCalculator
{
    /**
     * Calculate next date for day-of-month mode.
     *
     * Finds the next occurrence of the given day (1-28) strictly after
     * $referenceDate. If that date is not strictly after $now, advances
     * month-by-month until it is.
     *
     * @param int       $dayOfMonth   Day value (1-28)
     * @param \DateTime $referenceDate Date to calculate from
     * @param \DateTime $now           Current server time (for skip-forward)
     * @return \DateTime The next occurrence date (time set to 00:00:00)
     */
    public function calculateDayOfMonth(int $dayOfMonth, \DateTime $referenceDate, \DateTime $now): \DateTime
    {
        // Clone to avoid mutating the input
        $candidate = clone $referenceDate;
        $candidate->setTime(0, 0, 0);

        // Start from the reference date's month/year with the target day
        $candidate->setDate(
            (int) $candidate->format('Y'),
            (int) $candidate->format('m'),
            $dayOfMonth
        );

        // Ensure the candidate is strictly after referenceDate
        if ($candidate <= $referenceDate) {
            $candidate->modify('+1 month');
            // Re-set the day in case month modification changed it
            $candidate->setDate(
                (int) $candidate->format('Y'),
                (int) $candidate->format('m'),
                $dayOfMonth
            );
        }

        // Skip-forward: advance month-by-month until strictly after $now
        while ($candidate <= $now) {
            $candidate->modify('+1 month');
            $candidate->setDate(
                (int) $candidate->format('Y'),
                (int) $candidate->format('m'),
                $dayOfMonth
            );
        }

        $candidate->setTime(0, 0, 0);

        return $candidate;
    }

    /**
     * Calculate next date for number-of-days mode.
     *
     * Adds $days to $referenceDate. If the result is not strictly after $now,
     * keeps adding $days until it is.
     *
     * @param int       $days          Number of days to add (1-365)
     * @param \DateTime $referenceDate Date to calculate from
     * @param \DateTime $now           Current server time (for skip-forward)
     * @return \DateTime The next occurrence date (time set to 00:00:00)
     */
    public function calculateNumberOfDays(int $days, \DateTime $referenceDate, \DateTime $now): \DateTime
    {
        // Clone to avoid mutating the input
        $candidate = clone $referenceDate;
        $candidate->setTime(0, 0, 0);

        // Add the interval once
        $candidate->modify("+{$days} days");

        // Skip-forward: keep adding days until strictly after $now
        while ($candidate <= $now) {
            $candidate->modify("+{$days} days");
        }

        $candidate->setTime(0, 0, 0);

        return $candidate;
    }
}
