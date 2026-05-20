<?php

declare(strict_types=1);

namespace MyAlert\Middleware;

/**
 * CSRF Middleware
 *
 * Generates and validates CSRF tokens for all POST requests.
 * Tokens are per-session (not per-request) and stored in $_SESSION['csrf_token'].
 */
class CsrfMiddleware
{
    /**
     * Generate a new CSRF token and store it in the session.
     *
     * Uses bin2hex(random_bytes(32)) to produce a 64-character hex string.
     */
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;

        return $token;
    }

    /**
     * Get the current CSRF token from the session.
     *
     * If no token exists in the session, generates a new one.
     */
    public function getToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            return $this->generateToken();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a submitted token against the session-stored token.
     *
     * Uses hash_equals() for timing-safe comparison to prevent timing attacks.
     */
    public function validate(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Enforce CSRF validation on POST requests.
     *
     * On POST requests, checks $_POST['csrf_token'] against the session token.
     * If the token is missing or invalid, responds with HTTP 403 and renders
     * the 403 error template.
     */
    public function enforce(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $submittedToken = $_POST['csrf_token'] ?? '';

        if ($submittedToken === '' || !$this->validate($submittedToken)) {
            http_response_code(403);
            $templatePath = __DIR__ . '/../../templates/errors/403.php';
            if (file_exists($templatePath)) {
                include $templatePath;
            } else {
                echo 'Forbidden: Invalid CSRF token.';
            }
            exit;
        }
    }
}
