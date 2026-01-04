<?php
// api-session-timer.php v1.1 - Get session timer info + auto-end
// FIXED: Removed undefined sessionAutoEnded method
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$sessionId = (int)($_GET['session_id'] ?? 0);
if (!$sessionId) {
    echo json_encode(['success' => false, 'error' => 'Session ID required']);
    exit;
}

try {
    $pdo = (new Database())->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT s.id, s.duration, s.status, s.started_at, s.ended_at,
               s.student_id, s.mentor_id, u.name as mentor_name
        FROM sessions s
        JOIN users u ON s.mentor_id = u.id
        WHERE s.id = ? AND (s.student_id = ? OR s.mentor_id = ?)
    ");
    $stmt->execute([$sessionId, $userId, $userId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Session not found']);
        exit;
    }
    
    // Kalau belum ongoing atau sudah selesai
    if ($session['status'] !== 'ongoing' || $session['ended_at']) {
        echo json_encode([
            'success' => true,
            'status' => $session['ended_at'] ? 'completed' : $session['status'],
            'remaining_seconds' => 0,
            'is_expired' => true
        ]);
        exit;
    }
    
    // Kalau started_at belum diset (fallback untuk data lama)
    if (empty($session['started_at'])) {
        echo json_encode([
            'success' => true,
            'status' => 'ongoing',
            'remaining_seconds' => $session['duration'] * 60,
            'duration_seconds' => $session['duration'] * 60,
            'is_expired' => false,
            'warning_5min' => false,
            'warning_1min' => false,
            'no_timer' => true
        ]);
        exit;
    }
    
    // Hitung remaining time
    $tz = new DateTimeZone('Asia/Jakarta');
    $startedAt = new DateTime($session['started_at'], $tz);
    $now = new DateTime('now', $tz);
    $durationSeconds = $session['duration'] * 60;
    
    $elapsedSeconds = $now->getTimestamp() - $startedAt->getTimestamp();
    $remainingSeconds = max(0, $durationSeconds - $elapsedSeconds);
    
    $isExpired = $remainingSeconds <= 0;
    
    // Auto-end jika expired (langsung update DB, tanpa notifikasi dulu)
    if ($isExpired && $session['status'] === 'ongoing') {
        $stmt = $pdo->prepare("
            UPDATE sessions 
            SET status = 'completed', ended_at = NOW(), updated_at = NOW()
            WHERE id = ? AND status = 'ongoing'
        ");
        $stmt->execute([$sessionId]);
        
        // Notifikasi opsional - cek dulu method ada atau tidak
        // Kalau mau tambah notifikasi, pastikan method ini ada di NotificationHelper.php
        /*
        if (class_exists('NotificationHelper')) {
            $notif = new NotificationHelper($pdo);
            if (method_exists($notif, 'create')) {
                $notif->create(
                    $session['student_id'],
                    'session_ended',
                    'Sesi konsultasi dengan ' . $session['mentor_name'] . ' telah berakhir.',
                    ['session_id' => $sessionId]
                );
            }
        }
        */
    }
    
    echo json_encode([
        'success' => true,
        'status' => $isExpired ? 'completed' : 'ongoing',
        'remaining_seconds' => (int)$remainingSeconds,
        'duration_seconds' => (int)$durationSeconds,
        'elapsed_seconds' => (int)$elapsedSeconds,
        'started_at' => $session['started_at'],
        'is_expired' => $isExpired,
        'warning_5min' => $remainingSeconds <= 300 && $remainingSeconds > 60,
        'warning_1min' => $remainingSeconds <= 60 && $remainingSeconds > 0
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
