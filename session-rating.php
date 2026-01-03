<?php
// session-rating.php - Rating sesi setelah selesai
// INLINE CSS (tanpa import style.css) + INCLUDE student-navbar.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}

$student_id = $_SESSION['user_id'];

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed.');
}

$notif = new NotificationHelper($pdo);

// Ambil session_id dari GET / POST
$session_id = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? 0);
if (!$session_id) {
    NotificationHelper::setError('Sesi tidak valid.');
    header('Location: ' . BASE_PATH . '/student-sessions.php');
    exit;
}

// Ambil data sesi
$stmt = $pdo->prepare("
    SELECT s.*, u.name AS mentor_name, u.avatar AS mentor_avatar
    FROM sessions s
    JOIN users u ON s.mentor_id = u.id
    WHERE s.id = ? AND s.student_id = ?
");
$stmt->execute([$session_id, $student_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    NotificationHelper::setError('Sesi tidak ditemukan.');
    header('Location: ' . BASE_PATH . '/student-sessions.php');
    exit;
}

if ($session['status'] !== 'completed') {
    NotificationHelper::setError('Sesi ini belum selesai, tidak bisa diberi rating.');
    header('Location: ' . BASE_PATH . '/student-sessions.php');
    exit;
}

if (!empty($session['rating'])) {
    NotificationHelper::setError('Sesi ini sudah memiliki rating.');
    header('Location: ' . BASE_PATH . '/student-sessions.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $review = trim($_POST['review'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Pilih rating antara 1 sampai 5 bintang.';
    }

    if (strlen($review) < 5) {
        $errors[] = 'Review minimal 5 karakter.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Simpan rating & review
            $stmt = $pdo->prepare("UPDATE sessions SET rating = ?, review = ? WHERE id = ?");
            $stmt->execute([$rating, $review, $session_id]);

            // Update aggregate rating mentor
            $stmt = $pdo->prepare("
                UPDATE users
                SET total_rating = total_rating + ?,
                    review_count = review_count + 1
                WHERE id = ?
            ");
            $stmt->execute([$rating, $session['mentor_id']]);

            // Notifikasi ke mentor
            $studentName = $_SESSION['name'] ?? 'Mahasiswa';
            $notif->bookingCompleted($session['mentor_id'], $studentName, $session_id);

            $pdo->commit();

            NotificationHelper::setSuccess('Terima kasih! Rating dan review berhasil dikirim.');
            header('Location: ' . BASE_PATH . '/student-sessions.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Terjadi kesalahan saat menyimpan rating.';
        }
    }
}

// Helper untuk avatar
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar, $base = '') {
        if (empty($avatar)) return '';
        if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
        return $base . '/' . ltrim($avatar, '/');
    }
}

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beri Rating Sesi - JagoNugas</title>
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
        .container { max-width: 1280px; margin: 0 auto; padding: 0 40px; }

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
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a67d8;
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

        /* ===== ALERTS ===== */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        /* ===== RATING PAGE ===== */
        .rating-page-container {
            max-width: 640px;
            margin: 2.5rem auto;
            padding: 1.5rem;
        }
        .rating-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 50px rgba(15,23,42,0.12);
            border: 1px solid #e2e8f0;
        }
        .rating-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .rating-mentor-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.5rem;
            color: #fff;
            background: linear-gradient(135deg, #667eea, #764ba2);
            box-shadow: 0 10px 24px rgba(102, 126, 234, 0.35);
            overflow: hidden;
            flex-shrink: 0;
        }
        .rating-mentor-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .rating-mentor-info { flex: 1; }
        .rating-mentor-label { font-size: 0.8rem; color: #94a3b8; margin-bottom: 4px; }
        .rating-mentor-name { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0; }
        .rating-session-date { font-size: 0.85rem; color: #64748b; }

        /* Rating Stars */
        .rating-section { margin-bottom: 1.5rem; }
        .rating-label { font-weight: 600; font-size: 0.95rem; color: #1e293b; margin-bottom: 4px; }
        .rating-hint { font-size: 0.8rem; color: #94a3b8; margin-bottom: 12px; }
        .rating-stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 8px;
        }
        .rating-stars input { display: none; }
        .rating-stars label {
            font-size: 2.5rem;
            color: #e2e8f0;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .rating-stars label:hover,
        .rating-stars label:hover ~ label,
        .rating-stars input:checked ~ label {
            color: #fbbf24;
            transform: scale(1.1);
        }

        /* Textarea */
        .rating-textarea {
            width: 100%;
            min-height: 120px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 14px 16px;
            font-size: 0.95rem;
            font-family: inherit;
            resize: vertical;
            outline: none;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .rating-textarea:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }
        .rating-textarea::placeholder { color: #94a3b8; }

        /* Buttons */
        .rating-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border-radius: 12px;
            padding: 12px 24px;
            border: none;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-secondary:hover { background: #e2e8f0; color: #1e293b; }
        .btn-primary-solid {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-radius: 12px;
            padding: 12px 28px;
            border: none;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-primary-solid:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(102, 126, 234, 0.4);
        }

        /* Error Box */
        .rating-error {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
        }
        .rating-error strong { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .rating-error ul { margin: 0; padding-left: 20px; }
        .rating-error li { margin-bottom: 4px; }

        /* Responsive */
        @media (max-width: 768px) {
            .rating-page-container { padding: 1rem; margin: 1rem auto; }
            .rating-card { padding: 1.5rem; }
            .rating-header { flex-direction: column; text-align: center; }
            .rating-stars label { font-size: 2rem; }
        }
        @media (max-width: 480px) {
            .rating-actions { flex-direction: column; }
            .rating-actions a, .rating-actions button { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/student-navbar.php'; ?>

<!-- ========== RATING CONTENT ========== -->
<div class="rating-page-container">
    <div class="rating-card">
        <div class="rating-header">
            <?php 
            $mentorAvatarUrl = get_avatar_url($session['mentor_avatar'] ?? '', $BASE);
            ?>
            <div class="rating-mentor-avatar">
                <?php if ($mentorAvatarUrl): ?>
                    <img src="<?= htmlspecialchars($mentorAvatarUrl) ?>" alt="Mentor" referrerpolicy="no-referrer">
                <?php else: ?>
                    <?= strtoupper(substr($session['mentor_name'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="rating-mentor-info">
                <div class="rating-mentor-label">Beri Rating untuk Mentor</div>
                <h2 class="rating-mentor-name"><?= htmlspecialchars($session['mentor_name']) ?></h2>
                <div class="rating-session-date">
                    <i class="bi bi-calendar3"></i>
                    Sesi tanggal <?= date('d M Y, H:i', strtotime($session['created_at'])) ?> WIB
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="rating-error">
                <strong><i class="bi bi-exclamation-triangle"></i> Terjadi kesalahan:</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="session_id" value="<?= $session_id ?>">

            <div class="rating-section">
                <div class="rating-label">Pilih Rating</div>
                <div class="rating-hint">1 = sangat buruk, 5 = sangat puas</div>
                <div class="rating-stars">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>">
                        <label for="star<?= $i ?>"><i class="bi bi-star-fill"></i></label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="rating-section">
                <label for="review" class="rating-label">Tulis Review</label>
                <div class="rating-hint">Ceritakan pengalamanmu dengan sesi mentoring ini</div>
                <textarea id="review" name="review" class="rating-textarea" placeholder="Bagaimana pengalaman belajarmu dengan mentor ini? Apa yang paling membantu?"></textarea>
            </div>

            <div class="rating-actions">
                <a href="<?= BASE_PATH ?>/student-sessions.php" class="btn-secondary">
                    <i class="bi bi-arrow-left"></i> Batal
                </a>
                <button type="submit" class="btn-primary-solid">
                    <i class="bi bi-send"></i> Kirim Rating
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
