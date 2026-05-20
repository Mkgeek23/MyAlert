<?php

declare(strict_types=1);

namespace MyAlert\Tests\Unit\Handlers;

use MyAlert\Handlers\AlertsHandler;
use MyAlert\Models\Alert;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AlertsHandler.
 *
 * Tests filter parsing, pagination logic, and template rendering
 * for the alerts list page.
 */
class AlertsHandlerTest extends TestCase
{
    private PDO $pdo;
    private array $config;
    private Alert $alertModel;

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
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // --- Handler instantiation ---

    public function testHandlerCanBeInstantiated(): void
    {
        $handler = new AlertsHandler($this->config, $this->pdo);
        $this->assertInstanceOf(AlertsHandler::class, $handler);
    }

    // --- Template rendering tests ---

    public function testListTemplateRendersFilterControls(): void
    {
        $alerts = [];
        $filters = [];
        $currentPage = 1;
        $totalPages = 0;
        $totalCount = 0;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('name="status"', $output);
        $this->assertStringContainsString('name="type"', $output);
        $this->assertStringContainsString('All Statuses', $output);
        $this->assertStringContainsString('All Types', $output);
        $this->assertStringContainsString('Active', $output);
        $this->assertStringContainsString('Closed', $output);
        $this->assertStringContainsString('Overdue', $output);
        $this->assertStringContainsString('One-Time', $output);
        $this->assertStringContainsString('Repeat', $output);
        $this->assertStringContainsString('Series', $output);
    }

    public function testListTemplateShowsNoAlertsMessage(): void
    {
        $alerts = [];
        $filters = [];
        $currentPage = 1;
        $totalPages = 0;
        $totalCount = 0;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('No alerts found', $output);
    }

    public function testListTemplateShowsClearFiltersWhenFiltered(): void
    {
        $alerts = [];
        $filters = ['status' => 'active'];
        $currentPage = 1;
        $totalPages = 0;
        $totalCount = 0;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Clear Filters', $output);
    }

    public function testListTemplateDisplaysAlertRows(): void
    {
        $alerts = [
            [
                'id' => 1,
                'title' => 'Test Alert One',
                'alert_type' => 'one_time',
                'status' => 'active',
                'next_run_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ],
            [
                'id' => 2,
                'title' => 'Test Alert Two',
                'alert_type' => 'repeat_until_closed',
                'status' => 'closed',
                'next_run_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            ],
        ];
        $filters = [];
        $currentPage = 1;
        $totalPages = 1;
        $totalCount = 2;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Test Alert One', $output);
        $this->assertStringContainsString('Test Alert Two', $output);
        $this->assertStringContainsString('One-Time', $output);
        $this->assertStringContainsString('Repeat', $output);
    }

    public function testListTemplateShowsCloseButtonForActiveAlerts(): void
    {
        $alerts = [
            [
                'id' => 1,
                'title' => 'Active Alert',
                'alert_type' => 'one_time',
                'status' => 'active',
                'next_run_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ],
        ];
        $filters = [];
        $currentPage = 1;
        $totalPages = 1;
        $totalCount = 1;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Close', $output);
        $this->assertStringContainsString('View', $output);
    }

    public function testListTemplateShowsCloseButtonForOverdueAlerts(): void
    {
        $alerts = [
            [
                'id' => 1,
                'title' => 'Overdue Alert',
                'alert_type' => 'one_time',
                'status' => 'active',
                'next_run_at' => date('Y-m-d H:i:s', strtotime('-1 hour')), // past = overdue
            ],
        ];
        $filters = [];
        $currentPage = 1;
        $totalPages = 1;
        $totalCount = 1;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Close', $output);
        $this->assertStringContainsString('Overdue', $output);
    }

    public function testListTemplateDoesNotShowCloseButtonForClosedAlerts(): void
    {
        $alerts = [
            [
                'id' => 1,
                'title' => 'Closed Alert',
                'alert_type' => 'one_time',
                'status' => 'closed',
                'next_run_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ],
        ];
        $filters = [];
        $currentPage = 1;
        $totalPages = 1;
        $totalCount = 1;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('View', $output);
        // Close button should not appear for closed alerts
        $this->assertStringNotContainsString('btn-outline-danger', $output);
    }

    public function testListTemplateShowsPaginationWhenMultiplePages(): void
    {
        $alerts = [
            [
                'id' => 1,
                'title' => 'Alert 1',
                'alert_type' => 'one_time',
                'status' => 'active',
                'next_run_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ],
        ];
        $filters = [];
        $currentPage = 1;
        $totalPages = 3;
        $totalCount = 50;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('aria-label="Alerts pagination"', $output);
        $this->assertStringContainsString('page-link', $output);
        $this->assertStringContainsString('Showing page 1 of 3', $output);
    }

    public function testListTemplateDoesNotShowPaginationForSinglePage(): void
    {
        $alerts = [
            [
                'id' => 1,
                'title' => 'Alert 1',
                'alert_type' => 'one_time',
                'status' => 'active',
                'next_run_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ],
        ];
        $filters = [];
        $currentPage = 1;
        $totalPages = 1;
        $totalCount = 1;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringNotContainsString('aria-label="Alerts pagination"', $output);
    }

    public function testListTemplatePreservesFiltersInPaginationLinks(): void
    {
        $alerts = [
            [
                'id' => 1,
                'title' => 'Alert 1',
                'alert_type' => 'one_time',
                'status' => 'active',
                'next_run_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ],
        ];
        $filters = ['status' => 'active', 'type' => 'one_time'];
        $currentPage = 1;
        $totalPages = 3;
        $totalCount = 50;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        // Pagination links should include filter params
        $this->assertStringContainsString('status=active', $output);
        $this->assertStringContainsString('type=one_time', $output);
    }

    public function testListTemplateEscapesXssInAlertTitle(): void
    {
        $alerts = [
            [
                'id' => 1,
                'title' => '<script>alert("xss")</script>',
                'alert_type' => 'one_time',
                'status' => 'active',
                'next_run_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ],
        ];
        $filters = [];
        $currentPage = 1;
        $totalPages = 1;
        $totalCount = 1;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $output);
    }

    public function testListTemplateShowsSelectedStatusFilter(): void
    {
        $alerts = [];
        $filters = ['status' => 'overdue'];
        $currentPage = 1;
        $totalPages = 0;
        $totalCount = 0;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('value="overdue" selected', $output);
    }

    public function testListTemplateShowsSelectedTypeFilter(): void
    {
        $alerts = [];
        $filters = ['type' => 'recurring_series'];
        $currentPage = 1;
        $totalPages = 0;
        $totalCount = 0;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('value="recurring_series" selected', $output);
    }

    public function testListTemplateShowsFloatingActionButton(): void
    {
        $alerts = [];
        $filters = [];
        $currentPage = 1;
        $totalPages = 0;
        $totalCount = 0;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Create new alert', $output);
        $this->assertStringContainsString('d-md-none', $output);
    }

    public function testListTemplateShowsRecurringSeriesBadge(): void
    {
        $alerts = [
            [
                'id' => 1,
                'title' => 'Series Alert',
                'alert_type' => 'recurring_series',
                'status' => 'active',
                'next_run_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            ],
        ];
        $filters = [];
        $currentPage = 1;
        $totalPages = 1;
        $totalCount = 1;
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../../templates/alerts/list.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Series', $output);
    }

    // --- Model integration tests (filter and pagination logic) ---

    public function testFindByUserReturnsAllAlertsForUser(): void
    {
        $this->createAlert(1, 'Alert 1', 'one_time', 'active', '+1 hour');
        $this->createAlert(1, 'Alert 2', 'repeat_until_closed', 'active', '+2 hours');
        $this->createAlert(2, 'Other User Alert', 'one_time', 'active', '+3 hours');

        $alerts = $this->alertModel->findByUser(1, [], 1, 20);

        $this->assertCount(2, $alerts);
    }

    public function testFindByUserFiltersbyStatus(): void
    {
        $this->createAlert(1, 'Active Alert', 'one_time', 'active', '+1 hour');
        $this->createAlert(1, 'Closed Alert', 'one_time', 'closed', '+2 hours');

        $alerts = $this->alertModel->findByUser(1, ['status' => 'active'], 1, 20);

        $this->assertCount(1, $alerts);
        $this->assertEquals('Active Alert', $alerts[0]['title']);
    }

    public function testFindByUserFiltersByType(): void
    {
        $this->createAlert(1, 'One Time', 'one_time', 'active', '+1 hour');
        $this->createAlert(1, 'Repeat', 'repeat_until_closed', 'active', '+2 hours');
        $this->createAlert(1, 'Series', 'recurring_series', 'active', '+3 hours');

        $alerts = $this->alertModel->findByUser(1, ['type' => 'recurring_series'], 1, 20);

        $this->assertCount(1, $alerts);
        $this->assertEquals('Series', $alerts[0]['title']);
    }

    public function testFindByUserPaginatesResults(): void
    {
        // Create 5 alerts
        for ($i = 1; $i <= 5; $i++) {
            $this->createAlert(1, "Alert $i", 'one_time', 'active', "+{$i} hours");
        }

        // Page 1 with 2 per page
        $page1 = $this->alertModel->findByUser(1, [], 1, 2);
        $this->assertCount(2, $page1);

        // Page 2 with 2 per page
        $page2 = $this->alertModel->findByUser(1, [], 2, 2);
        $this->assertCount(2, $page2);

        // Page 3 with 2 per page (only 1 remaining)
        $page3 = $this->alertModel->findByUser(1, [], 3, 2);
        $this->assertCount(1, $page3);
    }

    public function testCountByUserReturnsCorrectCount(): void
    {
        $this->createAlert(1, 'Alert 1', 'one_time', 'active', '+1 hour');
        $this->createAlert(1, 'Alert 2', 'one_time', 'active', '+2 hours');
        $this->createAlert(1, 'Alert 3', 'one_time', 'closed', '+3 hours');
        $this->createAlert(2, 'Other User', 'one_time', 'active', '+4 hours');

        $total = $this->alertModel->countByUser(1, []);
        $this->assertEquals(3, $total);

        $activeCount = $this->alertModel->countByUser(1, ['status' => 'active']);
        $this->assertEquals(2, $activeCount);
    }

    // --- Helper methods ---

    private function createAlert(int $userId, string $title, string $type, string $status, string $timeOffset): int
    {
        $nextRunAt = date('Y-m-d H:i:s', strtotime($timeOffset));

        $alertId = $this->alertModel->create([
            'user_id' => $userId,
            'webhook_id' => 1,
            'title' => $title,
            'description' => null,
            'alert_type' => $type,
            'next_run_at' => $nextRunAt,
            'repeat_interval_minutes' => null,
            'default_next_days' => null,
        ]);

        // If status is 'closed', update it
        if ($status === 'closed') {
            $this->alertModel->updateStatus($alertId, 'closed');
        }

        return $alertId;
    }
}
