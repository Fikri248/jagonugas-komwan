<?php
require_once 'config.php';

$userId = $_SESSION['user_id'] ?? null;
$name = $_SESSION['name'] ?? 'Guest';

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
    // Filter: Pertanyaan Saya
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

// Get threads (+ avatar)
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

// Helper function
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
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="forum-page">
    <?php include 'partials/navbar.php'; ?>

    <div class="forum-container">
        <!-- Sidebar Categories -->
        <aside class="forum-sidebar">
            <div class="forum-sidebar-card">
                <h3>Kategori</h3>
                <ul class="forum-category-list">
                    <li>
                        <a href="<?php echo BASE_PATH; ?>/forum" class="<?php echo !$categorySlug ? 'active' : ''; ?>">
                            <i class="bi bi-grid"></i>
                            <span>Semua Kategori</span>
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="<?php echo BASE_PATH; ?>/forum?category=<?php echo $cat['slug']; ?>" 
                           class="<?php echo $categorySlug === $cat['slug'] ? 'active' : ''; ?>">
                            <i class="bi <?php echo $cat['icon']; ?>"></i>
                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($userId): ?>
            <a href="<?php echo BASE_PATH; ?>/forum/create" class="btn btn-primary btn-full">
                <i class="bi bi-plus-lg"></i> Buat Pertanyaan
            </a>
            <?php else: ?>
            <a href="<?php echo BASE_PATH; ?>/login?redirect=forum/create" class="btn btn-primary btn-full">
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
                            echo 'Daftar pertanyaan yang sudah lo ajukan';
                        } elseif ($currentCategory) {
                            echo htmlspecialchars($currentCategory['description']);
                        } else {
                            echo 'Tanya jawab seputar kuliah dan tugas';
                        }
                        ?>
                    </p>
                </div>
                <div class="forum-header-right">
                    <form class="forum-search" method="GET" action="<?php echo BASE_PATH; ?>/forum">
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

            <!-- Search Result Banner -->
            <?php if ($search): ?>
            <div class="search-result-banner">
                <div class="search-result-icon">
                    <i class="bi bi-search"></i>
                </div>
                <div class="search-result-content">
                    <h4>Hasil pencarian untuk "<span><?php echo htmlspecialchars($search); ?></span>"</h4>
                    <p>Ditemukan <strong><?php echo $totalThreads; ?></strong> pertanyaan yang cocok</p>
                </div>
                <a href="<?php echo BASE_PATH; ?>/forum<?php echo $categorySlug ? "?category=$categorySlug" : ''; ?><?php echo $filter === 'my' ? ($categorySlug ? '&' : '?') . 'filter=my' : ''; ?>" class="search-result-clear">
                    <i class="bi bi-x-lg"></i>
                    <span>Hapus</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Thread List -->
            <div class="forum-thread-list">
                <?php if (empty($threads)): ?>
                    <?php if ($search): ?>
                    <!-- Empty Search Result -->
                    <div class="search-empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-search"></i>
                        </div>
                        <h3>Tidak Ada Hasil</h3>
                        <p>Tidak ada pertanyaan yang cocok dengan "<strong><?php echo htmlspecialchars($search); ?></strong>". Coba kata kunci lain atau buat pertanyaan baru.</p>
                        <div class="empty-actions">
                            <a href="<?php echo BASE_PATH; ?>/forum<?php echo $categorySlug ? "?category=$categorySlug" : ''; ?><?php echo $filter === 'my' ? ($categorySlug ? '&' : '?') . 'filter=my' : ''; ?>" class="btn btn-outline">
                                <i class="bi bi-arrow-left"></i> Lihat Semua
                            </a>
                            <?php if ($userId): ?>
                            <a href="<?php echo BASE_PATH; ?>/forum/create" class="btn btn-primary">
                                <i class="bi bi-plus-lg"></i> Buat Pertanyaan
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif ($filter === 'my'): ?>
                    <!-- Empty My Questions -->
                    <div class="forum-empty">
                        <i class="bi bi-chat-square-text"></i>
                        <h3>Belum Ada Pertanyaan</h3>
                        <p>Lo belum pernah mengajukan pertanyaan. Yuk mulai tanya!</p>
                        <a href="<?php echo BASE_PATH; ?>/forum/create" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Buat Pertanyaan
                        </a>
                    </div>
                    <?php else: ?>
                    <!-- Normal Empty State -->
                    <div class="forum-empty">
                        <i class="bi bi-chat-square-text"></i>
                        <h3>Belum Ada Pertanyaan</h3>
                        <p><?php echo $filter === 'unanswered' ? 'Semua pertanyaan sudah dijawab!' : ($filter === 'solved' ? 'Belum ada pertanyaan yang terjawab.' : 'Jadi yang pertama bertanya!'); ?></p>
                        <?php if ($userId): ?>
                        <a href="<?php echo BASE_PATH; ?>/forum/create" class="btn btn-primary">Buat Pertanyaan</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
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
                                <a href="<?php echo BASE_PATH; ?>/forum/thread/<?php echo $thread['id']; ?>">
                                    <?php echo htmlspecialchars($thread['title']); ?>
                                </a>
                            </h3>
                            
                            <p class="forum-thread-excerpt">
                                <?php echo htmlspecialchars(substr(strip_tags($thread['content']), 0, 150)); ?>...
                            </p>
                            
                            <div class="forum-thread-footer">
                                <div class="forum-thread-author">
                                    <div class="forum-avatar sm">
                                        <?php if (!empty($thread['author_avatar'])): ?>
                                            <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($thread['author_avatar']); ?>" alt="Avatar">
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
    // Auto-hide success alert
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
