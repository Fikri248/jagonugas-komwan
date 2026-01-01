<?php
// google-callback.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// GOOGLE OAUTH CREDENTIALS
// =====================================================
define('GOOGLE_CLIENT_ID', '790923463312-mtdks539jsjttocijh63jv268uvvd74d.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');

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

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';
$action = $_SESSION['google_auth_action'] ?? 'login';

/**
 * Redirect dengan error message ke halaman yang sesuai
 */
function redirectWithError(string $message, string $base, string $action = 'login'): void {
    $redirectMap = [
        'mentor-register' => '/mentor-register.php',
        'mentor-login'    => '/mentor-login.php',
        'register'        => '/register.php',
        'login'           => '/login.php',
    ];
    
    $page = $redirectMap[$action] ?? '/login.php';
    header('Location: ' . $base . $page . '?error=' . urlencode($message));
    exit;
}

// Validasi state
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['google_oauth_state'] ?? '')) {
    redirectWithError('Invalid state parameter', $BASE, $action);
}

if (isset($_GET['error'])) {
    redirectWithError('Google login dibatalkan', $BASE, $action);
}

if (!isset($_GET['code'])) {
    redirectWithError('Authorization code tidak ditemukan', $BASE, $action);
}

$code = $_GET['code'];

try {
    // =====================================================
    // STEP 1: Exchange code for access token
    // =====================================================
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenData = [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($tokenData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $tokenResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('Google Token Error: ' . $tokenResponse);
        redirectWithError('Gagal mendapatkan access token', $BASE, $action);
    }

    $tokenResult = json_decode($tokenResponse, true);
    $accessToken = $tokenResult['access_token'] ?? null;

    if (!$accessToken) {
        redirectWithError('Access token tidak valid', $BASE, $action);
    }

    // =====================================================
    // STEP 2: Get user info from Google
    // =====================================================
    $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init($userInfoUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken]
    ]);
    $userResponse = curl_exec($ch);
    curl_close($ch);

    $googleUser = json_decode($userResponse, true);

    if (!isset($googleUser['email'])) {
        redirectWithError('Gagal mendapatkan info user dari Google', $BASE, $action);
    }

    $email    = strtolower(trim($googleUser['email']));
    $name     = $googleUser['name'] ?? '';
    $googleId = $googleUser['id'] ?? '';
    $picture  = $googleUser['picture'] ?? '';

    // =====================================================
    // STEP 3: Handle berdasarkan action
    // =====================================================
    $db = (new Database())->getConnection();

    // Cek user by email (case-insensitive)
    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // =====================================================
    // ACTION: MENTOR REGISTER
    // =====================================================
    if ($action === 'mentor-register') {
        if ($existingUser) {
            if ($existingUser['role'] === 'mentor') {
                unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);
                header('Location: ' . $BASE . '/mentor-login.php?error=' . urlencode('Email sudah terdaftar sebagai mentor. Silakan login.'));
                exit;
            } else {
                // Upgrade existing user ke mentor
                $_SESSION['google_prefill_mentor'] = [
                    'name'      => $existingUser['name'],
                    'email'     => $existingUser['email'],
                    'google_id' => $googleId,
                    'avatar'    => $existingUser['avatar'] ?: $picture,
                    'user_id'   => $existingUser['id'],
                ];
                unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);
                header('Location: ' . $BASE . '/complete-mentor-profile.php');
                exit;
            }
        } else {
            // User baru
            $_SESSION['google_prefill_mentor'] = [
                'name'      => $name,
                'email'     => $email,
                'google_id' => $googleId,
                'avatar'    => $picture,
            ];
            unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);
            header('Location: ' . $BASE . '/complete-mentor-profile.php');
            exit;
        }
    }

    // =====================================================
    // ACTION: MENTOR LOGIN
    // =====================================================
    if ($action === 'mentor-login') {
        if (!$existingUser) {
            unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);
            header('Location: ' . $BASE . '/mentor-login.php?error=' . urlencode('Akun tidak ditemukan. Silakan daftar terlebih dahulu.'));
            exit;
        }

        if ($existingUser['role'] !== 'mentor') {
            unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);
            header('Location: ' . $BASE . '/mentor-login.php?error=' . urlencode('Akun ini bukan akun mentor. Silakan login di halaman utama.'));
            exit;
        }

        if (isset($existingUser['is_verified']) && !$existingUser['is_verified']) {
            unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);
            header('Location: ' . $BASE . '/mentor-login.php?error=' . urlencode('Akun mentor belum diverifikasi. Mohon tunggu 1x24 jam.'));
            exit;
        }

        // Link Google account jika belum
        if (empty($existingUser['google_id'])) {
            $updateStmt = $db->prepare("UPDATE users SET google_id = ?, avatar = COALESCE(NULLIF(avatar, ''), ?) WHERE id = ?");
            $updateStmt->execute([$googleId, $picture, $existingUser['id']]);
        }

        // Set session & login
        session_regenerate_id(true);
        $_SESSION['user_id']    = $existingUser['id'];
        $_SESSION['name']       = $existingUser['name'];
        $_SESSION['email']      = $existingUser['email'];
        $_SESSION['role']       = 'mentor';
        $_SESSION['gems']       = $existingUser['gems'] ?? 0;
        $_SESSION['avatar']     = $existingUser['avatar'] ?: $picture;
        $_SESSION['login_time'] = time();

        unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);
        header('Location: ' . $BASE . '/mentor-dashboard.php');
        exit;
    }

    // =====================================================
    // ACTION: LOGIN / REGISTER STUDENT (default)
    // =====================================================
    if ($existingUser) {
        // USER SUDAH ADA - LANGSUNG LOGIN
        
        // Cek kalau mentor belum verified, tolak
        if ($existingUser['role'] === 'mentor' && isset($existingUser['is_verified']) && !$existingUser['is_verified']) {
            unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);
            header('Location: ' . $BASE . '/login.php?error=' . urlencode('Akun mentor belum diverifikasi. Mohon tunggu 1x24 jam.'));
            exit;
        }

        // Link Google account jika belum
        $updates = [];
        $params = [];
        
        if (empty($existingUser['google_id'])) {
            $updates[] = "google_id = ?";
            $params[] = $googleId;
        }
        if (empty($existingUser['avatar']) && !empty($picture)) {
            $updates[] = "avatar = ?";
            $params[] = $picture;
        }
        
        if (!empty($updates)) {
            $params[] = $existingUser['id'];
            $updateStmt = $db->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
            $updateStmt->execute($params);
            
            if (empty($existingUser['avatar']) && !empty($picture)) {
                $existingUser['avatar'] = $picture;
            }
        }

        // Set session
        session_regenerate_id(true);
        $_SESSION['user_id']    = $existingUser['id'];
        $_SESSION['name']       = $existingUser['name'];
        $_SESSION['email']      = $existingUser['email'];
        $_SESSION['role']       = $existingUser['role'];
        $_SESSION['gems']       = $existingUser['gems'] ?? 0;
        $_SESSION['avatar']     = $existingUser['avatar'] ?: $picture;
        $_SESSION['login_time'] = time();

        unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);

        // Redirect ke dashboard sesuai role
        if ($existingUser['role'] === 'admin') {
            header('Location: ' . $BASE . '/admin-dashboard.php');
        } elseif ($existingUser['role'] === 'mentor') {
            header('Location: ' . $BASE . '/mentor-dashboard.php');
        } else {
            header('Location: ' . $BASE . '/student-dashboard.php');
        }
        exit;

    } else {
        // USER BARU - Auto register sebagai student
        $randomPassword = bin2hex(random_bytes(16));
        $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, role, google_id, avatar, gems, created_at) 
            VALUES (?, ?, ?, 'student', ?, ?, 100, NOW())
        ");
        $stmt->execute([$name ?: explode('@', $email)[0], $email, $hashedPassword, $googleId, $picture]);
        $newUserId = $db->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['user_id']    = $newUserId;
        $_SESSION['name']       = $name ?: explode('@', $email)[0];
        $_SESSION['email']      = $email;
        $_SESSION['role']       = 'student';
        $_SESSION['gems']       = 100;
        $_SESSION['avatar']     = $picture;
        $_SESSION['login_time'] = time();

        unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);

        header('Location: ' . $BASE . '/student-dashboard.php');
        exit;
    }

} catch (Throwable $e) {
    error_log('Google OAuth Error: ' . $e->getMessage());
    redirectWithError('Terjadi kesalahan. Silakan coba lagi.', $BASE, $action);
}
