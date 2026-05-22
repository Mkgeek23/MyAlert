<?php
/**
 * Close Alert - Next Date Form Template
 *
 * Displays a form for recurring series alerts allowing the user to
 * set the next occurrence date or end the series.
 *
 * Variables expected:
 *   $csrfToken  - string, CSRF token for form submission
 *   $alertTitle - string, the alert's title
 *   $formToken  - string, the close token (hidden field)
 *   $formDate   - string, pre-filled date (Y-m-d format)
 *   $formError  - string, validation error message (empty if none)
 *   $config     - array, application configuration
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-3">Ustaw następną datę</h2>
                <p class="text-center text-muted mb-4">
                    Zaplanuj następne wystąpienie alertu
                    <strong><?= htmlspecialchars($alertTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                    lub zakończ serię.
                </p>

                <?php if ($formError !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/close-alert" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="next_date" class="form-label">Następna data alertu</label>
                        <input
                            type="date"
                            class="form-control"
                            id="next_date"
                            name="next_date"
                            value="<?= htmlspecialchars($formDate, ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="action" value="set_next_date" class="btn btn-primary">Ustaw następną datę</button>
                        <button type="submit" name="action" value="end_series" class="btn btn-outline-danger">Zakończ serię</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
