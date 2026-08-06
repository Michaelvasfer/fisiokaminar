<?php
require_once 'db.php';
try {
    $res = $pdo->query("SELECT * FROM treatment_protocols");
    echo "Table exists. Count: " . $res->rowCount();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
