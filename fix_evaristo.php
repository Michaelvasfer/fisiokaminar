<?php
require 'db.php';

// Ver datos actuales
$stmt = pdoQuery($pdo, "SELECT id, name, phone, dni FROM users WHERE name LIKE '%mike%' OR name LIKE '%evaristo%'");
$users = $stmt->fetchAll();

foreach($users as $user) {
    echo "User: " . $user['name'] . " - Phone: " . ($user['phone'] ?: 'NULL') . " - DNI: " . $user['dni'] . "<br>";
}

// Asegurar que tengan teléfono (si falta)
pdoQuery($pdo, "UPDATE users SET phone = '999888777' WHERE name LIKE '%evaristo%' AND (phone IS NULL OR phone = '')");
pdoQuery($pdo, "UPDATE users SET phone = '999888111' WHERE name LIKE '%mike%' AND (phone IS NULL OR phone = '')");

echo "<br>Done. Revisa si evaristo no tenía teléfono.";
?>
