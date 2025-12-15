<?php
// app.php

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
        require 'pages/home.php';
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

    case $request === 'settings':
        require 'pages/settings.php';
        break;

    // ===== FORUM ROUTES =====
    case $request === 'forum':
        require 'pages/forum.php';
        break;
    
    case $request === 'forum/create':
        require 'pages/forum-create.php';
        break;
    
    case preg_match('#^forum/edit/(\d+)$#', $request, $matches) === 1:
        $routeParams['id'] = $matches[1];
        require 'pages/forum-edit.php';
        break;
    
    case preg_match('#^forum/thread/(\d+)$#', $request, $matches) === 1:
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
    
    case $request === 'mentor/bookings':
        require 'pages/mentor/bookings.php';
        break;
    
    case $request === 'mentor/chat':
        require 'pages/mentor/chat.php';
        break;
    
    case $request === 'mentor/profile':
        require 'pages/mentor/profile.php';
        break;
    
    case $request === 'mentor/settings':
        require 'pages/mentor/settings.php';
        break;
    
    case $request === 'mentor/availability':
        require 'pages/mentor/availability.php';
        break;

    // ===== ADMIN ROUTES =====
    case $request === 'admin/dashboard':
        require 'pages/admin/dashboard.php';
        break;

    case $request === 'admin/users':
        require 'pages/admin/users.php';
        break;

    case $request === 'admin/mentors':
        require 'pages/admin/mentors.php';
        break;
    
    case $request === 'admin/transactions':
        require 'pages/admin/transactions.php';
        break;
    
    case $request === 'admin/settings':
        require 'pages/admin/settings.php';
        break;
    
    case $request === 'admin/reports':
        require 'pages/admin/reports.php';
        break;

    // ===== API ROUTES =====
    case $request === 'api/forum/upvote':
        require 'api/forum-upvote.php';
        break;

    // ===== 404 =====
    default:
        http_response_code(404);
        require 'pages/404.php'; // Buat halaman 404 yang proper
        break;
}
