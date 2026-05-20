<?php

/**
 * Alert Worker - CLI Script
 *
 * Processes due alerts and sends Discord webhook notifications.
 * Designed to be run via cron (Linux) or Task Scheduler (Windows).
 *
 * Usage: php worker.php
 *
 * Flow:
 * 1. Initialize app (load config, connect DB)
 * 2. Log cycle start
 * 3. Fetch due alerts (max 50)
 * 4. For each alert:
 *    a. Generate close token
 *    b. Store token with 72-hour expiry
 *    c. Look up webhook URL
 *    d. Send Discord webhook
 *    e. Record delivery
 *    f. Update alert state (close one-time, advance repeat interval)
 * 5. Log cycle end with count
 */

declare(strict_types=1);

// Ensure this script is run from CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────

require_once __DIR__ . '/src/bootstrap.php';

// ─── Dependencies ────────────────────────────────────────────────────────────

require_once __DIR__ . '/vendor/autoload.php';

use MyAlert\Services\LogService;
use MyAlert\Services\DiscordService;
use MyAlert\Models\Alert;
use MyAlert\Models\AlertToken;
use MyAlert\Models\AlertDelivery;
use MyAlert\Models\Webhook;

// ─── Initialize Services ─────────────────────────────────────────────────────

$logger = new LogService();
$discordService = new DiscordService($logger);
$alertModel = new Alert($pdo);
$alertTokenModel = new AlertToken($pdo);
$alertDeliveryModel = new AlertDelivery($pdo);
$webhookModel = new Webhook($pdo);

// ─── Worker Cycle ────────────────────────────────────────────────────────────

$logger->info('worker', 'Worker cycle started');

$dueAlerts = $alertModel->getDueAlerts(50);
$totalCount = count($dueAlerts);
$successCount = 0;
$failCount = 0;

$logger->info('worker', "Found {$totalCount} due alert(s) to process");

foreach ($dueAlerts as $alert) {
    try {
        $alertId = (int) $alert['id'];

        // a. Generate close token
        $token = bin2hex(random_bytes(32));

        // b. Calculate expiry (72 hours from now) and store token
        $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);
        $alertTokenModel->create($alertId, $token, $expiresAt);

        // c. Look up webhook URL
        $webhook = $webhookModel->findById((int) $alert['webhook_id']);

        if ($webhook === null) {
            $logger->error('worker', "Alert #{$alertId}: webhook ID {$alert['webhook_id']} not found, skipping");
            $failCount++;
            continue;
        }

        $webhookUrl = $webhook['url'];

        // d. Send Discord webhook
        $result = $discordService->sendAlert($webhookUrl, $alert, $token);

        // e. Record delivery
        $deliveryStatus = $result['success'] ? 'success' : 'failed';
        $alertDeliveryModel->create([
            'alert_id' => $alertId,
            'status' => $deliveryStatus,
            'http_status_code' => $result['status_code'],
            'response_body' => $result['body'],
        ]);

        // f. Update alert state based on delivery outcome and alert type
        if ($result['success']) {
            $alertType = $alert['alert_type'];

            if ($alertType === 'one_time') {
                // Close one-time alerts on successful delivery
                $alertModel->updateStatus($alertId, 'closed');
                $logger->info('worker', "Alert #{$alertId}: one_time delivered and closed");
            } elseif ($alertType === 'repeat_until_closed') {
                // Advance next_run_at by repeat_interval_minutes
                $intervalMinutes = (int) $alert['repeat_interval_minutes'];
                $nextRunAt = date('Y-m-d H:i:s', strtotime($alert['next_run_at']) + $intervalMinutes * 60);
                $alertModel->updateNextRun($alertId, $nextRunAt);
                $logger->info('worker', "Alert #{$alertId}: repeat_until_closed delivered, next run at {$nextRunAt}");
            } elseif ($alertType === 'recurring_series') {
                // Do nothing - wait for user to close via token
                $logger->info('worker', "Alert #{$alertId}: recurring_series delivered, awaiting user action");
            }

            $successCount++;
        } else {
            $logger->error('worker', "Alert #{$alertId}: delivery failed - HTTP {$result['status_code']}, error: {$result['error']}");
            $failCount++;
        }
    } catch (\Exception $e) {
        $alertId = $alert['id'] ?? 'unknown';
        $logger->error('worker', "Failed to process alert #{$alertId}: {$e->getMessage()}");
        $failCount++;
    }
}

$logger->info('worker', "Worker cycle completed: {$totalCount} processed, {$successCount} succeeded, {$failCount} failed");
