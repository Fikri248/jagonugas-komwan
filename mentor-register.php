<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';

// ✅ TRACK VISITOR
if (file_exists(__DIR__ . '/track-visitor.php')) {
    require_once __DIR__ . '/track-visitor.php';
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function url_path(string $path = ''): string
{
    $base = '';
    if (defined('BASE_PATH')) {
        $base = (string) constant('BASE_PATH');
    } elseif (defined('BASEPATH')) {
        $base = (string) constant('BASEPATH');
    }
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

$error = '';
$success = '';

// Cek apakah dari Google OAuth (prefill data)
$googlePrefill = $_SESSION['google_prefill_mentor'] ?? null;

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

$expertiseList = [
    'Pemrograman Web',
    'Pemrograman Mobile',
    'Database & SQL',
    'Data Science & Analytics',
    'Machine Learning & AI',
    'Jaringan Komputer',
    'Sistem Operasi',
    'Algoritma & Struktur Data',
    'Matematika & Statistika',
    'Desain UI/UX',
    'Cloud Computing',
    'Cyber Security'
];

function is_allowed_extension(string $ext): bool
{
    return in_array(strtolower($ext), ['pdf', 'jpg', 'jpeg', 'png'], true);
}

function detect_mime(string $tmpFile): string
{
    if (!is_file($tmpFile)) return '';
    if (!class_exists('finfo')) return mime_content_type($tmpFile) ?: '';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    return (string) $finfo->file($tmpFile);
}

function is_allowed_mime(string $mime): bool
{
    return in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $programStudi = trim($_POST['program_studi'] ?? '');
    $semester = (int)($_POST['semester'] ?? 0);
    $expertise = $_POST['expertise'] ?? [];
    $bio = trim($_POST['bio'] ?? '');
    $googleId = $_POST['google_id'] ?? null;
    $avatar = $_POST['avatar'] ?? null;

    // Validasi
    if ($name === '' || $email === '' || $programStudi === '' || $semester < 3) {
        $error = 'Semua field wajib diisi. Mentor minimal semester 3.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (empty($googleId) && strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif (empty($googleId) && $password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok';
    } elseif (!is_array($expertise) || empty($expertise)) {
        $error = 'Pilih minimal 1 keahlian';
    } elseif (!isset($_FILES['transkrip']) || ($_FILES['transkrip']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $error = 'Upload transkrip nilai wajib diisi';
    } else {
        $file = $_FILES['transkrip'];

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $error = 'Gagal mengupload file. Coba lagi.';
        } else {
            $maxSize = 5 * 1024 * 1024;
            $tmp = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $originalName = (string)($file['name'] ?? '');

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $mime = detect_mime($tmp);

            if ($size <= 0 || $tmp === '' || !is_uploaded_file($tmp)) {
                $error = 'File upload tidak valid.';
            } elseif ($size > $maxSize) {
                $error = 'Ukuran file maksimal 5MB';
            } elseif (!is_allowed_extension($ext)) {
                $error = 'Format file harus PDF, JPG, atau PNG';
            } elseif (!is_allowed_mime($mime)) {
                $error = 'Format file harus PDF, JPG, atau PNG';
            } else {
                $uploadDirAbs = __DIR__ . '/uploads/transkrip/';
                if (!is_dir($uploadDirAbs)) {
                    mkdir($uploadDirAbs, 0755, true);
                }

                $safeExt = $ext === 'jpeg' ? 'jpg' : $ext;
                $filename = 'transkrip_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $safeExt;

                $destinationAbs = $uploadDirAbs . $filename;
                $relativePath = 'uploads/transkrip/' . $filename;

                if (!move_uploaded_file($tmp, $destinationAbs)) {
                    $error = 'Gagal menyimpan file upload. Coba lagi.';
                } else {
                    try {
                        $db = (new Database())->getConnection();
                        $user = new User($db);

                        $user->name = $name;
                        $user->email = $email;
                        $user->password = !empty($googleId) ? bin2hex(random_bytes(16)) : $password;
                        $user->programstudi = $programStudi;
                        $user->program_studi = $programStudi;
                        $user->semester = $semester;
                        $user->role = 'mentor';
                        $user->google_id = $googleId;
                        $user->avatar = $avatar;

                        if (!method_exists($user, 'registerMentor')) {
                            @unlink($destinationAbs);
                            $error = 'Fitur register mentor belum tersedia.';
                        } else {
                            $result = $user->registerMentor($expertise, $bio, $relativePath);

                            if (is_array($result) && !empty($result['success'])) {
                                $success = 'Pendaftaran mentor berhasil! Akun akan diverifikasi oleh admin dalam 1x24 jam.';
                                unset($_SESSION['google_prefill_mentor']);
                            } else {
                                @unlink($destinationAbs);
                                $error = is_array($result) ? ($result['message'] ?? 'Registrasi gagal.') : 'Registrasi gagal.';
                            }
                        }
                    } catch (Throwable $e) {
                        @unlink($destinationAbs);
                        $error = 'Terjadi kesalahan server. Coba lagi nanti.';
                    }
                }
            }
        }
    }
}

// Prefill values
$prefillName = $googlePrefill['name'] ?? ($_POST['name'] ?? '');
$prefillEmail = $googlePrefill['email'] ?? ($_POST['email'] ?? '');
$prefillGoogleId = $googlePrefill['google_id'] ?? '';
$prefillAvatar = $googlePrefill['avatar'] ?? '';
$isGoogleRegister = !empty($prefillGoogleId);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Mentor - JagoNugas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f7fafc; }

        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
        
        .auth-card { background: #ffffff; border-radius: 20px; box-shadow: 0 18px 45px rgba(15,23,42,0.18); padding: 32px; max-width: 580px; width: 100%; border: 1px solid #e2e8f0; }
        
        .auth-title { font-size: 1.5rem; font-weight: 700; color: #1a202c; margin-bottom: 4px; }
        .auth-subtitle { font-size: 0.95rem; color: #718096; margin-bottom: 24px; }

        .auth-back-btn { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 500; margin-bottom: 16px; padding: 8px 12px; border-radius: 8px; transition: all 0.2s ease; }
        .auth-back-btn:hover { color: #10b981; background: rgba(16,185,129,0.08); transform: translateX(-2px); }

        .auth-badge-mentor { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 16px; }

        .auth-form { display: flex; flex-direction: column; gap: 16px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .required { color: #dc2626; }

        .auth-input { width: 100%; padding: 12px 16px; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 0.95rem; color: #1a202c; background: #f8fafc; outline: none; transition: all 0.25s ease; }
        .auth-input::placeholder { color: #94a3b8; }
        .auth-input:hover { border-color: #cbd5e1; background: #ffffff; }
        .auth-input:focus { border-color: #10b981; background: #ffffff; box-shadow: 0 0 0 4px rgba(16,185,129,0.15); }
        .auth-input:read-only { background: #e2e8f0; cursor: not-allowed; }
        
        .auth-textarea { min-height: 100px; resize: vertical; }

        /* Alerts */
        .alert { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 0.9rem; font-weight: 500; transition: opacity 0.5s ease, transform 0.5s ease; }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); color: #16a34a; border: 1px solid #bbf7d0; }
        .alert.fade-out { opacity: 0; transform: translateY(-10px); }

        /* Google Button */
        .btn-google { display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; padding: 14px 20px; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 12px; color: #1f2937; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; margin-bottom: 8px; }
        .btn-google:hover { border-color: #4285f4; background: #f8fafc; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(66,133,244,0.15); }

        .auth-divider { display: flex; align-items: center; margin: 20px 0; }
        .auth-divider::before, .auth-divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .auth-divider span { padding: 0 16px; color: #94a3b8; font-size: 0.85rem; }

        /* Google Prefill Info */
        .google-info { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 12px; margin-bottom: 16px; border: 1px solid #bfdbfe; }
        .google-info img { width: 40px; height: 40px; border-radius: 50%; }
        .google-info-text { flex: 1; }
        .google-info-name { font-weight: 600; color: #1e40af; }
        .google-info-email { font-size: 0.85rem; color: #3b82f6; }

        /* Custom Select */
        .custom-select { position: relative; width: 100%; font-size: 0.95rem; user-select: none; }
        .select-selected { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.3s ease; }
        .select-selected:hover { border-color: #cbd5e1; background: #ffffff; }
        .custom-select.active .select-selected { border-color: #10b981; background: #ffffff; box-shadow: 0 0 0 4px rgba(16,185,129,0.15); }
        .select-text { color: #64748b; font-weight: 500; }
        .select-text.has-value { color: #1e293b; font-weight: 600; }
        .select-arrow { color: #94a3b8; transition: transform 0.3s ease; }
        .custom-select.active .select-arrow { transform: rotate(180deg); color: #10b981; }
        .select-items { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.12); max-height: 240px; overflow-y: auto; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.25s ease; z-index: 1000; padding: 8px; }
        .custom-select.active .select-items { opacity: 1; visibility: visible; transform: translateY(0); }
        .select-item { padding: 10px 14px; border-radius: 8px; cursor: pointer; color: #475569; font-weight: 500; transition: all 0.2s ease; }
        .select-item:hover { background: #f0fdf4; color: #059669; }
        .select-item.selected { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; }

        /* Upload Area */
        .file-input-hidden { display: none; }
        .upload-area { border: 2px dashed #e2e8f0; border-radius: 12px; padding: 24px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: #f8fafc; }
        .upload-area:hover, .upload-area.dragover { border-color: #10b981; background: #f0fdf4; }
        .upload-area.has-file .upload-placeholder { display: none; }
        .upload-area:not(.has-file) .upload-preview { display: none; }
        .upload-icon { color: #94a3b8; margin-bottom: 12px; }
        .upload-text { color: #475569; font-size: 0.9rem; margin-bottom: 4px; }
        .upload-browse { color: #10b981; font-weight: 600; }
        .upload-hint { color: #94a3b8; font-size: 0.8rem; }
        .upload-preview { display: flex; align-items: center; gap: 12px; padding: 12px; background: #f0fdf4; border-radius: 8px; }
        .preview-icon { background: #10b981; color: white; padding: 8px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .preview-info { flex: 1; text-align: left; }
        .preview-name { display: block; font-weight: 600; color: #1a202c; font-size: 0.9rem; }
        .preview-size { color: #64748b; font-size: 0.8rem; }
        .preview-remove { background: #fee2e2; color: #dc2626; border: none; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; font-weight: 700; }
        .form-hint { font-size: 0.8rem; color: #64748b; margin-top: 8px; }

        /* Expertise Grid */
        .expertise-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
        .expertise-item { display: flex; align-items: center; }
        .expertise-item input { display: none; }
        .expertise-label { display: block; width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; color: #475569; cursor: pointer; transition: all 0.2s ease; text-align: center; }
        .expertise-label:hover { border-color: #10b981; background: #f0fdf4; }
        .expertise-item input:checked + .expertise-label { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-color: transparent; }

        /* Buttons */
        .btn { padding: 14px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: none; cursor: pointer; width: 100%; }
        .btn-mentor { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .btn-mentor:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16,185,129,0.3); }

        /* Pending Verification */
        .pending-verification { text-align: center; padding: 24px; background: #f0fdf4; border-radius: 16px; margin-top: 16px; }
        .pending-icon { display: inline-block; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; margin-bottom: 12px; }
        .pending-verification h3 { color: #059669; margin-bottom: 8px; }
        .pending-verification p { color: #64748b; font-size: 0.9rem; margin-bottom: 16px; }

        .auth-footer-text { font-size: 0.9rem; color: #4a5568; margin-top: 20px; text-align: center; }
        .auth-footer-text a { color: #10b981; font-weight: 600; text-decoration: none; }
        .auth-footer-text a:hover { text-decoration: underline; }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .auth-card { padding: 24px 20px; }
            .expertise-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card">
        <a href="<?php echo htmlspecialchars(url_path('index.php')); ?>" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>

        <div class="auth-badge-mentor">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Daftar Mentor
        </div>

        <h1 class="auth-title">Jadi Mentor JagoNugas</h1>
        <p class="auth-subtitle">Bantu adik tingkat dan dapatkan penghasilan tambahan</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" id="alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" id="alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <?php echo htmlspecialchars($success); ?>
            </div>

            <div class="pending-verification">
                <div class="pending-icon">⏳ PENDING</div>
                <h3>Menunggu Verifikasi</h3>
                <p>Tim admin sedang mereview transkrip nilai kamu. Kamu akan menerima notifikasi setelah akun diverifikasi.</p>
                <a href="<?php echo htmlspecialchars(url_path('mentor-login.php')); ?>" class="btn btn-mentor">Cek Status Login</a>
            </div>
        <?php else: ?>

        <?php if (!$isGoogleRegister): ?>
            <!-- Google Sign Up Button -->
            <a href="<?php echo htmlspecialchars(url_path('google-auth.php?action=mentor-register')); ?>" class="btn-google">
                <svg width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Daftar dengan Google
            </a>

            <div class="auth-divider">
                <span>atau daftar manual</span>
            </div>
        <?php else: ?>
            <!-- Google Prefill Info -->
            <div class="google-info">
                <?php if ($prefillAvatar): ?>
                    <img src="<?php echo htmlspecialchars($prefillAvatar); ?>" alt="Avatar">
                <?php endif; ?>
                <div class="google-info-text">
                    <div class="google-info-name"><?php echo htmlspecialchars($prefillName); ?></div>
                    <div class="google-info-email"><?php echo htmlspecialchars($prefillEmail); ?></div>
                </div>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#34A853">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form" enctype="multipart/form-data">
            <?php if ($isGoogleRegister): ?>
                <input type="hidden" name="google_id" value="<?php echo htmlspecialchars($prefillGoogleId); ?>">
                <input type="hidden" name="avatar" value="<?php echo htmlspecialchars($prefillAvatar); ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="auth-input" 
                        value="<?php echo htmlspecialchars($prefillName); ?>" 
                        placeholder="Masukkan nama lengkap" 
                        <?php echo $isGoogleRegister ? 'readonly' : ''; ?> required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="auth-input" 
                        value="<?php echo htmlspecialchars($prefillEmail); ?>" 
                        placeholder="emailgoogle@gmail.com" 
                        <?php echo $isGoogleRegister ? 'readonly' : ''; ?> required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Program Studi</label>
                    <div class="custom-select" data-name="program_studi">
                        <div class="select-selected">
                            <span class="select-text <?php echo !empty($_POST['program_studi']) ? 'has-value' : ''; ?>">
                                <?php echo !empty($_POST['program_studi']) ? htmlspecialchars($_POST['program_studi']) : 'Pilih Program Studi'; ?>
                            </span>
                            <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </div>
                        <div class="select-items">
                            <?php foreach ($programStudiList as $prodi): ?>
                                <div class="select-item <?php echo (($_POST['program_studi'] ?? '') === $prodi) ? 'selected' : ''; ?>" data-value="<?php echo htmlspecialchars($prodi); ?>">
                                    <?php echo htmlspecialchars($prodi); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="program_studi" value="<?php echo htmlspecialchars($_POST['program_studi'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Semester (min. 3)</label>
                    <div class="custom-select" data-name="semester">
                        <div class="select-selected">
                            <span class="select-text <?php echo !empty($_POST['semester']) ? 'has-value' : ''; ?>">
                                <?php echo !empty($_POST['semester']) ? 'Semester ' . htmlspecialchars((string)$_POST['semester']) : 'Pilih Semester'; ?>
                            </span>
                            <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </div>
                        <div class="select-items">
                            <?php for ($i = 3; $i <= 14; $i++): ?>
                                <div class="select-item <?php echo (($_POST['semester'] ?? '') == $i) ? 'selected' : ''; ?>" data-value="<?php echo $i; ?>">
                                    Semester <?php echo $i; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="semester" value="<?php echo htmlspecialchars($_POST['semester'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Upload Transkrip Nilai <span class="required">*</span></label>
                <input type="file" name="transkrip" id="transkrip" class="file-input-hidden" accept=".pdf,.jpg,.jpeg,.png" required>
                <div class="upload-area" id="uploadArea">
                    <div class="upload-placeholder">
                        <div class="upload-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                        </div>
                        <p class="upload-text">Drag & drop file di sini atau <span class="upload-browse">browse</span></p>
                        <p class="upload-hint">Format: PDF, JPG, PNG (Maks. 5MB)</p>
                    </div>
                    <div class="upload-preview" id="uploadPreview">
                        <div class="preview-icon">FILE</div>
                        <div class="preview-info">
                            <span class="preview-name" id="previewName"></span>
                            <span class="preview-size" id="previewSize"></span>
                        </div>
                        <button type="button" class="preview-remove" id="removeFile">✕</button>
                    </div>
                </div>
                <p class="form-hint">Transkrip nilai akan direview oleh admin untuk verifikasi kemampuan akademik</p>
            </div>

            <div class="form-group">
                <label>Keahlian (pilih yang dikuasai)</label>
                <div class="expertise-grid">
                    <?php foreach ($expertiseList as $exp): ?>
                        <label class="expertise-item">
                            <input type="checkbox" name="expertise[]" value="<?php echo htmlspecialchars($exp); ?>" <?php echo in_array($exp, $_POST['expertise'] ?? [], true) ? 'checked' : ''; ?>>
                            <span class="expertise-label"><?php echo htmlspecialchars($exp); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="bio">Bio Singkat</label>
                <textarea id="bio" name="bio" class="auth-input auth-textarea" placeholder="Ceritakan pengalaman dan keahlian lo..."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
            </div>

            <?php if (!$isGoogleRegister): ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="auth-input" placeholder="Minimal 6 karakter" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="auth-input" placeholder="Ulangi password" required>
                </div>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-mentor">Daftar sebagai Mentor</button>
        </form>

        <p class="auth-footer-text">
            Sudah punya akun mentor? <a href="<?php echo htmlspecialchars(url_path('mentor-login.php')); ?>">Login di sini</a>
        </p>

        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Custom Select
        const customSelects = document.querySelectorAll('.custom-select');
        customSelects.forEach(select => {
            const selected = select.querySelector('.select-selected');
            const hiddenInput = select.querySelector('input[type="hidden"]');
            const selectText = select.querySelector('.select-text');

            selected.addEventListener('click', function(e) {
                e.stopPropagation();
                customSelects.forEach(s => { if (s !== select) s.classList.remove('active'); });
                select.classList.toggle('active');
            });

            select.querySelectorAll('.select-item').forEach(item => {
                item.addEventListener('click', function() {
                    hiddenInput.value = this.dataset.value;
                    selectText.textContent = this.textContent.trim();
                    selectText.classList.add('has-value');
                    select.querySelectorAll('.select-item').forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                    setTimeout(() => select.classList.remove('active'), 150);
                });
            });
        });

        document.addEventListener('click', () => customSelects.forEach(s => s.classList.remove('active')));

        // File Upload
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('transkrip');
        const previewName = document.getElementById('previewName');
        const previewSize = document.getElementById('previewSize');
        const removeFile = document.getElementById('removeFile');

        if (uploadArea && fileInput) {
            uploadArea.addEventListener('click', function(e) {
                if (e.target === removeFile || removeFile?.contains(e.target)) return;
                fileInput.click();
            });

            uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); uploadArea.classList.add('dragover'); });
            uploadArea.addEventListener('dragleave', function(e) { e.preventDefault(); uploadArea.classList.remove('dragover'); });
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    handleFileSelect(e.dataTransfer.files[0]);
                    const dt = new DataTransfer();
                    dt.items.add(e.dataTransfer.files[0]);
                    fileInput.files = dt.files;
                }
            });

            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) handleFileSelect(this.files[0]);
            });

            removeFile?.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                fileInput.value = '';
                uploadArea.classList.remove('has-file');
            });

            function handleFileSelect(file) {
                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                const maxSize = 5 * 1024 * 1024;

                if (!allowedTypes.includes(file.type)) { alert('Format file harus PDF, JPG, atau PNG'); fileInput.value = ''; return; }
                if (file.size > maxSize) { alert('Ukuran file maksimal 5MB'); fileInput.value = ''; return; }

                previewName.textContent = file.name;
                previewSize.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                uploadArea.classList.add('has-file');
            }
        }

        // Auto-dismiss Alerts
        function autoDismissAlert(id, delay) {
            const alert = document.getElementById(id);
            if (alert) {
                setTimeout(() => {
                    alert.classList.add('fade-out');
                    setTimeout(() => alert.remove(), 500);
                }, delay);
            }
        }
        autoDismissAlert('alert-error', 5000);
        autoDismissAlert('alert-success', 10000);
    });
    </script>
</body>
</html>
