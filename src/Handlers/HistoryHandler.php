<?php

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Middleware\AuthMiddleware;
use MyAlert\Models\AlertDelivery;
use PDO;

/**
 * History Handler
 *
 * Displays paginated delivery history for the authenticated user's alerts.
 * Shows sent_at, alert title, delivery status, and HTTP response code.
 */
class HistoryHandler
{
    private const PER_PAGE = 20;

    private array $config;
    private PDO $pdo;

    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Main request handler. Displays paginated delivery history.
     */
    public function handle(): void
    {
        $auth = new AuthMiddleware($this->config);
        $deliveryModel = new AlertDelivery($this->pdo);

        $userId = $auth->getUserId();

        // Get page from query string, default to 1
        $page = max(1, (int) ($_GET['page'] ?? 1));

        // Fetch delivery records and total count
        $deliveries = $deliveryModel->findByUser($userId, $page, self::PER_PAGE);
        $totalCount = $deliveryModel->countByUser($userId);
        $totalPages = (int) ceil($totalCount / self::PER_PAGE);

        // Ensure page doesn't exceed total pages (but allow page 1 when empty)
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
            $deliveries = $deliveryModel->findByUser($userId, $page, self::PER_PAGE);
        }

        $this->render([
            'deliveries' => $deliveries,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * Render the history template within the layout.
     *
     * @param array $data Template variables
     */
    private function render(array $data): void
    {
        $pageTitle = 'Delivery History';
        $config = $this->config;

        // Extract template variables
        $deliveries = $data['deliveries'];
        $currentPage = $data['currentPage'];
        $totalPages = $data['totalPages'];
        $totalCount = $data['totalCount'];

        // Capture page content
        ob_start();
        require __DIR__ . '/../../templates/history.php';
        $content = ob_get_clean();

        // Render within layout
        require __DIR__ . '/../../templates/layout.php';
    }
}
