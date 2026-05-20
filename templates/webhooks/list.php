<?php
/**
 * Webhooks List Template
 *
 * Displays the user's webhooks with delete buttons.
 * Shows success/error messages and a link to create new webhooks.
 *
 * Variables expected:
 *   $webhooks  - array of webhook rows
 *   $csrfToken - string, CSRF token for delete forms
 *   $success   - string, success message (empty if none)
 *   $error     - string, error message (empty if none)
 *   $config    - array, application configuration (for base_url)
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Webhooks</h1>
    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/webhooks-create" class="btn btn-primary">Add Webhook</a>
</div>

<?php if (!empty($success)): ?>
<div class="alert alert-success" role="alert">
    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger" role="alert">
    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if (empty($webhooks)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <p class="text-muted mb-3">You haven't added any webhooks yet.</p>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/webhooks-create" class="btn btn-outline-primary">Add Your First Webhook</a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>URL</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($webhooks as $webhook): ?>
                <tr>
                    <td><?= htmlspecialchars($webhook['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <code class="small"><?= htmlspecialchars($webhook['url'], ENT_QUOTES, 'UTF-8') ?></code>
                    </td>
                    <td><?= htmlspecialchars(date('M j, Y', strtotime($webhook['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end">
                        <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/webhooks" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this webhook?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="webhook_id" value="<?= htmlspecialchars((string) $webhook['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted mt-2 small"><?= (int) count($webhooks) ?> of 25 webhooks used.</p>
<?php endif; ?>
