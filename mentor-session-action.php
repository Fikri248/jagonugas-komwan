<?php
// mentor-session-action.php v4.0 - ADDED: Set started_at saat accept untuk timer
// Actions: accept (set started_at), reject (refund gems), complete (end session)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check - hanya mentor
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mentor') {
    header('Location: ' . BASE_PATH . '/mentor-login.php');
    exit;
}

// Harus POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/mentor-sessions.php');
    exit;
}

$mentor_id  = $_SESSION['user_id'];
$session_id = (int)($_POST['session_id'] ?? 0);
$action     = $_POST['action'] ?? '';

// Validasi input
if (!$session_id || !in_array($action, ['accept', 'reject', 'complete'], true)) {
    NotificationHelper::setError('Aksi tidak valid.');
    header('Location: ' . BASE_PATH . '/mentor-sessions.php');
    exit;
}

// Database connection
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    NotificationHelper::setError('Koneksi database gagal.');
    header('Location: ' . BASE_PATH . '/mentor-sessions.php');
    exit;
}

// Ambil data session - pastikan milik mentor ini
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ? AND mentor_id = ?");
$stmt->execute([$session_id, $mentor_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    NotificationHelper::setError('Sesi tidak ditemukan.');
    header('Location: ' . BASE_PATH . '/mentor-sessions.php');
    exit;
}

// Init notification helper
$notif      = new NotificationHelper($pdo);
$mentorName = $_SESSION['name'] ?? 'Mentor';

try {
    $pdo->beginTransaction();

    switch ($action) {
        // ===== ACCEPT: Terima booking, mulai timer =====
        case 'accept':
            if ($session['status'] !== 'pending') {
                throw new Exception('Sesi ini tidak dalam status pending.');
            }

            // Cek apakah conversation sudah ada (dibuat saat booking)
            $stmt = $pdo->prepare("SELECT id FROM conversations WHERE session_id = ? LIMIT 1");
            $stmt->execute([$session_id]);
            $conv = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Fallback: Kalau conversation belum ada (data lama sebelum auto-create)
            if (!$conv) {
                $stmt = $pdo->prepare("
                    INSERT INTO conversations (mentor_id, student_id, session_id, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$mentor_id, $session['student_id'], $session_id]);
            }

            // v4.0: SET started_at = NOW() untuk timer chat
            // Timer dihitung dari started_at + duration menit
            $stmt = $pdo->prepare("
                UPDATE sessions 
                SET status = 'ongoing', 
                    started_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$session_id]);

            // Kirim notifikasi ke student
            $notif->bookingAccepted($session['student_id'], $mentorName, $session_id);
            
            NotificationHelper::setSuccess('Sesi berhasil diterima! Timer ' . $session['duration'] . ' menit dimulai sekarang.');
            break;

        // ===== REJECT: Tolak booking, kembalikan gems =====
        case 'reject':
            if ($session['status'] !== 'pending') {
                throw new Exception('Sesi ini tidak dalam status pending.');
            }

            $reject_reason = trim($_POST['reject_reason'] ?? '');
            $reject_reason = $reject_reason !== '' ? $reject_reason : null;

            // Kembalikan gems ke student (full refund)
            $stmt = $pdo->prepare("UPDATE users SET gems = gems + ? WHERE id = ?");
            $stmt->execute([$session['price'], $session['student_id']]);

            // Update status sesi + simpan alasan
            $stmt = $pdo->prepare("
                UPDATE sessions 
                SET status = 'cancelled', 
                    reject_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$reject_reason, $session_id]);

            // Hapus conversation yang sudah dibuat saat booking
            $stmt = $pdo->prepare("DELETE FROM conversations WHERE session_id = ?");
            $stmt->execute([$session_id]);

            // Kirim notifikasi ke student
            $notif->bookingRejected($session['student_id'], $mentorName, $session_id, $reject_reason);
            
            NotificationHelper::setSuccess('Sesi ditolak. ' . number_format($session['price']) . ' gems dikembalikan ke mahasiswa.');
            break;

        // ===== COMPLETE: Selesaikan sesi =====
        case 'complete':
            // Bisa complete dari pending (edge case) atau ongoing
            if (!in_array($session['status'], ['pending', 'ongoing'], true)) {
                throw new Exception('Sesi ini tidak dapat diselesaikan.');
            }

            // Update status + set ended_at
            $stmt = $pdo->prepare("
                UPDATE sessions 
                SET status = 'completed',
                    ended_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$session_id]);

            // Optional: Transfer payment ke mentor balance (jika pakai sistem balance)
            // Uncomment jika mau implementasi mentor earnings
            /*
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$session['price'], $mentor_id]);
            */

            // Kirim notifikasi ke student
            $notif->bookingCompleted($session['student_id'], $mentorName, $session_id);
            
            NotificationHelper::setSuccess('Sesi berhasil diselesaikan! Mahasiswa dapat memberikan rating.');
            break;
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    NotificationHelper::setError($e->getMessage());
}

// Redirect back ke halaman sessions
header('Location: ' . BASE_PATH . '/mentor-sessions.php');
exit;
