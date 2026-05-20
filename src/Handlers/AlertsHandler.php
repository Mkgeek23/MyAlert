<?php

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Middleware\AuthMiddleware;
use MyAlert\Models\Alert;
use PDO;

/**
 * Alerts Handler
 *
 * Handles the alerts list page with filter parsing, pagination logic,
 * and rendering of the alerts list template.
 */
class AlertsHandler
{
    private const PER_PAGE = 20;

    private const VALID_STATUSES = ['active', 'closed', 'overdue'];
    private const VALID_TYPES = ['one_time', 'repeat_until_closed', 'recurring_series'];

    private array $config;
    private PDO $pdo;

    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Main request handler. Displays the filtered, paginated alerts list.
     */
    public function handle(): void
    {
        $auth = new AuthMiddleware($this->config);
        $userId = $auth->getUserId();

        $alertModel = new Alert($this->pdo);

        // Parse filter parameters from $_GET
        $filters = $this->parseFilters();
        $page = $this->parsePage();

        // Fetch alerts and total count
        $alerts = $alertModel->findByUser($userId, $filters, $page, self::PER_PAGE);
        $totalCount = $alertModel->countByUser($userId, $filters);
        $totalPages = (int) ceil($totalCount / self::PER_PAGE);

        // Ensure page doesn't exceed total pages
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
            $alerts = $alertModel->findByUser($userId, $filters, $page, self::PER_PAGE);
        }

        $this->render([
            'alerts' => $alerts,
            'filters' => $filters,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * Parse status and type filter parameters from the query string.
     *
     * @return array Validated filters array
     */
    private function parseFilters(): array
    {
        $filters = [];

        $status = $_GET['status'] ?? '';
        if (in_array($status, self::VALID_STATUSES, true)) {
            $filters['status'] = $status;
        }

        $type = $_GET['type'] ?? '';
        if (in_array($type, self::VALID_TYPES, true)) {
            $filters['type'] = $type;
        }

        return $filters;
    }

    /**
     * Parse and validate the page number from the query string.
     * Uses 'p' parameter to avoid conflict with the router's 'page' parameter.
     *
     * @return int Valid page number (minimum 1)
     */
    private function parsePage(): int
    {
        $page = (int) ($_GET['p'] ?? 1);

        return max(1, $page);
    }

    /**
     * Render the alerts list template within the layout.
     *
     * @param array $data Template variables
     */
    private function render(array $data): void
    {
        $pageTitle = 'Alerts';
        $config = $this->config;

        // Extract template variables
        $alerts = $data['alerts'];
        $filters = $data['filters'];
        $currentPage = $data['currentPage'];
        $totalPages = $data['totalPages'];
        $totalCount = $data['totalCount'];

        // Capture page content
        ob_start();
        require __DIR__ . '/../../templates/alerts/list.php';
        $content = ob_get_clean();

        // Render within layout
        require __DIR__ . '/../../templates/layout.php';
    }
}
