<?php
// app.php - Simple Router untuk struktur flat (updated with CHAT & FORUM ROUTES)

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Ambil path request tanpa query string, lalu normalisasi dan buang BASE_PATH (kalau ada).
 */
function getRequestPath(string $basePath): string
{
    // Path tanpa query string (lebih aman daripada main str_replace ke full REQUEST_URI)
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

    // Normalisasi basePath
    $basePath = (string)$basePath;
    if ($basePath === '/') $basePath = '';
    $basePath = rtrim($basePath, '/');

    // Kalau basePath ada dan request dimulai dengan basePath, buang prefix-nya
    if ($basePath !== '' && str_starts_with($uriPath, $basePath)) {
        $uriPath = substr($uriPath, strlen($basePath));
        if ($uriPath === false) $uriPath = '/';
    }

    // Normalisasi path akhir
    $uriPath = '/' . ltrim($uriPath, '/');    // pastikan diawali "/"
    $uriPath = rtrim($uriPath, '/');          // buang trailing slash (kecuali root)
    if ($uriPath === '') $uriPath = '/';

    // Jadi format final tanpa slash depan untuk routing switch (kecuali root jadi '')
    $route = trim($uriPath, '/');

    // Proteksi simpel biar nggak ada path traversal aneh
    if (str_contains($route, '..')) {
        return '__invalid__';
    }

    return $route; // contoh: '', 'login', 'forum/thread/12', 'admin/mentors'
}

/**
 * Include file target dengan pengecekan exist.
 */
function includeRouteFile(string $file): void
{
    $full = __DIR__ . '/' . ltrim($file, '/');

    if (!is_file($full)) {
        http_response_code(500);
        echo "Route target tidak ditemukan: " . htmlspecialchars($file);
        exit;
    }

    require $full;
    exit;
}

$request = getRequestPath(BASE_PATH);

// Optional: rapihin URL kalau ada /app.php atau /index.php di URL (kalau kejadian)
if ($request === 'app.php' || str_starts_with($request, 'app.php/')) {
    $new = str_replace('app.php', '', $request);
    $new = trim($new, '/');
    header("Location: " . BASE_PATH . ($new ? '/' . $new : '/') , true, 301);
    exit;
}
if ($request === 'index.php' || str_starts_with($request, 'index.php/')) {
    $new = str_replace('index.php', '', $request);
    $new = trim($new, '/');
    header("Location: " . BASE_PATH . ($new ? '/' . $new : '/') , true, 301);
    exit;
}

// Route statis (langsung map string -> file)
$staticRoutes = [
    // ===== PUBLIC =====
    '' => 'index.php',
    'home' => 'index.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'logout' => 'logout.php',
    'forgot-password' => 'forgot-password.php',
    'reset-password' => 'reset-password.php',

    // ===== STUDENT =====
    'dashboard' => 'student-dashboard.php',
    'diskusi' => 'student-diskusi.php',
    'settings' => 'student-settings.php',
    'sessions' => 'student-sessions.php',
    'gems' => 'student-gems-purchase.php',
    
    // ===== STUDENT CHAT (NEW) =====
    'chat' => 'student-chat.php',
    'chat-history' => 'student-chat-history.php',

    // ===== FORUM =====
    'forum' => 'student-forum.php',
    'forum/create' => 'student-forum-create.php',

    // ===== MENTOR PUBLIC =====
    'mentor' => 'student-mentor.php',

    // ===== MENTOR PANEL =====
    'mentor/login' => 'mentor-login.php',
    'mentor/register' => 'mentor-register.php',
    'mentor/dashboard' => 'mentor-dashboard.php',
    'mentor/sessions' => 'mentor-sessions.php',
    'mentor/chat' => 'mentor-chat.php',
    'mentor/chat-history' => 'mentor-chat-history.php',
    'mentor/forum' => 'mentor-forum.php',
    'mentor/profile' => 'mentor-profile.php',
    'mentor/settings' => 'mentor-settings.php',

    // ===== ADMIN =====
    'admin/dashboard' => 'admin-dashboard.php',
    'admin/mentors' => 'admin-mentors.php',

    // ===== PAYMENT ROUTES =====
    'payment-process.php' => 'payment-process.php',
    'payment-direct-update.php' => 'payment-direct-update.php',
    'payment-cancel.php' => 'payment-cancel.php',
    
    // ===== API - FORUM =====
    'api/forum/upvote' => 'api-forum-upvote.php',
    
    // ===== API - NOTIFICATIONS =====
    'api/notifications/read' => 'api-notif-read.php',
    'api/notifications/read-all' => 'api-notif-read-all.php',
    
    // ===== API - CHAT (NEW) =====
    'api/chat/messages' => 'api-chat-messages.php',
    'api/chat/send' => 'student-chat-send.php',
    'api/message/delete' => 'api-message-delete.php',
    'api/typing-status' => 'api-typing-status.php',
    
    // ===== API - SESSION (NEW) =====
    'api/session/end' => 'api-session-end.php',
];

// Invalid path guard
if ($request === '__invalid__') {
    http_response_code(400);
    if (is_file(__DIR__ . '/404.php')) {
        require __DIR__ . '/404.php';
    } else {
        echo "400 Bad Request";
    }
    exit;
}

// 1) Cek route statis dulu
if (array_key_exists($request, $staticRoutes)) {
    includeRouteFile($staticRoutes[$request]);
}

// 2) Dynamic routes (pakai regex)
if (preg_match('#^forum/edit/(\d+)$#', $request, $m) === 1) {
    $_GET['id'] = $m[1];
    includeRouteFile('student-forum-edit.php');
}

if (preg_match('#^forum/thread/(\d+)$#', $request, $m) === 1) {
    $_GET['id'] = $m[1];
    includeRouteFile('student-forum-thread.php');
}

// ===== MENTOR FORUM THREAD (NEW) =====
if (preg_match('#^mentor/forum/thread/(\d+)$#', $request, $m) === 1) {
    $_GET['id'] = $m[1];
    includeRouteFile('mentor-forum-thread.php');
}

if (preg_match('#^book-session/(\d+)$#', $request, $m) === 1) {
    $_GET['mentor_id'] = $m[1];
    includeRouteFile('book-session.php');
}

// 3) Default 404
http_response_code(404);
if (is_file(__DIR__ . '/404.php')) {
    require __DIR__ . '/404.php';
} else {
    echo "404 Not Found";
}
exit;
