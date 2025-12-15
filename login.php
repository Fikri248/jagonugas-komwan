<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ModelUser.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    if ($role === 'admin') {
        header("Location: " . BASE_PATH . "/admin-dashboard.php");
    } elseif ($role === 'mentor') {
        header("Location: " . BASE_PATH . "/mentor-dashboard.php");
    } else {
        header("Location: " . BASE_PATH . "/student-dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = (new Database())->getConnection();
    $user = new User($db);

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi';
    } else {
        $user->email = $email;
        $user->password = $password;

        $result = $user->login();

        if ($result['success']) {
            // Cek jika mentor belum diverifikasi
            if ($result['user']['role'] === 'mentor' && !$result['user']['is_verified']) {
                $error = 'Akun mentor belum diverifikasi. Tunggu konfirmasi dari admin.';
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $result['user']['id'];
                $_SESSION['name'] = $result['user']['name'];
                $_SESSION['email'] = $result['user']['email'];
                $_SESSION['role'] = $result['user']['role'];
                $_SESSION['login_time'] = time();
                $_SESSION['avatar'] = $result['user']['avatar'] ?? null;

                // Redirect berdasarkan role
                switch ($result['user']['role']) {
                    case 'admin':
                        header("Location: " . BASE_PATH . "/admin-dashboard.php");
                        break;
                    case 'mentor':
                        header("Location: " . BASE_PATH . "/mentor-dashboard.php");
                        break;
                    default:
                        header("Location: " . BASE_PATH . "/student-dashboard.php");
                }
                exit;
            }
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
    <title>Login - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <a href="<?php echo BASE_PATH; ?>/index.php" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>

        <h1 class="auth-title">Selamat Datang Kembali</h1>
        <p class="auth-subtitle">Masuk ke akun JagoNugas lo</p>

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

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="auth-input" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                       placeholder="contoh@student.telkomuniversity.ac.id" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="auth-input" 
                       placeholder="Masukkan password" required>
            </div>

            <div class="form-group" style="text-align: right;">
                <a href="<?php echo BASE_PATH; ?>/forgot-password.php" class="auth-link">Lupa password?</a>
            </div>

            <button type="submit" class="btn btn-primary auth-button">Login</button>
        </form>

        <p class="auth-footer-text">
            Belum punya akun? <a href="<?php echo BASE_PATH; ?>/register.php">Daftar gratis</a>
        </p>

        <div class="auth-divider">
            <span>atau</span>
        </div>

        <a href="<?php echo BASE_PATH; ?>/mentor-login.php" class="btn-mentor-login">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Login sebagai Mentor
        </a>
    </div>
</body>
</html>
