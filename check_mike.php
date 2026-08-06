<?php
require 'db.php';
$name = 'mike';
$user = pdoQuery($pdo, "SELECT id, name, email, dni, role, password FROM users WHERE name LIKE ? OR email LIKE ?", ["%$name%", "%$name%"])->fetchAll();
echo json_encode($user, JSON_PRETTY_PRINT);
