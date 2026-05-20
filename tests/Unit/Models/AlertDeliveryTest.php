<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Models;

use MyAlert\Models\AlertDelivery;
use PDO;
use PHPUnit\Framework\TestCase;

class AlertDeliveryTest extends TestCase
{
    private PDO $pdo;
    private AlertDelivery $delivery;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create alerts table (simplified for testing)
        $this->pdo->exec('
            CREATE TABLE alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                next_run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Create alert_deliveries table
        $this->pdo->exec('
            CREATE TABLE alert_deliveries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                alert_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                http_status_code INTEGER NULL,
                response_body TEXT NULL,
                sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE
            )
        ');

        $this->delivery = new AlertDelivery($this->pdo);
    }

    private function createAlert(int $userId, string $title = 'Test Alert'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO alerts (user_id, title) VALUES (:user_id, :title)'
        );
        $stmt->execute([':user_id' => $userId, ':title' => $title]);

        return (int) $this->pdo->lastInsertId();
    }

    // --- create() tests ---

    public function testCreateReturnsNewDeliveryId(): void
    {
        $alertId = $this->createAlert(1);

        $id = $this->delivery->create([
            'alert_id' => $alertId,
            'status' => 'success',
            'http_status_code' => 200,
            'response_body' => '{"ok": true}',
        ]);

        $this->assertSame(1, $id);
    }

    public function testCreateStoresAllFields(): void
    {
        $alertId = $this->createAlert(1);

        $this->delivery->create([
            'alert_id' => $alertId,
            'status' => 'failed',
            'http_status_code' => 429,
            'response_body' => 'Rate limited',
        ]);

        $stmt = $this->pdo->query('SELECT * FROM alert_deliveries WHERE id = 1');
        $row = $stmt->fetch();

        $this->assertSame($alertId, (int) $row['alert_id']);
        $this->assertSame('failed', $row['status']);
        $this->assertSame(429, (int) $row['http_status_code']);
        $this->assertSame('Rate limited', $row['response_body']);
    }

    public function testCreateWithNullOptionalFields(): void
    {
        $alertId = $this->createAlert(1);

        $id = $this->delivery->create([
            'alert_id' => $alertId,
            'status' => 'failed',
            'http_status_code' => null,
            'response_body' => null,
        ]);

        $stmt = $this->pdo->query("SELECT * FROM alert_deliveries WHERE id = $id");
        $row = $stmt->fetch();

        $this->assertNull($row['http_status_code']);
        $this->assertNull($row['response_body']);
    }

    public function testCreateWithMissingOptionalFields(): void
    {
        $alertId = $this->createAlert(1);

        $id = $this->delivery->create([
            'alert_id' => $alertId,
            'status' => 'success',
        ]);

        $stmt = $this->pdo->query("SELECT * FROM alert_deliveries WHERE id = $id");
        $row = $stmt->fetch();

        $this->assertNull($row['http_status_code']);
        $this->assertNull($row['response_body']);
    }

    // --- findByUser() tests ---

    public function testFindByUserReturnsDeliveriesForUserAlerts(): void
    {
        $alertId = $this->createAlert(1, 'My Alert');
        $this->delivery->create([
            'alert_id' => $alertId,
            'status' => 'success',
            'http_status_code' => 200,
            'response_body' => 'ok',
        ]);

        $results = $this->delivery->findByUser(1, 1);

        $this->assertCount(1, $results);
        $this->assertSame('My Alert', $results[0]['alert_title']);
        $this->assertSame('success', $results[0]['status']);
        $this->assertSame(200, (int) $results[0]['http_status_code']);
    }

    public function testFindByUserExcludesOtherUsersDeliveries(): void
    {
        $alertUser1 = $this->createAlert(1, 'User 1 Alert');
        $alertUser2 = $this->createAlert(2, 'User 2 Alert');

        $this->delivery->create([
            'alert_id' => $alertUser1,
            'status' => 'success',
            'http_status_code' => 200,
            'response_body' => null,
        ]);
        $this->delivery->create([
            'alert_id' => $alertUser2,
            'status' => 'success',
            'http_status_code' => 200,
            'response_body' => null,
        ]);

        $resultsUser1 = $this->delivery->findByUser(1, 1);
        $resultsUser2 = $this->delivery->findByUser(2, 1);

        $this->assertCount(1, $resultsUser1);
        $this->assertSame('User 1 Alert', $resultsUser1[0]['alert_title']);

        $this->assertCount(1, $resultsUser2);
        $this->assertSame('User 2 Alert', $resultsUser2[0]['alert_title']);
    }

    public function testFindByUserSortsBySentAtDescending(): void
    {
        $alertId = $this->createAlert(1, 'Alert');

        // Insert with explicit sent_at values
        $stmt = $this->pdo->prepare(
            'INSERT INTO alert_deliveries (alert_id, status, sent_at) VALUES (:alert_id, :status, :sent_at)'
        );
        $stmt->execute([':alert_id' => $alertId, ':status' => 'success', ':sent_at' => '2024-01-01 10:00:00']);
        $stmt->execute([':alert_id' => $alertId, ':status' => 'failed', ':sent_at' => '2024-01-03 10:00:00']);
        $stmt->execute([':alert_id' => $alertId, ':status' => 'success', ':sent_at' => '2024-01-02 10:00:00']);

        $results = $this->delivery->findByUser(1, 1);

        $this->assertCount(3, $results);
        $this->assertSame('2024-01-03 10:00:00', $results[0]['sent_at']);
        $this->assertSame('2024-01-02 10:00:00', $results[1]['sent_at']);
        $this->assertSame('2024-01-01 10:00:00', $results[2]['sent_at']);
    }

    public function testFindByUserPaginatesCorrectly(): void
    {
        $alertId = $this->createAlert(1, 'Alert');

        // Insert 5 deliveries
        $stmt = $this->pdo->prepare(
            'INSERT INTO alert_deliveries (alert_id, status, sent_at) VALUES (:alert_id, :status, :sent_at)'
        );
        for ($i = 1; $i <= 5; $i++) {
            $stmt->execute([
                ':alert_id' => $alertId,
                ':status' => 'success',
                ':sent_at' => "2024-01-0{$i} 10:00:00",
            ]);
        }

        // Page 1 with perPage=2
        $page1 = $this->delivery->findByUser(1, 1, 2);
        $this->assertCount(2, $page1);
        $this->assertSame('2024-01-05 10:00:00', $page1[0]['sent_at']);
        $this->assertSame('2024-01-04 10:00:00', $page1[1]['sent_at']);

        // Page 2 with perPage=2
        $page2 = $this->delivery->findByUser(1, 2, 2);
        $this->assertCount(2, $page2);
        $this->assertSame('2024-01-03 10:00:00', $page2[0]['sent_at']);
        $this->assertSame('2024-01-02 10:00:00', $page2[1]['sent_at']);

        // Page 3 with perPage=2
        $page3 = $this->delivery->findByUser(1, 3, 2);
        $this->assertCount(1, $page3);
        $this->assertSame('2024-01-01 10:00:00', $page3[0]['sent_at']);
    }

    public function testFindByUserReturnsEmptyArrayWhenNoDeliveries(): void
    {
        $results = $this->delivery->findByUser(1, 1);

        $this->assertSame([], $results);
    }

    // --- countByUser() tests ---

    public function testCountByUserReturnsCorrectCount(): void
    {
        $alertId = $this->createAlert(1);

        $this->delivery->create(['alert_id' => $alertId, 'status' => 'success', 'http_status_code' => 200, 'response_body' => null]);
        $this->delivery->create(['alert_id' => $alertId, 'status' => 'failed', 'http_status_code' => 500, 'response_body' => null]);

        $this->assertSame(2, $this->delivery->countByUser(1));
    }

    public function testCountByUserExcludesOtherUsers(): void
    {
        $alertUser1 = $this->createAlert(1);
        $alertUser2 = $this->createAlert(2);

        $this->delivery->create(['alert_id' => $alertUser1, 'status' => 'success', 'http_status_code' => 200, 'response_body' => null]);
        $this->delivery->create(['alert_id' => $alertUser1, 'status' => 'success', 'http_status_code' => 200, 'response_body' => null]);
        $this->delivery->create(['alert_id' => $alertUser2, 'status' => 'success', 'http_status_code' => 200, 'response_body' => null]);

        $this->assertSame(2, $this->delivery->countByUser(1));
        $this->assertSame(1, $this->delivery->countByUser(2));
    }

    public function testCountByUserReturnsZeroWhenNoDeliveries(): void
    {
        $this->assertSame(0, $this->delivery->countByUser(1));
    }
}
