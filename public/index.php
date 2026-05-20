<?php

/**
 * Front Controller
 *
 * Entry point for all web requests. Loads bootstrap, initializes the router,
 * applies CSRF middleware on POST requests, and dispatches to the appropriate handler.
 */

declare(strict_types=1);

// ─── Autoloader ──────────────────────────────────────────────────────────────

require_once __DIR__ . '/../vendor/autoload.php';

// ─── Bootstrap (config, DB, session) ─────────────────────────────────────────

require_once __DIR__ . '/../src/bootstrap.php';

// ─── Determine Requested Page ────────────────────────────────────────────────

$page = $_GET['page'] ?? 'dashboard';

// ─── Logout (inline handler) ─────────────────────────────────────────────────
// Destroys session data, clears the session cookie, and redirects to login.
// Requirement 2.7: logout SHALL destroy session, clear cookie, redirect to login.

if ($page === 'logout') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    $loginUrl = rtrim($config['base_path'], '/') . '/login';
    header('Location: ' . $loginUrl, true, 302);
    exit;
}

// ─── CSRF Enforcement on POST Requests ───────────────────────────────────────
// Exempt API endpoints from CSRF (they use Bearer token auth instead)

if ($page !== 'api-worker') {
    $csrfMiddleware = new MyAlert\Middleware\CsrfMiddleware();
    $csrfMiddleware->enforce();
}

// ─── Router Setup ────────────────────────────────────────────────────────────

$router = new MyAlert\Router($config, $pdo);

// Register all routes with their handler classes and auth requirements
$router->register('dashboard', MyAlert\Handlers\DashboardHandler::class, true);
$router->register('login', MyAlert\Handlers\LoginHandler::class, false);
$router->register('register', MyAlert\Handlers\RegisterHandler::class, false);
$router->register('alerts', MyAlert\Handlers\AlertsHandler::class, true);
$router->register('alerts-create', MyAlert\Handlers\AlertsCreateHandler::class, true);
$router->register('alerts-edit', MyAlert\Handlers\AlertsEditHandler::class, true);
$router->register('webhooks', MyAlert\Handlers\WebhooksHandler::class, true);
$router->register('webhooks-create', MyAlert\Handlers\WebhooksCreateHandler::class, true);
$router->register('history', MyAlert\Handlers\HistoryHandler::class, true);
$router->register('close-alert', MyAlert\Handlers\CloseAlertHandler::class, false);
$router->register('api-worker', MyAlert\Handlers\ApiWorkerHandler::class, false);

// ─── Dispatch ────────────────────────────────────────────────────────────────

$router->dispatch($page);
