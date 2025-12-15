<?php
require_once __DIR__ . '/../config.php';
require_once 'includes/NotificationHelper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_PATH . "/login");
    exit;
}

$userId = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: " . BASE_PATH . "/logout");
    exit;
}

$successMsg = '';
$errorMsg = '';
$notif = new NotificationHelper($pdo);

// Handle Profile Photo Upload
if (isset($_POST['update_photo'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            $errorMsg = 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.';
        } elseif ($file['size'] > $maxSize) {
            $errorMsg = 'Ukuran file maksimal 2MB.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            if ($user['avatar'] && file_exists(__DIR__ . '/../' . $user['avatar'])) {
                unlink(__DIR__ . '/../' . $user['avatar']);
            }
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $avatarPath = 'uploads/avatars/' . $filename;
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$avatarPath, $userId]);
                
                $_SESSION['avatar'] = $avatarPath;
                $user['avatar'] = $avatarPath;
                $successMsg = 'Foto profil berhasil diperbarui!';
                
                // Kirim notifikasi
                $notif->profileUpdated($userId, 'foto profil');
            } else {
                $errorMsg = 'Gagal mengupload foto. Silakan coba lagi.';
            }
        }
    } else {
        $errorMsg = 'Pilih foto terlebih dahulu.';
    }
}

// Handle Remove Photo
if (isset($_POST['remove_photo'])) {
    if ($user['avatar'] && file_exists(__DIR__ . '/../' . $user['avatar'])) {
        unlink(__DIR__ . '/../' . $user['avatar']);
    }
    
    $stmt = $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
    $stmt->execute([$userId]);
    
    $_SESSION['avatar'] = null;
    $user['avatar'] = null;
    $successMsg = 'Foto profil berhasil dihapus!';
    
    // Kirim notifikasi
    $notif->profileUpdated($userId, 'foto profil');
}

// Handle Update Name
if (isset($_POST['update_name'])) {
    $newName = trim($_POST['name'] ?? '');
    $oldName = $user['name'];
    
    if (empty($newName)) {
        $errorMsg = 'Nama tidak boleh kosong.';
    } elseif (strlen($newName) < 3) {
        $errorMsg = 'Nama minimal 3 karakter.';
    } elseif (strlen($newName) > 100) {
        $errorMsg = 'Nama maksimal 100 karakter.';
    } elseif ($newName === $oldName) {
        // Tidak ada perubahan, langsung redirect tanpa notifikasi
        header("Location: " . BASE_PATH . "/dashboard");
        exit;
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt->execute([$newName, $userId]);
        
        $_SESSION['name'] = $newName;
        
        // Kirim notifikasi
        $notif->profileUpdated($userId, 'nama');
        
        // Redirect ke dashboard
        header("Location: " . BASE_PATH . "/dashboard?profile_updated=1");
        exit;
    }
}

// Handle Change Password
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorMsg = 'Semua field password wajib diisi.';
    } elseif (!password_verify($currentPassword, $user['password'])) {
        $errorMsg = 'Password saat ini salah.';
    } elseif (strlen($newPassword) < 6) {
        $errorMsg = 'Password baru minimal 6 karakter.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMsg = 'Konfirmasi password tidak cocok.';
    } elseif ($currentPassword === $newPassword) {
        $errorMsg = 'Password baru harus berbeda dengan password saat ini.';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        $successMsg = 'Password berhasil diubah!';
        
        // Kirim notifikasi
        $notif->profileUpdated($userId, 'password');
    }
}

// Get gem balance
$gemBalance = $user['gems'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="settings-page">
    <?php include 'partials/navbar.php'; ?>

    <div class="settings-container">
        <div class="settings-header">
            <h1><i class="bi bi-gear"></i> Pengaturan Akun</h1>
            <p>Kelola profil dan keamanan akun kamu</p>
        </div>

        <?php if ($successMsg): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <?php echo $successMsg; ?>
        </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
        <div class="alert alert-error">
            <i class="bi bi-exclamation-circle"></i> <?php echo $errorMsg; ?>
        </div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Profile Photo Section -->
            <section class="settings-card">
                <div class="settings-card-header">
                    <h2><i class="bi bi-person-circle"></i> Foto Profil</h2>
                </div>
                <div class="settings-card-body">
                    <div class="profile-photo-section">
                        <div class="profile-photo-preview">
                            <?php if ($user['avatar']): ?>
                                <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($user['avatar']); ?>" alt="Avatar" id="avatarPreview">
                            <?php else: ?>
                                <div class="avatar-placeholder" id="avatarPlaceholder">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                                <img src="" alt="Avatar" id="avatarPreview" style="display: none;">
                            <?php endif; ?>
                        </div>
                        
                        <div class="profile-photo-info">
                            <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                            <p><?php echo htmlspecialchars($user['email']); ?></p>
                            
                            <form method="POST" enctype="multipart/form-data" class="photo-upload-form">
                                <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                                <div class="photo-buttons">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('avatarInput').click()">
                                        <i class="bi bi-upload"></i> Upload Foto
                                    </button>
                                    <?php if ($user['avatar']): ?>
                                    <button type="submit" name="remove_photo" class="btn btn-outline btn-sm btn-danger-outline">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" name="update_photo" id="savePhotoBtn" class="btn btn-success btn-sm" style="display: none;">
                                    <i class="bi bi-check"></i> Simpan Foto
                                </button>
                            </form>
                            
                            <p class="photo-hint">Format: JPG, PNG, GIF, WebP. Maksimal 2MB.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Update Name Section -->
            <section class="settings-card">
                <div class="settings-card-header">
                    <h2><i class="bi bi-person-badge"></i> Informasi Profil</h2>
                </div>
                <div class="settings-card-body">
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label for="name">Nama Lengkap</label>
                            <input type="text" id="name" name="name" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" 
                                   placeholder="Masukkan nama lengkap" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <span class="form-hint">Email tidak dapat diubah.</span>
                        </div>

                        <div class="form-group">
                            <label>Program Studi</label>
                            <input type="text" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['program_studi'] ?? '-'); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label>Semester</label>
                            <input type="text" class="form-input" 
                                   value="<?php echo $user['semester'] ?? '-'; ?>" disabled>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_name" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Change Password Section -->
            <section class="settings-card">
                <div class="settings-card-header">
                    <h2><i class="bi bi-shield-lock"></i> Ubah Password</h2>
                </div>
                <div class="settings-card-body">
                    <form method="POST" class="settings-form" id="passwordForm">
                        <div class="form-group">
                            <label for="current_password">Password Saat Ini</label>
                            <div class="input-password-wrapper">
                                <input type="password" id="current_password" name="current_password" 
                                       class="form-input" placeholder="Masukkan password saat ini" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <div class="input-password-wrapper">
                                <input type="password" id="new_password" name="new_password" 
                                       class="form-input" placeholder="Minimal 6 karakter" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password Baru</label>
                            <div class="input-password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="form-input" placeholder="Ulangi password baru" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <span class="form-hint" id="passwordMatch"></span>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="bi bi-key"></i> Ubah Password
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Account Stats Section -->
            <section class="settings-card">
                <div class="settings-card-header">
                    <h2><i class="bi bi-bar-chart"></i> Statistik Akun</h2>
                </div>
                <div class="settings-card-body">
                    <div class="account-stats">
                        <div class="stat-item">
                            <div class="stat-icon gem">
                                <i class="bi bi-gem"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value"><?php echo number_format($gemBalance, 0, ',', '.'); ?></span>
                                <span class="stat-label">Total Gem</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon role">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value"><?php echo ucfirst($user['role']); ?></span>
                                <span class="stat-label">Role</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon date">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value"><?php echo date('d M Y', strtotime($user['created_at'])); ?></span>
                                <span class="stat-label">Bergabung</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
    // Avatar preview
    document.getElementById('avatarInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('avatarPreview');
                const placeholder = document.getElementById('avatarPlaceholder');
                
                preview.src = e.target.result;
                preview.style.display = 'block';
                if (placeholder) placeholder.style.display = 'none';
                
                document.getElementById('savePhotoBtn').style.display = 'inline-flex';
            };
            reader.readAsDataURL(file);
        }
    });

    // Toggle password visibility
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }

    // Password strength indicator
    document.getElementById('new_password').addEventListener('input', function() {
        const password = this.value;
        const strengthDiv = document.getElementById('passwordStrength');
        
        let strength = 0;
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        const levels = ['', 'Lemah', 'Cukup', 'Baik', 'Kuat', 'Sangat Kuat'];
        const colors = ['', '#ef4444', '#f59e0b', '#eab308', '#22c55e', '#10b981'];
        
        if (password.length > 0) {
            strengthDiv.innerHTML = `<span style="color: ${colors[strength]}">Kekuatan: ${levels[strength]}</span>`;
        } else {
            strengthDiv.innerHTML = '';
        }
    });

    // Password match checker
    document.getElementById('confirm_password').addEventListener('input', function() {
        const newPass = document.getElementById('new_password').value;
        const confirmPass = this.value;
        const matchDiv = document.getElementById('passwordMatch');
        
        if (confirmPass.length > 0) {
            if (newPass === confirmPass) {
                matchDiv.innerHTML = '<span style="color: #22c55e"><i class="bi bi-check-circle"></i> Password cocok</span>';
            } else {
                matchDiv.innerHTML = '<span style="color: #ef4444"><i class="bi bi-x-circle"></i> Password tidak cocok</span>';
            }
        } else {
            matchDiv.innerHTML = '';
        }
    });

    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    </script>
</body>
</html>
