<?php
// app.php

require_once __DIR__ . '/config.php';

$request = $_SERVER['REQUEST_URI'];
$basePath = BASE_PATH;

// Remove base path dan query string
$request = str_replace($basePath, '', $request);
$request = strtok($request, '?');
$request = trim($request, '/');

// Kalau kosong, berarti home
if ($request === '') {
    $request = 'home';
}

// Route params untuk dynamic routes
$routeParams = [];

switch (true) {
    // ===== PUBLIC ROUTES =====
    case $request === 'home':
    case $request === '':
        require 'pages/index.php';
        break;
    
    case $request === 'login':
        require 'pages/login.php';
        break;
    
    case $request === 'register':
        require 'pages/register.php';
        break;
    
    case $request === 'logout':
        require 'pages/logout.php';
        break;
    
    case $request === 'forgot-password':
        require 'pages/forgot-password.php';
        break;
    
    case $request === 'reset-password':
        require 'pages/reset-password.php';
        break;

    // ===== DASHBOARD =====
    case $request === 'dashboard':
        require 'pages/dashboard.php';
        break;
    
    case $request === 'diskusi':
        require 'pages/diskusi.php';
        break;

    // ===== FORUM ROUTES =====
    case $request === 'forum':
        require 'pages/forum.php';
        break;
    
    case $request === 'forum/create':
        require 'pages/forum-create.php';
        break;
    
    // Dynamic route: /forum/thread/123
    case preg_match('#^forum/thread/(\d+)$#', $request, $matches):
        $routeParams['id'] = $matches[1];
        require 'pages/forum-thread.php';
        break;

    // ===== MENTOR ROUTES =====
    case $request === 'mentor':
        require 'pages/mentor.php';
        break;

    case $request === 'mentor/login':
        require 'pages/mentor/login.php';
        break;

    case $request === 'mentor/register':
        require 'pages/mentor/register.php';
        break;

    case $request === 'mentor/dashboard':
        require 'pages/mentor/dashboard.php';
        break;

    // ===== ADMIN ROUTES =====
    case $request === 'admin/dashboard':
        require 'pages/admin/dashboard.php';
        break;

    case $request === 'admin/mentors':
        require 'pages/admin/mentors.php';
        break;

    // ===== API ROUTES =====
    case $request === 'api/forum/upvote':
        require 'api/forum-upvote.php';
        break;

    // ===== FORUM ROUTES =====
case $request === 'forum':
    require 'pages/forum.php';
    break;

case $request === 'forum/create':
    require 'pages/forum-create.php';
    break;

// Dynamic route: /forum/edit/123 - TAMBAH INI
case preg_match('#^forum/edit/(\d+)$#', $request, $matches):
    $routeParams['id'] = $matches[1];
    require 'pages/forum-edit.php';
    break;

// Dynamic route: /forum/thread/123
case preg_match('#^forum/thread/(\d+)$#', $request, $matches):
    $routeParams['id'] = $matches[1];
    require 'pages/forum-thread.php';
    break;

    case 'settings':
    require __DIR__ . '/pages/settings.php';
    break;


    // ===== 404 =====
    default:
        http_response_code(404);
        echo "404 Not Found (route: /$request)";
        break;
}
