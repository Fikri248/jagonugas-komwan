<?php
require_once 'config.php';
require_once 'includes/NotificationHelper.php';

$threadId = (int)($routeParams['id'] ?? $_GET['id'] ?? 0);
$userId = $_SESSION['user_id'] ?? null;
$name = $_SESSION['name'] ?? 'Guest';

if (!$threadId) {
    header("Location: " . BASE_PATH . "/forum");
    exit;
}

// Get thread with details (+ avatar)
$stmt = $pdo->prepare("
    SELECT ft.*, u.name as author_name, u.id as author_id, u.avatar as author_avatar,
           fc.name as category_name, fc.slug as category_slug, fc.color as category_color
    FROM forum_threads ft 
    JOIN users u ON ft.user_id = u.id 
    JOIN forum_categories fc ON ft.category_id = fc.id 
    WHERE ft.id = ?
");
$stmt->execute([$threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    header("Location: " . BASE_PATH . "/forum");
    exit;
}

// Handle DELETE thread
if (isset($_POST['delete_thread']) && $userId == $thread['author_id']) {
    $pdo->beginTransaction();
    try {
        // Ambil daftar user yang sudah menjawab (untuk notifikasi)
        $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM forum_replies WHERE thread_id = ? AND user_id != ?");
        $stmt->execute([$threadId, $userId]);
        $repliers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete attachments files
        $stmt = $pdo->prepare("SELECT file_path FROM forum_attachments WHERE thread_id = ?");
        $stmt->execute([$threadId]);
        $files = $stmt->fetchAll();
        foreach ($files as $file) {
            $filePath = __DIR__ . '/../' . $file['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Delete related data
        $pdo->prepare("DELETE FROM forum_attachments WHERE thread_id = ?")->execute([$threadId]);
        $pdo->prepare("DELETE FROM forum_upvotes WHERE reply_id IN (SELECT id FROM forum_replies WHERE thread_id = ?)")->execute([$threadId]);
        $pdo->prepare("DELETE FROM forum_replies WHERE thread_id = ?")->execute([$threadId]);
        
        // Refund gems to author (jika belum solved)
        $gemsRefunded = 0;
        if (!$thread['is_solved']) {
            $pdo->prepare("UPDATE users SET gems = gems + ? WHERE id = ?")->execute([$thread['gem_reward'], $userId]);
            $gemsRefunded = $thread['gem_reward'];
        }
        
        // Delete thread
        $pdo->prepare("DELETE FROM forum_threads WHERE id = ?")->execute([$threadId]);
        
        $pdo->commit();
        
        // Kirim notifikasi
        $notif = new NotificationHelper($pdo);
        
        // Notifikasi ke pemilik thread (konfirmasi hapus)
        if ($gemsRefunded > 0) {
            $notif->create(
                $userId,
                'thread_deleted',
                'Pertanyaan "' . substr($thread['title'], 0, 50) . '..." berhasil dihapus. ' . $gemsRefunded . ' gem telah dikembalikan ke akun kamu.',
                null,
                null
            );
        } else {
            $notif->create(
                $userId,
                'thread_deleted',
                'Pertanyaan "' . substr($thread['title'], 0, 50) . '..." berhasil dihapus.',
                null,
                null
            );
        }
        
        // Notifikasi ke semua yang sudah menjawab
        foreach ($repliers as $replierId) {
            $notif->create(
                $replierId,
                'thread_deleted',
                'Pertanyaan "' . substr($thread['title'], 0, 50) . '..." yang kamu jawab telah dihapus oleh penanya.',
                null,
                null
            );
        }
        
        header("Location: " . BASE_PATH . "/forum?deleted=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $deleteError = "Gagal menghapus thread";
    }
}

// Increment views - HANYA SEKALI per session per thread
$viewKey = 'viewed_thread_' . $threadId;
if (!isset($_SESSION[$viewKey])) {
    $pdo->prepare("UPDATE forum_threads SET views = views + 1 WHERE id = ?")->execute([$threadId]);
    $_SESSION[$viewKey] = true;
    $thread['views']++;
}

// Get attachments
$stmt = $pdo->prepare("SELECT * FROM forum_attachments WHERE thread_id = ?");
$stmt->execute([$threadId]);
$attachments = $stmt->fetchAll();

// Get replies (+ avatar)
$stmt = $pdo->prepare("
    SELECT fr.*, u.name as author_name, u.id as author_id, u.avatar as author_avatar,
           (SELECT COUNT(*) FROM forum_upvotes WHERE reply_id = fr.id) as upvote_count,
           " . ($userId ? "(SELECT COUNT(*) FROM forum_upvotes WHERE reply_id = fr.id AND user_id = ?) as user_upvoted" : "0 as user_upvoted") . "
    FROM forum_replies fr
    JOIN users u ON fr.user_id = u.id
    WHERE fr.thread_id = ?
    ORDER BY fr.is_best_answer DESC, fr.upvotes DESC, fr.created_at ASC
");
$stmt->execute($userId ? [$userId, $threadId] : [$threadId]);
$replies = $stmt->fetchAll();

// Handle new reply
$replyError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_content'])) {
    if (!$userId) {
        header("Location: " . BASE_PATH . "/login?redirect=forum/thread/$threadId");
        exit;
    }
    
    $replyContent = trim($_POST['reply_content']);
    
    if (empty($replyContent)) {
        $replyError = "Jawaban tidak boleh kosong";
    } elseif (strlen($replyContent) < 10) {
        $replyError = "Jawaban minimal 10 karakter";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO forum_replies (thread_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$threadId, $userId, $replyContent]);
            
            // Kirim notifikasi ke pemilik thread (jika bukan diri sendiri)
            if ($thread['author_id'] != $userId) {
                $notif = new NotificationHelper($pdo);
                $notif->newReplyToThread(
                    $thread['author_id'],
                    $name,
                    $threadId,
                    $thread['title']
                );
            }
            
            header("Location: " . BASE_PATH . "/forum/thread/$threadId?success=1#replies");
            exit;
        } catch (Exception $e) {
            $replyError = "Gagal mengirim jawaban. Silakan coba lagi.";
        }
    }
}

// Handle mark best answer
if (isset($_GET['best']) && $userId == $thread['author_id'] && !$thread['is_solved']) {
    $replyId = (int)$_GET['best'];
    
    $stmt = $pdo->prepare("SELECT user_id FROM forum_replies WHERE id = ? AND thread_id = ?");
    $stmt->execute([$replyId, $threadId]);
    $reply = $stmt->fetch();
    
    if ($reply && $reply['user_id'] != $userId) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE forum_threads SET is_solved = 1, best_answer_id = ? WHERE id = ?");
            $stmt->execute([$replyId, $threadId]);
            
            $stmt = $pdo->prepare("UPDATE forum_replies SET is_best_answer = 1 WHERE id = ?");
            $stmt->execute([$replyId]);
            
            $stmt = $pdo->prepare("UPDATE users SET gems = gems + ? WHERE id = ?");
            $stmt->execute([$thread['gem_reward'], $reply['user_id']]);
            
            // Kirim notifikasi ke penjawab bahwa jawabannya dipilih sebagai terbaik
            $notif = new NotificationHelper($pdo);
            $notif->bestAnswer($reply['user_id'], $thread['gem_reward'], $threadId);
            
            $pdo->commit();
            
            header("Location: " . BASE_PATH . "/forum/thread/$threadId?best_selected=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// Success messages
$successMsg = '';
if (isset($_GET['success'])) {
    $successMsg = 'Jawaban berhasil dikirim!';
} elseif (isset($_GET['best_selected'])) {
    $successMsg = 'Jawaban terbaik berhasil dipilih! Gem sudah diberikan ke penjawab.';
} elseif (isset($_GET['updated'])) {
    $successMsg = 'Pertanyaan berhasil diperbarui!';
}

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($thread['title']); ?> - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="forum-page">
    <?php include 'partials/navbar.php'; ?>

    <div class="thread-container">
        <!-- Breadcrumb -->
        <nav class="thread-breadcrumb">
            <a href="<?php echo BASE_PATH; ?>/forum">Forum</a>
            <i class="bi bi-chevron-right"></i>
            <a href="<?php echo BASE_PATH; ?>/forum?category=<?php echo $thread['category_slug']; ?>">
                <?php echo htmlspecialchars($thread['category_name']); ?>
            </a>
            <i class="bi bi-chevron-right"></i>
            <span>Pertanyaan</span>
        </nav>

        <!-- Success Message -->
        <?php if ($successMsg): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <?php echo $successMsg; ?>
        </div>
        <?php endif; ?>

        <!-- Delete Error -->
        <?php if (isset($deleteError)): ?>
        <div class="alert alert-error">
            <i class="bi bi-exclamation-circle"></i> <?php echo $deleteError; ?>
        </div>
        <?php endif; ?>

        <!-- Thread Question -->
        <article class="thread-question">
            <!-- Header -->
            <div class="thread-question-header">
                <div class="thread-meta">
                    <span class="thread-category" style="background: <?php echo $thread['category_color']; ?>20; color: <?php echo $thread['category_color']; ?>">
                        <?php echo htmlspecialchars($thread['category_name']); ?>
                    </span>
                    <?php if ($thread['is_solved']): ?>
                    <span class="thread-solved">
                        <i class="bi bi-check-circle-fill"></i> Terjawab
                    </span>
                    <?php else: ?>
                    <span class="thread-reward">
                        <i class="bi bi-gem"></i> +<?php echo $thread['gem_reward']; ?> gem untuk jawaban terbaik
                    </span>
                    <?php endif; ?>
                </div>
                <h1 class="thread-title"><?php echo htmlspecialchars($thread['title']); ?></h1>
            </div>

            <!-- Body -->
            <div class="thread-question-body">
                <!-- Author -->
                <div class="thread-author-inline">
                    <div class="thread-avatar">
                        <?php if (!empty($thread['author_avatar'])): ?>
                            <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($thread['author_avatar']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($thread['author_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="thread-author-info">
                        <span class="thread-author-name"><?php echo htmlspecialchars($thread['author_name']); ?></span>
                        <span class="thread-time">
                            <?php echo time_elapsed($thread['created_at']); ?>
                            <?php if ($thread['updated_at'] && $thread['updated_at'] != $thread['created_at']): ?>
                            <span class="edited-badge">(diedit)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="thread-text">
                    <?php echo nl2br(htmlspecialchars($thread['content'])); ?>
                </div>
                
                <!-- Attachments -->
                <?php if (!empty($attachments)): ?>
                <div class="thread-attachments">
                    <h4><i class="bi bi-paperclip"></i> Lampiran</h4>
                    <div class="attachment-list">
                        <?php foreach ($attachments as $att): ?>
                        <a href="<?php echo BASE_PATH . '/' . $att['file_path']; ?>" target="_blank" class="attachment-item">
                            <?php if (strpos($att['file_type'], 'image') !== false): ?>
                            <img src="<?php echo BASE_PATH . '/' . $att['file_path']; ?>" alt="">
                            <?php else: ?>
                            <i class="bi bi-file-earmark"></i>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($att['file_name']); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="thread-question-footer">
                <div class="thread-stats">
                    <span><i class="bi bi-eye"></i> <?php echo $thread['views']; ?> views</span>
                    <span><i class="bi bi-chat-dots"></i> <?php echo count($replies); ?> jawaban</span>
                </div>
                
                <?php if ($userId == $thread['author_id']): ?>
                <div class="thread-actions">
                    <a href="<?php echo BASE_PATH; ?>/forum/edit/<?php echo $threadId; ?>" class="btn btn-sm btn-outline">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <button type="button" class="btn btn-sm btn-outline btn-danger-outline" onclick="openDeleteModal()">
                        <i class="bi bi-trash"></i> Hapus
                    </button>
                </div>
                
                <!-- Delete Form (hidden) -->
                <form id="deleteForm" method="POST" style="display: none;">
                    <input type="hidden" name="delete_thread" value="1">
                </form>
                <?php endif; ?>
            </div>
        </article>

        <!-- Replies Section -->
        <section class="thread-replies" id="replies">
            <h2><i class="bi bi-chat-left-text"></i> <?php echo count($replies); ?> Jawaban</h2>

            <?php if (empty($replies)): ?>
            <div class="thread-no-replies">
                <i class="bi bi-chat-square-text"></i>
                <h3>Belum Ada Jawaban</h3>
                <p>Jadilah yang pertama menjawab dan dapatkan <?php echo $thread['gem_reward']; ?> gem!</p>
            </div>
            <?php else: ?>
                <?php foreach ($replies as $reply): ?>
                <article class="thread-reply <?php echo $reply['is_best_answer'] ? 'best-answer' : ''; ?>" id="reply-<?php echo $reply['id']; ?>">
                    <?php if ($reply['is_best_answer']): ?>
                    <div class="best-answer-badge">
                        <i class="bi bi-trophy-fill"></i> Jawaban Terbaik
                    </div>
                    <?php endif; ?>
                    
                    <div class="reply-body">
                        <!-- Author -->
                        <div class="reply-author-inline">
                            <div class="reply-avatar">
                                <?php if (!empty($reply['author_avatar'])): ?>
                                    <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($reply['author_avatar']); ?>" alt="Avatar">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($reply['author_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="reply-author-info">
                                <span class="reply-author-name">
                                    <?php echo htmlspecialchars($reply['author_name']); ?>
                                    <?php if ($reply['author_id'] == $thread['author_id']): ?>
                                    <span class="author-badge">Penanya</span>
                                    <?php endif; ?>
                                </span>
                                <span class="reply-time"><?php echo time_elapsed($reply['created_at']); ?></span>
                            </div>
                        </div>
                        
                        <!-- Content -->
                        <div class="reply-content">
                            <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                        </div>
                    </div>
                    
                    <div class="reply-footer">
                        <div class="reply-actions">
                            <?php if ($userId && $userId != $reply['author_id']): ?>
                            <button class="reply-upvote <?php echo $reply['user_upvoted'] ? 'upvoted' : ''; ?>" 
                                    data-reply-id="<?php echo $reply['id']; ?>">
                                <i class="bi bi-hand-thumbs-up<?php echo $reply['user_upvoted'] ? '-fill' : ''; ?>"></i>
                                <span><?php echo $reply['upvote_count']; ?></span>
                            </button>
                            <?php else: ?>
                            <span class="reply-upvote-count">
                                <i class="bi bi-hand-thumbs-up"></i>
                                <span><?php echo $reply['upvote_count']; ?></span>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($userId == $thread['author_id'] && !$thread['is_solved'] && $reply['author_id'] != $userId): ?>
                        <button type="button" class="btn btn-sm btn-success" 
                                onclick="openBestAnswerModal(<?php echo $reply['id']; ?>)">
                            <i class="bi bi-check-lg"></i> Pilih Jawaban Terbaik
                        </button>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Reply Form -->
        <?php if ($userId): ?>
        <section class="thread-reply-form" id="reply-form">
            <h3><i class="bi bi-reply"></i> Tulis Jawabanmu</h3>
            
            <?php if ($replyError): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i> <?php echo $replyError; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo BASE_PATH; ?>/forum/thread/<?php echo $threadId; ?>#reply-form">
                <div class="form-group">
                    <textarea name="reply_content" rows="5" 
                              placeholder="Tulis jawabanmu di sini. Jelaskan dengan detail dan jelas..."
                              required><?php echo htmlspecialchars($_POST['reply_content'] ?? ''); ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Kirim Jawaban
                    </button>
                </div>
            </form>
        </section>
        <?php else: ?>
        <div class="thread-login-prompt">
            <i class="bi bi-lock"></i>
            <p>Silakan <a href="<?php echo BASE_PATH; ?>/login?redirect=forum/thread/<?php echo $threadId; ?>">login</a> untuk menjawab pertanyaan ini.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <?php if ($userId == $thread['author_id']): ?>
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-container">
            <div class="modal-icon danger">
                <i class="bi bi-trash"></i>
            </div>
            <h3>Hapus Pertanyaan?</h3>
            <p>Yakin ingin menghapus pertanyaan ini?</p>
            <ul class="modal-info">
                <li><i class="bi bi-chat-dots"></i> Semua jawaban akan ikut terhapus</li>
                <li><i class="bi bi-paperclip"></i> Semua lampiran akan ikut terhapus</li>
                <?php if (!$thread['is_solved']): ?>
                <li><i class="bi bi-gem"></i> <strong><?php echo $thread['gem_reward']; ?> gem</strong> akan dikembalikan</li>
                <?php endif; ?>
            </ul>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Batal</button>
                <button type="button" class="btn btn-danger-solid" onclick="submitDelete()">
                    <i class="bi bi-trash"></i> Ya, Hapus
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Best Answer Confirmation Modal -->
    <?php if ($userId == $thread['author_id'] && !$thread['is_solved']): ?>
    <div class="modal-overlay" id="bestAnswerModal">
        <div class="modal-container">
            <div class="modal-icon success">
                <i class="bi bi-trophy"></i>
            </div>
            <h3>Pilih Jawaban Terbaik?</h3>
            <p>Kamu yakin ingin memilih jawaban ini sebagai yang terbaik?</p>
            <ul class="modal-info">
                <li><i class="bi bi-gem"></i> <strong><?php echo $thread['gem_reward']; ?> gem</strong> akan diberikan ke penjawab</li>
                <li><i class="bi bi-lock"></i> Pilihan ini tidak bisa diubah</li>
                <li><i class="bi bi-check-circle"></i> Pertanyaan akan ditandai terjawab</li>
            </ul>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeBestAnswerModal()">Batal</button>
                <a href="#" id="confirmBestAnswerLink" class="btn btn-success-solid">
                    <i class="bi bi-trophy"></i> Ya, Pilih Ini
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // Delete Modal
    const deleteModal = document.getElementById('deleteModal');

    function openDeleteModal() {
        if (deleteModal) {
            deleteModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeDeleteModal() {
        if (deleteModal) {
            deleteModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    function submitDelete() {
        document.getElementById('deleteForm').submit();
    }

    // Best Answer Modal
    const bestAnswerModal = document.getElementById('bestAnswerModal');

    function openBestAnswerModal(replyId) {
        if (bestAnswerModal) {
            document.getElementById('confirmBestAnswerLink').href = '?best=' + replyId;
            bestAnswerModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeBestAnswerModal() {
        if (bestAnswerModal) {
            bestAnswerModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    // Close modals on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    });

    // Upvote handler
    document.querySelectorAll('.reply-upvote').forEach(btn => {
        btn.addEventListener('click', async function() {
            const replyId = this.dataset.replyId;
            
            try {
                const res = await fetch('<?php echo BASE_PATH; ?>/api/forum/upvote', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({reply_id: replyId})
                });
                
                const data = await res.json();
                
                if (data.success) {
                    this.classList.toggle('upvoted');
                    this.querySelector('i').className = data.upvoted ? 'bi bi-hand-thumbs-up-fill' : 'bi bi-hand-thumbs-up';
                    this.querySelector('span').textContent = data.count;
                } else if (data.error === 'Not logged in') {
                    window.location.href = '<?php echo BASE_PATH; ?>/login?redirect=forum/thread/<?php echo $threadId; ?>';
                }
            } catch (e) {
                console.error(e);
            }
        });
    });

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
