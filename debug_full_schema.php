<?php
require_once 'db.php';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$schema = [];
foreach ($tables as $table) {
    $schema[$table] = $pdo->query("DESCRIBE $table")->fetchAll();
}
header('Content-Type: application/json');
echo json_encode($schema, JSON_PRETTY_PRINT);
