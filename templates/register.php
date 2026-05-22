<?php
/**
 * Registration Form Template
 *
 * Variables expected:
 *   $csrfToken - string, CSRF token for form submission
 *   $errors    - array, validation error messages (may be empty)
 *   $old       - array, previously submitted values for input preservation
 *   $config    - array, application configuration (for base_url)
 */

$oldEmail = htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8');
$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="card-title text-center mb-4">Utwórz konto</h1>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/register" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label">Adres e-mail</label>
                        <input
                            type="email"
                            class="form-control<?= !empty($errors) && $oldEmail !== '' ? ' is-invalid' : '' ?>"
                            id="email"
                            name="email"
                            value="<?= $oldEmail ?>"
                            required
                            maxlength="254"
                            autocomplete="email"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Hasło</label>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            required
                            minlength="8"
                            maxlength="72"
                            autocomplete="new-password"
                        >
                        <div class="form-text">Musi mieć od 8 do 72 znaków.</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Zarejestruj się</button>
                    </div>
                </form>

                <p class="text-center mt-3 mb-0">
                    Masz już konto? <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/login">Zaloguj się</a>
                </p>
            </div>
        </div>
    </div>
</div>
