<?php
/**
 * Login Template
 *
 * Variables expected:
 *   $csrfToken - string, CSRF token for form submission
 *   $error     - string, error message to display (empty if none)
 *   $email     - string, previously submitted email (for form re-population)
 *   $config    - array, application configuration (for base_url)
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">Login</h2>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/login" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                            required
                            autocomplete="email"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>

                <p class="text-center mt-3 mb-0">
                    Don't have an account?
                    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/register">Register</a>
                </p>
            </div>
        </div>
    </div>
</div>
