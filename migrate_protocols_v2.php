<?php
require_once 'db.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS `protocol_sessions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `phase_id` int(11) NOT NULL,
      `session_number` int(11) NOT NULL,
      `title` varchar(255) DEFAULT NULL,
      `activities` text DEFAULT NULL,
      `equipment` varchar(255) DEFAULT NULL,
      `duration_minutes` int(11) DEFAULT 45,
      PRIMARY KEY (`id`),
      KEY `idx_phase` (`phase_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `patient_sessions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `plan_id` int(11) NOT NULL,
      `phase_id` int(11) NOT NULL,
      `session_number` int(11) NOT NULL,
      `title` varchar(255) DEFAULT NULL,
      `status` enum('pending','completed','cancelled') DEFAULT 'pending',
      `scheduled_date` date DEFAULT NULL,
      `completed_date` datetime DEFAULT NULL,
      `observations` text DEFAULT NULL,
      `evolution` text DEFAULT NULL,
      `eva_score` int(11) DEFAULT NULL,
      `mobility_notes` text DEFAULT NULL,
      `treatment_changes` text DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_plan` (`plan_id`),
      KEY `idx_phase_pt` (`phase_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

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

echo "<h2>Starting Migration...</h2>";

// 1. Crear tablas
foreach ([$queries[0], $queries[1]] as $q) {
    try {
        $pdo->exec($q);
        echo "<p style='color:green'>SUCCESS: Table structure created/verified.</p>";
    } catch (Exception $e) {
        echo "<p style='color:orange'>WARN: " . $e->getMessage() . "</p>";
    }
}

// 2. Añadir columnas de forma segura
addColumnSafe($pdo, 'treatment_plans', 'protocol_id', 'int(11) DEFAULT NULL');
addColumnSafe($pdo, 'treatment_plans', 'total_sessions', 'int(11) DEFAULT 0');
addColumnSafe($pdo, 'treatment_plans', 'completed_sessions', 'int(11) DEFAULT 0');
addColumnSafe($pdo, 'treatment_plans', 'status', "enum('active','completed','on_hold') DEFAULT 'active'");
addColumnSafe($pdo, 'treatment_plans', 'start_date', 'date DEFAULT NULL');

echo "<h3>Migration Complete.</h3>";
?>
