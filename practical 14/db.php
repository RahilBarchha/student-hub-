<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "studenthub";

$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Database Connection Error: " . $conn->connect_error);
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
    status VARCHAR(20) DEFAULT 'Active',
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
if (!in_array('status', $cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'Active'");
}
if (!in_array('last_login', $cols)) {
    $conn->query("ALTER TABLE users ADD COLUMN last_login DATETIME NULL");
}

$tableAudit = "CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableAudit);

$tableEvents = "CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(100) NOT NULL,
    description TEXT,
    poster VARCHAR(255) NULL,
    status VARCHAR(20) DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableEvents);

$tableStudents = "CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mobile VARCHAR(15) NOT NULL,
    course VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    gender VARCHAR(10) NOT NULL,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableStudents);

$tableReg = "CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'Confirmed'
)";
$conn->query($tableReg);

$checkAdmin = $conn->query("SELECT id FROM users WHERE email = 'barchharahil720@gmail.com' OR username = 'barchharahil720@gmail.com'");
$admPass = password_hash("1@Rr2345", PASSWORD_DEFAULT);
if ($checkAdmin && $checkAdmin->num_rows > 0) {
    $admId = $checkAdmin->fetch_assoc()['id'];
    $updAdm = $conn->prepare("UPDATE users SET username = 'barchharahil720@gmail.com', email = 'barchharahil720@gmail.com', password = ?, role = 'admin', status = 'Active' WHERE id = ?");
    $updAdm->bind_param("si", $admPass, $admId);
    $updAdm->execute();
    $updAdm->close();
} else {
    $insAdm = $conn->prepare("INSERT INTO users (username, fullname, email, password, mobile, course, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $u = "barchharahil720@gmail.com";
    $f = "Rahil Barchha";
    $e = "barchharahil720@gmail.com";
    $m = "9876543210";
    $c = "BTech_IT";
    $r = "admin";
    $st = "Active";
    $insAdm->bind_param("ssssssss", $u, $f, $e, $admPass, $m, $c, $r, $st);
    $insAdm->execute();
    $insAdm->close();
}

$sampleUsers = [
    ['student_rohan', 'Rohan Sharma', 'rohan@example.com', '9876543211', 'BTech_CSE', 'student', 'Active'],
    ['priya_student', 'Priya Dave', 'priya.user@example.com', '9812345672', 'BTech_IT', 'student', 'Active'],
    ['aarav_student', 'Aarav Patel', 'aarav.user@example.com', '9823456781', 'BTech_AI', 'student', 'Inactive'],
    ['ananya_user', 'Ananya Iyer', 'ananya.user@example.com', '9876543201', 'BTech_CSE', 'student', 'Active'],
    ['faculty_advisor', 'Dr. Rajesh Mehta', 'rajesh.mehta@example.com', '9988776655', 'BTech_IT', 'faculty', 'Active']
];
$stuPass = password_hash("Student@123", PASSWORD_DEFAULT);
$stmtSample = $conn->prepare("INSERT INTO users (username, fullname, email, password, mobile, course, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id=id");
foreach ($sampleUsers as $su) {
    $stmtSample->bind_param("ssssssss", $su[0], $su[1], $su[2], $stuPass, $su[3], $su[4], $su[5], $su[6]);
    $stmtSample->execute();
}
$stmtSample->close();

$chkReg = $conn->query("SELECT COUNT(*) AS total FROM registrations");
$cntReg = $chkReg ? (int)$chkReg->fetch_assoc()['total'] : 0;
if ($cntReg < 6) {
    $conn->query("INSERT IGNORE INTO registrations (student_id, event_id, registered_at, status) VALUES 
        (1, 1, '2026-06-15 10:30:00', 'Confirmed'),
        (2, 1, '2026-06-20 14:15:00', 'Confirmed'),
        (3, 2, '2026-07-05 09:00:00', 'Confirmed'),
        (4, 2, '2026-07-18 11:20:00', 'Confirmed'),
        (1, 3, '2026-08-10 16:45:00', 'Confirmed'),
        (2, 3, '2026-08-25 12:10:00', 'Confirmed'),
        (3, 4, '2026-09-04 15:30:00', 'Confirmed'),
        (4, 5, '2026-09-22 17:00:00', 'Confirmed'),
        (5, 5, '2026-10-02 11:00:00', 'Confirmed'),
        (1, 6, '2026-10-12 14:30:00', 'Confirmed')");
}

function writeAuditLog($conn, $userId, $action, $details) {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $action, $details, $clientIp);
    $stmt->execute();
    $stmt->close();
}
?>
