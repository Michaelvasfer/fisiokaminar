<?php
require 'db.php';
try {
    $stmt = $pdo->query("DESCRIBE users");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Users columns: " . implode(", ", $cols) . "\n";
    
    $stmt = $pdo->query("DESCRIBE transactions");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Transactions columns: " . implode(", ", $cols) . "\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
