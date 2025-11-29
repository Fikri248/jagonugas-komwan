<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // cek domain: kalau ada "azurewebsites.net" anggap running di Azure
        $hostName = $_SERVER['HTTP_HOST'] ?? '';

        if (strpos($hostName, 'azurewebsites.net') !== false) {
            // === KONFIG AZURE ===
            $this->host     = "tubeskomwan.mysql.database.azure.com";
            $this->db_name  = "jagonugas_db";
            $this->username = "tubeskomwan";
            $this->password = "Monokuma00";
        } else {
            // === KONFIG LOCALHOST ===
            $this->host     = "localhost";
            $this->db_name  = "jagonugas_db";
            $this->username = "root";
            $this->password = "root";
        }
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
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
            die("Connection error: " . $e->getMessage());
        }
        return $this->conn;
    }
}
?>
