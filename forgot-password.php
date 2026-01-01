<?php
// forgot-password.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';
$BASE_URL = defined('BASE_URL') ? constant('BASE_URL') : '';

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

$error = '';
$success = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = (new Database())->getConnection();
    $user = new User($db);

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Email wajib diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } else {
        $user->email = $email;
        $result = $user->createResetToken();

        if ($result['success']) {
            $resetLink = $BASE_URL . "/reset-password.php?token=" . $result['token'];
            $success = 'Link reset password berhasil dibuat!';
        } else {
            $error = 'Email tidak terdaftar di sistem kami';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - JagoNugas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f7fafc; }

        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px; background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 50%, #f5f3ff 100%); }
        
        .auth-card { background: #ffffff; border-radius: 20px; box-shadow: 0 18px 45px rgba(15,23,42,0.18); padding: 40px; max-width: 440px; width: 100%; border: 1px solid #e2e8f0; }
        
        .auth-title { font-size: 1.75rem; font-weight: 700; color: #1a202c; margin-bottom: 8px; }
        .auth-subtitle { font-size: 0.95rem; color: #718096; margin-bottom: 28px; }

        .auth-back-btn { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 500; margin-bottom: 20px; padding: 8px 12px; border-radius: 8px; transition: all 0.2s ease; }
        .auth-back-btn:hover { color: #3b82f6; background: rgba(59,130,246,0.08); transform: translateX(-2px); }

        .auth-form { display: flex; flex-direction: column; gap: 20px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 8px; }

        .auth-input { width: 100%; padding: 14px 18px; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 1rem; color: #1a202c; background: #f8fafc; outline: none; transition: all 0.25s ease; }
        .auth-input::placeholder { color: #94a3b8; }
        .auth-input:hover { border-color: #cbd5e1; background: #ffffff; }
        .auth-input:focus { border-color: #3b82f6; background: #ffffff; box-shadow: 0 0 0 4px rgba(59,130,246,0.15); }

        /* Alerts */
        .alert { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; transition: opacity 0.5s ease, transform 0.5s ease; }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); color: #16a34a; border: 1px solid #bbf7d0; }
        .alert.fade-out { opacity: 0; transform: translateY(-10px); }

        /* Reset Link Box */
        .reset-link-box { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px solid #bfdbfe; border-radius: 16px; padding: 24px; text-align: center; margin-top: 8px; }
        .reset-link-info { color: #1e40af; font-size: 0.95rem; margin-bottom: 16px; font-weight: 500; }
        .reset-link-info::before { content: ''; display: block; font-size: 2rem; margin-bottom: 8px; }

        /* Buttons */
        .btn { padding: 14px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 1rem; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 10px; border: none; cursor: pointer; width: 100%; }
        .btn-primary { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(59,130,246,0.3); }

        .auth-footer-text { font-size: 0.9rem; color: #4a5568; margin-top: 24px; text-align: center; }
        .auth-footer-text a { color: #3b82f6; font-weight: 600; text-decoration: none; }
        .auth-footer-text a:hover { text-decoration: underline; }

        /* Lock Icon Animation */
        .lock-icon { width: 64px; height: 64px; margin: 0 auto 16px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .lock-icon svg { color: white; }

        @media (max-width: 480px) {
            .auth-card { padding: 28px 20px; }
            .auth-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card">
        <a href="<?php echo htmlspecialchars(url_path('login.php')); ?>" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali ke Login
        </a>

        <div class="lock-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>

        <h1 class="auth-title">Lupa Password?</h1>
        <p class="auth-subtitle">Masukkan email lo untuk reset password</p>

        <?php if ($error): ?>
            <div class="alert alert-error" id="alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success && $resetLink): ?>
            <div class="alert alert-success" id="alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
            
            <div class="reset-link-box">
                <p class="reset-link-info">
                    🔐 Klik tombol di bawah untuk membuat password baru
                </p>
                <a href="<?php echo htmlspecialchars($resetLink); ?>" class="btn btn-primary">
                    Reset Password Sekarang
                </a>
            </div>

            <p class="auth-footer-text">
                Link ini akan kadaluarsa dalam 1 jam
            </p>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="auth-input" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="contohnya emailgoogle@gmail.com" required>
            </div>

            <button type="submit" class="btn btn-primary">Kirim Link Reset</button>
        </form>

        <p class="auth-footer-text">
            Ingat password? <a href="<?php echo htmlspecialchars(url_path('login.php')); ?>">Login di sini</a>
        </p>
        <?php endif; ?>
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
        // Success alert tidak di-dismiss karena ada reset link
    });
    </script>
</body>
</html>
