<?php
// reset-password.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';

// Defensive: fallback kalau BASE_PATH ga ke-define
$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: " . $BASE . "/login.php");
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
    <link rel="stylesheet" href="<?php echo $BASE; ?>/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <h1 class="auth-title">Reset Password</h1>
        <p class="auth-subtitle">Masukkan password baru lo</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
            
            <div class="auth-footer-actions">
                <a href="<?php echo $BASE; ?>/login.php" class="btn btn-primary btn-full">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Login Sekarang
                </a>
            </div>
        <?php else: ?>
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="password">Password Baru</label>
                    <input type="password" id="password" name="password" class="auth-input" 
                           placeholder="Minimal 6 karakter" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="auth-input" 
                           placeholder="Ulangi password baru" required>
                </div>

                <!-- Tombol Group: Ubah + Batal -->
                <div class="auth-btn-group">
                    <button type="submit" class="btn btn-primary">Ubah Password</button>
                    <a href="<?php echo $BASE; ?>/login.php" class="btn btn-cancel">Batal</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
