<?php
// student-forum-edit.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$threadId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header("Location: " . $BASE . "/login.php?redirect=student-forum-edit.php?id=$threadId");
    exit;
}

if (!$threadId) {
    header("Location: " . $BASE . "/student-forum.php");
    exit;
}

$pdo = null;
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$stmt = $pdo->prepare("SELECT * FROM forum_threads WHERE id = ?");
$stmt->execute([$threadId]);
$thread = $stmt->fetch();

if (!$thread || $thread['user_id'] != $userId) {
    header("Location: " . $BASE . "/student-forum.php");
    exit;
}

$categories = $pdo->query("SELECT * FROM forum_categories ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM forum_attachments WHERE thread_id = ?");
$stmt->execute([$threadId]);
$attachments = $stmt->fetchAll();

$errors = [];
$success = false;

// Get current category
$currentCategoryId = $_POST['category_id'] ?? $thread['category_id'];
$currentCategory = null;
foreach ($categories as $cat) {
    if ($cat['id'] == $currentCategoryId) {
        $currentCategory = $cat;
        break;
    }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    
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
    
    $deleteAttachments = $_POST['delete_attachments'] ?? [];
    
    $hasChanges = false;
    if ($title !== $thread['title'] || 
        $content !== $thread['content'] || 
        $categoryId !== (int)$thread['category_id'] ||
        !empty($deleteAttachments) ||
        !empty($_FILES['attachments']['name'][0])) {
        $hasChanges = true;
    }
    
    if (empty($errors)) {
        if (!$hasChanges) {
            header("Location: " . $BASE . "/student-forum-thread.php?id=$threadId");
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE forum_threads SET title = ?, content = ?, category_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $content, $categoryId, $threadId]);
            
            if (!empty($deleteAttachments)) {
                foreach ($deleteAttachments as $attId) {
                    $stmt = $pdo->prepare("SELECT file_path FROM forum_attachments WHERE id = ? AND thread_id = ?");
                    $stmt->execute([$attId, $threadId]);
                    $att = $stmt->fetch();
                    if ($att) {
                        $filePath = __DIR__ . '/' . $att['file_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        $pdo->prepare("DELETE FROM forum_attachments WHERE id = ?")->execute([$attId]);
                    }
                }
            }
            
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
            
            $pdo->commit();
            header("Location: " . $BASE . "/student-forum-thread.php?id=$threadId&updated=1");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Gagal mengupdate pertanyaan: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pertanyaan - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f8fafc; min-height: 100vh; }
        
        .forum-page { background: #f8fafc; min-height: 100vh; }
        
        /* ===== BUTTONS ===== */
        .btn { padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
        .btn-outline { border: 2px solid #e2e8f0; color: #64748b; background: white; }
        .btn-outline:hover { border-color: #667eea; color: #667eea; background: rgba(102, 126, 234, 0.05); }
        
        /* Tombol Simpan */
        .btn-save { 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
            color: white; 
            padding: 14px 28px;
            font-size: 1rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-save:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4); 
        }
        .btn-save i { font-size: 1.1rem; }
        
        /* ===== ALERTS ===== */
        .alert { padding: 16px 20px; border-radius: 12px; margin: 24px 32px 0; font-size: 0.9rem; }
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
        .form-group textarea:focus { border-color: #667eea; box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); background: white; }
        .form-group textarea { resize: vertical; min-height: 160px; }
        
        /* ===== CUSTOM SELECT DROPDOWN ===== */
        .custom-select-wrapper { position: relative; z-index: 100; }
        
        .custom-select { position: relative; }
        
        .custom-select-trigger { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 14px 16px; 
            border: 2px solid #e2e8f0; 
            border-radius: 12px; 
            background: #f8fafc; 
            cursor: pointer; 
            transition: all 0.2s; 
        }
        .custom-select-trigger:hover { border-color: #cbd5e1; background: white; }
        .custom-select.open .custom-select-trigger { 
            border-color: #667eea; 
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); 
            border-radius: 12px 12px 0 0; 
            background: white;
        }
        
        .custom-select-trigger > span { color: #94a3b8; font-size: 0.95rem; }
        .custom-select-trigger.has-value > span { color: #1e293b; font-weight: 500; }
        
        /* Icon chevron - STATIS, tidak interaktif */
        .custom-select-trigger .chevron-icon { 
            color: #94a3b8; 
            font-size: 1rem;
            pointer-events: none; /* Tidak bisa diklik */
            transition: transform 0.2s;
            margin-left: auto;
            flex-shrink: 0;
        }
        .custom-select.open .custom-select-trigger .chevron-icon { 
            transform: rotate(180deg); 
            color: #667eea; 
        }
        
        /* Selected Preview (icon + name) */
        .selected-preview { display: flex; align-items: center; gap: 12px; flex: 1; }
        .selected-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .selected-name { font-weight: 600; color: #1e293b; }
        
        /* Dropdown Options */
        .custom-options { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            right: 0; 
            background: white; 
            border: 2px solid #667eea; 
            border-top: none; 
            border-radius: 0 0 12px 12px; 
            max-height: 0; 
            overflow: hidden; 
            opacity: 0; 
            z-index: 1000; 
            transition: all 0.3s ease;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .custom-select.open .custom-options { max-height: 320px; overflow-y: auto; opacity: 1; }
        
        .custom-option { 
            display: flex; 
            align-items: center; 
            gap: 14px; 
            padding: 14px 16px; 
            cursor: pointer; 
            transition: all 0.15s; 
            border-bottom: 1px solid #f1f5f9; 
        }
        .custom-option:last-child { border-bottom: none; }
        .custom-option:hover { background: #f8fafc; }
        .custom-option.selected { background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); }
        
        .option-icon { 
            width: 40px; 
            height: 40px; 
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.1rem; 
            flex-shrink: 0; 
        }
        .option-info { display: flex; flex-direction: column; gap: 2px; }
        .option-name { font-weight: 600; color: #1e293b; font-size: 0.95rem; }
        .option-desc { font-size: 0.8rem; color: #64748b; }
        
        /* Custom Scrollbar */
        .custom-options::-webkit-scrollbar { width: 6px; }
        .custom-options::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .custom-options::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 10px; }
        
        /* ===== EXISTING ATTACHMENTS ===== */
        .existing-attachments { display: flex; flex-direction: column; gap: 12px; }
        .existing-attachment-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; transition: all 0.2s; }
        .existing-attachment-item:has(input:checked) { border-color: #ef4444; background: #fef2f2; }
        .existing-attachment-item img { width: 48px; height: 48px; object-fit: cover; border-radius: 8px; }
        .existing-attachment-item i { font-size: 1.5rem; color: #64748b; }
        .attachment-name { flex: 1; font-weight: 500; color: #1e293b; font-size: 0.9rem; }
        .delete-hint { font-size: 0.8rem; color: #94a3b8; }
        .existing-attachment-item:has(input:checked) .delete-hint { color: #ef4444; font-weight: 500; }
        
        .attachment-checkbox { position: relative; cursor: pointer; }
        .attachment-checkbox input { width: 20px; height: 20px; cursor: pointer; accent-color: #ef4444; }
        
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
        
        /* ===== EDIT INFO ===== */
        .edit-info { display: flex; align-items: center; gap: 10px; padding: 14px 18px; background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 12px; color: #1e40af; font-size: 0.9rem; margin-bottom: 24px; }
        .edit-info i { font-size: 1.1rem; }
        
        /* ===== FORM ACTIONS ===== */
        .form-actions { display: flex; justify-content: flex-end; gap: 12px; padding-top: 20px; border-top: 1px solid #f1f5f9; margin-top: 8px; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .forum-create-container { padding: 20px 16px; }
            .forum-create-header, .forum-create-form { padding: 24px 20px; }
            .alert { margin: 20px 20px 0; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .existing-attachment-item { flex-wrap: wrap; }
            .delete-hint { width: 100%; margin-left: 32px; margin-top: -4px; }
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
                <a href="<?php echo $BASE; ?>/student-forum-thread.php?id=<?php echo $threadId; ?>" class="back-link">
                    <i class="bi bi-arrow-left"></i> Kembali ke Pertanyaan
                </a>
                <h1>Edit Pertanyaan</h1>
                <p>Perbarui pertanyaanmu</p>
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
                           value="<?php echo htmlspecialchars($_POST['title'] ?? $thread['title']); ?>"
                           required>
                </div>

                <!-- Kategori Custom Dropdown -->
                <div class="form-group">
                    <label>Kategori <span class="required">*</span></label>
                    <div class="custom-select-wrapper">
                        <div class="custom-select" id="categorySelect">
                            <div class="custom-select-trigger <?php echo $currentCategory ? 'has-value' : ''; ?>">
                                <?php if ($currentCategory): ?>
                                <div class="selected-preview">
                                    <div class="selected-icon" style="background: <?php echo $currentCategory['color']; ?>20; color: <?php echo $currentCategory['color']; ?>">
                                        <i class="bi <?php echo $currentCategory['icon']; ?>"></i>
                                    </div>
                                    <span class="selected-name"><?php echo htmlspecialchars($currentCategory['name']); ?></span>
                                </div>
                                <?php else: ?>
                                <span>Pilih Kategori</span>
                                <?php endif; ?>
                                <i class="bi bi-chevron-down chevron-icon"></i>
                            </div>
                            <div class="custom-options">
                                <?php foreach ($categories as $cat): ?>
                                <div class="custom-option <?php echo $cat['id'] == $currentCategoryId ? 'selected' : ''; ?>" 
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
                               value="<?php echo $currentCategoryId; ?>" required>
                    </div>
                </div>

                <!-- Detail Pertanyaan -->
                <div class="form-group">
                    <label for="content">Detail Pertanyaan <span class="required">*</span></label>
                    <textarea id="content" name="content" rows="8" 
                              placeholder="Jelaskan pertanyaanmu secara detail..."
                              required><?php echo htmlspecialchars($_POST['content'] ?? $thread['content']); ?></textarea>
                </div>

                <!-- Existing Attachments -->
                <?php if (!empty($attachments)): ?>
                <div class="form-group">
                    <label>Lampiran Saat Ini</label>
                    <div class="existing-attachments">
                        <?php foreach ($attachments as $att): ?>
                        <div class="existing-attachment-item">
                            <label class="attachment-checkbox">
                                <input type="checkbox" name="delete_attachments[]" value="<?php echo $att['id']; ?>">
                            </label>
                            <?php if (strpos($att['file_type'], 'image') !== false): ?>
                            <img src="<?php echo $BASE . '/' . $att['file_path']; ?>" alt="">
                            <?php else: ?>
                            <i class="bi bi-file-earmark"></i>
                            <?php endif; ?>
                            <span class="attachment-name"><?php echo htmlspecialchars($att['file_name']); ?></span>
                            <span class="delete-hint">Centang untuk hapus</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- New Attachments -->
                <div class="form-group">
                    <label>Tambah Lampiran Baru (Opsional)</label>
                    <div class="file-upload-area" id="dropZone">
                        <i class="bi bi-cloud-upload"></i>
                        <p>Drag & drop file atau <span>pilih file</span></p>
                        <small>Maks 5MB per file</small>
                        <input type="file" id="attachments" name="attachments[]" multiple 
                               accept="image/*,.pdf,.doc,.docx,.txt">
                    </div>
                    <div id="fileList" class="file-list"></div>
                </div>

                <!-- Info -->
                <div class="edit-info">
                    <i class="bi bi-info-circle"></i>
                    <span>Gem reward tidak bisa diubah setelah pertanyaan dibuat.</span>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <a href="<?php echo $BASE; ?>/student-forum-thread.php?id=<?php echo $threadId; ?>" class="btn btn-outline">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-save">
                        <i class="bi bi-check2-circle"></i> Simpan Perubahan
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

    // Toggle dropdown saat klik trigger (termasuk icon chevron karena pointer-events: none)
    trigger.addEventListener('click', () => {
        customSelect.classList.toggle('open');
    });

    // Tutup dropdown saat klik di luar
    document.addEventListener('click', (e) => {
        if (!customSelect.contains(e.target)) {
            customSelect.classList.remove('open');
        }
    });

    // Handle pilih option
    options.forEach(option => {
        option.addEventListener('click', () => {
            options.forEach(opt => opt.classList.remove('selected'));
            option.classList.add('selected');
            
            const value = option.dataset.value;
            const icon = option.dataset.icon;
            const color = option.dataset.color;
            const name = option.querySelector('.option-name').textContent;
            
            // Update trigger content - icon chevron tetap statis
            trigger.innerHTML = `
                <div class="selected-preview">
                    <div class="selected-icon" style="background: ${color}20; color: ${color}">
                        <i class="bi ${icon}"></i>
                    </div>
                    <span class="selected-name">${name}</span>
                </div>
                <i class="bi bi-chevron-down chevron-icon"></i>
            `;
            trigger.classList.add('has-value');
            hiddenInput.value = value;
            customSelect.classList.remove('open');
        });
    });

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
