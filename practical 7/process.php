<?php
session_start();

$csvFile = __DIR__ . '/students.csv';
$jsonFile = __DIR__ . '/students.json';
$contactCsv = __DIR__ . '/contacts.csv';
$contactJson = __DIR__ . '/contacts.json';

if (isset($_GET['download'])) {
    $file = $_GET['download'] === 'csv' ? $csvFile : ($_GET['download'] === 'json' ? $jsonFile : null);
    if ($file && file_exists($file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    }
}

$errors = [];
$success = "";
$mode = "";

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    if (isset($_POST["message"])) {
        $mode = "contact";
        $name = htmlspecialchars(trim($_POST["name"] ?? ""));
        $email = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
        $message = htmlspecialchars(trim($_POST["message"] ?? ""));

        if (empty($name) || !preg_match("/^[a-zA-Z\s]{2,50}$/", $name)) {
            $errors[] = "Name must contain only letters and spaces (2-50 characters).";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "A valid email address is required.";
        }
        if (empty($message) || strlen($message) < 10) {
            $errors[] = "Message must be at least 10 characters long.";
        }

        if (empty($errors)) {
            $needHeader = !file_exists($contactCsv) || filesize($contactCsv) === 0;
            $fp = fopen($contactCsv, "a");
            if ($fp && flock($fp, LOCK_EX)) {
                if ($needHeader) {
                    fputcsv($fp, ["Name", "Email", "Message", "Submitted_At"]);
                }
                fputcsv($fp, [$name, $email, $message, date("Y-m-d H:i:s")]);
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            $list = file_exists($contactJson) ? (json_decode(file_get_contents($contactJson), true) ?: []) : [];
            $list[] = [
                "name" => $name,
                "email" => $email,
                "message" => $message,
                "created_at" => date("Y-m-d H:i:s")
            ];
            file_put_contents($contactJson, json_encode($list, JSON_PRETTY_PRINT), LOCK_EX);

            $success = "Contact message has been validated and saved to CSV and JSON storage!";
        }
    } else {
        $mode = "register";
        $name = htmlspecialchars(trim($_POST["name"] ?? ""));
        $email = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
        $mobile = htmlspecialchars(trim($_POST["mobile"] ?? ""));
        $course = htmlspecialchars(trim($_POST["course"] ?? ""));
        $year = htmlspecialchars(trim($_POST["year"] ?? ""));
        $gender = htmlspecialchars(trim($_POST["gender"] ?? ""));

        if (empty($name) || !preg_match("/^[a-zA-Z\s]{2,50}$/", $name)) {
            $errors[] = "Full Name must contain only alphabets and spaces (2-50 characters).";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please provide a valid email format.";
        }
        if (empty($mobile) || !preg_match("/^[6-9]\d{9}$/", $mobile)) {
            $errors[] = "Mobile number must be exactly 10 digits starting with 6, 7, 8, or 9.";
        }
        if (empty($course)) {
            $errors[] = "Please select a valid course/branch.";
        }
        if (empty($year)) {
            $errors[] = "Please select an academic year.";
        }
        if (empty($gender)) {
            $errors[] = "Please select your gender.";
        }

        if (empty($errors)) {
            $needHeader = !file_exists($csvFile) || filesize($csvFile) === 0;
            $fp = fopen($csvFile, "a");
            if ($fp && flock($fp, LOCK_EX)) {
                if ($needHeader) {
                    fputcsv($fp, ["Name", "Email", "Mobile", "Course", "Year", "Gender", "Registered_At"]);
                }
                fputcsv($fp, [$name, $email, $mobile, $course, $year, $gender, date("Y-m-d H:i:s")]);
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            $records = file_exists($jsonFile) ? (json_decode(file_get_contents($jsonFile), true) ?: []) : [];
            $records[] = [
                "name" => $name,
                "email" => $email,
                "mobile" => $mobile,
                "course" => $course,
                "year" => $year,
                "gender" => $gender,
                "created_at" => date("Y-m-d H:i:s")
            ];
            file_put_contents($jsonFile, json_encode($records, JSON_PRETTY_PRINT), LOCK_EX);

            $success = "Student registration validated and successfully stored in CSV and JSON formats!";
        }
    }
}

$students = file_exists($jsonFile) ? (json_decode(file_get_contents($jsonFile), true) ?: []) : [];
$contacts = file_exists($contactJson) ? (json_decode(file_get_contents($contactJson), true) ?: []) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Form Storage - Practical 7</title>
    <link rel="stylesheet" href="../practical 3/style.css">
    <style>
        .result-box {
            width: 100%;
            margin: 20px 0;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-top: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--surface);
            font-size: 14px;
        }
        .data-table th, .data-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .data-table th {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }
        .data-table tr:hover {
            background: rgba(74, 144, 226, 0.08);
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            background: #e2e8f0;
            color: #334155;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .actions-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
    </style>
</head>
<body>

    <header>
        <div class="header">
            <h1>Student Hub Portal</h1>
            <p>PHP Server-Side Validation & File Storage</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu">☰</button>
        <div class="site-mini-title">PHP Storage</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle">
            <span>Dark Mode</span>
        </label>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Main navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="../practical 2/register.html" class="nav-btn">Register Form</a>
        <a href="../practical 2/contact.html" class="nav-btn">Contact Form</a>
        <a href="../practical 6/index.html" class="nav-btn">Data Hub</a>
        <a href="process.php" class="nav-btn active">PHP Storage</a>
    </nav>

    <main class="container">
        <h2 class="page-title">PHP Form Processing & Safe File Storage</h2>

        <?php if (!empty($success)): ?>
            <div class="form-message success" style="display: block; margin-bottom: 20px;">
                <strong>Success:</strong> <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="form-message error" style="display: block; margin-bottom: 20px;">
                <strong>Validation Failed:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= $err ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="actions-bar">
            <a href="../practical 2/register.html" class="btn">New Registration</a>
            <a href="../practical 2/contact.html" class="btn" style="background: #475569;">New Contact Message</a>
            <a href="process.php?download=csv" class="btn" style="background: #16a34a;">Download CSV</a>
            <a href="process.php?download=json" class="btn" style="background: #ea580c;">Download JSON</a>
        </div>

        <section class="result-box">
            <h3 style="color: var(--primary); margin-bottom: 8px;">
                Registered Students (Stored in CSV & JSON)
            </h3>
            <p style="color: var(--muted); font-size: 13px;">
                Records read dynamically from server-side storage (Total: <?= count($students) ?>)
            </p>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Gender</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="8" style="text-align:center; padding: 20px;">No registered records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $index => $item): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($item['name'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($item['email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($item['mobile'] ?? '') ?></td>
                                    <td><span class="badge"><?= htmlspecialchars($item['course'] ?? '') ?></span></td>
                                    <td><?= htmlspecialchars($item['year'] ?? '') ?> Year</td>
                                    <td><?= htmlspecialchars($item['gender'] ?? '') ?></td>
                                    <td><small><?= htmlspecialchars($item['created_at'] ?? '') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if (!empty($contacts)): ?>
        <section class="result-box" style="margin-top: 30px;">
            <h3 style="color: var(--primary); margin-bottom: 8px;">
                Contact Inquiries (Stored in CSV & JSON)
            </h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Message</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($item['name'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($item['email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($item['message'] ?? '') ?></td>
                                    <td><small><?= htmlspecialchars($item['created_at'] ?? '') ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

    </main>

    <footer>
        <div class="footer">
            <p>&copy; 2026 Student Hub Portal | All Rights Reserved.</p>
        </div>
    </footer>

    <script src="../practical 4/script.js"></script>
</body>
</html>
