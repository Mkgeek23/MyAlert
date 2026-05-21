<?php

declare(strict_types=1);

namespace MyAlert\Validation;

class IntervalConverter
{
    /**
     * Convert an interval value with a unit to minutes.
     *
     * @param string $value The raw input value (may be decimal for hours)
     * @param string $unit  Either "minutes" or "hours"
     * @return array{valid: bool, minutes: int|null, errors: string[]}
     */
    public static function toMinutes(string $value, string $unit): array
    {
        // Validate unit
        if (!in_array($unit, ['minutes', 'hours'], true)) {
            return ['valid' => false, 'minutes' => null, 'errors' => ['Invalid interval unit.']];
        }

        // Validate numeric
        if (!is_numeric($value)) {
            return ['valid' => false, 'minutes' => null, 'errors' => ['Repeat interval must be a valid number.']];
        }

        $numericValue = (float) $value;

        // Convert to minutes
        $converted = ($unit === 'hours') ? $numericValue * 60.0 : $numericValue;

        // Round to nearest integer
        $minutes = (int) round($converted);

        return ['valid' => true, 'minutes' => $minutes, 'errors' => []];
    }

    /**
     * Determine the best display unit for a stored minutes value.
     *
     * @param int $minutes The stored repeat_interval_minutes value
     * @return array{value: string, unit: string}
     */
    public static function toDisplayUnit(int $minutes): array
    {
        if ($minutes > 0 && $minutes % 60 === 0) {
            return ['value' => (string) ($minutes / 60), 'unit' => 'hours'];
        }

        return ['value' => (string) $minutes, 'unit' => 'minutes'];
    }
}
