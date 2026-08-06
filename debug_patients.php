<?php
require 'db.php';
$stmt = pdoQuery($pdo, "SELECT id, name, dni, role FROM users WHERE role = 'patient'");
$data = $stmt->fetchAll();
file_put_contents('patient_debug.txt', print_r($data, true));
echo "Debug data saved to patient_debug.txt";
