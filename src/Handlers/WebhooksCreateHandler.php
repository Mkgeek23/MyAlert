<?php

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Middleware\AuthMiddleware;
use MyAlert\Middleware\CsrfMiddleware;
use MyAlert\Models\Webhook;
use MyAlert\Services\DiscordService;
use MyAlert\Services\LogService;
use MyAlert\Validation\Validator;
use PDO;
use RuntimeException;

/**
 * Webhooks Create Handler
 *
 * Handles GET (show create form) and POST (create webhook or test webhook).
 * Validates URL pattern, name uniqueness, and 25 webhook limit.
 */
class WebhooksCreateHandler
{
    private array $config;
    private PDO $pdo;

    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Main request handler. Routes to GET or POST logic.
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
     * Display the create webhook form.
     */
    private function handleGet(array $overrides = []): void
    {
        $csrf = new CsrfMiddleware();

        $this->render(array_merge([
            'csrfToken' => $csrf->getToken(),
            'error' => '',
            'success' => '',
            'name' => '',
            'url' => '',
        ], $overrides));
    }

    /**
     * Process form submission: create webhook or test webhook.
     */
    private function handlePost(): void
    {
        $action = $_POST['action'] ?? 'create';

        if ($action === 'test') {
            $this->handleTest();
        } else {
            $this->handleCreate();
        }
    }

    /**
     * Handle webhook creation.
     *
     * Validates name (1-100 chars), URL pattern, name uniqueness, and 25 limit.
     */
    private function handleCreate(): void
    {
        $auth = new AuthMiddleware($this->config);
        $csrf = new CsrfMiddleware();
        $webhookModel = new Webhook($this->pdo);
        $validator = new Validator();

        $userId = $auth->getUserId();
        $name = Validator::sanitizeString($_POST['name'] ?? '', 100);
        $url = trim($_POST['url'] ?? '');

        // Validate name length (1-100 characters)
        if ($name === '' || mb_strlen($name, 'UTF-8') < 1) {
            $this->render([
                'csrfToken' => $csrf->getToken(),
                'error' => 'Webhook name is required.',
                'success' => '',
                'name' => $name,
                'url' => $url,
            ]);
            return;
        }

        if (mb_strlen($name, 'UTF-8') > 100) {
            $this->render([
                'csrfToken' => $csrf->getToken(),
                'error' => 'Webhook name must not exceed 100 characters.',
                'success' => '',
                'name' => $name,
                'url' => $url,
            ]);
            return;
        }

        // Validate URL pattern
        if (!$validator->validateWebhookUrl($url)) {
            $this->render([
                'csrfToken' => $csrf->getToken(),
                'error' => 'URL does not match the expected Discord webhook format (https://discord.com/api/webhooks/{id}/{token}).',
                'success' => '',
                'name' => $name,
                'url' => $url,
            ]);
            return;
        }

        // Check name uniqueness
        if (!$webhookModel->isNameUnique($userId, $name)) {
            $this->render([
                'csrfToken' => $csrf->getToken(),
                'error' => 'A webhook with this name already exists. Please choose a different name.',
                'success' => '',
                'name' => $name,
                'url' => $url,
            ]);
            return;
        }

        // Check 25 webhook limit
        if ($webhookModel->countByUser($userId) >= 25) {
            $this->render([
                'csrfToken' => $csrf->getToken(),
                'error' => 'Maximum webhook limit of 25 reached. Please delete an existing webhook before adding a new one.',
                'success' => '',
                'name' => $name,
                'url' => $url,
            ]);
            return;
        }

        // Create the webhook
        try {
            $webhookModel->create($userId, $name, $url);
        } catch (RuntimeException $e) {
            $this->render([
                'csrfToken' => $csrf->getToken(),
                'error' => $e->getMessage(),
                'success' => '',
                'name' => $name,
                'url' => $url,
            ]);
            return;
        }

        // Redirect to webhooks list on success
        $this->redirect('/webhooks');
    }

    /**
     * Handle webhook test.
     *
     * Sends a test message to the provided URL and displays the result.
     */
    private function handleTest(): void
    {
        $csrf = new CsrfMiddleware();
        $validator = new Validator();

        $name = Validator::sanitizeString($_POST['name'] ?? '', 100);
        $url = trim($_POST['url'] ?? '');

        // Validate URL pattern before testing
        if (!$validator->validateWebhookUrl($url)) {
            $this->render([
                'csrfToken' => $csrf->getToken(),
                'error' => 'URL does not match the expected Discord webhook format (https://discord.com/api/webhooks/{id}/{token}).',
                'success' => '',
                'name' => $name,
                'url' => $url,
            ]);
            return;
        }

        // Send test message
        $logger = new LogService();
        $discordService = new DiscordService($logger);
        $result = $discordService->sendTestMessage($url);

        if ($result['success']) {
            $this->render([
                'csrfToken' => $csrf->getToken(),
                'error' => '',
                'success' => 'Test message sent successfully! Check your Discord channel.',
                'name' => $name,
                'url' => $url,
            ]);
        } else {
            $errorMessage = 'Test failed: ';
            if ($result['error'] === 'Connection timeout') {
                $errorMessage .= 'Connection timed out. Please verify the webhook URL is correct.';
            } elseif ($result['status_code'] > 0) {
                $errorMessage .= "Discord returned HTTP {$result['status_code']}. Please verify the webhook URL is correct and active.";
            } else {
                $errorMessage .= 'Could not connect to Discord. Please check the URL and try again.';
            }

            $this->render([
                'csrfToken' => $csrf->getToken(),
                'error' => $errorMessage,
                'success' => '',
                'name' => $name,
                'url' => $url,
            ]);
        }
    }

    /**
     * Render the create webhook template within the layout.
     *
     * @param array $data Template variables
     */
    private function render(array $data): void
    {
        $pageTitle = 'Add Webhook';
        $config = $this->config;

        // Extract template variables
        $csrfToken = $data['csrfToken'];
        $error = $data['error'];
        $success = $data['success'];
        $name = $data['name'];
        $url = $data['url'];

        // Capture page content
        ob_start();
        require __DIR__ . '/../../templates/webhooks/create.php';
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
