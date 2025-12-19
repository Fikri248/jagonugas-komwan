<?php
/**
 * Notification Helper - JagoNugas
 * Fungsi untuk membuat notifikasi otomatis + flash message
 */

class NotificationHelper {
    private $pdo;

    // Definisi tipe notifikasi
    const TYPES = [
        'welcome' => [
            'title' => 'Selamat Datang! 🎉',
            'icon'  => 'stars',
            'color' => '#667eea'
        ],
        'new_reply' => [
            'title' => 'Jawaban Baru',
            'icon'  => 'chat-dots',
            'color' => '#3b82f6'
        ],
        'best_answer' => [
            'title' => 'Dapat 100 Gem! 💎',
            'icon'  => 'gem',
            'color' => '#8b5cf6'
        ],
        'thread_answered' => [
            'title' => 'Pertanyaan Dijawab',
            'icon'  => 'check-circle',
            'color' => '#10b981'
        ],
        'profile_updated' => [
            'title' => 'Profil Diperbarui',
            'icon'  => 'person-check',
            'color' => '#06b6d4'
        ],
        'gem_received' => [
            'title' => 'Gem Diterima! 💎',
            'icon'  => 'gem',
            'color' => '#8b5cf6'
        ],
        'thread_created' => [
            'title' => 'Pertanyaan Dibuat',
            'icon'  => 'plus-circle',
            'color' => '#10b981'
        ],
        'reply_received' => [
            'title' => 'Balasan Baru',
            'icon'  => 'reply',
            'color' => '#f59e0b'
        ],
        'upvote_received' => [
            'title' => 'Upvote Diterima! 👍',
            'icon'  => 'hand-thumbs-up',
            'color' => '#10b981'
        ],
        'gem_bonus' => [
            'title' => 'Bonus Gem! 🎁',
            'icon'  => 'gift-fill',
            'color' => '#f59e0b'
        ],

        // ====== Booking Mentor/Student ======
        'booking_created' => [
            'title' => 'Booking Baru',
            'icon'  => 'calendar-check',
            'color' => '#3b82f6'
        ],
        'booking_accepted' => [
            'title' => 'Booking Diterima',
            'icon'  => 'check-circle',
            'color' => '#10b981'
        ],
        'booking_rejected' => [
            'title' => 'Booking Ditolak',
            'icon'  => 'x-circle',
            'color' => '#ef4444'
        ],
        'booking_completed' => [
            'title' => 'Sesi Selesai',
            'icon'  => 'check-square',
            'color' => '#0ea5e9'
        ],
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Insert ke tabel notifications
     */
    public function create($userId, $type, $message, $relatedId = null, $relatedType = null) {
        try {
            $typeData = self::TYPES[$type] ?? [
                'title' => 'Notifikasi',
                'icon'  => 'bell',
                'color' => '#667eea'
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    type,
                    title,
                    message,
                    icon,
                    color,
                    related_id,
                    related_type,
                    is_read,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");

            return $stmt->execute([
                $userId,
                $type,
                $typeData['title'],
                $message,
                $typeData['icon'],
                $typeData['color'],
                $relatedId,
                $relatedType
            ]);
        } catch (Exception $e) {
            error_log('Notification Error: ' . $e->getMessage());
            return false;
        }
    }

    // ====== QUERY NOTIFIKASI (untuk dashboard / navbar) ======

    /**
     * Hitung jumlah notifikasi belum dibaca untuk user tertentu.
     */
    public function getUnreadCount($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Ambil list notifikasi terbaru untuk user.
     */
    public function getLatest($userId, $limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT id, title, message, type, icon, color, is_read, created_at, related_type, related_id
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tandai semua notifikasi user sebagai sudah dibaca.
     */
    public function markAllRead($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
        ");
        return $stmt->execute([$userId]);
    }

    // ====== NOTIFIKASI LAMA (FORUM, PROFIL, GEM) ======

    public function welcome($userId) {
        return $this->create(
            $userId,
            'welcome',
            'Halo! Selamat bergabung di JagoNugas.'
        );
    }

    public function newReplyToThread($threadOwnerId, $replierName, $threadId, $threadTitle) {
        return $this->create(
            $threadOwnerId,
            'new_reply',
            $replierName . ' menjawab pertanyaan kamu: "' . mb_substr($threadTitle, 0, 50) . '..."',
            $threadId,
            'thread'
        );
    }

    public function bestAnswer($userId, $gemAmount, $threadId) {
        return $this->create(
            $userId,
            'best_answer',
            'Jawaban kamu dipilih sebagai yang terbaik! +' . $gemAmount . ' Gem',
            $threadId,
            'thread'
        );
    }

    public function replyToReply($originalReplierId, $replierName, $threadId) {
        return $this->create(
            $originalReplierId,
            'reply_received',
            $replierName . ' membalas komentar kamu.',
            $threadId,
            'thread'
        );
    }

    public function profileUpdated($userId, $what = 'profil') {
        return $this->create(
            $userId,
            'profile_updated',
            ucfirst($what) . ' berhasil diperbarui.'
        );
    }

    public function threadCreated($userId, $threadId, $threadTitle) {
        return $this->create(
            $userId,
            'thread_created',
            'Pertanyaan "' . mb_substr($threadTitle, 0, 50) . '..." berhasil dibuat.',
            $threadId,
            'thread'
        );
    }

    public function gemReceived($userId, $amount, $reason) {
        return $this->create(
            $userId,
            'gem_received',
            'Kamu mendapat +' . $amount . ' Gem dari ' . $reason
        );
    }

    // ====== NOTIFIKASI BARU (BOOKING MENTOR/STUDENT) ======

    // Saat mahasiswa membuat booking baru → kirim ke mentor
    public function bookingCreated($mentorId, $studentName, $sessionId) {
        return $this->create(
            $mentorId,
            'booking_created',
            $studentName . ' membuat booking sesi baru dengan Anda.',
            $sessionId,
            'session'
        );
    }

    // Saat mentor menerima booking → kirim ke mahasiswa
    public function bookingAccepted($studentId, $mentorName, $sessionId) {
        return $this->create(
            $studentId,
            'booking_accepted',
            'Booking kamu diterima oleh ' . $mentorName . '.',
            $sessionId,
            'session'
        );
    }

    // Saat mentor menolak booking → kirim ke mahasiswa
    public function bookingRejected($studentId, $mentorName, $sessionId) {
        return $this->create(
            $studentId,
            'booking_rejected',
            'Booking kamu ditolak oleh ' . $mentorName . '. Gems telah dikembalikan.',
            $sessionId,
            'session'
        );
    }

    // Saat sesi selesai → kirim ke mahasiswa
    public function bookingCompleted($studentId, $mentorName, $sessionId) {
        return $this->create(
            $studentId,
            'booking_completed',
            'Sesi dengan ' . $mentorName . ' telah selesai. Jangan lupa beri rating ya!',
            $sessionId,
            'session'
        );
    }

    // ====== FLASH MESSAGE (SESSION) ======

    protected static function ensureSessionStarted() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function setSuccess($message) {
        self::ensureSessionStarted();
        $_SESSION['flash_success'] = $message;
    }

    public static function getSuccess() {
        self::ensureSessionStarted();
        if (!empty($_SESSION['flash_success'])) {
            $msg = $_SESSION['flash_success'];
            unset($_SESSION['flash_success']);
            return $msg;
        }
        return null;
    }

    public static function setError($message) {
        self::ensureSessionStarted();
        $_SESSION['flash_error'] = $message;
    }

    public static function getError() {
        self::ensureSessionStarted();
        if (!empty($_SESSION['flash_error'])) {
            $msg = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
            return $msg;
        }
        return null;
    }
}

/**
 * Helper function global
 */
function getNotificationHelper($pdo) {
    return new NotificationHelper($pdo);
}
