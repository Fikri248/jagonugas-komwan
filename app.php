<?php
session_start();
require_once 'config.php';

$request = strtok($_SERVER['REQUEST_URI'], '?');
$request = str_replace(BASE_PATH, '', $request);
if ($request === '' || $request === '/') {
    $request = '/';
}

switch ($request) {
    case '/':
        require 'index.php';
        break;

    case '/login':
        require 'login.php';
        break;

    case '/register':
        require 'register.php';
        break;

    case '/dashboard':
        require 'dashboard.php';
        break;

    case '/diskusi':
        require 'halaman-diskusi.php';
        break;

    case '/logout':
        require 'logout.php';
        break;

    default:
        http_response_code(404);
        echo "404 Not Found (route: $request)";
        break;
}
