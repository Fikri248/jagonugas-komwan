<?php
// student-session-cancel.php
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

// Pastikan method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/student-sessions.php');
    exit;
}

// Ambil session_id dari form
$session_id = (int)($_POST['session_id'] ?? 0);
if (!$session_id) {
    NotificationHelper::setError('Data sesi tidak valid.');
    header('Location: ' . BASE_PATH . '/student-sessions.php');
    exit;
}

// Koneksi PDO
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    NotificationHelper::setError('Koneksi database gagal.');
    header('Location: ' . BASE_PATH . '/student-sessions.php');
    exit;
}

// Ambil data sesi, pastikan milik student ini
$stmt = $pdo->prepare("
    SELECT s.*, u.name AS mentor_name
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

// Hanya boleh batalkan jika masih pending
if ($session['status'] !== 'pending') {
    NotificationHelper::setError('Sesi ini tidak bisa dibatalkan.');
    header('Location: ' . BASE_PATH . '/student-sessions.php');
    exit;
}

$notif = new NotificationHelper($pdo);

try {
    $pdo->beginTransaction();

    // Kembalikan gems ke student
    $stmt = $pdo->prepare("UPDATE users SET gems = gems + ? WHERE id = ?");
    $stmt->execute([$session['price'], $student_id]);

    // Update status sesi menjadi cancelled
    $stmt = $pdo->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$session_id]);

    // (Opsional) kirim notifikasi ke mentor bahwa student membatalkan
    // Jika mau tipe khusus, tambahkan di NotificationHelper (mis. booking_cancelled_by_student)
    $notif->bookingRejected($session['mentor_id'], $_SESSION['name'] ?? 'Mahasiswa', $session_id);

    $pdo->commit();

    NotificationHelper::setSuccess('Sesi berhasil dibatalkan dan gems sudah dikembalikan.');
} catch (Exception $e) {
    $pdo->rollBack();
    NotificationHelper::setError('Gagal membatalkan sesi: ' . $e->getMessage());
}

header('Location: ' . BASE_PATH . '/student-sessions.php');
exit;
