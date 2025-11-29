<?php
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'ModelUser.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = (new Database())->getConnection();
    $user = new User($db);
    $user->email = $_POST['email'] ?? '';
    $user->password = $_POST['password'] ?? '';

    if ($user->login()) {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['name']    = $user->name;

        header('Location: ' . BASE_PATH . '/dashboard.php');
        exit;
    } else {
        $error = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <h1 class="auth-title">Login Akun</h1>
        <p class="auth-subtitle">Masuk untuk lanjut ngobrol sama mentor dan liat riwayat chat lo.</p>

        <?php if (!empty($error)): ?>
            <p style="color:#e53e3e; font-size:0.9rem; margin-bottom:12px;">
                <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="" class="auth-form">
            <div>
                <label for="email">Email</label>
                <input class="auth-input" type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Password</label>
                <input class="auth-input" type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary auth-button">Login</button>
        </form>

        <p class="auth-footer-text">
            Belum punya akun?
            <a href="<?php echo BASE_PATH; ?>/register.php">Daftar</a>
        </p>
    </div>
</body>
</html>
