<?php

/**
 * Application Configuration - Example
 *
 * Copy this file to app.php and fill in your environment-specific values.
 */

return [
    // Database settings
    'db_host' => 'localhost',
    'db_name' => 'my_alerts',
    'db_user' => 'root',
    'db_password' => '',

    // Application settings
    // Base path: the URL path prefix where the app is served (no domain, no trailing slash)
    // Examples: '/php/MyAlert/public', '/myalert', '' (if at document root)
    'base_path' => '/php/MyAlert/public',
    'timezone' => 'UTC',

    // Worker API key (used by the Python cron script to authenticate)
    'worker_api_key' => 'TestHehe',
];
