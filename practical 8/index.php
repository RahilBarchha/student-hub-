<?php
require_once __DIR__ . '/db.php';

if (isset($_GET['download']) && $_GET['download'] === 'sql') {
    $sqlFile = __DIR__ . '/studenthub.sql';
    if (file_exists($sqlFile)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="studenthub.sql"');
        readfile($sqlFile);
        exit;
    }
}

$connStatus = "Connected successfully to MySQL Database: studenthub";

$stmtStudents = $pdo->prepare("SELECT * FROM students ORDER BY student_id ASC");
$stmtStudents->execute();
$students = $stmtStudents->fetchAll();

$stmtEvents = $pdo->prepare("SELECT * FROM events ORDER BY event_date ASC");
$stmtEvents->execute();
$events = $stmtEvents->fetchAll();

$stmtReg = $pdo->prepare("
    SELECT 
        r.registration_id,
        s.name AS student_name,
        s.email,
        e.title AS event_title,
        e.venue,
        e.event_date,
        r.status,
        r.registered_at
    FROM registrations r
    JOIN students s ON r.student_id = s.student_id
    JOIN events e ON r.event_id = e.event_id
    ORDER BY r.registration_id ASC
");
$stmtReg->execute();
$registrations = $stmtReg->fetchAll();

$testStudentId = 2;
$stmtProcedure = $pdo->prepare("CALL sp_get_student_events(?)");
$stmtProcedure->execute([$testStudentId]);
$procedureResults = $stmtProcedure->fetchAll();
$stmtProcedure->closeCursor();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection & Management - Practical 8</title>
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
            background: #dcfce7;
            color: #166534;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            width: 100%;
            margin: 20px 0;
        }
        .stat-card {
            background: var(--surface);
            padding: 18px;
            border-radius: 8px;
            border: 1px solid var(--border);
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            font-size: 26px;
            color: var(--primary);
            margin-bottom: 5px;
        }
        .stat-card p {
            color: var(--muted);
            font-size: 14px;
            font-weight: bold;
        }
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
            <p>Database Management & MySQL Connection</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu">☰</button>
        <div class="site-mini-title">Database Hub</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle">
            <span>Dark Mode</span>
        </label>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Main navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="../practical 2/register.html" class="nav-btn">Register</a>
        <a href="../practical 2/contact.html" class="nav-btn">Contact</a>
        <a href="../practical 6/index.html" class="nav-btn">Data Hub</a>
        <a href="../practical 7/process.php" class="nav-btn">PHP Storage</a>
        <a href="index.php" class="nav-btn active">Database</a>
    </nav>

    <main class="container">
        <h2 class="page-title">MySQL Database Connection & Schema Verification</h2>

        <div class="form-message success" style="display: block; margin-bottom: 20px;">
            <strong>✓ Status:</strong> <?= htmlspecialchars($connStatus) ?> (PDO & Prepared Statements Active)
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= count($students) ?></h3>
                <p>Total Students</p>
            </div>
            <div class="stat-card">
                <h3><?= count($events) ?></h3>
                <p>Active Events</p>
            </div>
            <div class="stat-card">
                <h3><?= count($registrations) ?></h3>
                <p>Total Registrations</p>
            </div>
        </div>

        <div class="actions-bar">
            <a href="index.php?download=sql" class="btn" style="background: #16a34a;">Download SQL Dump</a>
            <a href="../practical 7/process.php" class="btn">View File Storage</a>
            <a href="../practical 2/register.html" class="btn" style="background: #475569;">Register Student</a>
        </div>

        <section class="result-box">
            <h3 style="color: var(--primary); margin-bottom: 8px;">1. Students Table (`students`)</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Gender</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['student_id']) ?></td>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td><?= htmlspecialchars($s['email']) ?></td>
                                <td><?= htmlspecialchars($s['mobile']) ?></td>
                                <td><span class="badge"><?= htmlspecialchars($s['course']) ?></span></td>
                                <td><?= htmlspecialchars($s['year']) ?></td>
                                <td><?= htmlspecialchars($s['gender']) ?></td>
                                <td><small><?= htmlspecialchars($s['created_at']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="result-box" style="margin-top: 30px;">
            <h3 style="color: var(--primary); margin-bottom: 8px;">2. Events Table (`events`)</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Event Date</th>
                            <th>Venue</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td><?= htmlspecialchars($ev['event_id']) ?></td>
                                <td><strong><?= htmlspecialchars($ev['title']) ?></strong></td>
                                <td><span class="badge"><?= htmlspecialchars($ev['category']) ?></span></td>
                                <td><?= htmlspecialchars($ev['event_date']) ?></td>
                                <td><?= htmlspecialchars($ev['venue']) ?></td>
                                <td><small><?= htmlspecialchars($ev['created_at']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="result-box" style="margin-top: 30px;">
            <h3 style="color: var(--primary); margin-bottom: 8px;">3. Event Registrations Table (`registrations` - Normalized FK Junction)</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reg ID</th>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Event Title</th>
                            <th>Venue</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Registered At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['registration_id']) ?></td>
                                <td><strong><?= htmlspecialchars($r['student_name']) ?></strong></td>
                                <td><?= htmlspecialchars($r['email']) ?></td>
                                <td><?= htmlspecialchars($r['event_title']) ?></td>
                                <td><?= htmlspecialchars($r['venue']) ?></td>
                                <td><?= htmlspecialchars($r['event_date']) ?></td>
                                <td><span class="badge"><?= htmlspecialchars($r['status']) ?></span></td>
                                <td><small><?= htmlspecialchars($r['registered_at']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="result-box" style="margin-top: 30px;">
            <h3 style="color: var(--primary); margin-bottom: 8px;">4. Stored Procedure Test (`CALL sp_get_student_events(2)`)</h3>
            <p style="color: var(--muted); font-size: 13px; margin-bottom: 10px;">
                Demonstrating Advanced Stored Procedure concept using PDO Prepared Statements.
            </p>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Status</th>
                            <th>Registered At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedureResults as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['student_name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['event_title']) ?></td>
                                <td><?= htmlspecialchars($row['event_date']) ?></td>
                                <td><?= htmlspecialchars($row['venue']) ?></td>
                                <td><span class="badge"><?= htmlspecialchars($row['status']) ?></span></td>
                                <td><small><?= htmlspecialchars($row['registered_at']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <footer>
        <div class="footer">
            <p>&copy; 2026 Student Hub Portal | All Rights Reserved.</p>
        </div>
    </footer>

    <script src="../practical 4/script.js"></script>
</body>
</html>
