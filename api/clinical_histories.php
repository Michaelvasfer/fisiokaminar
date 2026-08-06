<?php
// api/clinical_histories.php - Manejo de historias clínicas rápidas
session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once '../includes/auth_helper.php';
require_once '../includes/csrf.php';
verifyCsrfRequest();
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userRole = $_SESSION['role'] ?? '';
$userId   = $_SESSION['user_id'] ?? 0;

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

try {
    $planColumns = $pdo->query("SHOW COLUMNS FROM treatment_plans")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('clinical_history_id', $planColumns)) {
        $pdo->exec("ALTER TABLE treatment_plans ADD COLUMN clinical_history_id INT NULL DEFAULT NULL");
    }
} catch (Exception $e) {}

if ($method === 'GET') {
    if (isset($_GET['patient_id'])) {
        $histories = pdoQuery($pdo, "
            SELECT ch.*,
                   tp.id AS linked_plan_id,
                   tp.title AS linked_plan_title,
                   tp.status AS linked_plan_status,
                   tp.completed_sessions AS linked_plan_completed_sessions,
                   tp.total_sessions AS linked_plan_total_sessions
            FROM clinical_histories ch
            LEFT JOIN treatment_plans tp ON tp.id = (
                SELECT tp2.id
                FROM treatment_plans tp2
                WHERE tp2.clinical_history_id = ch.id
                ORDER BY tp2.id DESC
                LIMIT 1
            )
            WHERE ch.patient_id = ?
            ORDER BY ch.created_at DESC, tp.id DESC
        ", [(int)$_GET['patient_id']])->fetchAll();
        echo json_encode($histories);
    } elseif (isset($_GET['id'])) {
        $history = pdoQuery($pdo, "
            SELECT ch.*,
                   tp.id AS linked_plan_id,
                   tp.title AS linked_plan_title,
                   tp.status AS linked_plan_status,
                   tp.completed_sessions AS linked_plan_completed_sessions,
                   tp.total_sessions AS linked_plan_total_sessions
            FROM clinical_histories ch
            LEFT JOIN treatment_plans tp ON tp.id = (
                SELECT tp2.id
                FROM treatment_plans tp2
                WHERE tp2.clinical_history_id = ch.id
                ORDER BY tp2.id DESC
                LIMIT 1
            )
            WHERE ch.id = ?
            ORDER BY tp.id DESC
            LIMIT 1
        ", [(int)$_GET['id']])->fetch();
        echo json_encode($history);
    }
    exit;
}

if ($method === 'POST') {
    if (!hasPermission($pdo, $userId, $userRole, 'add_clinical_hx')) {
        echo json_encode(['success' => false, 'error' => 'No tienes permiso para historia clínica']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Guardar la Historia Clínica
        $sql = "INSERT INTO clinical_histories (
            patient_id, therapist_id, reason_location, evolution_time, medical_diagnosis, 
            eva_score, pain_type, worsens_with, mobility, strength, 
            inflammation, functional_test, main_objective, indicated_sessions, frequency, 
            initial_treatment, observations
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['patient_id'],
            $userId,
            $data['reason_location'],
            $data['evolution_time'],
            $data['medical_diagnosis'] ?? '',
            (int)$data['eva_score'],
            $data['pain_type'],
            $data['worsens_with'],
            $data['mobility'],
            $data['strength'],
            (int)$data['inflammation'],
            $data['functional_test'] ?? '',
            $data['main_objective'],
            (int)$data['indicated_sessions'],
            $data['frequency'],
            $data['initial_treatment'] ?? '',
            $data['observations'] ?? ''
        ];

        pdoQuery($pdo, $sql, $params);
        $historyId = $pdo->lastInsertId();

        // 2. Si se seleccionó un protocolo, asignarlo automáticamente
        if (!empty($data['protocol_id'])) {
            $proto = pdoQuery($pdo, "SELECT * FROM treatment_protocols WHERE id = ?", [(int)$data['protocol_id']])->fetch();
            if ($proto) {
                // Crear el plan de tratamiento
                pdoQuery($pdo, "
                    INSERT INTO treatment_plans (patient_id, title, protocol_id, clinical_history_id, total_sessions, completed_sessions, status, start_date, duration_weeks)
                    VALUES (?, ?, ?, ?, ?, 0, 'active', CURDATE(), 12)
                ", [
                    (int)$data['patient_id'],
                    $proto['name'],
                    $proto['id'],
                    $historyId,
                    $proto['total_sessions']
                ]);
                $planId = $pdo->lastInsertId();

                // Generar sesiones iniciales basada en las fases
                $phases = pdoQuery($pdo, "SELECT * FROM protocol_phases WHERE protocol_id = ? ORDER BY step_order ASC", [$proto['id']])->fetchAll();
                foreach ($phases as $phase) {
                    $count = (int)$phase['sessions_count'];
                    for ($i = 1; $i <= $count; $i++) {
                        pdoQuery($pdo, "
                            INSERT INTO patient_sessions (plan_id, phase_id, session_number, title, status)
                            VALUES (?, ?, ?, ?, 'pending')
                        ", [
                            $planId,
                            $phase['id'],
                            $i,
                            $phase['name'] . " - Sesión " . $i
                        ]);
                    }
                }
            }
        }

        $pdo->commit();
        appLog($pdo, 'clinical_history.create', 'clinical_history', (string)$historyId, [
            'patient_id' => (int)$data['patient_id'],
            'protocol_id' => !empty($data['protocol_id']) ? (int)$data['protocol_id'] : null,
            'indicated_sessions' => (int)$data['indicated_sessions'],
        ]);
        echo json_encode(['success' => true, 'id' => $historyId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
