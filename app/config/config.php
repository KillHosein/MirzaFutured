<?php
/**
 * Configuration file for Mirza Web App
 * Uses the main project database configuration
 * 
 * @package MirzaWebApp
 * @version 1.0.0
 */

// Include the main project config to use existing database connection
require_once dirname(__DIR__, 2) . '/config.php';

// Environment settings
define('ENVIRONMENT', 'development'); // development, staging, production

// Application settings
define('APP_NAME', 'Mirza Web App');
define('APP_VERSION', '1.0.0');
define('APP_URL', $domainhosts ?? 'https://your-domain.com');
define('TIMEZONE', 'Asia/Tehran');

// Telegram Bot configuration
define('BOT_TOKEN', $APIKEY ?? '');
define('WEBHOOK_URL', $domainhosts ? $domainhosts . '/webhook.php' : '');
define('BOT_USERNAME', $usernamebot ?? '');

// Security settings
define('SECRET_KEY', $APIKEY . '_secret' ?? 'your-secret-key-here');
define('JWT_SECRET', $APIKEY . '_jwt' ?? 'your-jwt-secret-here');
define('ENCRYPTION_KEY', substr($APIKEY, 0, 32) ?? 'your-encryption-key-here');

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Cache settings
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600); // 1 hour

// Rate limiting
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_TIME_WINDOW', 3600); // 1 hour

// Admin settings
define('ADMIN_TELEGRAM_ID', $adminnumber ?? '123456789');

// Error reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set(TIMEZONE);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://web.telegram.org https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' https://api.telegram.org;");