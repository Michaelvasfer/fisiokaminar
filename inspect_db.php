<?php
require_once 'db.php';
$tables = ['users', 'appointments', 'treatment_plans', 'exercises', 'session_notes', 'patient_photos', 'treatment_protocols', 'protocol_phases'];
foreach ($tables as $t) {
    echo "<h3>Table: $t</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE $t");
        echo "<table border='1'>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
}
?>
