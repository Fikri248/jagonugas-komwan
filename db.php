<?php
// db.php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public  $conn;

    public function __construct() {
        // 1) Coba pakai environment variable (Docker / Azure App Settings)
        $envHost = getenv('DB_HOST');
        $envName = getenv('DB_NAME');
        $envUser = getenv('DB_USER');
        $envPass = getenv('DB_PASS');
        $envPort = getenv('DB_PORT');

        // Kalau ENV tersedia, selalu pakai ini (untuk Docker/Jenkins/Azure)
        if (!empty($envHost) && !empty($envName) && !empty($envUser)) {
            // === CONFIG DARI ENV (Docker / Azure) ===
            $this->host     = $envHost;
            $this->db_name  = $envName;
            $this->username = $envUser;
            $this->password = $envPass ?? '';
            $this->port     = $envPort ? (int)$envPort : 3306;
            return;
        }

        // 2) Kalau tidak ada ENV, deteksi Azure Web App
        $hostName = $_SERVER['HTTP_HOST'] ?? '';

        if (strpos($hostName, 'azurewebsites.net') !== false) {
            // === KONFIG AZURE DATABASE MYSQL ===
            $this->host     = 'jagonugas.mysql.database.azure.com';
            $this->db_name  = 'jagonugas_db';
            $this->username = 'ariyudakun';
            $this->password = 'asusTUFG4min9';
            $this->port     = 3306;
        } else {
            // 3) Fallback: LOCALHOST (XAMPP)
            $this->host     = 'localhost';
            $this->db_name  = 'jagonugas_db';
            $this->username = 'root';
            $this->password = '';
            $this->port     = 3306;
        }
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";

            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            // =====================================================
            // FIX TIMEZONE: Set MySQL session ke Asia/Jakarta (UTC+7)
            // Ini memastikan NOW(), CURRENT_TIMESTAMP, dan default
            // values menggunakan waktu Jakarta, bukan UTC
            // =====================================================
            $this->conn->exec("SET time_zone = '+07:00'");

        } catch (PDOException $e) {
            // Di dev bisa pakai die(), di prod sebaiknya log saja
            die('Connection error: ' . $e->getMessage());
        }

        return $this->conn;
    }
}

// ======================================
// AUTO INITIALIZE CONNECTION
// ======================================
$database = new Database();
$pdo      = $database->getConnection();
