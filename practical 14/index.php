<?php
session_start();
require_once __DIR__ . '/db.php';

$findAdm = $conn->query("SELECT id, fullname, email FROM users WHERE email = 'barchharahil720@gmail.com' OR role = 'admin' LIMIT 1");
$admRow = ($findAdm && $findAdm->num_rows > 0) ? $findAdm->fetch_assoc() : ['id' => 1, 'fullname' => 'Rahil Barchha', 'email' => 'barchharahil720@gmail.com'];
$adminId = (int)$admRow['id'];
$adminName = $admRow['fullname'];
$adminEmail = $admRow['email'];

if (isset($_GET['switch_role'])) {
    $targetRole = $_GET['switch_role'];
    if ($targetRole === 'admin') {
        $_SESSION['user_id'] = $adminId;
        $_SESSION['username'] = $adminEmail;
        $_SESSION['fullname'] = $adminName;
        $_SESSION['email'] = $adminEmail;
        $_SESSION['role'] = 'admin';
        $_SESSION['last_activity'] = time();
        writeAuditLog($conn, $adminId, 'ADMIN_LOGIN_SIM', 'Administrator logged into dashboard via session controller.');
    } elseif ($targetRole === 'student') {
        $_SESSION['user_id'] = 2;
        $_SESSION['username'] = 'student_rohan';
        $_SESSION['fullname'] = 'Rohan Sharma';
        $_SESSION['email'] = 'rohan@example.com';
        $_SESSION['role'] = 'student';
        $_SESSION['last_activity'] = time();
        writeAuditLog($conn, 2, 'STUDENT_LOGIN_SIM', 'Student user logged in.');
    } elseif ($targetRole === 'logout') {
        if (isset($_SESSION['user_id'])) {
            writeAuditLog($conn, $_SESSION['user_id'], 'USER_LOGOUT', 'User logged out.');
        }
        session_unset();
        session_destroy();
        session_start();
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
    $_SESSION['last_activity'] = time();
}

$currentRole = $_SESSION['role'] ?? 'guest';
$isAdmin = ($currentRole === 'admin');

if (isset($_GET['download']) && $_GET['download'] === 'sql') {
    if (!$isAdmin) {
        die("Unauthorized access: Administrator role required to export SQL dump.");
    }
    $file = __DIR__ . '/studenthub_p14.sql';
    if (file_exists($file)) {
        writeAuditLog($conn, $_SESSION['user_id'], 'SQL_EXPORT', 'Administrator downloaded studenthub_p14.sql database dump.');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="studenthub_p14.sql"');
        readfile($file);
        exit;
    }
}

$notice = "";
$errorMsg = "";

if ($isAdmin && isset($_POST['action']) && $_POST['action'] === 'toggle_user_status') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);

    if ($targetUserId > 0) {
        $stmtFind = $conn->prepare("SELECT email, fullname, status FROM users WHERE id = ?");
        $stmtFind->bind_param("i", $targetUserId);
        $stmtFind->execute();
        $targetUser = $stmtFind->get_result()->fetch_assoc();
        $stmtFind->close();

        if ($targetUser) {
            $currentStatus = $targetUser['status'] ?? 'Active';
            $newStatus = ($currentStatus === 'Active') ? 'Inactive' : 'Active';
            $stmtUpd = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmtUpd->bind_param("si", $newStatus, $targetUserId);
            if ($stmtUpd->execute()) {
                $logDetails = "Changed status of user #" . $targetUserId . " (" . $targetUser['email'] . ") from " . $currentStatus . " to " . $newStatus . ".";
                writeAuditLog($conn, $_SESSION['user_id'], 'USER_STATUS_TOGGLED', $logDetails);
                $notice = "User status for " . htmlspecialchars($targetUser['fullname']) . " toggled to " . $newStatus . " successfully.";
            } else {
                $errorMsg = "Database error updating user status: " . $conn->error;
            }
            $stmtUpd->close();
        }
    }
}

$totalUsers = 0;
$activeUsers = 0;
$inactiveUsers = 0;
$userStatsRes = $conn->query("SELECT status, COUNT(*) AS cnt FROM users GROUP BY status");
if ($userStatsRes) {
    while ($row = $userStatsRes->fetch_assoc()) {
        $st = $row['status'] ?? 'Active';
        $totalUsers += (int)$row['cnt'];
        if ($st === 'Active') {
            $activeUsers += (int)$row['cnt'];
        } else {
            $inactiveUsers += (int)$row['cnt'];
        }
    }
}

$totalStudents = 0;
$stuRes = $conn->query("SELECT COUNT(*) AS total FROM students");
if ($stuRes) {
    $totalStudents = (int)$stuRes->fetch_assoc()['total'];
}

$totalEvents = 0;
$openEvents = 0;
$evRes = $conn->query("SELECT status, COUNT(*) AS cnt FROM events GROUP BY status");
if ($evRes) {
    while ($row = $evRes->fetch_assoc()) {
        $totalEvents += (int)$row['cnt'];
        if ($row['status'] === 'Open') {
            $openEvents += (int)$row['cnt'];
        }
    }
}

$totalRegistrations = 0;
$regRes = $conn->query("SELECT COUNT(*) AS total FROM registrations");
if ($regRes) {
    $totalRegistrations = (int)$regRes->fetch_assoc()['total'];
}

$monthlyRegData = [];
$chartRes = $conn->query("SELECT DATE_FORMAT(registered_at, '%b %Y') AS month_label, COUNT(*) AS reg_count FROM registrations GROUP BY month_label ORDER BY MIN(registered_at) ASC");
$maxMonthlyCount = 1;
if ($chartRes) {
    while ($r = $chartRes->fetch_assoc()) {
        $cnt = (int)$r['reg_count'];
        if ($cnt > $maxMonthlyCount) {
            $maxMonthlyCount = $cnt;
        }
        $monthlyRegData[] = [
            'month' => $r['month_label'],
            'count' => $cnt
        ];
    }
}

$allUsers = [];
$usersListRes = $conn->query("SELECT id, username, fullname, email, mobile, course, role, status, created_at, last_login FROM users ORDER BY id ASC");
if ($usersListRes) {
    while ($u = $usersListRes->fetch_assoc()) {
        $allUsers[] = $u;
    }
}

$recentAuditLogs = [];
$auditRes = $conn->query("SELECT a.id, a.user_id, a.action, a.details, a.ip_address, a.created_at, u.fullname, u.role FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT 15");
if ($auditRes) {
    while ($al = $auditRes->fetch_assoc()) {
        $recentAuditLogs[] = $al;
    }
}

$recentRegs = [];
$recentRegRes = $conn->query("SELECT r.registration_id, r.registered_at, r.status, s.name AS student_name, s.course, e.title AS event_title, e.category FROM registrations r LEFT JOIN students s ON r.student_id = s.student_id LEFT JOIN events e ON r.event_id = e.event_id ORDER BY r.registration_id DESC LIMIT 8");
if ($recentRegRes) {
    while ($rr = $recentRegRes->fetch_assoc()) {
        $recentRegs[] = $rr;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role-Based Admin Dashboard & Analytics - Student Hub</title>
    <link rel="stylesheet" href="../practical 3/style.css">
    <style>
        .role-switcher-bar {
            background: #1e293b;
            color: #f8fafc;
            padding: 10px 16px;
            border-radius: 8px;
            margin: 15px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .role-tag {
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
        }
        .role-admin { background: #fee2e2; color: #991b1b; }
        .role-student { background: #dbeafe; color: #1e40af; }
        .role-guest { background: #f3f4f6; color: #374151; }
        .role-btn {
            background: #334155;
            color: #f8fafc;
            border: 1px solid #475569;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .role-btn:hover {
            background: #475569;
            color: #ffffff;
        }
        .role-btn.active-role {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
        }
        .stats-grid-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin: 20px 0;
            width: 100%;
        }
        .metric-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary);
        }
        .metric-title {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .metric-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.2;
        }
        .metric-subtext {
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
        }
        .analytics-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin: 24px 0;
            width: 100%;
        }
        @media (max-width: 900px) {
            .analytics-container {
                grid-template-columns: 1fr;
            }
        }
        .chart-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }
        .bar-chart-wrapper {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 200px;
            padding: 10px 0 20px 0;
            border-bottom: 2px solid var(--border);
            gap: 12px;
        }
        .bar-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            height: 100%;
            justify-content: flex-end;
        }
        .bar-pillar {
            width: 100%;
            max-width: 44px;
            background: linear-gradient(180deg, var(--primary) 0%, #2563eb 100%);
            border-radius: 6px 6px 0 0;
            transition: all 0.3s ease;
            position: relative;
            min-height: 8px;
        }
        .bar-pillar:hover {
            opacity: 0.85;
            transform: scaleY(1.04);
            transform-origin: bottom;
        }
        .bar-value-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }
        .bar-month-title {
            font-size: 11px;
            color: var(--muted);
            margin-top: 8px;
            text-align: center;
            font-weight: 600;
            white-space: nowrap;
        }
        .breakdown-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .breakdown-item:last-child {
            border-bottom: none;
        }
        .table-responsive-box {
            width: 100%;
            overflow-x: auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin: 16px 0 28px 0;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .data-table th, .data-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
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
        .badge-status-active {
            background: #dcfce7;
            color: #166534;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-status-inactive {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .btn-toggle-status {
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-toggle-deactivate {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .btn-toggle-deactivate:hover {
            background: #ef4444;
            color: #ffffff;
        }
        .btn-toggle-activate {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        .btn-toggle-activate:hover {
            background: #22c55e;
            color: #ffffff;
        }
        .badge-action-audit {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            background: #e0f2fe;
            color: #0369a1;
            text-transform: uppercase;
        }
        .rbac-denied-box {
            background: #fff1f2;
            border: 2px dashed #f43f5e;
            color: #881337;
            padding: 24px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>

    <header>
        <div class="header">
            <h1>Student Hub Portal</h1>
            <p>Role-Based Admin Dashboard & System Analytics</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu">☰</button>
        <div class="site-mini-title">Admin Dashboard & RBAC</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle">
            <span>Dark Mode</span>
        </label>
    </div>

    <div class="role-switcher-bar">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span>Current Authenticated Identity:</span>
            <strong><?= htmlspecialchars($_SESSION['fullname'] ?? 'Guest User') ?></strong>
            <span class="role-tag <?= $isAdmin ? 'role-admin' : 'role-student' ?>"><?= htmlspecialchars($currentRole) ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <span>RBAC Demo Switch:</span>
            <a href="index.php?switch_role=admin" class="role-btn <?= $isAdmin ? 'active-role' : '' ?>">Admin View</a>
            <a href="index.php?switch_role=student" class="role-btn <?= ($currentRole === 'student') ? 'active-role' : '' ?>">Student View</a>
            <a href="index.php?switch_role=logout" class="role-btn">Reset Session</a>
        </div>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Dynamic Role-Based Navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="index.php" class="nav-btn active">Dashboard</a>

        <?php if ($isAdmin): ?>
            <a href="../practical 13/index.php" class="nav-btn">Fetch API CRUD</a>
            <a href="../practical 11/index.php" class="nav-btn">Student CRUD</a>
            <a href="../practical 12/index.php" class="nav-btn">Events CRUD</a>
            <a href="#userManagementSection" class="nav-btn">User Management</a>
            <a href="#auditLogSection" class="nav-btn">Audit Logs</a>
            <a href="index.php?download=sql" class="nav-btn">Export SQL</a>
        <?php else: ?>
            <a href="../practical 13/index.php" class="nav-btn">Student Directory</a>
            <a href="../practical 12/index.php" class="nav-btn">Browse Events</a>
            <a href="../practical 9/index.php" class="nav-btn">My Registration</a>
            <a href="../practical 2/contact.html" class="nav-btn">Support Desk</a>
        <?php endif; ?>

        <a href="../practical 10/logout.php" class="nav-btn" style="color: #dc2626;">Logout</a>
    </nav>

    <main class="container">

        <?php if (!empty($notice)): ?>
            <div class="form-message success" style="display: block; margin-bottom: 20px;">
                <strong>✓ Success:</strong> <?= htmlspecialchars($notice) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="form-message error" style="display: block; margin-bottom: 20px;">
                <strong>✗ Error:</strong> <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid-dashboard">
            <div class="metric-card">
                <div class="metric-title">Total Portal Users</div>
                <div class="metric-value"><?= $totalUsers ?></div>
                <div class="metric-subtext"><?= $activeUsers ?> Active | <?= $inactiveUsers ?> Inactive</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Enrolled Students</div>
                <div class="metric-value"><?= $totalStudents ?></div>
                <div class="metric-subtext">Students in Central Registry</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Campus Events</div>
                <div class="metric-value"><?= $totalEvents ?></div>
                <div class="metric-subtext"><?= $openEvents ?> Currently Open</div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Event Registrations</div>
                <div class="metric-value"><?= $totalRegistrations ?></div>
                <div class="metric-subtext">Confirmed Student Signups</div>
            </div>
        </div>

        <div class="analytics-container">
            <div class="chart-box">
                <div class="chart-header">
                    <div>
                        <h3 style="margin: 0; color: var(--primary);">Event Registrations Analytics</h3>
                        <small style="color: var(--muted);">Monthly Student Registration Volume</small>
                    </div>
                    <span class="badge-status-active">Live DB Aggregation</span>
                </div>

                <div class="bar-chart-wrapper">
                    <?php if (empty($monthlyRegData)): ?>
                        <div style="width: 100%; text-align: center; color: var(--muted); padding-top: 60px;">No registrations recorded yet.</div>
                    <?php else: ?>
                        <?php foreach ($monthlyRegData as $item): 
                            $barHeightPercent = ($maxMonthlyCount > 0) ? round(($item['count'] / $maxMonthlyCount) * 100) : 10;
                            if ($barHeightPercent < 15) $barHeightPercent = 15;
                        ?>
                            <div class="bar-column">
                                <div class="bar-value-label"><?= $item['count'] ?></div>
                                <div class="bar-pillar" style="height: <?= $barHeightPercent ?>%;" title="<?= htmlspecialchars($item['month']) ?>: <?= $item['count'] ?> registrations"></div>
                                <div class="bar-month-title"><?= htmlspecialchars($item['month']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="chart-box">
                <div class="chart-header">
                    <h3 style="margin: 0; color: var(--primary);">System Summary</h3>
                    <small style="color: var(--muted);">RBAC & Security</small>
                </div>
                <ul class="breakdown-list">
                    <li class="breakdown-item">
                        <span>Current User Role</span>
                        <span class="role-tag <?= $isAdmin ? 'role-admin' : 'role-student' ?>"><?= htmlspecialchars($currentRole) ?></span>
                    </li>
                    <li class="breakdown-item">
                        <span>Active User Accounts</span>
                        <strong><?= $activeUsers ?> / <?= $totalUsers ?></strong>
                    </li>
                    <li class="breakdown-item">
                        <span>Account Moderation</span>
                        <span style="color: #16a34a; font-weight: 600;">Status Toggle Enabled</span>
                    </li>
                    <li class="breakdown-item">
                        <span>Audit Log Traceability</span>
                        <span style="color: #0369a1; font-weight: 600;">Active Logging</span>
                    </li>
                    <li class="breakdown-item">
                        <span>Database Name</span>
                        <code>studenthub</code>
                    </li>
                </ul>
            </div>
        </div>

        <?php if ($isAdmin): ?>

            <section id="userManagementSection" style="margin-top: 35px; width: 100%;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <h3 style="margin: 0; color: var(--primary);">User Account Management & Status Toggle</h3>
                        <small style="color: var(--muted);">Intermediate Extension: Moderate portal access by toggling active/inactive status</small>
                    </div>
                    <span style="font-size: 13px; color: var(--muted);"><?= count($allUsers) ?> Registered Accounts</span>
                </div>

                <div class="table-responsive-box">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User Account</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Course</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th style="text-align: center;">Moderation Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $u): 
                                $uStatus = $u['status'] ?? 'Active';
                                $isActive = ($uStatus === 'Active');
                            ?>
                                <tr>
                                    <td>#<?= $u['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                                        <div style="font-size: 11px; color: var(--muted);"><?= htmlspecialchars($u['email']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($u['fullname']) ?></td>
                                    <td>
                                        <span class="role-tag <?= ($u['role'] === 'admin') ? 'role-admin' : 'role-student' ?>">
                                            <?= htmlspecialchars($u['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($u['course']) ?></td>
                                    <td>
                                        <span class="<?= $isActive ? 'badge-status-active' : 'badge-status-inactive' ?>">
                                            <?= htmlspecialchars($uStatus) ?>
                                        </span>
                                    </td>
                                    <td><small><?= htmlspecialchars($u['created_at']) ?></small></td>
                                    <td style="text-align: center;">
                                        <?php if ($u['role'] === 'admin' && $u['id'] == $_SESSION['user_id']): ?>
                                            <span style="font-size: 11px; color: var(--muted);">Current Admin</span>
                                        <?php else: ?>
                                            <form method="POST" action="index.php" style="display: inline;" onsubmit="return confirm('Toggle status for <?= htmlspecialchars($u['fullname']) ?>?');">
                                                <input type="hidden" name="action" value="toggle_user_status">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= htmlspecialchars($uStatus) ?>">
                                                <button type="submit" class="btn-toggle-status <?= $isActive ? 'btn-toggle-deactivate' : 'btn-toggle-activate' ?>">
                                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="auditLogSection" style="margin-top: 35px; width: 100%;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <h3 style="margin: 0; color: var(--primary);">System Audit Logs</h3>
                        <small style="color: var(--muted);">Real-time recording of administrative, security, and moderation events</small>
                    </div>
                    <span style="font-size: 13px; color: var(--muted);">Showing latest <?= count($recentAuditLogs) ?> entries</span>
                </div>

                <div class="table-responsive-box">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>Triggered Action</th>
                                <th>Initiator</th>
                                <th>Audit Details</th>
                                <th>Client IP</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentAuditLogs)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px; color: var(--muted);">No audit events recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentAuditLogs as $log): ?>
                                    <tr>
                                        <td>#<?= $log['id'] ?></td>
                                        <td><span class="badge-action-audit"><?= htmlspecialchars($log['action']) ?></span></td>
                                        <td>
                                            <?= htmlspecialchars($log['fullname'] ?? 'System') ?>
                                            <?php if (!empty($log['role'])): ?>
                                                <span class="role-tag <?= ($log['role'] === 'admin') ? 'role-admin' : 'role-student' ?>" style="font-size: 9px; padding: 2px 6px;">
                                                    <?= htmlspecialchars($log['role']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($log['details']) ?></td>
                                        <td><code><?= htmlspecialchars($log['ip_address']) ?></code></td>
                                        <td><small><?= htmlspecialchars($log['created_at']) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php else: ?>

            <div class="rbac-denied-box">
                <h3 style="margin-top: 0;">Access Restricted by Role-Based Access Control (RBAC)</h3>
                <p>You are viewing the dashboard with <strong>Student privileges</strong>. The User Account Management table, status toggles, and administrative Audit Logs are restricted to Administrator accounts.</p>
                <div style="margin-top: 15px;">
                    <a href="index.php?switch_role=admin" class="btn-toggle-status btn-toggle-activate" style="text-decoration: none; padding: 8px 16px; font-size: 13px;">
                        Switch to Administrator Session
                    </a>
                </div>
            </div>

            <section style="margin-top: 25px; width: 100%;">
                <h3 style="color: var(--primary); margin-bottom: 12px;">Recent Event Registrations</h3>
                <div class="table-responsive-box">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reg #</th>
                                <th>Student Name</th>
                                <th>Course</th>
                                <th>Event Title</th>
                                <th>Category</th>
                                <th>Registration Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRegs as $rg): ?>
                                <tr>
                                    <td>#<?= $rg['registration_id'] ?></td>
                                    <td><strong><?= htmlspecialchars($rg['student_name'] ?? 'Student') ?></strong></td>
                                    <td><?= htmlspecialchars($rg['course'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($rg['event_title'] ?? 'Event') ?></td>
                                    <td><?= htmlspecialchars($rg['category'] ?? '-') ?></td>
                                    <td><small><?= htmlspecialchars($rg['registered_at']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php endif; ?>

    </main>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark');
                themeToggle.checked = true;
            }
            themeToggle.addEventListener('change', () => {
                if (themeToggle.checked) {
                    document.body.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                }
            });
        }

        const menuButton = document.getElementById('menuButton');
        const navMenu = document.getElementById('navMenu');
        if (menuButton && navMenu) {
            menuButton.addEventListener('click', () => {
                navMenu.classList.toggle('nav-open');
            });
        }
    </script>
</body>
</html>
