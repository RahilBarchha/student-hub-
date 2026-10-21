<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'search_students') {
    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $term = "%" . $q . "%";
        $stmt = $conn->prepare("SELECT student_id, name, email, mobile, course, year, gender, status FROM students WHERE name LIKE ? OR email LIKE ? OR course LIKE ? ORDER BY student_id DESC");
        $stmt->bind_param("sss", $term, $term, $term);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        $res = $conn->query("SELECT student_id, name, email, mobile, course, year, gender, status FROM students ORDER BY student_id DESC");
        $data = [];
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
    }
    exit;
}

if ($action === 'toggle_student_status') {
    $sid = (int)($_POST['student_id'] ?? 0);
    if ($sid <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
        exit;
    }
    $chk = $conn->prepare("SELECT name, status FROM students WHERE student_id = ?");
    $chk->bind_param("i", $sid);
    $chk->execute();
    $found = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$found) {
        echo json_encode(['status' => 'error', 'message' => 'Student not found']);
        exit;
    }

    $newStatus = ($found['status'] === 'Active') ? 'Inactive' : 'Active';
    $upd = $conn->prepare("UPDATE students SET status = ? WHERE student_id = ?");
    $upd->bind_param("si", $newStatus, $sid);
    if ($upd->execute()) {
        $userId = $_SESSION['user_id'] ?? 1;
        recordAudit($conn, $userId, 'STUDENT_STATUS_TOGGLED', "Toggled status of student " . $found['name'] . " to " . $newStatus);
        echo json_encode(['status' => 'success', 'new_status' => $newStatus]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed']);
    }
    $upd->close();
    exit;
}

if ($action === 'toggle_user_status') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
        exit;
    }
    $chk = $conn->prepare("SELECT fullname, status FROM users WHERE id = ?");
    $chk->bind_param("i", $uid);
    $chk->execute();
    $found = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$found) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    $newStatus = ($found['status'] === 'Active') ? 'Inactive' : 'Active';
    $upd = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $upd->bind_param("si", $newStatus, $uid);
    if ($upd->execute()) {
        $actorId = $_SESSION['user_id'] ?? 1;
        recordAudit($conn, $actorId, 'USER_STATUS_TOGGLED', "Toggled status of user " . $found['fullname'] . " to " . $newStatus);
        echo json_encode(['status' => 'success', 'new_status' => $newStatus]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed']);
    }
    $upd->close();
    exit;
}

if ($action === 'register_event') {
    $sid = (int)($_POST['student_id'] ?? 0);
    $eid = (int)($_POST['event_id'] ?? 0);
    if ($sid <= 0 || $eid <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid student or event selection']);
        exit;
    }

    $chk = $conn->prepare("SELECT registration_id FROM registrations WHERE student_id = ? AND event_id = ?");
    $chk->bind_param("ii", $sid, $eid);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        echo json_encode(['status' => 'error', 'message' => 'Student already registered for this event']);
        exit;
    }
    $chk->close();

    $ins = $conn->prepare("INSERT INTO registrations (student_id, event_id, status) VALUES (?, ?, 'Confirmed')");
    $ins->bind_param("ii", $sid, $eid);
    if ($ins->execute()) {
        $actorId = $_SESSION['user_id'] ?? 1;
        recordAudit($conn, $actorId, 'EVENT_REGISTRATION', "Student #" . $sid . " registered for Event #" . $eid);
        echo json_encode(['status' => 'success', 'message' => 'Registration confirmed successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
    }
    $ins->close();
    exit;
}

if ($action === 'get_stats') {
    $stuCount = $conn->query("SELECT COUNT(*) AS total FROM students")->fetch_assoc()['total'] ?? 0;
    $usrCount = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'] ?? 0;
    $evCount = $conn->query("SELECT COUNT(*) AS total FROM events")->fetch_assoc()['total'] ?? 0;
    $logCount = $conn->query("SELECT COUNT(*) AS total FROM audit_logs")->fetch_assoc()['total'] ?? 0;
    $regCount = $conn->query("SELECT COUNT(*) AS total FROM registrations")->fetch_assoc()['total'] ?? 0;

    echo json_encode([
        'status' => 'success',
        'stats' => [
            'students' => (int)$stuCount,
            'users' => (int)$usrCount,
            'events' => (int)$evCount,
            'audit_logs' => (int)$logCount,
            'registrations' => (int)$regCount
        ]
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action specified']);
exit;
?>
