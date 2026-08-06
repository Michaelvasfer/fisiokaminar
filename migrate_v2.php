<?php
require 'db.php';
try {
    // 1. Photos for Progress
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        therapist_id INT NOT NULL,
        title VARCHAR(255),
        photo_path VARCHAR(512) NOT NULL,
        category ENUM('before', 'after', 'progress', 'exam') DEFAULT 'progress',
        created_at DATETIME DEFAULT NOW(),
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Exercises for Patients
    $pdo->exec("CREATE TABLE IF NOT EXISTS exercises (
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

    echo "Tables created successfully\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
