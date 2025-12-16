<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper URL: pakai BASE_PATH (config baru), fallback ke BASEPATH (config lama).
 */
function url_path(string $path = ''): string
{
    $base = '';

    if (defined('BASE_PATH')) {
        $base = (string) constant('BASE_PATH');
    } elseif (defined('BASEPATH')) {
        $base = (string) constant('BASEPATH');
    }

    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

/**
 * Redirect by role.
 */
function redirect_by_role(string $role): void
{
    switch ($role) {
        case 'admin':
            header('Location: ' . url_path('admin-dashboard.php'));
            break;
        case 'mentor':
            header('Location: ' . url_path('mentor-dashboard.php'));
            break;
        default:
            header('Location: ' . url_path('dashboard.php'));
            break;
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
        $error = 'Email dan password wajib diisi';
    } else {
        try {
            $db = (new Database())->getConnection();
            $user = new User($db);

            $user->email = $oldEmail;
            $user->password = $password;

            $result = $user->login();

            // Mode A: login() mengembalikan array
            if (is_array($result)) {
                if (!empty($result['success'])) {
                    $u = $result['user'] ?? [];
                    $role = $u['role'] ?? 'student';

                    if ($role !== 'mentor') {
                        $error = 'Akun ini bukan akun mentor. Silakan login di halaman utama.';
                    } elseif (array_key_exists('is_verified', $u) && !$u['is_verified']) {
                        $error = 'Akun mentor belum diverifikasi oleh admin. Mohon tunggu 1x24 jam.';
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $u['id'] ?? null;
                        $_SESSION['name'] = $u['name'] ?? '';
                        $_SESSION['email'] = $u['email'] ?? $oldEmail;
                        $_SESSION['role'] = $role;
                        $_SESSION['login_time'] = time();
                        $_SESSION['avatar'] = $u['avatar'] ?? null;

                        header('Location: ' . url_path('mentor-dashboard.php'));
                        exit;
                    }
                } else {
                    $error = $result['message'] ?? 'Email atau password salah';
                }
            }
            // Mode B: login() mengembalikan boolean
            elseif (is_bool($result)) {
                if ($result === true) {
                    // Pastikan properti role, name, id tersedia di $user
                    $role = $user->role ?? 'student';
                    if ($role !== 'mentor') {
                        $error = 'Akun ini bukan akun mentor. Silakan login di halaman utama.';
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user->id ?? null;
                        $_SESSION['name'] = $user->name ?? '';
                        $_SESSION['email'] = $oldEmail;
                        $_SESSION['role'] = $role;
                        $_SESSION['login_time'] = time();

                        header('Location: ' . url_path('mentor-dashboard.php'));
                        exit;
                    }
                } else {
                    $error = 'Email atau password salah';
                }
            } else {
                $error = 'Terjadi kesalahan saat login. Coba lagi.';
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
    <title>Login Mentor - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(url_path('style.css')); ?>">
</head>
<body class="auth-page">
    <div class="auth-card auth-card-mentor">
        <a href="<?php echo htmlspecialchars(url_path('index.php')); ?>" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>

        <div class="auth-badge-mentor">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Portal Mentor
        </div>

        <h1 class="auth-title">Login Mentor</h1>
        <p class="auth-subtitle">Masuk untuk mulai membantu mahasiswa</p>

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
                    placeholder="email.mentor@telkomuniversity.ac.id"
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

            <button type="submit" class="btn btn-mentor auth-button">Login sebagai Mentor</button>
        </form>

        <p class="auth-footer-text">
            Belum jadi mentor? <a href="<?php echo htmlspecialchars(url_path('mentor-register.php')); ?>">Daftar jadi Mentor</a>
        </p>

        <div class="auth-divider">
            <span>atau</span>
        </div>

        <a href="<?php echo htmlspecialchars(url_path('login.php')); ?>" class="btn-student-login">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            Login sebagai Mahasiswa
        </a>
    </div>
</body>
</html>
