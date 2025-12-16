<?php
// student-diskusi.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Model/ModelDiskusi.php';

// Defensive: fallback kalau BASEPATH ga ke-define
$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: " . $BASE . "/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'User';

// Database connection
$pdo = null;
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$diskusi = new Diskusi($pdo);

// Messages
$successMsg = '';
$errorMsg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $judul = trim($_POST['judul'] ?? '');
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    
    // Validation
    if (empty($judul)) {
        $errorMsg = 'Judul tidak boleh kosong';
    } elseif (strlen($judul) < 5) {
        $errorMsg = 'Judul minimal 5 karakter';
    } elseif (empty($pertanyaan)) {
        $errorMsg = 'Pertanyaan tidak boleh kosong';
    } elseif (strlen($pertanyaan) < 10) {
        $errorMsg = 'Pertanyaan minimal 10 karakter';
    } else {
        try {
            $diskusi->user_id = $userId;
            $diskusi->judul = $judul;
            $diskusi->pertanyaan = $pertanyaan;
            
            // Handle file upload
            $file = $_FILES['image'] ?? null;
            if (!empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file['type'], $allowedTypes)) {
                    $errorMsg = 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.';
                } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB max
                    $errorMsg = 'Ukuran file maksimal 5MB';
                } else {
                    // Create upload directory if not exists
                    $uploadDir = __DIR__ . "/uploads/diskusi";
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'diskusi_' . $userId . '_' . time() . '.' . $ext;
                    $filepath = $uploadDir . '/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $diskusi->image_path = "uploads/diskusi/" . $filename;
                    } else {
                        $errorMsg = 'Gagal mengupload file';
                    }
                }
            } else {
                $diskusi->image_path = "";
            }
            
            // Create discussion if no errors
            if (empty($errorMsg)) {
                if ($diskusi->create()) {
                    header("Location: " . $BASE . "/student-diskusi.php?success=1");
                    exit;
                } else {
                    $errorMsg = 'Gagal membuat diskusi. Silakan coba lagi.';
                }
            }
        } catch (Exception $e) {
            $errorMsg = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Get all discussions
$pertanyaans = [];
try {
    $pertanyaans = $diskusi->getAll();
} catch (Exception $e) {
    $errorMsg = 'Gagal memuat diskusi: ' . $e->getMessage();
}

// Success message from redirect
if (isset($_GET['success'])) {
    $successMsg = 'Pertanyaan berhasil dikirim!';
}

// Helper function for time elapsed
function time_elapsed($datetime) {
    $tz = new DateTimeZone('Asia/Jakarta');
    $now = new DateTime('now', $tz);
    $ago = new DateTime($datetime, $tz);
    $diff = $now->diff($ago);
    
    if ($diff->d > 0) return $diff->d . ' hari yang lalu';
    if ($diff->h > 0) return $diff->h . ' jam yang lalu';
    if ($diff->i > 0) return $diff->i . ' menit yang lalu';
    return 'Baru saja';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum Diskusi - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo $BASE; ?>/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="dashboard-page">
    
    <?php include __DIR__ . '/student-navbar.php'; ?>

    <div class="dash-container">
        <main class="dash-main" style="grid-column: 1 / -1; max-width: 900px; margin: 0 auto;">
            
            <!-- Page Header -->
            <div class="dash-section-header" style="text-align: left; margin-bottom: 24px;">
                <a href="<?php echo $BASE; ?>/student-dashboard.php" class="btn btn-text" style="padding-left: 0;">
                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
                <h1 style="font-size: 2rem; margin: 12px 0 8px;">Forum Diskusi</h1>
                <p style="color: #6b7280; font-size: 1rem;">Ajukan pertanyaan dan diskusikan dengan mahasiswa lain</p>
            </div>

            <!-- Success Message -->
            <?php if ($successMsg): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
            </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($errorMsg): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?>
            </div>
            <?php endif; ?>

            <!-- Create Discussion Form -->
            <section class="dash-card" style="margin-bottom: 32px; padding: 24px;">
                <h2 style="font-size: 1.25rem; margin-bottom: 16px;">
                    <i class="bi bi-plus-circle"></i> Buat Pertanyaan Baru
                </h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            Judul Pertanyaan <span style="color: #e53e3e;">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="judul" 
                            placeholder="Contoh: Bagaimana cara membuat JOIN di MySQL?" 
                            required
                            value="<?php echo htmlspecialchars($_POST['judul'] ?? ''); ?>"
                            style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px;"
                        >
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            Detail Pertanyaan <span style="color: #e53e3e;">*</span>
                        </label>
                        <textarea 
                            name="pertanyaan" 
                            rows="5" 
                            placeholder="Jelaskan pertanyaan kamu secara detail..." 
                            required
                            style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; resize: vertical;"
                        ><?php echo htmlspecialchars($_POST['pertanyaan'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            Lampiran Gambar (Opsional)
                        </label>
                        <input 
                            type="file" 
                            name="image" 
                            accept="image/jpeg,image/png,image/gif,image/webp"
                        >
                        <small style="display: block; color: #718096; margin-top: 4px;">JPG, PNG, GIF, WebP - Maksimal 5MB</small>
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Kirim Pertanyaan
                        </button>
                        <button type="reset" class="btn btn-outline">
                            <i class="bi bi-x-circle"></i> Reset
                        </button>
                    </div>
                </form>
            </section>

            <!-- Discussions List -->
            <section>
                <h2 style="font-size: 1.25rem; margin-bottom: 16px;">
                    <i class="bi bi-chat-dots"></i> Semua Pertanyaan (<?php echo count($pertanyaans); ?>)
                </h2>

                <?php if (empty($pertanyaans)): ?>
                    <div class="dash-empty-state">
                        <i class="bi bi-chat-square-text"></i>
                        <h3>Belum Ada Diskusi</h3>
                        <p>Jadilah yang pertama untuk memulai diskusi!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pertanyaans as $row): ?>
                        <article class="dash-card" style="margin-bottom: 20px; padding: 20px;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                                <div class="dash-avatar sm">
                                    <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1a202c;">
                                        <?php echo htmlspecialchars($row['name']); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #718096;">
                                        <i class="bi bi-clock"></i> 
                                        <?php echo time_elapsed($row['created_at']); ?>
                                    </div>
                                </div>
                                <?php if ($row['user_id'] == $userId): ?>
                                    <span style="margin-left: auto; padding: 4px 12px; background: #eef2ff; color: #667eea; border-radius: 999px; font-size: 0.85rem; font-weight: 600;">
                                        <i class="bi bi-person-badge"></i> Pertanyaan Kamu
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 12px;">
                                <?php echo htmlspecialchars($row['judul']); ?>
                            </h3>

                            <p style="color: #4a5568; line-height: 1.6; margin-bottom: 16px;">
                                <?php echo nl2br(htmlspecialchars($row['pertanyaan'])); ?>
                            </p>

                            <?php if (!empty($row['image_path'])): ?>
                                <div style="margin-bottom: 16px; border-radius: 8px; overflow: hidden;">
                                    <img 
                                        src="<?php echo $BASE . '/' . htmlspecialchars($row['image_path']); ?>" 
                                        alt="Lampiran"
                                        style="width: 100%; height: auto; display: block;"
                                    >
                                </div>
                            <?php endif; ?>

                            <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 0.9rem; color: #718096;">
                                <div style="display: flex; gap: 16px;">
                                    <span><i class="bi bi-chat"></i> 0 Balasan</span>
                                    <span><i class="bi bi-eye"></i> 0 Views</span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

        </main>
    </div>

    <script>
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    </script>

</body>
</html>
