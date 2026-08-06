<?php
// logout.php
session_start();
require_once 'db.php';
ensureAuditSchema($pdo);
if (isset($_SESSION['user_id'])) {
    appLog($pdo, 'auth.logout', 'user', (string)$_SESSION['user_id'], [], [
        'user_id' => (int)$_SESSION['user_id'],
        'user_name' => $_SESSION['name'] ?? null,
        'user_role' => $_SESSION['role'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
