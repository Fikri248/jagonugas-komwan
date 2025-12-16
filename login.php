<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';

// config.php kamu sudah session_start(), tapi ini aman kalau dipakai ulang
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper URL berbasis BASE_PATH dari config.php kamu.
 * Fallback ke BASEPATH supaya kompatibel dengan versi project lama.
 */
function url_path(string $path = ''): string
{
    $base = '';

    if (defined('BASE_PATH')) {
        $base = (string) constant('BASE_PATH');
    } elseif (defined('BASEPATH')) {
        // fallback untuk project lama
        $base = (string) constant('BASEPATH');
    }

    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

function redirect_by_role(string $role): void
{
    if ($role === 'admin') {
        header("Location: " . url_path('admin-dashboard.php'));
    } elseif ($role === 'mentor') {
        header("Location: " . url_path('mentor-dashboard.php'));
    } else {
        header("Location: " . url_path('student-dashboard.php'));
    }
    exit;
}

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    redirect_by_role($role);
}

$error = '';
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($oldEmail === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        try {
            $db = (new Database())->getConnection();
            $user = new User($db);

            $user->email = $oldEmail;
            $user->password = $password;

            $loginResult = $user->login();

            // Mode A: login() return array: ['success'=>bool,'user'=>...]
            if (is_array($loginResult)) {
                if (!empty($loginResult['success'])) {
                    $u = $loginResult['user'] ?? [];
                    $role = $u['role'] ?? 'student';

                    // Optional: cek mentor verified (kalau fieldnya ada)
                    if ($role === 'mentor' && array_key_exists('is_verified', $u) && !$u['is_verified']) {
                        $error = 'Akun mentor belum diverifikasi. Tunggu konfirmasi dari admin.';
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $u['id'] ?? null;
                        $_SESSION['name'] = $u['name'] ?? '';
                        $_SESSION['email'] = $u['email'] ?? $oldEmail;
                        $_SESSION['role'] = $role;
                        $_SESSION['login_time'] = time();
                        $_SESSION['avatar'] = $u['avatar'] ?? null;

                        redirect_by_role($role);
                    }
                } else {
                    $error = $loginResult['message'] ?? 'Email atau password salah.';
                }
            }
            // Mode B: login() return boolean (versi ModelUser lama)
            else {
                if ($loginResult === true) {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user->id ?? null;
                    $_SESSION['name'] = $user->name ?? '';
                    $_SESSION['email'] = $oldEmail;
                    $_SESSION['role'] = $user->role ?? 'student';
                    $_SESSION['login_time'] = time();

                    redirect_by_role($_SESSION['role']);
                } else {
                    $error = 'Email atau password salah.';
                }
            }
        } catch (Throwable $e) {
            $error = 'Terjadi kesalahan server. Coba lagi nanti.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(url_path('style.css')); ?>">
</head>
<body class="auth-page">
    <div class="auth-card">
        <a href="<?php echo htmlspecialchars(url_path('index.php')); ?>" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>

        <h1 class="auth-title">Selamat Datang Kembali</h1>
        <p class="auth-subtitle">Masuk ke akun JagoNugas lo</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form" autocomplete="on">
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="auth-input"
                    value="<?php echo htmlspecialchars($oldEmail); ?>"
                    placeholder="contoh@student.telkomuniversity.ac.id"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="auth-input"
                    placeholder="Masukkan password"
                    required
                >
            </div>

            <div class="form-group" style="text-align: right;">
                <a href="<?php echo htmlspecialchars(url_path('forgot-password.php')); ?>" class="auth-link">Lupa password?</a>
            </div>

            <button type="submit" class="btn btn-primary auth-button">Login</button>
        </form>

        <p class="auth-footer-text">
            Belum punya akun? <a href="<?php echo htmlspecialchars(url_path('register.php')); ?>">Daftar gratis</a>
        </p>

        <div class="auth-divider">
            <span>atau</span>
        </div>

        <a href="<?php echo htmlspecialchars(url_path('mentor-login.php')); ?>" class="btn-mentor-login">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Login sebagai Mentor
        </a>
    </div>
</body>
</html>
