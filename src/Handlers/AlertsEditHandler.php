<?php

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Middleware\AuthMiddleware;
use MyAlert\Middleware\CsrfMiddleware;
use MyAlert\Models\Alert;
use MyAlert\Models\Webhook;
use MyAlert\Validation\IntervalConverter;
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

        // Handle edit POST submission
        if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleEditPost($alert);
            return;
        }

        // Handle edit GET (display edit form)
        if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->handleEditGet($alert);
            return;
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
     * Render the edit form template within the layout.
     *
     * @param array $data Template variables for the edit form
     */
    private function renderEditForm(array $data): void
    {
        $pageTitle = 'Edit Alert';
        $config = $this->config;

        // Extract template variables
        $alert = $data['alert'];
        $csrfToken = $data['csrfToken'];
        $errors = $data['errors'];
        $webhooks = $data['webhooks'];
        $title = $data['title'];
        $description = $data['description'];
        $webhook_id = $data['webhook_id'];
        $alert_type = $data['alert_type'];
        $scheduled_at = $data['scheduled_at'];
        $repeat_interval_minutes = $data['repeat_interval_minutes'];
        $repeat_interval_unit = $data['repeat_interval_unit'] ?? 'minutes';
        $default_next_days = $data['default_next_days'];

        // Capture page content
        ob_start();
        require __DIR__ . '/../../templates/alerts/edit-form.php';
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
     * Display the edit form pre-populated with current alert values.
     * Called when action=edit and method=GET.
     *
     * @param array $alert The alert record to edit
     */
    private function handleEditGet(array $alert): void
    {
        $webhookModel = new Webhook($this->pdo);
        $auth = new AuthMiddleware($this->config);
        $csrf = new CsrfMiddleware();
        $userId = $auth->getUserId();

        $webhooks = $webhookModel->findByUser($userId);

        // Auto-detect best display unit for the stored interval value
        $repeatIntervalMinutes = (string) ($alert['repeat_interval_minutes'] ?? '');
        $repeatIntervalUnit = 'minutes';

        if ($repeatIntervalMinutes !== '' && $repeatIntervalMinutes !== '0') {
            $display = IntervalConverter::toDisplayUnit((int) $repeatIntervalMinutes);
            $repeatIntervalMinutes = $display['value'];
            $repeatIntervalUnit = $display['unit'];
        }

        $this->renderEditForm([
            'alert' => $alert,
            'csrfToken' => $csrf->getToken(),
            'errors' => [],
            'webhooks' => $webhooks,
            'title' => $alert['title'],
            'description' => $alert['description'] ?? '',
            'webhook_id' => (string) $alert['webhook_id'],
            'alert_type' => $alert['alert_type'],
            'scheduled_at' => date('Y-m-d\TH:i', strtotime($alert['next_run_at'])),
            'repeat_interval_minutes' => $repeatIntervalMinutes,
            'repeat_interval_unit' => $repeatIntervalUnit,
            'default_next_days' => (string) ($alert['default_next_days'] ?? ''),
        ]);
    }

    /**
     * Process edit form submission: validate inputs and update alert.
     * Called when action=edit and method=POST.
     *
     * @param array $alert The current alert data from the database
     */
    private function handleEditPost(array $alert): void
    {
        $auth = new AuthMiddleware($this->config);
        $csrf = new CsrfMiddleware();
        $webhookModel = new Webhook($this->pdo);
        $alertModel = new Alert($this->pdo);
        $validator = new Validator();

        $userId = $auth->getUserId();
        $webhooks = $webhookModel->findByUser($userId);

        // Collect and sanitize input (same pattern as AlertsCreateHandler)
        $title = Validator::sanitizeString($_POST['title'] ?? '', 255);
        $description = Validator::sanitizeString($_POST['description'] ?? '');
        $webhookId = $_POST['webhook_id'] ?? '';
        $alertType = $_POST['alert_type'] ?? '';
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');
        $repeatIntervalMinutes = $_POST['repeat_interval_minutes'] ?? '';
        $repeatIntervalUnit = $_POST['repeat_interval_unit'] ?? 'minutes';
        $defaultNextDays = $_POST['default_next_days'] ?? '';

        $errors = [];
        $convertedMinutes = null;

        // Validate title (1-255 chars)
        $titleResult = $validator->validateAlertTitle($title);
        if (!$titleResult['valid']) {
            $errors = array_merge($errors, $titleResult['errors']);
        }

        // Validate alert type enum
        $validTypes = ['one_time', 'repeat_until_closed', 'recurring_series'];
        if (!in_array($alertType, $validTypes, true)) {
            $errors[] = 'Please select a valid alert type.';
        }

        // Validate webhook selection and ownership
        if ($webhookId === '') {
            $errors[] = 'Please select a webhook.';
        } else {
            $webhookIdInt = (int) $webhookId;
            $webhook = $webhookModel->findById($webhookIdInt);
            if ($webhook === null || (int) $webhook['user_id'] !== $userId) {
                $errors[] = 'The selected webhook is invalid or does not belong to you.';
            }
        }

        // Validate scheduled datetime (>1 min future)
        if ($scheduledAt === '') {
            $errors[] = 'Scheduled date/time is required.';
        } else {
            $dateResult = $validator->validateFutureDateTime($scheduledAt);
            if (!$dateResult['valid']) {
                $errors = array_merge($errors, $dateResult['errors']);
            }
        }

        // Type-specific validation: repeat_interval_minutes (5-525600) for repeat_until_closed
        if ($alertType === 'repeat_until_closed') {
            if ($repeatIntervalMinutes === '') {
                $errors[] = 'Repeat interval is required for repeat-until-closed alerts.';
            } else {
                // Convert using IntervalConverter (handles unit conversion + rounding)
                $conversionResult = IntervalConverter::toMinutes($repeatIntervalMinutes, $repeatIntervalUnit);
                if (!$conversionResult['valid']) {
                    $errors = array_merge($errors, $conversionResult['errors']);
                } else {
                    $convertedMinutes = $conversionResult['minutes'];
                    // Validate the converted integer is within range
                    $intervalResult = $validator->validateRepeatInterval($convertedMinutes);
                    if (!$intervalResult['valid']) {
                        $errors = array_merge($errors, $intervalResult['errors']);
                    }
                }
            }
        }

        // Type-specific validation: default_next_days (1-365) for recurring_series
        if ($alertType === 'recurring_series') {
            if ($defaultNextDays === '') {
                $errors[] = 'Default next days is required for recurring series alerts.';
            } else {
                $daysInt = (int) $defaultNextDays;
                $daysResult = $validator->validateDefaultNextDays($daysInt);
                if (!$daysResult['valid']) {
                    $errors = array_merge($errors, $daysResult['errors']);
                }
            }
        }

        // On validation errors, re-render edit form with errors and preserved input
        if (!empty($errors)) {
            $this->renderEditForm([
                'alert' => $alert,
                'csrfToken' => $csrf->getToken(),
                'errors' => $errors,
                'webhooks' => $webhooks,
                'title' => $title,
                'description' => $description,
                'webhook_id' => $webhookId,
                'alert_type' => $alertType,
                'scheduled_at' => $scheduledAt,
                'repeat_interval_minutes' => $repeatIntervalMinutes,
                'repeat_interval_unit' => $repeatIntervalUnit,
                'default_next_days' => $defaultNextDays,
            ]);
            return;
        }

        // Build update data with type-specific nullification
        $updateData = [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'webhook_id' => (int) $webhookId,
            'alert_type' => $alertType,
            'next_run_at' => date('Y-m-d H:i:s', strtotime($scheduledAt)),
            'repeat_interval_minutes' => $alertType === 'repeat_until_closed' ? $convertedMinutes : null,
            'default_next_days' => $alertType === 'recurring_series' ? (int) $defaultNextDays : null,
        ];

        // Auto-reopen closed alerts
        $wasClosed = ($alert['status'] === 'closed');
        if ($wasClosed) {
            $updateData['status'] = 'active';
            if ($alert['alert_type'] === 'recurring_series' || $alertType === 'recurring_series') {
                $updateData['series_ended'] = false;
            }
        }

        $alertModel->update((int) $alert['id'], $updateData);

        // Flash message and redirect
        $_SESSION['flash_message'] = $wasClosed
            ? 'Alert updated and reopened successfully.'
            : 'Alert updated successfully.';

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
