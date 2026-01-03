<?php
// mentor-settings.php v3.0 - Modern, Clean Settings (Inspired by student-settings.php)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mentor') {
    header('Location: ' . $BASE . '/mentor-login.php');
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
$notif = new NotificationHelper($pdo);

// Ambil data mentor
$stmt = $pdo->prepare("
    SELECT id, name, email, avatar, program_studi, semester, specialization, 
           hourly_rate, bio, expertise, google_id, created_at
    FROM users 
    WHERE id = ? AND role = 'mentor'
");
$stmt->execute([$mentor_id]);
$mentor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mentor) {
    die('Mentor tidak ditemukan.');
}

// Helper: Get Avatar URL
function get_avatar_url($avatar, $base = '') {
    if (empty($avatar)) return '';
    if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
    return $base . '/' . ltrim($avatar, '/');
}

$avatarUrl = get_avatar_url($mentor['avatar'] ?? '', $BASE);
$isGoogleAvatar = !empty($mentor['avatar']) && filter_var($mentor['avatar'], FILTER_VALIDATE_URL);
$isOAuthUser = !empty($mentor['google_id']);

// ===== Handle Profile Photo Upload =====
if (isset($_POST['update_photo'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024;
        
        if (!in_array($file['type'], $allowedTypes)) {
            $error = 'Format file tidak didukung (gunakan JPG, PNG, GIF, atau WebP).';
        } elseif ($file['size'] > $maxSize) {
            $error = 'Ukuran file maksimal 2MB.';
        } else {
            $uploadDir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'mentor_' . $mentor_id . '_' . time() . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            // Hapus foto lama
            if ($mentor['avatar'] && !filter_var($mentor['avatar'], FILTER_VALIDATE_URL) && file_exists(__DIR__ . '/' . $mentor['avatar'])) {
                @unlink(__DIR__ . '/' . $mentor['avatar']);
            }
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $avatarPath = 'uploads/avatars/' . $filename;
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$avatarPath, $mentor_id]);
                
                $_SESSION['avatar'] = $avatarPath;
                $mentor['avatar'] = $avatarPath;
                $avatarUrl = get_avatar_url($avatarPath, $BASE);
                $isGoogleAvatar = false;
                $success = 'Foto profil berhasil diperbarui!';
                $notif->profileUpdated($mentor_id, 'foto profil');
            } else {
                $error = 'Gagal mengupload foto.';
            }
        }
    } else {
        $error = 'Pilih foto terlebih dahulu.';
    }
}

// ===== Handle Remove Photo =====
if (isset($_POST['remove_photo'])) {
    if ($mentor['avatar'] && !filter_var($mentor['avatar'], FILTER_VALIDATE_URL) && file_exists(__DIR__ . '/' . $mentor['avatar'])) {
        @unlink(__DIR__ . '/' . $mentor['avatar']);
    }
    $stmt = $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
    $stmt->execute([$mentor_id]);
    $_SESSION['avatar'] = null;
    $mentor['avatar'] = null;
    $avatarUrl = '';
    $isGoogleAvatar = false;
    $success = 'Foto profil berhasil dihapus!';
    $notif->profileUpdated($mentor_id, 'foto profil');
}

// ===== Handle Update Profile (Nama & Bio saja) =====
if (isset($_POST['update_profile'])) {
    $newName = trim($_POST['name'] ?? '');
    $newBio = trim($_POST['bio'] ?? '');
    
    $errors = [];
    
    if (empty($newName)) {
        $errors[] = 'Nama tidak boleh kosong.';
    } elseif (strlen($newName) < 3) {
        $errors[] = 'Nama minimal 3 karakter.';
    }
    
    if (!empty($errors)) {
        $error = implode(' ', $errors);
    } else {
        $hasChanges = false;
        $updates = [];
        $params = [];
        
        if ($newName !== $mentor['name']) {
            $updates[] = "name = ?";
            $params[] = $newName;
            $hasChanges = true;
        }
        
        $currentBio = $mentor['bio'] ?? '';
        if ($newBio !== $currentBio) {
            $updates[] = "bio = ?";
            $params[] = $newBio ?: null;
            $hasChanges = true;
        }
        
        if ($hasChanges && !empty($updates)) {
            $params[] = $mentor_id;
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['name'] = $newName;
            $mentor['name'] = $newName;
            $mentor['bio'] = $newBio;
            
            $notif->profileUpdated($mentor_id, 'profil');
            $success = 'Profil berhasil diperbarui!';
        } else {
            $success = 'Tidak ada perubahan.';
        }
    }
}

// ===== Handle Change Password =====
if (isset($_POST['change_password'])) {
    if ($isOAuthUser && empty($mentor['password'])) {
        $error = 'Akun Google tidak bisa mengubah password di sini.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Semua field password wajib diisi.';
        } elseif (!password_verify($currentPassword, $mentor['password'])) {
            $error = 'Password saat ini salah.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $mentor_id]);
            $success = 'Password berhasil diubah!';
            $notif->profileUpdated($mentor_id, 'password');
        }
    }
}

// Hitung total earnings (60% share)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(price), 0) FROM sessions WHERE mentor_id = ? AND status IN ('completed', 'ongoing')");
$stmt->execute([$mentor_id]);
$gemsFromSessions = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(ft.gem_reward), 0) FROM forum_replies fr JOIN forum_threads ft ON fr.thread_id = ft.id WHERE fr.user_id = ? AND fr.is_best_answer = 1");
$stmt->execute([$mentor_id]);
$gemsFromForum = (int) $stmt->fetchColumn();

$totalGems = $gemsFromSessions + $gemsFromForum;
$totalEarnings = $totalGems * 2 * 0.6;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun Mentor - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f8fafc; min-height: 100vh; }
        
        /* ===== BUTTONS ===== */
        .btn { padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; border: 2px solid transparent; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4); }
        .btn-outline { border: 2px solid #e2e8f0; color: #475569; background: white; }
        .btn-outline:hover { border-color: #10b981; color: #10b981; }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; }
        .btn-success:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3); }
        .btn-sm { padding: 10px 16px; font-size: 0.85rem; }
        .btn-danger-outline { border-color: #fecaca !important; color: #ef4444 !important; }
        .btn-danger-outline:hover { background: #fef2f2 !important; border-color: #ef4444 !important; }
        
        /* ===== ALERTS ===== */
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; border: 1px solid #fecaca; }
        
        /* ===== SETTINGS PAGE ===== */
        .settings-container { max-width: 900px; margin: 0 auto; padding: 32px 20px; }
        .settings-header { margin-bottom: 32px; }
        .settings-header h1 { font-size: 1.8rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .settings-header h1 i { color: #10b981; }
        .settings-header p { color: #64748b; }
        .settings-grid { display: flex; flex-direction: column; gap: 24px; }
        
        .settings-card { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); overflow: visible; }
        .settings-card-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; }
        .settings-card-header h2 { font-size: 1.1rem; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 10px; margin: 0; }
        .settings-card-header h2 i { color: #10b981; font-size: 1.2rem; }
        .settings-card-body { padding: 24px; overflow: visible; }
        
        /* ===== PROFILE PHOTO SECTION ===== */
        .profile-photo-section { display: flex; gap: 24px; align-items: flex-start; }
        .profile-photo-preview { position: relative; width: 100px; height: 100px; border-radius: 50%; overflow: hidden; flex-shrink: 0; background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3); border: 3px solid #e2e8f0; }
        .profile-photo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-placeholder { font-size: 2.5rem; font-weight: 700; color: white; }
        .profile-photo-info { flex: 1; }
        .profile-photo-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .profile-name-email h3 { font-size: 1.1rem; font-weight: 600; color: #1e293b; margin: 0 0 4px 0; }
        .profile-name-email p { font-size: 0.9rem; color: #64748b; margin: 0; }
        .photo-upload-form { margin-top: 0; }
        .photo-buttons { display: flex; gap: 12px; margin-bottom: 12px; }
        .photo-hint { font-size: 0.8rem; color: #94a3b8; margin: 0; }
        
        /* ===== GOOGLE BADGE ===== */
        .google-connected-badge { display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; color: #475569; white-space: nowrap; }
        .google-connected-badge svg { flex-shrink: 0; }
        
        /* ===== OAUTH PASSWORD NOTICE ===== */
        .oauth-password-notice { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); color: #1d4ed8; padding: 20px; border-radius: 12px; display: flex; align-items: center; gap: 16px; border: 1px solid #bfdbfe; }
        .oauth-password-notice svg { width: 32px; height: 32px; flex-shrink: 0; }
        .oauth-password-notice strong { display: block; font-size: 1rem; margin-bottom: 4px; }
        .oauth-password-notice p { margin: 0; font-size: 0.9rem; opacity: 0.9; }
        
        /* ===== FORM STYLES ===== */
        .settings-form { display: flex; flex-direction: column; gap: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; position: relative; }
        .form-group { display: flex; flex-direction: column; gap: 8px; position: relative; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-size: 0.9rem; font-weight: 600; color: #475569; }
        .form-input { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 1rem; transition: all 0.2s; outline: none; background: white; box-sizing: border-box; }
        .form-input:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        .form-input:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }
        .form-hint { font-size: 0.85rem; color: #94a3b8; }
        .form-actions { padding-top: 8px; }
        textarea.form-input { min-height: 100px; resize: vertical; font-family: inherit; }
        
        /* ===== READ-ONLY FIELD STYLING ===== */
        .readonly-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 0.75rem; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 12px; margin-left: 8px; }
        
        /* ===== ACCOUNT STATS ===== */
        .account-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .stat-item { display: flex; align-items: center; gap: 16px; padding: 16px; background: #f8fafc; border-radius: 12px; }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
        .stat-icon.earnings { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #059669; }
        .stat-icon.role { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
        .stat-icon.date { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #4f46e5; }
        .stat-info { display: flex; flex-direction: column; }
        .stat-value { font-size: 1.1rem; font-weight: 700; color: #1e293b; }
        .stat-label { font-size: 0.85rem; color: #64748b; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .profile-photo-section { flex-direction: column; align-items: center; text-align: center; }
            .profile-photo-header { flex-direction: column; align-items: center; }
            .photo-buttons { justify-content: center; }
            .account-stats { grid-template-columns: 1fr; }
            .settings-container { padding: 20px 16px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/mentor-navbar.php'; ?>

    <div class="settings-container">
        <div class="settings-header">
            <h1><i class="bi bi-gear"></i> Pengaturan Akun Mentor</h1>
            <p>Kelola profil dan keamanan akun mentor</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
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
                            <?php if ($avatarUrl): ?>
                                <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" id="avatarPreview" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <div class="avatar-placeholder" id="avatarPlaceholder">
                                    <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                                </div>
                                <img src="" alt="Avatar" id="avatarPreview" style="display: none;" referrerpolicy="no-referrer">
                            <?php endif; ?>
                        </div>
                        
                        <div class="profile-photo-info">
                            <div class="profile-photo-header">
                                <div class="profile-name-email">
                                    <h3><?php echo htmlspecialchars($mentor['name']); ?></h3>
                                    <p><?php echo htmlspecialchars($mentor['email']); ?></p>
                                </div>
                                <?php if ($isOAuthUser): ?>
                                <div class="google-connected-badge">
                                    <svg viewBox="0 0 24 24" width="18" height="18">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                    </svg>
                                    <span>Terhubung</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" class="photo-upload-form">
                                <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                                <div class="photo-buttons">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('avatarInput').click()">
                                        <i class="bi bi-upload"></i> Upload Foto
                                    </button>
                                    <?php if ($avatarUrl): ?>
                                    <button type="submit" name="remove_photo" class="btn btn-outline btn-sm btn-danger-outline">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" name="update_photo" id="savePhotoBtn" class="btn btn-success btn-sm" style="display: none; margin-top: 12px;">
                                    <i class="bi bi-check"></i> Simpan Foto
                                </button>
                            </form>
                            <p class="photo-hint">Format: JPG, PNG, GIF, WebP. Maksimal 2MB.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Update Profile Section -->
            <section class="settings-card">
                <div class="settings-card-header">
                    <h2><i class="bi bi-person-badge"></i> Informasi Profil</h2>
                </div>
                <div class="settings-card-body">
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label for="name">Nama Lengkap</label>
                            <input type="text" id="name" name="name" class="form-input" 
                                   value="<?php echo htmlspecialchars($mentor['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">
                                Email
                                <span class="readonly-badge"><i class="bi bi-lock-fill"></i> Read-only</span>
                            </label>
                            <input type="email" id="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($mentor['email']); ?>" disabled>
                            <span class="form-hint">Email tidak dapat diubah.</span>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    Program Studi
                                    <span class="readonly-badge"><i class="bi bi-lock-fill"></i> Read-only</span>
                                </label>
                                <input type="text" class="form-input" 
                                       value="<?php echo htmlspecialchars($mentor['program_studi'] ?? 'Belum diisi'); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    Semester
                                    <span class="readonly-badge"><i class="bi bi-lock-fill"></i> Read-only</span>
                                </label>
                                <input type="text" class="form-input" 
                                       value="<?php echo !empty($mentor['semester']) ? 'Semester ' . htmlspecialchars((string)$mentor['semester']) : 'Belum diisi'; ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                Keahlian (Expertise)
                                <span class="readonly-badge"><i class="bi bi-lock-fill"></i> Read-only</span>
                            </label>
                            <textarea class="form-input" disabled><?php echo htmlspecialchars($mentor['expertise'] ?? 'Belum diisi'); ?></textarea>
                            <span class="form-hint">Hubungi admin untuk mengubah keahlian.</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="bio">Bio / Deskripsi Singkat</label>
                            <textarea name="bio" id="bio" class="form-input" placeholder="Ceritakan tentang pengalaman dan keahlian kamu..."><?php echo htmlspecialchars($mentor['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn btn-primary">
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
                    <?php if ($isOAuthUser && empty($mentor['password'])): ?>
                    <div class="oauth-password-notice">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        <div>
                            <strong>Login dengan Google</strong>
                            <p>Password dikelola oleh Google. Gunakan pengaturan akun Google untuk mengubah password.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label for="current_password">Password Saat Ini</label>
                            <input type="password" id="current_password" name="current_password" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <input type="password" id="new_password" name="new_password" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password Baru</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="bi bi-key"></i> Ubah Password
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Account Stats -->
            <section class="settings-card">
                <div class="settings-card-header">
                    <h2><i class="bi bi-bar-chart"></i> Statistik Mentor</h2>
                </div>
                <div class="settings-card-body">
                    <div class="account-stats">
                        <div class="stat-item">
                            <div class="stat-icon earnings"><i class="bi bi-wallet2"></i></div>
                            <div class="stat-info">
                                <span class="stat-value">Rp <?php echo number_format($totalEarnings, 0, ',', '.'); ?></span>
                                <span class="stat-label">Total Pendapatan (60%)</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon role"><i class="bi bi-person-badge"></i></div>
                            <div class="stat-info">
                                <span class="stat-value">Mentor</span>
                                <span class="stat-label">Role</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon date"><i class="bi bi-calendar-check"></i></div>
                            <div class="stat-info">
                                <span class="stat-value"><?php echo date('d M Y', strtotime($mentor['created_at'])); ?></span>
                                <span class="stat-label">Bergabung</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
    // Avatar Upload Preview
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
    
    // Auto-hide alerts after 5s
    ['.alert-success', '.alert-error'].forEach(sel => {
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
