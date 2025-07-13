<?php
/**
 * Free SMTP Tester Configuration
 * Main configuration file for the SMTP testing application
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'smtp_tester');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'Free SMTP Tester');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://smtp-tester.0mail.pro');
define('APP_DEBUG', false);

// File Upload Settings
define('MAX_ATTACHMENT_SIZE', 1048576); // 1MB in bytes
define('ALLOWED_ATTACHMENT_TYPES', [
    'pdf', 'doc', 'docx', 'txt', 'rtf',
    'jpg', 'jpeg', 'png', 'gif', 'bmp',
    'xls', 'xlsx', 'csv', 'ppt', 'pptx',
    'zip', 'rar', '7z'
]);

// SMTP Testing Settings
define('SMTP_TIMEOUT', 30); // seconds
define('MAX_RECIPIENTS', 5); // Maximum recipients per test
define('RATE_LIMIT_TESTS', 10); // Maximum tests per hour per IP
define('RATE_LIMIT_WINDOW', 3600); // Rate limit window in seconds

// Proxy Settings
define('PROXY_ENABLED', true);
define('PROXY_FILE', __DIR__ . '/../Resource/Data/Proxy/proxy.csv');
define('PROXY_ROTATION', true);
define('PROXY_TIMEOUT', 10);

// Data Storage Paths
define('ATTACHMENT_PATH', __DIR__ . '/../Resource/Data/attachments/');
define('CONTENT_PATH', __DIR__ . '/../Resource/Data/content/');
define('LOG_PATH', __DIR__ . '/../Resource/Data/logs/');

// Security Settings
define('CSRF_TOKEN_EXPIRY', 1800); // 30 minutes
define('SESSION_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Email Settings
define('DEFAULT_FROM_EMAIL', 'noreply@0mail.pro');
define('DEFAULT_FROM_NAME', 'SMTP Tester');
define('DEFAULT_REPLY_TO', 'support@0mail.pro');

// Cleanup Settings
define('CLEANUP_ATTACHMENTS_DAYS', 7); // Clean attachments older than 7 days
define('CLEANUP_LOGS_DAYS', 30); // Clean logs older than 30 days
define('CLEANUP_SMTP_DATA_DAYS', 30); // Clean SMTP data older than 30 days

// Google Analytics
define('GA_TRACKING_ID', 'G-XXXXXXXXXX'); // Replace with actual tracking ID

// Feature Flags
define('FEATURE_PROXY_SUPPORT', true);
define('FEATURE_FILE_ATTACHMENTS', true);
define('FEATURE_ADVANCED_LOGGING', true);
define('FEATURE_RATE_LIMITING', true);

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);