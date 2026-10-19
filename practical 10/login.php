<?php
require_once __DIR__ . '/auth.php';

check_remember_me();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: student_dashboard.php");
    }
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$val_identifier = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf_token"] ?? "";
    $val_identifier = trim($_POST["identifier"] ?? "");
    $password = $_POST["password"] ?? "";
    $rememberMe = isset($_POST["remember_me"]);

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = "Security validation failed. Please refresh and try again.";
    } elseif (empty($val_identifier) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, fullname, email, password, role FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $val_identifier, $val_identifier);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['login_time'] = time();

                $stmtUpdate = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmtUpdate->bind_param("i", $user['id']);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logAction = "USER_LOGIN";
                $logDetails = "User '" . $user['username'] . "' logged in successfully (Role: " . $user['role'] . ").";
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $logStmt->bind_param("isss", $user['id'], $logAction, $logDetails, $clientIp);
                $logStmt->execute();
                $logStmt->close();

                if ($rememberMe) {
                    $randomToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $randomToken);

                    $stmtRem = $conn->prepare("UPDATE users SET remember_token = ?, remember_expiry = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE id = ?");
                    $stmtRem->bind_param("si", $tokenHash, $user['id']);
                    $stmtRem->execute();
                    $stmtRem->close();

                    setcookie('remember_token', $user['id'] . ':' . $randomToken, time() + (14 * 86400), '/', '', false, true);
                }

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: student_dashboard.php");
                }
                exit;
            } else {
                $error = "Invalid password. Please check your credentials.";

                $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logAction = "LOGIN_FAILED";
                $logDetails = "Failed password attempt for user '" . $user['username'] . "'.";
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $logStmt->bind_param("isss", $user['id'], $logAction, $logDetails, $clientIp);
                $logStmt->execute();
                $logStmt->close();
            }
        } else {
            $error = "No account found with that username or email.";

            $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $logAction = "LOGIN_FAILED";
            $logDetails = "Failed login attempt with non-existent identifier '" . htmlspecialchars($val_identifier) . "'.";
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $nullUid = null;
            $logStmt->bind_param("isss", $nullUid, $logAction, $logDetails, $clientIp);
            $logStmt->execute();
            $logStmt->close();
        }
        $stmt->close();
    }
}

$notice = "";
$noticeType = "error";

if (isset($_GET['logged_out'])) {
    $notice = "You have been safely logged out of your session.";
    $noticeType = "success";
} elseif (isset($_GET['error'])) {
    if ($_GET['error'] === 'unauthorized') {
        $notice = "Access Denied: Please log in to access the protected dashboard.";
    } elseif ($_GET['error'] === 'timeout') {
        $notice = "Session Expired: You were automatically logged out due to inactivity.";
    } elseif ($_GET['error'] === 'forbidden') {
        $notice = "Access Denied: You do not possess authorization for that section.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal & Admin Login - Student Hub</title>
    <link rel="stylesheet" href="../practical 3/style.css">
    <style>
        .login-card {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        .remember-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0;
            font-size: 14px;
            cursor: pointer;
        }
        .remember-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .quick-accounts-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 20px;
            width: 100%;
            max-width: 500px;
        }
        .quick-accounts-box h4 {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .chip-btn-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .chip-btn {
            background: #e2e8f0;
            color: #334155;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .chip-btn:hover {
            background: var(--primary);
            color: #ffffff;
        }
    </style>
</head>
<body>

    <header>
        <div class="header">
            <h1>Student Hub Portal</h1>
            <p>Admin & Student Authentication</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu">☰</button>
        <div class="site-mini-title">Portal Login</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle">
            <span>Dark Mode</span>
        </label>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Main navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="login.php" class="nav-btn active">Login</a>
        <a href="../practical 9/index.php" class="nav-btn">Register</a>
        <a href="../practical 2/contact.html" class="nav-btn">Contact</a>
        <a href="../practical 2/About.html" class="nav-btn">About</a>
    </nav>

    <main class="container">

        <div class="quick-accounts-box">
            <h4>🧪 Quick Test Credentials:</h4>
            <div class="chip-btn-row">
                <button type="button" class="chip-btn" onclick="fillAdmin()">Admin (barchharahil720@gmail.com)</button>
                <button type="button" class="chip-btn" onclick="fillStudent()">Student (student_rohan)</button>
                <button type="button" class="chip-btn" onclick="fillInvalid()">Invalid Credentials</button>
            </div>
        </div>

        <h2 class="page-title">Secure Login</h2>

        <?php if (!empty($notice)): ?>
            <div class="form-message <?= $noticeType ?>" style="display: block; width: 100%; max-width: 500px; margin-bottom: 20px;">
                <?= htmlspecialchars($notice) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="form-message error" style="display: block; width: 100%; max-width: 500px; margin-bottom: 20px;">
                <strong>✗</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="login-card">
            <form id="secureLoginForm" action="login.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-group">
                    <label for="identifier">
                        Username or Email <span class="required-star">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="identifier" 
                        name="identifier" 
                        class="form-control" 
                        placeholder="Enter username or email address"
                        value="<?= htmlspecialchars($val_identifier) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">
                        Password <span class="required-star">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Enter your account password"
                        required
                    >
                </div>

                <label class="remember-group">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <span>Remember me on this browser (Secure Hash Token)</span>
                </label>

                <div class="button-area" style="margin-top: 20px;">
                    <button type="submit" class="btn" style="flex: 2;">Login</button>
                    <button type="reset" class="btn" style="flex: 1; background: #64748b;">Reset</button>
                </div>
            </form>

            <div class="button-area" style="margin-top: 25px;">
                <a href="../practical 9/index.php" class="btn" style="background: #475569;">New Student? Register Here</a>
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
    <script>
        const identInput = document.getElementById('identifier');
        const passInput = document.getElementById('password');
        const remInput = document.getElementById('remember_me');

        function fillAdmin() {
            identInput.value = 'barchharahil720@gmail.com';
            passInput.value = '1@Rr2345';
            remInput.checked = true;
        }

        function fillStudent() {
            identInput.value = 'student_rohan';
            passInput.value = 'Student@123';
            remInput.checked = false;
        }

        function fillInvalid() {
            identInput.value = 'unknown_user';
            passInput.value = 'WrongPassword@999';
            remInput.checked = false;
        }
    </script>
</body>
</html>
