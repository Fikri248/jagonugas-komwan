<?php
// ModelUser.php

class User {
    private $conn;
    private $table = 'users';

    // Properties
    public $id;
    public $name;
    public $email;
    public $password;
    public $program_studi;
    public $semester;
    public $role;
    public $avatar;
    public $gems;

    // Constants
    const SIGNUP_BONUS_GEMS = 75;
    const ROLE_STUDENT = 'student';
    const ROLE_MENTOR = 'mentor';
    const ROLE_ADMIN = 'admin';

    public function __construct($db = null) {
        if ($db) {
            $this->conn = $db;
        } else {
            global $pdo;
            $this->conn = $pdo;
        }
    }

    /**
     * REGISTER - Create new student
     */
    public function register() {
        if ($this->emailExists()) {
            return ['success' => false, 'message' => 'Email sudah terdaftar'];
        }

        $query = "INSERT INTO {$this->table} 
                  (name, email, password, program_studi, semester, role, gems) 
                  VALUES (:name, :email, :password, :program_studi, :semester, :role, :gems)";

        $stmt = $this->conn->prepare($query);

        // Sanitize & hash
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = filter_var($this->email, FILTER_SANITIZE_EMAIL);
        $this->program_studi = htmlspecialchars(strip_tags($this->program_studi));
        $hashedPassword = password_hash($this->password, PASSWORD_DEFAULT);
        $role = self::ROLE_STUDENT;
        $bonusGems = self::SIGNUP_BONUS_GEMS;

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':program_studi', $this->program_studi);
        $stmt->bindParam(':semester', $this->semester);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':gems', $bonusGems, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return [
                'success' => true, 
                'message' => 'Registrasi berhasil! Kamu dapat ' . $bonusGems . ' gems gratis.',
                'user_id' => $this->conn->lastInsertId(),
                'gems' => $bonusGems
            ];
        }
        return ['success' => false, 'message' => 'Registrasi gagal, coba lagi'];
    }

    /**
     * REGISTER MENTOR
     */
    public function registerMentor($expertise = [], $bio = '', $transkripPath = '') {
        if ($this->emailExists()) {
            return ['success' => false, 'message' => 'Email sudah terdaftar'];
        }

        $query = "INSERT INTO {$this->table} 
                  (name, email, password, program_studi, semester, role, expertise, bio, transkrip_path, is_verified, gems) 
                  VALUES (:name, :email, :password, :program_studi, :semester, :role, :expertise, :bio, :transkrip_path, 0, :gems)";

        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = filter_var($this->email, FILTER_SANITIZE_EMAIL);
        $this->program_studi = htmlspecialchars(strip_tags($this->program_studi));
        $hashedPassword = password_hash($this->password, PASSWORD_DEFAULT);
        $role = self::ROLE_MENTOR;
        $expertiseJson = json_encode($expertise);
        $bio = htmlspecialchars(strip_tags($bio));
        $bonusGems = self::SIGNUP_BONUS_GEMS;

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':program_studi', $this->program_studi);
        $stmt->bindParam(':semester', $this->semester);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':expertise', $expertiseJson);
        $stmt->bindParam(':bio', $bio);
        $stmt->bindParam(':transkrip_path', $transkripPath);
        $stmt->bindParam(':gems', $bonusGems, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return [
                'success' => true, 
                'message' => 'Registrasi mentor berhasil! Menunggu verifikasi admin.',
                'user_id' => $this->conn->lastInsertId(),
                'gems' => $bonusGems
            ];
        }
        return ['success' => false, 'message' => 'Registrasi gagal, coba lagi'];
    }

    /**
     * LOGIN - Verify credentials
     */
    public function login() {
        $query = "SELECT id, name, email, password, role, program_studi, semester, is_verified, gems, avatar 
                  FROM {$this->table} 
                  WHERE email = :email 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Email atau password salah'];
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($this->password, $row['password'])) {
            return ['success' => false, 'message' => 'Email atau password salah'];
        }

        // Cek verifikasi untuk mentor
        if ($row['role'] === self::ROLE_MENTOR && !$row['is_verified']) {
            return ['success' => false, 'message' => 'Akun mentor belum diverifikasi'];
        }

        return [
            'success' => true,
            'user' => [
                'id' => $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => $row['role'],
                'program_studi' => $row['program_studi'],
                'semester' => $row['semester'],
                'is_verified' => $row['is_verified'],
                'gems' => $row['gems'],
                'avatar' => $row['avatar']
            ]
        ];
    }

    /**
     * Check if email exists
     */
    public function emailExists() {
        $query = "SELECT id FROM {$this->table} WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Get user by ID
     */
    public function getById($id) {
        $query = "SELECT id, name, email, role, program_studi, semester, is_verified, gems, avatar, bio, expertise, created_at 
                  FROM {$this->table} 
                  WHERE id = :id 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update profile
     */
    public function updateProfile($userId, $data) {
        $allowedFields = ['name', 'program_studi', 'semester', 'bio', 'avatar'];
        $updates = [];
        $params = [':id' => $userId];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "{$key} = :{$key}";
                $params[":{$key}"] = htmlspecialchars(strip_tags($value));
            }
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'Tidak ada data untuk diupdate'];
        }

        $query = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        if ($stmt->execute($params)) {
            return ['success' => true, 'message' => 'Profil berhasil diupdate'];
        }
        return ['success' => false, 'message' => 'Gagal mengupdate profil'];
    }

    /**
     * Update gems
     */
    public function updateGems($userId, $amount, $operation = 'add') {
        $operator = $operation === 'add' ? '+' : '-';
        
        $query = "UPDATE {$this->table} SET gems = gems {$operator} :amount WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':amount', $amount, PDO::PARAM_INT);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Get user gems
     */
    public function getGems($userId) {
        $query = "SELECT gems FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['gems'] : 0;
    }

    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        // Verify current password
        $query = "SELECT password FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($currentPassword, $row['password'])) {
            return ['success' => false, 'message' => 'Password lama salah'];
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateQuery = "UPDATE {$this->table} SET password = :password WHERE id = :id";
        $updateStmt = $this->conn->prepare($updateQuery);
        $updateStmt->bindParam(':password', $hashedPassword);
        $updateStmt->bindParam(':id', $userId, PDO::PARAM_INT);

        if ($updateStmt->execute()) {
            return ['success' => true, 'message' => 'Password berhasil diubah'];
        }
        return ['success' => false, 'message' => 'Gagal mengubah password'];
    }

    /**
     * FORGOT PASSWORD - Generate reset token
     */
    public function createResetToken() {
        if (!$this->emailExists()) {
            return ['success' => false, 'message' => 'Email tidak ditemukan'];
        }

        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $query = "UPDATE {$this->table} 
                  SET reset_token = :token, reset_token_expiry = :expiry 
                  WHERE email = :email";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expiry', $expiry);
        $stmt->bindParam(':email', $this->email);

        if ($stmt->execute()) {
            return ['success' => true, 'token' => $token];
        }
        return ['success' => false, 'message' => 'Gagal membuat token'];
    }

    /**
     * RESET PASSWORD - Verify token & update password
     */
    public function resetPassword($token, $newPassword) {
        $query = "SELECT id, reset_token_expiry FROM {$this->table} 
                  WHERE reset_token = :token LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Token tidak valid'];
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (strtotime($row['reset_token_expiry']) < time()) {
            return ['success' => false, 'message' => 'Token sudah kadaluarsa'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateQuery = "UPDATE {$this->table} 
                        SET password = :password, reset_token = NULL, reset_token_expiry = NULL 
                        WHERE id = :id";

        $updateStmt = $this->conn->prepare($updateQuery);
        $updateStmt->bindParam(':password', $hashedPassword);
        $updateStmt->bindParam(':id', $row['id'], PDO::PARAM_INT);

        if ($updateStmt->execute()) {
            return ['success' => true, 'message' => 'Password berhasil diubah'];
        }
        return ['success' => false, 'message' => 'Gagal mengubah password'];
    }

    /**
     * Get all mentors (verified)
     */
    public function getMentors($limit = 20) {
        $query = "SELECT id, name, email, program_studi, semester, bio, expertise, avatar, created_at 
                  FROM {$this->table} 
                  WHERE role = 'mentor' AND is_verified = 1 
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete user (admin only)
     */
    public function delete($userId) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
