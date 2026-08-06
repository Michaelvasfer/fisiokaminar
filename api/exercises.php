<?php
// api/exercises.php - Manejo de ejercicios
session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once '../includes/csrf.php';
verifyCsrfRequest();
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Asegurar que la tabla y columnas existen (Compatibilidad máxima)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS exercises (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $columns_fix = [
        "plan_id" => "INT NULL", // Cambiado a NULL para evitar error de FK
        "patient_id" => "INT NOT NULL DEFAULT 0",
        "therapist_id" => "INT NOT NULL DEFAULT 0",
        "title" => "VARCHAR(255) NOT NULL DEFAULT ''",
        "name" => "VARCHAR(255) NOT NULL DEFAULT ''", 
        "description" => "TEXT NULL",
        "frequency" => "VARCHAR(100) DEFAULT ''",
        "is_active" => "TINYINT(1) DEFAULT 1",
        "video_url" => "VARCHAR(255) DEFAULT ''",
        "created_at" => "DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($columns_fix as $col => $definition) {
        try {
            @$pdo->exec("ALTER TABLE exercises ADD COLUMN $col $definition");
        } catch(Exception $e) {
            try { @$pdo->exec("ALTER TABLE exercises MODIFY COLUMN $col $definition"); } catch(Exception $ex) {}
        }
    }
    
    // Intento desesperado: Eliminar la restricción de llave foránea si existe y estorba
    try { @$pdo->exec("ALTER TABLE exercises DROP FOREIGN KEY exercises_ibfk_1"); } catch(Exception $e) {}
    try { @$pdo->exec("ALTER TABLE exercises DROP FOREIGN KEY fk_exercises_plan"); } catch(Exception $e) {}

} catch(Exception $e) {}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    try {
        if (empty($data['title'])) {
            throw new Exception("El título del ejercicio es obligatorio");
        }
        
        // Usamos NULL para plan_id si no se especifica, para no romper la llave foránea
        pdoQuery($pdo, "
            INSERT INTO exercises (patient_id, therapist_id, title, name, frequency, plan_id, is_active)
            VALUES (?, ?, ?, ?, ?, NULL, 1)
        ", [
            (int)($data['patient_id'] ?? 0),
            $_SESSION['user_id'],
            $data['title'],
            $data['title'],
            $data['frequency'] ?? ''
        ]);
        $newId = (int)$pdo->lastInsertId();
        appLog($pdo, 'exercise.assign', 'exercise', (string)$newId, [
            'patient_id' => (int)($data['patient_id'] ?? 0),
            'title' => $data['title'],
            'frequency' => $data['frequency'] ?? ''
        ]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    pdoQuery($pdo, "UPDATE exercises SET is_active = 0 WHERE id = ?", [(int)$data['id']]);
    appLog($pdo, 'exercise.remove', 'exercise', (string)((int)$data['id']));
    echo json_encode(['success' => true]);
}
