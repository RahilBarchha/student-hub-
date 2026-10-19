<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "studenthub";

$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->query("CREATE DATABASE IF NOT EXISTS " . $dbname);
$conn->select_db($dbname);
$conn->set_charset("utf8mb4");

$tableUsers = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    course VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableUsers);

$tableLogs = "CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableLogs);

$checkInitial = $conn->query("SELECT id FROM users LIMIT 1");
if ($checkInitial && $checkInitial->num_rows === 0) {
    $demoPassword = password_hash("Admin@123", PASSWORD_DEFAULT);
    $initStmt = $conn->prepare("INSERT INTO users (username, fullname, email, password, mobile, course) VALUES (?, ?, ?, ?, ?, ?)");
    $u1 = "rahil_admin";
    $f1 = "Rahil Patel";
    $e1 = "rahil@example.com";
    $m1 = "9876543210";
    $c1 = "BTech_IT";
    $initStmt->bind_param("ssssss", $u1, $f1, $e1, $demoPassword, $m1, $c1);
    $initStmt->execute();

    $u2 = "priya_dave";
    $f2 = "Priya Dave";
    $e2 = "priya@example.com";
    $m2 = "9812345678";
    $c2 = "BTech_CSE";
    $initStmt->bind_param("ssssss", $u2, $f2, $e2, $demoPassword, $m2, $c2);
    $initStmt->execute();
    $initStmt->close();
}
?>
