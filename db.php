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
        // Deteksi: kalau domain mengandung azurewebsites.net => pakai config Azure
        $hostName = $_SERVER['HTTP_HOST'] ?? '';

        if (strpos($hostName, 'azurewebsites.net') !== false) {
            // === KONFIG AZURE ===
            $this->host     = 'jagonugas.mysql.database.azure.com';
            $this->db_name  = 'jagonugas_db';
            $this->username = 'ariyudakun';
            $this->password = 'asusTUFG4min9';
            $this->port     = 3306;
        } else {
            // === KONFIG LOCALHOST ===
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
        } catch (PDOException $e) {
            // Untuk debug awal boleh tampilkan error, tapi di production sebaiknya pakai error_log saja
            die('Connection error: ' . $e->getMessage());
        }

        return $this->conn;
    }
}
// ======================================
// AUTO INITIALIZE CONNECTION
// ======================================
$database = new Database();
$pdo = $database->getConnection();