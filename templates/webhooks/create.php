<?php
/**
 * Webhooks Create Template
 *
 * Form for creating a new webhook with name, URL fields, test button, and CSRF token.
 *
 * Variables expected:
 *   $csrfToken - string, CSRF token
 *   $error     - string, error message (empty if none)
 *   $success   - string, success message (empty if none)
 *   $name      - string, previously submitted name (for form repopulation)
 *   $url       - string, previously submitted URL (for form repopulation)
 *   $config    - array, application configuration (for base_url)
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>

<div class="mb-4">
    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/webhooks" class="text-decoration-none">&larr; Powrót do webhooków</a>
</div>

<h1 class="mb-4">Dodaj webhook</h1>

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

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/webhooks-create">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Nazwa webhooka</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" maxlength="100" required placeholder="np. Alerty serwera">
                <div class="form-text">Unikalna nazwa do identyfikacji webhooka (1-100 znaków).</div>
            </div>

            <div class="mb-3">
                <label for="url" class="form-label">URL webhooka Discord</label>
                <input type="url" class="form-control" id="url" name="url" value="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" required placeholder="https://discord.com/api/webhooks/...">
                <div class="form-text">Musi pasować do formatu: https://discord.com/api/webhooks/{id}/{token}</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="action" value="create" class="btn btn-primary">Zapisz webhook</button>
                <button type="submit" name="action" value="test" class="btn btn-outline-secondary">Testuj webhook</button>
            </div>
        </form>
    </div>
</div>
