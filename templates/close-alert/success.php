<?php
/**
 * Close Alert - Success Template
 *
 * Displays a success confirmation after an alert has been closed.
 *
 * Variables expected:
 *   $successMessage - string, the success message to display
 *   $successTitle   - string, the page heading
 *   $config         - array, application configuration
 */
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#198754" class="bi bi-check-circle-fill" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                    </svg>
                </div>
                <h2 class="card-title mb-3"><?= htmlspecialchars($successTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-muted mb-0"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>
</div>
