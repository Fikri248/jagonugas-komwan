<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ModelUser.php';

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper URL: pakai BASE_PATH (baru), fallback ke BASEPATH (lama).
 */
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

// Daftar Program Studi
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

// Daftar Keahlian
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
    $ext = strtolower($ext);
    return in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);
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

    // Validasi dasar
    if ($name === '' || $email === '' || $password === '' || $programStudi === '' || $semester < 3) {
        $error = 'Semua field wajib diisi. Mentor minimal semester 3.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirmPassword) {
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
            $maxSize = 5 * 1024 * 1024; // 5MB
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
                // Folder upload
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

                        // Set field (buat kompatibilitas jika ModelUser beda penamaan)
                        $user->name = $name;
                        $user->email = $email;
                        $user->password = $password;

                        // Beberapa project pakai programstudi, sebagian program_studi
                        $user->programstudi = $programStudi;
                        $user->program_studi = $programStudi;

                        $user->semester = $semester;
                        $user->role = 'mentor';

                        // Wajib ada method registerMentor() (sesuai kode kamu)
                        if (!method_exists($user, 'registerMentor')) {
                            // rollback file kalau method belum ada
                            @unlink($destinationAbs);
                            $error = 'Fitur register mentor belum tersedia di ModelUser.php (method registerMentor tidak ditemukan).';
                        } else {
                            $result = $user->registerMentor($expertise, $bio, $relativePath);

                            if (is_array($result) && !empty($result['success'])) {
                                $success = 'Pendaftaran mentor berhasil! Akun akan diverifikasi oleh admin dalam 1x24 jam.';
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
    <title>Daftar Mentor - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(url_path('style.css')); ?>">
</head>
<body class="auth-page">
    <div class="auth-card auth-card-mentor auth-card-wide">
        <a href="<?php echo htmlspecialchars(url_path('index.php')); ?>" class="auth-back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Kembali
        </a>

        <div class="auth-badge-mentor">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
            <div class="alert alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <?php echo htmlspecialchars($success); ?>
            </div>

            <div class="pending-verification">
                <div class="pending-icon">PENDING</div>
                <h3>Menunggu Verifikasi</h3>
                <p>Tim admin sedang mereview transkrip nilai kamu. Kamu akan menerima notifikasi setelah akun diverifikasi.</p>
                <a href="<?php echo htmlspecialchars(url_path('mentor-login.php')); ?>" class="btn btn-mentor btn-full">Cek Status Login</a>
            </div>
        <?php else: ?>

        <form method="POST" class="auth-form" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="auth-input"
                        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                        placeholder="Masukkan nama lengkap"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="auth-input"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        placeholder="email@telkomuniversity.ac.id"
                        required
                    >
                </div>
            </div>

            <div class="form-row">
                <!-- Program Studi -->
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
                                <div
                                    class="select-item <?php echo (($_POST['program_studi'] ?? '') === $prodi) ? 'selected' : ''; ?>"
                                    data-value="<?php echo htmlspecialchars($prodi); ?>"
                                >
                                    <?php echo htmlspecialchars($prodi); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <input type="hidden" name="program_studi" value="<?php echo htmlspecialchars($_POST['program_studi'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- Semester (minimal 3) -->
                <div class="form-group">
                    <label>Semester (min. 3)</label>
                    <div class="custom-select" data-name="semester">
                        <div class="select-selected">
                            <span class="select-text <?php echo !empty($_POST['semester']) ? 'has-value' : ''; ?>">
                                <?php echo !empty($_POST['semester']) ? 'Semester ' . htmlspecialchars((string)$_POST['semester']) : 'Pilih Semester'; ?>
                            </span>
                            <svg class="select-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 9l6 6 6-6"/>
                            </svg>
                        </div>

                        <div class="select-items">
                            <?php for ($i = 3; $i <= 14; $i++): ?>
                                <div
                                    class="select-item <?php echo (($_POST['semester'] ?? '') == $i) ? 'selected' : ''; ?>"
                                    data-value="<?php echo $i; ?>"
                                >
                                    Semester <?php echo $i; ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <input type="hidden" name="semester" value="<?php echo htmlspecialchars($_POST['semester'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <!-- Upload Transkrip Nilai -->
            <div class="form-group">
                <label>Upload Transkrip Nilai <span class="required">*</span></label>

                <input
                    type="file"
                    name="transkrip"
                    id="transkrip"
                    class="file-input-hidden"
                    accept=".pdf,.jpg,.jpeg,.png"
                    required
                >

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
                        <button type="button" class="preview-remove" id="removeFile">X</button>
                    </div>
                </div>

                <p class="form-hint">Transkrip nilai akan direview oleh admin untuk verifikasi kemampuan akademik</p>
            </div>

            <!-- Keahlian (Multi-select) -->
            <div class="form-group">
                <label>Keahlian (pilih yang dikuasai)</label>
                <div class="expertise-grid">
                    <?php foreach ($expertiseList as $exp): ?>
                        <label class="expertise-item">
                            <input
                                type="checkbox"
                                name="expertise[]"
                                value="<?php echo htmlspecialchars($exp); ?>"
                                <?php echo in_array($exp, $_POST['expertise'] ?? [], true) ? 'checked' : ''; ?>
                            >
                            <span class="expertise-label"><?php echo htmlspecialchars($exp); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Bio -->
            <div class="form-group">
                <label for="bio">Bio Singkat</label>
                <textarea
                    id="bio"
                    name="bio"
                    class="auth-input auth-textarea"
                    placeholder="Ceritakan pengalaman dan keahlian lo..."
                ><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="auth-input"
                        placeholder="Minimal 6 karakter"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="auth-input"
                        placeholder="Ulangi password"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn btn-mentor auth-button">Daftar sebagai Mentor</button>
        </form>

        <p class="auth-footer-text">
            Sudah punya akun mentor? <a href="<?php echo htmlspecialchars(url_path('mentor-login.php')); ?>">Login di sini</a>
        </p>

        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ===== CUSTOM SELECT =====
        const customSelects = document.querySelectorAll('.custom-select');

        customSelects.forEach(select => {
            const selected = select.querySelector('.select-selected');
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
            customSelects.forEach(select => select.classList.remove('active'));
        });

        // ===== FILE UPLOAD =====
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('transkrip');
        const previewName = document.getElementById('previewName');
        const previewSize = document.getElementById('previewSize');
        const removeFile = document.getElementById('removeFile');

        uploadArea.addEventListener('click', function(e) {
            if (e.target === removeFile || removeFile.contains(e.target)) return;
            fileInput.click();
        });

        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(files[0]);
                fileInput.files = dataTransfer.files;
            }
        });

        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) handleFileSelect(this.files[0]);
        });

        removeFile.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.value = '';
            uploadArea.classList.remove('has-file');
        });

        function handleFileSelect(file) {
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            const maxSize = 5 * 1024 * 1024;

            if (!allowedTypes.includes(file.type)) {
                alert('Format file harus PDF, JPG, atau PNG');
                fileInput.value = '';
                return;
            }

            if (file.size > maxSize) {
                alert('Ukuran file maksimal 5MB');
                fileInput.value = '';
                return;
            }

            previewName.textContent = file.name;
            previewSize.textContent = formatFileSize(file.size);
            uploadArea.classList.add('has-file');
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }
    });
    </script>
</body>
</html>
