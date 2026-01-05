<?php
// student-forum.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

//  TRACK VISITOR
if (file_exists(__DIR__ . '/track-visitor.php')) {
    require_once __DIR__ . '/track-visitor.php';
}


$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$name = $_SESSION['name'] ?? 'Guest';

$pdo = null;
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function untuk avatar URL (handle Google avatar)
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar, $base = '') {
        if (empty($avatar)) return '';
        if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
        return $base . '/' . ltrim($avatar, '/');
    }
}

// Filter
$categorySlug = $_GET['category'] ?? null;
$filter = $_GET['filter'] ?? 'latest';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get categories
$categories = $pdo->query("SELECT * FROM forum_categories ORDER BY name")->fetchAll();

// Build query
$where = [];
$params = [];

if ($categorySlug) {
    $where[] = "fc.slug = ?";
    $params[] = $categorySlug;
}

if ($search) {
    $where[] = "(ft.title LIKE ? OR ft.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter === 'unanswered') {
    $where[] = "(SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id) = 0";
} elseif ($filter === 'solved') {
    $where[] = "ft.is_solved = 1";
} elseif ($filter === 'my' && $userId) {
    $where[] = "ft.user_id = ?";
    $params[] = $userId;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSql = "SELECT COUNT(*) FROM forum_threads ft 
             JOIN forum_categories fc ON ft.category_id = fc.id 
             $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalThreads = $countStmt->fetchColumn();
$totalPages = ceil($totalThreads / $perPage);

// Get threads
$sql = "SELECT 
            ft.id, 
            ft.title, 
            ft.content, 
            ft.gem_reward, 
            ft.views, 
            ft.is_solved, 
            ft.created_at as thread_time,
            u.name as author_name,
            u.avatar as author_avatar,
            fc.name as category_name, 
            fc.slug as category_slug, 
            fc.color as category_color,
            (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id) as reply_count
        FROM forum_threads ft 
        JOIN users u ON ft.user_id = u.id 
        JOIN forum_categories fc ON ft.category_id = fc.id 
        $whereClause
        ORDER BY ft.created_at DESC
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$threads = $stmt->fetchAll();

function time_elapsed($datetime) {
    $tz = new DateTimeZone('Asia/Jakarta');
    $now = new DateTime('now', $tz);
    $ago = new DateTime($datetime, $tz);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 7) return date('d M Y', strtotime($datetime));
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}

// Get current category info
$currentCategory = null;
if ($categorySlug) {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $categorySlug) {
            $currentCategory = $cat;
            break;
        }
    }
}

// Success message
$successMsg = '';
if (isset($_GET['deleted'])) {
    $successMsg = 'Pertanyaan berhasil dihapus!';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum Diskusi <?php echo $currentCategory ? '- ' . $currentCategory['name'] : ''; ?> - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f8fafc; min-height: 100vh; }
        
        /* ===== FORUM PAGE ===== */
        .forum-page { background: #f8fafc; min-height: 100vh; }
        
        .forum-container { max-width: 1400px; margin: 0 auto; padding: 32px 24px; display: grid; grid-template-columns: 280px 1fr; gap: 32px; }
        
        /* ===== BUTTONS ===== */
        .btn { padding: 12px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
        .btn-outline { border: 2px solid #e2e8f0; color: #475569; background: white; }
        .btn-outline:hover { border-color: #667eea; color: #667eea; }
        .btn-full { width: 100%; justify-content: center; }
        
        /* ===== ALERTS ===== */
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #16a34a; border: 1px solid #bbf7d0; }
        
        /* ===== FORUM SIDEBAR ===== */
        .forum-sidebar { display: flex; flex-direction: column; gap: 20px; }
        .forum-sidebar-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .forum-sidebar-card h3 { font-size: 1rem; color: #1e293b; margin-bottom: 16px; font-weight: 600; }
        
        .forum-category-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; }
        .forum-category-list a { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 10px; text-decoration: none; color: #475569; font-weight: 500; transition: all 0.2s; }
        .forum-category-list a:hover { background: #f1f5f9; color: #1e293b; }
        .forum-category-list a.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .forum-category-list a i { font-size: 1.1rem; width: 24px; text-align: center; }
        
        /* ===== FORUM MAIN ===== */
        .forum-main { min-width: 0; }
        
        .forum-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; gap: 20px; }
        .forum-header-left h1 { font-size: 1.75rem; color: #1e293b; margin-bottom: 4px; }
        .forum-header-left p { color: #64748b; font-size: 0.95rem; }
        
        .forum-search { position: relative; min-width: 300px; }
        .forum-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .forum-search input { width: 100%; padding: 12px 16px 12px 42px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.9rem; transition: all 0.2s; outline: none; }
        .forum-search input:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        
        /* ===== FORUM FILTERS ===== */
        .forum-filters { display: flex; gap: 8px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; }
        .forum-filter { display: flex; align-items: center; gap: 6px; padding: 10px 16px; border-radius: 50px; text-decoration: none; color: #64748b; font-size: 0.9rem; font-weight: 500; background: white; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .forum-filter:hover { border-color: #667eea; color: #667eea; }
        .forum-filter.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-color: transparent; }
        
        /* ===== FORUM THREAD LIST ===== */
        .forum-thread-list { display: flex; flex-direction: column; gap: 16px; }
        
        .forum-thread-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); display: flex; gap: 20px; transition: all 0.2s; border: 1px solid #e2e8f0; }
        .forum-thread-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .forum-thread-card.solved { border-left: 4px solid #10b981; }
        
        .forum-thread-votes { display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 70px; padding: 12px; background: #f8fafc; border-radius: 12px; text-align: center; }
        .forum-thread-votes .vote-count { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
        .forum-thread-votes .vote-label { font-size: 0.75rem; color: #64748b; }
        
        .forum-thread-content { flex: 1; min-width: 0; }
        
        .forum-thread-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
        .forum-thread-category { padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
        .forum-thread-solved { display: flex; align-items: center; gap: 4px; color: #10b981; font-size: 0.8rem; font-weight: 600; }
        .forum-thread-reward { display: flex; align-items: center; gap: 4px; color: #8b5cf6; font-size: 0.8rem; font-weight: 600; }
        
        .forum-thread-title { margin-bottom: 8px; }
        .forum-thread-title a { font-size: 1.1rem; font-weight: 600; color: #1e293b; text-decoration: none; transition: color 0.2s; }
        .forum-thread-title a:hover { color: #667eea; }
        
        .forum-thread-excerpt { color: #64748b; font-size: 0.9rem; line-height: 1.6; margin-bottom: 12px; }
        
        .forum-thread-footer { display: flex; justify-content: space-between; align-items: center; }
        .forum-thread-author { display: flex; align-items: center; gap: 10px; }
        
        /* Forum Avatar */
        .forum-avatar { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.7rem; flex-shrink: 0; overflow: hidden; }
        .forum-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .forum-thread-author span { font-size: 0.85rem; color: #475569; font-weight: 500; }
        
        .forum-thread-stats { display: flex; gap: 16px; font-size: 0.8rem; color: #94a3b8; }
        .forum-thread-stats span { display: flex; align-items: center; gap: 4px; }
        
        /* ===== FORUM EMPTY STATE ===== */
        .forum-empty { text-align: center; padding: 60px 20px; background: white; border-radius: 16px; border: 2px dashed #e2e8f0; }
        .forum-empty i { font-size: 4rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
        .forum-empty h3 { color: #1e293b; margin-bottom: 8px; font-size: 1.25rem; }
        .forum-empty p { color: #64748b; margin-bottom: 20px; }
        
        /* ===== FORUM PAGINATION ===== */
        .forum-pagination { display: flex; justify-content: center; align-items: center; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid #e2e8f0; }
        .pagination-btn { display: flex; align-items: center; gap: 6px; padding: 10px 20px; background: white; border: 1px solid #e2e8f0; border-radius: 10px; color: #475569; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .pagination-btn:hover { border-color: #667eea; color: #667eea; }
        .pagination-info { color: #64748b; font-size: 0.9rem; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .forum-container { grid-template-columns: 1fr; }
            .forum-sidebar { display: none; }
        }
        
        @media (max-width: 768px) {
            .forum-container { padding: 20px 16px; }
            .forum-header { flex-direction: column; }
            .forum-search { min-width: 100%; }
            .forum-thread-card { flex-direction: column; }
            .forum-thread-votes { flex-direction: row; justify-content: flex-start; gap: 8px; min-width: auto; }
        }
    </style>
</head>
<body class="forum-page">
    <?php include __DIR__ . '/student-navbar.php'; ?>

    <div class="forum-container">
        <!-- Sidebar Categories -->
        <aside class="forum-sidebar">
            <div class="forum-sidebar-card">
                <h3>Kategori</h3>
                <ul class="forum-category-list">
                    <li>
                        <a href="<?php echo $BASE; ?>/student-forum.php" class="<?php echo !$categorySlug ? 'active' : ''; ?>">
                            <i class="bi bi-grid"></i>
                            <span>Semua Kategori</span>
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="<?php echo $BASE; ?>/student-forum.php?category=<?php echo $cat['slug']; ?>" 
                           class="<?php echo $categorySlug === $cat['slug'] ? 'active' : ''; ?>">
                            <i class="bi <?php echo $cat['icon']; ?>"></i>
                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($userId): ?>
            <a href="<?php echo $BASE; ?>/student-forum-create.php" class="btn btn-primary btn-full">
                <i class="bi bi-plus-lg"></i> Buat Pertanyaan
            </a>
            <?php else: ?>
            <a href="<?php echo $BASE; ?>/login.php?redirect=student-forum-create.php" class="btn btn-primary btn-full">
                <i class="bi bi-plus-lg"></i> Buat Pertanyaan
            </a>
            <?php endif; ?>
        </aside>

        <!-- Main Content -->
        <main class="forum-main">
            <!-- Success Message -->
            <?php if ($successMsg): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?php echo $successMsg; ?>
            </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="forum-header">
                <div class="forum-header-left">
                    <h1>
                        <?php 
                        if ($filter === 'my') {
                            echo 'Pertanyaan Saya';
                        } elseif ($currentCategory) {
                            echo htmlspecialchars($currentCategory['name']);
                        } else {
                            echo 'Forum Diskusi';
                        }
                        ?>
                    </h1>
                    <p>
                        <?php 
                        if ($filter === 'my') {
                            echo 'Daftar pertanyaan yang sudah kamu ajukan';
                        } elseif ($currentCategory) {
                            echo htmlspecialchars($currentCategory['description']);
                        } else {
                            echo 'Tanya jawab seputar kuliah dan tugas';
                        }
                        ?>
                    </p>
                </div>
                <div class="forum-header-right">
                    <form class="forum-search" method="GET" action="<?php echo $BASE; ?>/student-forum.php">
                        <?php if ($categorySlug): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($categorySlug); ?>">
                        <?php endif; ?>
                        <?php if ($filter === 'my'): ?>
                        <input type="hidden" name="filter" value="my">
                        <?php endif; ?>
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" placeholder="Cari pertanyaan..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                </div>
            </div>

            <!-- Filters -->
            <div class="forum-filters">
                <a href="?<?php echo $categorySlug ? "category=$categorySlug&" : ''; ?>filter=latest<?php echo $search ? "&search=" . urlencode($search) : ''; ?>" 
                   class="forum-filter <?php echo $filter === 'latest' ? 'active' : ''; ?>">
                    <i class="bi bi-clock"></i> Terbaru
                </a>
                <a href="?<?php echo $categorySlug ? "category=$categorySlug&" : ''; ?>filter=unanswered<?php echo $search ? "&search=" . urlencode($search) : ''; ?>" 
                   class="forum-filter <?php echo $filter === 'unanswered' ? 'active' : ''; ?>">
                    <i class="bi bi-question-circle"></i> Belum Dijawab
                </a>
                <a href="?<?php echo $categorySlug ? "category=$categorySlug&" : ''; ?>filter=solved<?php echo $search ? "&search=" . urlencode($search) : ''; ?>" 
                   class="forum-filter <?php echo $filter === 'solved' ? 'active' : ''; ?>">
                    <i class="bi bi-check-circle"></i> Terjawab
                </a>
                <?php if ($userId): ?>
                <a href="?<?php echo $categorySlug ? "category=$categorySlug&" : ''; ?>filter=my<?php echo $search ? "&search=" . urlencode($search) : ''; ?>" 
                   class="forum-filter <?php echo $filter === 'my' ? 'active' : ''; ?>">
                    <i class="bi bi-person"></i> Pertanyaan Saya
                </a>
                <?php endif; ?>
            </div>

            <!-- Thread List -->
            <div class="forum-thread-list">
                <?php if (empty($threads)): ?>
                    <div class="forum-empty">
                        <i class="bi bi-chat-square-text"></i>
                        <h3>Belum Ada Pertanyaan</h3>
                        <p>Jadi yang pertama bertanya!</p>
                        <?php if ($userId): ?>
                        <a href="<?php echo $BASE; ?>/student-forum-create.php" class="btn btn-primary">Buat Pertanyaan</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($threads as $thread): ?>
                    <article class="forum-thread-card <?php echo $thread['is_solved'] ? 'solved' : ''; ?>">
                        <div class="forum-thread-votes">
                            <span class="vote-count"><?php echo $thread['reply_count']; ?></span>
                            <span class="vote-label">jawaban</span>
                        </div>
                        
                        <div class="forum-thread-content">
                            <div class="forum-thread-meta">
                                <span class="forum-thread-category" style="background: <?php echo $thread['category_color']; ?>20; color: <?php echo $thread['category_color']; ?>">
                                    <?php echo htmlspecialchars($thread['category_name']); ?>
                                </span>
                                <?php if ($thread['is_solved']): ?>
                                <span class="forum-thread-solved">
                                    <i class="bi bi-check-circle-fill"></i> Terjawab
                                </span>
                                <?php endif; ?>
                                <span class="forum-thread-reward">
                                    <i class="bi bi-gem"></i> +<?php echo $thread['gem_reward']; ?>
                                </span>
                            </div>
                            
                            <h3 class="forum-thread-title">
                                <a href="<?php echo $BASE; ?>/student-forum-thread.php?id=<?php echo $thread['id']; ?>">
                                    <?php echo htmlspecialchars($thread['title']); ?>
                                </a>
                            </h3>
                            
                            <p class="forum-thread-excerpt">
                                <?php echo htmlspecialchars(substr(strip_tags($thread['content']), 0, 150)); ?>...
                            </p>
                            
                            <div class="forum-thread-footer">
                                <div class="forum-thread-author">
                                    <div class="forum-avatar">
                                        <?php $avatarUrl = get_avatar_url($thread['author_avatar'], $BASE); ?>
                                        <?php if ($avatarUrl): ?>
                                            <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="" referrerpolicy="no-referrer">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($thread['author_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($thread['author_name']); ?></span>
                                </div>
                                <div class="forum-thread-stats">
                                    <span><i class="bi bi-eye"></i> <?php echo $thread['views']; ?></span>
                                    <span><i class="bi bi-clock"></i> <?php echo time_elapsed($thread['thread_time']); ?></span>
                                </div>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="forum-pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo $categorySlug ? "&category=$categorySlug" : ''; ?><?php echo $filter !== 'latest' ? "&filter=$filter" : ''; ?><?php echo $search ? "&search=" . urlencode($search) : ''; ?>" class="pagination-btn">
                    <i class="bi bi-chevron-left"></i> Prev
                </a>
                <?php endif; ?>
                
                <span class="pagination-info">Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?></span>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $categorySlug ? "&category=$categorySlug" : ''; ?><?php echo $filter !== 'latest' ? "&filter=$filter" : ''; ?><?php echo $search ? "&search=" . urlencode($search) : ''; ?>" class="pagination-btn">
                    Next <i class="bi bi-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            successAlert.style.transform = 'translateY(-10px)';
            setTimeout(() => successAlert.remove(), 300);
        }, 5000);
    }
    </script>
</body>
</html>
