<?php
// pages/register.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../ModelUser.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';

$error = '';
$success = '';

// Daftar Program Studi Telkom University Surabaya
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
    $db = (new Database())->getConnection();
    $user = new User($db);

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $programStudi = trim($_POST['program_studi'] ?? '');
    $semester = intval($_POST['semester'] ?? 0);

    if (empty($name) || empty($email) || empty($password) || empty($programStudi) || $semester < 1) {
        $error = 'Semua field wajib diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok';
    } else {
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->program_studi = $programStudi;
        $user->semester = $semester;

        $result = $user->register();

        if ($result['success']) {
            $newUserId = $result['user_id'];
            $bonusGems = $result['gems'] ?? 75;
            
            // Kirim notifikasi
            $notif = new NotificationHelper($db);
            
            // 1. Notifikasi Welcome
            $notif->welcome($newUserId);
            
            // 2. Notifikasi Bonus Gem
            $notif->create(
                $newUserId,
                'gem_bonus',
                'Selamat! Kamu mendapat ' . $bonusGems . ' Gem gratis sebagai hadiah pendaftaran. Gunakan untuk bertanya di forum!',
                null,
                null
            );
            
            $success = 'Registrasi berhasil! Kamu mendapat ' . $bonusGems . ' Gem gratis. Silakan login.';
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
    <title>Daftar - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <!-- Tombol Kembali -->
        <a href="<?php echo BASE_PATH; ?>/" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>

        <h1 class="auth-title">Buat Akun Baru</h1>
        <p class="auth-subtitle">Daftar untuk mulai belajar bareng mentor terbaik</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-gift-fill"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="name">Nama Lengkap</label>
                <input type="text" id="name" name="name" class="auth-input" 
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                       placeholder="Masukkan nama lengkap" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="auth-input" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                       placeholder="contoh@student.telkomuniversity.ac.id" required>
            </div>

            <!-- Program Studi Custom Dropdown -->
            <div class="form-group">
                <label>Program Studi</label>
                <div class="custom-select" data-name="program_studi">
                    <div class="select-selected">
                        <span class="select-text <?php echo !empty($_POST['program_studi']) ? 'has-value' : ''; ?>">
                            <?php echo !empty($_POST['program_studi']) ? htmlspecialchars($_POST['program_studi']) : 'Pilih Program Studi'; ?>
                        </span>
                        <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </div>
                    <div class="select-items">
                        <?php foreach ($programStudiList as $prodi): ?>
                            <div class="select-item <?php echo (($_POST['program_studi'] ?? '') === $prodi) ? 'selected' : ''; ?>" 
                                 data-value="<?php echo $prodi; ?>">
                                <span class="item-icon">🎓</span>
                                <?php echo $prodi; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="program_studi" value="<?php echo htmlspecialchars($_POST['program_studi'] ?? ''); ?>" required>
                </div>
            </div>

            <!-- Semester Custom Dropdown -->
            <div class="form-group">
                <label>Semester</label>
                <div class="custom-select" data-name="semester">
                    <div class="select-selected">
                        <span class="select-text <?php echo !empty($_POST['semester']) ? 'has-value' : ''; ?>">
                            <?php echo !empty($_POST['semester']) ? 'Semester ' . htmlspecialchars($_POST['semester']) : 'Pilih Semester'; ?>
                        </span>
                        <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </div>
                    <div class="select-items">
                        <?php for ($i = 1; $i <= 14; $i++): ?>
                            <div class="select-item <?php echo (($_POST['semester'] ?? '') == $i) ? 'selected' : ''; ?>" 
                                 data-value="<?php echo $i; ?>">
                                <span class="item-icon">📚</span>
                                Semester <?php echo $i; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="semester" value="<?php echo htmlspecialchars($_POST['semester'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="auth-input" 
                       placeholder="Minimal 6 karakter" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="auth-input" 
                       placeholder="Ulangi password" required>
            </div>

            <!-- Bonus Info -->
            <div class="register-bonus-info">
                <i class="bi bi-gift-fill"></i>
                <span>Daftar sekarang & dapatkan <strong>75 Gem gratis!</strong></span>
            </div>

            <button type="submit" class="btn btn-primary auth-button">Daftar Sekarang</button>
        </form>

        <p class="auth-footer-text">
            Sudah punya akun? <a href="<?php echo BASE_PATH; ?>/login">Login di sini</a>
        </p>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const customSelects = document.querySelectorAll('.custom-select');
        
        customSelects.forEach(select => {
            const selected = select.querySelector('.select-selected');
            const items = select.querySelector('.select-items');
            const hiddenInput = select.querySelector('input[type="hidden"]');
            const selectText = select.querySelector('.select-text');
            
            selected.addEventListener('click', function(e) {
                e.stopPropagation();
                customSelects.forEach(s => {
                    if (s !== select) s.classList.remove('active');
                });
                select.classList.toggle('active');
            });
            
            const selectItems = select.querySelectorAll('.select-item');
            selectItems.forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.dataset.value;
                    const text = this.textContent.trim();
                    hiddenInput.value = value;
                    selectText.textContent = text;
                    selectText.classList.add('has-value');
                    selectItems.forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                    setTimeout(() => {
                        select.classList.remove('active');
                    }, 150);
                });
            });
        });
        
        document.addEventListener('click', function() {
            customSelects.forEach(select => {
                select.classList.remove('active');
            });
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                customSelects.forEach(select => {
                    select.classList.remove('active');
                });
            }
        });
    });
    </script>
</body>
</html>
