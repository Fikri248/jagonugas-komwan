<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/NotificationHelper.php';

$threadId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header("Location: " . BASE_PATH . "/login.php?redirect=student-forum-edit.php?id=$threadId");
    exit;
}

if (!$threadId) {
    header("Location: " . BASE_PATH . "/student-forum.php");
    exit;
}

// Get thread
$stmt = $pdo->prepare("SELECT * FROM forum_threads WHERE id = ?");
$stmt->execute([$threadId]);
$thread = $stmt->fetch();

if (!$thread || $thread['user_id'] != $userId) {
    header("Location: " . BASE_PATH . "/student-forum.php");
    exit;
}

// Get categories
$categories = $pdo->query("SELECT * FROM forum_categories ORDER BY name")->fetchAll();

// Get existing attachments
$stmt = $pdo->prepare("SELECT * FROM forum_attachments WHERE thread_id = ?");
$stmt->execute([$threadId]);
$attachments = $stmt->fetchAll();

$errors = [];
$success = false;

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    
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
    
    // Handle delete attachments
    $deleteAttachments = $_POST['delete_attachments'] ?? [];
    
    // Cek apakah ada perubahan
    $hasChanges = false;
    if ($title !== $thread['title'] || 
        $content !== $thread['content'] || 
        $categoryId !== (int)$thread['category_id'] ||
        !empty($deleteAttachments) ||
        !empty($_FILES['attachments']['name'][0])) {
        $hasChanges = true;
    }
    
    if (empty($errors)) {
        // Jika tidak ada perubahan, redirect tanpa update
        if (!$hasChanges) {
            header("Location: " . BASE_PATH . "/student-forum-thread.php?id=$threadId");
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Update thread
            $stmt = $pdo->prepare("UPDATE forum_threads SET title = ?, content = ?, category_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $content, $categoryId, $threadId]);
            
            // Delete selected attachments
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
            
            // Handle new file uploads
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
            
            // Kirim notifikasi thread diupdate
            $notif = new NotificationHelper($pdo);
            $notif->create(
                $userId,
                'thread_updated',
                'Pertanyaan "' . mb_substr($title, 0, 50) . '..." berhasil diperbarui.',
                $threadId,
                'thread'
            );
            
            $pdo->commit();
            header("Location: " . BASE_PATH . "/student-forum-thread.php?id=$threadId&updated=1");
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
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="forum-page">
    <?php include __DIR__ . '/student-navbar.php'; ?>

    <div class="forum-create-container">
        <div class="forum-create-card">
            <div class="forum-create-header">
                <a href="<?php echo BASE_PATH; ?>/student-forum-thread.php?id=<?php echo $threadId; ?>" class="back-link">
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

                <!-- Kategori -->
                <div class="form-group">
                    <label for="category_id">Kategori <span class="required">*</span></label>
                    <select name="category_id" id="category_id" required>
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" 
                                <?php echo ($cat['id'] == ($_POST['category_id'] ?? $thread['category_id'])) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
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
                                <span class="checkmark"></span>
                            </label>
                            <?php if (strpos($att['file_type'], 'image') !== false): ?>
                            <img src="<?php echo BASE_PATH . '/' . $att['file_path']; ?>" alt="">
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
                    <a href="<?php echo BASE_PATH; ?>/student-forum-thread.php?id=<?php echo $threadId; ?>" class="btn btn-outline">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // File upload preview
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
        Array.from(fileInput.files).forEach((file) => {
            const item = document.createElement('div');
            item.className = 'file-item';
            
            let icon = 'bi-file-earmark';
            if (file.type.startsWith('image/')) icon = 'bi-file-image';
            else if (file.type === 'application/pdf') icon = 'bi-file-pdf';
            
            item.innerHTML = `
                <i class="bi ${icon}"></i>
                <span class="file-name">${file.name}</span>
                <small class="file-size">${(file.size / 1024).toFixed(1)} KB</small>
            `;
            fileList.appendChild(item);
        });
    }
    </script>
</body>
</html>
