<?php
// config.php - JagoNugas Configuration

// ============================================================
// TIMEZONE SETTINGS
// ============================================================
date_default_timezone_set('Asia/Jakarta');

// ============================================================
// SESSION MANAGEMENT
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// BASE PATH & URL CONFIGURATION
// ============================================================
// BASE_PATH -> otomatis dari folder aplikasi
// contoh: http://localhost/jagonugas-native/  => BASE_PATH = '/jagonugas-native'
//         https://namasite.azurewebsites.net/ => BASE_PATH = ''
$scriptName = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
define('BASE_PATH', $scriptName === '' ? '' : $scriptName);

// BASE_URL (untuk URL lengkap)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host . BASE_PATH);

// ============================================================
// DATABASE CONFIGURATION (untuk referensi, aktual di db.php)
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'jagonugas_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// MIDTRANS PAYMENT GATEWAY CONFIGURATION
// ============================================================

// Environment: 'sandbox' untuk testing, 'production' untuk live
define('MIDTRANS_ENVIRONMENT', 'sandbox'); // Ganti ke 'production' saat production

// Midtrans API Keys
// Dapatkan dari: https://dashboard.midtrans.com/settings/config_info
if (MIDTRANS_ENVIRONMENT === 'sandbox') {
    // SANDBOX KEYS (untuk testing)
    define('MIDTRANS_SERVER_KEY', 'Mid-server-CfBhax0bzR4foyH7oafH72Ti'); // Ganti dengan Server Key Sandbox
    define('MIDTRANS_CLIENT_KEY', 'Mid-client-gdMoToYnuDiDYGPN'); // Ganti dengan Client Key Sandbox
    define('MIDTRANS_API_URL', 'https://app.sandbox.midtrans.com');
    define('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/snap.js');
} else {
    // PRODUCTION KEYS (untuk live)
    define('MIDTRANS_SERVER_KEY', 'Mid-server-YOUR_PRODUCTION_SERVER_KEY'); // Ganti dengan Server Key Production
    define('MIDTRANS_CLIENT_KEY', 'Mid-client-YOUR_PRODUCTION_CLIENT_KEY'); // Ganti dengan Client Key Production
    define('MIDTRANS_API_URL', 'https://app.midtrans.com');
    define('MIDTRANS_SNAP_URL', 'https://app.midtrans.com/snap/snap.js');
}

// Midtrans Merchant ID (optional, untuk referensi)
define('MIDTRANS_MERCHANT_ID', 'G830712496'); // Ganti dengan Merchant ID dari Midtrans

// ============================================================
// APPLICATION SETTINGS
// ============================================================

// App Info
define('APP_NAME', 'JagoNugas');
define('APP_VERSION', '1.0.0');
define('APP_DESCRIPTION', 'Platform Mentoring & Forum Diskusi Mahasiswa');

// Upload Settings
define('UPLOAD_MAX_SIZE', 5242880); // 5MB dalam bytes
define('ALLOWED_AVATAR_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png']);
define('UPLOAD_AVATAR_PATH', __DIR__ . '/uploads/avatars/');
define('UPLOAD_TRANSKRIP_PATH', __DIR__ . '/uploads/transkrip/');
define('UPLOAD_FORUM_PATH', __DIR__ . '/uploads/forum/');

// Pagination
define('ITEMS_PER_PAGE', 20);
define('FORUM_THREADS_PER_PAGE', 15);

// Session Duration (dalam detik)
define('SESSION_TIMEOUT', 7200); // 2 jam

// ============================================================
// GEM SYSTEM CONFIGURATION
// ============================================================

// Gem Packages
define('GEM_PACKAGES', [
    'basic' => [
        'name' => 'Basic',
        'price' => 10000,
        'gems' => 4500,
        'bonus' => 0,
        'total_gems' => 4500
    ],
    'pro' => [
        'name' => 'Pro',
        'price' => 25000,
        'gems' => 12500,
        'bonus' => 500,
        'total_gems' => 13000
    ],
    'plus' => [
        'name' => 'Plus',
        'price' => 50000,
        'gems' => 27000,
        'bonus' => 2000,
        'total_gems' => 29000
    ]
]);

// Default Gem Rewards
define('GEM_REWARD_FORUM_BEST_ANSWER', 5);
define('GEM_REWARD_REGISTRATION', 100); // Bonus untuk user baru
define('GEM_COST_PER_MINUTE', 100); // Biaya per menit mentoring

// ============================================================
// EMAIL CONFIGURATION (untuk password reset, dll)
// ============================================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // Ganti dengan email Anda
define('SMTP_PASSWORD', 'your-app-password'); // Ganti dengan App Password Gmail
define('SMTP_FROM_EMAIL', 'noreply@jagonugas.id');
define('SMTP_FROM_NAME', 'JagoNugas');

// ============================================================
// SECURITY SETTINGS
// ============================================================

// Password Requirements
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);

// CSRF Token Settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_EXPIRY', 3600); // 1 jam

// Rate Limiting (untuk mencegah spam)
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 menit dalam detik

// ============================================================
// NOTIFICATION SETTINGS
// ============================================================
define('NOTIFICATION_TYPES', [
    'gem_purchase' => ['icon' => 'gem', 'color' => '#10b981'],
    'session_booking' => ['icon' => 'calendar-check', 'color' => '#667eea'],
    'session_completed' => ['icon' => 'check-circle', 'color' => '#10b981'],
    'forum_reply' => ['icon' => 'chat-dots', 'color' => '#667eea'],
    'best_answer' => ['icon' => 'star', 'color' => '#f59e0b'],
    'payment_success' => ['icon' => 'credit-card', 'color' => '#10b981'],
    'payment_failed' => ['icon' => 'exclamation-triangle', 'color' => '#ef4444']
]);

// ============================================================
// LOGGING SETTINGS
// ============================================================
define('LOG_PATH', __DIR__ . '/logs/');
define('LOG_LEVEL', 'DEBUG'); // DEBUG, INFO, WARNING, ERROR

// Create log directory if not exists
if (!file_exists(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

// ============================================================
// DEVELOPMENT/PRODUCTION MODE
// ============================================================
define('ENVIRONMENT', 'development'); // 'development' atau 'production'

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Generate URL dengan BASE_PATH
 */
function url($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . ($path ? '/' . $path : '');
}

/**
 * Redirect helper
 */
function redirect($path) {
    header('Location: ' . url($path));
    exit;
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 */
function has_role($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Get current user ID
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function get_user_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Sanitize input
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Format currency (IDR)
 */
function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Format gems
 */
function format_gems($amount) {
    return number_format($amount) . ' gems';
}

/**
 * Get time ago format
 */
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'Baru saja';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . ' menit yang lalu';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' jam yang lalu';
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return $days . ' hari yang lalu';
    } else {
        return date('d M Y', $timestamp);
    }
}

/**
 * Log message to file
 */
function log_message($level, $message, $context = []) {
    if (!defined('LOG_PATH')) return;
    
    $logFile = LOG_PATH . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// ============================================================
// AUTO-LOAD DEPENDENCIES
// ============================================================

// Load database connection
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}

// ============================================================
// SESSION TIMEOUT CHECK
// ============================================================
if (is_logged_in()) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        redirect('login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}

// ============================================================
// NOTES
// ============================================================
/*
CARA SETUP MIDTRANS:

1. Daftar di https://midtrans.com
2. Login ke Dashboard Midtrans
3. Pilih environment (Sandbox untuk testing)
4. Ambil Server Key & Client Key dari Settings > Access Keys
5. Copy-paste ke config ini
6. Setup Payment Notification URL di Settings > Configuration:
   - Notification URL: https://your-domain.com/payment-callback.php
   - Finish Redirect URL: https://your-domain.com/student-gems-purchase.php
   - Error Redirect URL: https://your-domain.com/student-gems-purchase.php
7. Untuk production, ganti MIDTRANS_ENVIRONMENT ke 'production'

TESTING PAYMENT DI SANDBOX:
- Credit Card: 4811 1111 1111 1114 (CVV: 123, Exp: 01/25)
- Status akan langsung menjadi success di sandbox
*/
?>
