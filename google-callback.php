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

/**
 * Cleanup session oauth data
 */
function cleanupOAuthSession(): void {
    unset($_SESSION['google_oauth_state'], $_SESSION['google_auth_action']);
}

// =====================================================
// VALIDASI PARAMETER
// =====================================================
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['google_oauth_state'] ?? '')) {
    error_log('Google OAuth: Invalid state parameter');
    redirectWithError('Sesi tidak valid. Silakan coba lagi.', $BASE, $action);
}

if (isset($_GET['error'])) {
    $errorDesc = $_GET['error_description'] ?? $_GET['error'];
    error_log('Google OAuth Error: ' . $errorDesc);
    redirectWithError('Login Google dibatalkan.', $BASE, $action);
}

if (!isset($_GET['code'])) {
    error_log('Google OAuth: Missing authorization code');
    redirectWithError('Kode otorisasi tidak ditemukan.', $BASE, $action);
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
    if ($ch === false) {
        throw new RuntimeException('Gagal inisialisasi CURL');
    }
    
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($tokenData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    
    $tokenResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($tokenResponse === false) {
        error_log('Google OAuth CURL Error: ' . $curlError);
        throw new RuntimeException('Gagal terhubung ke Google: ' . $curlError);
    }

    if ($httpCode !== 200) {
        error_log('Google Token Error [HTTP ' . $httpCode . ']: ' . $tokenResponse);
        throw new RuntimeException('Gagal mendapatkan token dari Google');
    }

    $tokenResult = json_decode($tokenResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Respons token tidak valid');
    }

    $accessToken = $tokenResult['access_token'] ?? null;
    if (!$accessToken) {
        error_log('Google OAuth: No access token in response');
        throw new RuntimeException('Access token tidak ditemukan');
    }

    // =====================================================
    // STEP 2: Get user info from Google
    // =====================================================
    $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init($userInfoUrl);
    if ($ch === false) {
        throw new RuntimeException('Gagal inisialisasi CURL untuk user info');
    }
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken]
    ]);
    
    $userResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($userResponse === false) {
        error_log('Google UserInfo CURL Error: ' . $curlError);
        throw new RuntimeException('Gagal mendapatkan info user: ' . $curlError);
    }

    $googleUser = json_decode($userResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($googleUser['email'])) {
        error_log('Google OAuth: Invalid user info response - ' . $userResponse);
        throw new RuntimeException('Data user dari Google tidak valid');
    }

    $email    = strtolower(trim($googleUser['email']));
    $name     = trim($googleUser['name'] ?? '');
    $googleId = $googleUser['id'] ?? '';
    $picture  = $googleUser['picture'] ?? '';

    // Fallback name dari email
    if (empty($name)) {
        $name = explode('@', $email)[0];
    }

    // =====================================================
    // STEP 3: Database lookup
    // =====================================================
    $db = (new Database())->getConnection();

    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // =====================================================
    // ACTION: MENTOR REGISTER
    // =====================================================
    if ($action === 'mentor-register') {
        if ($existingUser && $existingUser['role'] === 'mentor') {
            cleanupOAuthSession();
            header('Location: ' . $BASE . '/mentor-login.php?error=' . urlencode('Email sudah terdaftar sebagai mentor. Silakan login.'));
            exit;
        }
        
        // Simpan data ke session untuk complete-mentor-profile.php
        $_SESSION['google_prefill_mentor'] = [
            'name'      => $existingUser['name'] ?? $name,
            'email'     => $existingUser['email'] ?? $email,
            'google_id' => $googleId,
            'avatar'    => ($existingUser['avatar'] ?? '') ?: $picture,
            'user_id'   => $existingUser['id'] ?? null,
        ];
        
        cleanupOAuthSession();
        header('Location: ' . $BASE . '/complete-mentor-profile.php');
        exit;
    }

    // =====================================================
    // ACTION: MENTOR LOGIN
    // =====================================================
    if ($action === 'mentor-login') {
        if (!$existingUser) {
            cleanupOAuthSession();
            header('Location: ' . $BASE . '/mentor-login.php?error=' . urlencode('Akun tidak ditemukan. Silakan daftar terlebih dahulu.'));
            exit;
        }

        if ($existingUser['role'] !== 'mentor') {
            cleanupOAuthSession();
            header('Location: ' . $BASE . '/mentor-login.php?error=' . urlencode('Akun ini bukan akun mentor. Silakan login di halaman utama.'));
            exit;
        }

        if (isset($existingUser['is_verified']) && !$existingUser['is_verified']) {
            cleanupOAuthSession();
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

        cleanupOAuthSession();
        header('Location: ' . $BASE . '/mentor-dashboard.php');
        exit;
    }

    // =====================================================
    // ACTION: STUDENT LOGIN / REGISTER (default)
    // =====================================================
    if ($existingUser) {
        // USER SUDAH ADA - LANGSUNG LOGIN
        
        // Mentor belum verified
        if ($existingUser['role'] === 'mentor' && isset($existingUser['is_verified']) && !$existingUser['is_verified']) {
            cleanupOAuthSession();
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

        cleanupOAuthSession();

        // Redirect sesuai role
        switch ($existingUser['role']) {
            case 'admin':
                header('Location: ' . $BASE . '/admin-dashboard.php');
                break;
            case 'mentor':
                header('Location: ' . $BASE . '/mentor-dashboard.php');
                break;
            default:
                header('Location: ' . $BASE . '/student-dashboard.php');
        }
        exit;

    } else {
        // USER BARU - Redirect ke complete-profile.php
        $_SESSION['google_prefill'] = [
            'name'      => $name,
            'email'     => $email,
            'google_id' => $googleId,
            'avatar'    => $picture,
        ];

        cleanupOAuthSession();
        header('Location: ' . $BASE . '/complete-profile.php');
        exit;
    }

} catch (PDOException $e) {
    error_log('Google OAuth Database Error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    redirectWithError('Kesalahan database. Silakan coba lagi.', $BASE, $action);

} catch (RuntimeException $e) {
    error_log('Google OAuth Runtime Error: ' . $e->getMessage());
    redirectWithError($e->getMessage(), $BASE, $action);

} catch (Throwable $e) {
    error_log('Google OAuth Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine());
    redirectWithError('Terjadi kesalahan. Silakan coba lagi.', $BASE, $action);
}
