<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Models;

use MyAlert\Models\Alert;
use PDO;
use PHPUnit\Framework\TestCase;

class AlertTest extends TestCase
{
    private PDO $pdo;
    private Alert $alert;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec('
            CREATE TABLE alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                webhook_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                alert_type TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'active\',
                next_run_at DATETIME NOT NULL,
                repeat_interval_minutes INTEGER NULL,
                default_next_days INTEGER NULL,
                series_ended INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->alert = new Alert($this->pdo);
    }

    private function createClosedAlert(string $alertType, bool $seriesEnded = false): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO alerts (user_id, webhook_id, title, alert_type, status, next_run_at, series_ended)
             VALUES (:user_id, :webhook_id, :title, :alert_type, :status, :next_run_at, :series_ended)'
        );
        $stmt->execute([
            ':user_id' => 1,
            ':webhook_id' => 1,
            ':title' => 'Test Alert',
            ':alert_type' => $alertType,
            ':status' => 'closed',
            ':next_run_at' => '2024-01-01 10:00:00',
            ':series_ended' => $seriesEnded ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // --- reopenAlert() tests ---

    public function testReopenAlertSetsStatusToActive(): void
    {
        $id = $this->createClosedAlert('one_time');

        $this->alert->reopenAlert($id, '2025-06-01 12:00:00', 'one_time');

        $stmt = $this->pdo->prepare('SELECT status FROM alerts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        $this->assertSame('active', $row['status']);
    }

    public function testReopenAlertUpdatesNextRunAt(): void
    {
        $id = $this->createClosedAlert('one_time');

        $this->alert->reopenAlert($id, '2025-06-15 14:30:00', 'one_time');

        $stmt = $this->pdo->prepare('SELECT next_run_at FROM alerts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        $this->assertSame('2025-06-15 14:30:00', $row['next_run_at']);
    }

    public function testReopenAlertResetsSeriesEndedForRecurringSeries(): void
    {
        $id = $this->createClosedAlert('recurring_series', true);

        $this->alert->reopenAlert($id, '2025-06-01 12:00:00', 'recurring_series');

        $stmt = $this->pdo->prepare('SELECT series_ended FROM alerts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        $this->assertSame(0, (int) $row['series_ended']);
    }

    public function testReopenAlertDoesNotModifySeriesEndedForOneTime(): void
    {
        $id = $this->createClosedAlert('one_time', false);

        // Manually set series_ended to 0 (default)
        $this->alert->reopenAlert($id, '2025-06-01 12:00:00', 'one_time');

        $stmt = $this->pdo->prepare('SELECT series_ended FROM alerts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        $this->assertSame(0, (int) $row['series_ended']);
    }

    public function testReopenAlertDoesNotModifySeriesEndedForRepeatUntilClosed(): void
    {
        $id = $this->createClosedAlert('repeat_until_closed', false);

        $this->alert->reopenAlert($id, '2025-06-01 12:00:00', 'repeat_until_closed');

        $stmt = $this->pdo->prepare('SELECT series_ended FROM alerts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        $this->assertSame(0, (int) $row['series_ended']);
    }

    public function testReopenAlertWorksForAllAlertTypes(): void
    {
        $types = ['one_time', 'repeat_until_closed', 'recurring_series'];

        foreach ($types as $type) {
            $id = $this->createClosedAlert($type, $type === 'recurring_series');

            $this->alert->reopenAlert($id, '2025-07-01 09:00:00', $type);

            $stmt = $this->pdo->prepare('SELECT status, next_run_at FROM alerts WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            $this->assertSame('active', $row['status'], "Failed for alert_type: $type");
            $this->assertSame('2025-07-01 09:00:00', $row['next_run_at'], "Failed for alert_type: $type");
        }
    }
}
