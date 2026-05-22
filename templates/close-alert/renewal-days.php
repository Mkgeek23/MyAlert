<?php
/**
 * Close Alert - Renewal Days Form Template
 *
 * Displays a form for recurring_renewal alerts in "number_of_days" mode,
 * allowing the user to set the number of days until the next occurrence
 * or end the series without renewal.
 *
 * Variables expected:
 *   $csrfToken  - string, CSRF token for form submission
 *   $alertTitle - string, the alert's title
 *   $formToken  - string, the close token (hidden field)
 *   $formValue  - int, pre-filled renewal value (number of days)
 *   $formError  - string, validation error message (empty if none)
 *   $config     - array, application configuration
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-3">Odnów alert</h2>
                <p class="text-center text-muted mb-2">
                    <strong><?= htmlspecialchars($alertTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                </p>
                <p class="text-center text-muted mb-4">
                    Aktualna liczba dni: <?= (int) $formValue ?>
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
                        <label for="renewal_value" class="form-label">Liczba dni do następnego alertu</label>
                        <input
                            type="number"
                            class="form-control"
                            id="renewal_value"
                            name="renewal_value"
                            value="<?= (int) $formValue ?>"
                            min="1"
                            max="365"
                            required
                        >
                        <div class="form-text">Wartość od 1 do 365 dni.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Podgląd następnej daty</label>
                        <div class="form-control bg-light" id="date-preview" aria-live="polite">—</div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Ustaw nową datę</button>
                    </div>
                </form>

                <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/close-alert" class="mt-2" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="end_series">

                    <div class="d-grid">
                        <button type="submit" class="btn btn-outline-danger">Zakończ bez ponowienia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var input = document.getElementById('renewal_value');
    var preview = document.getElementById('date-preview');

    function updatePreview() {
        var days = parseInt(input.value, 10);
        if (isNaN(days) || days < 1 || days > 365) {
            preview.textContent = '—';
            return;
        }
        var date = new Date();
        date.setDate(date.getDate() + days);
        var dd = String(date.getDate()).padStart(2, '0');
        var mm = String(date.getMonth() + 1).padStart(2, '0');
        var yyyy = date.getFullYear();
        preview.textContent = dd + '.' + mm + '.' + yyyy;
    }

    input.addEventListener('input', updatePreview);
    updatePreview();
})();
</script>
