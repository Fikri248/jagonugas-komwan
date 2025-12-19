<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$logged_user_id   = $_SESSION['user_id'] ?? null;
$logged_user_role = $_SESSION['role'] ?? null;

// id mentor dari query string, fallback ke mentor yang login jika role mentor
$mentor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($mentor_id <= 0 && $logged_user_role === 'mentor') {
    $mentor_id = (int)$logged_user_id;
}

if ($mentor_id <= 0) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Ambil data mentor
$stmt = $pdo->prepare("
    SELECT id, name, email, avatar, program_studi, specialization, hourly_rate,
           bio, expertise, total_rating, review_count, created_at
    FROM users
    WHERE id = ? AND role = 'mentor' AND is_verified = 1
");
$stmt->execute([$mentor_id]);
$mentor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mentor) {
    die('Mentor tidak ditemukan atau belum terverifikasi.');
}

// Statistik dasar
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sessions,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_sessions
    FROM sessions
    WHERE mentor_id = ?
");
$stmt->execute([$mentor_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$avg_rating = $mentor['review_count'] > 0
    ? round($mentor['total_rating'] / $mentor['review_count'], 1)
    : 0;

// cek apakah viewer adalah mentor yang sama
$is_owner   = ($logged_user_role === 'mentor' && (int)$logged_user_id === (int)$mentor_id);
// cek kalau viewer student → bisa tampilkan tombol Book
$is_student = ($logged_user_role === 'student');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Mentor - <?php echo htmlspecialchars($mentor['name']); ?> - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style-mentor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php // kalau punya navbar umum/student/mentor, bisa include di sini ?>

    <div style="max-width:900px;margin:2rem auto;padding:0 1.5rem;">
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
            <a href="<?php echo BASE_PATH; ?>/<?php echo $is_student ? 'student-mentor.php' : 'mentor-dashboard.php'; ?>" 
               class="btn btn-outline" style="padding:0.5rem 0.9rem;font-size:0.85rem;">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <h1 style="margin:0;font-size:1.7rem;">
                <i class="bi bi-person-badge"></i> Profil Mentor
            </h1>
        </div>

        <div style="background:white;border-radius:18px;border:2px solid #e2e8f0;padding:1.75rem;display:flex;gap:1.5rem;align-items:flex-start;margin-bottom:1.5rem;">
            <div style="width:130px;min-width:130px;height:130px;border-radius:50%;overflow:hidden;border:4px solid #667eea;">
                <?php if (!empty($mentor['avatar'])): ?>
                    <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($mentor['avatar']); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#667eea,#764ba2);color:white;font-size:3rem;font-weight:bold;">
                        <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="flex:1;">
                <h2 style="margin:0 0 0.4rem;font-size:1.5rem;"><?php echo htmlspecialchars($mentor['name']); ?></h2>
                <p style="margin:0.1rem 0;color:#4a5568;">
                    <i class="bi bi-book"></i>
                    <?php echo htmlspecialchars($mentor['program_studi']); ?>
                </p>
                <?php if (!empty($mentor['specialization'])): ?>
                    <p style="margin:0.1rem 0;color:#4a5568;">
                        <i class="bi bi-lightbulb"></i>
                        <?php echo htmlspecialchars($mentor['specialization']); ?>
                    </p>
                <?php endif; ?>
                <p style="margin:0.1rem 0;color:#4a5568;">
                    <i class="bi bi-calendar-check"></i>
                    Bergabung sejak <?php echo date('d M Y', strtotime($mentor['created_at'])); ?>
                </p>

                <div style="display:flex;flex-wrap:wrap;gap:1rem;margin-top:0.8rem;">
                    <div style="display:flex;align-items:center;gap:0.35rem;color:#fbbf24;">
                        <i class="bi bi-star-fill"></i>
                        <strong><?php echo $avg_rating; ?></strong>
                        <span style="color:#718096;font-size:0.9rem;">
                            (<?php echo (int)$mentor['review_count']; ?> review)
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.35rem;color:#10b981;">
                        <i class="bi bi-check-circle"></i>
                        <span style="font-size:0.9rem;">
                            <?php echo (int)$stats['completed_sessions']; ?> sesi selesai
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.35rem;color:#6366f1;">
                        <i class="bi bi-gem"></i>
                        <span style="font-size:0.9rem;">
                            Rate: <?php echo number_format($mentor['hourly_rate']); ?> Gems/jam
                        </span>
                    </div>
                </div>

                <div style="margin-top:1.2rem;display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <?php if ($is_student): ?>
                        <a href="<?php echo BASE_PATH; ?>/book-session.php?mentor_id=<?php echo $mentor['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-calendar-plus"></i> Book Session
                        </a>
                    <?php endif; ?>

                    <?php if ($is_owner): ?>
                        <a href="<?php echo BASE_PATH; ?>/mentor-settings.php" class="btn btn-outline">
                            <i class="bi bi-gear"></i> Edit Profil
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($mentor['bio'])): ?>
        <div style="background:white;border-radius:16px;border:2px solid #e2e8f0;padding:1.5rem;margin-bottom:1.25rem;">
            <h3 style="margin-top:0;margin-bottom:0.75rem;">
                <i class="bi bi-file-text"></i> Tentang Mentor
            </h3>
            <p style="margin:0;color:#4a5568;white-space:pre-line;">
                <?php echo nl2br(htmlspecialchars($mentor['bio'])); ?>
            </p>
        </div>
        <?php endif; ?>

        <?php if (!empty($mentor['expertise'])): ?>
        <div style="background:white;border-radius:16px;border:2px solid #e2e8f0;padding:1.5rem;">
            <h3 style="margin-top:0;margin-bottom:0.75rem;">
                <i class="bi bi-award"></i> Keahlian
            </h3>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                <?php
                $tags = array_filter(array_map('trim', explode(',', $mentor['expertise'])));
                foreach ($tags as $tag): ?>
                    <span style="padding:0.25rem 0.6rem;border-radius:999px;background:#ebf4ff;color:#3b82f6;font-size:0.85rem;">
                        <?php echo htmlspecialchars($tag); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
