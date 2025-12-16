<?php
require_once __DIR__ . '/config.php';

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper URL: pakai BASE_PATH (config baru),
 * fallback ke BASEPATH (config lama) kalau masih ada.
 */
function url_path(string $path = ''): string
{
    $base = '';

    if (defined('BASE_PATH')) {
        $base = (string) constant('BASE_PATH');
    } elseif (defined('BASEPATH')) {
        $base = (string) constant('BASEPATH');
    }

    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

// Hapus semua data session di server
$_SESSION = [];

// Hapus session cookie di browser (kalau dipakai)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

// Destroy session ID
session_destroy();

// Redirect ke landing page
header('Location: ' . url_path('index.php'));
exit;
