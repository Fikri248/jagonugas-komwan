<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}

// koneksi aman
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed. Please contact administrator.');
}

$student_id = $_SESSION['user_id'];
$mentor_id  = (int)($_GET['mentor_id'] ?? 0);

// Get student gems
$stmt = $pdo->prepare("SELECT gems FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student       = $stmt->fetch(PDO::FETCH_ASSOC);
$student_gems  = $student['gems'] ?? 0;

// Get mentor details
$stmt = $pdo->prepare("
    SELECT id, name, email, program_studi, specialization, hourly_rate,
           CASE 
               WHEN review_count > 0 THEN ROUND(total_rating / review_count, 1)
               ELSE 0 
           END as avg_rating,
           review_count
    FROM users 
    WHERE id = ? AND role = 'mentor' AND is_verified = 1
");
$stmt->execute([$mentor_id]);
$mentor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mentor) {
    header('Location: ' . BASE_PATH . '/student-mentor.php');
    exit;
}

// Package prices based on table
$packages = [
    15 => ['gems' => 1000, 'name' => 'Tugas Biasa',   'desc' => 'Konsultasi singkat untuk tugas biasa'],
    30 => ['gems' => 2500, 'name' => 'Tugas Praktikum','desc' => 'Bimbingan untuk tugas praktikum'],
    60 => ['gems' => 5000, 'name' => 'Tugas Ngoding',  'desc' => 'Sesi lengkap untuk tugas coding/programming'],
    90 => ['gems' => 7500, 'name' => 'Tugas Besar',    'desc' => 'Sesi intensif untuk tugas besar/projek akhir'],
];

// Handle booking
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $duration = (int)($_POST['duration'] ?? 0);
    $notes    = trim($_POST['notes'] ?? '');

    if (!isset($packages[$duration])) {
        $error = 'Durasi tidak valid!';
    } else {
        $price = $packages[$duration]['gems'];

        if ($student_gems < $price) {
            $error = 'Saldo gems tidak cukup! Anda memerlukan ' . number_format($price) . ' gems.';
        } else {
            try {
                $pdo->beginTransaction();

                // Create session
                $stmt = $pdo->prepare("
                    INSERT INTO sessions (student_id, mentor_id, duration, price, notes, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$student_id, $mentor_id, $duration, $price, $notes]);

                // === AUTO CREATE CONVERSATION JIKA BELUM ADA ===
                $stmt = $pdo->prepare("
                    SELECT id 
                    FROM conversations 
                    WHERE mentor_id = ? AND student_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$mentor_id, $student_id]);
                $conv = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$conv) {
                    $stmt = $pdo->prepare("
                        INSERT INTO conversations (mentor_id, student_id)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$mentor_id, $student_id]);
                }
                // === END AUTO CREATE CONVERSATION ===

                // Deduct gems from student
                $stmt = $pdo->prepare("UPDATE users SET gems = gems - ? WHERE id = ?");
                $stmt->execute([$price, $student_id]);

                $pdo->commit();

                $_SESSION['success'] = 'Booking berhasil! Mentor akan segera menghubungi Anda.';
                header('Location: ' . BASE_PATH . '/student-sessions.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan saat booking. Silakan coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Session - <?php echo htmlspecialchars($mentor['name']); ?> - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style-mentor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php include 'student-navbar.php'; ?>

    <div class="booking-container">
        <?php if ($error): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Mentor Preview -->
        <div class="mentor-preview">
            <div class="avatar">
                <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
            </div>
            <div class="mentor-preview-info">
                <h2><?php echo htmlspecialchars($mentor['name']); ?></h2>
                <p>
                    <i class="bi bi-book-fill"></i>
                    <?php echo htmlspecialchars($mentor['program_studi']); ?>
                </p>
                <?php if ($mentor['specialization']): ?>
                    <p>
                        <i class="bi bi-lightbulb-fill"></i>
                        <?php echo htmlspecialchars($mentor['specialization']); ?>
                    </p>
                <?php endif; ?>
                <p>
                    <i class="bi bi-star-fill"></i>
                    Rating: <?php echo $mentor['avg_rating']; ?> (<?php echo $mentor['review_count']; ?> reviews)
                </p>
            </div>
        </div>

        <!-- Package Selection -->
        <div class="package-selection">
            <h2>Pilih Paket Konsultasi</h2>

            <form method="POST" action="" id="bookingForm">
                <!-- Package 15 Minutes - Tugas Biasa -->
                <label class="package-option">
                    <input type="radio" name="duration" value="15" required>
                    <span class="package-badge">
                        <i class="bi bi-check-circle-fill"></i> Dipilih
                    </span>
                    <div class="package-content">
                        <div class="package-header">
                            <h3>⚡ Tugas Biasa</h3>
                            <div class="package-price">
                                <i class="bi bi-gem"></i>
                                1,000
                            </div>
                        </div>
                        <p>15 menit - Konsultasi singkat untuk tugas biasa</p>
                    </div>
                </label>

                <!-- Package 30 Minutes - Tugas Praktikum -->
                <label class="package-option">
                    <input type="radio" name="duration" value="30">
                    <span class="package-badge">
                        <i class="bi bi-check-circle-fill"></i> Dipilih
                    </span>
                    <div class="package-content">
                        <div class="package-header">
                            <h3>📝 Tugas Praktikum</h3>
                            <div class="package-price">
                                <i class="bi bi-gem"></i>
                                2,500
                            </div>
                        </div>
                        <p>30 menit - Bimbingan untuk tugas praktikum</p>
                    </div>
                </label>

                <!-- Package 60 Minutes - Tugas Ngoding -->
                <label class="package-option">
                    <input type="radio" name="duration" value="60">
                    <span class="package-badge">
                        <i class="bi bi-check-circle-fill"></i> Dipilih
                    </span>
                    <div class="package-content">
                        <div class="package-header">
                            <h3>💻 Tugas Ngoding</h3>
                            <div class="package-price">
                                <i class="bi bi-gem"></i>
                                5,000
                            </div>
                        </div>
                        <p>60 menit - Sesi lengkap untuk tugas coding/programming</p>
                    </div>
                </label>

                <!-- Package 90 Minutes - Tugas Besar -->
                <label class="package-option">
                    <input type="radio" name="duration" value="90">
                    <span class="package-badge">
                        <i class="bi bi-check-circle-fill"></i> Dipilih
                    </span>
                    <div class="package-content">
                        <div class="package-header">
                            <h3>🚀 Tugas Besar</h3>
                            <div class="package-price">
                                <i class="bi bi-gem"></i>
                                7,500
                            </div>
                        </div>
                        <p>90 menit - Sesi intensif untuk tugas besar/projek akhir</p>
                    </div>
                </label>

                <!-- Gem Balance Info -->
                <div class="gem-info">
                    <strong>Saldo Gems Anda:</strong>
                    <span>
                        <i class="bi bi-gem"></i>
                        <?php echo number_format($student_gems); ?>
                    </span>
                </div>

                <!-- Notes -->
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label for="notes" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; font-weight: 600; color: #2d3748;">
                        <i class="bi bi-chat-text-fill"></i>
                        Catatan untuk Mentor (opsional)
                    </label>
                    <textarea id="notes" name="notes" rows="4" placeholder="Jelaskan topik yang ingin Anda diskusikan..." style="width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 12px; font-family: inherit; resize: vertical; font-size: 0.95rem;"></textarea>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-full" style="margin-top: 1.5rem;">
                    <i class="bi bi-calendar-check-fill"></i>
                    Konfirmasi Booking
                </button>

                <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="btn btn-outline btn-full" style="margin-top: 1rem;">
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Daftar Mentor
                </a>
            </form>
        </div>
    </div>

    <script>
    const errorMsg = document.querySelector('.error-message');
    if (errorMsg) {
        setTimeout(() => {
            errorMsg.style.opacity = '0';
            errorMsg.style.transform = 'translateY(-10px)';
            setTimeout(() => errorMsg.remove(), 300);
        }, 5000);
    }

    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        const selected = document.querySelector('input[name="duration"]:checked');
        if (!selected) {
            e.preventDefault();
            alert('Silakan pilih paket konsultasi terlebih dahulu!');
            return;
        }

        const priceText = selected.closest('.package-option').querySelector('.package-price').textContent;
        const price     = parseInt(priceText.replace(/[^0-9]/g, ''));
        const userGems  = <?php echo (int)$student_gems; ?>;

        if (price > userGems) {
            e.preventDefault();
            alert('Saldo gems tidak cukup! Anda memerlukan ' + price.toLocaleString('id-ID') + ' gems.');
        }
    });
    </script>
</body>
</html>
git config user.name