<?php
// config.php - All-in-One Configuration


// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('Asia/Jakarta');


// ============================================
// SESSION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ============================================
// BASE PATH & URL
// ============================================
$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Auto-detect: Azure = kosong, Local = /jagonugas-native
if (strpos($hostName, 'azurewebsites.net') !== false) {
    define('BASE_PATH', '');
} else {
    $basePath = getenv('BASE_PATH');
    if ($basePath === false || $basePath === '') {
        $basePath = '/jagonugas-native'; // Default untuk local
    }
    define('BASE_PATH', $basePath);
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('BASE_URL', $protocol . '://' . $hostName);


// ============================================
// DATABASE CONNECTION
// ============================================
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'jagonugas_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: 'root';
$db_port = getenv('DB_PORT') ?: '3306';


try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'"
        ]
    );
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Koneksi database gagal. Silakan coba lagi nanti.");
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get database connection
 * Untuk backward compatibility dengan kode yang pakai Database class
 */
function getDB() {
    global $pdo;
    return $pdo;
}

/**
 * Database class wrapper (backward compatibility)
 * Biar kode lama yang pakai "new Database()" tetap jalan
 */
class Database {
    public function getConnection() {
        global $pdo;
        return $pdo;
    }
}

/**
 * Redirect helper
 */
function redirect($path) {
    header("Location: " . BASE_PATH . $path);
    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check user role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Sanitize output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format rupiah
 */
function rupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Format tanggal Indonesia
 */
function tanggal($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}
