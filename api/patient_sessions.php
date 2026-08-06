<?php
// api/patient_sessions.php - Manejo del progreso de sesiones del paciente
session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once '../includes/csrf.php';
verifyCsrfRequest();
ensureProtocolSchema($pdo);
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userRole = $_SESSION['role'] ?? '';
$userId   = $_SESSION['user_id'] ?? 0;

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

if (!function_exists('rebuildPlanSessionsStructure')) {
    function rebuildPlanSessionsStructure($pdo, $planId) {
        $plan = pdoQuery($pdo, "
            SELECT id, patient_id, protocol_id, total_sessions
            FROM treatment_plans
            WHERE id = ?
            LIMIT 1
        ", [(int)$planId])->fetch();
        if (!$plan) {
            throw new Exception('Plan no encontrado');
        }

        $protocolId = (int)($plan['protocol_id'] ?? 0);
        if ($protocolId <= 0) {
            throw new Exception('El plan no tiene protocolo asociado');
        }

        $protoMeta = pdoQuery($pdo, "SELECT total_sessions FROM treatment_protocols WHERE id = ? LIMIT 1", [$protocolId])->fetch();
        $protocolTotal = max(0, (int)($protoMeta['total_sessions'] ?? 0));
        $planTotal = max(0, (int)($plan['total_sessions'] ?? 0));

        $phases = pdoQuery($pdo, "
            SELECT id, name, sessions_count
            FROM protocol_phases
            WHERE protocol_id = ?
            ORDER BY step_order ASC, id ASC
        ", [$protocolId])->fetchAll();

        if (!$phases || count($phases) === 0) {
            $fallback = $protocolTotal > 0 ? $protocolTotal : ($planTotal > 0 ? $planTotal : 10);
            pdoQuery($pdo, "
                INSERT INTO protocol_phases (protocol_id, name, sessions_count, step_order, objectives, activities)
                VALUES (?, 'Fase I', ?, 1, '', '')
            ", [$protocolId, $fallback]);
            $phases = pdoQuery($pdo, "
                SELECT id, name, sessions_count
                FROM protocol_phases
                WHERE protocol_id = ?
                ORDER BY step_order ASC, id ASC
            ", [$protocolId])->fetchAll();
        }

        $targetTotal = $protocolTotal > 0 ? $protocolTotal : $planTotal;
        $phaseTargetMeta = buildPlanPhaseTargets($phases, $targetTotal);
        $phaseTargets = $phaseTargetMeta['targets'] ?? [];

        $existingPerPhase = [];
        $maxSessionNumber = (int)(pdoQuery($pdo, "SELECT COALESCE(MAX(session_number), 0) AS max_n FROM patient_sessions WHERE plan_id = ?", [(int)$planId])->fetchColumn() ?: 0);
        $rows = pdoQuery($pdo, "
            SELECT phase_id, COUNT(*) AS total
            FROM patient_sessions
            WHERE plan_id = ?
            GROUP BY phase_id
        ", [(int)$planId])->fetchAll();
        foreach ($rows as $r) {
            $existingPerPhase[(int)$r['phase_id']] = (int)$r['total'];
        }

        $inserted = 0;
        $nextSession = $maxSessionNumber + 1;
        foreach ($phases as $phase) {
            $phaseId = (int)$phase['id'];
            $phaseName = trim((string)($phase['name'] ?? 'Fase'));
            $target = max(0, (int)($phaseTargets[$phaseId] ?? 0));
            $current = (int)($existingPerPhase[$phaseId] ?? 0);
            $missing = $target - $current;
            for ($i = 0; $i < $missing; $i++) {
                pdoQuery($pdo, "
                    INSERT INTO patient_sessions (plan_id, phase_id, session_number, title, status)
                    VALUES (?, ?, ?, ?, 'pending')
                ", [(int)$planId, $phaseId, $nextSession, $phaseName . ' - Sesion ' . $nextSession]);
                $inserted++;
                $nextSession++;
            }
        }

        $sync = syncTreatmentPlanFromSessions($pdo, (int)$planId);

        return [
            'plan_id' => (int)$planId,
            'inserted' => $inserted,
            'total_sessions' => (int)($sync['total_sessions'] ?? 0),
            'completed_sessions' => (int)($sync['completed_sessions'] ?? 0),
            'status' => (string)($sync['status'] ?? 'active')
        ];
    }
}

if ($method === 'GET') {
    if (isset($_GET['plan_id'])) {
        $sessions = pdoQuery($pdo, "SELECT * FROM patient_sessions WHERE plan_id = ? ORDER BY session_number ASC, id ASC", [(int)$_GET['plan_id']])->fetchAll();
        echo json_encode($sessions);
    } elseif (isset($_GET['id'])) {
        $session = pdoQuery($pdo, "SELECT * FROM patient_sessions WHERE id = ?", [(int)$_GET['id']])->fetch();
        if ($session) {
            $pendingBefore = pdoQuery(
                $pdo,
                "SELECT COUNT(*) AS total
                 FROM patient_sessions
                 WHERE plan_id = ?
                   AND (
                        session_number < ?
                        OR (session_number = ? AND id < ?)
                   )
                   AND status <> 'completed'",
                [
                    (int)$session['plan_id'],
                    (int)$session['session_number'],
                    (int)$session['session_number'],
                    (int)$session['id']
                ]
            )->fetch();
            $session['can_complete'] = ((int)($pendingBefore['total'] ?? 0) === 0);
        }
        echo json_encode($session);
    }
    exit;
}

if ($method === 'PUT') {
    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $sql = "UPDATE patient_sessions SET status = ?, observations = ?, evolution = ?, eva_score = ?, mobility_notes = ?, treatment_changes = ?";
        $params = [
            $data['status'],
            $data['observations'] ?? null,
            $data['evolution'] ?? null,
            (int)($data['eva_score'] ?? 0),
            $data['mobility_notes'] ?? null,
            $data['treatment_changes'] ?? null
        ];

        if ($data['status'] === 'completed') {
            $sql .= ", completed_date = NOW()";
        } else {
            $sql .= ", completed_date = NULL";
        }

        $sql .= " WHERE id = ?";
        $params[] = (int)$data['id'];

        // Obtener estado anterior y orden para validar secuencia.
        $oldSess = pdoQuery($pdo, "SELECT id, status, plan_id, session_number FROM patient_sessions WHERE id = ?", [(int)$data['id']])->fetch();
        if (!$oldSess) {
            throw new Exception('Sesion no encontrada');
        }

        // Regla estricta: no se puede completar una sesion si hay una previa sin completar.
        if (($data['status'] ?? '') === 'completed' && ($oldSess['status'] ?? '') !== 'completed') {
            $pendingBefore = pdoQuery(
                $pdo,
                "SELECT COUNT(*) AS total
                 FROM patient_sessions
                 WHERE plan_id = ?
                   AND (
                        session_number < ?
                        OR (session_number = ? AND id < ?)
                   )
                   AND status <> 'completed'",
                [
                    (int)$oldSess['plan_id'],
                    (int)$oldSess['session_number'],
                    (int)$oldSess['session_number'],
                    (int)$oldSess['id']
                ]
            )->fetch();
            if ((int)($pendingBefore['total'] ?? 0) > 0) {
                throw new Exception('Debes completar primero las sesiones anteriores');
            }
        }

        pdoQuery($pdo, $sql, $params);

        // Recalcular totales reales del plan para evitar desfases por doble registro/reversiones.
        syncTreatmentPlanFromSessions($pdo, (int)$oldSess['plan_id']);

        $pdo->commit();
        appLog($pdo, 'patient_session.update', 'patient_session', (string)((int)$data['id']), [
            'status' => $data['status'] ?? null,
            'eva_score' => (int)($data['eva_score'] ?? 0),
            'plan_id' => $oldSess['plan_id'] ?? null,
        ]);
        echo json_encode(['success' => true]);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $action = trim((string)($data['action'] ?? ''));
    if ($action !== 'rebuild_plan') {
        echo json_encode(['success' => false, 'error' => 'Accion no valida']);
        exit;
    }

    if ($userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Solo el admin puede reconstruir el plan']);
        exit;
    }

    $planId = (int)($data['plan_id'] ?? 0);
    if ($planId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Plan requerido']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $result = rebuildPlanSessionsStructure($pdo, $planId);
        $pdo->commit();
        appLog($pdo, 'treatment_plan.rebuild', 'treatment_plan', (string)$planId, $result);
        echo json_encode(['success' => true, 'message' => 'Plan reconstruido', 'result' => $result]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
