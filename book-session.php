<?php
// book-session.php v4.0 - Modern Clean Design with Inline CSS
// Auto-create conversation for each new session

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}

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
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$student_gems = $student['gems'] ?? 0;

// Get mentor details
$stmt = $pdo->prepare("
    SELECT id, name, email, program_studi, specialization, avatar,
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

// Package configuration (tanpa emoji)
$packages = [
    15 => ['gems' => 1000, 'name' => 'Tugas Biasa',     'desc' => 'Konsultasi singkat untuk tugas biasa',           'icon' => 'bi-lightning-charge-fill', 'color' => '#3b82f6'],
    30 => ['gems' => 2500, 'name' => 'Tugas Praktikum', 'desc' => 'Bimbingan untuk tugas praktikum',                'icon' => 'bi-journal-code',          'color' => '#8b5cf6'],
    60 => ['gems' => 5000, 'name' => 'Tugas Ngoding',   'desc' => 'Sesi lengkap untuk tugas coding/programming',    'icon' => 'bi-code-slash',            'color' => '#10b981', 'popular' => true],
    90 => ['gems' => 7500, 'name' => 'Tugas Besar',     'desc' => 'Sesi intensif untuk tugas besar/projek akhir',   'icon' => 'bi-rocket-takeoff-fill',   'color' => '#f59e0b'],
];

$error = '';

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

                $stmt = $pdo->prepare("
                    INSERT INTO sessions (student_id, mentor_id, duration, price, notes, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$student_id, $mentor_id, $duration, $price, $notes]);
                $session_id = $pdo->lastInsertId();

                // v3.0: Auto create conversation for this session
                $stmt = $pdo->prepare("
                    INSERT INTO conversations (mentor_id, student_id, session_id, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$mentor_id, $student_id, $session_id]);

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
    <title>Book Session - <?php echo htmlspecialchars($mentor['name']); ?> | JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1a202c;
            background: #f8fafc;
            min-height: 100vh;
        }
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 40px;
        }

        /* ===== BUTTONS ===== */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 2px solid transparent;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .btn-outline {
            border: 2px solid #e2e8f0;
            color: #4a5568;
            background: transparent;
        }
        .btn-outline:hover {
            border-color: #667eea;
            color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .btn-full {
            width: 100%;
            justify-content: center;
        }

        /* ===== BOOKING PAGE ===== */
        .booking-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 32px 24px 60px;
        }
        .booking-header {
            margin-bottom: 24px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 8px 0;
            transition: all 0.2s;
        }
        .back-link:hover {
            color: #667eea;
        }
        .booking-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            margin-top: 12px;
        }
        .booking-header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        /* Alert */
        .alert {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Mentor Card */
        .mentor-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 24px;
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .mentor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }
        .mentor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .mentor-details {
            flex: 1;
        }
        .mentor-details h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .mentor-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 8px;
        }
        .mentor-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: #64748b;
        }
        .mentor-meta i {
            color: #667eea;
        }
        .mentor-rating {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .mentor-rating i {
            color: #fbbf24;
        }
        .mentor-rating strong {
            color: #1e293b;
        }

        /* Package Section */
        .package-section {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 24px;
        }
        .package-section h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .package-section h3 i {
            color: #667eea;
        }
        .package-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .package-item {
            position: relative;
            cursor: pointer;
        }
        .package-item input {
            display: none;
        }
        .package-card {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            background: white;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
        }
        .package-item input:checked + .package-card {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        .package-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }
        .package-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .package-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .package-duration {
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
        }
        .package-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .package-desc {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 12px;
        }
        .package-price {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 1.25rem;
            font-weight: 700;
            color: #667eea;
        }
        .package-price i {
            font-size: 1rem;
        }
        .package-badge {
            position: absolute;
            top: -10px;
            right: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .check-indicator {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .package-item input:checked + .package-card .check-indicator {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* Gems Balance */
        .gems-balance {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            margin-top: 20px;
        }
        .gems-balance-left {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            color: #64748b;
        }
        .gems-balance-left i {
            color: #8b5cf6;
            font-size: 1.25rem;
        }
        .gems-balance-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }
        .topup-link {
            font-size: 0.9rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .topup-link:hover {
            text-decoration: underline;
        }

        /* Notes Section */
        .notes-section {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 24px;
        }
        .notes-section label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
        }
        .notes-section label i {
            color: #667eea;
        }
        .notes-section label span {
            font-weight: 400;
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .notes-textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 120px;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .notes-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }
        .notes-textarea::placeholder {
            color: #94a3b8;
        }

        /* Actions */
        .booking-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .booking-wrapper {
                padding: 20px 16px 40px;
            }
            .package-grid {
                grid-template-columns: 1fr;
            }
            .mentor-card {
                flex-direction: column;
                text-align: center;
            }
            .mentor-meta {
                justify-content: center;
            }
            .gems-balance {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'student-navbar.php'; ?>

    <main class="booking-wrapper">
        <div class="booking-header">
            <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Kembali ke Daftar Mentor
            </a>
            <h1>Book Sesi Konsultasi</h1>
            <p>Pilih paket yang sesuai dengan kebutuhanmu</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Mentor Info -->
        <div class="mentor-card">
            <div class="mentor-avatar">
                <?php if (!empty($mentor['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($mentor['avatar']); ?>" alt="<?php echo htmlspecialchars($mentor['name']); ?>">
                <?php else: ?>
                    <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="mentor-details">
                <h2><?php echo htmlspecialchars($mentor['name']); ?></h2>
                <div class="mentor-meta">
                    <span><i class="bi bi-mortarboard-fill"></i> <?php echo htmlspecialchars($mentor['program_studi']); ?></span>
                    <?php if ($mentor['specialization']): ?>
                    <span><i class="bi bi-lightbulb-fill"></i> <?php echo htmlspecialchars($mentor['specialization']); ?></span>
                    <?php endif; ?>
                    <span class="mentor-rating">
                        <i class="bi bi-star-fill"></i>
                        <strong><?php echo $mentor['avg_rating']; ?></strong>
                        (<?php echo $mentor['review_count']; ?> reviews)
                    </span>
                </div>
            </div>
        </div>

        <form method="POST" action="" id="bookingForm">
            <!-- Package Selection -->
            <div class="package-section">
                <h3><i class="bi bi-box-seam"></i> Pilih Paket Konsultasi</h3>
                <div class="package-grid">
                    <?php foreach ($packages as $duration => $pkg): ?>
                    <label class="package-item">
                        <input type="radio" name="duration" value="<?php echo $duration; ?>" required>
                        <div class="package-card">
                            <?php if (!empty($pkg['popular'])): ?>
                            <span class="package-badge">Populer</span>
                            <?php endif; ?>
                            <span class="check-indicator"><i class="bi bi-check"></i></span>
                            <div class="package-header">
                                <div class="package-icon" style="background: <?php echo $pkg['color']; ?>20; color: <?php echo $pkg['color']; ?>">
                                    <i class="bi <?php echo $pkg['icon']; ?>"></i>
                                </div>
                                <span class="package-duration"><?php echo $duration; ?> menit</span>
                            </div>
                            <div class="package-name"><?php echo htmlspecialchars($pkg['name']); ?></div>
                            <div class="package-desc"><?php echo htmlspecialchars($pkg['desc']); ?></div>
                            <div class="package-price">
                                <i class="bi bi-gem"></i>
                                <?php echo number_format($pkg['gems'], 0, ',', '.'); ?>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="gems-balance">
                    <div class="gems-balance-left">
                        <i class="bi bi-gem"></i>
                        <span>Saldo Gems Kamu:</span>
                        <span class="gems-balance-value"><?php echo number_format($student_gems, 0, ',', '.'); ?></span>
                    </div>
                    <a href="<?php echo BASE_PATH; ?>/student-gems-purchase.php" class="topup-link">
                        Top Up <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Notes -->
            <div class="notes-section">
                <label for="notes">
                    <i class="bi bi-chat-text"></i>
                    Catatan untuk Mentor
                    <span>(opsional)</span>
                </label>
                <textarea 
                    id="notes" 
                    name="notes" 
                    class="notes-textarea"
                    placeholder="Jelaskan topik yang ingin kamu diskusikan, misalnya: Butuh bantuan untuk tugas struktur data tentang linked list..."
                ></textarea>
            </div>

            <!-- Actions -->
            <div class="booking-actions">
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="bi bi-calendar-check"></i>
                    Konfirmasi Booking
                </button>
                <a href="<?php echo BASE_PATH; ?>/student-mentor.php" class="btn btn-outline btn-full">
                    <i class="bi bi-arrow-left"></i>
                    Batal
                </a>
            </div>
        </form>
    </main>

    <script>
    (function() {
        // Auto-hide error
        const errorAlert = document.querySelector('.alert-error');
        if (errorAlert) {
            setTimeout(() => {
                errorAlert.style.opacity = '0';
                errorAlert.style.transform = 'translateY(-10px)';
                setTimeout(() => errorAlert.remove(), 300);
            }, 5000);
        }

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const selected = document.querySelector('input[name="duration"]:checked');
            if (!selected) {
                e.preventDefault();
                alert('Silakan pilih paket konsultasi terlebih dahulu!');
                return;
            }

            const priceEl = selected.closest('.package-item').querySelector('.package-price');
            const price = parseInt(priceEl.textContent.replace(/[^0-9]/g, ''));
            const userGems = <?php echo (int)$student_gems; ?>;

            if (price > userGems) {
                e.preventDefault();
                alert('Saldo gems tidak cukup! Anda memerlukan ' + price.toLocaleString('id-ID') + ' gems.');
            }
        });
    })();
    </script>
</body>
</html>
