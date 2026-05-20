<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Models;

use MyAlert\Models\User;
use PDO;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private PDO $pdo;
    private User $user;

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

        $this->user = new User($this->pdo);
    }

    // --- create() tests ---

    public function testCreateReturnsNewUserId(): void
    {
        $hash = password_hash('password123', PASSWORD_BCRYPT);
        $id = $this->user->create('test@example.com', $hash);

        $this->assertSame(1, $id);
    }

    public function testCreateStoresEmailAndHash(): void
    {
        $hash = password_hash('password123', PASSWORD_BCRYPT);
        $this->user->create('test@example.com', $hash);

        $stmt = $this->pdo->query('SELECT email, password_hash FROM users WHERE id = 1');
        $row = $stmt->fetch();

        $this->assertSame('test@example.com', $row['email']);
        $this->assertSame($hash, $row['password_hash']);
    }

    public function testCreateIncrementsId(): void
    {
        $hash = password_hash('pass1234', PASSWORD_BCRYPT);
        $id1 = $this->user->create('user1@example.com', $hash);
        $id2 = $this->user->create('user2@example.com', $hash);

        $this->assertSame(1, $id1);
        $this->assertSame(2, $id2);
    }

    public function testCreateThrowsOnDuplicateEmail(): void
    {
        $hash = password_hash('pass1234', PASSWORD_BCRYPT);
        $this->user->create('dup@example.com', $hash);

        $this->expectException(\PDOException::class);
        $this->user->create('dup@example.com', $hash);
    }

    // --- findByEmail() tests ---

    public function testFindByEmailReturnsUserArray(): void
    {
        $hash = password_hash('password123', PASSWORD_BCRYPT);
        $this->user->create('find@example.com', $hash);

        $result = $this->user->findByEmail('find@example.com');

        $this->assertIsArray($result);
        $this->assertSame('find@example.com', $result['email']);
        $this->assertSame($hash, $result['password_hash']);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $result = $this->user->findByEmail('nonexistent@example.com');

        $this->assertNull($result);
    }

    // --- findById() tests ---

    public function testFindByIdReturnsUserArray(): void
    {
        $hash = password_hash('password123', PASSWORD_BCRYPT);
        $id = $this->user->create('byid@example.com', $hash);

        $result = $this->user->findById($id);

        $this->assertIsArray($result);
        $this->assertSame('byid@example.com', $result['email']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $result = $this->user->findById(999);

        $this->assertNull($result);
    }

    // --- incrementFailedAttempts() tests ---

    public function testIncrementFailedAttemptsIncrementsByOne(): void
    {
        $hash = password_hash('pass1234', PASSWORD_BCRYPT);
        $id = $this->user->create('lock@example.com', $hash);

        $this->user->incrementFailedAttempts($id);

        $user = $this->user->findById($id);
        $this->assertSame(1, (int) $user['failed_login_attempts']);
    }

    public function testIncrementFailedAttemptsLocksAfterFiveAttempts(): void
    {
        $hash = password_hash('pass1234', PASSWORD_BCRYPT);
        $id = $this->user->create('lockme@example.com', $hash);

        for ($i = 0; $i < 5; $i++) {
            $this->user->incrementFailedAttempts($id);
        }

        $user = $this->user->findById($id);
        $this->assertSame(5, (int) $user['failed_login_attempts']);
        $this->assertNotNull($user['locked_until']);

        // locked_until should be approximately 15 minutes from now
        $lockedUntil = strtotime($user['locked_until']);
        $expected = time() + 900;
        $this->assertEqualsWithDelta($expected, $lockedUntil, 5);
    }

    public function testIncrementFailedAttemptsDoesNotLockBeforeFive(): void
    {
        $hash = password_hash('pass1234', PASSWORD_BCRYPT);
        $id = $this->user->create('nolock@example.com', $hash);

        for ($i = 0; $i < 4; $i++) {
            $this->user->incrementFailedAttempts($id);
        }

        $user = $this->user->findById($id);
        $this->assertSame(4, (int) $user['failed_login_attempts']);
        $this->assertNull($user['locked_until']);
    }

    // --- resetFailedAttempts() tests ---

    public function testResetFailedAttemptsClearsCountAndLock(): void
    {
        $hash = password_hash('pass1234', PASSWORD_BCRYPT);
        $id = $this->user->create('reset@example.com', $hash);

        // Lock the account
        for ($i = 0; $i < 5; $i++) {
            $this->user->incrementFailedAttempts($id);
        }

        // Reset
        $this->user->resetFailedAttempts($id);

        $user = $this->user->findById($id);
        $this->assertSame(0, (int) $user['failed_login_attempts']);
        $this->assertNull($user['locked_until']);
    }

    // --- isLocked() tests ---

    public function testIsLockedReturnsFalseWhenNotLocked(): void
    {
        $hash = password_hash('pass1234', PASSWORD_BCRYPT);
        $id = $this->user->create('unlocked@example.com', $hash);

        $this->assertFalse($this->user->isLocked($id));
    }

    public function testIsLockedReturnsTrueWhenLockedUntilInFuture(): void
    {
        $hash = password_hash('pass1234', PASSWORD_BCRYPT);
        $id = $this->user->create('locked@example.com', $hash);

        // Lock the account
        for ($i = 0; $i < 5; $i++) {
            $this->user->incrementFailedAttempts($id);
        }

        $this->assertTrue($this->user->isLocked($id));
    }

    public function testIsLockedReturnsFalseWhenLockExpired(): void
    {
        $hash = password_hash('pass1234', PASSWORD_BCRYPT);
        $id = $this->user->create('expired@example.com', $hash);

        // Manually set locked_until to the past
        $pastTime = date('Y-m-d H:i:s', time() - 60);
        $stmt = $this->pdo->prepare('UPDATE users SET locked_until = :locked_until WHERE id = :id');
        $stmt->execute([':locked_until' => $pastTime, ':id' => $id]);

        $this->assertFalse($this->user->isLocked($id));
    }

    public function testIsLockedReturnsFalseForNonexistentUser(): void
    {
        $this->assertFalse($this->user->isLocked(999));
    }
}
