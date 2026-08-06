<?php
// api/protocols.php - Manejo de protocolos y asignación a pacientes
ob_start();
session_start();
header('Content-Type: application/json');
register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal: ' . $error['message'] . ' en ' . basename($error['file']) . ':' . $error['line']
    ]);
});
try {
    require_once '../db.php';
    require_once '../includes/csrf.php';
    verifyCsrfRequest();
    ensureProtocolSchema($pdo);
    ensurePackagesSchema($pdo);
    ensureAuditSchema($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userRole = $_SESSION['role'] ?? '';
$userId   = $_SESSION['user_id'] ?? 0;

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'GET') {
    // Si se pasa id, obtener un protocolo con sus fases
    if (isset($_GET['id'])) {
        $protocol = pdoQuery($pdo, "SELECT * FROM treatment_protocols WHERE id = ?", [(int)$_GET['id']])->fetch();
        if ($protocol) {
            $phases = pdoQuery($pdo, "SELECT * FROM protocol_phases WHERE protocol_id = ? ORDER BY step_order ASC", [$protocol['id']])->fetchAll();
            $protocol['phases'] = $phases;
            $protocol['recommended_package'] = null;
            if (!empty($protocol['recommended_package_template_id'])) {
                $protocol['recommended_package'] = pdoQuery($pdo, "
                    SELECT id, name, total_sessions, total_amount
                    FROM package_templates
                    WHERE id = ?
                    LIMIT 1
                ", [(int)$protocol['recommended_package_template_id']])->fetch() ?: null;
            }
            echo json_encode($protocol);
        } else {
            echo json_encode(['error' => 'No encontrado']);
        }
    } 
    // Por defecto, listar todos los protocolos
    else {
        $protocols = pdoQuery($pdo, "SELECT * FROM treatment_protocols ORDER BY name ASC")->fetchAll();
        foreach ($protocols as &$protocol) {
            $protocol['recommended_package'] = null;
            if (!empty($protocol['recommended_package_template_id'])) {
                $protocol['recommended_package'] = pdoQuery($pdo, "
                    SELECT id, name, total_sessions, total_amount
                    FROM package_templates
                    WHERE id = ?
                    LIMIT 1
                ", [(int)$protocol['recommended_package_template_id']])->fetch() ?: null;
            }
        }
        unset($protocol);
        echo json_encode($protocols);
    }
    exit;
}

if ($method === 'POST') {
    // Si se pasa patient_id y protocol_id, es una asignación
    if (isset($data['patient_id']) && isset($data['protocol_id'])) {
        try {
            $pdo->beginTransaction();
            
            $proto = pdoQuery($pdo, "SELECT * FROM treatment_protocols WHERE id = ?", [(int)$data['protocol_id']])->fetch();
            if (!$proto) throw new Exception("Protocolo no encontrado");
            $assignRecommendedPackage = !empty($data['assign_recommended_package']);
            $forceReplacePlan = !empty($data['force_replace_plan']);

            if ($forceReplacePlan && $userRole !== 'admin') {
                throw new Exception("Solo el administrador puede cambiar un plan ya iniciado");
            }

            $activePlan = pdoQuery($pdo, "
                SELECT id
                FROM treatment_plans
                WHERE patient_id = ?
                  AND status = 'active'
                ORDER BY id DESC
                LIMIT 1
            ", [(int)$data['patient_id']])->fetch();

            if ($activePlan && !$forceReplacePlan) {
                throw new Exception("El paciente ya tiene un plan activo. Usa 'Cambiar plan' para reemplazarlo.");
            }

            if ($activePlan && $forceReplacePlan) {
                pdoQuery($pdo, "
                    UPDATE treatment_plans
                    SET status = 'on_hold', end_date = CURDATE()
                    WHERE id = ?
                ", [(int)$activePlan['id']]);
            }

            if (!empty($data['clinical_history_id'])) {
                $existingPlan = pdoQuery($pdo, "
                    SELECT id
                    FROM treatment_plans
                    WHERE clinical_history_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ", [(int)$data['clinical_history_id']])->fetch();
                if ($existingPlan) {
                    if (!$forceReplacePlan) {
                        throw new Exception("Esta historia clinica ya tiene un plan enlazado");
                    }
                    pdoQuery($pdo, "
                        UPDATE treatment_plans
                        SET status = 'on_hold', end_date = CURDATE()
                        WHERE id = ?
                    ", [(int)$existingPlan['id']]);
                }
            }

            // 1. Crear el plan de tratamiento
            pdoQuery($pdo, "
                INSERT INTO treatment_plans (patient_id, title, protocol_id, clinical_history_id, total_sessions, completed_sessions, status, start_date, duration_weeks)
                VALUES (?, ?, ?, ?, ?, 0, 'active', CURDATE(), 12)
            ", [
                (int)$data['patient_id'],
                $proto['name'],
                $proto['id'],
                !empty($data['clinical_history_id']) ? (int)$data['clinical_history_id'] : null,
                $proto['total_sessions']
            ]);
            $planId = $pdo->lastInsertId();

            // 2. Obtener fases para generar sesiones
            $phases = pdoQuery($pdo, "SELECT * FROM protocol_phases WHERE protocol_id = ? ORDER BY step_order ASC", [$proto['id']])->fetchAll();
            $expectedTotal = max(1, (int)($proto['total_sessions'] ?? 0));
            if (!$phases || count($phases) === 0) {
                pdoQuery($pdo, "
                    INSERT INTO protocol_phases (protocol_id, name, sessions_count, step_order, objectives, activities)
                    VALUES (?, 'Fase I', ?, 1, '', '')
                ", [(int)$proto['id'], $expectedTotal]);
                $phases = pdoQuery($pdo, "SELECT * FROM protocol_phases WHERE protocol_id = ? ORDER BY step_order ASC", [$proto['id']])->fetchAll();
            }
            $phaseTargetMeta = buildPlanPhaseTargets($phases, $expectedTotal);
            $phaseTargets = $phaseTargetMeta['targets'] ?? [];
            $expectedTotal = max(1, (int)($phaseTargetMeta['total'] ?? $expectedTotal));
            $sessionNumber = 1;
            
            foreach ($phases as $phase) {
                $phaseId = (int)($phase['id'] ?? 0);
                $count = (int)($phaseTargets[$phaseId] ?? 0);
                for ($i = 1; $i <= $count; $i++) {
                    pdoQuery($pdo, "
                        INSERT INTO patient_sessions (plan_id, phase_id, session_number, title, status)
                        VALUES (?, ?, ?, ?, 'pending')
                    ", [
                        $planId,
                        $phaseId,
                        $sessionNumber,
                        $phase['name'] . " - Sesion " . $sessionNumber
                    ]);
                    $sessionNumber++;
                }
            }

            syncTreatmentPlanFromSessions($pdo, (int)$planId);

            if ($assignRecommendedPackage && !empty($proto['recommended_package_template_id'])) {
                $template = pdoQuery($pdo, "
                    SELECT id, name, total_sessions, total_amount
                    FROM package_templates
                    WHERE id = ? AND is_active = 1
                    LIMIT 1
                ", [(int)$proto['recommended_package_template_id']])->fetch();

                if ($template) {
                    $existingPackage = pdoQuery($pdo, "
                        SELECT id
                        FROM packages
                        WHERE patient_id = ? AND template_id = ?
                        ORDER BY id DESC
                        LIMIT 1
                    ", [(int)$data['patient_id'], (int)$template['id']])->fetch();

                    if (!$existingPackage) {
                        pdoQuery($pdo, "
                            INSERT INTO packages (patient_id, template_id, name, total_sessions, unused_sessions, total_amount, amount_paid, purchase_date)
                            VALUES (?, ?, ?, ?, ?, ?, 0, CURDATE())
                        ", [
                            (int)$data['patient_id'],
                            (int)$template['id'],
                            $template['name'],
                            (int)$template['total_sessions'],
                            (int)$template['total_sessions'],
                            (float)$template['total_amount']
                        ]);
                    }
                }
            }

            $pdo->commit();
            appLog($pdo, 'protocol.assign', 'treatment_plan', (string)$planId, [
                'patient_id' => (int)$data['patient_id'],
                'protocol_id' => (int)$proto['id'],
                'clinical_history_id' => !empty($data['clinical_history_id']) ? (int)$data['clinical_history_id'] : null,
                'assign_recommended_package' => $assignRecommendedPackage,
                'force_replace_plan' => $forceReplacePlan ? 1 : 0,
            ]);
            $message = 'Protocolo asignado y ' . $expectedTotal . ' sesiones generadas';
            if ($assignRecommendedPackage && !empty($proto['recommended_package_template_id'])) {
                $message .= '. Paquete sugerido asignado al paciente';
            }
            echo json_encode(['success' => true, 'message' => $message]);
        } catch(Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Si es creación de nuevo protocolo (Admin)
    if (in_array($userRole, ['admin', 'therapist'], true)) {
         try {
             $name = trim($data['name'] ?? '');
             $description = trim($data['description'] ?? '');
             $totalSessions = (int)($data['total_sessions'] ?? 0);
             $recommendedPackageTemplateId = (int)($data['recommended_package_template_id'] ?? 0);
             $phasesData = isset($data['phases']) && is_array($data['phases']) ? $data['phases'] : [];

             if ($name === '' || $totalSessions <= 0) {
                 throw new Exception('Completa el nombre del protocolo y el total de sesiones');
             }

             if (!$phasesData) {
                 throw new Exception('Agrega al menos una fase al protocolo');
             }

             $pdo->beginTransaction();
             pdoQuery($pdo, "INSERT INTO treatment_protocols (name, description, total_sessions, recommended_package_template_id) VALUES (?, ?, ?, ?)", 
                 [$name, $description, $totalSessions, $recommendedPackageTemplateId ?: null]);
             $protoId = $pdo->lastInsertId();
             
             foreach ($phasesData as $i => $phase) {
                 $phaseName = trim($phase['name'] ?? '');
                 $sessionsCount = (int)($phase['sessions_count'] ?? 0);
                 $objectives = trim($phase['objectives'] ?? '');
                 $activities = trim($phase['activities'] ?? '');

                 if ($phaseName === '' || $sessionsCount <= 0) {
                     continue;
                 }

                 try {
                     pdoQuery($pdo, "INSERT INTO protocol_phases (protocol_id, name, sessions_count, step_order, objectives, activities) VALUES (?, ?, ?, ?, ?, ?)", 
                         [$protoId, $phaseName, $sessionsCount, $i + 1, $objectives, $activities]);
                 } catch (Exception $phaseError) {
                     pdoQuery($pdo, "INSERT INTO protocol_phases (protocol_id, name, sessions_count, step_order) VALUES (?, ?, ?, ?)", 
                         [$protoId, $phaseName, $sessionsCount, $i + 1]);
                 }
             }

             $pdo->commit();
             appLog($pdo, 'protocol.create', 'protocol', (string)$protoId, [
                 'name' => $name,
                 'total_sessions' => $totalSessions,
                 'recommended_package_template_id' => $recommendedPackageTemplateId ?: null,
                 'phases_count' => count($phasesData),
             ]);
             echo json_encode(['success' => true, 'id' => $protoId]);
         } catch(Exception $e) {
             if ($pdo->inTransaction()) {
                 $pdo->rollBack();
             }
             echo json_encode(['success' => false, 'error' => $e->getMessage()]);
         }
         exit;
    }
}

if ($method === 'PUT' && $userRole === 'admin') {
    try {
        $pdo->beginTransaction();
        $protoId = (int)$data['id'];
        
        // 1. Actualizar datos básicos del protocolo
        pdoQuery($pdo, "UPDATE treatment_protocols SET name = ?, description = ?, total_sessions = ?, recommended_package_template_id = ? WHERE id = ?", 
            [$data['name'], $data['description'], (int)$data['total_sessions'], ((int)($data['recommended_package_template_id'] ?? 0)) ?: null, $protoId]);
        
        // 2. Eliminar fases anteriores para re-insertar
        pdoQuery($pdo, "DELETE FROM protocol_phases WHERE protocol_id = ?", [$protoId]);
        
        // 3. Insertar nuevas fases (incluyendo objetivos y actividades)
        if (isset($data['phases']) && is_array($data['phases'])) {
            foreach ($data['phases'] as $i => $phase) {
                pdoQuery($pdo, "INSERT INTO protocol_phases (protocol_id, name, sessions_count, step_order, objectives, activities) VALUES (?, ?, ?, ?, ?, ?)", 
                    [$protoId, $phase['name'], (int)$phase['sessions_count'], $i + 1, $phase['objectives'] ?? '', $phase['activities'] ?? '']);
            }
        }
        
        $pdo->commit();
        appLog($pdo, 'protocol.update', 'protocol', (string)$protoId, [
            'name' => $data['name'] ?? null,
            'total_sessions' => (int)($data['total_sessions'] ?? 0),
            'phases_count' => isset($data['phases']) && is_array($data['phases']) ? count($data['phases']) : 0,
        ]);
        echo json_encode(['success' => true]);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE' && $userRole === 'admin') {
    $protoId = (int)$data['id'];
    pdoQuery($pdo, "DELETE FROM treatment_protocols WHERE id = ?", [$protoId]);
    appLog($pdo, 'protocol.delete', 'protocol', (string)$protoId);
    echo json_encode(['success' => true]);
    exit;
}
?>
