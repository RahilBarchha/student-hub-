<?php
session_start();
require_once __DIR__ . '/db.php';

$success = "";
$error = "";

if (isset($_GET['download']) && $_GET['download'] === 'sql') {
    $file = __DIR__ . '/studenthub_p11.sql';
    if (file_exists($file)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="studenthub_p11.sql"');
        readfile($file);
        exit;
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $search = trim($_GET['q'] ?? '');
    $filterCourse = trim($_GET['course'] ?? '');
    $filterStatus = trim($_GET['status'] ?? '');
    $filterGender = trim($_GET['gender'] ?? '');

    $sql = "SELECT student_id, name, email, mobile, course, year, gender, status, created_at FROM students WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR email LIKE ? OR mobile LIKE ? OR course LIKE ?)";
        $searchTerm = "%" . $search . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ssss";
    }

    if (!empty($filterCourse)) {
        $sql .= " AND course = ?";
        $params[] = $filterCourse;
        $types .= "s";
    }

    if (!empty($filterStatus)) {
        $sql .= " AND status = ?";
        $params[] = $filterStatus;
        $types .= "s";
    }

    if (!empty($filterGender)) {
        $sql .= " AND gender = ?";
        $params[] = $filterGender;
        $types .= "s";
    }

    $sql .= " ORDER BY student_id DESC";
    $stmtExp = $conn->prepare($sql);
    if (!empty($types)) {
        $stmtExp->bind_param($types, ...$params);
    }
    $stmtExp->execute();
    $expRes = $stmtExp->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=students_export_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student ID', 'Name', 'Email', 'Mobile', 'Course', 'Year', 'Gender', 'Status', 'Created At']);

    while ($row = $expRes->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    $stmtExp->close();
    exit;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $targetId = (int)($_GET['id'] ?? 0);

    if ($action === 'soft_delete' && $targetId > 0) {
        $delStmt = $conn->prepare("UPDATE students SET status = 'Inactive' WHERE student_id = ?");
        $delStmt->bind_param("i", $targetId);
        if ($delStmt->execute()) {
            $success = "Student #$targetId status changed to Inactive (Soft Deleted).";
        } else {
            $error = "Soft delete failed: " . $conn->error;
        }
        $delStmt->close();
    } elseif ($action === 'restore' && $targetId > 0) {
        $resStmt = $conn->prepare("UPDATE students SET status = 'Active' WHERE student_id = ?");
        $resStmt->bind_param("i", $targetId);
        if ($resStmt->execute()) {
            $success = "Student #$targetId successfully restored to Active status.";
        } else {
            $error = "Restore failed: " . $conn->error;
        }
        $resStmt->close();
    } elseif ($action === 'delete' && $targetId > 0) {
        $permStmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $permStmt->bind_param("i", $targetId);
        if ($permStmt->execute()) {
            $success = "Student #$targetId permanently deleted from the database.";
        } else {
            $error = "Permanent delete failed: " . $conn->error;
        }
        $permStmt->close();
    }
}

$editStudent = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmtEdit = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmtEdit->bind_param("i", $editId);
    $stmtEdit->execute();
    $editStudent = $stmtEdit->get_result()->fetch_assoc();
    $stmtEdit->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formAction = $_POST["form_action"] ?? "add";
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mobile = trim($_POST["mobile"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $year = (int)($_POST["year"] ?? 1);
    $gender = trim($_POST["gender"] ?? "Male");
    $status = trim($_POST["status"] ?? "Active");

    if (empty($name) || !preg_match("/^[a-zA-Z\s]{2,50}$/", $name)) {
        $error = "Full Name must contain only letters and spaces (2-50 characters).";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (empty($mobile) || !preg_match("/^[6-9]\d{9}$/", $mobile)) {
        $error = "Mobile Number must be 10 digits starting with 6, 7, 8, or 9.";
    } elseif (empty($course)) {
        $error = "Please select a valid course.";
    } else {
        if ($formAction === "edit") {
            $studentId = (int)($_POST["student_id"] ?? 0);

            $dupStmt = $conn->prepare("SELECT student_id FROM students WHERE email = ? AND student_id != ?");
            $dupStmt->bind_param("si", $email, $studentId);
            $dupStmt->execute();
            if ($dupStmt->get_result()->num_rows > 0) {
                $error = "Email address '$email' is already used by another student.";
            } else {
                $upStmt = $conn->prepare("UPDATE students SET name = ?, email = ?, mobile = ?, course = ?, year = ?, gender = ?, status = ? WHERE student_id = ?");
                $upStmt->bind_param("ssssissi", $name, $email, $mobile, $course, $year, $gender, $status, $studentId);
                if ($upStmt->execute()) {
                    $success = "Student #$studentId updated successfully.";
                    $editStudent = null;
                } else {
                    $error = "Update failed: " . $conn->error;
                }
                $upStmt->close();
            }
            $dupStmt->close();
        } else {
            $dupStmt = $conn->prepare("SELECT student_id FROM students WHERE email = ?");
            $dupStmt->bind_param("s", $email);
            $dupStmt->execute();
            if ($dupStmt->get_result()->num_rows > 0) {
                $error = "Email address '$email' is already registered in the system.";
            } else {
                $insStmt = $conn->prepare("INSERT INTO students (name, email, mobile, course, year, gender, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insStmt->bind_param("ssssiss", $name, $email, $mobile, $course, $year, $gender, $status);
                if ($insStmt->execute()) {
                    $newId = $insStmt->insert_id;
                    $success = "New student #$newId ('$name') added successfully.";
                } else {
                    $error = "Insertion failed: " . $conn->error;
                }
                $insStmt->close();
            }
            $dupStmt->close();
        }
    }
}

$search = trim($_GET['q'] ?? '');
$filterCourse = trim($_GET['course'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterGender = trim($_GET['gender'] ?? '');

$sql = "SELECT * FROM students WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR mobile LIKE ? OR course LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

if (!empty($filterCourse)) {
    $sql .= " AND course = ?";
    $params[] = $filterCourse;
    $types .= "s";
}

if (!empty($filterStatus)) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if (!empty($filterGender)) {
    $sql .= " AND gender = ?";
    $params[] = $filterGender;
    $types .= "s";
}

$sql .= " ORDER BY student_id DESC";
$stmtList = $conn->prepare($sql);
if (!empty($types)) {
    $stmtList->bind_param($types, ...$params);
}
$stmtList->execute();
$listResult = $stmtList->get_result();
$students = [];
while ($row = $listResult->fetch_assoc()) {
    $students[] = $row;
}
$stmtList->close();

$totRes = $conn->query("SELECT COUNT(*) AS total FROM students");
$totalCount = $totRes ? (int)$totRes->fetch_assoc()['total'] : 0;

$actRes = $conn->query("SELECT COUNT(*) AS active_cnt FROM students WHERE status = 'Active'");
$activeCount = $actRes ? (int)$actRes->fetch_assoc()['active_cnt'] : 0;

$inactRes = $conn->query("SELECT COUNT(*) AS inact_cnt FROM students WHERE status = 'Inactive'");
$inactiveCount = $inactRes ? (int)$inactRes->fetch_assoc()['inact_cnt'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management CRUD - Student Hub</title>
    <link rel="stylesheet" href="../practical 3/style.css">
    <style>
        .filter-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0;
            width: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }
        @media (max-width: 900px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            width: 100%;
            margin: 20px 0;
        }
        .stat-card {
            background: var(--surface);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            font-size: 26px;
            color: var(--primary);
            margin-bottom: 4px;
        }
        .stat-card p {
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
        }
        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
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
            font-size: 13px;
        }
        .data-table th, .data-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .data-table th {
            background: var(--primary);
            color: #ffffff;
            font-weight: 600;
            white-space: nowrap;
        }
        .data-table tr:hover {
            background: rgba(74, 144, 226, 0.08);
        }
        .btn-action {
            display: inline-block;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-edit { background: #3b82f6; color: #fff; }
        .btn-softdel { background: #f59e0b; color: #fff; }
        .btn-restore { background: #10b981; color: #fff; }
        .btn-permdel { background: #ef4444; color: #fff; }
        .action-flex {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .form-modal-box {
            background: var(--surface);
            border: 2px solid var(--primary);
            border-radius: 8px;
            padding: 24px;
            margin: 20px 0;
            width: 100%;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    <header>
        <div class="header">
            <h1>Student Hub Portal</h1>
            <p>Student Management & Academic Records</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu">☰</button>
        <div class="site-mini-title">Student Management CRUD</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle">
            <span>Dark Mode</span>
        </label>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Main navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="index.php" class="nav-btn active">Student CRUD</a>
        <a href="../practical 13/index.php" class="nav-btn">Fetch API CRUD</a>
        <a href="../practical 12/index.php" class="nav-btn">Events CRUD</a>
        <a href="../practical 9/index.php" class="nav-btn">Register</a>
        <a href="../practical 10/admin_dashboard.php" class="nav-btn">Admin Portal</a>
        <a href="../practical 2/contact.html" class="nav-btn">Contact</a>
        <a href="../practical 2/About.html" class="nav-btn">About</a>
    </nav>

    <main class="container">

        <?php if (!empty($success)): ?>
            <div class="form-message success" style="display: block; margin-bottom: 20px;">
                <strong>✓ Success:</strong> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="form-message error" style="display: block; margin-bottom: 20px;">
                <strong>✗ Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $totalCount ?></h3>
                <p>Total Seeded Students</p>
            </div>
            <div class="stat-card">
                <h3 style="color: #166534;"><?= $activeCount ?></h3>
                <p>Active Students</p>
            </div>
            <div class="stat-card">
                <h3 style="color: #991b1b;"><?= $inactiveCount ?></h3>
                <p>Inactive / Soft-Deleted</p>
            </div>
            <div class="stat-card">
                <h3><?= count($students) ?></h3>
                <p>Matching Search Results</p>
            </div>
        </div>

        <section class="form-modal-box" id="studentFormSection">
            <h3 style="color: var(--primary); margin-bottom: 15px;">
                <?= $editStudent ? "Edit Student #" . htmlspecialchars($editStudent['student_id']) : "Add New Student Record" ?>
            </h3>
            <form action="index.php" method="POST">
                <input type="hidden" name="form_action" value="<?= $editStudent ? 'edit' : 'add' ?>">
                <?php if ($editStudent): ?>
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($editStudent['student_id']) ?>">
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required-star">*</span></label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="form-control" 
                            placeholder="e.g. Rahul Sharma"
                            value="<?= htmlspecialchars($editStudent['name'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="required-star">*</span></label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="student@example.com"
                            value="<?= htmlspecialchars($editStudent['email'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="mobile">Mobile Number <span class="required-star">*</span></label>
                        <input 
                            type="tel" 
                            id="mobile" 
                            name="mobile" 
                            class="form-control" 
                            placeholder="10-digit number"
                            maxlength="10"
                            value="<?= htmlspecialchars($editStudent['mobile'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="course">Course / Branch <span class="required-star">*</span></label>
                        <select id="course" name="course" class="form-control" required>
                            <option value="">-- Select Course --</option>
                            <?php
                            $courses = ['BTech_CSE' => 'B.Tech CSE', 'BTech_IT' => 'B.Tech IT', 'BTech_AI' => 'B.Tech AI', 'BCA' => 'BCA', 'MCA' => 'MCA'];
                            $selCourse = $editStudent['course'] ?? '';
                            foreach ($courses as $cVal => $cLabel) {
                                $selected = ($selCourse === $cVal) ? 'selected' : '';
                                echo "<option value=\"$cVal\" $selected>$cLabel</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="year">Academic Year <span class="required-star">*</span></label>
                        <select id="year" name="year" class="form-control" required>
                            <?php
                            $selYear = (int)($editStudent['year'] ?? 1);
                            for ($y = 1; $y <= 4; $y++) {
                                $selected = ($selYear === $y) ? 'selected' : '';
                                echo "<option value=\"$y\" $selected>{$y}st/nd/rd/th Year</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="gender">Gender <span class="required-star">*</span></label>
                        <select id="gender" name="gender" class="form-control" required>
                            <?php
                            $selGender = $editStudent['gender'] ?? 'Male';
                            foreach (['Male', 'Female', 'Other'] as $g) {
                                $selected = ($selGender === $g) ? 'selected' : '';
                                echo "<option value=\"$g\" $selected>$g</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Record Status <span class="required-star">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <?php
                            $selStatus = $editStudent['status'] ?? 'Active';
                            foreach (['Active', 'Inactive'] as $st) {
                                $selected = ($selStatus === $st) ? 'selected' : '';
                                echo "<option value=\"$st\" $selected>$st</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="button-area" style="margin-top: 20px;">
                    <button type="submit" class="btn" style="flex: 2;">
                        <?= $editStudent ? "Update Student Record" : "Save Student Record" ?>
                    </button>
                    <?php if ($editStudent): ?>
                        <a href="index.php" class="btn" style="flex: 1; background: #64748b; text-align: center;">Cancel Edit</a>
                    <?php else: ?>
                        <button type="reset" class="btn" style="flex: 1; background: #64748b;">Reset Fields</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="filter-panel">
            <h3 style="color: var(--primary); margin-bottom: 12px; font-size: 16px;">🔍 Search & Dynamic Filter</h3>
            <form action="index.php" method="GET" class="filter-grid">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="q" style="font-size: 12px;">Search by Keyword</label>
                    <input 
                        type="text" 
                        id="q" 
                        name="q" 
                        class="form-control" 
                        placeholder="Search by name, email, mobile, or course..."
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_course" style="font-size: 12px;">Filter Course</label>
                    <select id="filter_course" name="course" class="form-control">
                        <option value="">All Courses</option>
                        <option value="BTech_CSE" <?= $filterCourse === 'BTech_CSE' ? 'selected' : '' ?>>B.Tech CSE</option>
                        <option value="BTech_IT" <?= $filterCourse === 'BTech_IT' ? 'selected' : '' ?>>B.Tech IT</option>
                        <option value="BTech_AI" <?= $filterCourse === 'BTech_AI' ? 'selected' : '' ?>>B.Tech AI</option>
                        <option value="BCA" <?= $filterCourse === 'BCA' ? 'selected' : '' ?>>BCA</option>
                        <option value="MCA" <?= $filterCourse === 'MCA' ? 'selected' : '' ?>>MCA</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_status" style="font-size: 12px;">Filter Status</label>
                    <select id="filter_status" name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Active" <?= $filterStatus === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $filterStatus === 'Inactive' ? 'selected' : '' ?>>Inactive (Soft-Deleted)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_gender" style="font-size: 12px;">Filter Gender</label>
                    <select id="filter_gender" name="gender" class="form-control">
                        <option value="">All Genders</option>
                        <option value="Male" <?= $filterGender === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $filterGender === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>

                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn" style="padding: 10px 16px;">Apply</button>
                    <a href="index.php" class="btn" style="padding: 10px 14px; background: #64748b; text-decoration: none;">Reset</a>
                </div>
            </form>
        </section>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; flex-wrap: wrap; gap: 10px; width: 100%;">
            <h3 style="color: var(--primary);">Student Records Directory (Showing <?= count($students) ?>)</h3>
            <div style="display: flex; gap: 8px;">
                <a href="index.php?export=csv<?= !empty($search)?'&q='.urlencode($search):'' ?><?= !empty($filterCourse)?'&course='.urlencode($filterCourse):'' ?><?= !empty($filterStatus)?'&status='.urlencode($filterStatus):'' ?>" class="btn" style="background: #16a34a; font-size: 13px;">📥 Export CSV</a>
                <a href="index.php?download=sql" class="btn" style="background: #475569; font-size: 13px;">💾 Download SQL Dump</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student Name</th>
                        <th>Email Address</th>
                        <th>Mobile</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--muted); padding: 25px;">
                                No student records found matching your search and filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $st): ?>
                            <tr>
                                <td><?= htmlspecialchars($st['student_id']) ?></td>
                                <td><strong><?= htmlspecialchars($st['name']) ?></strong></td>
                                <td><?= htmlspecialchars($st['email']) ?></td>
                                <td><?= htmlspecialchars($st['mobile']) ?></td>
                                <td><span class="badge"><?= htmlspecialchars($st['course']) ?></span></td>
                                <td>Year <?= htmlspecialchars($st['year']) ?></td>
                                <td><?= htmlspecialchars($st['gender']) ?></td>
                                <td>
                                    <span class="badge-status <?= $st['status'] === 'Active' ? 'status-active' : 'status-inactive' ?>">
                                        <?= htmlspecialchars($st['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-flex">
                                        <a href="index.php?edit_id=<?= $st['student_id'] ?>#studentFormSection" class="btn-action btn-edit" title="Edit Student Record">Edit</a>
                                        
                                        <?php if ($st['status'] === 'Active'): ?>
                                            <a 
                                                href="index.php?action=soft_delete&id=<?= $st['student_id'] ?>" 
                                                class="btn-action btn-softdel" 
                                                onclick="return confirm('Are you sure you want to soft delete student #<?= $st['student_id'] ?> (<?= htmlspecialchars(addslashes($st['name'])) ?>)? Status will be set to Inactive.');"
                                                title="Soft Delete (Change status to Inactive)"
                                            >Soft Delete</a>
                                        <?php else: ?>
                                            <a 
                                                href="index.php?action=restore&id=<?= $st['student_id'] ?>" 
                                                class="btn-action btn-restore" 
                                                onclick="return confirm('Restore student #<?= $st['student_id'] ?> back to Active status?');"
                                                title="Restore to Active"
                                            >Restore</a>
                                        <?php endif; ?>

                                        <a 
                                            href="index.php?action=delete&id=<?= $st['student_id'] ?>" 
                                            class="btn-action btn-permdel" 
                                            onclick="return confirm('WARNING: Are you sure you want to PERMANENTLY delete student #<?= $st['student_id'] ?> from the database? This action cannot be undone.');"
                                            title="Permanent Delete"
                                        >Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <footer>
        <div class="footer">
            <p>&copy; 2026 Student Hub Portal | All Rights Reserved.</p>
        </div>
    </footer>

    <script src="../practical 4/script.js"></script>
</body>
</html>
