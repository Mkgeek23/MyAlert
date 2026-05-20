<?php

/**
 * Migration Runner
 *
 * CLI script that compares migration files in /migrations against the
 * migrations tracking table and executes pending migrations in order.
 *
 * Usage: php migrate.php
 */

// Ensure running from CLI
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

// Load configuration
$configPath = __DIR__ . '/config/app.php';
if (!file_exists($configPath)) {
    echo "Error: Configuration file not found at {$configPath}\n";
    echo "Copy config/app.example.php to config/app.php and update with your settings.\n";
    exit(1);
}

$config = require $configPath;

// Validate required config keys
$requiredKeys = ['db_host', 'db_name', 'db_user', 'db_password'];
foreach ($requiredKeys as $key) {
    if (!array_key_exists($key, $config)) {
        echo "Error: Missing required configuration key '{$key}'\n";
        exit(1);
    }
}

// Connect to database
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    echo "Error: Could not connect to database: {$e->getMessage()}\n";
    exit(1);
}

// Ensure migrations tracking table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `filename` VARCHAR(255) NOT NULL,
            `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_migrations_filename` (`filename`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    echo "Error: Could not create migrations tracking table: {$e->getMessage()}\n";
    exit(1);
}

// Get list of already-executed migrations
$executedMigrations = [];
try {
    $stmt = $pdo->query("SELECT filename FROM migrations ORDER BY filename ASC");
    $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    echo "Error: Could not read migrations table: {$e->getMessage()}\n";
    exit(1);
}

// Scan migrations directory
$migrationsDir = __DIR__ . '/migrations';
if (!is_dir($migrationsDir)) {
    echo "Error: Migrations directory not found at {$migrationsDir}\n";
    exit(1);
}

$files = glob($migrationsDir . '/*.sql');
if ($files === false) {
    echo "Error: Could not read migrations directory\n";
    exit(1);
}

// Sort files by name (ascending numeric prefix order)
sort($files);

// Filter to only pending migrations
$pendingMigrations = [];
foreach ($files as $file) {
    $filename = basename($file);
    if (!in_array($filename, $executedMigrations, true)) {
        $pendingMigrations[] = $file;
    }
}

if (empty($pendingMigrations)) {
    echo "No pending migrations.\n";
    exit(0);
}

echo sprintf("Found %d pending migration(s).\n", count($pendingMigrations));

// Execute pending migrations
$executed = 0;
foreach ($pendingMigrations as $file) {
    $filename = basename($file);
    $sql = file_get_contents($file);

    if ($sql === false) {
        echo "Error: Could not read migration file: {$filename}\n";
        exit(1);
    }

    echo "Running: {$filename}... ";

    try {
        $pdo->exec($sql);

        // Record successful migration
        $stmt = $pdo->prepare("INSERT INTO migrations (filename) VALUES (:filename)");
        $stmt->execute(['filename' => $filename]);

        echo "OK\n";
        $executed++;
    } catch (PDOException $e) {
        echo "FAILED\n";
        echo "Error in {$filename}: {$e->getMessage()}\n";
        exit(1);
    }
}

echo sprintf("\nDone. %d migration(s) executed successfully.\n", $executed);
exit(0);
