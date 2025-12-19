<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mentor') {
    header('Location: ' . BASE_PATH . '/mentor-login.php');
    exit;
}

$mentor_id = $_SESSION['user_id'];

// Ambil koneksi PDO (pola sama dengan student-settings.php)
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}

// Ambil semua sesi untuk mentor ini
$stmt = $pdo->prepare("
    SELECT s.*,
           u.name  AS student_name,
           u.email AS student_email,
           u.program_studi AS student_prodi
    FROM sessions s
    JOIN users u ON s.student_id = u.id
    WHERE s.mentor_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$mentor_id]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistik status
$stats = ['pending'=>0,'ongoing'=>0,'completed'=>0,'cancelled'=>0];
foreach ($sessions as $session) {
    if (isset($stats[$session['status']])) {
        $stats[$session['status']]++;
    }
}

// Flash notifikasi (static di NotificationHelper)
$success = NotificationHelper::getSuccess();
$error   = NotificationHelper::getError();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Sesi Saya - Mentor - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style-mentor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="mentor-dashboard-page">

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
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <a href="<?php echo BASE_PATH; ?>/mentor-dashboard.php"
               class="btn btn-outline"
               style="padding:0.5rem 0.9rem;font-size:0.85rem;display:inline-flex;align-items:center;gap:0.4rem;">
                <i class="bi bi-arrow-left"></i>
                Dashboard
            </a>
            <h1 style="margin:0;">
                <i class="bi bi-people"></i>
                Booking Sesi Saya
            </h1>
        </div>
        <span style="color:#718096;font-size:0.95rem;">
            Total sesi: <strong><?php echo count($sessions); ?></strong>
        </span>
    </div>

    <!-- Statistik -->
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem;margin-bottom:2rem;">
        <div class="stat-card" style="background:white;padding:1.5rem;border-radius:14px;border:2px solid #e2e8f0;text-align:center;">
            <div style="font-size:2rem;color:#fbbf24;margin-bottom:0.5rem;">
                <i class="bi bi-clock-history"></i>
            </div>
            <div style="font-size:1.75rem;font-weight:800;color:#1a202c;margin-bottom:0.25rem;">
                <?php echo $stats['pending']; ?>
            </div>
            <div style="font-size:0.9rem;color:#718096;font-weight:600;">
                Menunggu Respons Anda
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
                Sesi Berlangsung
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
                Sesi Selesai
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

    <!-- List Sesi -->
    <?php if (empty($sessions)): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h2>Belum Ada Sesi</h2>
            <p>Belum ada mahasiswa yang booking sesi dengan Anda.</p>
        </div>
    <?php else: ?>
        <?php foreach ($sessions as $session): ?>
            <div class="session-card">
                <div class="session-header">
                    <div class="session-mentor">
                        <div class="mentor-avatar" style="width:50px;height:50px;min-width:50px;font-size:1.25rem;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;display:flex;align-items:center;justify-content:center;border-radius:50%;font-weight:700;box-shadow:0 4px 12px rgba(102,126,234,0.3);">
                            <?php echo strtoupper(substr($session['student_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h3><?php echo htmlspecialchars($session['student_name']); ?></h3>
                            <p style="margin:0;color:#718096;font-size:0.9rem;">
                                <i class="bi bi-book"></i>
                                <?php echo htmlspecialchars($session['student_prodi']); ?>
                            </p>
                        </div>
                    </div>
                    <span class="session-status status-<?php echo $session['status']; ?>">
                        <?php 
                        $status_text = [
                            'pending'   => '⏳ Menunggu',
                            'ongoing'   => '▶️ Berlangsung',
                            'completed' => '✅ Selesai',
                            'cancelled' => '❌ Dibatalkan'
                        ];
                        echo $status_text[$session['status']];
                        ?>
                    </span>
                </div>

                <div class="session-details">
                    <div class="detail-item">
                        <span class="detail-label">
                            <i class="bi bi-clock"></i>
                            Durasi
                        </span>
                        <span class="detail-value"><?php echo $session['duration']; ?> menit</span>
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

                    <?php if ($session['notes']): ?>
                        <div class="detail-item" style="grid-column:1/-1;">
                            <span class="detail-label">
                                <i class="bi bi-chat-text"></i>
                                Catatan dari Mahasiswa
                            </span>
                            <span class="detail-value" style="font-weight:400;color:#718096;font-size:0.9rem;">
                                <?php echo nl2br(htmlspecialchars($session['notes'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($session['rating']): ?>
                        <div class="detail-item" style="grid-column:1/-1;">
                            <span class="detail-label">
                                <i class="bi bi-star-fill"></i>
                                Rating dari Mahasiswa
                            </span>
                            <span class="detail-value" style="display:flex;align-items:center;gap:0.5rem;">
                                <?php for ($i=1; $i<=5; $i++): ?>
                                    <i class="bi bi-star<?php echo $i <= $session['rating'] ? '-fill' : ''; ?>" style="color:#fbbf24;font-size:1.2rem;"></i>
                                <?php endfor; ?>
                                <?php if ($session['review']): ?>
                                    <span style="color:#718096;font-size:0.9rem;">
                                        "<?php echo htmlspecialchars($session['review']); ?>"
                                    </span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TOMBOL AKSI -->
                <div class="session-actions">
                    <?php if ($session['status'] === 'pending'): ?>
                        <form method="POST" action="<?php echo BASE_PATH; ?>/mentor-session-action.php" style="display:inline;" onsubmit="return confirm('Terima booking ini?');">
                            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i>
                                Terima
                            </button>
                        </form>

                        <form method="POST" action="<?php echo BASE_PATH; ?>/mentor-session-action.php" style="display:inline;" onsubmit="return confirm('Tolak booking ini? Gems akan dikembalikan ke mahasiswa.');">
                            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-x-circle"></i>
                                Tolak
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($session['status'] === 'ongoing'): ?>
                        <form method="POST" action="<?php echo BASE_PATH; ?>/mentor-session-action.php" style="display:inline;" onsubmit="return confirm('Tandai sesi ini sebagai selesai?');">
                            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-square"></i>
                                Selesaikan
                            </button>
                        </form>
                    <?php endif; ?>
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
