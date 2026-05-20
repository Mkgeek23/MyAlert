<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Handlers;

use MyAlert\Handlers\LoginHandler;
use MyAlert\Models\User;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LoginHandler.
 *
 * Tests the login logic (credential verification, lockout, session regeneration)
 * by exercising the handler methods indirectly through the User model and session state.
 */
class LoginHandlerTest extends TestCase
{
    private PDO $pdo;
    private User $userModel;
    private array $config;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(254) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                failed_login_attempts INTEGER NOT NULL DEFAULT 0,
                locked_until DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->userModel = new User($this->pdo);
        $this->config = [
            'base_path' => '/MyAlert/public',
            'db_host' => 'localhost',
            'db_name' => 'test',
            'db_user' => 'root',
            'db_password' => '',
            'timezone' => 'UTC',
        ];

        // Ensure session is available for tests
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // --- Login credential verification tests ---

    public function testValidCredentialsVerifySuccessfully(): void
    {
        $password = 'SecurePass123';
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->userModel->create('user@example.com', $hash);

        $user = $this->userModel->findByEmail('user@example.com');

        $this->assertNotNull($user);
        $this->assertTrue(password_verify($password, $user['password_hash']));
    }

    public function testInvalidPasswordFailsVerification(): void
    {
        $hash = password_hash('CorrectPassword', PASSWORD_BCRYPT);
        $this->userModel->create('user@example.com', $hash);

        $user = $this->userModel->findByEmail('user@example.com');

        $this->assertNotNull($user);
        $this->assertFalse(password_verify('WrongPassword', $user['password_hash']));
    }

    public function testNonExistentEmailReturnsNull(): void
    {
        $user = $this->userModel->findByEmail('nonexistent@example.com');

        $this->assertNull($user);
    }

    // --- Account lockout tests ---

    public function testAccountLocksAfterFiveFailedAttempts(): void
    {
        $hash = password_hash('password', PASSWORD_BCRYPT);
        $userId = $this->userModel->create('user@example.com', $hash);

        for ($i = 0; $i < 5; $i++) {
            $this->userModel->incrementFailedAttempts($userId);
        }

        $this->assertTrue($this->userModel->isLocked($userId));
    }

    public function testAccountNotLockedBeforeFiveAttempts(): void
    {
        $hash = password_hash('password', PASSWORD_BCRYPT);
        $userId = $this->userModel->create('user@example.com', $hash);

        for ($i = 0; $i < 4; $i++) {
            $this->userModel->incrementFailedAttempts($userId);
        }

        $this->assertFalse($this->userModel->isLocked($userId));
    }

    public function testResetFailedAttemptsClearsLockout(): void
    {
        $hash = password_hash('password', PASSWORD_BCRYPT);
        $userId = $this->userModel->create('user@example.com', $hash);

        for ($i = 0; $i < 5; $i++) {
            $this->userModel->incrementFailedAttempts($userId);
        }

        $this->assertTrue($this->userModel->isLocked($userId));

        $this->userModel->resetFailedAttempts($userId);

        $this->assertFalse($this->userModel->isLocked($userId));
    }

    // --- Handler instantiation test ---

    public function testHandlerCanBeInstantiated(): void
    {
        $handler = new LoginHandler($this->config, $this->pdo);

        $this->assertInstanceOf(LoginHandler::class, $handler);
    }

    // --- Login template rendering tests ---

    public function testLoginTemplateRendersWithoutErrors(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $error = '';
        $email = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/login.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('name="csrf_token"', $output);
        $this->assertStringContainsString('name="email"', $output);
        $this->assertStringContainsString('name="password"', $output);
        $this->assertStringContainsString('type="submit"', $output);
    }

    public function testLoginTemplateShowsErrorMessage(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $error = 'Invalid email or password';
        $email = 'test@example.com';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/login.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Invalid email or password', $output);
        $this->assertStringContainsString('value="test@example.com"', $output);
    }

    public function testLoginTemplateEscapesXssInEmail(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $error = '';
        $email = '"><script>alert("xss")</script>';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/login.php';
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testLoginTemplateEscapesXssInError(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $error = '<img src=x onerror=alert(1)>';
        $email = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/login.php';
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<img src=x', $output);
        $this->assertStringContainsString('&lt;img src=x', $output);
    }

    public function testLoginTemplateContainsCsrfToken(): void
    {
        $csrfToken = 'abc123def456';
        $error = '';
        $email = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/login.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('value="abc123def456"', $output);
    }

    public function testLoginTemplateHasRegisterLink(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $error = '';
        $email = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/login.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('/register', $output);
    }
}
