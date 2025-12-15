<?php
// ModelDiskusi.php

class Diskusi {
    private $conn;
    private $table = 'diskusi';

    // Properties
    public $id;
    public $user_id;
    public $judul;
    public $pertanyaan;
    public $image_path;
    public $created_at;

    public function __construct($db = null) {
        if ($db) {
            $this->conn = $db;
        } else {
            global $pdo;
            $this->conn = $pdo;
        }
    }

    /**
     * Create new diskusi
     */
    public function create() {
        $query = "INSERT INTO {$this->table} 
                  (user_id, judul, pertanyaan, image_path) 
                  VALUES (:user_id, :judul, :pertanyaan, :image_path)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $this->judul = htmlspecialchars(strip_tags($this->judul));
        $this->pertanyaan = htmlspecialchars(strip_tags($this->pertanyaan));
        
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':judul', $this->judul);
        $stmt->bindParam(':pertanyaan', $this->pertanyaan);
        $stmt->bindParam(':image_path', $this->image_path);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return ['success' => true, 'id' => $this->id];
        }
        return ['success' => false, 'message' => 'Gagal membuat diskusi'];
    }

    /**
     * Get all diskusi with user info
     */
    public function getAll($limit = null, $offset = 0) {
        $query = "SELECT d.*, u.name, u.avatar, u.program_studi,
                  (SELECT COUNT(*) FROM diskusi_replies WHERE diskusi_id = d.id) as reply_count,
                  (SELECT COUNT(*) FROM diskusi_upvotes WHERE diskusi_id = d.id) as upvote_count
                  FROM {$this->table} d 
                  INNER JOIN users u ON d.user_id = u.id 
                  ORDER BY d.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->conn->prepare($query);
        
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single diskusi by ID
     */
    public function getById($id) {
        $query = "SELECT d.*, u.name, u.avatar, u.program_studi,
                  (SELECT COUNT(*) FROM diskusi_replies WHERE diskusi_id = d.id) as reply_count,
                  (SELECT COUNT(*) FROM diskusi_upvotes WHERE diskusi_id = d.id) as upvote_count
                  FROM {$this->table} d 
                  INNER JOIN users u ON d.user_id = u.id 
                  WHERE d.id = :id 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get diskusi by user
     */
    public function getByUser($userId, $limit = 10) {
        $query = "SELECT d.*, 
                  (SELECT COUNT(*) FROM diskusi_replies WHERE diskusi_id = d.id) as reply_count,
                  (SELECT COUNT(*) FROM diskusi_upvotes WHERE diskusi_id = d.id) as upvote_count
                  FROM {$this->table} d 
                  WHERE d.user_id = :user_id 
                  ORDER BY d.created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update diskusi
     */
    public function update() {
        $query = "UPDATE {$this->table} 
                  SET judul = :judul, pertanyaan = :pertanyaan, image_path = :image_path 
                  WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        
        $this->judul = htmlspecialchars(strip_tags($this->judul));
        $this->pertanyaan = htmlspecialchars(strip_tags($this->pertanyaan));
        
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':judul', $this->judul);
        $stmt->bindParam(':pertanyaan', $this->pertanyaan);
        $stmt->bindParam(':image_path', $this->image_path);
        
        return $stmt->execute();
    }

    /**
     * Delete diskusi
     */
    public function delete($id, $userId) {
        // Ambil image path dulu untuk dihapus
        $diskusi = $this->getById($id);
        
        $query = "DELETE FROM {$this->table} WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            // Hapus file gambar jika ada
            if ($diskusi && $diskusi['image_path']) {
                $filePath = __DIR__ . '/' . $diskusi['image_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Gagal menghapus diskusi'];
    }

    /**
     * Search diskusi
     */
    public function search($keyword, $limit = 20) {
        $query = "SELECT d.*, u.name, u.avatar,
                  (SELECT COUNT(*) FROM diskusi_replies WHERE diskusi_id = d.id) as reply_count
                  FROM {$this->table} d 
                  INNER JOIN users u ON d.user_id = u.id 
                  WHERE d.judul LIKE :keyword OR d.pertanyaan LIKE :keyword
                  ORDER BY d.created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $searchTerm = '%' . $keyword . '%';
        $stmt->bindParam(':keyword', $searchTerm);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total diskusi
     */
    public function countAll() {
        $query = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }

    /**
     * Upvote diskusi
     */
    public function upvote($diskusiId, $userId) {
        // Cek sudah upvote atau belum
        $checkQuery = "SELECT id FROM diskusi_upvotes WHERE diskusi_id = :diskusi_id AND user_id = :user_id";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':diskusi_id', $diskusiId, PDO::PARAM_INT);
        $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            // Sudah upvote, hapus (toggle off)
            $deleteQuery = "DELETE FROM diskusi_upvotes WHERE diskusi_id = :diskusi_id AND user_id = :user_id";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->bindParam(':diskusi_id', $diskusiId, PDO::PARAM_INT);
            $deleteStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $deleteStmt->execute();
            return ['success' => true, 'action' => 'removed'];
        } else {
            // Belum upvote, tambah
            $insertQuery = "INSERT INTO diskusi_upvotes (diskusi_id, user_id) VALUES (:diskusi_id, :user_id)";
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->bindParam(':diskusi_id', $diskusiId, PDO::PARAM_INT);
            $insertStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $insertStmt->execute();
            return ['success' => true, 'action' => 'added'];
        }
    }

    /**
     * Check if user has upvoted
     */
    public function hasUpvoted($diskusiId, $userId) {
        $query = "SELECT id FROM diskusi_upvotes WHERE diskusi_id = :diskusi_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':diskusi_id', $diskusiId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
