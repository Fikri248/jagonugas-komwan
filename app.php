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

switch ($request) {
    case 'home':
    case '':
        require 'pages/index.php';
        break;
    
    case 'login':
        require 'pages/login.php';
        break;
    
    case 'register':
        require 'pages/register.php';
        break;
    
    case 'logout':
        require 'pages/logout.php';
        break;
    
    case 'dashboard':
        require 'pages/dashboard.php';
        break;
    
    case 'diskusi':
        require 'pages/diskusi.php';
        break;
    
    // Tambahin ini 👇
    case 'forgot-password':
        require 'pages/forgot-password.php';
        break;
    
    case 'reset-password':
        require 'pages/reset-password.php';
        break;

    // Tambahkan di switch case app.php

case 'mentor/login':
    require 'pages/mentor/login.php';
    break;

case 'mentor/register':
    require 'pages/mentor/register.php';
    break;

case 'mentor/dashboard':
    require 'pages/mentor/dashboard.php';
    break;

case 'admin/dashboard':
    require 'pages/admin/dashboard.php';
    break;

    case 'admin/mentors':
    require 'pages/admin/mentors.php';
    break;

    
    default:
        http_response_code(404);
        echo "404 Not Found (route: /$request)";
        break;
}
