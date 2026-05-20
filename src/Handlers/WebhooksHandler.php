<?php

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Middleware\AuthMiddleware;
use MyAlert\Middleware\CsrfMiddleware;
use MyAlert\Models\Webhook;
use PDO;

/**
 * Webhooks Handler
 *
 * Handles GET (list user's webhooks) and POST (delete a webhook).
 * Enforces the active alert check before allowing deletion.
 */
class WebhooksHandler
{
    private array $config;
    private PDO $pdo;

    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Main request handler. Routes to GET (list) or POST (delete).
     */
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        } else {
            $this->handleGet();
        }
    }

    /**
     * Display the list of user's webhooks.
     */
    private function handleGet(string $success = '', string $error = ''): void
    {
        $auth = new AuthMiddleware($this->config);
        $csrf = new CsrfMiddleware();
        $webhookModel = new Webhook($this->pdo);

        $userId = $auth->getUserId();
        $webhooks = $webhookModel->findByUser($userId);

        $this->render([
            'webhooks' => $webhooks,
            'csrfToken' => $csrf->getToken(),
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * Process webhook deletion.
     *
     * Validates ownership and checks for active alerts before deleting.
     */
    private function handlePost(): void
    {
        $auth = new AuthMiddleware($this->config);
        $csrf = new CsrfMiddleware();
        $webhookModel = new Webhook($this->pdo);

        $userId = $auth->getUserId();
        $webhookId = (int) ($_POST['webhook_id'] ?? 0);

        // Verify webhook exists and belongs to user
        $webhook = $webhookModel->findById($webhookId);

        if ($webhook === null || (int) $webhook['user_id'] !== $userId) {
            $this->handleGet('', 'Webhook not found.');
            return;
        }

        // Check for active alerts referencing this webhook
        if ($webhookModel->hasActiveAlerts($webhookId)) {
            $this->handleGet('', 'Cannot delete this webhook because it is referenced by one or more active alerts. Please close or reassign those alerts first.');
            return;
        }

        // Delete the webhook
        $webhookModel->delete($webhookId);

        $this->handleGet('Webhook deleted successfully.', '');
    }

    /**
     * Render the webhooks list template within the layout.
     *
     * @param array $data Template variables
     */
    private function render(array $data): void
    {
        $pageTitle = 'Webhooks';
        $config = $this->config;

        // Extract template variables
        $webhooks = $data['webhooks'];
        $csrfToken = $data['csrfToken'];
        $success = $data['success'];
        $error = $data['error'];

        // Capture page content
        ob_start();
        require __DIR__ . '/../../templates/webhooks/list.php';
        $content = ob_get_clean();

        // Render within layout
        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Redirect to a relative URL using the configured base_url.
     */
    private function redirect(string $path): void
    {
        $baseUrl = rtrim($this->config['base_path'] ?? '', '/');
        header('Location: ' . $baseUrl . $path, true, 302);
        exit;
    }
}
