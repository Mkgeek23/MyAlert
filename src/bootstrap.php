<?php

/**
 * Application Bootstrap
 *
 * Loads configuration, verifies dependencies, establishes database connection,
 * and starts the session with secure settings.
 *
 * This file is included by the front controller (public/index.php) and the worker script.
 */

declare(strict_types=1);

// ─── Dependency Check ────────────────────────────────────────────────────────

$requiredExtensions = ['PDO', 'pdo_mysql', 'curl', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    $missing = implode(', ', $missingExtensions);
    die("Missing required PHP extensions: {$missing}. Please install them before continuing.");
}

// ─── Configuration ───────────────────────────────────────────────────────────

$configPath = __DIR__ . '/../config/app.php';

if (!file_exists($configPath)) {
    die('Configuration file not found. Please copy config/app.example.php to config/app.php and update with your settings.');
}

$config = require $configPath;

// Validate required config keys
$requiredKeys = ['db_host', 'db_name', 'db_user', 'db_password', 'base_path', 'timezone'];
$missingKeys = [];

foreach ($requiredKeys as $key) {
    if (!array_key_exists($key, $config)) {
        $missingKeys[] = $key;
    }
}

// Backward compatibility: accept 'base_url' if 'base_path' is missing
if (in_array('base_path', $missingKeys, true) && array_key_exists('base_url', $config)) {
    // Extract path from full URL for backward compatibility
    $config['base_path'] = rtrim(parse_url($config['base_url'], PHP_URL_PATH) ?? '', '/');
    $missingKeys = array_filter($missingKeys, fn($k) => $k !== 'base_path');
}

if (!empty($missingKeys)) {
    $missing = implode(', ', $missingKeys);
    die("Missing required configuration keys: {$missing}. Please update config/app.php.");
}

// Normalize base_path (no trailing slash)
$config['base_path'] = rtrim($config['base_path'] ?? '', '/');

// Build full base_url dynamically from $_SERVER (for redirects and external links)
if (php_sapi_name() !== 'cli' && isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $config['base_url'] = $scheme . '://' . $_SERVER['HTTP_HOST'] . $config['base_path'];
} else {
    // CLI context: base_url not available, use base_path only
    $config['base_url'] = $config['base_path'];
}

// ─── Timezone ────────────────────────────────────────────────────────────────

date_default_timezone_set($config['timezone']);

// ─── Database Connection ─────────────────────────────────────────────────────

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $config['db_host'],
    $config['db_name']
);

try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// ─── Session ─────────────────────────────────────────────────────────────────

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = 1800; // 30 minutes

    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);

    session_start();

    // Check session inactivity timeout
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $sessionLifetime) {
            session_unset();
            session_destroy();
            session_start();
        }
    }

    $_SESSION['last_activity'] = time();
}
