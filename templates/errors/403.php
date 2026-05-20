<?php
/**
 * 403 Forbidden Error Page
 *
 * Displayed when a CSRF token validation fails.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Forbidden</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <h1 class="display-1 text-danger">403</h1>
                <h2 class="mb-3">Forbidden</h2>
                <p class="text-muted mb-4">Your request could not be processed. The security token is missing or invalid. Please go back and try again.</p>
                <a href="/" class="btn btn-primary">Return to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
