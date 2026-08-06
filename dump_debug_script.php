<?php
require 'db.php';
$stmt = pdoQuery($pdo, "SELECT * FROM users WHERE name LIKE '%mike%' OR name LIKE '%evaristo%'");
$users = $stmt->fetchAll();
$stmtA = pdoQuery($pdo, "SELECT * FROM appointments WHERE appointment_date = CURDATE()");
$apts = $stmtA->fetchAll();
file_put_contents('dump_debug.txt', "USERS:\n" . print_r($users, true) . "\n\nAPPOINTMENTS:\n" . print_r($apts, true));
?>
