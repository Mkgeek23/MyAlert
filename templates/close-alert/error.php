<?php
/**
 * Close Alert - Error Template
 *
 * Displays an error message for already-used or invalid token scenarios.
 *
 * Variables expected:
 *   $errorMessage - string, the error message to display
 *   $errorTitle   - string, the page heading
 *   $config       - array, application configuration
 */
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#dc3545" class="bi bi-exclamation-circle-fill" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4m.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2"/>
                    </svg>
                </div>
                <h2 class="card-title mb-3"><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-muted mb-0"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>
</div>
