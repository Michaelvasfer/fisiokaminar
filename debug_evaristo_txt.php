<?php
require 'db.php';
$name = 'evaristo';
$stmt = pdoQuery($pdo, "SELECT id, name, phone, dni, role FROM users WHERE name LIKE ?", ["%$name%"]);
$user = $stmt->fetch();
file_put_contents('evaristo_debug.txt', print_r($user, true));
?>
