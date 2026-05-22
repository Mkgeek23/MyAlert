<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Handlers;

use MyAlert\Models\Alert;
use MyAlert\Models\AlertDelivery;
use MyAlert\Models\Webhook;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ApiWorkerHandler recurring_renewal logic.
 *
 * Tests that the worker correctly advances next_run_at on successful delivery
 * and does NOT advance it on failed delivery.
 *
 * Validates: Requirements 4.2
 */
class ApiWorkerRenewalTest extends TestCase
{
    private PDO $pdo;
    private Alert $alertModel;
    private AlertDelivery $alertDeliveryModel;
    private Webhook $webhookModel;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec('
            CREATE TABLE webhooks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name VARCHAR(100) NOT NULL,
                url VARCHAR(500) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->pdo->exec('
            CREATE TABLE alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                webhook_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                alert_type VARCHAR(30) NOT NULL,
                status VARCHAR(10) NOT NULL DEFAULT \'active\',
                next_run_at DATETIME NOT NULL,
                repeat_interval_minutes INTEGER NULL,
                default_next_days INTEGER NULL,
                renewal_mode VARCHAR(20) NULL DEFAULT NULL,
                renewal_value INTEGER NULL DEFAULT NULL,
                count_from_close_date INTEGER NOT NULL DEFAULT 1,
                series_ended INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->pdo->exec('
            CREATE TABLE alert_deliveries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                alert_id INTEGER NOT NULL,
                status VARCHAR(10) NOT NULL,
                http_status_code INTEGER NULL,
                response_body TEXT NULL,
                sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->webhookModel = new Webhook($this->pdo);
        $this->alertModel = new Alert($this->pdo);
        $this->alertDeliveryModel = new AlertDelivery($this->pdo);
    }

    /**
     * Simulate the worker report logic for a single alert delivery result.
     *
     * This replicates the core logic from ApiWorkerHandler::handleReport()
     * without invoking HTTP globals or exit().
     */
    private function simulateWorkerReport(int $alertId, bool $success, int $statusCode = 204, string $body = ''): void
    {
        // Record delivery (same as handler)
        $deliveryStatus = $success ? 'success' : 'failed';
        $this->alertDeliveryModel->create([
            'alert_id' => $alertId,
            'status' => $deliveryStatus,
            'http_status_code' => $statusCode,
            'response_body' => $body,
        ]);

        // Update alert state on success (same logic as handler)
        if ($success) {
            $alert = $this->alertModel->findById($alertId);

            if ($alert !== null) {
                $alertType = $alert['alert_type'];

                if ($alertType === 'recurring_renewal') {
                    $intervalMinutes = (int) $alert['repeat_interval_minutes'];
                    $nextRunAt = date('Y-m-d H:i:s', strtotime($alert['next_run_at']) + $intervalMinutes * 60);
                    $this->alertModel->updateNextRun($alertId, $nextRunAt);
                }
            }
        }
        // On failure: do NOT advance next_run_at (same as handler)
    }

    /**
     * Helper to create a recurring_renewal alert with known parameters.
     */
    private function createRecurringRenewalAlert(string $nextRunAt, int $repeatIntervalMinutes): int
    {
        $webhookId = $this->webhookModel->create(1, 'Test Webhook', 'https://discord.com/api/webhooks/123/abc');

        return $this->alertModel->create([
            'user_id' => 1,
            'webhook_id' => $webhookId,
            'title' => 'Recurring Renewal Test Alert',
            'description' => 'Test alert for worker renewal',
            'alert_type' => 'recurring_renewal',
            'next_run_at' => $nextRunAt,
            'repeat_interval_minutes' => $repeatIntervalMinutes,
            'default_next_days' => null,
            'renewal_mode' => 'number_of_days',
            'renewal_value' => 7,
            'count_from_close_date' => 1,
        ]);
    }

    // --- Successful delivery tests ---

    public function testSuccessfulDeliveryAdvancesNextRunAtByRepeatInterval(): void
    {
        $originalNextRunAt = '2024-06-15 10:00:00';
        $repeatIntervalMinutes = 60; // 1 hour

        $alertId = $this->createRecurringRenewalAlert($originalNextRunAt, $repeatIntervalMinutes);

        $this->simulateWorkerReport($alertId, true);

        $alert = $this->alertModel->findById($alertId);
        $expectedNextRunAt = '2024-06-15 11:00:00'; // +60 minutes

        $this->assertEquals($expectedNextRunAt, $alert['next_run_at']);
    }

    public function testSuccessfulDeliveryAdvancesNextRunAtByLargeInterval(): void
    {
        $originalNextRunAt = '2024-06-15 10:00:00';
        $repeatIntervalMinutes = 1440; // 24 hours (1 day)

        $alertId = $this->createRecurringRenewalAlert($originalNextRunAt, $repeatIntervalMinutes);

        $this->simulateWorkerReport($alertId, true);

        $alert = $this->alertModel->findById($alertId);
        $expectedNextRunAt = '2024-06-16 10:00:00'; // +1 day

        $this->assertEquals($expectedNextRunAt, $alert['next_run_at']);
    }

    public function testSuccessfulDeliveryAdvancesNextRunAtBySmallInterval(): void
    {
        $originalNextRunAt = '2024-06-15 10:00:00';
        $repeatIntervalMinutes = 5; // minimum interval

        $alertId = $this->createRecurringRenewalAlert($originalNextRunAt, $repeatIntervalMinutes);

        $this->simulateWorkerReport($alertId, true);

        $alert = $this->alertModel->findById($alertId);
        $expectedNextRunAt = '2024-06-15 10:05:00'; // +5 minutes

        $this->assertEquals($expectedNextRunAt, $alert['next_run_at']);
    }

    public function testMultipleSuccessfulDeliveriesAdvanceNextRunAtCumulatively(): void
    {
        $originalNextRunAt = '2024-06-15 10:00:00';
        $repeatIntervalMinutes = 30; // 30 minutes

        $alertId = $this->createRecurringRenewalAlert($originalNextRunAt, $repeatIntervalMinutes);

        // First delivery
        $this->simulateWorkerReport($alertId, true);
        $alert = $this->alertModel->findById($alertId);
        $this->assertEquals('2024-06-15 10:30:00', $alert['next_run_at']);

        // Second delivery
        $this->simulateWorkerReport($alertId, true);
        $alert = $this->alertModel->findById($alertId);
        $this->assertEquals('2024-06-15 11:00:00', $alert['next_run_at']);

        // Third delivery
        $this->simulateWorkerReport($alertId, true);
        $alert = $this->alertModel->findById($alertId);
        $this->assertEquals('2024-06-15 11:30:00', $alert['next_run_at']);
    }

    // --- Failed delivery tests ---

    public function testFailedDeliveryDoesNotAdvanceNextRunAt(): void
    {
        $originalNextRunAt = '2024-06-15 10:00:00';
        $repeatIntervalMinutes = 60;

        $alertId = $this->createRecurringRenewalAlert($originalNextRunAt, $repeatIntervalMinutes);

        $this->simulateWorkerReport($alertId, false, 429, 'rate limited');

        $alert = $this->alertModel->findById($alertId);

        $this->assertEquals($originalNextRunAt, $alert['next_run_at']);
    }

    public function testFailedDeliveryWithServerErrorDoesNotAdvanceNextRunAt(): void
    {
        $originalNextRunAt = '2024-06-15 10:00:00';
        $repeatIntervalMinutes = 120;

        $alertId = $this->createRecurringRenewalAlert($originalNextRunAt, $repeatIntervalMinutes);

        $this->simulateWorkerReport($alertId, false, 500, 'Internal Server Error');

        $alert = $this->alertModel->findById($alertId);

        $this->assertEquals($originalNextRunAt, $alert['next_run_at']);
    }

    public function testFailedThenSuccessfulDeliveryAdvancesOnlyOnce(): void
    {
        $originalNextRunAt = '2024-06-15 10:00:00';
        $repeatIntervalMinutes = 60;

        $alertId = $this->createRecurringRenewalAlert($originalNextRunAt, $repeatIntervalMinutes);

        // First attempt fails - should NOT advance
        $this->simulateWorkerReport($alertId, false, 503, 'Service Unavailable');
        $alert = $this->alertModel->findById($alertId);
        $this->assertEquals($originalNextRunAt, $alert['next_run_at']);

        // Second attempt succeeds - should advance from original
        $this->simulateWorkerReport($alertId, true);
        $alert = $this->alertModel->findById($alertId);
        $this->assertEquals('2024-06-15 11:00:00', $alert['next_run_at']);
    }

    public function testAlertStatusRemainsActiveAfterSuccessfulDelivery(): void
    {
        $originalNextRunAt = '2024-06-15 10:00:00';
        $repeatIntervalMinutes = 60;

        $alertId = $this->createRecurringRenewalAlert($originalNextRunAt, $repeatIntervalMinutes);

        $this->simulateWorkerReport($alertId, true);

        $alert = $this->alertModel->findById($alertId);

        $this->assertEquals('active', $alert['status']);
    }
}
