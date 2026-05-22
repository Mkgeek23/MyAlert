<?php
/**
 * Alerts Create Template
 *
 * Form for creating a new alert with fields for title, description, webhook select,
 * alert type, datetime, repeat interval, default_next_days, and renewal configuration.
 *
 * Variables expected:
 *   $csrfToken              - string, CSRF token
 *   $errors                 - array, validation error messages
 *   $webhooks               - array, user's webhooks for the select dropdown
 *   $title                  - string, previously submitted title (for form repopulation)
 *   $description            - string, previously submitted description
 *   $webhook_id             - string, previously selected webhook ID
 *   $alert_type             - string, previously selected alert type
 *   $scheduled_at           - string, previously submitted datetime
 *   $repeat_interval_minutes - string, previously submitted repeat interval
 *   $repeat_interval_unit   - string, previously selected unit ("minutes" or "hours")
 *   $default_next_days      - string, previously submitted default next days
 *   $renewal_mode           - string, previously selected renewal mode ("day_of_month" or "number_of_days")
 *   $renewal_value          - string, previously submitted renewal value
 *   $count_from_close_date  - bool, whether to count from close date (default true)
 *   $config                 - array, application configuration (for base_url)
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>

<div class="mb-4">
    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts" class="text-decoration-none">&larr; Powrót do alertów</a>
</div>

<h1 class="mb-4">Utwórz alert</h1>

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
        <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-create">
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
                    <option value="recurring_renewal"<?= $alert_type === 'recurring_renewal' ? ' selected' : '' ?>>Cykliczny z odnowieniem</option>
                </select>
                <div class="form-text">
                    <strong>Jednorazowy:</strong> Wysyła raz o zaplanowanej godzinie.<br>
                    <strong>Powtarzalny do zamknięcia:</strong> Powtarza w stałym interwale aż go zamkniesz.<br>
                    <strong>Seria cykliczna:</strong> Po zamknięciu możesz zaplanować następne wystąpienie.<br>
                    <strong>Cykliczny z odnowieniem:</strong> Po zamknięciu automatycznie oblicza następną datę na podstawie trybu odnowienia.
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
                <div class="form-text">Jak często powtarzać (5 min – 8 760 godz.). Dla alertów powtarzalnych i cyklicznych z odnowieniem.</div>
            </div>

            <div class="mb-3" id="default_next_days_group">
                <label for="default_next_days" class="form-label">Domyślna liczba dni <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="default_next_days" name="default_next_days" value="<?= htmlspecialchars((string) $default_next_days, ENT_QUOTES, 'UTF-8') ?>" min="1" max="365" placeholder="np. 7 dla tygodniowego">
                <div class="form-text">Wstępnie wypełnia następną datę przy zamykaniu (1-365 dni). Tylko dla serii cyklicznej.</div>
            </div>

            <div id="renewal_fields_group">
                <div class="mb-3">
                    <label class="form-label">Tryb odnowienia <span class="text-danger">*</span></label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="renewal_mode" id="renewal_mode_day" value="day_of_month"<?= ($renewal_mode ?? '') === 'day_of_month' ? ' checked' : '' ?>>
                            <label class="form-check-label" for="renewal_mode_day">Dzień miesiąca</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="renewal_mode" id="renewal_mode_days" value="number_of_days"<?= ($renewal_mode ?? '') === 'number_of_days' ? ' checked' : '' ?>>
                            <label class="form-check-label" for="renewal_mode_days">Liczba dni</label>
                        </div>
                    </div>
                    <div class="form-text">Wybierz sposób obliczania następnej daty alertu po zamknięciu.</div>
                </div>

                <div class="mb-3" id="renewal_value_group">
                    <label for="renewal_value" class="form-label">Wartość <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="renewal_value" name="renewal_value"
                           value="<?= htmlspecialchars((string) ($renewal_value ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           min="1" max="365" step="1" placeholder="Wprowadź wartość">
                    <div class="form-text" id="renewal_value_help">Dzień miesiąca (1–28) lub liczba dni (1–365).</div>
                </div>

                <div class="mb-3" id="count_from_close_date_group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="count_from_close_date" id="count_from_close_date" value="1"<?= ($count_from_close_date ?? true) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="count_from_close_date">Licz od daty zakończenia alertu</label>
                    </div>
                    <div class="form-text">Gdy zaznaczone, następna data będzie liczona od momentu zamknięcia alertu. Gdy odznaczone — od oryginalnej daty zaplanowania (next_run_at).</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Utwórz alert</button>
        </form>
    </div>
</div>

<script>
    // Show/hide type-specific fields based on alert type selection
    (function() {
        var alertTypeSelect = document.getElementById('alert_type');
        var repeatGroup = document.getElementById('repeat_interval_group');
        var nextDaysGroup = document.getElementById('default_next_days_group');
        var renewalFieldsGroup = document.getElementById('renewal_fields_group');

        function toggleFields() {
            var type = alertTypeSelect.value;
            repeatGroup.style.display = (type === 'repeat_until_closed' || type === 'recurring_renewal') ? 'block' : 'none';
            nextDaysGroup.style.display = (type === 'recurring_series') ? 'block' : 'none';
            renewalFieldsGroup.style.display = (type === 'recurring_renewal') ? 'block' : 'none';
        }

        alertTypeSelect.addEventListener('change', toggleFields);
        toggleFields(); // Run on page load
    })();

    // Show/hide renewal sub-fields based on renewal mode selection
    (function() {
        var renewalModeDay = document.getElementById('renewal_mode_day');
        var renewalModeDays = document.getElementById('renewal_mode_days');
        var renewalValueInput = document.getElementById('renewal_value');
        var renewalValueHelp = document.getElementById('renewal_value_help');
        var countFromCloseDateGroup = document.getElementById('count_from_close_date_group');

        function toggleRenewalFields() {
            var mode = '';
            if (renewalModeDay.checked) {
                mode = 'day_of_month';
            } else if (renewalModeDays.checked) {
                mode = 'number_of_days';
            }

            if (mode === 'day_of_month') {
                renewalValueInput.setAttribute('min', '1');
                renewalValueInput.setAttribute('max', '28');
                renewalValueInput.setAttribute('placeholder', 'Dzień miesiąca (1–28)');
                renewalValueHelp.textContent = 'Dzień miesiąca, na który zostanie zaplanowany następny alert (1–28).';
                countFromCloseDateGroup.style.display = 'none';
            } else if (mode === 'number_of_days') {
                renewalValueInput.setAttribute('min', '1');
                renewalValueInput.setAttribute('max', '365');
                renewalValueInput.setAttribute('placeholder', 'Liczba dni (1–365)');
                renewalValueHelp.textContent = 'Liczba dni do dodania do daty odniesienia (1–365).';
                countFromCloseDateGroup.style.display = 'block';
            } else {
                renewalValueInput.setAttribute('min', '1');
                renewalValueInput.setAttribute('max', '365');
                renewalValueInput.setAttribute('placeholder', 'Wprowadź wartość');
                renewalValueHelp.textContent = 'Dzień miesiąca (1–28) lub liczba dni (1–365).';
                countFromCloseDateGroup.style.display = 'block';
            }
        }

        renewalModeDay.addEventListener('change', toggleRenewalFields);
        renewalModeDays.addEventListener('change', toggleRenewalFields);
        toggleRenewalFields(); // Run on page load
    })();

    // Toggle interval input constraints based on selected unit
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
                intervalInput.setAttribute('placeholder', 'np. 60');
            }
        }

        unitSelect.addEventListener('change', updateConstraints);
        updateConstraints(); // Apply on page load
    })();
</script>
