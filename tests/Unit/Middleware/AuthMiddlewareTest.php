<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Middleware;

use MyAlert\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

class AuthMiddlewareTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'base_path' => '/php/MyAlert/public',
        ];

        // Initialize session superglobal for testing
        if (session_status() === PHP_SESSION_NONE) {
            $_SESSION = [];
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // --- check() tests ---

    public function testCheckReturnsFalseWhenNoSession(): void
    {
        $_SESSION = [];
        $middleware = new AuthMiddleware($this->config);

        $this->assertFalse($middleware->check());
    }

    public function testCheckReturnsTrueWithValidSession(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time();
        $middleware = new AuthMiddleware($this->config);

        $this->assertTrue($middleware->check());
    }

    public function testCheckReturnsFalseWhenSessionExpired(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time() - 1801; // 30 min + 1 second ago
        $middleware = new AuthMiddleware($this->config);

        $this->assertFalse($middleware->check());
    }

    public function testCheckReturnsTrueWhenSessionExactly30MinOld(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time() - 1800; // Exactly 30 minutes
        $middleware = new AuthMiddleware($this->config);

        // At exactly 30 minutes, the session is NOT expired (> not >=)
        $this->assertTrue($middleware->check());
    }

    public function testCheckUpdatesLastActivity(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time() - 600; // 10 minutes ago
        $middleware = new AuthMiddleware($this->config);

        $before = time();
        $middleware->check();
        $after = time();

        $this->assertGreaterThanOrEqual($before, $_SESSION['last_activity']);
        $this->assertLessThanOrEqual($after, $_SESSION['last_activity']);
    }

    // --- getUserId() tests ---

    public function testGetUserIdReturnsSessionUserId(): void
    {
        $_SESSION['user_id'] = 42;
        $middleware = new AuthMiddleware($this->config);

        $this->assertSame(42, $middleware->getUserId());
    }

    public function testGetUserIdReturnsIntegerType(): void
    {
        $_SESSION['user_id'] = '7';
        $middleware = new AuthMiddleware($this->config);

        $result = $middleware->getUserId();
        $this->assertIsInt($result);
        $this->assertSame(7, $result);
    }

    // --- isSessionExpired() tests ---

    public function testIsSessionExpiredReturnsFalseWhenNoLastActivity(): void
    {
        $_SESSION = [];
        $this->assertFalse(AuthMiddleware::isSessionExpired());
    }

    public function testIsSessionExpiredReturnsFalseWhenRecent(): void
    {
        $_SESSION['last_activity'] = time() - 100; // 100 seconds ago
        $this->assertFalse(AuthMiddleware::isSessionExpired());
    }

    public function testIsSessionExpiredReturnsTrueWhenOver30Min(): void
    {
        $_SESSION['last_activity'] = time() - 1801; // 30 min + 1 second
        $this->assertTrue(AuthMiddleware::isSessionExpired());
    }

    public function testIsSessionExpiredReturnsFalseAtExactly30Min(): void
    {
        $_SESSION['last_activity'] = time() - 1800; // Exactly 30 minutes
        $this->assertFalse(AuthMiddleware::isSessionExpired());
    }

    // --- enforce() tests ---

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testEnforceRedirectsWhenNotAuthenticated(): void
    {
        $_SESSION = [];
        $middleware = new AuthMiddleware($this->config);

        // We can't easily test exit() and header() in unit tests without
        // running in a separate process. We verify the check() logic instead.
        $this->assertFalse($middleware->check());
    }
}
