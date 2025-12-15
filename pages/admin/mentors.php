<?php
// pages/admin/mentors.php
require_once __DIR__ . '/../../config.php';

// Cek login & role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_PATH . "/login");
    exit;
}

$name = $_SESSION['name'] ?? 'Admin';
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
            $stmt = $db->prepare("SELECT transkrip_path FROM users WHERE id = :id");
            $stmt->bindParam(':id', $mentorId);
            $stmt->execute();
            $mentor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mentor && $mentor['transkrip_path']) {
                $filePath = __DIR__ . '/../../' . $mentor['transkrip_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="admin-dashboard-page">
    <!-- Navbar Admin -->
    <header class="admin-navbar">
        <div class="admin-navbar-inner">
            <div class="admin-navbar-left">
                <a href="<?php echo BASE_PATH; ?>/admin/dashboard" class="admin-logo">
                    <div class="admin-logo-mark">A</div>
                    <span class="admin-logo-text">JagoNugas</span>
                    <span class="admin-badge">Admin</span>
                </a>
                <nav class="admin-nav-links">
                    <a href="<?php echo BASE_PATH; ?>/admin/dashboard">Dashboard</a>
                    <a href="<?php echo BASE_PATH; ?>/admin/users">Users</a>
                    <a href="<?php echo BASE_PATH; ?>/admin/mentors" class="active">Mentors</a>
                    <a href="<?php echo BASE_PATH; ?>/admin/transactions">Transaksi</a>
                    <a href="<?php echo BASE_PATH; ?>/admin/settings">Settings</a>
                </nav>
            </div>
            
            <div class="admin-navbar-right">
                <div class="admin-user-menu">
                    <div class="admin-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                    <div class="admin-user-info">
                        <span class="admin-user-name"><?php echo htmlspecialchars($name); ?></span>
                        <span class="admin-user-role">Administrator</span>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                    <div class="admin-dropdown">
                        <a href="<?php echo BASE_PATH; ?>/admin/profile"><i class="bi bi-person"></i> Profil</a>
                        <a href="<?php echo BASE_PATH; ?>/admin/settings"><i class="bi bi-gear"></i> Pengaturan</a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo BASE_PATH; ?>/logout" class="logout"><i class="bi bi-box-arrow-right"></i> Keluar</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="admin-main">
        <!-- Page Header -->
        <div class="admin-page-header">
            <div class="page-header-content">
                <h1><i class="bi bi-mortarboard-fill"></i> Kelola Mentor</h1>
                <p>Review dan verifikasi pendaftaran mentor baru</p>
            </div>
            
            <!-- Filter Tabs -->
            <div class="admin-filter-tabs">
                <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    <i class="bi bi-hourglass-split"></i>
                    Menunggu Verifikasi
                    <?php if ($pendingCount > 0): ?>
                        <span class="tab-badge"><?php echo $pendingCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?filter=verified" class="filter-tab <?php echo $filter === 'verified' ? 'active' : ''; ?>">
                    <i class="bi bi-check-circle-fill"></i>
                    Terverifikasi
                    <span class="tab-count">(<?php echo $verifiedCount; ?>)</span>
                </a>
                <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="bi bi-people-fill"></i>
                    Semua
                </a>
            </div>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
            <div class="admin-alert <?php echo $messageType; ?>">
                <?php if ($messageType === 'success'): ?>
                    <i class="bi bi-check-circle-fill"></i>
                <?php else: ?>
                    <i class="bi bi-x-circle-fill"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Mentor List -->
        <?php if (empty($mentors)): ?>
            <div class="admin-empty-state">
                <div class="empty-icon">
                    <i class="bi bi-inbox"></i>
                </div>
                <h3>Tidak ada data</h3>
                <p>Belum ada mentor <?php echo $filter === 'pending' ? 'yang menunggu verifikasi' : ''; ?></p>
            </div>
        <?php else: ?>
            <div class="admin-mentor-list">
                <?php foreach ($mentors as $mentor): ?>
                    <div class="admin-mentor-card <?php echo $mentor['is_verified'] ? 'verified' : 'pending'; ?>">
                        <div class="mentor-card-header">
                            <div class="mentor-avatar-lg">
                                <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                            </div>
                            <div class="mentor-info">
                                <h3><?php echo htmlspecialchars($mentor['name']); ?></h3>
                                <p class="mentor-email"><?php echo htmlspecialchars($mentor['email']); ?></p>
                                <div class="mentor-meta">
                                    <span class="meta-item">
                                        <i class="bi bi-mortarboard"></i>
                                        <?php echo htmlspecialchars($mentor['program_studi']); ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="bi bi-book"></i>
                                        Semester <?php echo $mentor['semester']; ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="bi bi-calendar3"></i>
                                        <?php echo date('d M Y', strtotime($mentor['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mentor-status">
                                <?php if ($mentor['is_verified']): ?>
                                    <span class="status-badge verified"><i class="bi bi-check-circle-fill"></i> Terverifikasi</span>
                                <?php else: ?>
                                    <span class="status-badge pending"><i class="bi bi-hourglass-split"></i> Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Expertise -->
                        <?php if ($mentor['expertise']): ?>
                            <div class="mentor-expertise">
                                <strong><i class="bi bi-lightbulb"></i> Keahlian:</strong>
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
                                <strong><i class="bi bi-person-lines-fill"></i> Bio:</strong>
                                <p><?php echo htmlspecialchars($mentor['bio']); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Transkrip Section -->
                        <?php if ($mentor['transkrip_path']): ?>
                            <div class="transkrip-section">
                                <strong><i class="bi bi-file-earmark-text"></i> Transkrip Nilai:</strong>
                                <div class="transkrip-actions">
                                    <?php 
                                    $ext = pathinfo($mentor['transkrip_path'], PATHINFO_EXTENSION);
                                    $isImage = in_array(strtolower($ext), ['jpg', 'jpeg', 'png']);
                                    ?>
                                    
                                    <a href="<?php echo BASE_PATH . '/' . $mentor['transkrip_path']; ?>" 
                                       target="_blank" class="btn-transkrip view">
                                        <i class="bi bi-eye"></i>
                                        Lihat
                                    </a>
                                    
                                    <a href="<?php echo BASE_PATH . '/' . $mentor['transkrip_path']; ?>" 
                                       download class="btn-transkrip download">
                                        <i class="bi bi-download"></i>
                                        Download
                                    </a>
                                </div>

                                <?php if ($isImage): ?>
                                    <div class="transkrip-preview">
                                        <img src="<?php echo BASE_PATH . '/' . $mentor['transkrip_path']; ?>" 
                                             alt="Transkrip <?php echo htmlspecialchars($mentor['name']); ?>"
                                             onclick="openImageModal(this.src)">
                                        <p class="preview-hint"><i class="bi bi-zoom-in"></i> Klik gambar untuk memperbesar</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <?php if (!$mentor['is_verified']): ?>
                            <div class="mentor-card-actions">
                                <button type="button" class="btn-action approve" 
                                        onclick="showConfirmModal('approve', <?php echo $mentor['id']; ?>, '<?php echo htmlspecialchars($mentor['name'], ENT_QUOTES); ?>')">
                                    <i class="bi bi-check-lg"></i>
                                    Setujui Mentor
                                </button>
                                
                                <button type="button" class="btn-action reject"
                                        onclick="showConfirmModal('reject', <?php echo $mentor['id']; ?>, '<?php echo htmlspecialchars($mentor['name'], ENT_QUOTES); ?>')">
                                    <i class="bi bi-x-lg"></i>
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
            <span class="modal-close"><i class="bi bi-x-lg"></i></span>
            <img id="modalImage" src="" alt="Transkrip Preview">
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="confirm-modal-overlay" id="confirmModal">
        <div class="confirm-modal">
            <div class="confirm-modal-icon" id="confirmIcon"></div>
            <h3 class="confirm-modal-title" id="confirmTitle">Konfirmasi</h3>
            <p class="confirm-modal-message" id="confirmMessage">Apakah Anda yakin?</p>
            
            <form method="POST" id="confirmForm">
                <input type="hidden" name="mentor_id" id="confirmMentorId">
                <input type="hidden" name="action" id="confirmAction">
                
                <div class="confirm-modal-actions">
                    <button type="button" class="btn-modal cancel" onclick="closeConfirmModal()">
                        Batal
                    </button>
                    <button type="submit" class="btn-modal confirm" id="confirmButton">
                        Konfirmasi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openImageModal(src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        document.getElementById('imageModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function showConfirmModal(action, mentorId, mentorName) {
        const modal = document.getElementById('confirmModal');
        const icon = document.getElementById('confirmIcon');
        const title = document.getElementById('confirmTitle');
        const message = document.getElementById('confirmMessage');
        const confirmBtn = document.getElementById('confirmButton');
        
        document.getElementById('confirmMentorId').value = mentorId;
        document.getElementById('confirmAction').value = action;
        
        if (action === 'approve') {
            icon.innerHTML = '<i class="bi bi-check-circle" style="color: #10b981; font-size: 3rem;"></i>';
            title.textContent = 'Setujui Mentor?';
            message.innerHTML = `Anda akan menyetujui <strong>${mentorName}</strong> sebagai mentor.`;
            confirmBtn.textContent = 'Ya, Setujui';
            confirmBtn.className = 'btn-modal confirm approve';
        } else {
            icon.innerHTML = '<i class="bi bi-x-circle" style="color: #ef4444; font-size: 3rem;"></i>';
            title.textContent = 'Tolak Pendaftaran?';
            message.innerHTML = `Anda akan menolak pendaftaran <strong>${mentorName}</strong>. Data akan dihapus permanen.`;
            confirmBtn.textContent = 'Ya, Tolak';
            confirmBtn.className = 'btn-modal confirm reject';
        }
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageModal();
            closeConfirmModal();
        }
    });

    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeConfirmModal();
    });
    </script>
</body>
</html>
