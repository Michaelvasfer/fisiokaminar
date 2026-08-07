<?php
// api/appointments.php - CRUD de citas
session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once '../includes/auth_helper.php';
require_once '../includes/csrf.php';
verifyCsrfRequest();
ensureAuditSchema($pdo);
ensureProtocolSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']); exit;
}

$userRole = $_SESSION['role'];
$userId   = $_SESSION['user_id'];
$method   = $_SERVER['REQUEST_METHOD'];
$body     = json_decode(file_get_contents('php://input'), true) ?? [];

if (!function_exists('countOverlappingAppointments')) {
    function countOverlappingAppointments($pdo, $date, $startTime, $endTime, $column, $value, $excludeId = 0) {
        $sql = "
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE appointment_date = ?
              AND {$column} = ?
              AND status != 'cancelled'
              AND start_time < ?
              AND end_time > ?
        ";
        $params = [$date, $value, $endTime, $startTime];

        if ($excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $row = pdoQuery($pdo, $sql, $params)->fetch();
        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('ensurePlanSessionsGenerated')) {
    function ensurePlanSessionsGenerated($pdo, $planId, $protocolId) {
        $planId = (int)$planId;
        $protocolId = (int)$protocolId;
        if ($planId <= 0) {
            return;
        }

        if ($protocolId <= 0) {
            $planMeta = pdoQuery($pdo, "SELECT protocol_id FROM treatment_plans WHERE id = ? LIMIT 1", [$planId])->fetch();
            $protocolId = (int)($planMeta['protocol_id'] ?? 0);
        }
        if ($protocolId <= 0) {
            return;
        }

        $planMeta = pdoQuery($pdo, "SELECT total_sessions FROM treatment_plans WHERE id = ? LIMIT 1", [$planId])->fetch();
        $planTotalSessions = max(0, (int)($planMeta['total_sessions'] ?? 0));
        $protoMeta = pdoQuery($pdo, "SELECT total_sessions FROM treatment_protocols WHERE id = ? LIMIT 1", [$protocolId])->fetch();
        $protocolTotalSessions = max(0, (int)($protoMeta['total_sessions'] ?? 0));

        $phases = pdoQuery($pdo, "
            SELECT id, name, sessions_count
            FROM protocol_phases
            WHERE protocol_id = ?
            ORDER BY step_order ASC, id ASC
        ", [$protocolId])->fetchAll();

        // Si el protocolo quedo sin fases, crear una fase unica para no dejar el plan en 0/0.
        if (!$phases || count($phases) === 0) {
            $fallbackSessions = $protocolTotalSessions > 0 ? $protocolTotalSessions : ($planTotalSessions > 0 ? $planTotalSessions : 10);
            pdoQuery($pdo, "
                INSERT INTO protocol_phases (protocol_id, name, sessions_count, step_order, objectives, activities)
                VALUES (?, 'Fase I', ?, 1, '', '')
            ", [$protocolId, $fallbackSessions]);

            $phases = pdoQuery($pdo, "
                SELECT id, name, sessions_count
                FROM protocol_phases
                WHERE protocol_id = ?
                ORDER BY step_order ASC, id ASC
            ", [$protocolId])->fetchAll();
            if (!$phases || count($phases) === 0) {
                return;
            }
        }

        $targetTotal = $protocolTotalSessions > 0 ? $protocolTotalSessions : $planTotalSessions;
        $phaseTargetMeta = buildPlanPhaseTargets($phases, $targetTotal);
        $phaseTargets = $phaseTargetMeta['targets'] ?? [];

        $existingCounts = [];
        $existingRows = pdoQuery($pdo, "
            SELECT phase_id, COUNT(*) AS total
            FROM patient_sessions
            WHERE plan_id = ?
            GROUP BY phase_id
        ", [$planId])->fetchAll();
        foreach ($existingRows as $row) {
            $existingCounts[(int)$row['phase_id']] = (int)$row['total'];
        }

        $maxSession = pdoQuery($pdo, "SELECT COALESCE(MAX(session_number), 0) AS max_n FROM patient_sessions WHERE plan_id = ?", [$planId])->fetch();
        $nextNumber = ((int)($maxSession['max_n'] ?? 0)) + 1;

        foreach ($phases as $phase) {
            $phaseId = (int)$phase['id'];
            $phaseName = trim((string)($phase['name'] ?? 'Fase'));
            $target = max(0, (int)($phaseTargets[$phaseId] ?? 0));
            $current = (int)($existingCounts[$phaseId] ?? 0);
            $missing = $target - $current;
            for ($i = 0; $i < $missing; $i++) {
                pdoQuery($pdo, "
                    INSERT INTO patient_sessions (plan_id, phase_id, session_number, title, status)
                    VALUES (?, ?, ?, ?, 'pending')
                ", [$planId, $phaseId, $nextNumber, $phaseName . ' - Sesion ' . $nextNumber]);
                $nextNumber++;
            }
        }

        syncTreatmentPlanFromSessions($pdo, $planId);
    }
}

if (!function_exists('syncPlanByAttendanceStatus')) {
    function syncPlanByAttendanceStatus($pdo, $appointmentId, $patientId, $markAsCompleted, $appointmentDate = null) {
        $appointmentId = (int)$appointmentId;
        $patientId = (int)$patientId;
        if ($appointmentId <= 0 || $patientId <= 0) {
            return;
        }

        $plan = pdoQuery($pdo, "
            SELECT id, protocol_id
            FROM treatment_plans
            WHERE patient_id = ? AND status IN ('active', 'completed')
            ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END, id DESC
            LIMIT 1
        ", [$patientId])->fetch();
        if (!$plan) {
            return;
        }

        $planId = (int)$plan['id'];
        $protocolId = (int)($plan['protocol_id'] ?? 0);
        ensurePlanSessionsGenerated($pdo, $planId, $protocolId);

        if ($markAsCompleted) {
            $linkedSession = pdoQuery($pdo, "
                SELECT id
                FROM patient_sessions
                WHERE plan_id = ? AND appointment_id = ?
                LIMIT 1
            ", [$planId, $appointmentId])->fetch();

            if ($linkedSession) {
                pdoQuery($pdo, "
                    UPDATE patient_sessions
                    SET status = 'completed',
                        completed_date = CASE WHEN completed_date IS NULL THEN NOW() ELSE completed_date END,
                        scheduled_date = COALESCE(scheduled_date, ?)
                    WHERE id = ?
                ", [$appointmentDate ?: null, (int)$linkedSession['id']]);
            } else {
                $nextSession = pdoQuery($pdo, "
                    SELECT id
                    FROM patient_sessions
                    WHERE plan_id = ?
                      AND status = 'pending'
                      AND appointment_id IS NULL
                    ORDER BY session_number ASC, id ASC
                    LIMIT 1
                ", [$planId])->fetch();

                if ($nextSession) {
                    pdoQuery($pdo, "
                        UPDATE patient_sessions
                        SET status = 'completed',
                            completed_date = NOW(),
                            scheduled_date = COALESCE(scheduled_date, ?),
                            appointment_id = ?
                        WHERE id = ?
                    ", [$appointmentDate ?: null, $appointmentId, (int)$nextSession['id']]);
                }
            }
        } else {
            $linkedSession = pdoQuery($pdo, "
                SELECT id
                FROM patient_sessions
                WHERE plan_id = ? AND appointment_id = ?
                LIMIT 1
            ", [$planId, $appointmentId])->fetch();

            if ($linkedSession) {
                pdoQuery($pdo, "
                    UPDATE patient_sessions
                    SET status = 'pending',
                        completed_date = NULL,
                        appointment_id = NULL
                    WHERE id = ?
                ", [(int)$linkedSession['id']]);
            } else {
                // Compatibilidad con registros antiguos creados antes del vinculo cita-sesion.
                $legacySession = pdoQuery($pdo, "
                    SELECT id
                    FROM patient_sessions
                    WHERE plan_id = ?
                      AND status = 'completed'
                      AND appointment_id IS NULL
                    ORDER BY session_number DESC, id DESC
                    LIMIT 1
                ", [$planId])->fetch();
                if ($legacySession) {
                    pdoQuery($pdo, "
                        UPDATE patient_sessions
                        SET status = 'pending',
                            completed_date = NULL
                        WHERE id = ?
                    ", [(int)$legacySession['id']]);
                }
            }
        }

        syncTreatmentPlanFromSessions($pdo, $planId);
    }
}

try {
    $columns = $pdo->query("SHOW COLUMNS FROM appointments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('checked_in_at', $columns)) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN checked_in_at DATETIME NULL DEFAULT NULL");
    }
    if (!in_array('checked_in_by', $columns)) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN checked_in_by INT NULL DEFAULT NULL");
    }
    if (!in_array('created_by', $columns)) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN created_by INT NULL DEFAULT NULL");
    }
} catch (Exception $e) {
}

switch ($method) {

    case 'GET':
        $date = $_GET['date'] ?? date('Y-m-d');
        $stmt = pdoQuery($pdo, "
            SELECT a.*, u.name AS patient_name, t.name AS therapist_name
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            JOIN users t ON a.therapist_id = t.id
            WHERE a.appointment_date = ?
            ORDER BY a.start_time ASC
        ", [$date]);
        echo json_encode(['success' => true, 'appointments' => $stmt->fetchAll()]);
        break;

    case 'POST':
        if (!hasPermission($pdo, $userId, $userRole, 'add_apt')) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Sin permiso para agregar citas']); exit;
        }
        $patientId    = (int)($body['patient_id'] ?? 0);
        $therapistId  = (int)($body['therapist_id'] ?? 0);
        $date         = trim($body['appointment_date'] ?? '');
        $startTime    = trim($body['start_time'] ?? '');
        $endTime      = trim($body['end_time'] ?? '');
        $type         = trim($body['type'] ?? 'Sesion General');
        $status       = trim($body['status'] ?? 'scheduled');
        $notes        = trim($body['notes'] ?? '');

        if (!$patientId || !$therapistId || !$date || !$startTime || !$endTime) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Faltan datos obligatorios']); exit;
        }
        $therapist = pdoQuery($pdo, "SELECT id, is_active FROM users WHERE id = ? AND role = 'therapist' LIMIT 1", [$therapistId])->fetch();
        if (!$therapist || (int)($therapist['is_active'] ?? 0) !== 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El fisioterapeuta seleccionado ya no esta disponible']); exit;
        }
        if ($endTime <= $startTime) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'La hora de fin debe ser mayor que la hora de inicio']); exit;
        }

        // Validacion inteligente
        $now = new DateTime();
        $selectedDate = new DateTime($date . ' ' . $startTime);
        
        if ($selectedDate < $now && $date !== date('Y-m-d')) {
            echo json_encode(['success' => false, 'error' => 'No se puede agendar en el pasado']); exit;
        }
        
        if ($selectedDate->format('N') == 7) { // 7 = Domingo
            echo json_encode(['success' => false, 'error' => 'No se trabaja los domingos']); exit;
        }
        $patientOverlapCount = countOverlappingAppointments($pdo, $date, $startTime, $endTime, 'patient_id', $patientId);
        if ($patientOverlapCount >= 1) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'El paciente ya tiene una cita en ese horario']); exit;
        }

        $stmt = pdoQuery($pdo,
            "INSERT INTO appointments (patient_id, therapist_id, appointment_date, start_time, end_time, type, notes, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$patientId, $therapistId, $date, $startTime, $endTime, $type, $notes, $status, $userId]
        );
        $newId = $pdo->lastInsertId();
        if ($status === 'completed') {
            pdoQuery($pdo, "UPDATE appointments SET checked_in_at = NOW(), checked_in_by = ? WHERE id = ?", [$userId, $newId]);
        }

        // Sincronizar con plan de tratamiento si se marca asistencia.
        if ($status === 'completed') {
            syncPlanByAttendanceStatus($pdo, (int)$newId, $patientId, true, $date);
        }

        appLog($pdo, 'appointment.create', 'appointment', (string)$newId, [
            'patient_id' => $patientId,
            'therapist_id' => $therapistId,
            'appointment_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
            'type' => $type,
        ]);
        echo json_encode(['success' => true, 'message' => 'Cita agendada exitosamente', 'id' => $newId]);
        break;

    case 'PUT':
        $id     = (int)($body['id'] ?? 0);
        $status = trim($body['status'] ?? '');
        $notes  = trim($body['notes'] ?? '');
        $therapist_id = (int)($body['therapist_id'] ?? 0);

        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'ID requerido']); exit; }

        if (!hasPermission($pdo, $userId, $userRole, 'edit_apt')) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Sin permiso para modificar citas']); exit;
        }

        // Reagendar: nueva fecha y/u horario.
        $newDate  = trim($body['appointment_date'] ?? '');
        $newStart = trim($body['start_time'] ?? '');
        $newEnd   = trim($body['end_time'] ?? '');
        if ($newDate !== '' && $newStart !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate) || !preg_match('/^\d{2}:\d{2}/', $newStart)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Formato de fecha/hora inválido']); exit;
            }
            if ($newEnd === '') $newEnd = date('H:i', strtotime($newStart) + 3600);
            pdoQuery($pdo,
                "UPDATE appointments SET appointment_date = ?, start_time = ?, end_time = ?, rescheduled_at = NOW() WHERE id = ?",
                [$newDate, $newStart, $newEnd, $id]
            );
        }

        if ($therapist_id > 0) {
            $therapist = pdoQuery($pdo, "SELECT id, is_active FROM users WHERE id = ? AND role = 'therapist' LIMIT 1", [$therapist_id])->fetch();
            if (!$therapist || (int)($therapist['is_active'] ?? 0) !== 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El fisioterapeuta seleccionado ya no esta disponible']); exit;
            }
            pdoQuery($pdo, "UPDATE appointments SET therapist_id = ? WHERE id = ?", [$therapist_id, $id]);
        }

        if ($status) {
            // Obtener estado anterior para detectar transiciones de asistencia.
            $apt = pdoQuery($pdo, "SELECT patient_id, status, appointment_date FROM appointments WHERE id = ?", [$id])->fetch();
            if (!$apt) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Cita no encontrada']); exit;
            }

            if ($status === 'completed' && ($apt['status'] ?? '') !== 'completed') {
                pdoQuery($pdo, "UPDATE appointments SET status = ?, notes = ?, checked_in_at = NOW(), checked_in_by = ? WHERE id = ?", [$status, $notes, $userId, $id]);
            } elseif ($status !== 'completed' && ($apt['status'] ?? '') === 'completed') {
                pdoQuery($pdo, "UPDATE appointments SET status = ?, notes = ?, checked_in_at = NULL, checked_in_by = NULL WHERE id = ?", [$status, $notes, $id]);
            } else {
                pdoQuery($pdo, "UPDATE appointments SET status = ?, notes = ? WHERE id = ?", [$status, $notes, $id]);
            }

            if ($status === 'completed' && ($apt['status'] ?? '') !== 'completed') {
                syncPlanByAttendanceStatus($pdo, $id, (int)$apt['patient_id'], true, $apt['appointment_date'] ?? null);
            } elseif ($status !== 'completed' && ($apt['status'] ?? '') === 'completed') {
                syncPlanByAttendanceStatus($pdo, $id, (int)$apt['patient_id'], false, $apt['appointment_date'] ?? null);
            }
        }

        appLog($pdo, 'appointment.update', 'appointment', (string)$id, [
            'status' => $status ?: null,
            'notes' => $notes !== '' ? $notes : null,
            'therapist_id' => $therapist_id > 0 ? $therapist_id : null
        ]);
        echo json_encode(['success' => true, 'message' => 'Cita actualizada']);
        break;

    case 'DELETE':
        if (!hasPermission($pdo, $userId, $userRole, 'delete_apt')) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Sin permiso para eliminar citas']); exit;
        }
        $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'ID requerido']); exit; }
        $apt = pdoQuery($pdo, "SELECT patient_id, status, appointment_date FROM appointments WHERE id = ?", [$id])->fetch();
        if (!$apt) { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Cita no encontrada']); exit; }
        if ($apt && ($apt['status'] ?? '') === 'completed') {
            syncPlanByAttendanceStatus($pdo, $id, (int)$apt['patient_id'], false, $apt['appointment_date'] ?? null);
        }
        pdoQuery($pdo, "DELETE FROM appointments WHERE id = ?", [$id]);
        appLog($pdo, 'appointment.delete', 'appointment', (string)$id);
        echo json_encode(['success' => true, 'message' => 'Cita eliminada']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
}
