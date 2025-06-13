<?php
// Development mode (set to false in production)
define('DEVELOPMENT', true);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'revenueqr');

// Application constants
define('APP_NAME', 'RevenueQR');
define('APP_URL', 'https://revenueqr.sharedvaluevending.com');
define('UPLOAD_PATH', __DIR__ . '/../assets/img/uploads');
define('QR_BASE_DIR', __DIR__ . '/../uploads/qr');
define('QR_BASE_URL', APP_URL . '/uploads/qr');

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'REVENUEQR_SESS');

// QR Code settings
define('QR_DEFAULT_SIZE', 300);
define('QR_DEFAULT_MARGIN', 10);
define('QR_DEFAULT_ERROR_CORRECTION', 'M'); // L, M, Q, H

// QR Code Types
define('QR_TYPE_VOTE', 'vote');
define('QR_TYPE_PROMO', 'promo');
define('QR_TYPE_SALES', 'sales');

// Ensure base QR directory exists and is writable
if (!file_exists(QR_BASE_DIR)) {
    mkdir(QR_BASE_DIR, 0775, true);
}
if (!is_writable(QR_BASE_DIR)) {
    chmod(QR_BASE_DIR, 0775);
}

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // PDO::ATTR_PERSISTENT => true, // TEMPORARILY DISABLED - Causing CSRF token issues
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Set timezone to Eastern Time (Ontario, Canada)
date_default_timezone_set('America/Toronto');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Cache busting function to prevent browser caching issues
function asset_version($file_path) {
    $full_path = __DIR__ . '/../' . $file_path;
    if (file_exists($full_path)) {
        return filemtime($full_path);
    }
    return time(); // Fallback to current time if file doesn't exist 
} 