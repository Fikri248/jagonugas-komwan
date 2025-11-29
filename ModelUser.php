<?php
class User {
    private $conn;
    public $id, $name, $email, $password, $program_studi, $semester, $role;

    public function __construct($db) { $this->conn = $db; }

    public function register() {
        $query = "INSERT INTO users SET name=:name, email=:email, password=:password, program_studi=:program_studi, semester=:semester, role=:role";
        $stmt = $this->conn->prepare($query);
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":program_studi", $this->program_studi);
        $stmt->bindParam(":semester", $this->semester);
        $stmt->bindParam(":role", $this->role);
        return $stmt->execute();
    }

    public function login() {
        $query = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if($user && password_verify($this->password, $user['password'])) {
            $this->id = $user['id'];
            $this->name = $user['name'];
            $this->role = $user['role'];
            return true;
        }
        return false;
    }
}
?>
