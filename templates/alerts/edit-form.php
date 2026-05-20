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
 *   $default_next_days      - string, current/submitted default next days
 *   $config                 - array, application configuration
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>

<div class="mb-4">
    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none">&larr; Back to Alert Details</a>
</div>

<h1 class="mb-4">Edit Alert</h1>

<?php if ($alert['status'] === 'closed'): ?>
<div class="alert alert-info" role="alert">
    This alert is currently closed. Saving changes will reopen it.
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
                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required placeholder="e.g. Daily standup reminder">
                <div class="form-text">A short title for your alert (1-255 characters).</div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional description for your alert"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="form-text">Optional. Will be included in the Discord message.</div>
            </div>

            <div class="mb-3">
                <label for="webhook_id" class="form-label">Webhook <span class="text-danger">*</span></label>
                <select class="form-select" id="webhook_id" name="webhook_id" required>
                    <option value="">-- Select a webhook --</option>
                    <?php foreach ($webhooks as $webhook): ?>
                    <option value="<?= htmlspecialchars((string) $webhook['id'], ENT_QUOTES, 'UTF-8') ?>"<?= (string) $webhook['id'] === (string) $webhook_id ? ' selected' : '' ?>><?= htmlspecialchars($webhook['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">The Discord webhook to send the alert to.</div>
            </div>

            <div class="mb-3">
                <label for="alert_type" class="form-label">Alert Type <span class="text-danger">*</span></label>
                <select class="form-select" id="alert_type" name="alert_type" required>
                    <option value="one_time"<?= $alert_type === 'one_time' ? ' selected' : '' ?>>One-Time</option>
                    <option value="repeat_until_closed"<?= $alert_type === 'repeat_until_closed' ? ' selected' : '' ?>>Repeat Until Closed</option>
                    <option value="recurring_series"<?= $alert_type === 'recurring_series' ? ' selected' : '' ?>>Recurring Series</option>
                </select>
                <div class="form-text">
                    <strong>One-Time:</strong> Fires once at the scheduled time.<br>
                    <strong>Repeat Until Closed:</strong> Repeats at a fixed interval until you close it.<br>
                    <strong>Recurring Series:</strong> After closing, you can schedule the next occurrence.
                </div>
            </div>

            <div class="mb-3">
                <label for="scheduled_at" class="form-label">Scheduled Date/Time <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at" value="<?= htmlspecialchars($scheduled_at, ENT_QUOTES, 'UTF-8') ?>" required>
                <div class="form-text">Must be at least 1 minute in the future.</div>
            </div>

            <div class="mb-3" id="repeat_interval_group">
                <label for="repeat_interval_minutes" class="form-label">Repeat Interval (minutes) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="repeat_interval_minutes" name="repeat_interval_minutes" value="<?= htmlspecialchars((string) $repeat_interval_minutes, ENT_QUOTES, 'UTF-8') ?>" min="5" max="525600" placeholder="e.g. 60 for hourly">
                <div class="form-text">How often to repeat (5 - 525,600 minutes). Only for Repeat Until Closed alerts.</div>
            </div>

            <div class="mb-3" id="default_next_days_group">
                <label for="default_next_days" class="form-label">Default Next Days <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="default_next_days" name="default_next_days" value="<?= htmlspecialchars((string) $default_next_days, ENT_QUOTES, 'UTF-8') ?>" min="1" max="365" placeholder="e.g. 7 for weekly">
                <div class="form-text">Pre-fills the next date when closing an occurrence (1-365 days). Only for Recurring Series alerts.</div>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
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
</script>
