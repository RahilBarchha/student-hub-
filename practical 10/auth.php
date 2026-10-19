<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

require_once __DIR__ . '/db.php';

$sessionTimeout = 900;

if (isset($_GET['simulate_timeout']) && $_GET['simulate_timeout'] === '1') {
    $_SESSION['last_activity'] = time() - ($sessionTimeout + 10);
}

if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $sessionTimeout) {
        destroy_user_session();
        header("Location: login.php?error=timeout");
        exit;
    }
}

if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

function check_remember_me() {
    global $conn;

    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
        $parts = explode(':', $_COOKIE['remember_token'], 2);
        if (count($parts) === 2) {
            $userId = (int)$parts[0];
            $rawToken = $parts[1];
            $hashedToken = hash('sha256', $rawToken);

            $stmt = $conn->prepare("SELECT id, username, fullname, email, role FROM users WHERE id = ? AND remember_token = ? AND remember_expiry > NOW()");
            $stmt->bind_param("is", $userId, $hashedToken);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($user = $res->fetch_assoc()) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();

                $up = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $up->bind_param("is", $user['id']);
                $up->execute();
                $up->close();
            }
            $stmt->close();
        }
    }
}

function check_auth($requiredRole = null) {
    check_remember_me();

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?error=unauthorized");
        exit;
    }

    if ($requiredRole !== null) {
        if ($requiredRole === 'admin') {
            if ($_SESSION['role'] !== 'admin' || ($_SESSION['email'] ?? '') !== 'barchharahil720@gmail.com') {
                header("Location: login.php?error=forbidden");
                exit;
            }
        } elseif ($_SESSION['role'] !== $requiredRole) {
            header("Location: student_dashboard.php?error=forbidden");
            exit;
        }
    }
}

function destroy_user_session() {
    global $conn;

    if (isset($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}
?>
