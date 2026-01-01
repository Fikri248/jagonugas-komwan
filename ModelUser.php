<?php
// ModelUser.php

class User
{
    private $conn;
    private $table = 'users';

    // Properties (utama)
    public $id;
    public $name;
    public $email;
    public $password;
    public $program_studi;   // versi baru (snake_case)
    public $semester;
    public $role;
    public $avatar;
    public $gems;
    public $is_verified;
    public $bio;
    public $expertise;
    public $transkrip_path;
    public $google_id;       // <-- TAMBAH: untuk Google OAuth
    public $created_at;

    // Properties (alias untuk kompatibilitas kode lama)
    public $programstudi;    // versi lama (tanpa underscore)

    // Constants
    const SIGNUP_BONUS_GEMS = 75;
    const ROLE_STUDENT = 'student';
    const ROLE_MENTOR  = 'mentor';
    const ROLE_ADMIN   = 'admin';

    public function __construct($db = null)
    {
        if ($db) {
            $this->conn = $db;
            return;
        }

        // fallback global $pdo kalau project kamu pakai itu
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            $this->conn = $pdo;
            return;
        }

        throw new RuntimeException('Database connection (PDO) tidak tersedia.');
    }

    /**
     * Ambil nilai program studi dari properti manapun yang terisi.
     */
    private function getProgramStudiValue(): string
    {
        $v1 = trim((string)($this->program_studi ?? ''));
        if ($v1 !== '') return $v1;

        $v2 = trim((string)($this->programstudi ?? ''));
        if ($v2 !== '') return $v2;

        return '';
    }

    private function sanitizeText($value): string
    {
        return htmlspecialchars(strip_tags((string)$value));
    }

    private function sanitizeEmail($value): string
    {
        return (string) filter_var((string)$value, FILTER_SANITIZE_EMAIL);
    }

    private function normalizeExpertise($expertise): string
    {
        if (is_array($expertise)) {
            return json_encode(array_values($expertise));
        }

        $expertise = trim((string)$expertise);
        if ($expertise === '') {
            return json_encode([]);
        }

        $decoded = json_decode($expertise, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded);
        }

        return json_encode([$expertise]);
    }

    /**
     * REGISTER - Create new student
     */
    public function register(): array
    {
        if ($this->emailExists()) {
            return ['success' => false, 'message' => 'Email sudah terdaftar'];
        }

        $query = "INSERT INTO {$this->table}
                  (name, email, password, program_studi, semester, role, gems, google_id, avatar)
                  VALUES (:name, :email, :password, :program_studi, :semester, :role, :gems, :google_id, :avatar)";

        $stmt = $this->conn->prepare($query);

        $name         = $this->sanitizeText($this->name);
        $email        = $this->sanitizeEmail($this->email);
        $programStudi = $this->sanitizeText($this->getProgramStudiValue());
        $semester     = (int) $this->semester;
        $googleId     = $this->google_id ? $this->sanitizeText($this->google_id) : null;
        $avatar       = $this->avatar ? $this->sanitizeText($this->avatar) : null;

        $hashedPassword = password_hash((string)$this->password, PASSWORD_DEFAULT);
        $role = self::ROLE_STUDENT;
        $bonusGems = self::SIGNUP_BONUS_GEMS;

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':program_studi', $programStudi);
        $stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':gems', $bonusGems, PDO::PARAM_INT);
        $stmt->bindParam(':google_id', $googleId);
        $stmt->bindParam(':avatar', $avatar);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Registrasi berhasil! Kamu dapat ' . $bonusGems . ' gems gratis.',
                'user_id'  => $this->conn->lastInsertId(),
                'gems'     => $bonusGems
            ];
        }

        return ['success' => false, 'message' => 'Registrasi gagal, coba lagi'];
    }

    /**
     * REGISTER MENTOR - Support Google OAuth
     */
    public function registerMentor($expertise = [], $bio = '', $transkripPath = ''): array
    {
        if ($this->emailExists()) {
            return ['success' => false, 'message' => 'Email sudah terdaftar'];
        }

        $query = "INSERT INTO {$this->table}
                  (name, email, password, program_studi, semester, role, expertise, bio, transkrip_path, is_verified, gems, google_id, avatar)
                  VALUES (:name, :email, :password, :program_studi, :semester, :role, :expertise, :bio, :transkrip_path, 0, :gems, :google_id, :avatar)";

        $stmt = $this->conn->prepare($query);

        $name         = $this->sanitizeText($this->name);
        $email        = $this->sanitizeEmail($this->email);
        $programStudi = $this->sanitizeText($this->getProgramStudiValue());
        $semester     = (int) $this->semester;
        $googleId     = $this->google_id ? $this->sanitizeText($this->google_id) : null;
        $avatar       = $this->avatar ? $this->sanitizeText($this->avatar) : null;

        $hashedPassword = password_hash((string)$this->password, PASSWORD_DEFAULT);
        $role = self::ROLE_MENTOR;

        $expertiseJson = $this->normalizeExpertise($expertise);
        $bioClean = $this->sanitizeText($bio);
        $transkripPathClean = $this->sanitizeText($transkripPath);

        $bonusGems = self::SIGNUP_BONUS_GEMS;

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':program_studi', $programStudi);
        $stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':expertise', $expertiseJson);
        $stmt->bindParam(':bio', $bioClean);
        $stmt->bindParam(':transkrip_path', $transkripPathClean);
        $stmt->bindParam(':gems', $bonusGems, PDO::PARAM_INT);
        $stmt->bindParam(':google_id', $googleId);
        $stmt->bindParam(':avatar', $avatar);

        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Registrasi mentor berhasil! Menunggu verifikasi admin.',
                'user_id'  => $this->conn->lastInsertId(),
                'gems'     => $bonusGems
            ];
        }

        return ['success' => false, 'message' => 'Registrasi gagal, coba lagi'];
    }

    /**
     * LOGIN - Verify credentials (support Google OAuth)
     */
    public function login(): array
    {
        $query = "SELECT id, name, email, password, role, program_studi, semester, is_verified, gems, avatar, google_id
                  FROM {$this->table}
                  WHERE email = :email
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $email = $this->sanitizeEmail($this->email);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Email atau password salah'];
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify((string)$this->password, (string)$row['password'])) {
            return ['success' => false, 'message' => 'Email atau password salah'];
        }

        if ($row['role'] === self::ROLE_MENTOR && !(int)$row['is_verified']) {
            return ['success' => false, 'message' => 'Akun mentor belum diverifikasi'];
        }

        return [
            'success' => true,
            'user' => [
                'id'          => $row['id'],
                'name'        => $row['name'],
                'email'       => $row['email'],
                'role'        => $row['role'],
                'program_studi' => $row['program_studi'],
                'semester'    => $row['semester'],
                'is_verified' => $row['is_verified'],
                'gems'        => $row['gems'],
                'avatar'      => $row['avatar'],
                'google_id'   => $row['google_id']
            ]
        ];
    }

    /**
     * LOGIN BY GOOGLE ID
     */
    public function loginByGoogleId(string $googleId): array
    {
        $query = "SELECT id, name, email, password, role, program_studi, semester, is_verified, gems, avatar, google_id
                  FROM {$this->table}
                  WHERE google_id = :google_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':google_id', $googleId);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Akun Google tidak ditemukan'];
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row['role'] === self::ROLE_MENTOR && !(int)$row['is_verified']) {
            return ['success' => false, 'message' => 'Akun mentor belum diverifikasi'];
        }

        return [
            'success' => true,
            'user' => [
                'id'          => $row['id'],
                'name'        => $row['name'],
                'email'       => $row['email'],
                'role'        => $row['role'],
                'program_studi' => $row['program_studi'],
                'semester'    => $row['semester'],
                'is_verified' => $row['is_verified'],
                'gems'        => $row['gems'],
                'avatar'      => $row['avatar'],
                'google_id'   => $row['google_id']
            ]
        ];
    }

    /**
     * Link Google account to existing user
     */
    public function linkGoogleAccount(int $userId, string $googleId, ?string $avatar = null): bool
    {
        $updates = ['google_id = :google_id'];
        $params = [':google_id' => $googleId, ':id' => $userId];

        if ($avatar) {
            $updates[] = 'avatar = :avatar';
            $params[':avatar'] = $this->sanitizeText($avatar);
        }

        $query = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        return (bool)$stmt->execute($params);
    }

    /**
     * Check if email exists
     */
    public function emailExists(): bool
    {
        $query = "SELECT id FROM {$this->table} WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);

        $email = $this->sanitizeEmail($this->email);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if Google ID exists
     */
    public function googleIdExists(string $googleId): bool
    {
        $query = "SELECT id FROM {$this->table} WHERE google_id = :google_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':google_id', $googleId);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Get user by email
     */
    public function getByEmail(string $email)
    {
        $query = "SELECT id, name, email, role, program_studi, semester, is_verified, gems, avatar, google_id, bio, expertise, created_at
                  FROM {$this->table}
                  WHERE email = :email
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $email = $this->sanitizeEmail($email);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by ID
     */
    public function getById($id)
    {
        $query = "SELECT id, name, email, role, program_studi, semester, is_verified, gems, avatar, google_id, bio, expertise, created_at
                  FROM {$this->table}
                  WHERE id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $id = (int)$id;
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update profile
     */
    public function updateProfile($userId, $data): array
    {
        $allowedFields = ['name', 'program_studi', 'semester', 'bio', 'avatar', 'expertise', 'google_id'];

        $updates = [];
        $params = [':id' => (int)$userId];

        foreach ((array)$data as $key => $value) {
            if (!in_array($key, $allowedFields, true)) continue;

            if ($key === 'expertise') {
                $updates[] = "expertise = :expertise";
                $params[':expertise'] = $this->normalizeExpertise($value);
                continue;
            }

            $updates[] = "{$key} = :{$key}";
            $params[":{$key}"] = $this->sanitizeText($value);
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
    public function updateGems($userId, $amount, $operation = 'add'): bool
    {
        $operator = ($operation === 'add') ? '+' : '-';

        $query = "UPDATE {$this->table} SET gems = gems {$operator} :amount WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $amount = (int)$amount;
        $userId = (int)$userId;

        $stmt->bindParam(':amount', $amount, PDO::PARAM_INT);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);

        return (bool)$stmt->execute();
    }

    /**
     * Get user gems
     */
    public function getGems($userId): int
    {
        $query = "SELECT gems FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);

        $userId = (int)$userId;
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['gems'] : 0;
    }

    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword): array
    {
        $query = "SELECT password FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);

        $userId = (int)$userId;
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify((string)$currentPassword, (string)$row['password'])) {
            return ['success' => false, 'message' => 'Password lama salah'];
        }

        $hashedPassword = password_hash((string)$newPassword, PASSWORD_DEFAULT);

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
    public function createResetToken(): array
    {
        if (!$this->emailExists()) {
            return ['success' => false, 'message' => 'Email tidak ditemukan'];
        }

        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $email = $this->sanitizeEmail($this->email);

        $query = "UPDATE {$this->table}
                  SET reset_token = :token, reset_token_expiry = :expiry
                  WHERE email = :email";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expiry', $expiry);
        $stmt->bindParam(':email', $email);

        if ($stmt->execute()) {
            return ['success' => true, 'token' => $token];
        }

        return ['success' => false, 'message' => 'Gagal membuat token'];
    }

    /**
     * RESET PASSWORD - Verify token & update password
     */
    public function resetPassword($token, $newPassword): array
    {
        $query = "SELECT id, reset_token_expiry FROM {$this->table}
                  WHERE reset_token = :token LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Token tidak valid'];
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (strtotime((string)$row['reset_token_expiry']) < time()) {
            return ['success' => false, 'message' => 'Token sudah kadaluarsa'];
        }

        $hashedPassword = password_hash((string)$newPassword, PASSWORD_DEFAULT);

        $updateQuery = "UPDATE {$this->table}
                        SET password = :password, reset_token = NULL, reset_token_expiry = NULL
                        WHERE id = :id";

        $updateStmt = $this->conn->prepare($updateQuery);

        $id = (int)$row['id'];
        $updateStmt->bindParam(':password', $hashedPassword);
        $updateStmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($updateStmt->execute()) {
            return ['success' => true, 'message' => 'Password berhasil diubah'];
        }

        return ['success' => false, 'message' => 'Gagal mengubah password'];
    }

    /**
     * Get all mentors (verified)
     */
    public function getMentors($limit = 20): array
    {
        $query = "SELECT id, name, email, program_studi, semester, bio, expertise, avatar, created_at
                  FROM {$this->table}
                  WHERE role = 'mentor' AND is_verified = 1
                  ORDER BY created_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $limit = (int)$limit;
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return (array)$stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete user (admin only)
     */
    public function delete($userId): bool
    {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $userId = (int)$userId;
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);

        return (bool)$stmt->execute();
    }
}
