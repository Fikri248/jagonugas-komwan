<?php
// pages/forgot-password.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../ModelUser.php';

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
            $resetLink = BASE_URL . BASE_PATH . "/reset-password?token=" . $result['token'];
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
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <a href="<?php echo BASE_PATH; ?>/login" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>

        <h1 class="auth-title">Lupa Password?</h1>
        <p class="auth-subtitle">Masukkan email lo untuk reset password</p>

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

        <?php if ($success && $resetLink): ?>
            <div class="alert alert-success">
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
                <a href="<?php echo $resetLink; ?>" class="btn btn-primary btn-full">
                    Reset Password Sekarang
                </a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="auth-input" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="contoh@student.telkomuniversity.ac.id" required>
            </div>

            <button type="submit" class="btn btn-primary auth-button">Kirim Link Reset</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
