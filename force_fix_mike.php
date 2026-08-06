<?php
require 'db.php';
// This script will find 'mike' and ensure he can login with a specific DNI if provided, 
// or just find him and report what DNI he has.
$name = 'mike';
$dni = '44681550'; // From the screenshot
$user = pdoQuery($pdo, "SELECT * FROM users WHERE name LIKE ? LIMIT 1", ["%$name%"])->fetch();

if ($user) {
    // Force update Mike with the DNI from the screenshot to be sure
    pdoQuery($pdo, "UPDATE users SET dni = ?, password = ? WHERE id = ?", [$dni, password_hash($dni, PASSWORD_DEFAULT), $user['id']]);
    $msg = "Paciente 'mike' actualizado con DNI: $dni. Ahora puede ingresar con $dni/$dni.";
} else {
    $msg = "No se encontró ningún paciente con el nombre 'mike'.";
}
file_put_contents('fix_result.txt', $msg);
echo $msg;
