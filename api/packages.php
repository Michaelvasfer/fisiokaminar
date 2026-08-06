<?php
// api/packages.php — CRUD de paquetes de sesiones
session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once '../includes/csrf.php';
verifyCsrfRequest();
ensurePackagesSchema($pdo);
ensurePackagePaymentLinkSchema($pdo);
ensureReferralSchema($pdo);
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']); exit;
}

$userRole = $_SESSION['role'];
$method   = $_SERVER['REQUEST_METHOD'];
$body     = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {

    case 'GET':
        if (isset($_GET['catalog'])) {
            $templates = pdoQuery($pdo, "SELECT id, name, total_sessions, total_amount, is_active FROM package_templates WHERE is_active = 1 ORDER BY total_sessions ASC, total_amount ASC, name ASC")->fetchAll();
            echo json_encode(['success' => true, 'templates' => $templates]);
            break;
        }
        $patientId = (int)($_GET['patient_id'] ?? 0);
        if (!$patientId) { echo json_encode(['success' => true, 'packages' => []]); break; }
        syncPatientPackagePayments($pdo, $patientId);
        $stmt = pdoQuery($pdo, "SELECT * FROM packages WHERE patient_id = ? ORDER BY purchase_date DESC", [$patientId]);
        echo json_encode(['success' => true, 'packages' => $stmt->fetchAll()]);
        break;

    case 'POST':
        if (!in_array($userRole, ['admin', 'receptionist'])) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Sin permiso']); exit;
        }
        if (($body['action'] ?? '') === 'create_template') {
            if ($userRole !== 'admin') {
                http_response_code(403); echo json_encode(['success' => false, 'error' => 'Solo el admin puede crear paquetes base']); exit;
            }

            $name = trim($body['name'] ?? '');
            $totalSessions = (int)($body['total_sessions'] ?? 0);
            $totalAmount = round((float)($body['total_amount'] ?? 0), 2);

            if ($name === '' || $totalSessions <= 0 || $totalAmount <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Completa nombre, sesiones y monto total']); exit;
            }

            pdoQuery(
                $pdo,
                "INSERT INTO package_templates (name, total_sessions, total_amount, is_active) VALUES (?, ?, ?, 1)",
                [$name, $totalSessions, $totalAmount]
            );

            $newTemplateId = (int)$pdo->lastInsertId();
            appLog($pdo, 'package_template.create', 'package_template', (string)$newTemplateId, [
                'name' => $name,
                'total_sessions' => $totalSessions,
                'total_amount' => $totalAmount
            ]);
            echo json_encode(['success' => true, 'message' => 'Paquete base creado', 'id' => $newTemplateId]);
            break;
        }

        $patientId    = (int)($body['patient_id'] ?? 0);
        $templateId   = (int)($body['template_id'] ?? 0);
        $name         = trim($body['name'] ?? '');
        $totalSessions= (int)($body['total_sessions'] ?? 0);
        $purchaseDate = $body['purchase_date'] ?? date('Y-m-d');
        $totalAmount  = round((float)($body['total_amount'] ?? 0), 2);
        $amountPaid   = round((float)($body['amount_paid'] ?? 0), 2);
        $paymentMethod= trim($body['payment_method'] ?? 'Efectivo');

        if ($templateId > 0) {
            $template = pdoQuery($pdo, "SELECT * FROM package_templates WHERE id = ? AND is_active = 1 LIMIT 1", [$templateId])->fetch();
            if (!$template) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El paquete base ya no está disponible']); exit;
            }
            $name = trim((string)$template['name']);
            $totalSessions = (int)$template['total_sessions'];
            $totalAmount = round((float)$template['total_amount'], 2);
        }

        if (!$patientId || !$name || $totalSessions <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']); exit;
        }
        if ($totalAmount < 0 || $amountPaid < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Los montos no pueden ser negativos']); exit;
        }
        if ($amountPaid > $totalAmount && $totalAmount > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El abono no puede ser mayor al monto total del paquete']); exit;
        }

        // Regla de negocio: solo un paquete activo por paciente.
        // Se considera activo si aun tiene sesiones por usar o saldo pendiente.
        $openPackage = pdoQuery(
            $pdo,
            "SELECT id, name
             FROM packages
             WHERE patient_id = ?
               AND (
                    COALESCE(unused_sessions, 0) > 0
                    OR (COALESCE(total_amount, 0) - COALESCE(amount_paid, 0)) > 0.009
               )
             ORDER BY purchase_date DESC, id DESC
             LIMIT 1",
            [$patientId]
        )->fetch();
        if ($openPackage) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'El paciente ya tiene un paquete activo. Debes completarlo antes de asignar otro.'
            ]);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $rewardResult = null;
            $transactionTimestamp = app_now();

            pdoQuery($pdo,
                "INSERT INTO packages (patient_id, template_id, name, total_sessions, unused_sessions, total_amount, amount_paid, purchase_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$patientId, $templateId ?: null, $name, $totalSessions, $totalSessions, $totalAmount, $amountPaid, $purchaseDate]
            );
            $packageId = (int)$pdo->lastInsertId();

            if ($amountPaid > 0) {
                pdoQuery($pdo,
                    "INSERT INTO transactions (patient_id, type, amount, transaction_date, description, payment_method)
                     VALUES (?, 'payment_received', ?, ?, ?, ?)",
                    [$patientId, $amountPaid, $transactionTimestamp, 'Abono inicial paquete: ' . $name, $paymentMethod]
                );
                $initialPaymentId = (int)$pdo->lastInsertId();
                if ($initialPaymentId > 0) {
                    pdoQuery(
                        $pdo,
                        "INSERT INTO package_payment_links (transaction_id, package_id, applied_amount, applied_by)
                         VALUES (?, ?, ?, ?)",
                        [$initialPaymentId, $packageId, $amountPaid, $_SESSION['user_id'] ?? null]
                    );
                    $rewardResult = createReferralRewardFromPayment($pdo, $patientId, $initialPaymentId, $amountPaid);
                }
            }

            syncPatientPackagePayments($pdo, $patientId);
            syncReferralRewardsForPatient($pdo, $patientId);

            $pdo->commit();
            appLog($pdo, 'package.create', 'package', (string)$packageId, [
                'patient_id' => $patientId,
                'template_id' => $templateId ?: null,
                'name' => $name,
                'total_sessions' => $totalSessions,
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
                'reward_generated' => $rewardResult ? (float)$rewardResult['generated_amount'] : 0.0,
            ]);
            echo json_encode([
                'success' => true,
                'message' => 'Paquete creado',
                'id' => $packageId,
                'reward' => $rewardResult
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'PUT':
        if (!in_array($userRole, ['admin', 'receptionist'])) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Sin permiso']); exit;
        }
        if (($body['action'] ?? '') === 'change_template') {
            if ($userRole !== 'admin') {
                http_response_code(403); echo json_encode(['success' => false, 'error' => 'Solo el admin puede cambiar el tipo de paquete']); exit;
            }

            $id = (int)($body['id'] ?? 0);
            $templateId = (int)($body['template_id'] ?? 0);
            if (!$id || !$templateId) {
                http_response_code(400); echo json_encode(['success' => false, 'error' => 'ID de paquete y template requeridos']); exit;
            }

            $pkg = pdoQuery($pdo, "
                SELECT id, patient_id, name, total_sessions, unused_sessions, total_amount, amount_paid
                FROM packages
                WHERE id = ?
                LIMIT 1
            ", [$id])->fetch();
            if (!$pkg) {
                http_response_code(404); echo json_encode(['success' => false, 'error' => 'Paquete no encontrado']); exit;
            }

            $tpl = pdoQuery($pdo, "
                SELECT id, name, total_sessions, total_amount
                FROM package_templates
                WHERE id = ? AND is_active = 1
                LIMIT 1
            ", [$templateId])->fetch();
            if (!$tpl) {
                http_response_code(400); echo json_encode(['success' => false, 'error' => 'El paquete base seleccionado no esta disponible']); exit;
            }

            $usedSessions = max(0, (int)$pkg['total_sessions'] - (int)$pkg['unused_sessions']);
            $newTotalSessions = (int)$tpl['total_sessions'];
            $newTotalAmount = round((float)$tpl['total_amount'], 2);
            $amountPaid = round((float)$pkg['amount_paid'], 2);

            if ($usedSessions > $newTotalSessions) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'No se puede cambiar: el paciente ya uso mas sesiones de las que tiene el nuevo paquete'
                ]);
                exit;
            }
            if ($amountPaid > $newTotalAmount) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'No se puede cambiar: el abono actual es mayor al monto total del nuevo paquete'
                ]);
                exit;
            }

            $newUnusedSessions = max(0, $newTotalSessions - $usedSessions);
            pdoQuery($pdo, "
                UPDATE packages
                SET template_id = ?, name = ?, total_sessions = ?, unused_sessions = ?, total_amount = ?
                WHERE id = ?
            ", [
                (int)$tpl['id'],
                $tpl['name'],
                $newTotalSessions,
                $newUnusedSessions,
                $newTotalAmount,
                $id
            ]);

            appLog($pdo, 'package.change_template', 'package', (string)$id, [
                'old_name' => $pkg['name'],
                'new_name' => $tpl['name'],
                'old_total_sessions' => (int)$pkg['total_sessions'],
                'new_total_sessions' => $newTotalSessions,
                'used_sessions' => $usedSessions,
                'old_total_amount' => (float)$pkg['total_amount'],
                'new_total_amount' => $newTotalAmount,
            ]);

            echo json_encode(['success' => true, 'message' => 'Paquete cambiado correctamente']);
            break;
        }

        $id      = (int)($body['id'] ?? 0);
        $unused  = (int)($body['unused_sessions'] ?? 0);
        $totalAmount = isset($body['total_amount']) ? round((float)$body['total_amount'], 2) : null;
        $amountPaid  = isset($body['amount_paid']) ? round((float)$body['amount_paid'], 2) : null;
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'ID requerido']); exit; }
        $fields = ["unused_sessions = ?"];
        $params = [$unused];
        if ($totalAmount !== null) {
            $fields[] = "total_amount = ?";
            $params[] = $totalAmount;
        }
        if ($amountPaid !== null) {
            $fields[] = "amount_paid = ?";
            $params[] = $amountPaid;
        }
        $params[] = $id;
        pdoQuery($pdo, "UPDATE packages SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        appLog($pdo, 'package.update', 'package', (string)$id, [
            'unused_sessions' => $unused,
            'total_amount' => $totalAmount,
            'amount_paid' => $amountPaid,
        ]);
        echo json_encode(['success' => true, 'message' => 'Paquete actualizado']);
        break;

    case 'DELETE':
        if ($userRole !== 'admin') {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Solo el admin puede eliminar paquetes']); exit;
        }
        $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'ID requerido']); exit; }
        pdoQuery($pdo, "DELETE FROM packages WHERE id = ?", [$id]);
        appLog($pdo, 'package.delete', 'package', (string)$id);
        echo json_encode(['success' => true, 'message' => 'Paquete eliminado']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
