<?php
/**
 * Alert Edit Form Template
 *
 * Form for editing an existing alert with fields for title, description, webhook select,
 * alert type, datetime, repeat interval, and default_next_days.
 *
 * Variables expected:
 *   $alert                  - array, the alert being edited
 *   $csrfToken              - string, CSRF token
 *   $errors                 - array, validation error messages
 *   $webhooks               - array, user's webhooks for the select dropdown
 *   $title                  - string, current/submitted title
 *   $description            - string, current/submitted description
 *   $webhook_id             - string, current/submitted webhook ID
 *   $alert_type             - string, current/submitted alert type
 *   $scheduled_at           - string, current/submitted datetime
 *   $repeat_interval_minutes - string, current/submitted repeat interval
 *   $repeat_interval_unit    - string, selected unit ("minutes" or "hours")
 *   $default_next_days      - string, current/submitted default next days
 *   $config                 - array, application configuration
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>

<div class="mb-4">
    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none">&larr; Powrót do szczegółów alertu</a>
</div>

<h1 class="mb-4">Edytuj alert</h1>

<?php if ($alert['status'] === 'closed'): ?>
<div class="alert alert-info" role="alert">
    Ten alert jest zamknięty. Zapisanie zmian spowoduje jego wznowienie.
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>&amp;action=edit">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
                <label for="title" class="form-label">Tytuł <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required placeholder="np. Przypomnienie o spotkaniu">
                <div class="form-text">Krótki tytuł alertu (1-255 znaków).</div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Opis</label>
                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Opcjonalny opis alertu"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="form-text">Opcjonalnie. Zostanie dołączony do wiadomości na Discordzie.</div>
            </div>

            <div class="mb-3">
                <label for="webhook_id" class="form-label">Webhook <span class="text-danger">*</span></label>
                <select class="form-select" id="webhook_id" name="webhook_id" required>
                    <option value="">-- Wybierz webhook --</option>
                    <?php foreach ($webhooks as $webhook): ?>
                    <option value="<?= htmlspecialchars((string) $webhook['id'], ENT_QUOTES, 'UTF-8') ?>"<?= (string) $webhook['id'] === (string) $webhook_id ? ' selected' : '' ?>><?= htmlspecialchars($webhook['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Webhook Discord, na który zostanie wysłany alert.</div>
            </div>

            <div class="mb-3">
                <label for="alert_type" class="form-label">Typ alertu <span class="text-danger">*</span></label>
                <select class="form-select" id="alert_type" name="alert_type" required>
                    <option value="one_time"<?= $alert_type === 'one_time' ? ' selected' : '' ?>>Jednorazowy</option>
                    <option value="repeat_until_closed"<?= $alert_type === 'repeat_until_closed' ? ' selected' : '' ?>>Powtarzalny do zamknięcia</option>
                    <option value="recurring_series"<?= $alert_type === 'recurring_series' ? ' selected' : '' ?>>Seria cykliczna</option>
                </select>
                <div class="form-text">
                    <strong>Jednorazowy:</strong> Wysyła raz o zaplanowanej godzinie.<br>
                    <strong>Powtarzalny do zamknięcia:</strong> Powtarza w stałym interwale aż go zamkniesz.<br>
                    <strong>Seria cykliczna:</strong> Po zamknięciu możesz zaplanować następne wystąpienie.
                </div>
            </div>

            <div class="mb-3">
                <label for="scheduled_at" class="form-label">Zaplanowana data/godzina <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at" value="<?= htmlspecialchars($scheduled_at, ENT_QUOTES, 'UTF-8') ?>" required>
                <div class="form-text">Musi być co najmniej 1 minutę w przyszłości.</div>
            </div>

            <div class="mb-3" id="repeat_interval_group">
                <label for="repeat_interval_minutes" class="form-label">Interwał powtarzania <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" class="form-control" id="repeat_interval_minutes" name="repeat_interval_minutes"
                           value="<?= htmlspecialchars((string) $repeat_interval_minutes, ENT_QUOTES, 'UTF-8') ?>"
                           min="5" max="525600" step="1" placeholder="np. 60">
                    <select class="form-select" id="repeat_interval_unit" name="repeat_interval_unit" style="max-width: 120px;">
                        <option value="minutes"<?= ($repeat_interval_unit ?? 'minutes') === 'minutes' ? ' selected' : '' ?>>minuty</option>
                        <option value="hours"<?= ($repeat_interval_unit ?? 'minutes') === 'hours' ? ' selected' : '' ?>>godziny</option>
                    </select>
                </div>
                <div class="form-text">Jak często powtarzać (5 min – 8 760 godz.). Tylko dla alertów powtarzalnych.</div>
            </div>

            <div class="mb-3" id="default_next_days_group">
                <label for="default_next_days" class="form-label">Domyślna liczba dni <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="default_next_days" name="default_next_days" value="<?= htmlspecialchars((string) $default_next_days, ENT_QUOTES, 'UTF-8') ?>" min="1" max="365" placeholder="np. 7 dla tygodniowego">
                <div class="form-text">Wstępnie wypełnia następną datę przy zamykaniu (1-365 dni). Tylko dla serii cyklicznej.</div>
            </div>

            <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
        </form>
    </div>
</div>

<script>
    // Show/hide type-specific fields based on alert type selection
    (function() {
        var alertTypeSelect = document.getElementById('alert_type');
        var repeatGroup = document.getElementById('repeat_interval_group');
        var nextDaysGroup = document.getElementById('default_next_days_group');

        function toggleFields() {
            var type = alertTypeSelect.value;
            repeatGroup.style.display = (type === 'repeat_until_closed') ? 'block' : 'none';
            nextDaysGroup.style.display = (type === 'recurring_series') ? 'block' : 'none';
        }

        alertTypeSelect.addEventListener('change', toggleFields);
        toggleFields(); // Run on page load
    })();

    // Toggle interval input constraints based on unit selection
    (function() {
        var unitSelect = document.getElementById('repeat_interval_unit');
        var intervalInput = document.getElementById('repeat_interval_minutes');

        function updateConstraints() {
            if (unitSelect.value === 'hours') {
                intervalInput.setAttribute('min', '0.084');
                intervalInput.setAttribute('max', '8760');
                intervalInput.setAttribute('step', 'any');
                intervalInput.setAttribute('placeholder', 'np. 1 dla co godzinę');
            } else {
                intervalInput.setAttribute('min', '5');
                intervalInput.setAttribute('max', '525600');
                intervalInput.setAttribute('step', '1');
                intervalInput.setAttribute('placeholder', 'np. 60 dla co godzinę');
            }
        }

        unitSelect.addEventListener('change', updateConstraints);
        updateConstraints(); // Apply on page load
    })();
</script>
