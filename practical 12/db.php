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

$tableEvents = "CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(100) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) DEFAULT 'Open',
    poster VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableEvents);

$cols = [];
$resCols = $conn->query("SHOW COLUMNS FROM events");
if ($resCols) {
    while ($c = $resCols->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
}

if (!in_array('description', $cols)) {
    $conn->query("ALTER TABLE events ADD COLUMN description TEXT NULL");
}
if (!in_array('status', $cols)) {
    $conn->query("ALTER TABLE events ADD COLUMN status VARCHAR(20) DEFAULT 'Open'");
}
if (!in_array('poster', $cols)) {
    $conn->query("ALTER TABLE events ADD COLUMN poster VARCHAR(255) NULL");
}

$countRes = $conn->query("SELECT COUNT(*) AS total FROM events");
$currCount = $countRes ? (int)$countRes->fetch_assoc()['total'] : 0;

if ($currCount < 6) {
    $seedEvents = [
        ['National Hackathon 2026', 'Technical', '2026-11-15', 'Main Auditorium', 'A 36-hour national coding and robotics hackathon for engineering students.', 'Open', ''],
        ['Cyber Security Symposium', 'Technical', '2026-11-20', 'Seminar Hall B', 'Industry experts discuss digital forensics, penetration testing, and ethical hacking.', 'Open', ''],
        ['Cultural Annual Fest', 'Cultural', '2026-12-05', 'Campus Grounds', 'Annual university cultural extravaganza featuring music, dance, and drama.', 'Open', ''],
        ['Inter-College Cricket Tournament', 'Sports', '2026-11-25', 'University Sports Arena', 'Championship tournament featuring 16 collegiate cricket teams.', 'Closed', ''],
        ['AI & Machine Learning Conclave', 'Technical', '2026-12-12', 'Tech Park Lab 4', 'Hands-on workshops on deep learning and generative AI architectures.', 'Open', ''],
        ['Alumni Leadership Talk', 'Academic', '2026-11-30', 'Conference Room A', 'Distinguished alumni share career milestones and startup leadership strategies.', 'Open', '']
    ];

    $insStmt = $conn->prepare("INSERT INTO events (title, category, event_date, venue, description, status, poster) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE venue=VALUES(venue)");
    foreach ($seedEvents as $ev) {
        $insStmt->bind_param("sssssss", $ev[0], $ev[1], $ev[2], $ev[3], $ev[4], $ev[5], $ev[6]);
        $insStmt->execute();
    }
    $insStmt->close();
}
?>
