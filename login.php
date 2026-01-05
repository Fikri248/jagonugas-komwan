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
    if ($role === 'admin') {
        header('Location: ' . url_path('admin-dashboard.php'));
    } elseif ($role === 'mentor') {
        header('Location: ' . url_path('mentor-dashboard.php'));
    } else {
        header('Location: ' . url_path('student-dashboard.php'));
    }
    exit;
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    redirect_by_role($role);
}

$error    = '';
$oldEmail = '';

if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($oldEmail === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        try {
            $db   = (new Database())->getConnection();
            $user = new User($db);

            $user->email    = $oldEmail;
            $user->password = $password;

            $loginResult = $user->login();

            if (is_array($loginResult)) {
                if (!empty($loginResult['success'])) {
                    $u    = $loginResult['user'] ?? [];
                    $role = $u['role'] ?? 'student';

                    if (
                        $role === 'mentor'
                        && array_key_exists('is_verified', $u)
                        && !$u['is_verified']
                    ) {
                        $error = 'Akun mentor belum diverifikasi. Tunggu konfirmasi dari admin.';
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['user_id']    = $u['id'] ?? null;
                        $_SESSION['name']       = $u['name'] ?? '';
                        $_SESSION['email']      = $u['email'] ?? $oldEmail;
                        $_SESSION['role']       = $role;
                        $_SESSION['login_time'] = time();
                        $_SESSION['avatar']     = $u['avatar'] ?? null;

                        redirect_by_role($role);
                    }
                } else {
                    $error = $loginResult['message'] ?? 'Email atau password salah.';
                }
            } else {
                if ($loginResult === true) {
                    session_regenerate_id(true);

                    $_SESSION['user_id']    = $user->id ?? null;
                    $_SESSION['name']       = $user->name ?? '';
                    $_SESSION['email']      = $oldEmail;
                    $_SESSION['role']       = $user->role ?? 'student';
                    $_SESSION['login_time'] = time();

                    redirect_by_role($_SESSION['role']);
                } else {
                    $error = 'Email atau password salah.';
                }
            }
        } catch (Throwable $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #ffffff; }
        
        .auth-page { min-height: 100vh; background: #f7fafc; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
        
        .auth-card { background: #ffffff; border-radius: 20px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18); padding: 32px 32px 28px; max-width: 420px; width: 100%; border: 1px solid #e2e8f0; }
        
        .auth-title { font-size: 1.6rem; font-weight: 700; color: #1a202c; margin-bottom: 4px; }
        .auth-subtitle { font-size: 0.95rem; color: #718096; margin-bottom: 24px; }
        
        .auth-back-btn { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 500; margin-bottom: 20px; padding: 8px 12px; border-radius: 8px; transition: all 0.2s ease; }
        .auth-back-btn:hover { color: #667eea; background: rgba(102, 126, 234, 0.08); transform: translateX(-2px); }
        .auth-back-btn svg { transition: transform 0.2s ease; }
        .auth-back-btn:hover svg { transform: translateX(-3px); }
        
        .auth-form { display: flex; flex-direction: column; gap: 14px; margin-bottom: 16px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        
        .auth-input { width: 100%; padding: 0.75rem 1rem; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 0.95rem; color: #1a202c; background: #f8fafc; outline: none; transition: all 0.25s ease; }
        .auth-input::placeholder { color: #94a3b8; }
        .auth-input:hover { border-color: #cbd5e1; background: #ffffff; }
        .auth-input:focus { border-color: #667eea; background: #ffffff; box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); }
        
        .btn { padding: 0.75rem 1.5rem; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3); }
        
        .auth-button { width: 100%; margin-top: 8px; justify-content: center; }
        
        .btn-google { width: 100%; padding: 14px 20px; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 12px; color: #1f2937; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 12px; cursor: pointer; transition: all 0.3s ease; text-decoration: none; }
        .btn-google:hover { border-color: #4285f4; background: #f8fafc; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(66, 133, 244, 0.15); }
        .btn-google svg { flex-shrink: 0; }
        
        /* Alert dengan animasi fade-out */
        .alert { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 0.9rem; font-weight: 500; transition: opacity 0.5s ease, transform 0.5s ease; }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; border: 1px solid #fecaca; }
        .alert svg { flex-shrink: 0; }
        .alert.fade-out { opacity: 0; transform: translateY(-10px); }
        
        .auth-link { color: #667eea; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: color 0.2s ease; }
        .auth-link:hover { color: #5a67d8; text-decoration: underline; }
        
        .auth-footer-text { font-size: 0.9rem; color: #4a5568; margin-top: 16px; text-align: center; }
        .auth-footer-text a { color: #667eea; font-weight: 600; text-decoration: none; }
        .auth-footer-text a:hover { text-decoration: underline; }
        
        .auth-divider { display: flex; align-items: center; margin: 20px 0; }
        .auth-divider::before, .auth-divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .auth-divider span { padding: 0 16px; color: #94a3b8; font-size: 0.85rem; }
        
        .btn-mentor-login { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 14px 24px; border: 2px solid #e2e8f0; border-radius: 12px; background: white; color: #475569; font-weight: 600; font-size: 0.95rem; text-decoration: none; transition: all 0.2s ease; }
        .btn-mentor-login:hover { border-color: #10b981; color: #10b981; background: #f0fdf4; }
        .btn-mentor-login svg { flex-shrink: 0; }
        
        @media (max-width: 480px) {
            .auth-card { padding: 24px 20px 20px; border-radius: 16px; }
            .auth-input { font-size: 16px; }
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

        <h1 class="auth-title">Selamat Datang Kembali</h1>
        <p class="auth-subtitle">Masuk ke akun JagoNugas lo</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" id="alert-notif">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <a href="<?php echo htmlspecialchars(url_path('google-auth.php?action=login')); ?>" class="btn-google">
            <svg width="20" height="20" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Login dengan Google
        </a>

        <div class="auth-divider">
            <span>atau login dengan email</span>
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
                    placeholder="contohnya emailgoogle@gmail.com"
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
                <a href="<?php echo htmlspecialchars(url_path('forgot-password.php')); ?>" class="auth-link">
                    Lupa password?
                </a>
            </div>

            <button type="submit" class="btn btn-primary auth-button">Login</button>
        </form>

        <p class="auth-footer-text">
            Belum punya akun?
            <a href="<?php echo htmlspecialchars(url_path('register.php')); ?>">Daftar gratis</a>
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

    <script>
        // Auto-dismiss alert setelah 5 detik
        (function() {
            const alert = document.getElementById('alert-notif');
            if (alert) {
                setTimeout(function() {
                    alert.classList.add('fade-out');
                    setTimeout(function() {
                        alert.remove();
                    }, 500); // Hapus element setelah animasi selesai
                }, 5000); // 5 detik
            }
        })();
    </script>
</body>
</html>
