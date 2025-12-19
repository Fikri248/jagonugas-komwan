<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}

$student_id = $_SESSION['user_id'];

// Koneksi PDO (kalau pakai class Database seperti di file lain)
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed. Please contact administrator.');
}

// Get all sessions for this student
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.name          AS mentor_name,
           u.email         AS mentor_email,
           u.program_studi AS mentor_prodi,
           CASE 
               WHEN u.review_count > 0 THEN ROUND(u.total_rating / u.review_count, 1)
               ELSE 0 
           END AS mentor_rating
    FROM sessions s
    JOIN users u ON s.mentor_id = u.id
    WHERE s.student_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$student_id]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = [
    'pending'   => 0,
    'ongoing'   => 0,
    'completed' => 0,
    'cancelled' => 0
];
foreach ($sessions as $session) {
    if (isset($stats[$session['status']])) {
        $stats[$session['status']]++;
    }
}

// Flash message via NotificationHelper (bisa juga pakai $_SESSION biasa)
$success = NotificationHelper::getSuccess();
$error   = NotificationHelper::getError();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesi Konsultasi Saya - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style-mentor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<?php include 'student-navbar.php'; ?>

<div class="sessions-container">

    <?php if ($success): ?>
        <div class="success-message">
            <i class="bi bi-check-circle-fill"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="sessions-header">
        <h1>
            <i class="bi bi-calendar-check"></i>
            Sesi Konsultasi Saya
        </h1>
        <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i>
            Book Sesi Baru
        </a>
    </div>

    <!-- Statistics -->
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.25rem;margin-bottom:2rem;">
        <div class="stat-card" style="background:white;padding:1.5rem;border-radius:14px;border:2px solid #e2e8f0;text-align:center;">
            <div style="font-size:2rem;color:#fbbf24;margin-bottom:0.5rem;">
                <i class="bi bi-clock-history"></i>
            </div>
            <div style="font-size:1.75rem;font-weight:800;color:#1a202c;margin-bottom:0.25rem;">
                <?php echo $stats['pending']; ?>
            </div>
            <div style="font-size:0.9rem;color:#718096;font-weight:600;">
                Menunggu Konfirmasi
            </div>
        </div>

        <div class="stat-card" style="background:white;padding:1.5rem;border-radius:14px;border:2px solid #e2e8f0;text-align:center;">
            <div style="font-size:2rem;color:#3b82f6;margin-bottom:0.5rem;">
                <i class="bi bi-play-circle"></i>
            </div>
            <div style="font-size:1.75rem;font-weight:800;color:#1a202c;margin-bottom:0.25rem;">
                <?php echo $stats['ongoing']; ?>
            </div>
            <div style="font-size:0.9rem;color:#718096;font-weight:600;">
                Sedang Berlangsung
            </div>
        </div>

        <div class="stat-card" style="background:white;padding:1.5rem;border-radius:14px;border:2px solid #e2e8f0;text-align:center;">
            <div style="font-size:2rem;color:#10b981;margin-bottom:0.5rem;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div style="font-size:1.75rem;font-weight:800;color:#1a202c;margin-bottom:0.25rem;">
                <?php echo $stats['completed']; ?>
            </div>
            <div style="font-size:0.9rem;color:#718096;font-weight:600;">
                Selesai
            </div>
        </div>

        <div class="stat-card" style="background:white;padding:1.5rem;border-radius:14px;border:2px solid #e2e8f0;text-align:center;">
            <div style="font-size:2rem;color:#ef4444;margin-bottom:0.5rem;">
                <i class="bi bi-x-circle"></i>
            </div>
            <div style="font-size:1.75rem;font-weight:800;color:#1a202c;margin-bottom:0.25rem;">
                <?php echo $stats['cancelled']; ?>
            </div>
            <div style="font-size:0.9rem;color:#718096;font-weight:600;">
                Dibatalkan
            </div>
        </div>
    </div>

    <!-- Sessions List -->
    <?php if (empty($sessions)): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h2>Belum Ada Sesi</h2>
            <p>Anda belum pernah booking sesi konsultasi dengan mentor.</p>
            <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="btn btn-primary" style="margin-top:1rem;">
                <i class="bi bi-search"></i>
                Cari Mentor
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($sessions as $session): ?>
            <div class="session-card">
                <div class="session-header">
                    <div class="session-mentor">
                        <div class="mentor-avatar" style="width:50px;height:50px;min-width:50px;font-size:1.25rem;">
                            <?php echo strtoupper(substr($session['mentor_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h3><?php echo htmlspecialchars($session['mentor_name']); ?></h3>
                            <p style="margin:0;color:#718096;font-size:0.9rem;">
                                <i class="bi bi-book"></i>
                                <?php echo htmlspecialchars($session['mentor_prodi']); ?>
                            </p>
                        </div>
                    </div>
                    <span class="session-status status-<?php echo $session['status']; ?>">
                        <?php
                        $status_text = [
                            'pending'   => 'Menunggu',
                            'ongoing'   => 'Berlangsung',
                            'completed' => 'Selesai',
                            'cancelled' => 'Dibatalkan'
                        ];
                        echo $status_text[$session['status']] ?? $session['status'];
                        ?>
                    </span>
                </div>

                <div class="session-details">
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="bi bi-clock"></i>
                            Durasi
                        </span>
                        <span class="detail-value"><?php echo (int)$session['duration']; ?> menit</span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="bi bi-gem"></i>
                            Harga
                        </span>
                        <span class="detail-value"><?php echo number_format($session['price']); ?> Gems</span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="bi bi-calendar"></i>
                            Tanggal Booking
                        </span>
                        <span class="detail-value">
                            <?php echo date('d M Y, H:i', strtotime($session['created_at'])); ?>
                        </span>
                    </div>

                    <?php if (!empty($session['notes'])): ?>
                        <div class="detail-item" style="grid-column:1/-1;">
                            <span class="detail-label">
                                <i class="bi bi-chat-text"></i>
                                Catatan
                            </span>
                            <span class="detail-value" style="font-weight:400;color:#718096;font-size:0.9rem;">
                                <?php echo nl2br(htmlspecialchars($session['notes'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="session-actions">
                    <!-- Batalkan (hanya pending) -->
                    <?php if ($session['status'] === 'pending'): ?>
                        <form method="POST"
                              action="<?php echo BASE_PATH; ?>/student-session-cancel.php"
                              style="display:inline;"
                              onsubmit="return confirm('Batalkan booking ini? Gems akan dikembalikan.');">
                            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                            <button type="submit" class="btn-cancel">
                                <i class="bi bi-x-circle"></i> Batalkan
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Beri Rating (completed, belum rating) -->
                    <?php if ($session['status'] === 'completed' && !$session['rating']): ?>
                        <a href="<?php echo BASE_PATH; ?>/session-rating.php?session_id=<?php echo $session['id']; ?>"
                           class="btn-rating">
                            <i class="bi bi-star-fill"></i> Beri Rating
                        </a>
                    <?php endif; ?>

                    <!-- Tampilkan rating jika sudah ada -->
                    <?php if ($session['status'] === 'completed' && $session['rating']): ?>
                        <div class="rating-display" style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1.25rem;background:rgba(251,191,36,0.1);border-radius:12px;border:2px solid rgba(251,191,36,0.2);">
                            <span style="color:#f59e0b;font-weight:700;">
                                Rating Anda:
                            </span>
                            <div style="display:flex;gap:0.25rem;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?php echo $i <= $session['rating'] ? '-fill' : ''; ?>" style="color:#fbbf24;font-size:1.25rem;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="btn btn-outline">
                        <i class="bi bi-arrow-left"></i>
                        Kembali ke Mentor
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
    const successMsg = document.querySelector('.success-message');
    const errorMsg   = document.querySelector('.error-message');

    [successMsg, errorMsg].forEach(msg => {
        if (!msg) return;
        setTimeout(() => {
            msg.style.transition = 'all 0.3s';
            msg.style.opacity    = '0';
            msg.style.transform  = 'translateY(-10px)';
            setTimeout(() => msg.remove(), 300);
        }, 5000);
    });
</script>
</body>
</html>
