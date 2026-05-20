<?php

declare(strict_types=1);

namespace MyAlert\Validation;

class Validator
{
    /**
     * Validate an email address.
     * Rules: exactly one @ symbol, domain part has at least one dot, total length <= 254.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateEmail(string $email): array
    {
        $errors = [];

        if (strlen($email) === 0) {
            $errors[] = 'Email is required.';
            return ['valid' => false, 'errors' => $errors];
        }

        if (strlen($email) > 254) {
            $errors[] = 'Email must not exceed 254 characters.';
            return ['valid' => false, 'errors' => $errors];
        }

        $atCount = substr_count($email, '@');
        if ($atCount !== 1) {
            $errors[] = 'Email must contain exactly one @ symbol.';
            return ['valid' => false, 'errors' => $errors];
        }

        $parts = explode('@', $email);
        $local = $parts[0];
        $domain = $parts[1];

        if (strlen($local) === 0) {
            $errors[] = 'Email local part cannot be empty.';
            return ['valid' => false, 'errors' => $errors];
        }

        if (strlen($domain) === 0) {
            $errors[] = 'Email domain cannot be empty.';
            return ['valid' => false, 'errors' => $errors];
        }

        if (strpos($domain, '.') === false) {
            $errors[] = 'Email domain must contain at least one dot.';
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Validate a password.
     * Rules: 8-72 characters inclusive.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validatePassword(string $password): array
    {
        $errors = [];
        $length = strlen($password);

        if ($length < 8 || $length > 72) {
            $errors[] = 'Password must be between 8 and 72 characters.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate a Discord webhook URL.
     * Must match: https://discord.com/api/webhooks/{numeric_id}/{alphanumeric_token}
     */
    public function validateWebhookUrl(string $url): bool
    {
        $pattern = '#^https://discord\.com/api/webhooks/[0-9]+/[a-zA-Z0-9_-]+$#';
        return (bool) preg_match($pattern, $url);
    }

    /**
     * Validate an alert title.
     * Rules: after trimming, length must be 1-255 characters.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateAlertTitle(string $title): array
    {
        $errors = [];
        $trimmed = trim($title);
        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length < 1) {
            $errors[] = 'Alert title is required.';
        } elseif ($length > 255) {
            $errors[] = 'Alert title must not exceed 255 characters.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate that a datetime is at least 1 minute in the future.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateFutureDateTime(string $datetime): array
    {
        $errors = [];

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            $errors[] = 'Invalid date/time format.';
            return ['valid' => false, 'errors' => $errors];
        }

        $minimumTime = time() + 60; // at least 1 minute in the future

        if ($timestamp < $minimumTime) {
            $errors[] = 'Date/time must be at least 1 minute in the future.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate a repeat interval in minutes.
     * Rules: integer 5-525600 inclusive.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateRepeatInterval(int $minutes): array
    {
        $errors = [];

        if ($minutes < 5 || $minutes > 525600) {
            $errors[] = 'Repeat interval must be between 5 and 525,600 minutes.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate default next days value.
     * Rules: integer 1-365 inclusive.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateDefaultNextDays(int $days): array
    {
        $errors = [];

        if ($days < 1 || $days > 365) {
            $errors[] = 'Default next days must be between 1 and 365.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate a close token.
     * Rules: exactly 64 hexadecimal characters (0-9, a-f).
     */
    public function validateCloseToken(string $token): bool
    {
        return (bool) preg_match('/^[0-9a-f]{64}$/', $token);
    }

    /**
     * Sanitize a string: trim whitespace and optionally enforce max length.
     */
    public static function sanitizeString(string $input, int $maxLength = 0): string
    {
        $sanitized = trim($input);

        if ($maxLength > 0 && mb_strlen($sanitized, 'UTF-8') > $maxLength) {
            $sanitized = mb_substr($sanitized, 0, $maxLength, 'UTF-8');
        }

        return $sanitized;
    }
}
