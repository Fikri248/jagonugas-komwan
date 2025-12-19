<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mentor') {
    header('Location: ' . BASE_PATH . '/mentor-login.php');
    exit;
}

$mentor_id = $_SESSION['user_id'];

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$error = '';
$success = '';

// Ambil data mentor
$stmt = $pdo->prepare("
    SELECT id, name, email, avatar, program_studi, specialization, hourly_rate,
           bio, expertise
    FROM users 
    WHERE id = ? AND role = 'mentor'
");
$stmt->execute([$mentor_id]);
$mentor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mentor) {
    die('Mentor tidak ditemukan.');
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        if ($action === 'update_profile') {
            $name           = trim($_POST['name'] ?? '');
            $email          = trim($_POST['email'] ?? '');
            $program_studi  = trim($_POST['program_studi'] ?? '');
            $specialization = trim($_POST['specialization'] ?? '');
            $hourly_rate    = (int)($_POST['hourly_rate'] ?? 1500);
            $bio            = trim($_POST['bio'] ?? '');
            $expertise      = trim($_POST['expertise'] ?? '');

            if (!$name || !$email || !$program_studi) {
                throw new Exception('Nama, email, dan program studi wajib diisi.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format email tidak valid.');
            }

            // Cek email duplikat
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $mentor_id]);
            if ($stmt->fetch()) {
                throw new Exception('Email sudah digunakan oleh user lain.');
            }

            // Handle avatar
            $avatar_path = $mentor['avatar'];
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowed   = ['jpg','jpeg','png','gif'];
                $filename  = $_FILES['avatar']['name'];
                $ext       = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    throw new Exception('Format avatar harus JPG, PNG, atau GIF.');
                }

                if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                    throw new Exception('Ukuran avatar maksimal 2MB.');
                }

                $upload_dir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $new_filename = 'mentor_' . $mentor_id . '_' . time() . '.' . $ext;
                $upload_path  = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    if ($mentor['avatar'] && file_exists(__DIR__ . '/' . $mentor['avatar'])) {
                        @unlink(__DIR__ . '/' . $mentor['avatar']);
                    }
                    $avatar_path = 'uploads/avatars/' . $new_filename;
                }
            }

            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, email = ?, avatar = ?, program_studi = ?, 
                    specialization = ?, hourly_rate = ?, bio = ?, expertise = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $email, $avatar_path, $program_studi,
                $specialization, $hourly_rate, $bio, $expertise, $mentor_id
            ]);

            $_SESSION['name'] = $name;

            // sinkron ke array lokal
            $mentor['name']           = $name;
            $mentor['email']          = $email;
            $mentor['avatar']         = $avatar_path;
            $mentor['program_studi']  = $program_studi;
            $mentor['specialization'] = $specialization;
            $mentor['hourly_rate']    = $hourly_rate;
            $mentor['bio']            = $bio;
            $mentor['expertise']      = $expertise;

            $success = 'Profil berhasil diperbarui.';
        } elseif ($action === 'change_password') {
            $current_password  = $_POST['current_password'] ?? '';
            $new_password      = $_POST['new_password'] ?? '';
            $confirm_password  = $_POST['confirm_password'] ?? '';

            if (!$current_password || !$new_password || !$confirm_password) {
                throw new Exception('Semua field password wajib diisi.');
            }

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$mentor_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !password_verify($current_password, $row['password'])) {
                throw new Exception('Password lama salah.');
            }

            if ($new_password !== $confirm_password) {
                throw new Exception('Password baru dan konfirmasi tidak cocok.');
            }

            if (strlen($new_password) < 6) {
                throw new Exception('Password minimal 6 karakter.');
            }

            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $mentor_id]);

            $success = 'Password berhasil diubah.';
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun Mentor - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style-mentor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .settings-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1.5rem 2.5rem;
        }
        .settings-container .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem 1.5rem;
        }
        .settings-container .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .settings-container .form-group label {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.9rem;
        }
        .settings-container .input-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.6rem 0.8rem;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .settings-container .input-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.25);
        }
        .settings-container .textarea-control {
            min-height: 90px;
            resize: vertical;
        }
        .settings-container .section-card {
            background: #ffffff;
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            padding: 1.75rem;
            margin-bottom: 2rem;
        }
        .settings-container .section-title {
            margin: 0 0 1rem;
            font-size: 1.15rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1a202c;
        }
        .settings-container .error-message,
        .settings-container .success-message {
            padding: 0.9rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.95rem;
        }
        .settings-container .error-message {
            background: #fef2f2;
            border: 2px solid #fecaca;
            color: #b91c1c;
        }
        .settings-container .success-message {
            background: #ecfdf5;
            border: 2px solid #bbf7d0;
            color: #166534;
        }
    </style>
</head>
<body>
    <?php // include 'mentor-navbar.php'; ?>

    <div class="settings-container">
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
            <a href="<?php echo BASE_PATH; ?>/mentor-dashboard.php"
               class="btn btn-outline"
               style="padding:0.5rem 0.9rem;font-size:0.85rem;display:inline-flex;align-items:center;gap:0.4rem;">
                <i class="bi bi-arrow-left"></i>
                Dashboard
            </a>
            <h1 style="margin:0;font-size:1.6rem;color:#1a202c;">
                <i class="bi bi-gear-fill"></i>
                Pengaturan Akun Mentor
            </h1>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="bi bi-check-circle-fill"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- PROFIL & INFO PROFESIONAL -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="bi bi-person-badge"></i>
                Profil & Informasi Profesional
            </h2>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">

                <!-- Avatar -->
                <div style="text-align:center;margin-bottom:1.5rem;">
                    <div style="width:120px;height:120px;margin:0 auto 1rem;border-radius:50%;overflow:hidden;border:4px solid #667eea;box-shadow:0 4px 12px rgba(102,126,234,0.35);">
                        <?php if (!empty($mentor['avatar'])): ?>
                            <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($mentor['avatar']); ?>"
                                 alt="Avatar"
                                 style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#667eea,#764ba2);color:white;font-size:3rem;font-weight:700;">
                                <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <label for="avatar" style="cursor:pointer;color:#667eea;font-weight:600;display:inline-flex;align-items:center;gap:0.4rem;">
                        <i class="bi bi-camera-fill"></i>
                        Ganti Foto Profil
                    </label>
                    <input type="file" id="avatar" name="avatar" accept="image/*" style="display:none;">
                    <div id="avatar-filename" style="font-size:0.85rem;color:#718096;margin-top:0.3rem;"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap *</label>
                        <input type="text" name="name" class="input-control"
                               value="<?php echo htmlspecialchars($mentor['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="input-control"
                               value="<?php echo htmlspecialchars($mentor['email']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Program Studi *</label>
                        <input type="text" name="program_studi" class="input-control"
                               value="<?php echo htmlspecialchars($mentor['program_studi']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Spesialisasi</label>
                        <input type="text" name="specialization" class="input-control"
                               value="<?php echo htmlspecialchars($mentor['specialization'] ?? ''); ?>"
                               placeholder="Misal: Pemrograman Web, Database">
                    </div>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label>Hourly Rate (Gems)</label>
                    <input type="number" name="hourly_rate" class="input-control"
                           value="<?php echo (int)$mentor['hourly_rate']; ?>" min="0">
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label>Bio</label>
                    <textarea name="bio" class="input-control textarea-control"
                              placeholder="Ceritakan singkat tentang diri Anda..."><?php echo htmlspecialchars($mentor['bio'] ?? ''); ?></textarea>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label>Keahlian (expertise)</label>
                    <textarea name="expertise" class="input-control textarea-control"
                              placeholder="Pisahkan dengan koma: PHP, Laravel, React, Database..."><?php echo htmlspecialchars($mentor['expertise'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-full" style="margin-top:1.5rem;">
                    <i class="bi bi-save"></i>
                    Simpan Perubahan
                </button>
            </form>
        </div>

        <!-- GANTI PASSWORD -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="bi bi-lock-fill"></i>
                Ganti Password
            </h2>

            <form method="POST">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label>Password Lama *</label>
                    <input type="password" name="current_password" class="input-control" required>
                </div>

                <div class="form-row" style="margin-top:0.8rem;">
                    <div class="form-group">
                        <label>Password Baru *</label>
                        <input type="password" name="new_password" class="input-control" required>
                    </div>
                    <div class="form-group">
                        <label>Konfirmasi Password Baru *</label>
                        <input type="password" name="confirm_password" class="input-control" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full" style="margin-top:1.3rem;">
                    <i class="bi bi-shield-lock"></i>
                    Ubah Password
                </button>
            </form>
        </div>
    </div>

    <script>
    const avatarInput = document.getElementById('avatar');
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const f = e.target.files[0];
            if (f) {
                document.getElementById('avatar-filename').textContent = '📁 ' + f.name;
            }
        });
    }

    ['.success-message','.error-message'].forEach(sel => {
        const el = document.querySelector(sel);
        if (el) {
            setTimeout(() => {
                el.style.transition = 'all 0.3s';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-8px)';
                setTimeout(() => el.remove(), 300);
            }, 5000);
        }
    });
    </script>
</body>
</html>
