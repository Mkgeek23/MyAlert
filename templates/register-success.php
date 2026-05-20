<?php
/**
 * Registration Success Template
 *
 * Variables expected:
 *   $registeredEmail - string, the email that was registered
 *   $config          - array, application configuration (for base_url)
 */

$baseUrl = rtrim($config['base_path'] ?? '', '/');
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4 text-center">
                <h1 class="card-title mb-3">Registration Successful</h1>
                <p class="text-muted">
                    Your account has been created for
                    <strong><?= htmlspecialchars($registeredEmail, ENT_QUOTES, 'UTF-8') ?></strong>.
                </p>
                <p>You can now log in to start managing your alerts.</p>
                <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/login" class="btn btn-primary">
                    Go to Login
                </a>
            </div>
        </div>
    </div>
</div>
