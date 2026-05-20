<?php

declare(strict_types=1);

namespace MyAlert\Middleware;

/**
 * Authentication Middleware
 *
 * Validates session state and enforces authentication on protected routes.
 * Sessions expire after 30 minutes of inactivity.
 */
class AuthMiddleware
{
    private const SESSION_TIMEOUT = 1800; // 30 minutes in seconds

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Check if the current session is valid and not expired.
     *
     * Returns true if $_SESSION contains a valid user_id AND
     * the session hasn't exceeded the 30-minute inactivity timeout.
     */
    public function check(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        if (self::isSessionExpired()) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
                session_start();
            }
            return false;
        }

        // Update last activity timestamp
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Enforce authentication. Redirects to login page with HTTP 302 if not authenticated.
     */
    public function enforce(): void
    {
        if (!$this->check()) {
            $loginUrl = rtrim($this->config['base_path'], '/') . '/login';
            header('Location: ' . $loginUrl, true, 302);
            exit;
        }
    }

    /**
     * Get the authenticated user's ID from the session.
     *
     * Assumes check() has already passed.
     */
    public function getUserId(): int
    {
        return (int) $_SESSION['user_id'];
    }

    /**
     * Check if the session has expired due to inactivity.
     *
     * Returns true if last_activity is more than 30 minutes ago.
     */
    public static function isSessionExpired(): bool
    {
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }

        return (time() - $_SESSION['last_activity']) > self::SESSION_TIMEOUT;
    }
}
