<?php
// pages/admin/mentors.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

// Cek login & role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_PATH . "/login");
    exit;
}

$db = (new Database())->getConnection();
$message = '';
$messageType = '';

// Handle Approve/Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mentorId = intval($_POST['mentor_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($mentorId > 0) {
        if ($action === 'approve') {
            $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = :id AND role = 'mentor'");
            $stmt->bindParam(':id', $mentorId);
            if ($stmt->execute()) {
                $message = 'Mentor berhasil diverifikasi!';
                $messageType = 'success';
            }
        } elseif ($action === 'reject') {
            // Ambil path transkrip untuk dihapus
            $stmt = $db->prepare("SELECT transkrip_path FROM users WHERE id = :id");
            $stmt->bindParam(':id', $mentorId);
            $stmt->execute();
            $mentor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Hapus file transkrip
            if ($mentor && $mentor['transkrip_path']) {
                $filePath = __DIR__ . '/../../' . $mentor['transkrip_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Hapus user dari database
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND role = 'mentor' AND is_verified = 0");
            $stmt->bindParam(':id', $mentorId);
            if ($stmt->execute()) {
                $message = 'Pendaftaran mentor ditolak dan dihapus.';
                $messageType = 'error';
            }
        }
    }
}

// Filter
$filter = $_GET['filter'] ?? 'pending';

// Query berdasarkan filter
if ($filter === 'pending') {
    $query = "SELECT * FROM users WHERE role = 'mentor' AND is_verified = 0 ORDER BY created_at DESC";
} elseif ($filter === 'verified') {
    $query = "SELECT * FROM users WHERE role = 'mentor' AND is_verified = 1 ORDER BY created_at DESC";
} else {
    $query = "SELECT * FROM users WHERE role = 'mentor' ORDER BY created_at DESC";
}

$stmt = $db->prepare($query);
$stmt->execute();
$mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count untuk tabs
$stmtPending = $db->query("SELECT COUNT(*) FROM users WHERE role = 'mentor' AND is_verified = 0");
$pendingCount = $stmtPending->fetchColumn();

$stmtVerified = $db->query("SELECT COUNT(*) FROM users WHERE role = 'mentor' AND is_verified = 1");
$verifiedCount = $stmtVerified->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mentor - Admin JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">
</head>
<body class="dashboard-page">
    <!-- Navbar Admin -->
    <header class="dash-navbar dash-navbar-admin">
        <div class="dash-container">
            <div class="dash-nav-inner">
                <div class="dash-logo">
                    <div class="dash-logo-mark admin">A</div>
                    <span class="dash-logo-text">JagoNugas <span class="role-badge admin">Admin</span></span>
                </div>
                
                <div class="dash-nav-right">
                    <nav class="dash-nav-links">
                        <a href="<?php echo BASE_PATH; ?>/admin/dashboard">Dashboard</a>
                        <a href="<?php echo BASE_PATH; ?>/admin/users">Users</a>
                        <a href="<?php echo BASE_PATH; ?>/admin/mentors" class="active">Mentors</a>
                        <a href="<?php echo BASE_PATH; ?>/admin/transactions">Transaksi</a>
                    </nav>
                    
                    <div class="dash-user-menu">
                        <div class="dash-avatar admin"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                        <div class="dash-user-info">
                            <span class="dash-user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                            <span class="dash-user-role">Administrator</span>
                        </div>
                        <div class="dash-dropdown">
                            <a href="<?php echo BASE_PATH; ?>/logout" class="logout">Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="dash-container dash-main">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <h1>🎓 Kelola Mentor</h1>
                <p>Review dan verifikasi pendaftaran mentor baru</p>
            </div>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php if ($messageType === 'success'): ?>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                <?php else: ?>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                <?php endif; ?>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                <span class="tab-icon">⏳</span>
                Menunggu Verifikasi
                <?php if ($pendingCount > 0): ?>
                    <span class="tab-badge"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="?filter=verified" class="filter-tab <?php echo $filter === 'verified' ? 'active' : ''; ?>">
                <span class="tab-icon">✅</span>
                Terverifikasi
                <span class="tab-count">(<?php echo $verifiedCount; ?>)</span>
            </a>
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <span class="tab-icon">👥</span>
                Semua Mentor
            </a>
        </div>

        <!-- Mentor List -->
        <?php if (empty($mentors)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>Tidak ada data</h3>
                <p>Belum ada mentor <?php echo $filter === 'pending' ? 'yang menunggu verifikasi' : ''; ?></p>
            </div>
        <?php else: ?>
            <div class="mentor-review-list">
                <?php foreach ($mentors as $mentor): ?>
                    <div class="mentor-review-card <?php echo $mentor['is_verified'] ? 'verified' : 'pending'; ?>">
                        <div class="mentor-review-header">
                            <div class="mentor-avatar">
                                <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                            </div>
                            <div class="mentor-info">
                                <h3><?php echo htmlspecialchars($mentor['name']); ?></h3>
                                <p class="mentor-email"><?php echo htmlspecialchars($mentor['email']); ?></p>
                                <div class="mentor-meta">
                                    <span class="meta-item">
                                        🎓 <?php echo htmlspecialchars($mentor['program_studi']); ?>
                                    </span>
                                    <span class="meta-item">
                                        📚 Semester <?php echo $mentor['semester']; ?>
                                    </span>
                                    <span class="meta-item">
                                        📅 <?php echo date('d M Y', strtotime($mentor['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mentor-status">
                                <?php if ($mentor['is_verified']): ?>
                                    <span class="status-badge verified">✓ Terverifikasi</span>
                                <?php else: ?>
                                    <span class="status-badge pending">⏳ Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Expertise -->
                        <?php if ($mentor['expertise']): ?>
                            <div class="mentor-expertise">
                                <strong>Keahlian:</strong>
                                <div class="expertise-tags">
                                    <?php 
                                    $expertiseArr = json_decode($mentor['expertise'], true) ?? [];
                                    foreach ($expertiseArr as $exp): 
                                    ?>
                                        <span class="expertise-tag"><?php echo htmlspecialchars($exp); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Bio -->
                        <?php if ($mentor['bio']): ?>
                            <div class="mentor-bio">
                                <strong>Bio:</strong>
                                <p><?php echo htmlspecialchars($mentor['bio']); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Transkrip Section -->
                        <?php if ($mentor['transkrip_path']): ?>
                            <div class="transkrip-section">
                                <strong>📄 Transkrip Nilai:</strong>
                                <div class="transkrip-actions">
                                    <?php 
                                    $ext = pathinfo($mentor['transkrip_path'], PATHINFO_EXTENSION);
                                    $isImage = in_array(strtolower($ext), ['jpg', 'jpeg', 'png']);
                                    ?>
                                    
                                    <a href="<?php echo BASE_PATH . '/' . $mentor['transkrip_path']; ?>" 
                                       target="_blank" class="btn-view-transkrip">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        Lihat Transkrip
                                    </a>
                                    
                                    <a href="<?php echo BASE_PATH . '/' . $mentor['transkrip_path']; ?>" 
                                       download class="btn-download-transkrip">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="7 10 12 15 17 10"/>
                                            <line x1="12" y1="15" x2="12" y2="3"/>
                                        </svg>
                                        Download
                                    </a>
                                </div>

                                <!-- Preview jika gambar -->
                                <?php if ($isImage): ?>
                                    <div class="transkrip-preview">
                                        <img src="<?php echo BASE_PATH . '/' . $mentor['transkrip_path']; ?>" 
                                             alt="Transkrip <?php echo htmlspecialchars($mentor['name']); ?>"
                                             onclick="openImageModal(this.src)">
                                        <p class="preview-hint">Klik gambar untuk memperbesar</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons (hanya untuk pending) -->
                        <?php if (!$mentor['is_verified']): ?>
                            <div class="mentor-review-actions">
                                <button type="button" class="btn btn-approve" 
                                        onclick="showConfirmModal('approve', <?php echo $mentor['id']; ?>, '<?php echo htmlspecialchars($mentor['name'], ENT_QUOTES); ?>')">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Setujui Mentor
                                </button>
                                
                                <button type="button" class="btn btn-reject"
                                        onclick="showConfirmModal('reject', <?php echo $mentor['id']; ?>, '<?php echo htmlspecialchars($mentor['name'], ENT_QUOTES); ?>')">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                    Tolak
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Image Modal -->
    <div class="image-modal" id="imageModal" onclick="closeImageModal()">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <img id="modalImage" src="" alt="Transkrip Preview">
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="confirm-modal-overlay" id="confirmModal">
        <div class="confirm-modal">
            <div class="confirm-modal-icon" id="confirmIcon">
                <!-- Icon akan diubah via JS -->
            </div>
            <h3 class="confirm-modal-title" id="confirmTitle">Konfirmasi</h3>
            <p class="confirm-modal-message" id="confirmMessage">Apakah Anda yakin?</p>
            
            <form method="POST" id="confirmForm">
                <input type="hidden" name="mentor_id" id="confirmMentorId">
                <input type="hidden" name="action" id="confirmAction">
                
                <div class="confirm-modal-actions">
                    <button type="button" class="btn-modal-cancel" onclick="closeConfirmModal()">
                        Batal
                    </button>
                    <button type="submit" class="btn-modal-confirm" id="confirmButton">
                        Konfirmasi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Image Modal
    function openImageModal(src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        document.getElementById('imageModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Confirm Modal
    function showConfirmModal(action, mentorId, mentorName) {
        const modal = document.getElementById('confirmModal');
        const icon = document.getElementById('confirmIcon');
        const title = document.getElementById('confirmTitle');
        const message = document.getElementById('confirmMessage');
        const confirmBtn = document.getElementById('confirmButton');
        
        document.getElementById('confirmMentorId').value = mentorId;
        document.getElementById('confirmAction').value = action;
        
        if (action === 'approve') {
            icon.innerHTML = `
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="9 12 12 15 16 10"/>
                </svg>
            `;
            icon.className = 'confirm-modal-icon approve';
            title.textContent = 'Setujui Mentor?';
            message.innerHTML = `Anda akan menyetujui <strong>${mentorName}</strong> sebagai mentor. Mentor akan dapat login dan mulai mengajar.`;
            confirmBtn.textContent = 'Ya, Setujui';
            confirmBtn.className = 'btn-modal-confirm approve';
        } else {
            icon.innerHTML = `
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            `;
            icon.className = 'confirm-modal-icon reject';
            title.textContent = 'Tolak Pendaftaran?';
            message.innerHTML = `Anda akan menolak pendaftaran <strong>${mentorName}</strong>. Data dan transkrip akan dihapus permanen.`;
            confirmBtn.textContent = 'Ya, Tolak';
            confirmBtn.className = 'btn-modal-confirm reject';
        }
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageModal();
            closeConfirmModal();
        }
    });

    // Close on overlay click
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeConfirmModal();
        }
    });
    </script>
</body>
</html>
