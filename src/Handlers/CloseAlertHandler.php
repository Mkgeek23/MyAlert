<?php

declare(strict_types=1);

namespace MyAlert\Handlers;

use MyAlert\Middleware\CsrfMiddleware;
use MyAlert\Models\Alert;
use MyAlert\Models\AlertToken;
use MyAlert\Services\RenewalDateCalculator;
use MyAlert\Validation\Validator;
use PDO;

/**
 * Close Alert Handler
 *
 * Handles GET (token validation + display) and POST (close action for recurring series).
 * No authentication required — access is controlled via cryptographic close tokens.
 *
 * GET flow:
 *   1. Validate token format (64 hex chars)
 *   2. Look up token in database
 *   3. Check token not used, not expired
 *   4. Look up associated alert
 *   5. Handle already-closed alerts gracefully
 *   6. Route by alert type:
 *      - one_time / repeat_until_closed → close directly, show success
 *      - recurring_series → show next date form
 *
 * POST flow (recurring series):
 *   1. CSRF validated (by global middleware)
 *   2. Validate token, look up alert
 *   3. Handle action: 'set_next_date' or 'end_series'
 */
class CloseAlertHandler
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
     * Handle GET request: validate token and display appropriate page.
     */
    private function handleGet(): void
    {
        $token = $_GET['token'] ?? '';
        $validator = new Validator();

        // Step 1: Validate token format (64 hex chars)
        if ($token === '' || !$validator->validateCloseToken($token)) {
            $this->show404();
            return;
        }

        // Step 2: Look up token in database
        $alertTokenModel = new AlertToken($this->pdo);
        $tokenRecord = $alertTokenModel->findByToken($token);

        if ($tokenRecord === null) {
            $this->show404();
            return;
        }

        // Step 3: Check if token is already used
        if (!empty($tokenRecord['used'])) {
            $this->renderError('This close link has already been used.', 'Token Already Used');
            return;
        }

        // Step 4: Check if token is expired
        $expiresAt = strtotime($tokenRecord['expires_at']);
        if ($expiresAt === false || time() >= $expiresAt) {
            $this->renderExpired();
            return;
        }

        // Step 5: Look up the associated alert
        $alertModel = new Alert($this->pdo);
        $alert = $alertModel->findById((int) $tokenRecord['alert_id']);

        if ($alert === null) {
            $this->show404();
            return;
        }

        // Step 6: Handle already-closed alerts gracefully
        if ($alert['status'] === 'closed' || !empty($alert['series_ended'])) {
            $alertTokenModel->markUsed((int) $tokenRecord['id']);
            $this->renderSuccess('This alert has already been closed.', 'Already Closed');
            return;
        }

        // Step 7: Route by alert type
        if ($alert['alert_type'] === 'one_time' || $alert['alert_type'] === 'repeat_until_closed') {
            // Close directly
            $alertModel->updateStatus((int) $alert['id'], 'closed');
            $alertTokenModel->markUsed((int) $tokenRecord['id']);
            $this->renderSuccess(
                'The alert "' . htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8') . '" has been closed successfully.',
                'Alert Closed'
            );
            return;
        }

        // recurring_renewal: show mode-specific renewal form
        if ($alert['alert_type'] === 'recurring_renewal') {
            $renewalMode = $alert['renewal_mode'] ?? 'number_of_days';
            $renewalValue = (int) ($alert['renewal_value'] ?? 1);

            if ($renewalMode === 'day_of_month') {
                $this->renderRenewalDayForm($token, $alert, $renewalValue);
            } else {
                $this->renderRenewalDaysForm($token, $alert, $renewalValue);
            }
            return;
        }

        // recurring_series: show next date form
        $defaultNextDays = (int) ($alert['default_next_days'] ?? 7);
        $prefilledDate = date('Y-m-d', strtotime("+{$defaultNextDays} days"));

        $this->renderNextDateForm($token, $alert, $prefilledDate);
    }

    /**
     * Handle POST request: process recurring series close action.
     */
    private function handlePost(): void
    {
        $token = $_POST['token'] ?? '';
        $action = $_POST['action'] ?? '';
        $validator = new Validator();

        // Validate token format
        if ($token === '' || !$validator->validateCloseToken($token)) {
            $this->show404();
            return;
        }

        // Look up token
        $alertTokenModel = new AlertToken($this->pdo);
        $tokenRecord = $alertTokenModel->findByToken($token);

        if ($tokenRecord === null) {
            $this->show404();
            return;
        }

        // Check if token is already used
        if (!empty($tokenRecord['used'])) {
            $this->renderError('This close link has already been used.', 'Token Already Used');
            return;
        }

        // Check if token is expired
        $expiresAt = strtotime($tokenRecord['expires_at']);
        if ($expiresAt === false || time() >= $expiresAt) {
            $this->renderExpired();
            return;
        }

        // Look up the associated alert
        $alertModel = new Alert($this->pdo);
        $alert = $alertModel->findById((int) $tokenRecord['alert_id']);

        if ($alert === null) {
            $this->show404();
            return;
        }

        // Handle already-closed alerts
        if ($alert['status'] === 'closed' || !empty($alert['series_ended'])) {
            $alertTokenModel->markUsed((int) $tokenRecord['id']);
            $this->renderSuccess('This alert has already been closed.', 'Already Closed');
            return;
        }

        // Process action
        if ($alert['alert_type'] === 'recurring_renewal' && $action === 'renew') {
            $this->handleRenewAction($token, $alert, $alertModel, $alertTokenModel, $tokenRecord, $validator);
            return;
        }

        if ($alert['alert_type'] === 'recurring_renewal' && $action === 'end_series') {
            $alertModel->endSeries((int) $alert['id']);
            $alertTokenModel->markUsed((int) $tokenRecord['id']);
            $this->renderSuccess(
                'Seria alertu "' . htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8') . '" została trwale zakończona.',
                'Seria zakończona'
            );
            return;
        }

        if ($action === 'end_series') {
            $alertModel->endSeries((int) $alert['id']);
            $alertTokenModel->markUsed((int) $tokenRecord['id']);
            $this->renderSuccess(
                'The recurring series "' . htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8') . '" has been ended.',
                'Series Ended'
            );
            return;
        }

        if ($action === 'set_next_date') {
            $nextDate = $_POST['next_date'] ?? '';

            // Validate the next date is in the future
            $nextDateResult = $validator->validateFutureDateTime($nextDate . ' 00:00:00');

            if (!$nextDateResult['valid']) {
                // Re-render form with error
                $defaultNextDays = (int) ($alert['default_next_days'] ?? 7);
                $this->renderNextDateForm($token, $alert, $nextDate, 'The next date must be in the future.');
                return;
            }

            // Set next_run_at to the submitted date (start of day)
            $alertModel->updateNextRun((int) $alert['id'], $nextDate . ' 00:00:00');
            $alertModel->updateStatus((int) $alert['id'], 'active');
            $alertTokenModel->markUsed((int) $tokenRecord['id']);

            $this->renderSuccess(
                'The next occurrence of "' . htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8')
                . '" has been scheduled for ' . htmlspecialchars($nextDate, ENT_QUOTES, 'UTF-8') . '.',
                'Next Date Set'
            );
            return;
        }

        // Invalid action - show form again
        $defaultNextDays = (int) ($alert['default_next_days'] ?? 7);
        $prefilledDate = date('Y-m-d', strtotime("+{$defaultNextDays} days"));
        $this->renderNextDateForm($token, $alert, $prefilledDate, 'Invalid action.');
    }

    /**
     * Render the success page.
     */
    private function renderSuccess(string $message, string $title = 'Success'): void
    {
        $pageTitle = $title;
        $config = $this->config;
        $successMessage = $message;
        $successTitle = $title;

        ob_start();
        require __DIR__ . '/../../templates/close-alert/success.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Render the next date form for recurring series.
     */
    private function renderNextDateForm(string $token, array $alert, string $prefilledDate, string $error = ''): void
    {
        $pageTitle = 'Set Next Date';
        $config = $this->config;
        $csrf = new CsrfMiddleware();
        $csrfToken = $csrf->getToken();
        $alertTitle = $alert['title'];
        $formToken = $token;
        $formDate = $prefilledDate;
        $formError = $error;

        ob_start();
        require __DIR__ . '/../../templates/close-alert/next-date.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Render the expired token page.
     */
    private function renderExpired(): void
    {
        $pageTitle = 'Token Expired';
        $config = $this->config;

        ob_start();
        require __DIR__ . '/../../templates/close-alert/expired.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Render a generic error page.
     */
    private function renderError(string $message, string $title = 'Error'): void
    {
        $pageTitle = $title;
        $config = $this->config;
        $errorMessage = $message;
        $errorTitle = $title;

        ob_start();
        require __DIR__ . '/../../templates/close-alert/error.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Handle the 'renew' action for recurring_renewal alerts.
     * Validates the submitted renewal value, computes the next date, and updates the alert.
     */
    private function handleRenewAction(
        string $token,
        array $alert,
        Alert $alertModel,
        AlertToken $alertTokenModel,
        array $tokenRecord,
        Validator $validator
    ): void {
        $renewalMode = $alert['renewal_mode'] ?? 'number_of_days';
        $submittedValue = $_POST['renewal_value'] ?? '';

        // Validate the submitted renewal value
        $validationResult = $validator->validateRenewalValue($renewalMode, $submittedValue);

        if (!$validationResult['valid']) {
            $errorMessage = $validationResult['errors'][0] ?? 'Invalid value.';

            if ($renewalMode === 'day_of_month') {
                $this->renderRenewalDayForm($token, $alert, (int) $submittedValue, $errorMessage);
            } else {
                $this->renderRenewalDaysForm($token, $alert, (int) $submittedValue, $errorMessage);
            }
            return;
        }

        $renewalValue = (int) $submittedValue;

        // Determine reference date based on count_from_close_date flag
        $countFromCloseDate = !empty($alert['count_from_close_date']);

        if ($countFromCloseDate) {
            $referenceDate = new \DateTime();
        } else {
            $referenceDate = new \DateTime($alert['next_run_at']);
        }

        // Calculate the next date using RenewalDateCalculator
        $calculator = new RenewalDateCalculator();
        $now = new \DateTime();

        if ($renewalMode === 'day_of_month') {
            $nextDate = $calculator->calculateDayOfMonth($renewalValue, $referenceDate, $now);
        } else {
            $nextDate = $calculator->calculateNumberOfDays($renewalValue, $referenceDate, $now);
        }

        // Update the alert: set next_run_at and status to active
        $nextRunAtFormatted = $nextDate->format('Y-m-d H:i:s');
        $alertModel->update((int) $alert['id'], [
            'next_run_at' => $nextRunAtFormatted,
            'status' => 'active',
        ]);

        // Mark token as used
        $alertTokenModel->markUsed((int) $tokenRecord['id']);

        // Render success page with the computed next date
        $displayDate = $nextDate->format('d.m.Y');
        $this->renderSuccess(
            'Następne wystąpienie alertu "' . htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8')
            . '" zostało zaplanowane na ' . $displayDate . '.',
            'Nowa data ustawiona'
        );
    }

    /**
     * Render the day-of-month renewal form for recurring_renewal alerts.
     */
    private function renderRenewalDayForm(string $token, array $alert, int $dayValue, string $error = ''): void
    {
        $pageTitle = 'Odnowienie alertu';
        $config = $this->config;
        $csrf = new CsrfMiddleware();
        $csrfToken = $csrf->getToken();
        $alertTitle = $alert['title'];
        $formToken = $token;
        $formDay = $dayValue;
        $formError = $error;

        ob_start();
        require __DIR__ . '/../../templates/close-alert/renewal-day.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Render the number-of-days renewal form for recurring_renewal alerts.
     */
    private function renderRenewalDaysForm(string $token, array $alert, int $daysValue, string $error = ''): void
    {
        $pageTitle = 'Odnów alert';
        $config = $this->config;
        $csrf = new CsrfMiddleware();
        $csrfToken = $csrf->getToken();
        $alertTitle = $alert['title'];
        $formToken = $token;
        $formValue = $daysValue;
        $formError = $error;

        ob_start();
        require __DIR__ . '/../../templates/close-alert/renewal-days.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Show the 404 error page.
     */
    private function show404(): void
    {
        http_response_code(404);
        $templatePath = __DIR__ . '/../../templates/errors/404.php';
        if (file_exists($templatePath)) {
            require $templatePath;
        } else {
            echo 'Page not found.';
        }
    }
}
