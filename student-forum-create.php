<?php
// student-forum-create.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: " . $BASE . "/login.php?redirect=student-forum-create.php");
    exit;
}

$userId = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';
$errors = [];
$success = false;

$pdo = null;
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$categories = $pdo->query("SELECT * FROM forum_categories ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("SELECT gems FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userGems = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $gemReward = min(50, max(5, (int)($_POST['gem_reward'] ?? 5)));
    
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
            
            $stmt = $pdo->prepare("INSERT INTO forum_threads (user_id, category_id, title, content, gem_reward) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $categoryId, $title, $content, $gemReward]);
            $threadId = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("UPDATE users SET gems = gems - ? WHERE id = ?");
            $stmt->execute([$gemReward, $userId]);
            
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f8fafc; min-height: 100vh; }
        
        /* ===== FORUM PAGE ===== */
        .forum-page { background: #f8fafc; min-height: 100vh; }
        
        /* ===== BUTTONS ===== */
        .btn { padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
        .btn-outline { border: 2px solid #e2e8f0; color: #475569; background: white; }
        .btn-outline:hover { border-color: #667eea; color: #667eea; }
        
        /* ===== ALERTS ===== */
        .alert { padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 0.9rem; }
        .alert-error { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; border: 1px solid #fecaca; }
        .alert-error ul { margin: 8px 0 0 20px; }
        .alert-error li { margin: 4px 0; }
        
        /* ===== FORUM CREATE CONTAINER ===== */
        .forum-create-container { max-width: 800px; margin: 0 auto; padding: 32px 24px; }
        
        /* ===== FORUM CREATE CARD ===== */
        .forum-create-card { background: white; border-radius: 20px; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06); overflow: hidden; }
        
        .forum-create-header { padding: 32px 32px 24px; border-bottom: 1px solid #f1f5f9; }
        .forum-create-header h1 { font-size: 1.75rem; font-weight: 700; color: #1e293b; margin: 16px 0 8px; }
        .forum-create-header p { color: #64748b; font-size: 1rem; }
        
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: all 0.2s; }
        .back-link:hover { color: #667eea; }
        
        /* ===== FORM ===== */
        .forum-create-form { padding: 32px; }
        
        .form-group { margin-bottom: 24px; }
        .form-group label { display: block; font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 0.95rem; }
        .form-group .required { color: #ef4444; }
        .form-group small { display: block; color: #94a3b8; margin-top: 6px; font-size: 0.85rem; }
        
        .form-group input[type="text"],
        .form-group textarea { width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s; outline: none; background: #f8fafc; font-family: inherit; }
        .form-group input[type="text"]:focus,
        .form-group textarea:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); background: white; }
        .form-group textarea { resize: vertical; min-height: 160px; }
        
        /* ===== CUSTOM SELECT ===== */
        .custom-select-wrapper { position: relative; }
        .custom-select { position: relative; }
        
        .custom-select-trigger { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; background: #f8fafc; cursor: pointer; transition: all 0.2s; }
        .custom-select-trigger:hover { border-color: #cbd5e1; }
        .custom-select.open .custom-select-trigger { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); background: white; }
        .custom-select-trigger.has-value { background: white; }
        .custom-select-trigger span { color: #94a3b8; }
        .custom-select-trigger.has-value span { color: #1e293b; }
        
        .selected-preview { display: flex; align-items: center; gap: 12px; }
        .selected-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .selected-name { font-weight: 600; color: #1e293b; }
        
        .custom-options { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: white; border: 2px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12); max-height: 300px; overflow-y: auto; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
        .custom-select.open .custom-options { opacity: 1; visibility: visible; transform: translateY(0); }
        
        .custom-option { display: flex; align-items: center; gap: 12px; padding: 14px 16px; cursor: pointer; transition: all 0.2s; border-bottom: 1px solid #f1f5f9; }
        .custom-option:last-child { border-bottom: none; }
        .custom-option:hover { background: #f8fafc; }
        .custom-option.selected { background: linear-gradient(135deg, #eef2ff, #e0e7ff); }
        
        .option-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .option-info { flex: 1; }
        .option-name { display: block; font-weight: 600; color: #1e293b; font-size: 0.95rem; }
        .option-desc { font-size: 0.8rem; color: #64748b; }
        
        /* ===== FILE UPLOAD ===== */
        .file-upload-area { border: 2px dashed #e2e8f0; border-radius: 12px; padding: 32px; text-align: center; cursor: pointer; transition: all 0.2s; background: #f8fafc; }
        .file-upload-area:hover { border-color: #667eea; background: #f0f4ff; }
        .file-upload-area.dragover { border-color: #667eea; background: #eef2ff; transform: scale(1.01); }
        .file-upload-area i { font-size: 2.5rem; color: #94a3b8; margin-bottom: 12px; display: block; }
        .file-upload-area p { color: #64748b; margin-bottom: 4px; }
        .file-upload-area p span { color: #667eea; font-weight: 600; }
        .file-upload-area small { color: #94a3b8; font-size: 0.8rem; }
        .file-upload-area input[type="file"] { display: none; }
        
        .file-list { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
        .file-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; }
        .file-item i { color: #667eea; font-size: 1.2rem; }
        .file-name { flex: 1; font-weight: 500; color: #1e293b; font-size: 0.9rem; }
        .file-size { color: #94a3b8; font-size: 0.8rem; }
        .file-remove { background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px; border-radius: 4px; transition: all 0.2s; }
        .file-remove:hover { background: #fee2e2; color: #ef4444; }
        
        /* ===== GEM REWARD SELECTOR ===== */
        .gem-reward-selector { background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; }
        .gem-reward-header { margin-bottom: 16px; }
        .gem-balance { display: flex; align-items: center; gap: 8px; font-size: 0.95rem; color: #475569; }
        .gem-balance i { color: #667eea; }
        .gem-balance strong { color: #1e293b; }
        
        .gem-reward-options { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
        .gem-option { cursor: pointer; }
        .gem-option input { display: none; }
        .gem-option-card { padding: 16px; background: white; border: 2px solid #e2e8f0; border-radius: 12px; text-align: center; transition: all 0.2s; position: relative; }
        .gem-option input:checked + .gem-option-card { border-color: #667eea; background: linear-gradient(135deg, #eef2ff, #e0e7ff); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2); }
        .gem-option-card:hover { border-color: #cbd5e1; }
        .gem-amount { display: block; font-size: 1.5rem; font-weight: 700; color: #1e293b; }
        .gem-label { font-size: 0.8rem; color: #64748b; }
        .gem-option-card.popular { border-color: #f59e0b; }
        .gem-option input:checked + .gem-option-card.popular { border-color: #f59e0b; background: linear-gradient(135deg, #fffbeb, #fef3c7); }
        .popular-badge { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #f59e0b, #d97706); color: white; font-size: 0.7rem; font-weight: 600; padding: 3px 10px; border-radius: 50px; }
        
        .gem-reward-selector > small { display: flex; align-items: center; gap: 6px; color: #64748b; font-size: 0.85rem; }
        
        /* ===== FORM ACTIONS ===== */
        .form-actions { display: flex; justify-content: flex-end; gap: 12px; padding-top: 16px; border-top: 1px solid #f1f5f9; margin-top: 8px; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .forum-create-container { padding: 20px 16px; }
            .forum-create-header, .forum-create-form { padding: 24px 20px; }
            .gem-reward-options { grid-template-columns: repeat(2, 1fr); }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .forum-create-header h1 { font-size: 1.5rem; }
        }
    </style>
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
            <div class="alert alert-error" style="margin: 24px 32px 0;">
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
        if (initialOption) initialOption.click();
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
    
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        fileInput.files = e.dataTransfer.files;
        updateFileList();
    });
    
    fileInput.addEventListener('change', updateFileList);
    
    function updateFileList() {
        fileList.innerHTML = '';
        Array.from(fileInput.files).forEach((file) => {
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
