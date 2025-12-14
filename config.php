<?php
// config.php

// Set timezone ke WIB (Jakarta)
date_default_timezone_set('Asia/Jakarta');

// Start session sekali di sini
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_PATH', '/jagonugas-native');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host);

// Database connection
$db_host = 'localhost';
$db_name = 'jagonugas_db';
$db_user = 'root';
$db_pass = 'root';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
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
    die("Koneksi database gagal: " . $e->getMessage());
}
