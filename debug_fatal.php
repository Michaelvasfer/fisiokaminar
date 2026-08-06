<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🕵️ Diagnóstico del Sistema</h2>";

// 1. Probar Conexión
echo "1. Conectando a BD... ";
try {
    require_once 'db.php';
    echo "<span style='color:green;'>✓ OK</span><br>";
} catch(Exception $e) {
    die("<span style='color:red;'>✗ FALLÓ: " . $e->getMessage() . "</span>");
}

// 2. Probar Tablas Críticas
$tables = ['users', 'appointments', 'treatment_plans', 'exercises', 'treatment_protocols'];
echo "2. Verificando tablas... ";
foreach ($tables as $t) {
    try {
        $pdo->query("SELECT 1 FROM $t LIMIT 1");
        echo "<span style='color:green;'>$t ✓</span> ";
    } catch(Exception $e) {
        echo "<span style='color:red;'>$t ✗</span> ";
    }
}
echo "<br>";

// 3. Probar Columnas Específicas
echo "3. Verificando columnas nuevas... ";
$checks = [
    ['exercises', 'created_at'],
    ['treatment_plans', 'completed_sessions'],
    ['treatment_plans', 'protocol_id']
];
foreach ($checks as $c) {
    try {
        $pdo->query("SELECT {$c[1]} FROM {$c[0]} LIMIT 1");
        echo "<span style='color:green;'>{$c[0]}.{$c[1]} ✓</span> ";
    } catch(Exception $e) {
        echo "<span style='color:red;'>{$c[0]}.{$c[1]} ✗</span> ";
    }
}
echo "<br>";

// 4. Probar sesión
session_start();
echo "4. Sesión activa: " . (isset($_SESSION['user_id']) ? "SI (User ID: {$_SESSION['user_id']})" : "NO") . "<br>";

echo "<h3>Si ves todo en VERDE arriba, el sistema debería estar funcionando.</h3>";
echo "<a href='index.php'>Volver al Inicio</a>";
?>
