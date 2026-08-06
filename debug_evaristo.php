<?php
require 'db.php';
$name = 'evaristo';
$stmt = pdoQuery($pdo, "SELECT id, name, phone, dni, role FROM users WHERE name LIKE ?", ["%$name%"]);
$user = $stmt->fetch();
header('Content-Type: application/json');
echo json_encode($user, JSON_PRETTY_PRINT);
?>
