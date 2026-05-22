<?php
/**
 * Close Alert - Day-of-Month Renewal Form Template
 *
 * Displays a form for recurring_renewal alerts in day_of_month mode,
 * allowing the user to select a day (1–28) for the next occurrence
 * or end the series without renewal.
 *
 * Variables expected:
 *   $csrfToken    - string, CSRF token for form submission
 *   $alertTitle   - string, the alert's title
 *   $formToken    - string, the close token (hidden field)
 *   $formDay      - int, pre-filled day value (1–28, from alert's renewal_value)
 *   $formError    - string, validation error message (empty if none)
 *   $config       - array, application configuration
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-3">Odnowienie alertu</h2>
                <p class="text-center text-muted mb-2">
                    <strong><?= htmlspecialchars($alertTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                </p>
                <p class="text-center text-muted mb-4">
                    Aktualny dzień miesiąca: <strong><?= (int) $formDay ?></strong>
                </p>

                <?php if ($formError !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/close-alert" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="renew">

                    <div class="mb-3">
                        <label for="renewal_value" class="form-label">Dzień miesiąca</label>
                        <select class="form-select" id="renewal_value" name="renewal_value" required>
                            <?php for ($day = 1; $day <= 28; $day++): ?>
                                <option value="<?= $day ?>"<?= ((int) $formDay === $day) ? ' selected' : '' ?>><?= $day ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Ustaw nową datę</button>
                    </div>
                </form>

                <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/close-alert" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="end_series">

                    <div class="d-grid mt-2">
                        <button type="submit" class="btn btn-outline-danger">Zakończ bez ponowienia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
