<?php
// config.php

// Start session sekali di sini
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_PATH', '/jagonugas-native');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host);
