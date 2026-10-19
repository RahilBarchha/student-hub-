<?php
require_once __DIR__ . '/auth.php';

check_auth('student');

$notice = "";
if (isset($_GET['welcome'])) {
    $notice = "Welcome back! You have successfully signed in to your student account.";
} elseif (isset($_GET['error']) && $_GET['error'] === 'forbidden') {
    $notice = "Access Denied: You do not possess administrator credentials to view the Admin Dashboard.";
}

$stmtUser = $conn->prepare("SELECT id, username, fullname, email, mobile, course, role, last_login, created_at FROM users WHERE id = ?");
$stmtUser->bind_param("i", $_SESSION['user_id']);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Student Hub</title>
    <link rel="stylesheet" href="../practical 3/style.css">
    <style>
        .badge-student {
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
            font-size: 15px;
            font-weight: bold;
            color: var(--primary);
            word-break: break-all;
        }
        .profile-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 24px;
            margin: 20px 0;
            width: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .profile-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        .profile-row:last-child {
            border-bottom: none;
        }
        .profile-label {
            font-weight: 600;
            color: var(--muted);
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
            <p>Student Portal & Academic Dashboard</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu">☰</button>
        <div class="site-mini-title">Student Dashboard</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle">
            <span>Dark Mode</span>
        </label>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Main navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="student_dashboard.php" class="nav-btn active">Dashboard</a>
        <a href="../practical 2/profile.html" class="nav-btn">Profile</a>
        <a href="../practical 2/contact.html" class="nav-btn">Contact</a>
        <a href="../practical 2/About.html" class="nav-btn">About</a>
        <a href="logout.php" class="nav-btn" style="color: #dc2626;">Logout</a>
    </nav>

    <main class="container">

        <h2 class="page-title">Welcome, <?= htmlspecialchars($_SESSION['fullname']) ?></h2>

        <?php if (!empty($notice)): ?>
            <div class="form-message <?= isset($_GET['error']) ? 'error' : 'success' ?>" style="display: block; margin-bottom: 20px;">
                <?= htmlspecialchars($notice) ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h4>Active Role</h4>
                <p><?= htmlspecialchars($_SESSION['username']) ?> <span class="badge-student">Student</span></p>
            </div>
            <div class="stat-card">
                <h4>Session ID</h4>
                <p><code style="font-size: 11px;"><?= htmlspecialchars(session_id()) ?></code></p>
            </div>
            <div class="stat-card">
                <h4>Last Login</h4>
                <p style="font-size: 13px; color: #166534;"><?= htmlspecialchars($userData['last_login'] ?? 'Just now') ?></p>
            </div>
            <div class="stat-card">
                <h4>Inactivity Timer</h4>
                <p>15-Min Timeout Protected</p>
            </div>
        </div>

        <div class="control-bar">
            <a href="admin_dashboard.php" class="btn" style="background: #ef4444;">Test Unauthorized Admin Access (RBAC)</a>
            <a href="student_dashboard.php?simulate_timeout=1" class="btn" style="background: #ea580c;">Simulate Session Timeout</a>
            <a href="logout.php" class="btn" style="background: #dc2626; margin-left: auto;">Logout Securely</a>
        </div>

        <div class="profile-card">
            <h3 style="color: var(--primary); margin-bottom: 15px;">Your Student Profile</h3>
            <div class="profile-row">
                <span class="profile-label">Full Name:</span>
                <span><strong><?= htmlspecialchars($userData['fullname']) ?></strong></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Username:</span>
                <span><?= htmlspecialchars($userData['username']) ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Email Address:</span>
                <span><?= htmlspecialchars($userData['email']) ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Mobile Number:</span>
                <span><?= htmlspecialchars($userData['mobile']) ?></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Course / Branch:</span>
                <span><span class="badge-student"><?= htmlspecialchars($userData['course']) ?></span></span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Access Level:</span>
                <span>Standard Student Privileges (RBAC: Level 2)</span>
            </div>
            <div class="profile-row">
                <span class="profile-label">Registered Since:</span>
                <span><small><?= htmlspecialchars($userData['created_at']) ?></small></span>
            </div>
        </div>

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
