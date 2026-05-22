<?php
/**
 * 403 Forbidden Error Page
 *
 * Displayed when a CSRF token validation fails.
 */
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Zabronione</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <h1 class="display-1 text-danger">403</h1>
                <h2 class="mb-3">Zabronione</h2>
                <p class="text-muted mb-4">Twoje żądanie nie mogło zostać przetworzone. Token bezpieczeństwa jest nieprawidłowy lub brakuje go. Wróć i spróbuj ponownie.</p>
                <a href="/" class="btn btn-primary">Wróć na stronę główną</a>
            </div>
        </div>
    </div>
</body>
</html>
