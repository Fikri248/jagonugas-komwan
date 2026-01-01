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
            $this->host     = $envHost;                     // contoh: db
            $this->db_name  = $envName;                    // jagonugas_db
            $this->username = $envUser;                    // jagonugas_user
            $this->password = $envPass ?? '';              // secretpassword
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
            $this->password = 'root';
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

            // DEBUG OPSIONAL: cek host yang dipakai
            // error_log("DB CONNECT TO {$this->host}:{$this->port} / {$this->db_name} ({$this->username})");

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
