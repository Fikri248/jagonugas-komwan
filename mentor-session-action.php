<?php
// mentor-session-action.php v3.1 - FIXED: Conversation sudah dibuat di booking, jangan INSERT lagi
// Actions: accept (update status only), reject (delete conversation + refund), complete (set ended_at)

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

            // ===== v3.1: JANGAN INSERT CONVERSATION LAGI =====
            // Conversation sudah dibuat otomatis di book-session.php v3.0
            // Kita cuma perlu UPDATE status session ke ongoing
            
            // Validasi conversation sudah exist (untuk keamanan)
            $stmt = $pdo->prepare("
                SELECT id FROM conversations 
                WHERE session_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$session_id]);
            $conv = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$conv) {
                // Fallback: Kalau conversation belum ada (data lama sebelum v3.0)
                // Baru bikin conversation
                $stmt = $pdo->prepare("
                    INSERT INTO conversations (mentor_id, student_id, session_id, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$mentor_id, $session['student_id'], $session_id]);
            }
            // ===== END v3.1 FIX =====

            // Ubah status session jadi ongoing
            $stmt = $pdo->prepare("UPDATE sessions SET status = 'ongoing' WHERE id = ?");
            $stmt->execute([$session_id]);

            // Notifikasi ke mahasiswa
            $notif->bookingAccepted($session['student_id'], $mentorName, $session_id);

            NotificationHelper::setSuccess('Sesi berhasil diterima! Chat dengan mahasiswa sekarang.');
            break;

        case 'reject':
            if ($session['status'] !== 'pending') {
                throw new Exception('Sesi ini tidak dalam status pending.');
            }

            // Ambil reject_reason dari form
            $reject_reason = trim($_POST['reject_reason'] ?? '');
            $reject_reason = $reject_reason !== '' ? $reject_reason : null;

            // Kembalikan gems ke student
            $stmt = $pdo->prepare("UPDATE users SET gems = gems + ? WHERE id = ?");
            $stmt->execute([$session['price'], $session['student_id']]);

            // Update status + reject_reason
            $stmt = $pdo->prepare("
                UPDATE sessions 
                SET status = 'cancelled', 
                    reject_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$reject_reason, $session_id]);

            // Hapus conversation kalau session ditolak (opsional, tergantung bisnis logic)
            // Kalau mau keep history, comment line ini
            $stmt = $pdo->prepare("DELETE FROM conversations WHERE session_id = ?");
            $stmt->execute([$session_id]);

            // Notifikasi ke mahasiswa
            $notif->bookingRejected($session['student_id'], $mentorName, $session_id, $reject_reason);

            NotificationHelper::setSuccess('Sesi ditolak. Gems dikembalikan ke mahasiswa.');
            break;

        case 'complete':
            if (!in_array($session['status'], ['pending', 'ongoing'], true)) {
                throw new Exception('Sesi ini tidak dapat diselesaikan.');
            }

            // Update status + set ended_at = NOW()
            $stmt = $pdo->prepare("
                UPDATE sessions 
                SET status = 'completed',
                    ended_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$session_id]);

            // Transfer payment ke mentor
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$session['price'], $mentor_id]);

            // Notifikasi ke mahasiswa
            $notif->bookingCompleted($session['student_id'], $mentorName, $session_id);

            NotificationHelper::setSuccess('Sesi selesai! Pembayaran telah ditransfer.');
            break;
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    NotificationHelper::setError($e->getMessage());
}

header('Location: ' . BASE_PATH . '/mentor-sessions.php');
exit;
