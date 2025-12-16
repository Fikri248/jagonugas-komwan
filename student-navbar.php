<?php
// student-navbar.php

// Defensive: fallback kalau BASE_PATH ga ke-define
$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

$currentUser = null;
$userGems = 0;
$notifications = [];
$unreadCount = 0;

// Cek apakah $pdo valid sebelum query
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
?>
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
            <div class="dash-gem">
                <i class="bi bi-gem"></i>
                <span><?php echo number_format($userGems, 0, ',', '.'); ?></span>
            </div>

            <nav class="dash-nav-links">
                <a href="<?php echo $BASE; ?>/student-mentor.php">Mentor</a>
                <a href="<?php echo $BASE; ?>/student-membership.php">Membership</a>
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
            <div class="dash-user-dropdown" id="userDropdown">
                <div class="dash-user-trigger" id="userTrigger">
                    <div class="dash-avatar">
                        <?php if (!empty($currentUser['avatar'])): ?>
                            <img src="<?php echo $BASE . '/' . htmlspecialchars($currentUser['avatar']); ?>" alt="Avatar">
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
                    <a href="<?php echo $BASE; ?>/student-chat-history.php"><i class="bi bi-chat-left-text"></i> Histori Chat</a>
                    <a href="<?php echo $BASE; ?>/student-settings.php"><i class="bi bi-gear"></i> Pengaturan Akun</a>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo $BASE; ?>/logout.php" class="logout"><i class="bi bi-box-arrow-right"></i> Keluar</a>
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
