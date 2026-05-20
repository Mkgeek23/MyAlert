<?php
/**
 * Alert Edit/View Template
 *
 * Displays alert details in a card layout with status and type badges.
 * Shows close button for active alerts and back link to alerts list.
 *
 * Variables expected:
 *   $alert        - array, the alert row from database
 *   $webhookName  - string, the name of the associated webhook
 *   $closeMessage - string, message after close action (empty if no action)
 *   $csrfToken    - string, CSRF token for forms
 *   $config       - array, application configuration (for base_url)
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');

// Determine display status (overdue if active and next_run_at is past)
$isOverdue = ($alert['status'] === 'active' && strtotime($alert['next_run_at']) < time());
$displayStatus = $isOverdue ? 'overdue' : $alert['status'];

// Map alert types to display labels
$typeLabels = [
    'one_time' => 'One-Time',
    'repeat_until_closed' => 'Repeat',
    'recurring_series' => 'Series',
];
$typeLabel = $typeLabels[$alert['alert_type']] ?? $alert['alert_type'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Alert Details</h1>
    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts" class="btn btn-outline-secondary">&larr; Back to Alerts</a>
</div>

<?php if ($closeMessage !== ''): ?>
<div class="alert <?= str_contains($closeMessage, 'successfully') ? 'alert-success' : 'alert-info' ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($closeMessage, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?= htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8') ?></h5>
        <div>
            <!-- Type Badge -->
            <?php if ($alert['alert_type'] === 'one_time'): ?>
                <span class="badge bg-info">One-Time</span>
            <?php elseif ($alert['alert_type'] === 'repeat_until_closed'): ?>
                <span class="badge bg-warning text-dark">Repeat</span>
            <?php elseif ($alert['alert_type'] === 'recurring_series'): ?>
                <span class="badge" style="background-color: #6f42c1; color: #fff;">Series</span>
            <?php endif; ?>

            <!-- Status Badge -->
            <?php if ($displayStatus === 'active'): ?>
                <span class="badge bg-success">Active</span>
            <?php elseif ($displayStatus === 'closed'): ?>
                <span class="badge bg-secondary">Closed</span>
            <?php elseif ($displayStatus === 'overdue'): ?>
                <span class="badge bg-danger">Overdue</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Title</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($alert['title'], ENT_QUOTES, 'UTF-8') ?></dd>

            <dt class="col-sm-4">Description</dt>
            <dd class="col-sm-8">
                <?php if (!empty($alert['description'])): ?>
                    <?= nl2br(htmlspecialchars($alert['description'], ENT_QUOTES, 'UTF-8')) ?>
                <?php else: ?>
                    <span class="text-muted">No description</span>
                <?php endif; ?>
            </dd>

            <dt class="col-sm-4">Type</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></dd>

            <dt class="col-sm-4">Status</dt>
            <dd class="col-sm-8">
                <?php if ($displayStatus === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                <?php elseif ($displayStatus === 'closed'): ?>
                    <span class="badge bg-secondary">Closed</span>
                <?php elseif ($displayStatus === 'overdue'): ?>
                    <span class="badge bg-danger">Overdue</span>
                <?php endif; ?>
            </dd>

            <dt class="col-sm-4">Next Run</dt>
            <dd class="col-sm-8">
                <?php if (!empty($alert['next_run_at'])): ?>
                    <?= htmlspecialchars(date('M j, Y g:i A', strtotime($alert['next_run_at'])), ENT_QUOTES, 'UTF-8') ?>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </dd>

            <?php if ($alert['alert_type'] === 'repeat_until_closed' && !empty($alert['repeat_interval_minutes'])): ?>
            <dt class="col-sm-4">Repeat Interval</dt>
            <dd class="col-sm-8">
                <?php
                    $minutes = (int) $alert['repeat_interval_minutes'];
                    if ($minutes >= 1440) {
                        $days = floor($minutes / 1440);
                        $remainingMinutes = $minutes % 1440;
                        $intervalDisplay = $days . ' day' . ($days > 1 ? 's' : '');
                        if ($remainingMinutes > 0) {
                            $hours = floor($remainingMinutes / 60);
                            $mins = $remainingMinutes % 60;
                            if ($hours > 0) {
                                $intervalDisplay .= ', ' . $hours . ' hour' . ($hours > 1 ? 's' : '');
                            }
                            if ($mins > 0) {
                                $intervalDisplay .= ', ' . $mins . ' minute' . ($mins > 1 ? 's' : '');
                            }
                        }
                    } elseif ($minutes >= 60) {
                        $hours = floor($minutes / 60);
                        $mins = $minutes % 60;
                        $intervalDisplay = $hours . ' hour' . ($hours > 1 ? 's' : '');
                        if ($mins > 0) {
                            $intervalDisplay .= ', ' . $mins . ' minute' . ($mins > 1 ? 's' : '');
                        }
                    } else {
                        $intervalDisplay = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
                    }
                ?>
                <?= htmlspecialchars($intervalDisplay, ENT_QUOTES, 'UTF-8') ?>
            </dd>
            <?php endif; ?>

            <?php if ($alert['alert_type'] === 'recurring_series' && !empty($alert['default_next_days'])): ?>
            <dt class="col-sm-4">Default Next Days</dt>
            <dd class="col-sm-8"><?= htmlspecialchars((string) $alert['default_next_days'], ENT_QUOTES, 'UTF-8') ?> days</dd>
            <?php endif; ?>

            <dt class="col-sm-4">Webhook</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($webhookName, ENT_QUOTES, 'UTF-8') ?></dd>

            <dt class="col-sm-4">Created</dt>
            <dd class="col-sm-8">
                <?php if (!empty($alert['created_at'])): ?>
                    <?= htmlspecialchars(date('M j, Y g:i A', strtotime($alert['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </dd>
        </dl>
    </div>

    <?php if ($displayStatus === 'active' || $displayStatus === 'overdue'): ?>
    <div class="card-footer">
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>&action=edit" class="btn btn-primary">Edit</a>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>&action=close" class="btn btn-danger">Close Alert</a>
    </div>
    <?php endif; ?>

    <?php if ($alert['status'] === 'closed'): ?>
    <div class="card-footer">
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>&action=edit" class="btn btn-primary">Edit</a>
        <?php if ($showReopenForm): ?>
            <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>&action=reopen" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label for="next_run_at" class="form-label">New Schedule Date/Time</label>
                        <input type="datetime-local" class="form-control" id="next_run_at" name="next_run_at" value="<?= htmlspecialchars($preservedDate, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-success">Confirm Reopen</button>
                    </div>
                </div>
                <?php if ($reopenError): ?>
                    <div class="text-danger mt-2"><?= htmlspecialchars($reopenError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/alerts-edit?id=<?= htmlspecialchars((string) $alert['id'], ENT_QUOTES, 'UTF-8') ?>&action=reopen" class="btn btn-success">Reopen</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
