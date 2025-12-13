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

    public function __construct($db) {
        $this->conn = $db;
    }

    // REGISTER - Create new user
    public function register() {
        // Cek email sudah ada atau belum
        if ($this->emailExists()) {
            return ['success' => false, 'message' => 'Email sudah terdaftar'];
        }

        $query = "INSERT INTO " . $this->table . " 
                  (name, email, password, program_studi, semester, role) 
                  VALUES (:name, :email, :password, :program_studi, :semester, 'student')";

        $stmt = $this->conn->prepare($query);

        // Sanitize & hash
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->program_studi = htmlspecialchars(strip_tags($this->program_studi));
        $hashedPassword = password_hash($this->password, PASSWORD_DEFAULT);

        // Bind
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':program_studi', $this->program_studi);
        $stmt->bindParam(':semester', $this->semester);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Registrasi berhasil'];
        }
        return ['success' => false, 'message' => 'Registrasi gagal, coba lagi'];
    }

    // LOGIN - Verify credentials
    public function login() {
    $query = "SELECT id, name, email, password, role, program_studi, semester, is_verified 
              FROM " . $this->table . " 
              WHERE email = :email 
              LIMIT 1";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':email', $this->email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($this->password, $row['password'])) {
            return [
                'success' => true,
                'user' => [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'role' => $row['role'],
                    'program_studi' => $row['program_studi'],
                    'semester' => $row['semester'],
                    'is_verified' => $row['is_verified']
                ]
            ];
        }
    }

    return ['success' => false, 'message' => 'Email atau password salah'];
}


    // Check if email exists
    public function emailExists() {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // FORGOT PASSWORD - Generate reset token
    public function createResetToken() {
        if (!$this->emailExists()) {
            return ['success' => false, 'message' => 'Email tidak ditemukan'];
        }

        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $query = "UPDATE " . $this->table . " 
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

    // RESET PASSWORD - Verify token & update password
    public function resetPassword($token, $newPassword) {
        $query = "SELECT id, reset_token_expiry FROM " . $this->table . " 
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

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateQuery = "UPDATE " . $this->table . " 
                        SET password = :password, reset_token = NULL, reset_token_expiry = NULL 
                        WHERE id = :id";

        $updateStmt = $this->conn->prepare($updateQuery);
        $updateStmt->bindParam(':password', $hashedPassword);
        $updateStmt->bindParam(':id', $row['id']);

        if ($updateStmt->execute()) {
            return ['success' => true, 'message' => 'Password berhasil diubah'];
        }
        return ['success' => false, 'message' => 'Gagal mengubah password'];
    }

    public function registerMentor($expertise = [], $bio = '', $transkripPath = '') {
    if ($this->emailExists()) {
        return ['success' => false, 'message' => 'Email sudah terdaftar'];
    }

    $query = "INSERT INTO " . $this->table . " 
              (name, email, password, program_studi, semester, role, expertise, bio, transkrip_path, is_verified) 
              VALUES (:name, :email, :password, :program_studi, :semester, 'mentor', :expertise, :bio, :transkrip_path, 0)";

    $stmt = $this->conn->prepare($query);

    $this->name = htmlspecialchars(strip_tags($this->name));
    $this->email = htmlspecialchars(strip_tags($this->email));
    $this->program_studi = htmlspecialchars(strip_tags($this->program_studi));
    $hashedPassword = password_hash($this->password, PASSWORD_DEFAULT);
    $expertiseJson = json_encode($expertise);
    $bio = htmlspecialchars(strip_tags($bio));

    $stmt->bindParam(':name', $this->name);
    $stmt->bindParam(':email', $this->email);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':program_studi', $this->program_studi);
    $stmt->bindParam(':semester', $this->semester);
    $stmt->bindParam(':expertise', $expertiseJson);
    $stmt->bindParam(':bio', $bio);
    $stmt->bindParam(':transkrip_path', $transkripPath);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Registrasi mentor berhasil'];
    }
    return ['success' => false, 'message' => 'Registrasi gagal, coba lagi'];
}

}
