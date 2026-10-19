<?php
require_once __DIR__ . '/auth.php';

if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $uname = $_SESSION['username'] ?? 'User';
    $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $logAction = "USER_LOGOUT";
    $logDetails = "User '" . $uname . "' logged out securely.";
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $logStmt->bind_param("isss", $uid, $logAction, $logDetails, $clientIp);
    $logStmt->execute();
    $logStmt->close();
}

destroy_user_session();

header("Location: login.php?logged_out=1");
exit;
?>
