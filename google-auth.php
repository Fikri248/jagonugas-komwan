<?php
// google-auth.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// GOOGLE OAUTH CREDENTIALS
// =====================================================
define('GOOGLE_CLIENT_ID', '790923463312-mtdks539jsjttocijh63jv268uvvd74d.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');

// Detect environment untuk redirect URI
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

if (strpos($host, 'jagonugasweb.azurewebsites.net') !== false) {
    define('GOOGLE_REDIRECT_URI', 'https://jagonugasweb.azurewebsites.net/google-callback.php');
} elseif (strpos($host, 'jagonugas.azurewebsites.net') !== false) {
    define('GOOGLE_REDIRECT_URI', 'https://jagonugas.azurewebsites.net/google-callback.php');
} elseif (strpos($requestUri, '/jagonugas-native/') !== false) {
    define('GOOGLE_REDIRECT_URI', 'http://localhost/jagonugas-native/google-callback.php');
} else {
    define('GOOGLE_REDIRECT_URI', 'http://localhost/jagonugas-komwan/google-callback.php');
}

// Action: login, register, atau mentor-register
$action = $_GET['action'] ?? 'login';
$_SESSION['google_auth_action'] = $action;

// Build Google OAuth URL
$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'access_type'   => 'offline',
    'prompt'        => 'select_account',
    'state'         => bin2hex(random_bytes(16))
];

$_SESSION['google_oauth_state'] = $params['state'];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $authUrl);
exit;
