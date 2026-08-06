<?php
require_once 'db.php';

echo "<h2>Fixing Database for Clinical Histories and Protocols...</h2>";

try {
    // 1. Add missing columns to protocol_phases
    $colsPhases = [
        "objectives" => "TEXT",
        "activities" => "TEXT"
    ];
    foreach ($colsPhases as $col => $def) {
        try {
            @$pdo->exec("ALTER TABLE protocol_phases ADD COLUMN $col $def");
            echo "✓ Column $col added to protocol_phases.<br>";
        } catch(Exception $e) { 
            echo "ℹ Column $col in protocol_phases likely exists.<br>";
        }
    }

    // 2. Ensure treatment_plans has necessary columns
    $colsPlans = [
        "protocol_id"        => "INT DEFAULT NULL",
        "total_sessions"     => "INT NOT NULL DEFAULT 0",
        "completed_sessions" => "INT NOT NULL DEFAULT 0",
        "status"             => "ENUM('active', 'completed', 'paused') DEFAULT 'active'",
        "start_date"         => "DATE DEFAULT NULL",
        "duration_weeks"     => "INT NOT NULL DEFAULT 1"
    ];
    foreach ($colsPlans as $col => $def) {
        try {
            @$pdo->exec("ALTER TABLE treatment_plans ADD COLUMN $col $def");
            echo "✓ Column $col added to treatment_plans.<br>";
        } catch(Exception $e) {
            echo "ℹ Column $col in treatment_plans likely exists.<br>";
        }
    }

    echo "<h3>Fix Complete.</h3>";
} catch (Exception $e) {
    echo "<p style='color:red'>ERROR: " . $e->getMessage() . "</p>";
}
?>
