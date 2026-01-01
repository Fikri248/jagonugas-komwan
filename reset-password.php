<?php
// reset-password.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

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
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: " . url_path('login.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = (new Database())->getConnection();
    $user = new User($db);

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($password)) {
        $error = 'Password wajib diisi';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok';
    } else {
        $result = $user->resetPassword($token, $password);

        if ($result['success']) {
            $success = 'Password berhasil diubah!';
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - JagoNugas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f7fafc; }

        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px; background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 50%, #f5f3ff 100%); }
        
        .auth-card { background: #ffffff; border-radius: 20px; box-shadow: 0 18px 45px rgba(15,23,42,0.18); padding: 40px; max-width: 440px; width: 100%; border: 1px solid #e2e8f0; }
        
        .auth-title { font-size: 1.75rem; font-weight: 700; color: #1a202c; margin-bottom: 8px; }
        .auth-subtitle { font-size: 0.95rem; color: #718096; margin-bottom: 28px; }

        .auth-form { display: flex; flex-direction: column; gap: 20px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 8px; }

        .auth-input { width: 100%; padding: 14px 18px; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 1rem; color: #1a202c; background: #f8fafc; outline: none; transition: all 0.25s ease; }
        .auth-input::placeholder { color: #94a3b8; }
        .auth-input:hover { border-color: #cbd5e1; background: #ffffff; }
        .auth-input:focus { border-color: #3b82f6; background: #ffffff; box-shadow: 0 0 0 4px rgba(59,130,246,0.15); }

        /* Password Strength Indicator - Hidden by default */
        .password-strength { margin-top: 8px; opacity: 0; max-height: 0; overflow: hidden; transition: all 0.3s ease; }
        .password-strength.visible { opacity: 1; max-height: 40px; }
        .strength-bar { height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden; }
        .strength-fill { height: 100%; width: 0%; transition: all 0.3s ease; border-radius: 2px; }
        .strength-fill.weak { width: 33%; background: #ef4444; }
        .strength-fill.medium { width: 66%; background: #f59e0b; }
        .strength-fill.strong { width: 100%; background: #10b981; }
        .strength-text { font-size: 0.75rem; margin-top: 4px; color: #64748b; }

        /* Alerts */
        .alert { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; transition: opacity 0.5s ease, transform 0.5s ease; }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); color: #16a34a; border: 1px solid #bbf7d0; }
        .alert.fade-out { opacity: 0; transform: translateY(-10px); }

        /* Success Box */
        .success-box { text-align: center; padding: 24px 0; }
        .success-icon { width: 80px; height: 80px; margin: 0 auto 20px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; animation: scaleIn 0.5s ease; }
        .success-icon svg { color: white; }
        @keyframes scaleIn { 0% { transform: scale(0); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }

        /* Buttons */
        .btn { padding: 14px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 1rem; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 10px; border: none; cursor: pointer; width: 100%; }
        .btn-primary { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(59,130,246,0.3); }
        .btn-cancel { background: #f1f5f9; color: #64748b; border: 2px solid #e2e8f0; }
        .btn-cancel:hover { background: #e2e8f0; color: #475569; }

        .auth-btn-group { display: flex; gap: 12px; margin-top: 8px; }
        .auth-btn-group .btn { flex: 1; }

        /* Key Icon */
        .key-icon { width: 64px; height: 64px; margin: 0 auto 16px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .key-icon svg { color: white; }

        @media (max-width: 480px) {
            .auth-card { padding: 28px 20px; }
            .auth-title { font-size: 1.5rem; }
            .auth-btn-group { flex-direction: column; }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card">
        <?php if ($success): ?>
            <!-- Success State -->
            <div class="success-box">
                <div class="success-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <h1 class="auth-title">Password Diubah!</h1>
                <p class="auth-subtitle">Password baru lo sudah aktif. Silakan login dengan password baru.</p>
            </div>
            
            <a href="<?php echo htmlspecialchars(url_path('login.php')); ?>" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
                Login Sekarang
            </a>
        <?php else: ?>
            <!-- Form State -->
            <div class="key-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                </svg>
            </div>

            <h1 class="auth-title">Reset Password</h1>
            <p class="auth-subtitle">Masukkan password baru lo</p>

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

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="password">Password Baru</label>
                    <input type="password" id="password" name="password" class="auth-input" 
                           placeholder="Minimal 6 karakter" required>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="auth-input" 
                           placeholder="Ulangi password baru" required>
                </div>

                <div class="auth-btn-group">
                    <button type="submit" class="btn btn-primary">Ubah Password</button>
                    <a href="<?php echo htmlspecialchars(url_path('login.php')); ?>" class="btn btn-cancel">Batal</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-dismiss error alert
        const alertError = document.getElementById('alert-error');
        if (alertError) {
            setTimeout(() => {
                alertError.classList.add('fade-out');
                setTimeout(() => alertError.remove(), 500);
            }, 5000);
        }

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthContainer = document.getElementById('passwordStrength');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');

        if (passwordInput && strengthFill && strengthText && strengthContainer) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let text = '';

                // Show/hide strength indicator based on input
                if (password.length > 0) {
                    strengthContainer.classList.add('visible');
                } else {
                    strengthContainer.classList.remove('visible');
                }

                if (password.length >= 6) strength++;
                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;

                strengthFill.className = 'strength-fill';

                if (password.length === 0) {
                    text = '';
                } else if (strength <= 2) {
                    strengthFill.classList.add('weak');
                    text = 'Lemah - tambahkan huruf besar, angka, atau simbol';
                } else if (strength <= 3) {
                    strengthFill.classList.add('medium');
                    text = 'Sedang - hampir bagus!';
                } else {
                    strengthFill.classList.add('strong');
                    text = 'Kuat - password aman! ✓';
                }

                strengthText.textContent = text;
            });
        }

        // Confirm password validation
        const confirmInput = document.getElementById('confirm_password');
        if (confirmInput && passwordInput) {
            confirmInput.addEventListener('input', function() {
                if (this.value !== passwordInput.value) {
                    this.style.borderColor = '#ef4444';
                } else if (this.value.length > 0) {
                    this.style.borderColor = '#10b981';
                } else {
                    this.style.borderColor = '#e2e8f0';
                }
            });
        }
    });
    </script>
</body>
</html>
