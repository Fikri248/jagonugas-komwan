<?php
session_start();
require_once __DIR__ . '/config.php';

// Ambil path dari URL tanpa query string
$requestUri = strtok($_SERVER['REQUEST_URI'], '?');

// Hilangkan BASE_PATH (misal: /jagonugas-native) dari depan URL
$basePath = BASE_PATH ?? '';
if ($basePath !== '' && strpos($requestUri, $basePath) === 0) {
    $request = substr($requestUri, strlen($basePath));
} else {
    $request = $requestUri;
}

// Normalisasi:
// - kalau kosong, /, atau /app.php  -> anggap sebagai halaman utama
if ($request === '' || $request === false || $request === '/' || $request === '/app.php') {
    $request = '/';
} else {
    // selain itu, buang trailing slash
    $request = rtrim($request, '/');
}

// Routing sederhana
switch ($request) {
    case '/':
        require __DIR__ . '/pages/index.php';
        break;

    case '/login':
        require __DIR__ . '/pages/login.php';
        break;

    case '/register':
        require __DIR__ . '/pages/register.php';
        break;

    case '/dashboard':
        require __DIR__ . '/pages/dashboard.php';
        break;

    case '/diskusi':
        require __DIR__ . '/pages/diskusi.php';
        break;

    case '/logout':
        require __DIR__ . '/pages/logout.php';
        break;

    default:
        http_response_code(404);
        echo "404 Not Found (route: " . htmlspecialchars($request) . ")";
        break;
}
