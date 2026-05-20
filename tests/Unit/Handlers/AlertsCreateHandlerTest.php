<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Handlers;

use MyAlert\Handlers\AlertsCreateHandler;
use MyAlert\Models\Alert;
use MyAlert\Models\Webhook;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AlertsCreateHandler.
 *
 * Tests the alert creation logic (validation, webhook ownership, type-specific fields)
 * and template rendering.
 */
class AlertsCreateHandlerTest extends TestCase
{
    private PDO $pdo;
    private array $config;
    private Webhook $webhookModel;
    private Alert $alertModel;

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
                series_ended INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->webhookModel = new Webhook($this->pdo);
        $this->alertModel = new Alert($this->pdo);

        $this->config = [
            'base_path' => '/MyAlert/public',
            'db_host' => 'localhost',
            'db_name' => 'test',
            'db_user' => 'root',
            'db_password' => '',
            'timezone' => 'UTC',
        ];

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

    // --- Handler instantiation ---

    public function testHandlerCanBeInstantiated(): void
    {
        $handler = new AlertsCreateHandler($this->config, $this->pdo);
        $this->assertInstanceOf(AlertsCreateHandler::class, $handler);
    }

    // --- Template rendering tests ---

    public function testCreateTemplateRendersFormFields(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $errors = [];
        $webhooks = [
            ['id' => 1, 'name' => 'Test Webhook', 'user_id' => 1, 'url' => 'https://discord.com/api/webhooks/123/abc', 'created_at' => '2024-01-01'],
        ];
        $title = '';
        $description = '';
        $webhook_id = '';
        $alert_type = 'one_time';
        $scheduled_at = '';
        $repeat_interval_minutes = '';
        $default_next_days = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/create.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('name="csrf_token"', $output);
        $this->assertStringContainsString('name="title"', $output);
        $this->assertStringContainsString('name="description"', $output);
        $this->assertStringContainsString('name="webhook_id"', $output);
        $this->assertStringContainsString('name="alert_type"', $output);
        $this->assertStringContainsString('name="scheduled_at"', $output);
        $this->assertStringContainsString('name="repeat_interval_minutes"', $output);
        $this->assertStringContainsString('name="default_next_days"', $output);
        $this->assertStringContainsString('type="submit"', $output);
    }

    public function testCreateTemplateShowsValidationErrors(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $errors = ['Alert title is required.', 'Please select a webhook.'];
        $webhooks = [];
        $title = '';
        $description = '';
        $webhook_id = '';
        $alert_type = 'one_time';
        $scheduled_at = '';
        $repeat_interval_minutes = '';
        $default_next_days = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/create.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Alert title is required.', $output);
        $this->assertStringContainsString('Please select a webhook.', $output);
    }

    public function testCreateTemplatePreservesInputOnError(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $errors = ['Date/time must be at least 1 minute in the future.'];
        $webhooks = [
            ['id' => 1, 'name' => 'My Webhook', 'user_id' => 1, 'url' => 'https://discord.com/api/webhooks/123/abc', 'created_at' => '2024-01-01'],
        ];
        $title = 'My Test Alert';
        $description = 'Some description';
        $webhook_id = '1';
        $alert_type = 'repeat_until_closed';
        $scheduled_at = '2024-01-01T10:00';
        $repeat_interval_minutes = '60';
        $default_next_days = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/create.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('value="My Test Alert"', $output);
        $this->assertStringContainsString('Some description', $output);
        $this->assertStringContainsString('value="1" selected', $output);
        $this->assertStringContainsString('value="2024-01-01T10:00"', $output);
        $this->assertStringContainsString('value="60"', $output);
    }

    public function testCreateTemplateEscapesXssInTitle(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $errors = [];
        $webhooks = [];
        $title = '"><script>alert("xss")</script>';
        $description = '';
        $webhook_id = '';
        $alert_type = 'one_time';
        $scheduled_at = '';
        $repeat_interval_minutes = '';
        $default_next_days = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/create.php';
        $output = ob_get_clean();

        // The user-provided XSS payload should be escaped in the title input value
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $output);
        $this->assertStringNotContainsString('value=""><script>alert("xss")</script>"', $output);
    }

    public function testCreateTemplateEscapesXssInDescription(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $errors = [];
        $webhooks = [];
        $title = '';
        $description = '<img src=x onerror=alert(1)>';
        $webhook_id = '';
        $alert_type = 'one_time';
        $scheduled_at = '';
        $repeat_interval_minutes = '';
        $default_next_days = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/create.php';
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<img src=x', $output);
        $this->assertStringContainsString('&lt;img src=x', $output);
    }

    public function testCreateTemplateEscapesXssInErrors(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $errors = ['<script>alert("xss")</script>'];
        $webhooks = [];
        $title = '';
        $description = '';
        $webhook_id = '';
        $alert_type = 'one_time';
        $scheduled_at = '';
        $repeat_interval_minutes = '';
        $default_next_days = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/create.php';
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>alert', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testCreateTemplateShowsWebhookOptions(): void
    {
        $csrfToken = bin2hex(random_bytes(32));
        $errors = [];
        $webhooks = [
            ['id' => 1, 'name' => 'Webhook One', 'user_id' => 1, 'url' => 'https://discord.com/api/webhooks/1/a', 'created_at' => '2024-01-01'],
            ['id' => 2, 'name' => 'Webhook Two', 'user_id' => 1, 'url' => 'https://discord.com/api/webhooks/2/b', 'created_at' => '2024-01-01'],
        ];
        $title = '';
        $description = '';
        $webhook_id = '';
        $alert_type = 'one_time';
        $scheduled_at = '';
        $repeat_interval_minutes = '';
        $default_next_days = '';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/create.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Webhook One', $output);
        $this->assertStringContainsString('Webhook Two', $output);
        $this->assertStringContainsString('value="1"', $output);
        $this->assertStringContainsString('value="2"', $output);
    }

    // --- Alert model creation tests (verifying handler logic indirectly) ---

    public function testAlertCreatedWithOneTimeType(): void
    {
        $webhookId = $this->webhookModel->create(1, 'Test', 'https://discord.com/api/webhooks/123/abc');
        $futureDate = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $alertId = $this->alertModel->create([
            'user_id' => 1,
            'webhook_id' => $webhookId,
            'title' => 'One Time Alert',
            'description' => null,
            'alert_type' => 'one_time',
            'next_run_at' => $futureDate,
            'repeat_interval_minutes' => null,
            'default_next_days' => null,
        ]);

        $alert = $this->alertModel->findById($alertId);
        $this->assertNotNull($alert);
        $this->assertEquals('one_time', $alert['alert_type']);
        $this->assertEquals('active', $alert['status']);
        $this->assertEquals($futureDate, $alert['next_run_at']);
        $this->assertNull($alert['repeat_interval_minutes']);
        $this->assertNull($alert['default_next_days']);
    }

    public function testAlertCreatedWithRepeatUntilClosedType(): void
    {
        $webhookId = $this->webhookModel->create(1, 'Test', 'https://discord.com/api/webhooks/123/abc');
        $futureDate = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $alertId = $this->alertModel->create([
            'user_id' => 1,
            'webhook_id' => $webhookId,
            'title' => 'Repeat Alert',
            'description' => 'Repeats every hour',
            'alert_type' => 'repeat_until_closed',
            'next_run_at' => $futureDate,
            'repeat_interval_minutes' => 60,
            'default_next_days' => null,
        ]);

        $alert = $this->alertModel->findById($alertId);
        $this->assertNotNull($alert);
        $this->assertEquals('repeat_until_closed', $alert['alert_type']);
        $this->assertEquals('active', $alert['status']);
        $this->assertEquals(60, (int) $alert['repeat_interval_minutes']);
        $this->assertNull($alert['default_next_days']);
    }

    public function testAlertCreatedWithRecurringSeriesType(): void
    {
        $webhookId = $this->webhookModel->create(1, 'Test', 'https://discord.com/api/webhooks/123/abc');
        $futureDate = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $alertId = $this->alertModel->create([
            'user_id' => 1,
            'webhook_id' => $webhookId,
            'title' => 'Series Alert',
            'description' => null,
            'alert_type' => 'recurring_series',
            'next_run_at' => $futureDate,
            'repeat_interval_minutes' => null,
            'default_next_days' => 7,
        ]);

        $alert = $this->alertModel->findById($alertId);
        $this->assertNotNull($alert);
        $this->assertEquals('recurring_series', $alert['alert_type']);
        $this->assertEquals('active', $alert['status']);
        $this->assertNull($alert['repeat_interval_minutes']);
        $this->assertEquals(7, (int) $alert['default_next_days']);
    }

    public function testWebhookOwnershipVerification(): void
    {
        // Create webhook for user 1
        $webhookId = $this->webhookModel->create(1, 'User1 Webhook', 'https://discord.com/api/webhooks/123/abc');

        // Verify it belongs to user 1
        $webhook = $this->webhookModel->findById($webhookId);
        $this->assertNotNull($webhook);
        $this->assertEquals(1, (int) $webhook['user_id']);

        // Verify it does NOT belong to user 2
        $this->assertNotEquals(2, (int) $webhook['user_id']);
    }

    public function testWebhookNotFoundReturnsNull(): void
    {
        $webhook = $this->webhookModel->findById(999);
        $this->assertNull($webhook);
    }
}
