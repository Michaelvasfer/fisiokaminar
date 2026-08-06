<?php
require 'db.php';
try {
    // Force drop and recreate to ensure clean state since it's a new feature
    $pdo->exec("DROP TABLE IF EXISTS exercises");
    $pdo->exec("CREATE TABLE exercises (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        therapist_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        frequency VARCHAR(100),
        video_url VARCHAR(512),
        is_active BOOLEAN DEFAULT TRUE,
        created_at DATETIME DEFAULT NOW(),
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table 'exercises' fixed successfully.";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
