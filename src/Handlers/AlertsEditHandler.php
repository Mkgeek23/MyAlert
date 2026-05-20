<?php

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Middleware\AuthMiddleware;
use MyAlert\Middleware\CsrfMiddleware;
use MyAlert\Models\Alert;
use MyAlert\Models\Webhook;
use MyAlert\Validation\Validator;
use PDO;

/**
 * Alerts Edit Handler
 *
 * Handles viewing alert details and closing alerts from the web UI.
 * Verifies alert ownership before displaying any data.
 *
 * GET flow:
 *   1. Get alert ID from $_GET['id']
 *   2. Look up alert via Alert::findById()
 *   3. Verify alert.user_id matches authenticated user (403 if not)
 *   4. If action=close: close non-recurring alerts directly, show message for recurring
 *   5. Display alert details with status/type badges
 */
class AlertsEditHandler
{
    private array $config;
    private PDO $pdo;

    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Main request handler.
     */
    public function handle(): void
    {
        $auth = new AuthMiddleware($this->config);
        $userId = $auth->getUserId();

        // Step 1: Get alert ID from query string
        $alertId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($alertId <= 0) {
            $this->show404();
            return;
        }

        // Step 2: Look up alert
        $alertModel = new Alert($this->pdo);
        $alert = $alertModel->findById($alertId);

        if ($alert === null) {
            $this->show404();
            return;
        }

        // Step 3: Verify ownership
        if ((int) $alert['user_id'] !== $userId) {
            $this->show403();
            return;
        }

        // Step 4: Handle actions
        $action = $_GET['action'] ?? '';
        $closeMessage = '';

        // Check for flash message from redirect (e.g., after successful reopen)
        if (isset($_SESSION['flash_message']) && $_SESSION['flash_message'] !== '') {
            $closeMessage = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
        }

        // Handle reopen POST submission (must be checked before GET)
        if ($action === 'reopen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleReopen($alert, $alertModel);
            return;
        }

        // Handle reopen action (GET shows form)
        if ($action === 'reopen' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            // Look up webhook name for the form render
            $webhookModel = new Webhook($this->pdo);
            $webhook = $webhookModel->findById((int) $alert['webhook_id']);
            $webhookName = $webhook !== null ? $webhook['name'] : 'Unknown';

            if ($alert['status'] === 'active') {
                // Alert is already active, show info message instead of form
                $this->render([
                    'alert' => $alert,
                    'webhookName' => $webhookName,
                    'closeMessage' => 'This alert is already active.',
                    'csrfToken' => (new CsrfMiddleware())->getToken(),
                ]);
                return;
            }

            $this->renderWithReopenForm($alert, $webhookName);
            return;
        }

        if ($action === 'close') {
            if ($alert['status'] === 'closed') {
                $closeMessage = 'This alert is already closed.';
            } elseif ($alert['alert_type'] === 'recurring_series') {
                $closeMessage = 'Recurring series alerts can only be closed via the close link in Discord messages.';
            } else {
                // Close one-time and repeat-until-closed alerts directly
                $alertModel->updateStatus($alertId, 'closed');
                $alert['status'] = 'closed';
                $closeMessage = 'Alert has been closed successfully.';
            }
        }

        // Step 5: Look up webhook name
        $webhookModel = new Webhook($this->pdo);
        $webhook = $webhookModel->findById((int) $alert['webhook_id']);
        $webhookName = $webhook !== null ? $webhook['name'] : 'Unknown';

        // Get CSRF token for any forms
        $csrf = new CsrfMiddleware();

        $this->render([
            'alert' => $alert,
            'webhookName' => $webhookName,
            'closeMessage' => $closeMessage,
            'csrfToken' => $csrf->getToken(),
        ]);
    }

    /**
     * Render the alert edit/view template within the layout.
     *
     * @param array $data Template variables
     */
    private function render(array $data): void
    {
        $pageTitle = 'Alert Details';
        $config = $this->config;

        // Extract template variables
        $alert = $data['alert'];
        $webhookName = $data['webhookName'];
        $closeMessage = $data['closeMessage'];
        $csrfToken = $data['csrfToken'];

        // Reopen form variables (defaults for normal render)
        $showReopenForm = false;
        $reopenError = '';
        $preservedDate = '';

        // Capture page content
        ob_start();
        require __DIR__ . '/../../templates/alerts/edit.php';
        $content = ob_get_clean();

        // Render within layout
        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Render the alert details page with the reopen date/time form visible.
     *
     * @param array  $alert         The alert data
     * @param string $webhookName   The webhook name for display
     * @param string $error         Validation error message (empty if none)
     * @param string $preservedDate Previously submitted date value to preserve in the input
     */
    private function renderWithReopenForm(array $alert, string $webhookName, string $error = '', string $preservedDate = ''): void
    {
        $pageTitle = 'Alert Details';
        $config = $this->config;

        $csrf = new CsrfMiddleware();
        $csrfToken = $csrf->getToken();
        $closeMessage = '';
        $showReopenForm = true;
        $reopenError = $error;

        // Capture page content
        ob_start();
        require __DIR__ . '/../../templates/alerts/edit.php';
        $content = ob_get_clean();

        // Render within layout
        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Handle POST submission of the reopen form.
     * Validates the next_run_at date, calls reopenAlert(), and redirects with success message.
     *
     * @param array $alert      The alert data
     * @param Alert $alertModel The Alert model instance
     */
    private function handleReopen(array $alert, Alert $alertModel): void
    {
        // Look up webhook name for potential re-render
        $webhookModel = new Webhook($this->pdo);
        $webhook = $webhookModel->findById((int) $alert['webhook_id']);
        $webhookName = $webhook !== null ? $webhook['name'] : 'Unknown';

        // Check if alert is already active
        if ($alert['status'] === 'active') {
            $this->render([
                'alert' => $alert,
                'webhookName' => $webhookName,
                'closeMessage' => 'This alert is already active.',
                'csrfToken' => (new CsrfMiddleware())->getToken(),
            ]);
            return;
        }

        // Get and validate next_run_at from POST data
        $nextRunAt = $_POST['next_run_at'] ?? '';

        $validator = new Validator();
        $result = $validator->validateFutureDateTime($nextRunAt);

        if (!$result['valid']) {
            // Re-render with error and preserved input
            $this->renderWithReopenForm($alert, $webhookName, $result['errors'][0], $nextRunAt);
            return;
        }

        // Success: reopen the alert
        $alertModel->reopenAlert((int) $alert['id'], $nextRunAt, $alert['alert_type']);

        // Set flash message and redirect to alert details page
        $_SESSION['flash_message'] = 'Alert has been reopened successfully.';

        $baseUrl = rtrim($this->config['base_path'] ?? '', '/');
        header('Location: ' . $baseUrl . '/alerts-edit?id=' . $alert['id'], true, 302);
        exit;
    }

    /**
     * Show the 404 error page.
     */
    private function show404(): void
    {
        http_response_code(404);
        $config = $this->config;
        $pageTitle = 'Not Found';

        ob_start();
        require __DIR__ . '/../../templates/errors/404.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Show the 403 error page.
     */
    private function show403(): void
    {
        http_response_code(403);
        $config = $this->config;
        $pageTitle = 'Forbidden';

        ob_start();
        require __DIR__ . '/../../templates/errors/403.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }
}
