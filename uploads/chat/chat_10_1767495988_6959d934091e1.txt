<?php
// mentor-navbar.php - Navbar untuk halaman mentor (v2.1 - Fixed 60% Share)

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

$navMentor = null;
$notifications = [];
$unreadCount = 0;
$totalEarnings = 0;

// Helper untuk avatar URL (Google atau lokal)
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar, $base = '') {
        if (empty($avatar)) return '';
        if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
        return $base . '/' . ltrim($avatar, '/');
    }
}

// Helper waktu relatif
if (!function_exists('notif_time_ago')) {
    function notif_time_ago($datetime) {
        $tz = new DateTimeZone('Asia/Jakarta');
        $now = new DateTime('now', $tz);
        $ago = new DateTime($datetime, $tz);
        $diff = $now->diff($ago);
        
        if ($diff->y > 0) return $diff->y . ' tahun lalu';
        if ($diff->m > 0) return $diff->m . ' bulan lalu';
        if ($diff->d > 0) return $diff->d . ' hari lalu';
        if ($diff->h > 0) return $diff->h . ' jam lalu';
        if ($diff->i > 0) return $diff->i . ' menit lalu';
        return 'Baru saja';
    }
}

// Helper format rupiah - TANPA SINGKATAN
if (!function_exists('format_rupiah_full')) {
    function format_rupiah_full($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

// Get mentor data
if ($pdo && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'mentor'");
    $stmt->execute([$_SESSION['user_id']]);
    $navMentor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($navMentor) {
        try {
            // Get notifications
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$_SESSION['user_id']]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$_SESSION['user_id']]);
            $unreadCount = (int) $stmt->fetchColumn();
            
            // =====================================================
            // HITUNG PENDAPATAN MENTOR = (GEMS SESI + GEMS FORUM) * 2 * 60%
            // =====================================================
            
            // 1. Total Gems dari SESI (completed & ongoing)
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(price), 0) 
                FROM sessions 
                WHERE mentor_id = ? AND status IN ('completed', 'ongoing')
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $gemsFromSessions = (int) $stmt->fetchColumn();
            
            // 2. Total Gems dari FORUM (best answer rewards)
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(ft.gem_reward), 0) 
                FROM forum_replies fr
                JOIN forum_threads ft ON fr.thread_id = ft.id
                WHERE fr.user_id = ? AND fr.is_best_answer = 1
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $gemsFromForum = (int) $stmt->fetchColumn();
            
            // 3. Total Gems = Sesi + Forum
            $totalGems = $gemsFromSessions + $gemsFromForum;
            
            // 4. Konversi ke Rupiah: 
            //    - 1 gem = 2 rupiah
            //    - Mentor dapat 60%
            //    - Formula: totalGems * 2 * 0.6
            $totalEarnings = $totalGems * 2 * 0.6;
            
        } catch (Exception $e) {
            $notifications = [];
            $unreadCount = 0;
            $totalEarnings = 0;
        }
    }
}

$navName = $navMentor['name'] ?? ($_SESSION['name'] ?? 'Mentor');
$navAvatar = $navMentor['avatar'] ?? null;
$navInitial = mb_strtoupper(mb_substr($navName, 0, 1, 'UTF-8'), 'UTF-8');
$avatarUrl = get_avatar_url($navAvatar, $BASE);
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<style>
/* ===== MENTOR TOPBAR ===== */
.mentor-topbar {
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid #e2e8f0;
}

.mentor-topbar-inner {
    max-width: 1400px;
    margin: 0 auto;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    gap: 24px;
}

.mentor-topbar-left {
    display: flex;
    align-items: center;
    gap: 32px;
}

.mentor-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.mentor-logo-icon {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 800;
    font-size: 1.1rem;
}

.mentor-logo-text {
    font-size: 1.4rem;
    font-weight: 800;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.mentor-badge {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Nav Links */
.mentor-nav-links {
    display: flex;
    gap: 4px;
}

.mentor-nav-links a {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    color: #64748b;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    border-radius: 10px;
    transition: all 0.2s;
}

.mentor-nav-links a:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.mentor-nav-links a.active {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

/* Topbar Right */
.mentor-topbar-right {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-left: auto;
}

/* ===== EARNINGS DISPLAY ===== */
.mentor-earnings {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border-radius: 12px;
    border: 1px solid #a7f3d0;
}

.mentor-earnings-icon {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.mentor-earnings-info {
    display: flex;
    flex-direction: column;
}

.mentor-earnings-label {
    font-size: 0.7rem;
    color: #059669;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mentor-earnings-amount {
    font-size: 0.95rem;
    font-weight: 700;
    color: #065f46;
}

/* ===== NOTIFICATION DROPDOWN ===== */
.mentor-notif-dropdown {
    position: relative;
}

.mentor-notif-trigger {
    position: relative;
    background: #f1f5f9;
    border: none;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}

.mentor-notif-trigger:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.notif-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    border: 2px solid white;
}

.mentor-notif-menu {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: 380px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
    overflow: hidden;
}

.mentor-notif-dropdown.active .mentor-notif-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    background: #f8fafc;
}

.notif-header h4 {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.notif-mark-all {
    background: none;
    border: none;
    color: #10b981;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 6px;
    transition: all 0.2s;
}

.notif-mark-all:hover {
    background: rgba(16, 185, 129, 0.1);
}

.notif-list {
    max-height: 400px;
    overflow-y: auto;
}

.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    border-bottom: 1px solid #f8fafc;
    text-decoration: none;
    color: inherit;
}

.notif-item:hover {
    background: #f8fafc;
}

.notif-item.unread {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
}

.notif-item.unread:hover {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
}

.notif-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.notif-content {
    flex: 1;
    min-width: 0;
}

.notif-title {
    display: block;
    font-weight: 600;
    color: #1e293b;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.notif-message {
    color: #64748b;
    font-size: 0.85rem;
    line-height: 1.5;
    margin: 0 0 6px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.notif-time {
    font-size: 0.75rem;
    color: #94a3b8;
}

.notif-dot {
    width: 10px;
    height: 10px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 6px;
}

.notif-empty {
    padding: 48px 20px;
    text-align: center;
}

.notif-empty i {
    font-size: 3rem;
    color: #cbd5e1;
    margin-bottom: 12px;
    display: block;
}

.notif-empty p {
    color: #94a3b8;
    font-size: 0.9rem;
    margin: 0;
}

/* ===== USER DROPDOWN ===== */
.mentor-user-dropdown {
    position: relative;
}

.mentor-user-trigger {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 6px 12px 6px 6px;
    border-radius: 12px;
    transition: all 0.2s;
    background: #f8fafc;
    border: 2px solid transparent;
}

.mentor-user-trigger:hover {
    background: #f1f5f9;
    border-color: #e2e8f0;
}

.mentor-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    overflow: hidden;
    flex-shrink: 0;
}

.mentor-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mentor-user-info {
    display: flex;
    flex-direction: column;
    text-align: left;
}

.mentor-user-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.9rem;
    line-height: 1.2;
}

.mentor-user-role {
    font-size: 0.75rem;
    color: #10b981;
    font-weight: 500;
}

.mentor-user-trigger i.bi-chevron-down {
    color: #94a3b8;
    font-size: 0.8rem;
    transition: transform 0.2s;
}

.mentor-user-dropdown.active .mentor-user-trigger i.bi-chevron-down {
    transform: rotate(180deg);
}

.mentor-dropdown-menu {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    min-width: 220px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    padding: 8px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
}

.mentor-user-dropdown.active .mentor-dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.mentor-dropdown-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    color: #475569;
    text-decoration: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s;
}

.mentor-dropdown-menu a:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.mentor-dropdown-menu a i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
    color: #64748b;
}

.mentor-dropdown-menu a:hover i {
    color: #10b981;
}

.mentor-dropdown-menu a.logout {
    color: #ef4444;
}

.mentor-dropdown-menu a.logout:hover {
    background: #fef2f2;
    color: #dc2626;
}

.mentor-dropdown-menu a.logout i {
    color: #ef4444;
}

.dropdown-divider {
    height: 1px;
    background: #f1f5f9;
    margin: 8px 0;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .mentor-nav-links a span {
        display: none;
    }
    
    .mentor-nav-links a {
        padding: 10px 12px;
    }
    
    .mentor-earnings-label {
        display: none;
    }
    
    .mentor-earnings {
        padding: 8px 12px;
    }
}

@media (max-width: 768px) {
    .mentor-topbar-inner {
        padding: 12px 16px;
        gap: 12px;
    }
    
    .mentor-user-info {
        display: none;
    }
    
    .mentor-user-trigger {
        padding: 6px;
    }
    
    .mentor-notif-menu {
        width: 320px;
        right: -60px;
    }
    
    .mentor-dropdown-menu {
        right: -10px;
    }
    
    .mentor-logo-text {
        display: none;
    }
    
    .mentor-earnings-info {
        display: none;
    }
    
    .mentor-earnings {
        padding: 8px;
        border-radius: 10px;
    }
    
    .mentor-earnings-icon {
        width: 28px;
        height: 28px;
        font-size: 0.9rem;
    }
}
</style>

<header class="mentor-topbar">
    <div class="mentor-topbar-inner">
        <div class="mentor-topbar-left">
            <a href="<?= $BASE ?>/mentor-dashboard.php" class="mentor-logo">
                <div class="mentor-logo-icon">M</div>
                <span class="mentor-logo-text">JagoNugas</span>
                <span class="mentor-badge">Mentor</span>
            </a>
            
            <nav class="mentor-nav-links">
                <a href="<?= $BASE ?>/mentor-dashboard.php" 
                   class="<?= $currentPage === 'mentor-dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= $BASE ?>/mentor-sessions.php"
                   class="<?= $currentPage === 'mentor-sessions.php' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-check"></i>
                    <span>Booking</span>
                </a>
                <a href="<?= $BASE ?>/mentor-chat.php"
                   class="<?= $currentPage === 'mentor-chat.php' ? 'active' : '' ?>">
                    <i class="bi bi-chat-dots"></i>
                    <span>Chat</span>
                </a>
            </nav>
        </div>

        <div class="mentor-topbar-right">
            <!-- Earnings Display -->
            <div class="mentor-earnings" title="Total pendapatan: <?= number_format($totalGems ?? 0, 0, ',', '.') ?> gems × Rp2 × 60%">
                <div class="mentor-earnings-icon">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="mentor-earnings-info">
                    <span class="mentor-earnings-label">Pendapatan</span>
                    <span class="mentor-earnings-amount"><?= format_rupiah_full($totalEarnings) ?></span>
                </div>
            </div>
            
            <!-- Notification -->
            <div class="mentor-notif-dropdown" id="notifDropdown">
                <button type="button" class="mentor-notif-trigger" id="notifTrigger">
                    <i class="bi bi-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                    <?php endif; ?>
                </button>
                
                <div class="mentor-notif-menu">
                    <div class="notif-header">
                        <h4>Notifikasi</h4>
                        <?php if ($unreadCount > 0): ?>
                        <button type="button" class="notif-mark-all" id="markAllRead">Tandai dibaca</button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notif-list">
                        <?php if (empty($notifications)): ?>
                        <div class="notif-empty">
                            <i class="bi bi-bell-slash"></i>
                            <p>Belum ada notifikasi</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <div class="notif-item <?= !$notif['is_read'] ? 'unread' : '' ?>" data-notif-id="<?= $notif['id'] ?>">
                                <div class="notif-icon">
                                    <i class="bi bi-<?= htmlspecialchars($notif['icon'] ?? 'bell') ?>"></i>
                                </div>
                                <div class="notif-content">
                                    <span class="notif-title"><?= htmlspecialchars($notif['title']) ?></span>
                                    <p class="notif-message"><?= htmlspecialchars($notif['message']) ?></p>
                                    <span class="notif-time"><?= notif_time_ago($notif['created_at']) ?></span>
                                </div>
                                <?php if (!$notif['is_read']): ?>
                                <span class="notif-dot"></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- User Dropdown -->
            <div class="mentor-user-dropdown" id="userDropdown">
                <div class="mentor-user-trigger" id="userTrigger">
                    <div class="mentor-avatar">
                        <?php if ($avatarUrl): ?>
                            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <?= htmlspecialchars($navInitial) ?>
                        <?php endif; ?>
                    </div>
                    <div class="mentor-user-info">
                        <span class="mentor-user-name"><?= htmlspecialchars($navName) ?></span>
                        <span class="mentor-user-role">Mentor</span>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                
                <div class="mentor-dropdown-menu">
                    <a href="<?= $BASE ?>/mentor-dashboard.php">
                        <i class="bi bi-grid-1x2"></i> Dashboard
                    </a>
                    <a href="<?= $BASE ?>/mentor-sessions.php">
                        <i class="bi bi-calendar-check"></i> Booking Saya
                    </a>
                    <a href="<?= $BASE ?>/mentor-chat.php">
                        <i class="bi bi-chat-dots"></i> Chat Aktif
                    </a>
                    <a href="<?= $BASE ?>/mentor-chat-history.php">
                        <i class="bi bi-clock-history"></i> Histori Chat
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?= $BASE ?>/mentor-settings.php">
                        <i class="bi bi-gear"></i> Pengaturan
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?= $BASE ?>/logout.php" class="logout">
                        <i class="bi bi-box-arrow-right"></i> Keluar
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
(function() {
    const notifTrigger = document.getElementById('notifTrigger');
    const notifDropdown = document.getElementById('notifDropdown');
    const userTrigger = document.getElementById('userTrigger');
    const userDropdown = document.getElementById('userDropdown');
    
    notifTrigger?.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        notifDropdown.classList.toggle('active');
        userDropdown?.classList.remove('active');
    });
    
    userTrigger?.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        userDropdown.classList.toggle('active');
        notifDropdown?.classList.remove('active');
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#notifDropdown')) notifDropdown?.classList.remove('active');
        if (!e.target.closest('#userDropdown')) userDropdown?.classList.remove('active');
    });
    
    document.getElementById('markAllRead')?.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fetch('<?= $BASE ?>/api-notif-read-all.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        }).then(() => {
            document.querySelectorAll('.notif-item.unread').forEach(item => {
                item.classList.remove('unread');
                item.querySelector('.notif-dot')?.remove();
            });
            document.querySelector('.notif-badge')?.remove();
            this.style.display = 'none';
        });
    });
})();
</script>
