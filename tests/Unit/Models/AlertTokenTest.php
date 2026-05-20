<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Models;

use MyAlert\Models\AlertToken;
use PDO;
use PHPUnit\Framework\TestCase;

class AlertTokenTest extends TestCase
{
    private PDO $pdo;
    private AlertToken $alertToken;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec('
            CREATE TABLE alert_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                alert_id INTEGER NOT NULL,
                token CHAR(64) NOT NULL UNIQUE,
                used INTEGER NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->alertToken = new AlertToken($this->pdo);
    }

    // --- create() tests ---

    public function testCreateReturnsNewTokenId(): void
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);

        $id = $this->alertToken->create(1, $token, $expiresAt);

        $this->assertSame(1, $id);
    }

    public function testCreateStoresCorrectData(): void
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);

        $this->alertToken->create(42, $token, $expiresAt);

        $stmt = $this->pdo->query('SELECT alert_id, token, used, expires_at FROM alert_tokens WHERE id = 1');
        $row = $stmt->fetch();

        $this->assertSame(42, (int) $row['alert_id']);
        $this->assertSame($token, $row['token']);
        $this->assertSame(0, (int) $row['used']);
        $this->assertSame($expiresAt, $row['expires_at']);
    }

    public function testCreateIncrementsId(): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);

        $id1 = $this->alertToken->create(1, bin2hex(random_bytes(32)), $expiresAt);
        $id2 = $this->alertToken->create(2, bin2hex(random_bytes(32)), $expiresAt);

        $this->assertSame(1, $id1);
        $this->assertSame(2, $id2);
    }

    public function testCreateThrowsOnDuplicateToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);

        $this->alertToken->create(1, $token, $expiresAt);

        $this->expectException(\PDOException::class);
        $this->alertToken->create(2, $token, $expiresAt);
    }

    // --- findByToken() tests ---

    public function testFindByTokenReturnsTokenRow(): void
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);

        $this->alertToken->create(5, $token, $expiresAt);

        $result = $this->alertToken->findByToken($token);

        $this->assertIsArray($result);
        $this->assertSame(5, (int) $result['alert_id']);
        $this->assertSame($token, $result['token']);
        $this->assertSame(0, (int) $result['used']);
    }

    public function testFindByTokenReturnsNullWhenNotFound(): void
    {
        $result = $this->alertToken->findByToken('nonexistent_token_string_that_does_not_exist_in_db_at_all_1234');

        $this->assertNull($result);
    }

    // --- markUsed() tests ---

    public function testMarkUsedSetsUsedToTrue(): void
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);

        $id = $this->alertToken->create(1, $token, $expiresAt);

        $this->alertToken->markUsed($id);

        $stmt = $this->pdo->prepare('SELECT used FROM alert_tokens WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        $this->assertSame(1, (int) $row['used']);
    }

    public function testMarkUsedDoesNotAffectOtherTokens(): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);

        $id1 = $this->alertToken->create(1, bin2hex(random_bytes(32)), $expiresAt);
        $id2 = $this->alertToken->create(2, bin2hex(random_bytes(32)), $expiresAt);

        $this->alertToken->markUsed($id1);

        $stmt = $this->pdo->prepare('SELECT used FROM alert_tokens WHERE id = :id');
        $stmt->execute([':id' => $id2]);
        $row = $stmt->fetch();

        $this->assertSame(0, (int) $row['used']);
    }

    // --- isValid() tests ---

    public function testIsValidReturnsTrueForUnusedNonExpiredToken(): void
    {
        $tokenRecord = [
            'used' => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ];

        $this->assertTrue($this->alertToken->isValid($tokenRecord));
    }

    public function testIsValidReturnsFalseForUsedToken(): void
    {
        $tokenRecord = [
            'used' => 1,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ];

        $this->assertFalse($this->alertToken->isValid($tokenRecord));
    }

    public function testIsValidReturnsFalseForExpiredToken(): void
    {
        $tokenRecord = [
            'used' => 0,
            'expires_at' => date('Y-m-d H:i:s', time() - 3600),
        ];

        $this->assertFalse($this->alertToken->isValid($tokenRecord));
    }

    public function testIsValidReturnsFalseForUsedAndExpiredToken(): void
    {
        $tokenRecord = [
            'used' => 1,
            'expires_at' => date('Y-m-d H:i:s', time() - 3600),
        ];

        $this->assertFalse($this->alertToken->isValid($tokenRecord));
    }

    public function testIsValidReturnsFalseForTokenExpiringExactlyNow(): void
    {
        // Token that expires at exactly the current second - time() < expires_at is false
        $tokenRecord = [
            'used' => 0,
            'expires_at' => date('Y-m-d H:i:s', time()),
        ];

        $this->assertFalse($this->alertToken->isValid($tokenRecord));
    }

    public function testIsValidReturnsTrueForTokenExpiringOneSecondFromNow(): void
    {
        $tokenRecord = [
            'used' => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + 1),
        ];

        $this->assertTrue($this->alertToken->isValid($tokenRecord));
    }
}
