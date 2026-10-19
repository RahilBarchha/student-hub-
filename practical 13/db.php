<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "studenthub";

$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection error: " . $conn->connect_error
    ]);
    exit;
}

$conn->query("CREATE DATABASE IF NOT EXISTS " . $dbname);
$conn->select_db($dbname);
$conn->set_charset("utf8mb4");

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

$chk = $conn->query("SELECT COUNT(*) AS total FROM students");
$cnt = $chk ? (int)$chk->fetch_assoc()['total'] : 0;

if ($cnt < 5) {
    $seed = [
        ['Aarav Patel', 'aarav.api@example.com', '9823456781', 'BTech_IT', 2, 'Male', 'Active'],
        ['Priya Dave', 'priya.api@example.com', '9812345672', 'BTech_CSE', 3, 'Female', 'Active'],
        ['Spandan Shah', 'spandan.api@example.com', '9712345673', 'BTech_AI', 1, 'Male', 'Active'],
        ['Ananya Iyer', 'ananya.api@example.com', '9876543201', 'BTech_CSE', 4, 'Female', 'Active'],
        ['Rohan Sharma', 'rohan.api@example.com', '9822334402', 'BTech_IT', 2, 'Male', 'Active']
    ];
    $stmt = $conn->prepare("INSERT INTO students (name, email, mobile, course, year, gender, status) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
    foreach ($seed as $s) {
        $stmt->bind_param("ssssiss", $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6]);
        $stmt->execute();
    }
    $stmt->close();
}
?>
