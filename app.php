<?php
// app.php - Simple Router untuk struktur flat

require_once __DIR__ . '/config.php';

$request = $_SERVER['REQUEST_URI'];
$basePath = BASE_PATH;

// Remove base path dan query string
$request = str_replace($basePath, '', $request);
$request = strtok($request, '?');
$request = trim($request, '/');

// Route params untuk dynamic routes
$routeParams = [];

switch (true) {
    // ===== PUBLIC ROUTES =====
    case $request === '':
    case $request === 'home':
        require __DIR__ . '/index.php';
        break;
    
    case $request === 'login':
        require __DIR__ . '/login.php';
        break;
    
    case $request === 'register':
        require __DIR__ . '/register.php';
        break;
    
    case $request === 'logout':
        require __DIR__ . '/logout.php';
        break;
    
    case $request === 'forgot-password':
        require __DIR__ . '/forgot-password.php';
        break;
    
    case $request === 'reset-password':
        require __DIR__ . '/reset-password.php';
        break;

    // ===== STUDENT DASHBOARD =====
    case $request === 'dashboard':
        require __DIR__ . '/student-dashboard.php';
        break;
    
    case $request === 'diskusi':
        require __DIR__ . '/student-diskusi.php';
        break;

    case $request === 'settings':
        require __DIR__ . '/student-settings.php';
        break;

    // ===== FORUM ROUTES =====
    case $request === 'forum':
        require __DIR__ . '/student-forum.php';
        break;
    
    case $request === 'forum/create':
        require __DIR__ . '/student-forum-create.php';
        break;
    
    case preg_match('#^forum/edit/(\d+)$#', $request, $matches) === 1:
        $_GET['id'] = $matches[1];
        require __DIR__ . '/student-forum-edit.php';
        break;
    
    case preg_match('#^forum/thread/(\d+)$#', $request, $matches) === 1:
        $_GET['id'] = $matches[1];
        require __DIR__ . '/student-forum-thread.php';
        break;

    // ===== MENTOR PUBLIC =====
    case $request === 'mentor':
        require __DIR__ . '/student-mentor.php';
        break;

    // ===== MENTOR PANEL =====
    case $request === 'mentor/login':
        require __DIR__ . '/mentor-login.php';
        break;

    case $request === 'mentor/register':
        require __DIR__ . '/mentor-register.php';
        break;

    case $request === 'mentor/dashboard':
        require __DIR__ . '/mentor-dashboard.php';
        break;
    
    case $request === 'mentor/bookings':
        require __DIR__ . '/mentor-bookings.php';
        break;
    
    case $request === 'mentor/chat':
        require __DIR__ . '/mentor-chat.php';
        break;
    
    case $request === 'mentor/profile':
        require __DIR__ . '/mentor-profile.php';
        break;
    
    case $request === 'mentor/settings':
        require __DIR__ . '/mentor-settings.php';
        break;
    
    case $request === 'mentor/availability':
        require __DIR__ . '/mentor-availability.php';
        break;

    // ===== ADMIN ROUTES =====
    case $request === 'admin/dashboard':
        require __DIR__ . '/admin-dashboard.php';
        break;

    case $request === 'admin/users':
        require __DIR__ . '/admin-users.php';
        break;

    case $request === 'admin/mentors':
        require __DIR__ . '/admin-mentors.php';
        break;
    
    case $request === 'admin/transactions':
        require __DIR__ . '/admin-transactions.php';
        break;
    
    case $request === 'admin/settings':
        require __DIR__ . '/admin-settings.php';
        break;
    
    case $request === 'admin/reports':
        require __DIR__ . '/admin-reports.php';
        break;

    // ===== API ROUTES =====
    case $request === 'api/forum/upvote':
        require __DIR__ . '/api-forum-upvote.php';
        break;
    
    case $request === 'api/notifications/read':
        require __DIR__ . '/api-notif-read.php';
        break;
    
    case $request === 'api/notifications/read-all':
        require __DIR__ . '/api-notif-read-all.php';
        break;

    // ===== 404 =====
    default:
        http_response_code(404);
        require __DIR__ . '/404.php';
        break;
}
