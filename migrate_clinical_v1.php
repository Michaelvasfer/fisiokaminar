<?php
require_once 'db.php';

echo "<h2>Expanding Database for Clinical Histories...</h2>";

// Crear tabla de Historias Clínicas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinical_histories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        therapist_id INT NOT NULL,
        reason_location VARCHAR(255),
        evolution_time VARCHAR(50),
        medical_diagnosis TEXT,
        eva_score INT DEFAULT 0,
        pain_type VARCHAR(50),
        worsens_with VARCHAR(100),
        mobility VARCHAR(50),
        strength VARCHAR(50),
        inflammation TINYINT(1) DEFAULT 0,
        functional_test TEXT,
        main_objective VARCHAR(255),
        indicated_sessions INT,
        frequency VARCHAR(50),
        initial_treatment TEXT,
        observations TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "<p style='color:green'>SUCCESS: Table `clinical_histories` created or already exists.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>ERROR creating table: " . $e->getMessage() . "</p>";
}

echo "<h3>Migration Complete.</h3>";
?>
