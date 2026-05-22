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
 * Alerts Create Handler
 *
 * Handles GET (show create form) and POST (create alert).
 * Supports three alert types: one_time, repeat_until_closed, recurring_series.
 * Validates all inputs per alert type and verifies webhook ownership.
 */
class AlertsCreateHandler
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
     * Display the create alert form.
     */
    private function handleGet(array $overrides = []): void
    {
        $auth = new AuthMiddleware($this->config);
        $csrf = new CsrfMiddleware();
        $webhookModel = new Webhook($this->pdo);

        $userId = $auth->getUserId();
        $webhooks = $webhookModel->findByUser($userId);

        $this->render(array_merge([
            'csrfToken' => $csrf->getToken(),
            'errors' => [],
            'webhooks' => $webhooks,
            'title' => '',
            'description' => '',
            'webhook_id' => '',
            'alert_type' => 'one_time',
            'scheduled_at' => '',
            'repeat_interval_minutes' => '',
            'repeat_interval_unit' => 'minutes',
            'default_next_days' => '',
            'renewal_mode' => '',
            'renewal_value' => '',
            'count_from_close_date' => true,
        ], $overrides));
    }

    /**
     * Process form submission: validate inputs and create alert.
     */
    private function handlePost(): void
    {
        $auth = new AuthMiddleware($this->config);
        $csrf = new CsrfMiddleware();
        $webhookModel = new Webhook($this->pdo);
        $alertModel = new Alert($this->pdo);
        $validator = new Validator();

        $userId = $auth->getUserId();
        $webhooks = $webhookModel->findByUser($userId);

        // Collect input
        $title = Validator::sanitizeString($_POST['title'] ?? '', 255);
        $description = Validator::sanitizeString($_POST['description'] ?? '');
        $webhookId = $_POST['webhook_id'] ?? '';
        $alertType = $_POST['alert_type'] ?? '';
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');
        $repeatIntervalMinutes = $_POST['repeat_interval_minutes'] ?? '';
        $repeatIntervalUnit = $_POST['repeat_interval_unit'] ?? 'minutes';
        $defaultNextDays = $_POST['default_next_days'] ?? '';
        $renewalMode = $_POST['renewal_mode'] ?? '';
        $renewalValue = $_POST['renewal_value'] ?? '';
        $countFromCloseDate = !isset($_POST['count_from_close_date']) || $_POST['count_from_close_date'] === '1';

        $errors = [];

        // Validate title
        $titleResult = $validator->validateAlertTitle($title);
        if (!$titleResult['valid']) {
            $errors = array_merge($errors, $titleResult['errors']);
        }

        // Validate alert type
        $validTypes = ['one_time', 'repeat_until_closed', 'recurring_series', 'recurring_renewal'];
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

        // Validate scheduled datetime
        if ($scheduledAt === '') {
            $errors[] = 'Scheduled date/time is required.';
        } else {
            $dateResult = $validator->validateFutureDateTime($scheduledAt);
            if (!$dateResult['valid']) {
                $errors = array_merge($errors, $dateResult['errors']);
            }
        }

        // Type-specific validation
        $convertedMinutes = null;
        if ($alertType === 'repeat_until_closed') {
            if ($repeatIntervalMinutes === '') {
                $errors[] = 'Repeat interval is required for repeat-until-closed alerts.';
            } else {
                $converted = IntervalConverter::toMinutes($repeatIntervalMinutes, $repeatIntervalUnit);
                if (!$converted['valid']) {
                    $errors = array_merge($errors, $converted['errors']);
                } else {
                    $convertedMinutes = $converted['minutes'];
                    $intervalResult = $validator->validateRepeatInterval($convertedMinutes);
                    if (!$intervalResult['valid']) {
                        $errors = array_merge($errors, $intervalResult['errors']);
                    }
                }
            }
        }

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

        if ($alertType === 'recurring_renewal') {
            // Validate renewal mode and value
            if ($renewalMode === '') {
                $errors[] = 'Please select a renewal mode.';
            } else {
                $renewalResult = $validator->validateRenewalValue($renewalMode, $renewalValue);
                if (!$renewalResult['valid']) {
                    $errors = array_merge($errors, $renewalResult['errors']);
                }
            }

            // Validate repeat interval (same as repeat_until_closed)
            if ($repeatIntervalMinutes === '') {
                $errors[] = 'Repeat interval is required for recurring renewal alerts.';
            } else {
                $converted = IntervalConverter::toMinutes($repeatIntervalMinutes, $repeatIntervalUnit);
                if (!$converted['valid']) {
                    $errors = array_merge($errors, $converted['errors']);
                } else {
                    $convertedMinutes = $converted['minutes'];
                    $intervalResult = $validator->validateRepeatInterval($convertedMinutes);
                    if (!$intervalResult['valid']) {
                        $errors = array_merge($errors, $intervalResult['errors']);
                    }
                }
            }
        }

        // If there are validation errors, re-render form
        if (!empty($errors)) {
            $this->render([
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
                'renewal_mode' => $renewalMode,
                'renewal_value' => $renewalValue,
                'count_from_close_date' => $countFromCloseDate,
            ]);
            return;
        }

        // Create the alert
        $alertData = [
            'user_id' => $userId,
            'webhook_id' => (int) $webhookId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'alert_type' => $alertType,
            'next_run_at' => date('Y-m-d H:i:s', strtotime($scheduledAt)),
            'repeat_interval_minutes' => $alertType === 'repeat_until_closed' || $alertType === 'recurring_renewal' ? $convertedMinutes : null,
            'default_next_days' => $alertType === 'recurring_series' ? (int) $defaultNextDays : null,
            'renewal_mode' => $alertType === 'recurring_renewal' ? $renewalMode : null,
            'renewal_value' => $alertType === 'recurring_renewal' ? (int) $renewalValue : null,
            'count_from_close_date' => $alertType === 'recurring_renewal' ? $countFromCloseDate : true,
        ];

        $alertModel->create($alertData);

        // Redirect to alerts list on success
        $this->redirect('/alerts');
    }

    /**
     * Render the create alert template within the layout.
     *
     * @param array $data Template variables
     */
    private function render(array $data): void
    {
        $pageTitle = 'Create Alert';
        $config = $this->config;

        // Extract template variables
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
        $renewal_mode = $data['renewal_mode'] ?? '';
        $renewal_value = $data['renewal_value'] ?? '';
        $count_from_close_date = $data['count_from_close_date'] ?? true;

        // Capture page content
        ob_start();
        require __DIR__ . '/../../templates/alerts/create.php';
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
