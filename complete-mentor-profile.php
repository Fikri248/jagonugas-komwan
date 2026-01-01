<?php
// complete-mentor-profile.php
// Halaman khusus untuk mentor yang daftar via Google melengkapi profil
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';

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

// Cek apakah ada Google prefill data
$googlePrefill = $_SESSION['google_prefill_mentor'] ?? null;

if (!$googlePrefill) {
    // Tidak ada data Google, redirect ke mentor-register biasa
    header('Location: ' . url_path('mentor-register.php'));
    exit;
}

$error = '';
$success = '';

$prefillName = $googlePrefill['name'] ?? '';
$prefillEmail = $googlePrefill['email'] ?? '';
$prefillGoogleId = $googlePrefill['google_id'] ?? '';
$prefillAvatar = $googlePrefill['avatar'] ?? '';
$existingUserId = $googlePrefill['user_id'] ?? null; // Untuk upgrade existing student ke mentor

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
    $programStudi = trim($_POST['program_studi'] ?? '');
    $semester = (int)($_POST['semester'] ?? 0);
    $expertise = $_POST['expertise'] ?? [];
    $bio = trim($_POST['bio'] ?? '');

    // Validasi
    if ($programStudi === '' || $semester < 3) {
        $error = 'Program studi dan semester wajib diisi. Mentor minimal semester 3.';
    } elseif (!in_array($programStudi, $programStudiList, true)) {
        $error = 'Program studi tidak valid';
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

                        $user->name = $prefillName;
                        $user->email = $prefillEmail;
                        $user->password = bin2hex(random_bytes(16)); // Random password for Google users
                        $user->programstudi = $programStudi;
                        $user->program_studi = $programStudi;
                        $user->semester = $semester;
                        $user->role = 'mentor';
                        $user->google_id = $prefillGoogleId;
                        $user->avatar = $prefillAvatar;

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapi Profil Mentor - JagoNugas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f7fafc; }

        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
        
        .auth-card { background: #ffffff; border-radius: 20px; box-shadow: 0 18px 45px rgba(15,23,42,0.18); padding: 32px; max-width: 580px; width: 100%; border: 1px solid #e2e8f0; }
        
        .auth-title { font-size: 1.5rem; font-weight: 700; color: #1a202c; margin-bottom: 4px; }
        .auth-subtitle { font-size: 0.95rem; color: #718096; margin-bottom: 24px; }

        .auth-back-btn { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 500; margin-bottom: 16px; padding: 8px 12px; border-radius: 8px; transition: all 0.2s ease; }
        .auth-back-btn:hover { color: #10b981; background: rgba(16,185,129,0.08); transform: translateX(-2px); }

        /* Step Indicator */
        .step-indicator { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 24px; }
        .step { display: flex; align-items: center; gap: 8px; }
        .step-number { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
        .step-number.completed { background: #10b981; color: white; }
        .step-number.active { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        .step-number.pending { background: #e2e8f0; color: #94a3b8; }
        .step-text { font-size: 0.85rem; color: #64748b; font-weight: 500; }
        .step-text.active { color: #059669; font-weight: 600; }
        .step-line { width: 40px; height: 2px; background: #e2e8f0; }
        .step-line.completed { background: #10b981; }

        /* Google User Card */
        .google-user-card { display: flex; align-items: center; gap: 16px; padding: 20px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 16px; margin-bottom: 24px; border: 1px solid #bbf7d0; }
        .google-user-avatar { width: 56px; height: 56px; border-radius: 50%; border: 3px solid #10b981; }
        .google-user-info { flex: 1; }
        .google-user-name { font-size: 1.1rem; font-weight: 700; color: #166534; }
        .google-user-email { font-size: 0.9rem; color: #15803d; }
        .google-verified { display: flex; align-items: center; gap: 6px; margin-top: 4px; font-size: 0.8rem; color: #16a34a; font-weight: 500; }

        .auth-form { display: flex; flex-direction: column; gap: 16px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .required { color: #dc2626; }

        .auth-input { width: 100%; padding: 12px 16px; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 0.95rem; color: #1a202c; background: #f8fafc; outline: none; transition: all 0.25s ease; }
        .auth-input::placeholder { color: #94a3b8; }
        .auth-input:hover { border-color: #cbd5e1; background: #ffffff; }
        .auth-input:focus { border-color: #10b981; background: #ffffff; box-shadow: 0 0 0 4px rgba(16,185,129,0.15); }
        
        .auth-textarea { min-height: 100px; resize: vertical; }

        /* Alerts */
        .alert { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 0.9rem; font-weight: 500; transition: opacity 0.5s ease, transform 0.5s ease; }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); color: #16a34a; border: 1px solid #bbf7d0; }
        .alert.fade-out { opacity: 0; transform: translateY(-10px); }

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
        .pending-verification { text-align: center; padding: 32px 24px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 16px; margin-top: 16px; }
        .pending-icon { font-size: 3rem; margin-bottom: 16px; }
        .pending-badge { display: inline-block; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; padding: 8px 20px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; margin-bottom: 16px; }
        .pending-verification h3 { color: #059669; margin-bottom: 8px; font-size: 1.25rem; }
        .pending-verification p { color: #64748b; font-size: 0.95rem; margin-bottom: 20px; line-height: 1.7; }
        .pending-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn-outline { background: transparent; border: 2px solid #10b981; color: #059669; }
        .btn-outline:hover { background: #f0fdf4; }

        .auth-footer-text { font-size: 0.9rem; color: #4a5568; margin-top: 20px; text-align: center; }
        .auth-footer-text a { color: #10b981; font-weight: 600; text-decoration: none; }
        .auth-footer-text a:hover { text-decoration: underline; }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .auth-card { padding: 24px 20px; }
            .expertise-grid { grid-template-columns: 1fr 1fr; }
            .step-text { display: none; }
            .pending-actions { flex-direction: column; }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card">
        <a href="<?php echo htmlspecialchars(url_path('mentor-register.php')); ?>" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step">
                <div class="step-number completed">✓</div>
                <span class="step-text">Google Account</span>
            </div>
            <div class="step-line completed"></div>
            <div class="step">
                <div class="step-number active">2</div>
                <span class="step-text active">Lengkapi Profil</span>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <div class="step-number pending">3</div>
                <span class="step-text">Verifikasi Admin</span>
            </div>
        </div>

        <h1 class="auth-title">Lengkapi Profil Mentor</h1>
        <p class="auth-subtitle">Satu langkah lagi untuk jadi mentor JagoNugas!</p>

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
            <div class="pending-verification">
                <div class="pending-icon">🎉</div>
                <div class="pending-badge">⏳ MENUNGGU VERIFIKASI</div>
                <h3>Pendaftaran Berhasil!</h3>
                <p>
                    Terima kasih sudah mendaftar sebagai mentor.<br>
                    Tim admin sedang mereview transkrip nilai kamu.<br>
                    Kamu akan menerima notifikasi setelah akun diverifikasi (1x24 jam).
                </p>
                <div class="pending-actions">
                    <a href="<?php echo htmlspecialchars(url_path('mentor-login.php')); ?>" class="btn btn-mentor">Cek Status Login</a>
                    <a href="<?php echo htmlspecialchars(url_path('index.php')); ?>" class="btn btn-outline">Kembali ke Beranda</a>
                </div>
            </div>
        <?php else: ?>

        <!-- Google User Card -->
        <div class="google-user-card">
            <?php if ($prefillAvatar): ?>
                <img src="<?php echo htmlspecialchars($prefillAvatar); ?>" alt="Avatar" class="google-user-avatar">
            <?php else: ?>
                <div class="google-user-avatar" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.5rem;">
                    <?php echo strtoupper(substr($prefillName, 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="google-user-info">
                <div class="google-user-name"><?php echo htmlspecialchars($prefillName); ?></div>
                <div class="google-user-email"><?php echo htmlspecialchars($prefillEmail); ?></div>
                <div class="google-verified">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    Terverifikasi via Google
                </div>
            </div>
        </div>

        <form method="POST" class="auth-form" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label>Program Studi <span class="required">*</span></label>
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
                    <label>Semester <span class="required">*</span> <small>(min. 3)</small></label>
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
                    <div class="upload-preview">
                        <div class="preview-icon">FILE</div>
                        <div class="preview-info">
                            <span class="preview-name" id="previewName"></span>
                            <span class="preview-size" id="previewSize"></span>
                        </div>
                        <button type="button" class="preview-remove" id="removeFile">✕</button>
                    </div>
                </div>
                <p class="form-hint">Transkrip nilai untuk memverifikasi kemampuan akademik kamu</p>
            </div>

            <div class="form-group">
                <label>Keahlian <span class="required">*</span> <small>(pilih yang dikuasai)</small></label>
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
                <textarea id="bio" name="bio" class="auth-input auth-textarea" placeholder="Ceritakan pengalaman dan keahlian lo sebagai mentor..."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn btn-mentor">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                Daftar sebagai Mentor
            </button>
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
    });
    </script>
</body>
</html>
