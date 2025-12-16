<?php
// config.php (simple)

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH -> otomatis dari folder aplikasi
// contoh: http://localhost/jagonugas-native/  => BASE_PATH = '/jagonugas-native'
//         https://namasite.azurewebsites.net/ => BASE_PATH = ''
$scriptName = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
define('BASE_PATH', $scriptName === '' ? '' : $scriptName);

// BASE_URL (kalau mau dipakai)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host . BASE_PATH);
