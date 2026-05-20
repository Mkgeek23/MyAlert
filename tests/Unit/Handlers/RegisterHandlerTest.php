<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Handlers;

use MyAlert\Handlers\RegisterHandler;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RegisterHandler.
 *
 * Tests the registration flow including validation, duplicate email detection,
 * and successful user creation with bcrypt hashing.
 */
class RegisterHandlerTest extends TestCase
{
    private PDO $pdo;
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

        $this->config = [
            'base_path' => '/MyAlert/public',
            'db_host' => 'localhost',
            'db_name' => 'test',
            'db_user' => 'root',
            'db_password' => '',
            'timezone' => 'UTC',
        ];

        // Initialize session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['csrf_token'] = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testGetRequestRendersRegistrationForm(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $handler = new RegisterHandler($this->config, $this->pdo);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        $this->assertStringContainsString('Create Account', $output);
        $this->assertStringContainsString('name="email"', $output);
        $this->assertStringContainsString('name="password"', $output);
        $this->assertStringContainsString('name="csrf_token"', $output);
    }

    public function testPostWithValidDataCreatesUser(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = 'newuser@example.com';
        $_POST['password'] = 'securepass123';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];

        $handler = new RegisterHandler($this->config, $this->pdo);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        // Verify user was created in database
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => 'newuser@example.com']);
        $user = $stmt->fetch();

        $this->assertNotFalse($user);
        $this->assertSame('newuser@example.com', $user['email']);
        $this->assertTrue(password_verify('securepass123', $user['password_hash']));

        // Verify success page is shown
        $this->assertStringContainsString('Registration Successful', $output);
        $this->assertStringContainsString('newuser@example.com', $output);
    }

    public function testPostWithEmptyEmailShowsError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = '';
        $_POST['password'] = 'securepass123';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];

        $handler = new RegisterHandler($this->config, $this->pdo);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        $this->assertStringContainsString('Email is required', $output);
        $this->assertStringContainsString('Create Account', $output);
    }

    public function testPostWithEmptyPasswordShowsError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = 'test@example.com';
        $_POST['password'] = '';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];

        $handler = new RegisterHandler($this->config, $this->pdo);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        $this->assertStringContainsString('Password is required', $output);
    }

    public function testPostWithInvalidEmailShowsError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = 'invalid-email';
        $_POST['password'] = 'securepass123';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];

        $handler = new RegisterHandler($this->config, $this->pdo);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        $this->assertStringContainsString('Email must contain exactly one @ symbol', $output);
        // Email should be preserved in form
        $this->assertStringContainsString('invalid-email', $output);
    }

    public function testPostWithShortPasswordShowsError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = 'test@example.com';
        $_POST['password'] = 'short';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];

        $handler = new RegisterHandler($this->config, $this->pdo);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        $this->assertStringContainsString('Password must be between 8 and 72 characters', $output);
    }

    public function testPostWithDuplicateEmailShowsError(): void
    {
        // Create existing user
        $hash = password_hash('existing123', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (:email, :hash)');
        $stmt->execute([':email' => 'existing@example.com', ':hash' => $hash]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = 'existing@example.com';
        $_POST['password'] = 'newpassword123';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];

        $handler = new RegisterHandler($this->config, $this->pdo);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        $this->assertStringContainsString('already registered', $output);
        // Email should be preserved
        $this->assertStringContainsString('existing@example.com', $output);
    }

    public function testPostPreservesEmailOnValidationFailure(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = 'user@example.com';
        $_POST['password'] = 'short';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];

        $handler = new RegisterHandler($this->config, $this->pdo);

        ob_start();
        $handler->handle();
        $output = ob_get_clean();

        // Email should be preserved in the form value attribute
        $this->assertStringContainsString('user@example.com', $output);
    }

    public function testPasswordIsHashedWithBcrypt(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email'] = 'bcrypt@example.com';
        $_POST['password'] = 'mypassword123';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];

        $handler = new RegisterHandler($this->config, $this->pdo);

        ob_start();
        $handler->handle();
        ob_get_clean();

        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE email = :email');
        $stmt->execute([':email' => 'bcrypt@example.com']);
        $user = $stmt->fetch();

        // Verify bcrypt hash format ($2y$ prefix)
        $this->assertStringStartsWith('$2y$', $user['password_hash']);
        $this->assertTrue(password_verify('mypassword123', $user['password_hash']));
    }
}
