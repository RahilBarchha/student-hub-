<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

function sendApiResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data,
        "status_code" => $statusCode,
        "timestamp" => date("c")
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
    if (isset($_GET["id"])) {
        $id = (int)$_GET["id"];
        $stmt = $conn->prepare("SELECT student_id, name, email, mobile, course, year, gender, status, created_at FROM students WHERE student_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($student = $res->fetch_assoc()) {
            sendApiResponse(true, "Student record retrieved successfully.", $student, 200);
        } else {
            sendApiResponse(false, "Student with ID #$id was not found.", null, 404);
        }
        $stmt->close();
    } elseif (isset($_GET["q"])) {
        $q = trim($_GET["q"]);
        $searchTerm = "%" . $q . "%";
        $stmt = $conn->prepare("SELECT student_id, name, email, mobile, course, year, gender, status, created_at FROM students WHERE name LIKE ? OR email LIKE ? OR course LIKE ? OR mobile LIKE ? ORDER BY student_id DESC");
        $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result();
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
        $stmt->close();
        sendApiResponse(true, "Search completed. Found " . count($list) . " matching records.", $list, 200);
    } else {
        $res = $conn->query("SELECT student_id, name, email, mobile, course, year, gender, status, created_at FROM students ORDER BY student_id DESC");
        $list = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $list[] = $row;
            }
        }
        sendApiResponse(true, "All student records retrieved successfully.", $list, 200);
    }
} elseif ($method === "POST") {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    if (empty($data)) {
        $data = $_POST;
    }

    $action = $data["action"] ?? "create";

    if ($action === "create" || $action === "add") {
        $name = trim($data["name"] ?? "");
        $email = trim($data["email"] ?? "");
        $mobile = trim($data["mobile"] ?? "");
        $course = trim($data["course"] ?? "");
        $year = (int)($data["year"] ?? 1);
        $gender = trim($data["gender"] ?? "Male");
        $status = trim($data["status"] ?? "Active");

        if (empty($name) || !preg_match("/^[a-zA-Z\s]{2,50}$/", $name)) {
            sendApiResponse(false, "Validation error: Name must contain only letters and spaces (2-50 characters).", null, 400);
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendApiResponse(false, "Validation error: A valid email address is required.", null, 400);
        }
        if (empty($mobile) || !preg_match("/^[6-9]\d{9}$/", $mobile)) {
            sendApiResponse(false, "Validation error: Mobile number must be 10 digits starting with 6, 7, 8, or 9.", null, 400);
        }
        if (empty($course)) {
            sendApiResponse(false, "Validation error: Please select a valid course.", null, 400);
        }

        $dup = $conn->prepare("SELECT student_id FROM students WHERE email = ?");
        $dup->bind_param("s", $email);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $dup->close();
            sendApiResponse(false, "Duplicate error: Email address '$email' is already registered.", null, 400);
        }
        $dup->close();

        $stmt = $conn->prepare("INSERT INTO students (name, email, mobile, course, year, gender, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiss", $name, $email, $mobile, $course, $year, $gender, $status);

        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();

            $fetchStmt = $conn->prepare("SELECT student_id, name, email, mobile, course, year, gender, status, created_at FROM students WHERE student_id = ?");
            $fetchStmt->bind_param("i", $newId);
            $fetchStmt->execute();
            $newStudent = $fetchStmt->get_result()->fetch_assoc();
            $fetchStmt->close();

            sendApiResponse(true, "Student #$newId ('$name') created successfully.", $newStudent, 201);
        } else {
            sendApiResponse(false, "Database insert failure: " . $conn->error, null, 500);
        }
    } elseif ($action === "update" || $action === "edit") {
        $id = (int)($data["student_id"] ?? 0);
        $name = trim($data["name"] ?? "");
        $email = trim($data["email"] ?? "");
        $mobile = trim($data["mobile"] ?? "");
        $course = trim($data["course"] ?? "");
        $year = (int)($data["year"] ?? 1);
        $gender = trim($data["gender"] ?? "Male");
        $status = trim($data["status"] ?? "Active");

        if ($id <= 0) {
            sendApiResponse(false, "Validation error: Valid student_id is required for update.", null, 400);
        }
        if (empty($name) || !preg_match("/^[a-zA-Z\s]{2,50}$/", $name)) {
            sendApiResponse(false, "Validation error: Name must contain only letters and spaces (2-50 characters).", null, 400);
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendApiResponse(false, "Validation error: A valid email address is required.", null, 400);
        }
        if (empty($mobile) || !preg_match("/^[6-9]\d{9}$/", $mobile)) {
            sendApiResponse(false, "Validation error: Mobile number must be 10 digits starting with 6, 7, 8, or 9.", null, 400);
        }
        if (empty($course)) {
            sendApiResponse(false, "Validation error: Please select a course.", null, 400);
        }

        $dup = $conn->prepare("SELECT student_id FROM students WHERE email = ? AND student_id != ?");
        $dup->bind_param("si", $email, $id);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $dup->close();
            sendApiResponse(false, "Duplicate error: Email address '$email' is already used by another student.", null, 400);
        }
        $dup->close();

        $stmt = $conn->prepare("UPDATE students SET name = ?, email = ?, mobile = ?, course = ?, year = ?, gender = ?, status = ? WHERE student_id = ?");
        $stmt->bind_param("ssssissi", $name, $email, $mobile, $course, $year, $gender, $status, $id);

        if ($stmt->execute()) {
            $stmt->close();
            $fetchStmt = $conn->prepare("SELECT student_id, name, email, mobile, course, year, gender, status, created_at FROM students WHERE student_id = ?");
            $fetchStmt->bind_param("i", $id);
            $fetchStmt->execute();
            $updatedStudent = $fetchStmt->get_result()->fetch_assoc();
            $fetchStmt->close();

            sendApiResponse(true, "Student #$id updated successfully.", $updatedStudent, 200);
        } else {
            sendApiResponse(false, "Database update failure: " . $conn->error, null, 500);
        }
    } elseif ($action === "delete") {
        $id = (int)($data["student_id"] ?? 0);
        if ($id <= 0) {
            sendApiResponse(false, "Validation error: Valid student_id is required for deletion.", null, 400);
        }

        $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $stmt->close();
                sendApiResponse(true, "Student #$id permanently deleted successfully.", ["deleted_id" => $id], 200);
            } else {
                $stmt->close();
                sendApiResponse(false, "Student #$id does not exist.", null, 404);
            }
        } else {
            sendApiResponse(false, "Database deletion failure: " . $conn->error, null, 500);
        }
    } else {
        sendApiResponse(false, "Invalid action requested: '$action'. Supported actions: create, update, delete.", null, 400);
    }
} else {
    sendApiResponse(false, "HTTP Method '$method' is not supported. Use GET or POST.", null, 405);
}
?>
