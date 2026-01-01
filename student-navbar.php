<?php
// student-navbar.php

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

$currentUser = null;
$userGems = 0;
$notifications = [];
$unreadCount = 0;

// Helper function untuk avatar URL
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar, $base = '') {
        if (empty($avatar)) return '';
        if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
        return $base . '/' . ltrim($avatar, '/');
    }
}

if ($pdo && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    $userGems = $currentUser['gems'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$_SESSION['user_id']]);
        $notifications = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadCount = (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        $notifications = [];
        $unreadCount = 0;
    }
}

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

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<style>
/* ===== DASHBOARD TOPBAR ===== */
.dash-topbar {
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid #e2e8f0;
}

.dash-topbar-inner {
    max-width: 1400px;
    margin: 0 auto;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    gap: 24px;
}

.dash-topbar-left {
    flex-shrink: 0;
}

.dash-logo {
    text-decoration: none;
}

.dash-logo span {
    font-size: 1.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Search */
.dash-search {
    flex: 1;
    max-width: 500px;
    position: relative;
}

.dash-search i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

.dash-search input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.9rem;
    transition: all 0.2s;
    background: #f8fafc;
}

.dash-search input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
}

/* Topbar Right */
.dash-topbar-right {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-left: auto;
}

.dash-gem {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
}

.dash-gem:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Nav Links */
.dash-nav-links {
    display: flex;
    gap: 4px;
}

.dash-nav-links a {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    color: #64748b;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    border-radius: 8px;
    transition: all 0.2s;
}

.dash-nav-links a:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.dash-nav-links a.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

/* ===== NOTIFICATION DROPDOWN ===== */
.dash-notif-dropdown {
    position: relative;
}

.dash-notif-trigger {
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

.dash-notif-trigger:hover {
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

.dash-notif-menu {
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

.dash-notif-dropdown.active .dash-notif-menu {
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
    color: #667eea;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 6px;
    transition: all 0.2s;
}

.notif-mark-all:hover {
    background: rgba(102, 126, 234, 0.1);
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
}

.notif-item:hover {
    background: #f8fafc;
}

.notif-item.unread {
    background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
}

.notif-item.unread:hover {
    background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%);
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
    background: linear-gradient(135deg, #667eea, #764ba2);
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
.dash-user-dropdown {
    position: relative;
}

.dash-user-trigger {
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

.dash-user-trigger:hover {
    background: #f1f5f9;
    border-color: #e2e8f0;
}

.dash-avatar {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.95rem;
    overflow: hidden;
    flex-shrink: 0;
}

.dash-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dash-user-info {
    display: flex;
    flex-direction: column;
    text-align: left;
}

.dash-user-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.9rem;
    line-height: 1.2;
}

.dash-user-role {
    font-size: 0.75rem;
    color: #64748b;
}

.dash-user-trigger i.bi-chevron-down {
    color: #94a3b8;
    font-size: 0.8rem;
    transition: transform 0.2s;
}

.dash-user-dropdown.active .dash-user-trigger i.bi-chevron-down {
    transform: rotate(180deg);
}

.dash-dropdown-menu {
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

.dash-user-dropdown.active .dash-dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dash-dropdown-menu a {
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

.dash-dropdown-menu a:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.dash-dropdown-menu a i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
    color: #64748b;
}

.dash-dropdown-menu a:hover i {
    color: #667eea;
}

.dash-dropdown-menu a.logout {
    color: #ef4444;
}

.dash-dropdown-menu a.logout:hover {
    background: #fef2f2;
    color: #dc2626;
}

.dash-dropdown-menu a.logout i {
    color: #ef4444;
}

.dropdown-divider {
    height: 1px;
    background: #f1f5f9;
    margin: 8px 0;
}

/* Auth Buttons */
.dash-auth-buttons {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 2px solid transparent;
    cursor: pointer;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.85rem;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-outline {
    border: 2px solid #e2e8f0;
    color: #475569;
    background: white;
}

.btn-outline:hover {
    border-color: #667eea;
    color: #667eea;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .dash-nav-links {
        display: none;
    }
    
    .dash-search {
        max-width: 300px;
    }
}

@media (max-width: 768px) {
    .dash-search {
        display: none;
    }
    
    .dash-topbar-inner {
        padding: 12px 16px;
        gap: 12px;
    }
    
    .dash-user-info {
        display: none;
    }
    
    .dash-user-trigger {
        padding: 6px;
    }
    
    .dash-notif-menu {
        width: 320px;
        right: -60px;
    }
    
    .dash-dropdown-menu {
        right: -10px;
    }
}

@media (max-width: 480px) {
    .dash-gem span {
        display: none;
    }
    
    .dash-gem {
        padding: 10px;
        border-radius: 10px;
    }
    
    .dash-notif-menu {
        width: 300px;
        right: -100px;
    }
}
</style>

<header class="dash-topbar">
    <div class="dash-topbar-inner">
        <div class="dash-topbar-left">
            <a href="<?php echo $BASE; ?>/student-dashboard.php" class="dash-logo">
                <span>JagoNugas</span>
            </a>
        </div>

        <form class="dash-search" action="<?php echo $BASE; ?>/student-forum.php" method="GET">
            <i class="bi bi-search"></i>
            <input type="text" name="search" placeholder="Cari jawaban untuk pertanyaan apa aja..." />
        </form>

        <div class="dash-topbar-right">
            <a href="<?php echo $BASE; ?>/student-gems-purchase.php" class="dash-gem" title="Top Up Gems">
                <i class="bi bi-gem"></i>
                <span><?php echo number_format($userGems, 0, ',', '.'); ?></span>
            </a>

            <nav class="dash-nav-links">
                <a href="<?php echo $BASE; ?>/student-mentor.php" 
                   class="<?php echo in_array($currentPage, ['student-mentor.php', 'mentor-booking.php']) ? 'active' : ''; ?>">
                    <i class="bi bi-person-video3"></i>
                    <span>Mentor</span>
                </a>
                <a href="<?php echo $BASE; ?>/student-sessions.php" 
                   class="<?php echo in_array($currentPage, ['student-sessions.php', 'session-rating.php']) ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-check"></i>
                    <span>Sesi Saya</span>
                </a>
                <a href="<?php echo $BASE; ?>/student-gems-purchase.php" 
                   class="<?php echo $currentPage === 'student-membership.php' ? 'active' : ''; ?>">
                    <i class="bi bi-star"></i>
                    <span>Membership</span>
                </a>
            </nav>

            <?php if ($currentUser): ?>
            <div class="dash-notif-dropdown" id="notifDropdown">
                <button type="button" class="dash-notif-trigger" id="notifTrigger">
                    <i class="bi bi-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
                    <?php endif; ?>
                </button>
                
                <div class="dash-notif-menu">
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
                            <div class="notif-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>" data-notif-id="<?php echo $notif['id']; ?>">
                                <div class="notif-icon" style="background: <?php echo htmlspecialchars($notif['color'] ?? '#667eea'); ?>20; color: <?php echo htmlspecialchars($notif['color'] ?? '#667eea'); ?>">
                                    <i class="bi bi-<?php echo htmlspecialchars($notif['icon'] ?? 'bell'); ?>"></i>
                                </div>
                                <div class="notif-content">
                                    <span class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></span>
                                    <p class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <span class="notif-time"><?php echo notif_time_ago($notif['created_at']); ?></span>
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
            <?php else: ?>
            <button type="button" class="dash-notif-trigger" onclick="window.location.href='<?php echo $BASE; ?>/login.php'">
                <i class="bi bi-bell"></i>
            </button>
            <?php endif; ?>

            <?php if ($currentUser): ?>
            <?php $avatarUrl = get_avatar_url($currentUser['avatar'] ?? '', $BASE); ?>
            <div class="dash-user-dropdown" id="userDropdown">
                <div class="dash-user-trigger" id="userTrigger">
                    <div class="dash-avatar">
                        <?php if ($avatarUrl): ?>
                            <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="dash-user-info">
                        <span class="dash-user-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                        <span class="dash-user-role">
                            <?php 
                            switch($currentUser['role']) {
                                case 'admin': echo 'Admin'; break;
                                case 'mentor': echo 'Mentor'; break;
                                default: echo 'Mahasiswa';
                            }
                            ?>
                        </span>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="dash-dropdown-menu">
                    <a href="<?php echo $BASE; ?>/student-dashboard.php">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                    <a href="<?php echo $BASE; ?>/student-sessions.php">
                        <i class="bi bi-calendar-check"></i> Sesi Mentor Saya
                    </a>
                    <a href="<?php echo $BASE; ?>/student-chat-history.php">
                        <i class="bi bi-chat-left-text"></i> Histori Chat
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo $BASE; ?>/student-gems-purchase.php">
                        <i class="bi bi-gem"></i> Top Up Gems
                    </a>
                    <a href="<?php echo $BASE; ?>/student-settings.php">
                        <i class="bi bi-gear"></i> Pengaturan Akun
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo $BASE; ?>/logout.php" class="logout">
                        <i class="bi bi-box-arrow-right"></i> Keluar
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="dash-auth-buttons">
                <a href="<?php echo $BASE; ?>/login.php" class="btn btn-outline btn-sm">Login</a>
                <a href="<?php echo $BASE; ?>/register.php" class="btn btn-primary btn-sm">Daftar</a>
            </div>
            <?php endif; ?>
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
        fetch('<?php echo $BASE; ?>/api-notif-read-all.php', {
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
    
    document.querySelectorAll('.notif-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            const id = this.dataset.notifId;
            if (id && this.classList.contains('unread')) {
                fetch('<?php echo $BASE; ?>/api-notif-read.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                }).then(() => {
                    this.classList.remove('unread');
                    this.querySelector('.notif-dot')?.remove();
                    const badge = document.querySelector('.notif-badge');
                    if (badge) {
                        let count = parseInt(badge.textContent) - 1;
                        count <= 0 ? badge.remove() : badge.textContent = count;
                    }
                });
            }
        });
    });
})();
</script>
