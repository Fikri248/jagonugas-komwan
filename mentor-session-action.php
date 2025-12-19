<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan role mentor
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mentor') {
    header('Location: ' . BASE_PATH . '/mentor-login.php');
    exit;
}

// Hanya boleh POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/mentor-sessions.php');
    exit;
}

$mentor_id  = $_SESSION['user_id'];
$session_id = (int)($_POST['session_id'] ?? 0);
$action     = $_POST['action'] ?? '';

if (!$session_id || !in_array($action, ['accept', 'reject', 'complete'], true)) {
    NotificationHelper::setError('Aksi tidak valid.');
    header('Location: ' . BASE_PATH . '/mentor-sessions.php');
    exit;
}

// Ambil koneksi PDO
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    NotificationHelper::setError('Koneksi database gagal.');
    header('Location: ' . BASE_PATH . '/mentor-sessions.php');
    exit;
}

// Cek apakah sesi ini milik mentor
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ? AND mentor_id = ?");
$stmt->execute([$session_id, $mentor_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    NotificationHelper::setError('Sesi tidak ditemukan.');
    header('Location: ' . BASE_PATH . '/mentor-sessions.php');
    exit;
}

// Instance NotificationHelper
$notif      = new NotificationHelper($pdo);
$mentorName = $_SESSION['name'] ?? 'Mentor';

try {
    $pdo->beginTransaction();

    switch ($action) {
        case 'accept':
            if ($session['status'] !== 'pending') {
                throw new Exception('Sesi ini tidak dalam status pending.');
            }

            // Ubah status jadi ongoing
            $stmt = $pdo->prepare("UPDATE sessions SET status = 'ongoing' WHERE id = ?");
            $stmt->execute([$session_id]);

            // ===== AUTO CREATE CONVERSATION (jika belum ada) =====
            $student_id = (int)$session['student_id'];

            // Cek apakah sudah ada conversation mentor-student ini
            $stmt = $pdo->prepare("
                SELECT id 
                FROM conversations 
                WHERE mentor_id = ? AND student_id = ?
                LIMIT 1
            ");
            $stmt->execute([$mentor_id, $student_id]);
            $conv = $stmt->fetch(PDO::FETCH_ASSOC);

            // Jika belum ada, buat baru
            if (!$conv) {
                $stmt = $pdo->prepare("
                    INSERT INTO conversations (mentor_id, student_id, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$mentor_id, $student_id]);
            }
            // ===== END AUTO CREATE CONVERSATION =====

            // Notifikasi ke mahasiswa
            $notif->bookingAccepted($session['student_id'], $mentorName, $session_id);

            NotificationHelper::setSuccess('Sesi berhasil diterima dan dimulai!');
            break;

        case 'reject':
            if ($session['status'] !== 'pending') {
                throw new Exception('Sesi ini tidak dalam status pending.');
            }

            // Kembalikan gems ke student
            $stmt = $pdo->prepare("UPDATE users SET gems = gems + ? WHERE id = ?");
            $stmt->execute([$session['price'], $session['student_id']]);

            // Update status
            $stmt = $pdo->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$session_id]);

            // Notifikasi ke mahasiswa
            $notif->bookingRejected($session['student_id'], $mentorName, $session_id);

            NotificationHelper::setSuccess('Sesi ditolak. Gems dikembalikan ke mahasiswa.');
            break;

        case 'complete':
            if (!in_array($session['status'], ['pending', 'ongoing'], true)) {
                throw new Exception('Sesi ini tidak dapat diselesaikan.');
            }

            $stmt = $pdo->prepare("UPDATE sessions SET status = 'completed' WHERE id = ?");
            $stmt->execute([$session_id]);

            // Notifikasi ke mahasiswa
            $notif->bookingCompleted($session['student_id'], $mentorName, $session_id);

            NotificationHelper::setSuccess('Sesi berhasil diselesaikan!');
            break;
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    NotificationHelper::setError($e->getMessage());
}

header('Location: ' . BASE_PATH . '/mentor-sessions.php');
exit;
