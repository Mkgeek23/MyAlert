<?php

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Middleware\AuthMiddleware;
use MyAlert\Models\Alert;
use PDO;

/**
 * Dashboard Handler
 *
 * Displays the main dashboard with summary cards (active, today's, overdue alert counts)
 * and a list of up to 10 overdue alerts sorted by next_run_at ascending.
 */
class DashboardHandler
{
    private array $config;
    private PDO $pdo;

    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Main request handler. Fetches dashboard data and renders the template.
     */
    public function handle(): void
    {
        $auth = new AuthMiddleware($this->config);
        $alertModel = new Alert($this->pdo);

        $userId = $auth->getUserId();

        // Fetch summary counts: active, today, overdue
        $counts = $alertModel->getDashboardCounts($userId);

        // Fetch up to 10 overdue alerts sorted by next_run_at ASC
        $overdueAlerts = $alertModel->getOverdueAlerts($userId, 10);

        $this->render([
            'counts' => $counts,
            'overdueAlerts' => $overdueAlerts,
        ]);
    }

    /**
     * Render the dashboard template within the layout.
     *
     * @param array $data Template variables
     */
    private function render(array $data): void
    {
        $pageTitle = 'Dashboard';
        $config = $this->config;

        // Extract template variables
        $counts = $data['counts'];
        $overdueAlerts = $data['overdueAlerts'];

        // Capture page content
        ob_start();
        require __DIR__ . '/../../templates/dashboard.php';
        $content = ob_get_clean();

        // Render within layout
        require __DIR__ . '/../../templates/layout.php';
    }
}
