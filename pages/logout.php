<?php
// pages/logout.php
require_once __DIR__ . '/../config.php';

// Hapus semua session data
$_SESSION = array();

// Hapus session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect ke landing page
header("Location: " . BASE_PATH . "/");
exit;
