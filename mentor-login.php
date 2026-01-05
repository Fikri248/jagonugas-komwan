<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';

// ✅ TRACK VISITOR
if (file_exists(__DIR__ . '/track-visitor.php')) {
    require_once __DIR__ . '/track-visitor.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
            header('Location: ' . url_path('student-dashboard.php'));
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
$success = '';
$oldEmail = '';

// Cek error/success dari URL (redirect dari Google OAuth)
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($oldEmail === '' || $password === '') {
        $error = 'Email dan password wajib diisi';
    } else {
        try {
            $user = new User($pdo);

            $user->email = $oldEmail;
            $user->password = $password;

            $result = $user->login();

            if (is_array($result)) {
                if (!empty($result['success'])) {
                    $u = $result['user'] ?? [];
                    $role = $u['role'] ?? 'student';

                    if ($role !== 'mentor') {
                        $error = 'Akun ini bukan akun mentor. Silakan login di halaman utama.';
                    } elseif (array_key_exists('is_verified', $u) && !$u['is_verified']) {
                        // ✅ CHECK is_verified ONLY
                        $error = 'Akun mentor belum diverifikasi oleh admin. Mohon tunggu 1x24 jam.';
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $u['id'] ?? null;
                        $_SESSION['name'] = $u['name'] ?? '';
                        $_SESSION['email'] = $u['email'] ?? $oldEmail;
                        $_SESSION['role'] = 'mentor';
                        $_SESSION['gems'] = $u['gems'] ?? 0;
                        $_SESSION['avatar'] = $u['avatar'] ?? null;
                        $_SESSION['login_time'] = time();

                        header('Location: ' . url_path('mentor-dashboard.php'));
                        exit;
                    }
                } else {
                    $error = $result['message'] ?? 'Email atau password salah';
                }
            } elseif (is_bool($result)) {
                if ($result === true) {
                    $role = $user->role ?? 'student';
                    if ($role !== 'mentor') {
                        $error = 'Akun ini bukan akun mentor. Silakan login di halaman utama.';
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user->id ?? null;
                        $_SESSION['name'] = $user->name ?? '';
                        $_SESSION['email'] = $oldEmail;
                        $_SESSION['role'] = 'mentor';
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
            error_log('Mentor Login Error: ' . $e->getMessage());
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f7fafc; }

        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px; background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #f0fdfa 100%); }
        
        .auth-card { background: #ffffff; border-radius: 20px; box-shadow: 0 18px 45px rgba(15,23,42,0.18); padding: 40px; max-width: 440px; width: 100%; border: 1px solid #e2e8f0; }
        
        .auth-title { font-size: 1.75rem; font-weight: 700; color: #1a202c; margin-bottom: 8px; }
        .auth-subtitle { font-size: 0.95rem; color: #718096; margin-bottom: 28px; }

        .auth-back-btn { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 500; margin-bottom: 20px; padding: 8px 12px; border-radius: 8px; transition: all 0.2s ease; }
        .auth-back-btn:hover { color: #10b981; background: rgba(16,185,129,0.08); transform: translateX(-2px); }

        .auth-badge-mentor { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 16px; }

        .auth-form { display: flex; flex-direction: column; gap: 20px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 8px; }

        .auth-input { width: 100%; padding: 14px 18px; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 1rem; color: #1a202c; background: #f8fafc; outline: none; transition: all 0.25s ease; }
        .auth-input::placeholder { color: #94a3b8; }
        .auth-input:hover { border-color: #cbd5e1; background: #ffffff; }
        .auth-input:focus { border-color: #10b981; background: #ffffff; box-shadow: 0 0 0 4px rgba(16,185,129,0.15); }

        .alert { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; transition: opacity 0.5s ease, transform 0.5s ease; }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); color: #16a34a; border: 1px solid #bbf7d0; }
        .alert.fade-out { opacity: 0; transform: translateY(-10px); }

        .auth-link { color: #10b981; font-size: 0.9rem; text-decoration: none; font-weight: 500; }
        .auth-link:hover { text-decoration: underline; }

        .btn { padding: 14px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 1rem; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 10px; border: none; cursor: pointer; width: 100%; }
        .btn-mentor { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .btn-mentor:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16,185,129,0.3); }

        .btn-google { display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; padding: 14px 20px; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 12px; color: #1f2937; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; margin-bottom: 16px; }
        .btn-google:hover { border-color: #4285f4; background: #f8fafc; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(66,133,244,0.15); }

        .auth-divider { display: flex; align-items: center; margin: 24px 0; }
        .auth-divider::before, .auth-divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .auth-divider span { padding: 0 16px; color: #94a3b8; font-size: 0.85rem; }

        .btn-student-login { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 14px 20px; background: #f1f5f9; border: 2px solid #e2e8f0; border-radius: 12px; color: #475569; font-size: 0.95rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease; }
        .btn-student-login:hover { background: #e2e8f0; border-color: #cbd5e1; }

        .auth-footer-text { font-size: 0.9rem; color: #4a5568; margin-top: 24px; text-align: center; }
        .auth-footer-text a { color: #10b981; font-weight: 600; text-decoration: none; }
        .auth-footer-text a:hover { text-decoration: underline; }

        @media (max-width: 480px) {
            .auth-card { padding: 28px 20px; }
            .auth-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card">
        <a href="<?php echo htmlspecialchars(url_path('index.php')); ?>" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>

        <div class="auth-badge-mentor">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
            <div class="alert alert-error" id="alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" id="alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <a href="<?php echo htmlspecialchars(url_path('google-auth.php?action=mentor-login')); ?>" class="btn-google">
            <svg width="20" height="20" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Login dengan Google
        </a>

        <div class="auth-divider">
            <span>atau login manual</span>
        </div>

        <form method="POST" class="auth-form" autocomplete="on">
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="auth-input"
                    value="<?php echo htmlspecialchars($oldEmail); ?>"
                    placeholder="emailgoogle@gmail.com"
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

            <button type="submit" class="btn btn-mentor">Login sebagai Mentor</button>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function autoDismissAlert(id, delay) {
            const alert = document.getElementById(id);
            if (alert) {
                setTimeout(() => {
                    alert.classList.add('fade-out');
                    setTimeout(() => alert.remove(), 500);
                }, delay);
            }
        }

        autoDismissAlert('alert-error', 5000);
        autoDismissAlert('alert-success', 8000);
    });
    </script>
</body>
</html>
