<?php
require_once 'config.php';
require 'db.php';
require 'ModelUser.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = (new Database())->getConnection();
    $user = new User($db);
    $user->name = $_POST['name'];
    $user->email = $_POST['email'];
    $user->password = $_POST['password'];
    $user->program_studi = $_POST['program_studi'];
    $user->semester = $_POST['semester'];
    $user->role = 'student';

    if ($user->register()) {
        header("Location: " . BASE_PATH . "/login");
        exit;
    } else {
        $error = "Registrasi gagal. Coba lagi.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <h1 class="auth-title">Daftar Akun</h1>
        <p class="auth-subtitle">Buat akun baru dan mulai cari kakak tingkat buat bantuin nugas lo.</p>

        <?php if (!empty($error)): ?>
            <p style="color:#e53e3e; font-size:0.9rem; margin-bottom:12px;">
                <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div>
                <label for="name">Nama</label>
                <input class="auth-input" type="text" id="name" name="name" required>
            </div>
            <div>
                <label for="email">Email</label>
                <input class="auth-input" type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Password</label>
                <input class="auth-input" type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="program_studi">Program Studi</label>
                <input class="auth-input" type="text" id="program_studi" name="program_studi" required>
            </div>
            <div>
                <label for="semester">Semester</label>
                <input class="auth-input" type="number" id="semester" name="semester" min="1" max="14" required>
            </div>
            <button type="submit" class="btn btn-primary auth-button">Daftar</button>
        </form>

        <p class="auth-footer-text">
            Sudah punya akun?
            <a href="<?php echo BASE_PATH; ?>/login">Login</a>
        </p>
        <p class="auth-note">Dengan daftar, lo setuju sama ketentuan penggunaan JagoNugas.</p>
    </div>
</body>
</html>
