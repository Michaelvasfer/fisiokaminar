<?php
require_once 'db.php';

// Función para añadir columnas de forma segura
function addColumnSafe($pdo, $table, $column, $definition) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "<p style='color:green'>SUCCESS: Column `$column` added to `$table`</p>";
        } else {
            echo "<p style='color:blue'>SKIP: Column `$column` already exists in `$table`</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>ERROR adding $column: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>Expanding Protocol Phases...</h2>";

addColumnSafe($pdo, 'protocol_phases', 'objectives', 'TEXT DEFAULT NULL');
addColumnSafe($pdo, 'protocol_phases', 'activities', 'TEXT DEFAULT NULL');

echo "<h3>Migration Complete.</h3>";
?>
