<?php
require_once __DIR__ . '/auth.php';

check_auth('admin');

$notice = "";
if (isset($_GET['welcome'])) {
    $notice = "Welcome back, Administrator! Session initialized with regenerated ID.";
} elseif (isset($_GET['error']) && $_GET['error'] === 'forbidden') {
    $notice = "Access Denied: You were redirected back because that action is restricted.";
}

$activityQuery = $conn->query("SELECT id, user_id, action, details, ip_address, created_at FROM audit_logs ORDER BY id DESC LIMIT 25");
$activities = [];
if ($activityQuery) {
    while ($row = $activityQuery->fetch_assoc()) {
        $activities[] = $row;
    }
}

$allUsersQuery = $conn->query("SELECT id, username, fullname, email, course, role, last_login, created_at FROM users ORDER BY id DESC");
$allUsers = [];
if ($allUsersQuery) {
    while ($row = $allUsersQuery->fetch_assoc()) {
        $allUsers[] = $row;
    }
}

$studentsQuery = $conn->query("SELECT * FROM students ORDER BY student_id ASC");
$students = [];
if ($studentsQuery) {
    while ($row = $studentsQuery->fetch_assoc()) {
        $students[] = $row;
    }
}

$eventsQuery = $conn->query("SELECT * FROM events ORDER BY event_date ASC");
$events = [];
if ($eventsQuery) {
    while ($row = $eventsQuery->fetch_assoc()) {
        $events[] = $row;
    }
}

$regQuery = $conn->query("
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
$registrations = [];
if ($regQuery) {
    while ($row = $regQuery->fetch_assoc()) {
        $registrations[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Dashboard - Student Hub</title>
    <link rel="stylesheet" href="../practical 3/style.css">
    <style>
        .badge-role {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-admin {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-action {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .action-login { background: #dcfce7; color: #166534; }
        .action-logout { background: #e0e7ff; color: #3730a3; }
        .action-reg { background: #fef3c7; color: #92400e; }
        .action-fail { background: #fee2e2; color: #991b1b; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            width: 100%;
            margin: 20px 0;
        }
        .stat-card {
            background: var(--surface);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            text-align: left;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-card h4 {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .stat-card p {
            font-size: 18px;
            font-weight: bold;
            color: var(--primary);
            word-break: break-all;
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
            font-size: 13px;
        }
        .data-table th, .data-table td {
            padding: 12px 14px;
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
        .control-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 20px 0;
            width: 100%;
        }
    </style>
</head>
<body>

    <header>
        <div class="header">
            <h1>Student Hub Portal</h1>
            <p>Administrator Control Center & Database Management</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu">☰</button>
        <div class="site-mini-title">Admin Dashboard</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle">
            <span>Dark Mode</span>
        </label>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Main navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="admin_dashboard.php" class="nav-btn active">Admin Dashboard</a>
        <a href="../practical 14/index.php" class="nav-btn">RBAC Dashboard</a>
        <a href="../practical 13/index.php" class="nav-btn">Fetch API CRUD</a>
        <a href="../practical 11/index.php" class="nav-btn">Student CRUD</a>
        <a href="../practical 12/index.php" class="nav-btn">Events CRUD</a>
        <a href="../practical 6/index.html" class="nav-btn">Data Hub</a>
        <a href="../practical 7/process.php" class="nav-btn">PHP Storage</a>
        <a href="../practical 8/index.php" class="nav-btn">Database Hub</a>
        <a href="logout.php" class="nav-btn" style="color: #dc2626;">Logout</a>
    </nav>

    <main class="container">

        <h2 class="page-title">Administrator Dashboard</h2>

        <?php if (!empty($notice)): ?>
            <div class="form-message info" style="display: block; margin-bottom: 20px;">
                <strong>ℹ Notice:</strong> <?= htmlspecialchars($notice) ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h4>Authenticated Administrator</h4>
                <p><?= htmlspecialchars($_SESSION['fullname']) ?> <span class="badge-role badge-admin">Admin</span></p>
            </div>
            <div class="stat-card">
                <h4>Total Portal Users</h4>
                <p><?= count($allUsers) ?></p>
            </div>
            <div class="stat-card">
                <h4>Total Activity Logs</h4>
                <p><?= count($activities) ?></p>
            </div>
            <div class="stat-card">
                <h4>Database Events</h4>
                <p><?= count($events) ?></p>
            </div>
        </div>

        <div class="control-bar">
            <a href="admin_dashboard.php?simulate_timeout=1" class="btn" style="background: #ea580c;">Simulate Session Timeout</a>
            <a href="student_dashboard.php" class="btn" style="background: #475569;">Test Student View (RBAC Check)</a>
            <a href="logout.php" class="btn" style="background: #dc2626; margin-left: auto;">Logout Securely</a>
        </div>

        <section style="width: 100%; margin-top: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="color: var(--primary);">1. Live User Activity & Security Logs (`audit_logs`)</h3>
                <span class="badge-role">Live Tracking</span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Action</th>
                            <th>Activity Details</th>
                            <th>User ID</th>
                            <th>Client IP</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activities)): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 15px;">No activity logs recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($activities as $act): ?>
                                <?php
                                $badgeClass = "action-reg";
                                if (strpos($act['action'], 'LOGIN') !== false && strpos($act['action'], 'FAIL') === false) $badgeClass = "action-login";
                                elseif (strpos($act['action'], 'LOGOUT') !== false) $badgeClass = "action-logout";
                                elseif (strpos($act['action'], 'FAIL') !== false) $badgeClass = "action-fail";
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($act['id']) ?></td>
                                    <td><span class="badge-action <?= $badgeClass ?>"><?= htmlspecialchars($act['action']) ?></span></td>
                                    <td><?= htmlspecialchars($act['details']) ?></td>
                                    <td><?= htmlspecialchars($act['user_id'] ?? 'N/A') ?></td>
                                    <td><code><?= htmlspecialchars($act['ip_address']) ?></code></td>
                                    <td><small><?= htmlspecialchars($act['created_at']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section style="width: 100%; margin-top: 35px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="color: var(--primary);">2. Registered Accounts & Last Login Times (`users`)</h3>
                <span class="badge-role">Total Accounts: <?= count($allUsers) ?></span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Course</th>
                            <th>Role</th>
                            <th>Last Login</th>
                            <th>Registered At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['id']) ?></td>
                                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                <td><?= htmlspecialchars($u['fullname']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['course']) ?></td>
                                <td>
                                    <span class="badge-role <?= $u['role'] === 'admin' ? 'badge-admin' : '' ?>">
                                        <?= htmlspecialchars(strtoupper($u['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($u['last_login'])): ?>
                                        <span style="color: #166534; font-weight: 600;"><?= htmlspecialchars($u['last_login']) ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--muted);">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= htmlspecialchars($u['created_at']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section style="width: 100%; margin-top: 35px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="color: var(--primary);">3. Student Hub Database - Students (`students`)</h3>
                <span class="badge-role">Records: <?= count($students) ?></span>
            </div>
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
                                <td><?= htmlspecialchars($s['course']) ?></td>
                                <td><?= htmlspecialchars($s['year']) ?></td>
                                <td><?= htmlspecialchars($s['gender']) ?></td>
                                <td><small><?= htmlspecialchars($s['created_at']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section style="width: 100%; margin-top: 35px; margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="color: var(--primary);">4. Student Hub Database - Events (`events`)</h3>
                <span class="badge-role">Events: <?= count($events) ?></span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event ID</th>
                            <th>Event Title</th>
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
                                <td><?= htmlspecialchars($ev['category']) ?></td>
                                <td><?= htmlspecialchars($ev['event_date']) ?></td>
                                <td><?= htmlspecialchars($ev['venue']) ?></td>
                                <td><small><?= htmlspecialchars($ev['created_at']) ?></small></td>
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
            <p>Developed by Student Hub Team</p>
        </div>
    </footer>

    <script src="../practical 4/script.js"></script>
</body>
</html>
