<?php
// student-forum-create.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

// Defensive: fallback kalau BASE_PATH ga ke-define
$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Harus login
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $BASE . "/login.php?redirect=student-forum-create.php");
    exit;
}

$userId = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';
$errors = [];
$success = false;

// Database connection
$pdo = null;
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get categories
$categories = $pdo->query("SELECT * FROM forum_categories ORDER BY name")->fetchAll();

// Get user gems
$stmt = $pdo->prepare("SELECT gems FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userGems = $stmt->fetchColumn();

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $gemReward = min(50, max(5, (int)($_POST['gem_reward'] ?? 5)));
    
    // Validation
    if (empty($title)) {
        $errors[] = "Judul pertanyaan wajib diisi";
    } elseif (strlen($title) < 10) {
        $errors[] = "Judul minimal 10 karakter";
    }
    
    if (empty($content)) {
        $errors[] = "Isi pertanyaan wajib diisi";
    } elseif (strlen($content) < 20) {
        $errors[] = "Isi pertanyaan minimal 20 karakter";
    }
    
    if ($categoryId <= 0) {
        $errors[] = "Pilih kategori";
    }
    
    if ($gemReward > $userGems) {
        $errors[] = "Gem tidak cukup. Kamu punya $userGems gem.";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert thread
            $stmt = $pdo->prepare("INSERT INTO forum_threads (user_id, category_id, title, content, gem_reward) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $categoryId, $title, $content, $gemReward]);
            $threadId = $pdo->lastInsertId();
            
            // Deduct gems from user
            $stmt = $pdo->prepare("UPDATE users SET gems = gems - ? WHERE id = ?");
            $stmt->execute([$gemReward, $userId]);
            
            // Handle file uploads
            if (!empty($_FILES['attachments']['name'][0])) {
                $uploadDir = __DIR__ . '/uploads/forum/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                        $fileName = $_FILES['attachments']['name'][$key];
                        $fileSize = $_FILES['attachments']['size'][$key];
                        $fileType = $_FILES['attachments']['type'][$key];
                        
                        // Max 5MB
                        if ($fileSize > 5 * 1024 * 1024) continue;
                        
                        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                        $newName = uniqid() . '_' . time() . '.' . $ext;
                        $filePath = 'uploads/forum/' . $newName;
                        
                        if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                            $stmt = $pdo->prepare("INSERT INTO forum_attachments (thread_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$threadId, $fileName, $filePath, $fileType, $fileSize]);
                        }
                    }
                }
            }
            
            // Kirim notifikasi thread dibuat
            $notif = new NotificationHelper($pdo);
            $notif->threadCreated($userId, $threadId, $title);
            
            $pdo->commit();
            header("Location: " . $BASE . "/student-forum-thread.php?id=$threadId");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Gagal membuat pertanyaan: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Pertanyaan - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo $BASE; ?>/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="forum-page">
    <?php include __DIR__ . '/student-navbar.php'; ?>

    <div class="forum-create-container">
        <div class="forum-create-card">
            <div class="forum-create-header">
                <a href="<?php echo $BASE; ?>/student-forum.php" class="back-link">
                    <i class="bi bi-arrow-left"></i> Kembali ke Forum
                </a>
                <h1>Buat Pertanyaan Baru</h1>
                <p>Jelaskan pertanyaanmu dengan detail agar mudah dijawab</p>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i>
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="forum-create-form">
                <!-- Judul -->
                <div class="form-group">
                    <label for="title">Judul Pertanyaan <span class="required">*</span></label>
                    <input type="text" id="title" name="title" 
                           placeholder="Contoh: Bagaimana cara membuat JOIN di MySQL?"
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                           required>
                    <small>Buat judul yang jelas dan spesifik</small>
                </div>

                <!-- Kategori Custom Dropdown -->
                <div class="form-group">
                    <label>Kategori <span class="required">*</span></label>
                    <div class="custom-select-wrapper">
                        <div class="custom-select" id="categorySelect">
                            <div class="custom-select-trigger">
                                <span>Pilih Kategori</span>
                                <i class="bi bi-chevron-down"></i>
                            </div>
                            <div class="custom-options">
                                <?php foreach ($categories as $cat): ?>
                                <div class="custom-option" 
                                     data-value="<?php echo $cat['id']; ?>" 
                                     data-icon="<?php echo $cat['icon']; ?>" 
                                     data-color="<?php echo $cat['color']; ?>">
                                    <div class="option-icon" style="background: <?php echo $cat['color']; ?>20; color: <?php echo $cat['color']; ?>">
                                        <i class="bi <?php echo $cat['icon']; ?>"></i>
                                    </div>
                                    <div class="option-info">
                                        <span class="option-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                                        <span class="option-desc"><?php echo htmlspecialchars($cat['description']); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" name="category_id" id="categoryInput" 
                               value="<?php echo $_POST['category_id'] ?? ''; ?>" required>
                    </div>
                </div>

                <!-- Detail Pertanyaan -->
                <div class="form-group">
                    <label for="content">Detail Pertanyaan <span class="required">*</span></label>
                    <textarea id="content" name="content" rows="8" 
                              placeholder="Jelaskan pertanyaanmu secara detail. Sertakan kode, error message, atau langkah yang sudah dicoba..."
                              required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                    <small>Semakin detail, semakin mudah dijawab</small>
                </div>

                <!-- Lampiran -->
                <div class="form-group">
                    <label>Lampiran (Opsional)</label>
                    <div class="file-upload-area" id="dropZone">
                        <i class="bi bi-cloud-upload"></i>
                        <p>Drag & drop file atau <span>pilih file</span></p>
                        <small>Maks 5MB per file (gambar, PDF, dokumen)</small>
                        <input type="file" id="attachments" name="attachments[]" multiple 
                               accept="image/*,.pdf,.doc,.docx,.txt">
                    </div>
                    <div id="fileList" class="file-list"></div>
                </div>

                <!-- Reward Gem -->
                <div class="form-group">
                    <label>Reward Gem untuk Jawaban Terbaik</label>
                    <div class="gem-reward-selector">
                        <div class="gem-reward-header">
                            <div class="gem-balance">
                                <i class="bi bi-gem"></i>
                                <span>Saldo: <strong><?php echo number_format($userGems, 0, ',', '.'); ?></strong> gem</span>
                            </div>
                        </div>
                        <div class="gem-reward-options">
                            <label class="gem-option">
                                <input type="radio" name="gem_reward" value="5" <?php echo ($_POST['gem_reward'] ?? 5) == 5 ? 'checked' : ''; ?>>
                                <div class="gem-option-card">
                                    <span class="gem-amount">5</span>
                                    <span class="gem-label">gem</span>
                                </div>
                            </label>
                            <label class="gem-option">
                                <input type="radio" name="gem_reward" value="10" <?php echo ($_POST['gem_reward'] ?? '') == 10 ? 'checked' : ''; ?>>
                                <div class="gem-option-card">
                                    <span class="gem-amount">10</span>
                                    <span class="gem-label">gem</span>
                                </div>
                            </label>
                            <label class="gem-option">
                                <input type="radio" name="gem_reward" value="20" <?php echo ($_POST['gem_reward'] ?? '') == 20 ? 'checked' : ''; ?>>
                                <div class="gem-option-card">
                                    <span class="gem-amount">20</span>
                                    <span class="gem-label">gem</span>
                                </div>
                            </label>
                            <label class="gem-option">
                                <input type="radio" name="gem_reward" value="50" <?php echo ($_POST['gem_reward'] ?? '') == 50 ? 'checked' : ''; ?>>
                                <div class="gem-option-card popular">
                                    <span class="popular-badge">Populer</span>
                                    <span class="gem-amount">50</span>
                                    <span class="gem-label">gem</span>
                                </div>
                            </label>
                        </div>
                        <small><i class="bi bi-info-circle"></i> Gem akan diberikan ke jawaban terbaik yang kamu pilih</small>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="<?php echo $BASE; ?>/student-forum.php" class="btn btn-outline">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Kirim Pertanyaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // ===== CUSTOM SELECT DROPDOWN =====
    const customSelect = document.getElementById('categorySelect');
    const trigger = customSelect.querySelector('.custom-select-trigger');
    const options = customSelect.querySelectorAll('.custom-option');
    const hiddenInput = document.getElementById('categoryInput');

    trigger.addEventListener('click', () => {
        customSelect.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (!customSelect.contains(e.target)) {
            customSelect.classList.remove('open');
        }
    });

    options.forEach(option => {
        option.addEventListener('click', () => {
            options.forEach(opt => opt.classList.remove('selected'));
            option.classList.add('selected');
            
            const value = option.dataset.value;
            const icon = option.dataset.icon;
            const color = option.dataset.color;
            const name = option.querySelector('.option-name').textContent;
            
            trigger.innerHTML = `
                <div class="selected-preview">
                    <div class="selected-icon" style="background: ${color}20; color: ${color}">
                        <i class="bi ${icon}"></i>
                    </div>
                    <span class="selected-name">${name}</span>
                </div>
                <i class="bi bi-chevron-down"></i>
            `;
            trigger.classList.add('has-value');
            hiddenInput.value = value;
            customSelect.classList.remove('open');
        });
    });

    const initialValue = hiddenInput.value;
    if (initialValue) {
        const initialOption = customSelect.querySelector(`[data-value="${initialValue}"]`);
        if (initialOption) {
            initialOption.click();
        }
    }

    // ===== FILE UPLOAD =====
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('attachments');
    const fileList = document.getElementById('fileList');

    dropZone.addEventListener('click', () => fileInput.click());
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        fileInput.files = e.dataTransfer.files;
        updateFileList();
    });
    
    fileInput.addEventListener('change', updateFileList);
    
    function updateFileList() {
        fileList.innerHTML = '';
        Array.from(fileInput.files).forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'file-item';
            
            let icon = 'bi-file-earmark';
            if (file.type.startsWith('image/')) icon = 'bi-file-image';
            else if (file.type === 'application/pdf') icon = 'bi-file-pdf';
            else if (file.type.includes('word')) icon = 'bi-file-word';
            
            item.innerHTML = `
                <i class="bi ${icon}"></i>
                <span class="file-name">${file.name}</span>
                <small class="file-size">${(file.size / 1024).toFixed(1)} KB</small>
                <button type="button" class="file-remove" onclick="this.parentElement.remove()">
                    <i class="bi bi-x"></i>
                </button>
            `;
            fileList.appendChild(item);
        });
    }
    </script>
</body>
</html>
