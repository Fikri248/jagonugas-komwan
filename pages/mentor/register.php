<?php
// pages/mentor/register.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../ModelUser.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = (new Database())->getConnection();
    $user = new User($db);

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $programStudi = trim($_POST['program_studi'] ?? '');
    $semester = intval($_POST['semester'] ?? 0);
    $expertise = $_POST['expertise'] ?? [];
    $bio = trim($_POST['bio'] ?? '');

    // Validasi dasar
    if (empty($name) || empty($email) || empty($password) || empty($programStudi) || $semester < 3) {
        $error = 'Semua field wajib diisi. Mentor minimal semester 3.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok';
    } elseif (empty($expertise)) {
        $error = 'Pilih minimal 1 keahlian';
    } elseif (!isset($_FILES['transkrip']) || $_FILES['transkrip']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Upload transkrip nilai wajib diisi';
    } else {
        // Validasi file transkrip
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $file = $_FILES['transkrip'];
        $fileType = mime_content_type($file['tmp_name']);
        $fileSize = $file['size'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $error = 'Format file harus PDF, JPG, atau PNG';
        } elseif ($fileSize > $maxSize) {
            $error = 'Ukuran file maksimal 5MB';
        } else {
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'transkrip_' . time() . '_' . uniqid() . '.' . $extension;
            $uploadPath = __DIR__ . '/../../uploads/transkrip/';
            
            // Buat folder jika belum ada
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            $destination = $uploadPath . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Simpan ke database
                $user->name = $name;
                $user->email = $email;
                $user->password = $password;
                $user->program_studi = $programStudi;
                $user->semester = $semester;
                $user->role = 'mentor';

                $result = $user->registerMentor($expertise, $bio, 'uploads/transkrip/' . $filename);

                if ($result['success']) {
                    $success = 'Pendaftaran mentor berhasil! Akun akan diverifikasi oleh admin dalam 1x24 jam.';
                } else {
                    // Hapus file jika gagal simpan ke DB
                    unlink($destination);
                    $error = $result['message'];
                }
            } else {
                $error = 'Gagal mengupload file. Coba lagi.';
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
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card auth-card-mentor auth-card-wide">
        <a href="<?php echo BASE_PATH; ?>/" class="auth-back-btn">
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

        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
            
            <div class="pending-verification">
                <div class="pending-icon">⏳</div>
                <h3>Menunggu Verifikasi</h3>
                <p>Tim admin sedang mereview transkrip nilai kamu. Kamu akan menerima notifikasi setelah akun diverifikasi.</p>
                <a href="<?php echo BASE_PATH; ?>/mentor/login" class="btn btn-mentor btn-full">Cek Status Login</a>
            </div>
        <?php else: ?>

        <form method="POST" class="auth-form" enctype="multipart/form-data">
            <div class="form-row">
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
                           placeholder="email@telkomuniversity.ac.id" required>
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

                <!-- Semester (minimal 3) -->
                <div class="form-group">
                    <label>Semester (min. 3)</label>
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
                            <?php for ($i = 3; $i <= 14; $i++): ?>
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
            </div>

            <!-- Upload Transkrip Nilai -->
            <div class="form-group">
                <label>Upload Transkrip Nilai <span class="required">*</span></label>
                
                <!-- File input di LUAR upload-area -->
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
                        <div class="preview-icon">📄</div>
                        <div class="preview-info">
                            <span class="preview-name" id="previewName"></span>
                            <span class="preview-size" id="previewSize"></span>
                        </div>
                        <button type="button" class="preview-remove" id="removeFile">✕</button>
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
                            <input type="checkbox" name="expertise[]" value="<?php echo $exp; ?>"
                                <?php echo in_array($exp, $_POST['expertise'] ?? []) ? 'checked' : ''; ?>>
                            <span class="expertise-label"><?php echo $exp; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Bio -->
            <div class="form-group">
                <label for="bio">Bio Singkat</label>
                <textarea id="bio" name="bio" class="auth-input auth-textarea" 
                          placeholder="Ceritakan pengalaman dan keahlian lo..."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
            </div>

            <div class="form-row">
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
            </div>

            <button type="submit" class="btn btn-mentor auth-button">Daftar sebagai Mentor</button>
        </form>

        <p class="auth-footer-text">
            Sudah punya akun mentor? <a href="<?php echo BASE_PATH; ?>/mentor/login">Login di sini</a>
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
            customSelects.forEach(select => {
                select.classList.remove('active');
            });
        });

        // ===== FILE UPLOAD =====
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('transkrip');
        const previewName = document.getElementById('previewName');
        const previewSize = document.getElementById('previewSize');
        const removeFile = document.getElementById('removeFile');

        // Klik upload area = buka file dialog
        uploadArea.addEventListener('click', function(e) {
            if (e.target === removeFile || removeFile.contains(e.target)) {
                return;
            }
            fileInput.click();
        });

        // Drag over
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.add('dragover');
        });

        // Drag leave
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('dragover');
        });

        // Drop file
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

        // File input change
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFileSelect(this.files[0]);
            }
        });

        // Remove file
        removeFile.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.value = '';
            uploadArea.classList.remove('has-file');
        });

        // Handle file selection
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

        // Format file size
        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }
    });
    </script>
</body>
</html>
