<?php

/**
 * API Worker Handler
 *
 * Provides a JSON API for the external Python cron worker.
 * Two actions:
 *   GET  ?action=fetch  — Returns due alerts with generated close tokens and webhook URLs
 *   POST ?action=report — Accepts delivery results and updates alert state
 *
 * Secured via Bearer token (worker_api_key in config).
 */

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Models\Alert;
use MyAlert\Models\AlertDelivery;
use MyAlert\Models\AlertToken;
use MyAlert\Models\Webhook;
use MyAlert\Services\LogService;
use PDO;

class ApiWorkerHandler
{
    private array $config;
    private PDO $pdo;

    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    public function handle(): void
    {
        // Authenticate via Bearer token
        if (!$this->authenticate()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'fetch':
                $this->handleFetch();
                break;
            case 'report':
                $this->handleReport();
                break;
            default:
                $this->jsonResponse(['error' => 'Invalid action. Use ?action=fetch or ?action=report'], 400);
        }
    }

    /**
     * GET ?action=fetch
     *
     * Fetches due alerts, generates close tokens, and returns them as JSON.
     * Each alert includes: id, title, description, alert_type, webhook_url, close_token, next_run_at
     */
    private function handleFetch(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(['error' => 'Method not allowed. Use GET for fetch.'], 405);
            return;
        }

        $logger = new LogService();
        $alertModel = new Alert($this->pdo);
        $alertTokenModel = new AlertToken($this->pdo);
        $webhookModel = new Webhook($this->pdo);

        $dueAlerts = $alertModel->getDueAlerts(50);

        $logger->info('worker', 'API fetch: found ' . count($dueAlerts) . ' due alert(s)');

        $result = [];

        foreach ($dueAlerts as $alert) {
            try {
                $alertId = (int) $alert['id'];

                // Generate close token
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 72 * 3600);
                $alertTokenModel->create($alertId, $token, $expiresAt);

                // Look up webhook URL
                $webhook = $webhookModel->findById((int) $alert['webhook_id']);

                if ($webhook === null) {
                    $logger->error('worker', "API fetch: Alert #{$alertId} - webhook ID {$alert['webhook_id']} not found, skipping");
                    continue;
                }

                $result[] = [
                    'id' => $alertId,
                    'title' => $alert['title'],
                    'description' => $alert['description'] ?? '',
                    'alert_type' => $alert['alert_type'],
                    'next_run_at' => $alert['next_run_at'],
                    'repeat_interval_minutes' => $alert['repeat_interval_minutes'] ?? null,
                    'webhook_url' => $webhook['url'],
                    'close_token' => $token,
                ];
            } catch (\Exception $e) {
                $alertId = $alert['id'] ?? 'unknown';
                $logger->error('worker', "API fetch: Failed to prepare alert #{$alertId}: {$e->getMessage()}");
            }
        }

        $this->jsonResponse(['alerts' => $result, 'count' => count($result)]);
    }

    /**
     * POST ?action=report
     *
     * Accepts delivery results from the Python worker.
     * Expected JSON body:
     * {
     *   "results": [
     *     {"alert_id": 1, "success": true, "status_code": 204, "body": ""},
     *     {"alert_id": 2, "success": false, "status_code": 429, "body": "rate limited", "error": "..."}
     *   ]
     * }
     */
    private function handleReport(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed. Use POST for report.'], 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input) || !isset($input['results']) || !is_array($input['results'])) {
            $this->jsonResponse(['error' => 'Invalid JSON body. Expected {"results": [...]}'], 400);
            return;
        }

        $logger = new LogService();
        $alertModel = new Alert($this->pdo);
        $alertDeliveryModel = new AlertDelivery($this->pdo);

        $processed = 0;
        $errors = [];

        foreach ($input['results'] as $item) {
            if (!isset($item['alert_id'])) {
                $errors[] = 'Missing alert_id in result item';
                continue;
            }

            $alertId = (int) $item['alert_id'];
            $success = (bool) ($item['success'] ?? false);
            $statusCode = (int) ($item['status_code'] ?? 0);
            $body = (string) ($item['body'] ?? '');

            try {
                // Record delivery
                $deliveryStatus = $success ? 'success' : 'failed';
                $alertDeliveryModel->create([
                    'alert_id' => $alertId,
                    'status' => $deliveryStatus,
                    'http_status_code' => $statusCode,
                    'response_body' => $body,
                ]);

                // Update alert state on success
                if ($success) {
                    $alert = $alertModel->findById($alertId);

                    if ($alert !== null) {
                        $alertType = $alert['alert_type'];

                        if ($alertType === 'one_time') {
                            $alertModel->updateStatus($alertId, 'closed');
                            $logger->info('worker', "API report: Alert #{$alertId} (one_time) closed");
                        } elseif ($alertType === 'repeat_until_closed') {
                            $intervalMinutes = (int) $alert['repeat_interval_minutes'];
                            $nextRunAt = date('Y-m-d H:i:s', strtotime($alert['next_run_at']) + $intervalMinutes * 60);
                            $alertModel->updateNextRun($alertId, $nextRunAt);
                            $logger->info('worker', "API report: Alert #{$alertId} (repeat_until_closed) next run: {$nextRunAt}");
                        } elseif ($alertType === 'recurring_series') {
                            $logger->info('worker', "API report: Alert #{$alertId} (recurring_series) awaiting user action");
                        } elseif ($alertType === 'recurring_renewal') {
                            $intervalMinutes = (int) $alert['repeat_interval_minutes'];
                            $nextRunAt = date('Y-m-d H:i:s', strtotime($alert['next_run_at']) + $intervalMinutes * 60);
                            $alertModel->updateNextRun($alertId, $nextRunAt);
                            $logger->info('worker', "API report: Alert #{$alertId} (recurring_renewal) next run: {$nextRunAt}");
                        }
                    }
                } else {
                    $errorMsg = $item['error'] ?? 'Unknown error';
                    $logger->error('worker', "API report: Alert #{$alertId} delivery failed - HTTP {$statusCode}, error: {$errorMsg}");
                }

                $processed++;
            } catch (\Exception $e) {
                $errors[] = "Alert #{$alertId}: {$e->getMessage()}";
                $logger->error('worker', "API report: Exception for alert #{$alertId}: {$e->getMessage()}");
            }
        }

        $this->jsonResponse([
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    /**
     * Validate the Bearer token from the Authorization header.
     */
    private function authenticate(): bool
    {
        $expectedKey = $this->config['worker_api_key'] ?? '';

        if (empty($expectedKey) || $expectedKey === 'CHANGE_ME_TO_A_RANDOM_STRING') {
            return false;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        // Fallback: use getallheaders() if Apache didn't pass it via $_SERVER
        if (empty($authHeader) && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (empty($authHeader)) {
            return false;
        }

        if (strpos($authHeader, 'Bearer ') !== 0) {
            return false;
        }

        $providedKey = substr($authHeader, 7);

        return hash_equals($expectedKey, $providedKey);
    }

    /**
     * Send a JSON response.
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
