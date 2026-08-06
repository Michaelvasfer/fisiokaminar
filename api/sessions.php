<?php
// api/sessions.php ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â CRUD de notas de sesiones clÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­nicas
session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once '../includes/auth_helper.php';
require_once '../includes/csrf.php';
verifyCsrfRequest();
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']); exit;
}

$userRole = $_SESSION['role'];
$userId   = $_SESSION['user_id'];
$method   = $_SERVER['REQUEST_METHOD'];
$body     = json_decode(file_get_contents('php://input'), true) ?? [];

// Asegurar que la tabla existe
$pdo->exec("CREATE TABLE IF NOT EXISTS session_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    therapist_id INT NOT NULL,
    appointment_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    notes TEXT,
    session_date DATE NOT NULL,
    created_at DATETIME DEFAULT NOW(),
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

switch ($method) {

    case 'GET':
        $patientId = (int)($_GET['patient_id'] ?? 0);
        if (!$patientId) { echo json_encode(['success' => true, 'sessions' => []]); break; }
        $stmt = pdoQuery($pdo,
            "SELECT s.*, u.name AS therapist_name 
             FROM session_notes s 
             JOIN users u ON s.therapist_id = u.id
             WHERE s.patient_id = ? ORDER BY s.session_date DESC, s.created_at DESC",
            [$patientId]
        );
        echo json_encode(['success' => true, 'sessions' => $stmt->fetchAll()]);
        break;

    case 'POST':
        if (!hasPermission($pdo, $userId, $userRole, 'add_note')) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Sin permiso para agregar notas de sesiÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n']); exit;
        }
        $patientId = (int)($body['patient_id'] ?? 0);
        $title     = trim($body['title'] ?? '');
        $notes     = trim($body['notes'] ?? '');
        $date      = $body['session_date'] ?? date('Y-m-d');
        $aptId     = (int)($body['appointment_id'] ?? 0) ?: null;

        if (!$patientId || !$title) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Paciente y tÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­tulo son obligatorios']); exit;
        }

        pdoQuery($pdo,
            "INSERT INTO session_notes (patient_id, therapist_id, appointment_id, title, notes, session_date) VALUES (?, ?, ?, ?, ?, ?)",
            [$patientId, $userId, $aptId, $title, $notes, $date]
        );
        $newId = (int)$pdo->lastInsertId();
        appLog($pdo, 'session_note.create', 'session_note', (string)$newId, ['patient_id' => $patientId, 'appointment_id' => $aptId, 'title' => $title, 'session_date' => $date]);
        echo json_encode(['success' => true, 'message' => 'Nota de sesiÃƒÂ³n guardada', 'id' => $newId]);
        break;

    case 'DELETE':
        if (!in_array($userRole, ['admin', 'therapist'])) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Sin permiso']); exit;
        }
        $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'ID requerido']); exit; }
        // Terapeuta solo puede borrar sus propias notas
        if ($userRole === 'therapist') {
            pdoQuery($pdo, "DELETE FROM session_notes WHERE id = ? AND therapist_id = ?", [$id, $userId]);
        } else {
            pdoQuery($pdo, "DELETE FROM session_notes WHERE id = ?", [$id]);
        }
        appLog($pdo, 'session_note.delete', 'session_note', (string)$id);
        echo json_encode(['success' => true, 'message' => 'Nota eliminada']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todo no permitido']);
}
