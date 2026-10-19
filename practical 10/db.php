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
    role VARCHAR(20) DEFAULT 'student',
    last_login DATETIME NULL,
    remember_token VARCHAR(255) NULL,
    remember_expiry DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableUsers);

$cols = [];
$resCols = $conn->query("SHOW COLUMNS FROM users");
if ($resCols) {
    while ($c = $resCols->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
}

if (!in_array('role', $cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'student'");
}
if (!in_array('last_login', $cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN last_login DATETIME NULL");
}
if (!in_array('remember_token', $cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) NULL");
}
if (!in_array('remember_expiry', $cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN remember_expiry DATETIME NULL");
}

$conn->query("UPDATE users SET role = 'student' WHERE username != 'barchharahil720@gmail.com' AND role = 'admin'");

$checkStudent = $conn->query("SELECT id FROM users WHERE username = 'student_rohan'");
if ($checkStudent && $checkStudent->num_rows === 0) {
    $stuPass = password_hash("Student@123", PASSWORD_DEFAULT);
    $insStu = $conn->prepare("INSERT INTO users (username, fullname, email, password, mobile, course, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $u = "student_rohan";
    $f = "Rohan Sharma";
    $e = "rohan@example.com";
    $m = "9876543211";
    $c = "BTech_CSE";
    $r = "student";
    $insStu->bind_param("sssssss", $u, $f, $e, $stuPass, $m, $c, $r);
    $insStu->execute();
    $insStu->close();
}

$checkAdmin = $conn->query("SELECT id FROM users WHERE email = 'barchharahil720@gmail.com' OR username = 'barchharahil720@gmail.com'");
if ($checkAdmin && $checkAdmin->num_rows > 0) {
    $admId = $checkAdmin->fetch_assoc()['id'];
    $admPass = password_hash("1@Rr2345", PASSWORD_DEFAULT);
    $updAdm = $conn->prepare("UPDATE users SET username = 'barchharahil720@gmail.com', email = 'barchharahil720@gmail.com', password = ?, role = 'admin' WHERE id = ?");
    $updAdm->bind_param("si", $admPass, $admId);
    $updAdm->execute();
    $updAdm->close();
} else {
    $admPass = password_hash("1@Rr2345", PASSWORD_DEFAULT);
    $insAdm = $conn->prepare("INSERT INTO users (username, fullname, email, password, mobile, course, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $u = "barchharahil720@gmail.com";
    $f = "Rahil Barchha";
    $e = "barchharahil720@gmail.com";
    $m = "9876543210";
    $c = "BTech_IT";
    $r = "admin";
    $insAdm->bind_param("sssssss", $u, $f, $e, $admPass, $m, $c, $r);
    $insAdm->execute();
    $insAdm->close();
}
?>
