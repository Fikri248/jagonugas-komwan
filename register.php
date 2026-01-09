<?php
// register.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';
require_once __DIR__ . '/NotificationHelper.php';

// ✅ TRACK VISITOR
if (file_exists(__DIR__ . '/track-visitor.php')) {
    require_once __DIR__ . '/track-visitor.php';
}


$error = '';
$success = '';

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

$programStudiList = [
    'S1 Informatika',
    'S1 Sistem Informasi',
    'S1 Teknologi Informasi',
    'S1 Rekayasa Perangkat Lunak',
    'S1 Sains Data',
    'S1 Bisnis Digital',
    'S1 Teknik Elektro',
    'S1 Teknik Telekomunikasi',
    'S1 Teknik Komputer',
    'S1 Teknik Industri',
    'S1 Teknik Logistik'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = (new Database())->getConnection();
        $user = new User($db);

        $name            = trim((string)($_POST['name'] ?? ''));
        $email           = trim((string)($_POST['email'] ?? ''));
        $password        = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $programStudi    = trim((string)($_POST['program_studi'] ?? ''));
        $semester        = (int)($_POST['semester'] ?? 0);

        if ($name === '' || $email === '' || $password === '' || $programStudi === '' || $semester < 1) {
            $error = 'Semua field wajib diisi';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid';
        } elseif (!in_array($programStudi, $programStudiList, true)) {
            $error = 'Program studi tidak valid';
        } elseif ($semester < 1 || $semester > 14) {
            $error = 'Semester harus antara 1 sampai 14';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter';
        } elseif ($password !== $confirmPassword) {
            $error = 'Konfirmasi password tidak cocok';
        } else {
            $user->name = $name;
            $user->email = $email;
            $user->password = $password;
            $user->program_studi = $programStudi;
            $user->programstudi  = $programStudi;
            $user->semester = $semester;

            $result = $user->register();

            if (!empty($result['success'])) {
                $newUserId = (int)($result['user_id'] ?? 0);
                $bonusGems = (int)($result['gems'] ?? 500);

                try {
                    $notif = new NotificationHelper($db);
                    if (method_exists($notif, 'welcome') && $newUserId > 0) {
                        $notif->welcome($newUserId);
                    }
                    if (method_exists($notif, 'create') && $newUserId > 0) {
                        $notif->create($newUserId, 'gem_bonus', 'Selamat! Kamu mendapat ' . $bonusGems . ' Gem gratis sebagai hadiah pendaftaran.', null, null);
                    }
                } catch (Throwable $e) {}

                $success = 'Registrasi berhasil! Kamu mendapat ' . $bonusGems . ' Gem gratis. Silakan login.';
                $_POST = [];
            } else {
                $error = (string)($result['message'] ?? 'Registrasi gagal, coba lagi');
            }
        }
    } catch (Throwable $e) {
        $error = 'Terjadi kesalahan server. Coba lagi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #ffffff; }

        /* ===== AUTH PAGE ===== */
        .auth-page { min-height: 100vh !important; background: #f7fafc !important; display: flex !important; align-items: center !important; justify-content: center !important; padding: 40px 16px !important; }
        .auth-card { background: #ffffff; border-radius: 20px; box-shadow: 0 18px 45px rgba(15,23,42,0.18); padding: 32px 32px 28px; max-width: 420px; width: 100%; border: 1px solid #e2e8f0; }
        .auth-title { font-size: 1.6rem; font-weight: 700; color: #1a202c; margin-bottom: 4px; }
        .auth-subtitle { font-size: 0.95rem; color: #718096; margin-bottom: 24px; }

        /* ===== BACK BUTTON ===== */
        .auth-back-btn { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 500; margin-bottom: 20px; padding: 8px 12px; border-radius: 8px; transition: all 0.2s ease; }
        .auth-back-btn:hover { color: #667eea; background: rgba(102,126,234,0.08); transform: translateX(-2px); }
        .auth-back-btn:hover svg { transform: translateX(-3px); }
        .auth-back-btn svg { transition: transform 0.2s ease; }

        /* ===== FORM ===== */
        .auth-form { display: flex; flex-direction: column; gap: 14px; margin-bottom: 16px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .auth-input { width: 100%; padding: 0.75rem 1rem; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 0.95rem; color: #1a202c; background: #f8fafc; outline: none; transition: all 0.25s ease; }
        .auth-input::placeholder { color: #94a3b8; }
        .auth-input:hover { border-color: #cbd5e1; background: #ffffff; }
        .auth-input:focus { border-color: #667eea; background: #ffffff; box-shadow: 0 0 0 4px rgba(102,126,234,0.15); }

        /* ===== BUTTONS ===== */
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; border: 2px solid transparent; cursor: pointer; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102,126,234,0.3); }
        .auth-button { width: 100%; margin-top: 8px; border: none; cursor: pointer; justify-content: center; }

        /* ===== GOOGLE BUTTON ===== */
        .btn-google { display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; padding: 14px 20px; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 12px; color: #1f2937; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; }
        .btn-google:hover { border-color: #4285f4; background: #f8fafc; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(66,133,244,0.15); }
        .btn-google svg { flex-shrink: 0; }

        /* ===== DIVIDER ===== */
        .auth-divider { display: flex; align-items: center; margin: 20px 0; }
        .auth-divider::before, .auth-divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .auth-divider span { padding: 0 16px; color: #94a3b8; font-size: 0.85rem; }

        /* ===== ALERTS dengan animasi fade-out ===== */
        .alert { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 0.9rem; font-weight: 500; transition: opacity 0.5s ease, transform 0.5s ease; }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); color: #16a34a; border: 1px solid #bbf7d0; }
        .alert.fade-out { opacity: 0; transform: translateY(-10px); }

        /* ===== CUSTOM SELECT ===== */
        .custom-select { position: relative; width: 100%; font-size: 0.95rem; user-select: none; }
        .select-selected { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 2px solid #e2e8f0; border-radius: 14px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .select-selected:hover { border-color: #cbd5e1; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .custom-select.active .select-selected { border-color: #667eea; background: #ffffff; box-shadow: 0 0 0 4px rgba(102,126,234,0.15), 0 8px 24px rgba(102,126,234,0.1); }
        .select-text { color: #64748b; font-weight: 500; }
        .select-text.has-value { color: #1e293b; font-weight: 600; }
        .select-arrow { color: #94a3b8; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), color 0.2s; }
        .custom-select.active .select-arrow { transform: rotate(180deg); color: #667eea; }
        .select-items { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.12), 0 8px 16px rgba(0,0,0,0.08); max-height: 280px; overflow-y: auto; opacity: 0; visibility: hidden; transform: translateY(-10px) scale(0.98); transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1000; padding: 8px; }
        .custom-select.active .select-items { opacity: 1; visibility: visible; transform: translateY(0) scale(1); }
        .select-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 10px; cursor: pointer; color: #475569; font-weight: 500; transition: all 0.2s ease; margin-bottom: 2px; }
        .select-item:last-child { margin-bottom: 0; }
        .select-item:hover { background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); color: #4338ca; transform: translateX(4px); }
        .select-item.selected { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; box-shadow: 0 4px 12px rgba(102,126,234,0.3); }
        .select-item.selected:hover { transform: translateX(4px); color: #ffffff; }
        .select-items::-webkit-scrollbar { width: 6px; }
        .select-items::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .select-items::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 10px; }

        /* ===== BONUS INFO ===== */
        .register-bonus-info { display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; color: #92400e; font-size: 0.9rem; margin-top: 8px; }
        .register-bonus-info i { font-size: 1.25rem; }
        .register-bonus-info strong { color: #78350f; }

        /* ===== FOOTER TEXT ===== */
        .auth-footer-text { font-size: 0.9rem; color: #4a5568; margin-top: 16px; text-align: center; }
        .auth-footer-text a { color: #667eea; font-weight: 600; text-decoration: none; }
        .auth-footer-text a:hover { text-decoration: underline; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            .auth-card { padding: 24px 20px 20px; border-radius: 16px; }
            .auth-input, .select-selected { font-size: 16px; }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card">
        <a href="<?php echo htmlspecialchars($BASE); ?>/index.php" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>

        <h1 class="auth-title">Buat Akun Baru</h1>
        <p class="auth-subtitle">Daftar untuk mulai belajar bareng mentor terbaik</p>

        <?php if ($error): ?>
            <div class="alert alert-error" id="alert-error">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" id="alert-success">
                <i class="bi bi-gift-fill"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Google Sign Up Button -->
        <a href="<?php echo htmlspecialchars($BASE); ?>/google-auth.php?action=register" class="btn-google">
            <svg width="20" height="20" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Daftar dengan Google
        </a>

        <div class="auth-divider">
            <span>atau daftar dengan email</span>
        </div>

        <form method="POST" class="auth-form" autocomplete="off">
            <div class="form-group">
                <label for="name">Nama Lengkap</label>
                <input type="text" id="name" name="name" class="auth-input" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="Masukkan nama lengkap" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="auth-input" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="contoh@email.com" required>
            </div>

            <div class="form-group">
                <label>Program Studi</label>
                <div class="custom-select" data-name="program_studi">
                    <div class="select-selected">
                        <span class="select-text <?php echo !empty($_POST['program_studi']) ? 'has-value' : ''; ?>">
                            <?php echo !empty($_POST['program_studi']) ? htmlspecialchars($_POST['program_studi']) : 'Pilih Program Studi'; ?>
                        </span>
                        <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </div>
                    <div class="select-items">
                        <?php foreach ($programStudiList as $prodi): ?>
                            <div class="select-item <?php echo (($_POST['program_studi'] ?? '') === $prodi) ? 'selected' : ''; ?>" data-value="<?php echo htmlspecialchars($prodi); ?>">
                                <i class="bi bi-mortarboard-fill"></i>
                                <?php echo htmlspecialchars($prodi); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="program_studi" value="<?php echo htmlspecialchars($_POST['program_studi'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Semester</label>
                <div class="custom-select" data-name="semester">
                    <div class="select-selected">
                        <span class="select-text <?php echo !empty($_POST['semester']) ? 'has-value' : ''; ?>">
                            <?php echo !empty($_POST['semester']) ? 'Semester ' . htmlspecialchars((string)$_POST['semester']) : 'Pilih Semester'; ?>
                        </span>
                        <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </div>
                    <div class="select-items">
                        <?php for ($i = 1; $i <= 14; $i++): ?>
                            <div class="select-item <?php echo (($_POST['semester'] ?? '') == $i) ? 'selected' : ''; ?>" data-value="<?php echo $i; ?>">
                                <i class="bi bi-book-fill"></i>
                                Semester <?php echo $i; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="semester" value="<?php echo htmlspecialchars((string)($_POST['semester'] ?? '')); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="auth-input" placeholder="Minimal 6 karakter" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="auth-input" placeholder="Ulangi password" required>
            </div>

            <div class="register-bonus-info">
                <i class="bi bi-gift-fill"></i>
                <span>Daftar sekarang & dapatkan <strong>500 Gem gratis!</strong></span>
            </div>

            <button type="submit" class="btn btn-primary auth-button">Daftar Sekarang</button>
        </form>

        <p class="auth-footer-text">
            Sudah punya akun? <a href="<?php echo htmlspecialchars($BASE); ?>/login.php">Login di sini</a>
        </p>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ===== Custom Select Logic =====
        const customSelects = document.querySelectorAll('.custom-select');
        customSelects.forEach(select => {
            const selected = select.querySelector('.select-selected');
            const hiddenInput = select.querySelector('input[type="hidden"]');
            const selectText = select.querySelector('.select-text');

            selected.addEventListener('click', function(e) {
                e.stopPropagation();
                customSelects.forEach(s => { if (s !== select) s.classList.remove('active'); });
                select.classList.toggle('active');
            });

            select.querySelectorAll('.select-item').forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.dataset.value;
                    const clone = this.cloneNode(true);
                    const icon = clone.querySelector('i');
                    if (icon) icon.remove();
                    const text = clone.textContent.trim();

                    hiddenInput.value = value;
                    selectText.textContent = text;
                    selectText.classList.add('has-value');

                    select.querySelectorAll('.select-item').forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                    setTimeout(() => select.classList.remove('active'), 150);
                });
            });
        });

        document.addEventListener('click', () => customSelects.forEach(s => s.classList.remove('active')));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') customSelects.forEach(s => s.classList.remove('active')); });

        // ===== Auto-dismiss Alerts =====
        function autoDismissAlert(id, delay) {
            const alert = document.getElementById(id);
            if (alert) {
                setTimeout(function() {
                    alert.classList.add('fade-out');
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, delay);
            }
        }

        // Error alert: 5 detik
        autoDismissAlert('alert-error', 5000);
        
        // Success alert: 8 detik (lebih lama biar user sempet baca)
        autoDismissAlert('alert-success', 8000);
    });
    </script>
</body>
</html>
