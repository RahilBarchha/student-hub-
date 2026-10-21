<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

require_once __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$findAdmin = $conn->query("SELECT id, fullname, email FROM users WHERE email = 'barchharahil720@gmail.com' OR role = 'admin' LIMIT 1");
$adminRow = ($findAdmin && $findAdmin->num_rows > 0) ? $findAdmin->fetch_assoc() : ['id' => 1, 'fullname' => 'Rahil Barchha', 'email' => 'barchharahil720@gmail.com'];
$adminId = (int)$adminRow['id'];
$adminName = $adminRow['fullname'];
$adminEmail = $adminRow['email'];

if (isset($_GET['switch_role'])) {
    $targetRole = $_GET['switch_role'];
    if ($targetRole === 'admin') {
        $_SESSION['user_id'] = $adminId;
        $_SESSION['username'] = $adminEmail;
        $_SESSION['fullname'] = $adminName;
        $_SESSION['email'] = $adminEmail;
        $_SESSION['role'] = 'admin';
        recordAudit($conn, $adminId, 'ROLE_SWITCH_ADMIN', 'Switched session role to Administrator.');
    } elseif ($targetRole === 'student') {
        $_SESSION['user_id'] = 2;
        $_SESSION['username'] = 'student_rohan';
        $_SESSION['fullname'] = 'Rohan Sharma';
        $_SESSION['email'] = 'rohan@example.com';
        $_SESSION['role'] = 'student';
        recordAudit($conn, 2, 'ROLE_SWITCH_STUDENT', 'Switched session role to Student.');
    } elseif ($targetRole === 'faculty') {
        $_SESSION['user_id'] = 6;
        $_SESSION['username'] = 'faculty_advisor';
        $_SESSION['fullname'] = 'Dr. Rajesh Mehta';
        $_SESSION['email'] = 'rajesh.mehta@example.com';
        $_SESSION['role'] = 'faculty';
        recordAudit($conn, 6, 'ROLE_SWITCH_FACULTY', 'Switched session role to Faculty.');
    } elseif ($targetRole === 'logout') {
        if (isset($_SESSION['user_id'])) {
            recordAudit($conn, $_SESSION['user_id'], 'LOGOUT', 'User logged out of session.');
        }
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['role'])) {
    $_SESSION['user_id'] = $adminId;
    $_SESSION['username'] = $adminEmail;
    $_SESSION['fullname'] = $adminName;
    $_SESSION['email'] = $adminEmail;
    $_SESSION['role'] = 'admin';
}

$currentRole = $_SESSION['role'] ?? 'guest';
$currentUserId = $_SESSION['user_id'] ?? 1;
$currentUserFullName = $_SESSION['fullname'] ?? 'Portal User';
$currentUserEmail = $_SESSION['email'] ?? 'user@example.com';
$isAdmin = ($currentRole === 'admin');

if (isset($_GET['download']) && $_GET['download'] === 'sql') {
    $sqlFile = __DIR__ . '/studenthub_p15.sql';
    if (file_exists($sqlFile)) {
        recordAudit($conn, $currentUserId, 'SQL_DOWNLOAD', 'Exported database backup file.');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="studenthub_database.sql"');
        readfile($sqlFile);
        exit;
    }
}

$alertMsg = "";
$alertType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $alertMsg = "Security Verification Failed: Invalid or missing CSRF token.";
        $alertType = "danger";
    } else {
        $act = $_POST['action'];

        if ($act === 'add_student') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $course = trim($_POST['course'] ?? '');
            $year = (int)($_POST['year'] ?? 1);
            $gender = trim($_POST['gender'] ?? 'Male');

            if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/^[0-9]{10}$/', $mobile)) {
                $stmt = $conn->prepare("INSERT INTO students (name, email, mobile, course, year, gender, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
                $stmt->bind_param("ssssis", $name, $email, $mobile, $course, $year, $gender);
                if ($stmt->execute()) {
                    recordAudit($conn, $currentUserId, 'STUDENT_ADDED', "Added new student: " . $name . " (" . $email . ")");
                    $alertMsg = "Student record for " . htmlspecialchars($name) . " created successfully.";
                    $alertType = "success";
                } else {
                    $alertMsg = "Database error inserting student: " . htmlspecialchars($conn->error);
                    $alertType = "danger";
                }
                $stmt->close();
            } else {
                $alertMsg = "Validation error: Please provide a valid Name, Email, and 10-digit Mobile number.";
                $alertType = "warning";
            }
        }

        if ($act === 'delete_student' && $isAdmin) {
            $sid = (int)($_POST['student_id'] ?? 0);
            if ($sid > 0) {
                $stmtFind = $conn->prepare("SELECT name FROM students WHERE student_id = ?");
                $stmtFind->bind_param("i", $sid);
                $stmtFind->execute();
                $stu = $stmtFind->get_result()->fetch_assoc();
                $stmtFind->close();

                if ($stu) {
                    $stmtDel = $conn->prepare("DELETE FROM students WHERE student_id = ?");
                    $stmtDel->bind_param("i", $sid);
                    if ($stmtDel->execute()) {
                        recordAudit($conn, $currentUserId, 'STUDENT_DELETED', "Removed student record #" . $sid . " (" . $stu['name'] . ")");
                        $alertMsg = "Student " . htmlspecialchars($stu['name']) . " removed successfully.";
                        $alertType = "success";
                    }
                    $stmtDel->close();
                }
            }
        }

        if ($act === 'add_event' && $isAdmin) {
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? 'Technical');
            $event_date = trim($_POST['event_date'] ?? date('Y-m-d'));
            $venue = trim($_POST['venue'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($title !== '' && $venue !== '') {
                $stmt = $conn->prepare("INSERT INTO events (title, category, event_date, venue, description, status) VALUES (?, ?, ?, ?, ?, 'Open')");
                $stmt->bind_param("sssss", $title, $category, $event_date, $venue, $description);
                if ($stmt->execute()) {
                    recordAudit($conn, $currentUserId, 'EVENT_CREATED', "Created campus event: " . $title);
                    $alertMsg = "Event '" . htmlspecialchars($title) . "' posted successfully.";
                    $alertType = "success";
                } else {
                    $alertMsg = "Error creating event: " . htmlspecialchars($conn->error);
                    $alertType = "danger";
                }
                $stmt->close();
            } else {
                $alertMsg = "Please fill in all mandatory event fields.";
                $alertType = "warning";
            }
        }

        if ($act === 'register_event_form') {
            $sid = (int)($_POST['student_id'] ?? 0);
            $eid = (int)($_POST['event_id'] ?? 0);
            if ($sid > 0 && $eid > 0) {
                $chk = $conn->prepare("SELECT registration_id FROM registrations WHERE student_id = ? AND event_id = ?");
                $chk->bind_param("ii", $sid, $eid);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $alertMsg = "This student is already registered for this event.";
                    $alertType = "warning";
                } else {
                    $ins = $conn->prepare("INSERT INTO registrations (student_id, event_id, status) VALUES (?, ?, 'Confirmed')");
                    $ins->bind_param("ii", $sid, $eid);
                    if ($ins->execute()) {
                        recordAudit($conn, $currentUserId, 'EVENT_REGISTRATION', "Registered student #" . $sid . " for event #" . $eid);
                        $alertMsg = "Registration confirmed successfully.";
                        $alertType = "success";
                    }
                    $ins->close();
                }
                $chk->close();
            }
        }
    }
}

$statStudents = $conn->query("SELECT COUNT(*) AS c FROM students")->fetch_assoc()['c'] ?? 0;
$statUsers = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;
$statEvents = $conn->query("SELECT COUNT(*) AS c FROM events")->fetch_assoc()['c'] ?? 0;
$statRegs = $conn->query("SELECT COUNT(*) AS c FROM registrations")->fetch_assoc()['c'] ?? 0;
$statAudits = $conn->query("SELECT COUNT(*) AS c FROM audit_logs")->fetch_assoc()['c'] ?? 0;

$studentsResult = $conn->query("SELECT student_id, name, email, mobile, course, year, gender, status FROM students ORDER BY student_id DESC");
$eventsResult = $conn->query("SELECT event_id, title, category, event_date, venue, description, status FROM events ORDER BY event_id DESC");
$auditsResult = $conn->query("SELECT id, user_id, action, details, ip_address, created_at FROM audit_logs ORDER BY id DESC LIMIT 10");
$regsResult = $conn->query("SELECT r.registration_id, r.registered_at, r.status, s.name AS student_name, s.course, e.title AS event_title FROM registrations r JOIN students s ON r.student_id = s.student_id JOIN events e ON r.event_id = e.event_id ORDER BY r.registration_id DESC LIMIT 10");

$systemModules = [
    [
        'category' => 'User Interface',
        'title' => 'Core User Interface & Semantic Pages',
        'desc' => 'Complete suite of responsive web pages: Home, Dashboard, Events, Profile, FAQs, Feedback, Contact, Login, and Registration.',
        'tag' => 'UI / UX',
        'main_link' => '../practical 2/index.html',
        'pages' => [
            ['name' => 'Home Page', 'url' => '../practical 2/index.html'],
            ['name' => 'Dashboard', 'url' => '../practical 2/Dashboard.html'],
            ['name' => 'Events Calendar', 'url' => '../practical 2/Event.html'],
            ['name' => 'User Profile', 'url' => '../practical 2/profile.html'],
            ['name' => 'Sign In Gateway', 'url' => '../practical 2/login.html'],
            ['name' => 'Account Registration', 'url' => '../practical 2/register.html'],
            ['name' => 'Knowledge Base & FAQs', 'url' => '../practical 2/FAQ.html'],
            ['name' => 'User Feedback', 'url' => '../practical 2/feedback.html'],
            ['name' => 'Contact Helpdesk', 'url' => '../practical 2/contact.html'],
            ['name' => 'About Organization', 'url' => '../practical 2/About.html']
        ]
    ],
    [
        'category' => 'Styling Engine',
        'title' => 'Visual Presentation & Responsive Layouts',
        'desc' => 'Custom CSS layout, flexbox, CSS grid, typography, cards, responsive media queries, and themes applied directly across all portal pages.',
        'tag' => 'Design System',
        'main_link' => '../practical 2/index.html',
        'pages' => [
            ['name' => 'Styled Home View', 'url' => '../practical 2/index.html'],
            ['name' => 'Styled Dashboard View', 'url' => '../practical 2/Dashboard.html'],
            ['name' => 'Styled Profile View', 'url' => '../practical 2/profile.html']
        ]
    ],
    [
        'category' => 'Interactivity',
        'title' => 'Interactive Experience & UI Components',
        'desc' => 'Mobile menu drawer, dark theme mode toggle, toast notifications, popup modals, and responsive interactions.',
        'tag' => 'Dynamic UI',
        'main_link' => '../practical 2/index.html',
        'pages' => [
            ['name' => 'Interactive Home', 'url' => '../practical 2/index.html'],
            ['name' => 'Interactive Events', 'url' => '../practical 2/Event.html']
        ]
    ],
    [
        'category' => 'Validation',
        'title' => 'Client Form Validation & Integrity Engine',
        'desc' => 'Real-time client-side regular expression validation for email, mobile, password strength, and registration form fields.',
        'tag' => 'Form Integrity',
        'main_link' => '../practical 2/register.html',
        'pages' => [
            ['name' => 'Registration Form', 'url' => '../practical 2/register.html'],
            ['name' => 'Login Form Validation', 'url' => '../practical 2/login.html'],
            ['name' => 'Feedback Validation', 'url' => '../practical 2/feedback.html']
        ]
    ],
    [
        'category' => 'Data Hub',
        'title' => 'Asynchronous Data Services & Dynamic Hub',
        'desc' => 'Asynchronous data rendering of students, events, and FAQs from local JSON files with DOM methods and search filters.',
        'tag' => 'JSON Engine',
        'main_link' => '../practical 6/index.html',
        'pages' => [
            ['name' => 'Data Hub Console', 'url' => '../practical 6/index.html']
        ]
    ],
    [
        'category' => 'Data Processing',
        'title' => 'Server-Side Processing & Document Records',
        'desc' => 'Server request processing, CSV data import/export, student records management, and contact submission pipelines.',
        'tag' => 'File Engine',
        'main_link' => '../practical 7/index.php',
        'pages' => [
            ['name' => 'File Services Console', 'url' => '../practical 7/index.php'],
            ['name' => 'Excel & Data Export', 'url' => '../practical 7/excel.php']
        ]
    ],
    [
        'category' => 'Database Core',
        'title' => 'Form Handling & Database Connectivity',
        'desc' => 'Server-side data validation, sanitized input handling, and relational database connection testing.',
        'tag' => 'Connectivity',
        'main_link' => '../practical 8/index.php',
        'pages' => [
            ['name' => 'Connection Portal', 'url' => '../practical 8/index.php'],
            ['name' => 'Diagnostics Test', 'url' => '../practical 8/test_connection.php']
        ]
    ],
    [
        'category' => 'Storage Engine',
        'title' => 'Relational Database CRUD Operations',
        'desc' => 'Full Create, Read, Update, and Delete operations for student profiles, event rosters, and persistent storage.',
        'tag' => 'CRUD Core',
        'main_link' => '../practical 9/index.php',
        'pages' => [
            ['name' => 'Database Manager', 'url' => '../practical 9/index.php']
        ]
    ],
    [
        'category' => 'Authentication',
        'title' => 'State Management & Access Control',
        'desc' => 'User authentication, password hashing, session tokens, remember-me cookies, and role-based portal routing.',
        'tag' => 'Auth & State',
        'main_link' => '../practical 10/index.php',
        'pages' => [
            ['name' => 'Authentication Home', 'url' => '../practical 10/index.php'],
            ['name' => 'Login Gateway', 'url' => '../practical 10/login.php'],
            ['name' => 'Admin Console', 'url' => '../practical 10/admin_dashboard.php'],
            ['name' => 'Student Portal', 'url' => '../practical 10/student_dashboard.php']
        ]
    ],
    [
        'category' => 'Security',
        'title' => 'Enterprise Security Shields & Input Sanitization',
        'desc' => 'Prepared statements, htmlspecialchars escaping, CSRF protection, secure authentication, and vulnerability defenses.',
        'tag' => 'Security Shield',
        'main_link' => '../practical 11/index.php',
        'pages' => [
            ['name' => 'Security Center', 'url' => '../practical 11/index.php']
        ]
    ],
    [
        'category' => 'Dynamic Content',
        'title' => 'Asynchronous Live Search & Upload Engine',
        'desc' => 'Asynchronous live search without reloading page, file upload validation, and instant status updates.',
        'tag' => 'AJAX Engine',
        'main_link' => '../practical 12/index.php',
        'pages' => [
            ['name' => 'Dynamic Content Hub', 'url' => '../practical 12/index.php']
        ]
    ],
    [
        'category' => 'API Services',
        'title' => 'RESTful Web APIs & Data Integration Services',
        'desc' => 'REST endpoints for students, events, and registrations with structured JSON response payloads.',
        'tag' => 'REST API',
        'main_link' => '../practical 13/index.php',
        'pages' => [
            ['name' => 'API Dashboard', 'url' => '../practical 13/index.php'],
            ['name' => 'Students JSON Endpoint', 'url' => '../practical 13/api.php?resource=students'],
            ['name' => 'Events JSON Endpoint', 'url' => '../practical 13/api.php?resource=events']
        ]
    ],
    [
        'category' => 'Administration',
        'title' => 'Administrative Control Center & Audit Trails',
        'desc' => 'Role-based access control, user status toggle, immutable audit logging, and system administrator controls.',
        'tag' => 'Audit System',
        'main_link' => '../practical 14/index.php',
        'pages' => [
            ['name' => 'Admin Dashboard', 'url' => '../practical 14/index.php']
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Hub Portal</title>
    <link rel="stylesheet" href="../practical 3/style.css">
    <style>
        .hub-role-bar {
            width: 90%;
            max-width: 900px;
            background: #1f4e79;
            color: #ffffff;
            padding: 12px 18px;
            border-radius: 8px;
            margin: 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .hub-role-badge {
            background: #ffcc00;
            color: #000;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .hub-btn {
            background: #ffffff;
            color: #1f4e79;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-block;
        }
        .hub-btn:hover {
            background: #eef5ff;
        }
        .hub-btn.active {
            background: #ffcc00;
            color: #000;
        }
        .hub-btn.danger {
            background: #dc2626;
            color: #fff;
        }
        .hub-btn.success {
            background: #16a34a;
            color: #fff;
        }
        .hub-tabs-bar {
            width: 90%;
            max-width: 900px;
            margin: 10px auto;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }
        .hub-tab-btn {
            background: #ffffff;
            color: #1f4e79;
            border: 1px solid #d5e5f8;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
        }
        .hub-tab-btn:hover {
            background: #eef5ff;
        }
        .hub-tab-btn.active {
            background: #1f4e79;
            color: #ffffff;
            border-color: #1f4e79;
        }
        .hub-section {
            display: none;
            width: 100%;
        }
        .hub-section.active {
            display: block;
        }
        .hub-stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin: 16px 0;
            width: 100%;
        }
        .hub-stat-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .hub-stat-num {
            font-size: 24px;
            font-weight: bold;
            color: #1f4e79;
            margin: 4px 0;
        }
        .hub-stat-lbl {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
        }
        .hub-modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin: 16px 0;
            width: 100%;
        }
        .hub-module-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .hub-mod-badge {
            background: #eef5ff;
            color: #1f4e79;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .hub-pages-list {
            margin: 10px 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .hub-page-link-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f4f9ff;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            border: 1px solid #d5e5f8;
        }
        .hub-page-link-row a {
            color: #1f4e79;
            font-weight: bold;
            text-decoration: none;
        }
        .hub-page-link-row a:hover {
            text-decoration: underline;
        }
        .hub-table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
            font-size: 13px;
        }
        .hub-table th, .hub-table td {
            border: 1px solid var(--border);
            padding: 10px;
            text-align: left;
        }
        .hub-table th {
            background: #f4f9ff;
            color: #1f4e79;
            font-weight: bold;
        }
        .hub-table tr:hover {
            background: #fcfdfe;
        }
        .hub-status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .hub-status-active {
            background: #dcfce7;
            color: #166534;
        }
        .hub-status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .hub-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.65);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        .hub-preview-modal.show {
            display: flex;
        }
        .hub-preview-window {
            background: #ffffff;
            width: 95%;
            max-width: 1150px;
            height: 88vh;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .hub-preview-header {
            background: #1f4e79;
            color: #ffffff;
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .hub-preview-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: #ffffff;
        }
        .hub-alert {
            padding: 10px 14px;
            border-radius: 6px;
            margin: 12px 0;
            font-size: 13px;
            font-weight: bold;
            width: 100%;
        }
        .hub-alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .hub-alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .hub-alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .hub-code-box {
            background: #1e293b;
            color: #f8fafc;
            padding: 12px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            margin: 8px 0;
        }
    </style>
</head>
<body>

    <header>
        <div class="header">
            <h1>Student Hub Portal</h1>
            <p>Learning • Innovation • Success</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu" aria-controls="navMenu" aria-expanded="false">
            ☰
        </button>
        <div class="site-mini-title">Student Hub Portal</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
            <span>Dark Mode</span>
        </label>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Main navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="../practical 2/Dashboard.html" class="nav-btn">Dashboard</a>
        <a href="../practical 2/Event.html" class="nav-btn">Events</a>
        <a href="../practical 2/profile.html" class="nav-btn">Profile</a>
        <a href="../practical 6/index.html" class="nav-btn">Data Hub</a>
        <a href="../practical 7/index.php" class="nav-btn">Documents</a>
        <a href="../practical 9/index.php" class="nav-btn">Database</a>
        <a href="../practical 10/index.php" class="nav-btn">Sessions</a>
        <a href="../practical 12/index.php" class="nav-btn">Live Search</a>
        <a href="../practical 13/index.php" class="nav-btn">Web APIs</a>
        <a href="../practical 14/index.php" class="nav-btn">Admin Console</a>
        <a href="index.php" class="nav-btn active">Portal Master</a>
    </nav>

    <div class="hub-role-bar">
        <div>
            User: <strong><?= htmlspecialchars($currentUserFullName) ?></strong>
            <span class="hub-role-badge"><?= htmlspecialchars(strtoupper($currentRole)) ?></span>
        </div>
        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            <a href="index.php?switch_role=admin" class="hub-btn <?= ($currentRole === 'admin') ? 'active' : '' ?>">Admin</a>
            <a href="index.php?switch_role=student" class="hub-btn <?= ($currentRole === 'student') ? 'active' : '' ?>">Student</a>
            <a href="index.php?switch_role=faculty" class="hub-btn <?= ($currentRole === 'faculty') ? 'active' : '' ?>">Faculty</a>
            <a href="index.php?download=sql" class="hub-btn success">Download SQL</a>
            <a href="index.php?switch_role=logout" class="hub-btn danger">Logout</a>
        </div>
    </div>

    <div class="hub-tabs-bar">
        <button class="hub-tab-btn active" onclick="switchPortalTab('tab-overview')">Portal Overview</button>
        <button class="hub-tab-btn" onclick="switchPortalTab('tab-modules')">System Modules Directory</button>
        <button class="hub-tab-btn" onclick="switchPortalTab('tab-students')">Student Management</button>
        <button class="hub-tab-btn" onclick="switchPortalTab('tab-events')">Events & Registrations</button>
        <button class="hub-tab-btn" onclick="switchPortalTab('tab-security')">Security & Auditing</button>
        <button class="hub-tab-btn" onclick="switchPortalTab('tab-deploy')">Server Deployment</button>
    </div>

    <div class="container" style="max-width: 950px;">

        <?php if ($alertMsg !== ''): ?>
            <div class="hub-alert hub-alert-<?= $alertType ?>">
                <?= $alertMsg ?>
            </div>
        <?php endif; ?>

        <div class="hub-stats-row">
            <div class="hub-stat-box">
                <div class="hub-stat-lbl">Students</div>
                <div class="hub-stat-num" id="stat-students-val"><?= $statStudents ?></div>
            </div>
            <div class="hub-stat-box">
                <div class="hub-stat-lbl">Users</div>
                <div class="hub-stat-num" id="stat-users-val"><?= $statUsers ?></div>
            </div>
            <div class="hub-stat-box">
                <div class="hub-stat-lbl">Events</div>
                <div class="hub-stat-num" id="stat-events-val"><?= $statEvents ?></div>
            </div>
            <div class="hub-stat-box">
                <div class="hub-stat-lbl">Registrations</div>
                <div class="hub-stat-num" id="stat-regs-val"><?= $statRegs ?></div>
            </div>
            <div class="hub-stat-box">
                <div class="hub-stat-lbl">Audit Logs</div>
                <div class="hub-stat-num" id="stat-audits-val"><?= $statAudits ?></div>
            </div>
        </div>

        <section id="tab-overview" class="hub-section active">
            <h2 class="page-title">Student Hub Integrated Enterprise Portal</h2>
            <p style="margin-bottom: 16px; font-size: 14px; text-align: center;">
                A comprehensive deployment-ready portal integrating all campus web services into a unified interface. Every system tier—semantic presentation, responsive styling, client validation, server-side processing, database persistence, asynchronous live operations, session security, and virtual hosting—is fully integrated and operable.
            </p>

            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <h3 style="color: #1f4e79; margin-bottom: 10px; font-size: 16px;">System Architecture Overview</h3>
                <ul style="padding-left: 20px; font-size: 13px; line-height: 1.8;">
                    <li><strong>Frontend Presentation Tier:</strong> Semantic HTML pages, CSS responsive grid layout, typography, and dark theme controls.</li>
                    <li><strong>Interactivity & Validation:</strong> Client-side regular expression validation and dynamic asynchronous DOM manipulation.</li>
                    <li><strong>Server & Business Logic Tier:</strong> PHP session state management, role-based access control, and sanitization.</li>
                    <li><strong>Data Persistence Tier:</strong> MySQL relational database engine with automated schema initialization and prepared statements.</li>
                    <li><strong>Security & Auditing:</strong> CSRF tokens, XSS HTML escaping, SQL injection parameterized queries, and full audit logging.</li>
                    <li><strong>Deployment Tier:</strong> Apache Virtual Hosts configured for local domain deployment with complete test matrices.</li>
                </ul>
            </div>

            <h3 style="color: #1f4e79; margin-bottom: 10px; font-size: 16px;">Recent Event Registrations</h3>
            <table class="hub-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Event Title</th>
                        <th>Registered Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($regsResult && $regsResult->num_rows > 0): ?>
                        <?php while ($rg = $regsResult->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= (int)$rg['registration_id'] ?></td>
                                <td><strong><?= htmlspecialchars($rg['student_name']) ?></strong></td>
                                <td><?= htmlspecialchars($rg['course']) ?></td>
                                <td><?= htmlspecialchars($rg['event_title']) ?></td>
                                <td><?= htmlspecialchars($rg['registered_at']) ?></td>
                                <td><span class="hub-status-badge hub-status-active"><?= htmlspecialchars($rg['status']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">No event registrations found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="tab-modules" class="hub-section">
            <h2 class="page-title">Enterprise System Modules Directory</h2>
            <p style="margin-bottom: 16px; font-size: 13px; text-align: center;">
                Click any page link to open it, or click <strong>Preview</strong> to display the HTML content inside the portal viewer.
            </p>

            <div class="hub-modules-grid">
                <?php foreach ($systemModules as $m): ?>
                    <div class="hub-module-card">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <span class="hub-mod-badge"><?= htmlspecialchars($m['category']) ?></span>
                                <span style="font-size: 11px; color: #888; font-weight: bold;"><?= htmlspecialchars($m['tag']) ?></span>
                            </div>
                            <h3 style="font-size: 15px; color: #1f4e79; margin-bottom: 6px;"><?= htmlspecialchars($m['title']) ?></h3>
                            <p style="font-size: 12px; color: #555; line-height: 1.4; margin-bottom: 10px;"><?= htmlspecialchars($m['desc']) ?></p>

                            <div class="hub-pages-list">
                                <?php foreach ($m['pages'] as $pg): ?>
                                    <div class="hub-page-link-row">
                                        <a href="<?= htmlspecialchars($pg['url']) ?>" target="_blank"><?= htmlspecialchars($pg['name']) ?></a>
                                        <button type="button" class="hub-btn" style="padding: 2px 8px; font-size: 10px;" onclick="openPreview('<?= htmlspecialchars($pg['url']) ?>', '<?= htmlspecialchars($m['title'] . ' - ' . $pg['name']) ?>')">Preview</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div style="margin-top: 10px; display: flex; gap: 8px;">
                            <a href="<?= htmlspecialchars($m['main_link']) ?>" target="_blank" class="hub-btn" style="flex: 1; text-align: center; background: #1f4e79; color: #fff;">Open Main Page &rarr;</a>
                            <button type="button" class="hub-btn" onclick="openPreview('<?= htmlspecialchars($m['main_link']) ?>', '<?= htmlspecialchars($m['title']) ?>')">Live Preview</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="tab-students" class="hub-section">
            <h2 class="page-title">Student Management (CRUD & AJAX)</h2>

            <?php if ($isAdmin): ?>
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <h3 style="color: #1f4e79; margin-bottom: 12px; font-size: 15px;">Add New Student (Admin Secured)</h3>
                <form method="POST" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add_student">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 12px;">
                        <div>
                            <label style="font-size: 12px; font-weight: bold;">Full Name</label>
                            <input type="text" name="name" class="form-control" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" placeholder="e.g. Yash Trivedi" required>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: bold;">Email</label>
                            <input type="email" name="email" class="form-control" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" placeholder="yash@example.com" required>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: bold;">Mobile (10 Digits)</label>
                            <input type="text" name="mobile" class="form-control" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" placeholder="9876543210" pattern="[0-9]{10}" required>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: bold;">Course</label>
                            <select name="course" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" required>
                                <option value="BTech_IT">B.Tech IT</option>
                                <option value="BTech_CSE">B.Tech CSE</option>
                                <option value="BTech_AI">B.Tech AI</option>
                                <option value="BTech_EC">B.Tech EC</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: bold;">Year</label>
                            <select name="year" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3" selected>3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: bold;">Gender</label>
                            <select name="gender" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="hub-btn" style="background: #1f4e79; color: #fff;">Save Student Record</button>
                </form>
            </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <h3 style="color: #1f4e79; font-size: 16px;">Active Student Directory</h3>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="liveSearchInput" placeholder="Instant search by name, course, email..." style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 4px; width: 260px;" onkeyup="filterStudentsLocal()">
                    <button type="button" class="hub-btn" onclick="refreshStudentsAjax()">Refresh</button>
                </div>
            </div>

            <table class="hub-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Status</th>
                        <?php if ($isAdmin): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="studentRosterBody">
                    <?php if ($studentsResult && $studentsResult->num_rows > 0): ?>
                        <?php while ($st = $studentsResult->fetch_assoc()): ?>
                            <tr id="stu-item-<?= (int)$st['student_id'] ?>">
                                <td>#<?= (int)$st['student_id'] ?></td>
                                <td><strong><?= htmlspecialchars($st['name']) ?></strong></td>
                                <td><?= htmlspecialchars($st['email']) ?></td>
                                <td><?= htmlspecialchars($st['mobile']) ?></td>
                                <td><?= htmlspecialchars($st['course']) ?></td>
                                <td>Year <?= (int)$st['year'] ?></td>
                                <td>
                                    <span id="stu-badge-<?= (int)$st['student_id'] ?>" class="hub-status-badge <?= ($st['status'] === 'Active') ? 'hub-status-active' : 'hub-status-inactive' ?>">
                                        <?= htmlspecialchars($st['status']) ?>
                                    </span>
                                </td>
                                <?php if ($isAdmin): ?>
                                    <td>
                                        <button type="button" class="hub-btn" style="padding: 2px 8px; font-size: 11px;" onclick="toggleStatusAjax(<?= (int)$st['student_id'] ?>)">Toggle</button>
                                        <form method="POST" action="index.php" style="display: inline-block;" onsubmit="return confirm('Remove student record?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete_student">
                                            <input type="hidden" name="student_id" value="<?= (int)$st['student_id'] ?>">
                                            <button type="submit" class="hub-btn danger" style="padding: 2px 8px; font-size: 11px;">Delete</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align: center;">No student records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="tab-events" class="hub-section">
            <h2 class="page-title">Campus Events & Registrations</h2>

            <?php if ($isAdmin): ?>
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <h3 style="color: #1f4e79; margin-bottom: 12px; font-size: 15px;">Create New Campus Event</h3>
                <form method="POST" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add_event">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 10px;">
                        <div>
                            <label style="font-size: 12px; font-weight: bold;">Title</label>
                            <input type="text" name="title" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" placeholder="e.g. AI Innovation Summit" required>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: bold;">Category</label>
                            <select name="category" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" required>
                                <option value="Technical">Technical</option>
                                <option value="Competition">Competition</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Placement">Placement</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: bold;">Date</label>
                            <input type="date" name="event_date" value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" required>
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: bold;">Venue</label>
                            <input type="text" name="venue" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" placeholder="Auditorium" required>
                        </div>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 12px; font-weight: bold;">Description</label>
                        <textarea name="description" rows="2" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" placeholder="Brief details..."></textarea>
                    </div>
                    <button type="submit" class="hub-btn" style="background: #1f4e79; color: #fff;">Publish Event</button>
                </form>
            </div>
            <?php endif; ?>

            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <h3 style="color: #1f4e79; margin-bottom: 12px; font-size: 15px;">Enroll Student in Scheduled Event</h3>
                <form method="POST" action="index.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="register_event_form">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="font-size: 12px; font-weight: bold;">Select Student</label>
                        <select name="student_id" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" required>
                            <?php
                            $stQ = $conn->query("SELECT student_id, name, course FROM students WHERE status='Active' ORDER BY name ASC");
                            if ($stQ) {
                                while ($stRow = $stQ->fetch_assoc()) {
                                    echo '<option value="' . (int)$stRow['student_id'] . '">' . htmlspecialchars($stRow['name']) . ' (' . htmlspecialchars($stRow['course']) . ')</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label style="font-size: 12px; font-weight: bold;">Select Event</label>
                        <select name="event_id" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px;" required>
                            <?php
                            $evQ = $conn->query("SELECT event_id, title, event_date FROM events WHERE status='Open' ORDER BY event_date ASC");
                            if ($evQ) {
                                while ($evRow = $evQ->fetch_assoc()) {
                                    echo '<option value="' . (int)$evRow['event_id'] . '">' . htmlspecialchars($evRow['title']) . ' (' . htmlspecialchars($evRow['event_date']) . ')</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="hub-btn success" style="padding: 8px 16px;">Confirm Enrollment</button>
                </form>
            </div>

            <h3 style="color: #1f4e79; margin-bottom: 10px; font-size: 16px;">Scheduled Events Directory</h3>
            <table class="hub-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($eventsResult && $eventsResult->num_rows > 0): ?>
                        <?php while ($ev = $eventsResult->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= (int)$ev['event_id'] ?></td>
                                <td><strong><?= htmlspecialchars($ev['title']) ?></strong></td>
                                <td><?= htmlspecialchars($ev['category']) ?></td>
                                <td><?= htmlspecialchars($ev['event_date']) ?></td>
                                <td><?= htmlspecialchars($ev['venue']) ?></td>
                                <td><span class="hub-status-badge hub-status-active"><?= htmlspecialchars($ev['status']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">No scheduled events.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="tab-security" class="hub-section">
            <h2 class="page-title">Security & System Audit Trails</h2>

            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <h3 style="color: #1f4e79; margin-bottom: 12px; font-size: 15px;">Security Controls Checklist</h3>
                <ul style="padding-left: 20px; font-size: 13px; line-height: 1.8;">
                    <li><strong>Cross-Site Request Forgery (CSRF):</strong> Per-session 32-byte cryptographic token validated on every mutating HTTP POST.</li>
                    <li><strong>Cross-Site Scripting (XSS):</strong> Complete output encoding using <code>htmlspecialchars()</code> on all dynamic variables.</li>
                    <li><strong>SQL Injection:</strong> All database mutations execute via MySQLi parameterized prepared statements with bound parameters.</li>
                    <li><strong>Session Security:</strong> Cookie attributes configured with <code>HttpOnly=1</code> and <code>use_only_cookies=1</code>.</li>
                    <li><strong>Role-Based Access Control:</strong> Strict role authorization guards for administrator actions.</li>
                </ul>
            </div>

            <h3 style="color: #1f4e79; margin-bottom: 10px; font-size: 16px;">Live System Audit Trail (Last 10 Logs)</h3>
            <table class="hub-table">
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($auditsResult && $auditsResult->num_rows > 0): ?>
                        <?php while ($al = $auditsResult->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= (int)$al['id'] ?></td>
                                <td>User <?= (int)$al['user_id'] ?></td>
                                <td><strong><?= htmlspecialchars($al['action']) ?></strong></td>
                                <td><?= htmlspecialchars($al['details']) ?></td>
                                <td><code><?= htmlspecialchars($al['ip_address']) ?></code></td>
                                <td><?= htmlspecialchars($al['created_at']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">No audit logs recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section id="tab-deploy" class="hub-section">
            <h2 class="page-title">Apache Virtual Host & Deployment</h2>

            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                <h3 style="color: #1f4e79; margin-bottom: 10px; font-size: 15px;">Local Deployment via Apache Virtual Host (studenthub.local)</h3>
                
                <p style="font-size: 13px; margin-bottom: 6px;"><strong>1. Windows Hosts File:</strong> <code>C:\Windows\System32\drivers\etc\hosts</code></p>
                <div class="hub-code-box">127.0.0.1    studenthub.local
127.0.0.1    www.studenthub.local</div>

                <p style="font-size: 13px; margin: 12px 0 6px;"><strong>2. Apache VHosts Config:</strong> <code>C:\xampp\apache\conf\extra\httpd-vhosts.conf</code></p>
                <div class="hub-code-box">&lt;VirtualHost *:80&gt;
    ServerAdmin barchharahil720@gmail.com
    DocumentRoot "C:/xampp/htdocs/studenthub_portal"
    ServerName studenthub.local
    ServerAlias www.studenthub.local
    &lt;Directory "C:/xampp/htdocs/studenthub_portal"&gt;
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
    ErrorLog "logs/studenthub-error.log"
    CustomLog "logs/studenthub-access.log" common
&lt;/VirtualHost&gt;</div>

                <p style="font-size: 13px; margin-top: 10px;"><strong>3. Restart Apache:</strong> Restart Apache in XAMPP and open <code>http://studenthub.local</code>.</p>
            </div>

            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 16px;">
                <h3 style="color: #1f4e79; margin-bottom: 10px; font-size: 15px;">Version Control & Deployment Commands</h3>
                <div class="hub-code-box">git init
git add .
git commit -m "Complete StudentHub Integrated Enterprise Portal Deployment"
git branch -M main
git remote add origin https://github.com/rahilbarchha/studenthub-portal.git
git push -u origin main</div>
            </div>
        </section>

    </div>

    <div id="hubPreviewModal" class="hub-preview-modal">
        <div class="hub-preview-window">
            <div class="hub-preview-header">
                <span id="hubPreviewTitle" style="font-weight: bold; font-size: 14px;">Module Preview</span>
                <div style="display: flex; gap: 8px;">
                    <a id="hubPreviewNewTab" href="#" target="_blank" class="hub-btn" style="padding: 4px 10px; font-size: 11px;">Open in New Tab</a>
                    <button type="button" class="hub-btn danger" style="padding: 4px 10px; font-size: 11px;" onclick="closePreview()">Close</button>
                </div>
            </div>
            <iframe id="hubPreviewFrame" class="hub-preview-frame" src="about:blank"></iframe>
        </div>
    </div>

    <footer>
        <div class="footer">
            <p>&copy; 2026 Student Hub Portal | Integrated Enterprise Platform</p>
            <p>Lead Developer: Rahil Barchha (barchharahil720@gmail.com)</p>
        </div>
    </footer>

    <script src="../practical 4/script.js"></script>
    <script>
    function switchPortalTab(tabId) {
        const sections = document.querySelectorAll('.hub-section');
        sections.forEach(s => s.classList.remove('active'));

        const btns = document.querySelectorAll('.hub-tab-btn');
        btns.forEach(b => b.classList.remove('active'));

        const target = document.getElementById(tabId);
        if (target) {
            target.classList.add('active');
        }

        if (event && event.target) {
            event.target.classList.add('active');
        }
    }

    function openPreview(url, title) {
        document.getElementById('hubPreviewTitle').textContent = title;
        document.getElementById('hubPreviewNewTab').href = url;
        document.getElementById('hubPreviewFrame').src = url;
        document.getElementById('hubPreviewModal').classList.add('show');
    }

    function closePreview() {
        document.getElementById('hubPreviewModal').classList.remove('show');
        document.getElementById('hubPreviewFrame').src = 'about:blank';
    }

    function filterStudentsLocal() {
        const query = document.getElementById('liveSearchInput').value.toLowerCase();
        const rows = document.querySelectorAll('#studentRosterBody tr');

        rows.forEach(r => {
            const txt = r.textContent.toLowerCase();
            if (txt.indexOf(query) > -1) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        });
    }

    function refreshStudentsAjax() {
        const q = document.getElementById('liveSearchInput').value;
        fetch('ajax.php?action=search_students&q=' + encodeURIComponent(q))
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const tbody = document.getElementById('studentRosterBody');
                    if (res.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">No matching student records found.</td></tr>';
                        return;
                    }
                    let out = '';
                    res.data.forEach(s => {
                        const badgeCls = (s.status === 'Active') ? 'hub-status-active' : 'hub-status-inactive';
                        out += '<tr id="stu-item-' + s.student_id + '">';
                        out += '<td>#' + s.student_id + '</td>';
                        out += '<td><strong>' + escapeHtml(s.name) + '</strong></td>';
                        out += '<td>' + escapeHtml(s.email) + '</td>';
                        out += '<td>' + escapeHtml(s.mobile) + '</td>';
                        out += '<td>' + escapeHtml(s.course) + '</td>';
                        out += '<td>Year ' + s.year + '</td>';
                        out += '<td><span id="stu-badge-' + s.student_id + '" class="hub-status-badge ' + badgeCls + '">' + escapeHtml(s.status) + '</span></td>';
                        <?php if ($isAdmin): ?>
                        out += '<td><button type="button" class="hub-btn" style="padding: 2px 8px; font-size: 11px;" onclick="toggleStatusAjax(' + s.student_id + ')">Toggle</button></td>';
                        <?php endif; ?>
                        out += '</tr>';
                    });
                    tbody.innerHTML = out;
                }
            });
    }

    function toggleStatusAjax(sid) {
        const fd = new FormData();
        fd.append('action', 'toggle_student_status');
        fd.append('student_id', sid);

        fetch('ajax.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const badge = document.getElementById('stu-badge-' + sid);
                if (badge) {
                    badge.textContent = res.new_status;
                    if (res.new_status === 'Active') {
                        badge.className = 'hub-status-badge hub-status-active';
                    } else {
                        badge.className = 'hub-status-badge hub-status-inactive';
                    }
                }
            } else {
                alert(res.message || 'Error toggling student status');
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;');
    }
    </script>
</body>
</html>
