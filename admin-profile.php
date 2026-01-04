<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function url_path(string $path = ''): string {
    $base = defined('BASE_PATH') ? (string) constant('BASE_PATH') : '';
    $path = '/' . ltrim($path, '/');
    return rtrim($base, '/') . $path;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url_path('login.php'));
    exit;
}

$admin_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // Validate
        if (empty($name) || empty($email)) {
            $error = 'Nama dan email wajib diisi!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid!';
        } else {
            // Check email duplicate (exclude current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $admin_id]);
            
            if ($stmt->fetch()) {
                $error = 'Email sudah digunakan oleh user lain!';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, phone = ?, bio = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $email, $phone, $bio, $admin_id]);
                    
                    // Update session
                    $_SESSION['name'] = $name;
                    $_SESSION['email'] = $email;
                    
                    $success = 'Profile berhasil diperbarui!';
                } catch (Exception $e) {
                    $error = 'Gagal memperbarui profile: ' . $e->getMessage();
                }
            }
        }
    }
    
    // Handle Password Change
    if ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Get current password hash
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$admin_id]);
        $user = $stmt->fetch();
        
        if (!password_verify($current_password, $user['password'])) {
            $error = 'Password lama tidak sesuai!';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password baru minimal 6 karakter!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru dan konfirmasi tidak cocok!';
        } else {
            try {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed, $admin_id]);
                
                $success = 'Password berhasil diubah!';
            } catch (Exception $e) {
                $error = 'Gagal mengubah password: ' . $e->getMessage();
            }
        }
    }
}

// Get admin data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - JagoNugas Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 0;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #718096;
            margin: 0;
        }

        .profile-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }

        .card-header-custom h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body-custom {
            padding: 2rem;
        }

        .avatar-section {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .avatar-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            font-weight: 700;
            color: white;
            border: 4px solid white;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .form-group-custom {
            margin-bottom: 1.5rem;
        }

        .form-group-custom label {
            display: block;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control-custom {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control-custom {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .alert-custom {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 2px solid #10b981;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 2px solid #dc2626;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }

        .info-item i {
            font-size: 1.25rem;
            color: #667eea;
        }

        .info-item .label {
            font-weight: 600;
            color: #4a5568;
            min-width: 100px;
        }

        .info-item .value {
            color: #1a202c;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin-navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="bi bi-person-circle"></i> Profile Admin</h1>
            <p>Kelola informasi akun dan keamanan</p>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
        <div class="alert-custom alert-success">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-custom alert-error">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Profile Info -->
        <div class="profile-card">
            <div class="card-header-custom">
                <h3><i class="bi bi-person-badge"></i> Informasi Profile</h3>
            </div>
            <div class="card-body-custom">
                <div class="avatar-section">
                    <div class="avatar-circle">
                        <?= strtoupper(substr($admin['name'], 0, 1)) ?>
                    </div>
                    <h4 style="margin-bottom: 0.5rem; font-weight: 700; color: #1a202c;"><?= htmlspecialchars($admin['name']) ?></h4>
                    <p style="color: #718096; font-size: 0.9rem; margin: 0;">
                        <i class="bi bi-shield-check"></i> Administrator
                    </p>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-row">
                        <div class="form-group-custom">
                            <label><i class="bi bi-person"></i> Nama Lengkap</label>
                            <input type="text" 
                                   name="name" 
                                   class="form-control-custom" 
                                   value="<?= htmlspecialchars($admin['name']) ?>" 
                                   required>
                        </div>

                        <div class="form-group-custom">
                            <label><i class="bi bi-envelope"></i> Email</label>
                            <input type="email" 
                                   name="email" 
                                   class="form-control-custom" 
                                   value="<?= htmlspecialchars($admin['email']) ?>" 
                                   required>
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label><i class="bi bi-phone"></i> Nomor Telepon</label>
                        <input type="text" 
                               name="phone" 
                               class="form-control-custom" 
                               value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" 
                               placeholder="Opsional">
                    </div>

                    <div class="form-group-custom">
                        <label><i class="bi bi-chat-left-text"></i> Bio</label>
                        <textarea name="bio" 
                                  class="form-control-custom" 
                                  placeholder="Ceritakan tentang diri Anda..."><?= htmlspecialchars($admin['bio'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-save">
                        <i class="bi bi-save"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="profile-card">
            <div class="card-header-custom">
                <h3><i class="bi bi-shield-lock"></i> Ubah Password</h3>
            </div>
            <div class="card-body-custom">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group-custom">
                        <label><i class="bi bi-key"></i> Password Lama</label>
                        <input type="password" 
                               name="current_password" 
                               class="form-control-custom" 
                               required>
                    </div>

                    <div class="form-row">
                        <div class="form-group-custom">
                            <label><i class="bi bi-lock"></i> Password Baru</label>
                            <input type="password" 
                                   name="new_password" 
                                   class="form-control-custom" 
                                   minlength="6"
                                   required>
                            <small style="color: #718096; font-size: 0.85rem;">Minimal 6 karakter</small>
                        </div>

                        <div class="form-group-custom">
                            <label><i class="bi bi-lock-fill"></i> Konfirmasi Password Baru</label>
                            <input type="password" 
                                   name="confirm_password" 
                                   class="form-control-custom" 
                                   minlength="6"
                                   required>
                        </div>
                    </div>

                    <button type="submit" class="btn-save">
                        <i class="bi bi-shield-check"></i>
                        <span>Ubah Password</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Account Info -->
        <div class="profile-card">
            <div class="card-header-custom">
                <h3><i class="bi bi-info-circle"></i> Informasi Akun</h3>
            </div>
            <div class="card-body-custom">
                <div class="info-item">
                    <i class="bi bi-calendar-check"></i>
                    <span class="label">Bergabung:</span>
                    <span class="value"><?= date('d F Y', strtotime($admin['created_at'])) ?></span>
                </div>

                <div class="info-item">
                    <i class="bi bi-clock-history"></i>
                    <span class="label">Terakhir Update:</span>
                    <span class="value"><?= date('d F Y, H:i', strtotime($admin['updated_at'] ?? $admin['created_at'])) ?></span>
                </div>

                <div class="info-item">
                    <i class="bi bi-shield-fill-check"></i>
                    <span class="label">Status:</span>
                    <span class="value">
                        <span style="display: inline-flex; align-items: center; gap: 0.5rem; background: #d1fae5; color: #065f46; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600; font-size: 0.85rem;">
                            <i class="bi bi-check-circle-fill"></i> Active
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert-custom').forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                alert.style.transition = 'all 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Password confirmation validation
        const form = document.querySelector('form[action=""][method="POST"]');
        if (form && form.querySelector('input[name="confirm_password"]')) {
            form.addEventListener('submit', function(e) {
                const newPass = this.querySelector('input[name="new_password"]').value;
                const confirmPass = this.querySelector('input[name="confirm_password"]').value;
                
                if (newPass !== confirmPass) {
                    e.preventDefault();
                    alert('Password baru dan konfirmasi tidak cocok!');
                }
            });
        }
    </script>
</body>
</html>
