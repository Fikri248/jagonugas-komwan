<?php
/**
 * Notification Helper - JagoNugas
 * Fungsi untuk membuat notifikasi otomatis
 */

class NotificationHelper {
    private $pdo;
    
    // Definisi tipe notifikasi
    const TYPES = [
        'welcome' => [
            'title' => 'Selamat Datang! 🎉',
            'icon' => 'stars',
            'color' => '#667eea'
        ],
        'new_reply' => [
            'title' => 'Jawaban Baru',
            'icon' => 'chat-dots',
            'color' => '#3b82f6'
        ],
        'best_answer' => [
            'title' => 'Dapat 100 Gem! 💎',
            'icon' => 'gem',
            'color' => '#8b5cf6'
        ],
        'thread_answered' => [
            'title' => 'Pertanyaan Dijawab',
            'icon' => 'check-circle',
            'color' => '#10b981'
        ],
        'profile_updated' => [
            'title' => 'Profil Diperbarui',
            'icon' => 'person-check',
            'color' => '#06b6d4'
        ],
        'gem_received' => [
            'title' => 'Gem Diterima! 💎',
            'icon' => 'gem',
            'color' => '#8b5cf6'
        ],
        'thread_created' => [
            'title' => 'Pertanyaan Dibuat',
            'icon' => 'plus-circle',
            'color' => '#10b981'
        ],
        'reply_received' => [
            'title' => 'Balasan Baru',
            'icon' => 'reply',
            'color' => '#f59e0b'
        ],
        'upvote_received' => [
            'title' => 'Upvote Diterima! 👍',
            'icon' => 'hand-thumbs-up',
            'color' => '#10b981'
        ],
        'gem_bonus' => [
            'title' => 'Bonus Gem! 🎁',
            'icon' => 'gift-fill',
            'color' => '#f59e0b'
        ]
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($userId, $type, $message, $relatedId = null, $relatedType = null) {
        try {
            $typeData = self::TYPES[$type] ?? [
                'title' => 'Notifikasi',
                'icon' => 'bell',
                'color' => '#667eea'
            ];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, icon, color, related_id, related_type, is_read, created_at)
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
            error_log("Notification Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function welcome($userId) {
        return $this->create($userId, 'welcome', 'Halo! Selamat bergabung di JagoNugas.');
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
        return $this->create($userId, 'profile_updated', ucfirst($what) . ' berhasil diperbarui.');
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
        return $this->create($userId, 'gem_received', 'Kamu mendapat +' . $amount . ' Gem dari ' . $reason);
    }
}

function getNotificationHelper($pdo) {
    return new NotificationHelper($pdo);
}
