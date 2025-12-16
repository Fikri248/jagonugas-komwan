<?php
// ModelDiskusi.php

class Diskusi
{
    private $conn;
    private $table = 'diskusi';

    // Detected column names for compatibility (snake_case vs no underscore)
    private $colUserId   = 'user_id';
    private $colImage    = 'image_path';
    private $colCreated  = 'created_at';

    // Users table program studi column
    private $colUserProgram = 'program_studi';

    // Properties
    public $id;
    public $user_id;     // preferred
    public $userid;      // legacy alias (not used for writes)
    public $judul;
    public $pertanyaan;
    public $image_path;  // preferred
    public $imagepath;   // legacy alias (not used for writes)
    public $created_at;  // preferred
    public $createdat;   // legacy alias (not used for writes)

    public function __construct($db = null)
    {
        if ($db) {
            $this->conn = $db;
        } else {
            global $pdo;
            $this->conn = $pdo;
        }

        $this->detectSchema();
    }

    private function detectSchema(): void
    {
        try {
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM {$this->table}");
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            // Diskusi columns
            $this->colUserId  = in_array('user_id', $cols, true) ? 'user_id' : (in_array('userid', $cols, true) ? 'userid' : 'user_id');
            $this->colImage   = in_array('image_path', $cols, true) ? 'image_path' : (in_array('imagepath', $cols, true) ? 'imagepath' : 'image_path');
            $this->colCreated = in_array('created_at', $cols, true) ? 'created_at' : (in_array('createdat', $cols, true) ? 'createdat' : 'created_at');

            // Users.program_studi vs users.programstudi
            $stmt2 = $this->conn->prepare("SHOW COLUMNS FROM users");
            $stmt2->execute();
            $userCols = $stmt2->fetchAll(PDO::FETCH_COLUMN, 0);
            $this->colUserProgram = in_array('program_studi', $userCols, true) ? 'program_studi' : (in_array('programstudi', $userCols, true) ? 'programstudi' : 'program_studi');
        } catch (\Throwable $e) {
            // Keep defaults if schema probing fails
        }
    }

    private function sanitizeText($value): string
    {
        return htmlspecialchars(strip_tags((string)$value));
    }

    /**
     * Create new diskusi
     */
    public function create(): array
    {
        $query = "INSERT INTO {$this->table}
                  ({$this->colUserId}, judul, pertanyaan, {$this->colImage})
                  VALUES (:user_id, :judul, :pertanyaan, :image_path)";

        $stmt = $this->conn->prepare($query);

        $judul = $this->sanitizeText($this->judul);
        $pertanyaan = $this->sanitizeText($this->pertanyaan);
        $imagePath = (string)$this->image_path;

        $userId = (int)($this->user_id ?: $this->userid);

        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':judul', $judul);
        $stmt->bindParam(':pertanyaan', $pertanyaan);
        $stmt->bindParam(':image_path', $imagePath);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return ['success' => true, 'id' => $this->id];
        }
        return ['success' => false, 'message' => 'Gagal membuat diskusi'];
    }

    /**
     * Get all diskusi with user info (+ counts if tables exist)
     */
    public function getAll($limit = null, $offset = 0): array
    {
        $baseSelect = "
            SELECT
              d.id,
              IFNULL(d.user_id, d.userid)        AS user_id,
              d.judul,
              d.pertanyaan,
              IFNULL(d.image_path, d.imagepath)  AS image_path,
              IFNULL(d.created_at, d.createdat)  AS created_at,
              u.name,
              u.avatar,
              IFNULL(u.program_studi, u.programstudi) AS program_studi
        ";

        $fromJoin = "
            FROM {$this->table} d
            INNER JOIN users u ON u.id = IFNULL(d.user_id, d.userid)
        ";

        $order = " ORDER BY IFNULL(d.created_at, d.createdat) DESC ";

        $queryWithCounts = $baseSelect . ",
              (SELECT COUNT(*) FROM diskusi_replies r WHERE r.diskusi_id = d.id)   AS reply_count,
              (SELECT COUNT(*) FROM diskusi_upvotes v WHERE v.diskusi_id = d.id)   AS upvote_count
        " . $fromJoin . $order;

        $queryFallback = $baseSelect . ",
              0 AS reply_count,
              0 AS upvote_count
        " . $fromJoin . $order;

        $useQuery = $queryWithCounts;
        $stmt = null;

        try {
            if ($limit !== null) {
                $useQuery .= " LIMIT :limit OFFSET :offset";
            }
            $stmt = $this->conn->prepare($useQuery);
            if ($limit !== null) {
                $limit = (int)$limit;
                $offset = (int)$offset;
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            }
            $stmt->execute();
        } catch (\Throwable $e) {
            // Fallback if count tables don't exist
            $useQuery = $queryFallback . ($limit !== null ? " LIMIT :limit OFFSET :offset" : "");
            $stmt = $this->conn->prepare($useQuery);
            if ($limit !== null) {
                $limit = (int)$limit;
                $offset = (int)$offset;
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            }
            $stmt->execute();
        }

        return (array)$stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single diskusi by ID
     */
    public function getById($id)
    {
        $id = (int)$id;

        $baseSelect = "
            SELECT
              d.id,
              IFNULL(d.user_id, d.userid)        AS user_id,
              d.judul,
              d.pertanyaan,
              IFNULL(d.image_path, d.imagepath)  AS image_path,
              IFNULL(d.created_at, d.createdat)  AS created_at,
              u.name,
              u.avatar,
              IFNULL(u.program_studi, u.programstudi) AS program_studi
        ";
        $fromJoinWhere = "
            FROM {$this->table} d
            INNER JOIN users u ON u.id = IFNULL(d.user_id, d.userid)
            WHERE d.id = :id
            LIMIT 1
        ";

        $queryWithCounts = $baseSelect . ",
              (SELECT COUNT(*) FROM diskusi_replies r WHERE r.diskusi_id = d.id)   AS reply_count,
              (SELECT COUNT(*) FROM diskusi_upvotes v WHERE v.diskusi_id = d.id)   AS upvote_count
        " . $fromJoinWhere;

        $queryFallback = $baseSelect . ",
              0 AS reply_count,
              0 AS upvote_count
        " . $fromJoinWhere;

        try {
            $stmt = $this->conn->prepare($queryWithCounts);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            $stmt = $this->conn->prepare($queryFallback);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        }

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get diskusi by user
     */
    public function getByUser($userId, $limit = 10): array
    {
        $userId = (int)$userId;
        $limit = (int)$limit;

        $query = "SELECT
                    d.id,
                    IFNULL(d.user_id, d.userid)        AS user_id,
                    d.judul,
                    d.pertanyaan,
                    IFNULL(d.image_path, d.imagepath)  AS image_path,
                    IFNULL(d.created_at, d.createdat)  AS created_at,
                    (SELECT COUNT(*) FROM diskusi_replies r WHERE r.diskusi_id = d.id) AS reply_count,
                    (SELECT COUNT(*) FROM diskusi_upvotes v WHERE v.diskusi_id = d.id) AS upvote_count
                  FROM {$this->table} d
                  WHERE {$this->colUserId} = :user_id
                  ORDER BY {$this->colCreated} DESC
                  LIMIT :limit";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Fallback without counts
            $query2 = "SELECT
                         d.id,
                         IFNULL(d.user_id, d.userid)       AS user_id,
                         d.judul,
                         d.pertanyaan,
                         IFNULL(d.image_path, d.imagepath) AS image_path,
                         IFNULL(d.created_at, d.createdat) AS created_at,
                         0 AS reply_count,
                         0 AS upvote_count
                       FROM {$this->table} d
                       WHERE {$this->colUserId} = :user_id
                       ORDER BY {$this->colCreated} DESC
                       LIMIT :limit";
            $stmt = $this->conn->prepare($query2);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        }

        return (array)$stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update diskusi
     */
    public function update(): bool
    {
        $query = "UPDATE {$this->table}
                  SET judul = :judul, pertanyaan = :pertanyaan, {$this->colImage} = :image_path
                  WHERE id = :id AND {$this->colUserId} = :user_id";

        $stmt = $this->conn->prepare($query);

        $id = (int)$this->id;
        $userId = (int)($this->user_id ?: $this->userid);
        $judul = $this->sanitizeText($this->judul);
        $pertanyaan = $this->sanitizeText($this->pertanyaan);
        $imagePath = (string)$this->image_path;

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':judul', $judul);
        $stmt->bindParam(':pertanyaan', $pertanyaan);
        $stmt->bindParam(':image_path', $imagePath);

        return (bool)$stmt->execute();
    }

    /**
     * Delete diskusi
     */
    public function delete($id, $userId): array
    {
        $id = (int)$id;
        $userId = (int)$userId;

        // Ambil record untuk hapus file setelah delete sukses
        $diskusi = $this->getById($id);

        $query = "DELETE FROM {$this->table} WHERE id = :id AND {$this->colUserId} = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            if ($diskusi && !empty($diskusi['image_path'])) {
                $fileRel = $diskusi['image_path'];
                // Jika path relatif, prefiks dengan root project
                if (strpos($fileRel, DIRECTORY_SEPARATOR) !== 0 && !preg_match('/^[A-Za-z]:\\\\/', $fileRel)) {
                    $baseDir = dirname(__DIR__); // sesuaikan jika Model ada di root
                    $filePath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($fileRel, '/\\');
                } else {
                    $filePath = $fileRel;
                }
                if (is_file($filePath) && file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Gagal menghapus diskusi'];
    }

    /**
     * Search diskusi
     */
    public function search($keyword, $limit = 20): array
    {
        $limit = (int)$limit;
        $q = "SELECT
                d.id,
                IFNULL(d.user_id, d.userid)        AS user_id,
                d.judul,
                d.pertanyaan,
                IFNULL(d.image_path, d.imagepath)  AS image_path,
                IFNULL(d.created_at, d.createdat)  AS created_at,
                u.name,
                u.avatar
              FROM {$this->table} d
              INNER JOIN users u ON u.id = IFNULL(d.user_id, d.userid)
              WHERE d.judul LIKE :kw OR d.pertanyaan LIKE :kw
              ORDER BY IFNULL(d.created_at, d.createdat) DESC
              LIMIT :limit";

        $stmt = $this->conn->prepare($q);
        $kw = '%' . (string)$keyword . '%';
        $stmt->bindParam(':kw', $kw);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return (array)$stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total diskusi
     */
    public function countAll(): int
    {
        $q = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->conn->prepare($q);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Upvote (toggle)
     */
    public function upvote($diskusiId, $userId): array
    {
        $diskusiId = (int)$diskusiId;
        $userId = (int)$userId;

        try {
            // Cek sudah upvote?
            $check = $this->conn->prepare("SELECT id FROM diskusi_upvotes WHERE diskusi_id = :d AND user_id = :u");
            $check->bindParam(':d', $diskusiId, PDO::PARAM_INT);
            $check->bindParam(':u', $userId, PDO::PARAM_INT);
            $check->execute();

            if ($check->rowCount() > 0) {
                $del = $this->conn->prepare("DELETE FROM diskusi_upvotes WHERE diskusi_id = :d AND user_id = :u");
                $del->bindParam(':d', $diskusiId, PDO::PARAM_INT);
                $del->bindParam(':u', $userId, PDO::PARAM_INT);
                $del->execute();
                return ['success' => true, 'action' => 'removed'];
            } else {
                $ins = $this->conn->prepare("INSERT INTO diskusi_upvotes (diskusi_id, user_id) VALUES (:d, :u)");
                $ins->bindParam(':d', $diskusiId, PDO::PARAM_INT);
                $ins->bindParam(':u', $userId, PDO::PARAM_INT);
                $ins->execute();
                return ['success' => true, 'action' => 'added'];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Fitur upvote belum tersedia'];
        }
    }

    /**
     * Check if user has upvoted
     */
    public function hasUpvoted($diskusiId, $userId): bool
    {
        $diskusiId = (int)$diskusiId;
        $userId = (int)$userId;

        try {
            $q = "SELECT id FROM diskusi_upvotes WHERE diskusi_id = :d AND user_id = :u";
            $stmt = $this->conn->prepare($q);
            $stmt->bindParam(':d', $diskusiId, PDO::PARAM_INT);
            $stmt->bindParam(':u', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
