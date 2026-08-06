<?php
require_once 'db.php';
$stmt = $pdo->query("DESCRIBE exercises");
$columns = $stmt->fetchAll();
header('Content-Type: application/json');
echo json_encode($columns, JSON_PRETTY_PRINT);
