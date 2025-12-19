<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mentor') {
    header('Location: ' . BASE_PATH . '/mentor-login.php');
    exit;
}

$mentor_id = $_SESSION['user_id'];
$name      = $_SESSION['name'] ?? 'Mentor';
$email     = $_SESSION['email'] ?? '';

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed.');
}

// avatar initial
$initial = 'M';
if (is_string($name) && $name !== '') {
    $initial = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($name, 0, 1));
}

// conversation yang dipilih
$currentConvId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;

/* ===== Sidebar: list conversations mentor ===== */
$stmt = $pdo->prepare("
    SELECT c.id,
           u.name AS student_name,
           u.program_studi AS student_prodi
    FROM conversations c
    JOIN users u ON c.student_id = u.id
    WHERE c.mentor_id = ?
    ORDER BY c.updated_at DESC
");
$stmt->execute([$mentor_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$currentConvId && !empty($conversations)) {
    $currentConvId = (int)$conversations[0]['id'];
}

/* ===== Data conversation aktif & messages ===== */
$currentConv = null;
$currentMsgs = [];

if ($currentConvId) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.name AS student_name
        FROM conversations c
        JOIN users u ON c.student_id = u.id
        WHERE c.id = ? AND c.mentor_id = ?
    ");
    $stmt->execute([$currentConvId, $mentor_id]);
    $currentConv = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($currentConv) {
        $stmt = $pdo->prepare("
            SELECT m.*, u.name AS sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$currentConvId]);
        $currentMsgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // tandai pesan dari student sebagai read
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE conversation_id = ? 
              AND sender_id != ?
        ");
        $stmt->execute([$currentConvId, $mentor_id]);
    } else {
        $currentConvId = 0;
    }
}

function url_path(string $path = ''): string
{
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Mentor - JagoNugas</title>

    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style-mentor-dashboard.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style-mentor-chat.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="mentor-dashboard-page">

<header class="mentor-navbar">
    <div class="mentor-navbar-inner">
        <div class="mentor-navbar-left">
            <a href="<?php echo htmlspecialchars(url_path('mentor-dashboard.php')); ?>" class="mentor-logo">
                <div class="mentor-logo-mark">M</div>
                <span class="mentor-logo-text">JagoNugas</span>
                <span class="mentor-badge">Mentor</span>
            </a>
            <nav class="mentor-nav-links">
                <a href="<?php echo htmlspecialchars(url_path('mentor-dashboard.php')); ?>">Dashboard</a>
                <a href="<?php echo htmlspecialchars(url_path('mentor-sessions.php')); ?>">Booking Saya</a>
                <a href="<?php echo htmlspecialchars(url_path('mentor-chat.php')); ?>" class="active">Chat</a>
            </nav>
        </div>

        <div class="mentor-navbar-right">
            <div class="mentor-user-menu">
                <div class="mentor-avatar"><?php echo htmlspecialchars($initial); ?></div>
                <div class="mentor-user-info">
                    <span class="mentor-user-name"><?php echo htmlspecialchars($name); ?></span>
                    <span class="mentor-user-role">Mentor</span>
                </div>
                <i class="bi bi-chevron-down"></i>
                <div class="mentor-dropdown">
                    <a href="<?php echo htmlspecialchars(url_path('mentor-profile.php')); ?>"><i class="bi bi-person"></i> Profil Saya</a>
                    <a href="<?php echo htmlspecialchars(url_path('mentor-settings.php')); ?>"><i class="bi bi-gear"></i> Pengaturan</a>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo htmlspecialchars(url_path('logout.php')); ?>" class="logout"><i class="bi bi-box-arrow-right"></i> Keluar</a>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="mentor-main">
    <div class="chat-layout">
        <!-- Sidebar conversations -->
        <aside class="chat-sidebar">
            <div class="chat-sidebar-header">
                <h2>Chat Mahasiswa</h2>
            </div>

            <?php if (empty($conversations)): ?>
                <p class="chat-empty">Belum ada percakapan. Chat akan muncul setelah ada sesi dengan mahasiswa.</p>
            <?php else: ?>
                <ul class="chat-conversation-list">
                    <?php foreach ($conversations as $conv): ?>
                        <?php
                            $cId     = (int)$conv['id'];
                            $initialS = 'S';
                            if (!empty($conv['student_name'])) {
                                $initialS = function_exists('mb_substr')
                                    ? mb_strtoupper(mb_substr($conv['student_name'], 0, 1, 'UTF-8'), 'UTF-8')
                                    : strtoupper(substr($conv['student_name'], 0, 1));
                            }
                            $active = $currentConvId === $cId;
                        ?>
                        <li>
                            <a href="?conversation_id=<?php echo $cId; ?>"
                               class="chat-conversation-item <?php echo $active ? 'active' : ''; ?>">
                                <div class="chat-avatar"><?php echo htmlspecialchars($initialS); ?></div>
                                <div class="chat-conversation-info">
                                    <div class="chat-conversation-name">
                                        <?php echo htmlspecialchars($conv['student_name']); ?>
                                    </div>
                                    <?php if (!empty($conv['student_prodi'])): ?>
                                        <div class="chat-conversation-sub">
                                            <?php echo htmlspecialchars($conv['student_prodi']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>

        <!-- Chat main panel -->
        <section class="chat-main">
            <?php if (!$currentConvId || !$currentConv): ?>
                <div class="chat-empty-state">
                    <i class="bi bi-chat-dots"></i>
                    <h2>Pilih percakapan</h2>
                    <p>Pilih salah satu mahasiswa di sisi kiri untuk mulai melihat pesan.</p>
                </div>
            <?php else: ?>
                <div class="chat-header">
                    <?php
                        $initialS = function_exists('mb_substr')
                            ? mb_strtoupper(mb_substr($currentConv['student_name'], 0, 1, 'UTF-8'), 'UTF-8')
                            : strtoupper(substr($currentConv['student_name'], 0, 1));
                    ?>
                    <div class="chat-avatar"><?php echo htmlspecialchars($initialS); ?></div>
                    <div>
                        <div class="chat-header-name">
                            <?php echo htmlspecialchars($currentConv['student_name']); ?>
                        </div>
                        <div class="chat-header-sub">Mahasiswa</div>
                    </div>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($currentMsgs)): ?>
                        <div class="chat-empty-thread">
                            Belum ada pesan. Mulai percakapan dengan mengirim pesan pertama.
                        </div>
                    <?php else: ?>
                        <?php foreach ($currentMsgs as $msg): ?>
                            <?php $isMe = $msg['sender_id'] == $mentor_id; ?>
                            <div class="chat-message-row <?php echo $isMe ? 'me' : 'other'; ?>">
                                <div class="chat-message-bubble">
                                    <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                    <span class="chat-message-time">
                                        <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form class="chat-input-area" method="POST" action="<?php echo BASE_PATH; ?>/mentor-chat-send.php">
                    <input type="hidden" name="conversation_id" value="<?php echo (int)$currentConvId; ?>">
                    <div class="chat-input-wrapper">
                        <textarea name="message" rows="1" placeholder="Tulis pesan..." required></textarea>
                        <button type="submit" class="chat-send-btn">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
const msgBox = document.getElementById('chatMessages');
if (msgBox) {
    msgBox.scrollTop = msgBox.scrollHeight;
}
</script>
</body>
</html>
