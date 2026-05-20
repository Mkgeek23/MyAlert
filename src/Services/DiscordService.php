<?php

declare(strict_types=1);

namespace MyAlert\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

/**
 * Handles all communication with Discord webhook endpoints via Guzzle.
 *
 * Sends alert messages and test messages to Discord webhooks,
 * with structured error handling and logging.
 */
class DiscordService
{
    private Client $client;
    private LogService $logger;
    private string $baseUrl;

    public function __construct(LogService $logger, ?string $baseUrl = null)
    {
        $this->client = new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);
        $this->logger = $logger;
        $this->baseUrl = $baseUrl ?? $this->loadBaseUrl();
    }

    /**
     * Send an alert message to a Discord webhook with an embed.
     *
     * @param string $webhookUrl The Discord webhook URL
     * @param array  $alert      Alert data (must contain 'title', optionally 'description', 'next_run_at', 'id')
     * @param string $closeToken The close token for the alert
     *
     * @return array{success: bool, status_code: int, body: string, error: ?string}
     */
    public function sendAlert(string $webhookUrl, array $alert, string $closeToken): array
    {
        $embed = $this->buildEmbed($alert, $closeToken);

        $payload = [
            'embeds' => [$embed],
        ];

        $payloadSize = strlen(json_encode($payload));
        $alertId = $alert['id'] ?? 'unknown';

        $this->logger->info('discord', "Sending alert #{$alertId} to webhook: {$webhookUrl} (payload size: {$payloadSize} bytes)");

        $result = $this->sendRequest($webhookUrl, $payload);

        if ($result['success']) {
            $this->logger->info('discord', "Alert #{$alertId} delivered successfully - HTTP {$result['status_code']}, body: {$result['body']}");
        } else {
            $this->logger->error('discord', "Alert #{$alertId} delivery failed - HTTP {$result['status_code']}, error: {$result['error']}");
        }

        return $result;
    }

    /**
     * Send a test message to verify a webhook URL works.
     *
     * @param string $webhookUrl The Discord webhook URL to test
     *
     * @return array{success: bool, status_code: int, body: string, error: ?string}
     */
    public function sendTestMessage(string $webhookUrl): array
    {
        $payload = [
            'content' => '✅ MyAlert webhook test successful! This webhook is configured correctly.',
        ];

        $payloadSize = strlen(json_encode($payload));

        $this->logger->info('discord', "Sending test message to webhook: {$webhookUrl} (payload size: {$payloadSize} bytes)");

        $result = $this->sendRequest($webhookUrl, $payload);

        if ($result['success']) {
            $this->logger->info('discord', "Test message delivered successfully - HTTP {$result['status_code']}, body: {$result['body']}");
        } else {
            $this->logger->error('discord', "Test message delivery failed - HTTP {$result['status_code']}, error: {$result['error']}");
        }

        return $result;
    }

    /**
     * Build the Discord embed array for an alert.
     *
     * @param array  $alert      Alert data
     * @param string $closeToken The close token
     *
     * @return array The Discord embed structure
     */
    private function buildEmbed(array $alert, string $closeToken): array
    {
        $title = $alert['title'] ?? 'Alert';
        $description = $alert['description'] ?? '';
        $nextRunAt = $alert['next_run_at'] ?? date('Y-m-d H:i:s');

        $closeLink = $this->baseUrl . '/close-alert?token=' . $closeToken;

        $embed = [
            'title' => $title,
            'color' => $this->getEmbedColor($nextRunAt),
            'fields' => [
                [
                    'name' => 'Close Alert',
                    'value' => "[Click here to close]({$closeLink})",
                    'inline' => false,
                ],
            ],
        ];

        if (!empty($description)) {
            $embed['description'] = $description;
        }

        return $embed;
    }

    /**
     * Determine the embed color based on the alert's next_run_at date.
     *
     * - Green (0x00FF00): next_run_at date is after today
     * - Yellow (0xFFFF00): next_run_at date equals today
     * - Red (0xFF0000): next_run_at date is before today
     *
     * @param string $nextRunAt The next_run_at datetime string
     *
     * @return int The color as an integer
     */
    private function getEmbedColor(string $nextRunAt): int
    {
        $alertDate = date('Y-m-d', strtotime($nextRunAt));
        $today = date('Y-m-d');

        if ($alertDate > $today) {
            return 0x00FF00; // Green - future
        }

        if ($alertDate === $today) {
            return 0xFFFF00; // Yellow - today
        }

        return 0xFF0000; // Red - past
    }

    /**
     * Send a POST request to a Discord webhook URL.
     *
     * @param string $webhookUrl The webhook URL
     * @param array  $payload    The JSON payload
     *
     * @return array{success: bool, status_code: int, body: string, error: ?string}
     */
    private function sendRequest(string $webhookUrl, array $payload): array
    {
        try {
            $response = $this->client->post($webhookUrl, [
                'json' => $payload,
                'timeout' => 10,
                'connect_timeout' => 5,
            ]);

            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'body' => (string) $response->getBody(),
                'error' => null,
            ];
        } catch (ConnectException $e) {
            return [
                'success' => false,
                'status_code' => 0,
                'body' => '',
                'error' => 'Connection timeout',
            ];
        } catch (RequestException $e) {
            $response = $e->getResponse();

            return [
                'success' => false,
                'status_code' => $response ? $response->getStatusCode() : 0,
                'body' => $response ? (string) $response->getBody() : '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Load the base URL from the application config.
     * Builds full URL dynamically from $_SERVER when available,
     * otherwise falls back to base_path only.
     */
    private function loadBaseUrl(): string
    {
        $configPath = dirname(__DIR__, 2) . '/config/app.php';

        if (file_exists($configPath)) {
            $config = require $configPath;
            $basePath = rtrim($config['base_path'] ?? '', '/');

            // Build full URL if running in web context
            if (php_sapi_name() !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                return $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath;
            }

            // Fallback: use base_url if set (backward compat), otherwise just path
            return rtrim($config['base_url'] ?? $basePath, '/');
        }

        return '';
    }
}
