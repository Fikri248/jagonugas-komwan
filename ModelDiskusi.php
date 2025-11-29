<?php
class Diskusi {
    private $conn;
    public $id, $user_id, $judul, $pertanyaan, $image_path;

    public function __construct($db) { $this->conn = $db; }

    public function create() {
        $query = "INSERT INTO diskusi SET user_id=:user_id, judul=:judul, pertanyaan=:pertanyaan, image_path=:image_path";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":judul", $this->judul);
        $stmt->bindParam(":pertanyaan", $this->pertanyaan);
        $stmt->bindParam(":image_path", $this->image_path);
        return $stmt->execute();
    }

    public function getAll() {
        $query = "SELECT d.*, u.name FROM diskusi d INNER JOIN users u ON d.user_id=u.id ORDER BY d.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
