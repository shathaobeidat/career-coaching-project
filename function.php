<?php
class DB_con {
    private $dbh;

    public function __construct() {
        $host = 'localhost';
        $db   = 'career_coaching';
        $user = 'root';
        $pass = '';

        try {
         
            $this->dbh = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function register($name, $email, $password, $role) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $inst = $this->dbh->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        return $inst->execute([$name, $email, $hashedPassword, $role]);
    }

    public function login($email, $password) {
        $stmt = $this->dbh->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            return $user; 
        }
        return false; 
    }
}
?>