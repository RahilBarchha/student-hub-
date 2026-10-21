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

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    course VARCHAR(50) NOT NULL,
    role VARCHAR(20) DEFAULT 'student',
    status VARCHAR(20) DEFAULT 'Active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mobile VARCHAR(15) NOT NULL,
    course VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    gender VARCHAR(10) NOT NULL,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(100) NOT NULL,
    description TEXT,
    poster VARCHAR(255) NULL,
    status VARCHAR(20) DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'Confirmed'
)");

$conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$adminCheck = $conn->query("SELECT id FROM users WHERE email = 'barchharahil720@gmail.com' OR username = 'barchharahil720@gmail.com'");
$defaultAdminPass = password_hash("1@Rr2345", PASSWORD_DEFAULT);

if ($adminCheck && $adminCheck->num_rows > 0) {
    $row = $adminCheck->fetch_assoc();
    $aid = $row['id'];
    $stmt = $conn->prepare("UPDATE users SET username='barchharahil720@gmail.com', email='barchharahil720@gmail.com', password=?, role='admin', status='Active' WHERE id=?");
    $stmt->bind_param("si", $defaultAdminPass, $aid);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO users (username, fullname, email, password, mobile, course, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $u = "barchharahil720@gmail.com";
    $fn = "Rahil Barchha";
    $em = "barchharahil720@gmail.com";
    $mb = "9876543210";
    $cr = "BTech_IT";
    $rl = "admin";
    $st = "Active";
    $stmt->bind_param("ssssssss", $u, $fn, $em, $defaultAdminPass, $mb, $cr, $rl, $st);
    $stmt->execute();
    $stmt->close();
}

$sampleStudents = [
    ['Rohan Sharma', 'rohan@example.com', '9876543211', 'BTech_CSE', 3, 'Male', 'Active'],
    ['Priya Dave', 'priya.user@example.com', '9812345672', 'BTech_IT', 2, 'Female', 'Active'],
    ['Aarav Patel', 'aarav.user@example.com', '9823456781', 'BTech_AI', 1, 'Male', 'Active'],
    ['Ananya Iyer', 'ananya.user@example.com', '9876543201', 'BTech_CSE', 4, 'Female', 'Active'],
    ['Karan Verma', 'karan.verma@example.com', '9898989898', 'BTech_IT', 3, 'Male', 'Active'],
    ['Sneha Joshi', 'sneha.joshi@example.com', '9723456789', 'BTech_CSE', 2, 'Female', 'Active']
];

$stmtStu = $conn->prepare("INSERT INTO students (name, email, mobile, course, year, gender, status) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
foreach ($sampleStudents as $s) {
    $stmtStu->bind_param("ssssiss", $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6]);
    $stmtStu->execute();
}
$stmtStu->close();

$sampleEvents = [
    ['Annual Tech Symposium 2026', 'Technical', '2026-11-15', 'Main Auditorium', 'A full day symposium showcasing cutting-edge AI and web technologies.', 'Open'],
    ['National Hackathon Sprint', 'Competition', '2026-11-20', 'Lab Complex 3', '24-hour coding marathon building real-world open source solutions.', 'Open'],
    ['Web Security & Pentesting Workshop', 'Workshop', '2026-12-05', 'Seminar Hall B', 'Hands-on training session on web application security and secure coding.', 'Open'],
    ['Campus Career & Internship Fair', 'Placement', '2026-12-18', 'Student Convention Center', 'Meet leading tech recruiters for summer internships and full-time roles.', 'Open']
];

$eventCheck = $conn->query("SELECT COUNT(*) AS total FROM events");
$eventCount = ($eventCheck) ? (int)$eventCheck->fetch_assoc()['total'] : 0;
if ($eventCount === 0) {
    $stmtEv = $conn->prepare("INSERT INTO events (title, category, event_date, venue, description, status) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($sampleEvents as $ev) {
        $stmtEv->bind_param("ssssss", $ev[0], $ev[1], $ev[2], $ev[3], $ev[4], $ev[5]);
        $stmtEv->execute();
    }
    $stmtEv->close();
}

function recordAudit($conn, $userId, $action, $details) {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $action, $details, $clientIp);
    $stmt->execute();
    $stmt->close();
}
?>
