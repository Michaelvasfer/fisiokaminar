<?php
// api/payments.php - CRUD de pagos/transacciones
session_start();
header('Content-Type: application/json');

require_once '../db.php';
require_once '../includes/auth_helper.php';
require_once '../includes/csrf.php';

verifyCsrfRequest();
ensurePackagesSchema($pdo);
ensureReferralSchema($pdo);
ensureAuditSchema($pdo);
ensurePackagePaymentLinkSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userRole = $_SESSION['role'];
$userId   = $_SESSION['user_id'];
$method   = $_SERVER['REQUEST_METHOD'];
$body     = json_decode(file_get_contents('php://input'), true) ?? [];

if (!function_exists('findPotentialDuplicatePayments')) {
    function findPotentialDuplicatePayments($pdo, $patientId, $amount, $paymentMethod, $description, $packageId = 0) {
        $sql = "
            SELECT
                t.id,
                t.amount,
                t.transaction_date,
                t.description,
                t.payment_method,
                COALESCE(ppl.package_id, 0) AS package_id
            FROM transactions t
            LEFT JOIN package_payment_links ppl ON ppl.transaction_id = t.id
            WHERE t.patient_id = ?
              AND t.type = 'payment_received'
              AND ROUND(t.amount, 2) = ?
              AND t.payment_method = ?
              AND t.description = ?
              AND DATE(t.transaction_date) = CURDATE()
        ";
        $params = [$patientId, round((float)$amount, 2), $paymentMethod, $description];

        if ($packageId > 0) {
            $sql .= " AND COALESCE(ppl.package_id, 0) = ?";
            $params[] = $packageId;
        }

        $sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT 3";

        return pdoQuery($pdo, $sql, $params)->fetchAll();
    }
}

switch ($method) {

    case 'GET':
        $patientId = (int)($_GET['patient_id'] ?? 0);
        if (!$patientId) {
            echo json_encode(['success' => true, 'transactions' => []]);
            break;
        }

        syncPatientPackagePayments($pdo, $patientId);

        $stmt = pdoQuery(
            $pdo,
            "SELECT t.*,
                    ppl.package_id AS linked_package_id,
                    ppl.applied_amount AS linked_amount
             FROM transactions t
             LEFT JOIN package_payment_links ppl ON ppl.transaction_id = t.id
             WHERE t.patient_id = ?
             ORDER BY t.transaction_date DESC, t.id DESC",
            [$patientId]
        );
        echo json_encode(['success' => true, 'transactions' => $stmt->fetchAll()]);
        break;

    case 'POST':
        if (!hasPermission($pdo, $userId, $userRole, 'add_payment')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sin permiso para registrar pagos']);
            exit;
        }

        $patientId = (int)($body['patient_id'] ?? 0);
        $amount = (float)($body['amount'] ?? 0);
        $serviceAmount = isset($body['service_amount']) && $body['service_amount'] !== ''
            ? (float)$body['service_amount']
            : $amount;
        $type = trim($body['type'] ?? 'payment_received');
        $description = app_text(trim($body['description'] ?? 'Pago recibido'));
        $paymentMethod = trim($body['payment_method'] ?? 'Efectivo');
        $packageId = (int)($body['package_id'] ?? 0);
        $useReferralCredit = !empty($body['use_referral_credit']);
        $confirmDuplicate = !empty($body['confirm_duplicate']);

        if ($patientId <= 0 || $serviceAmount <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Paciente y monto son obligatorios']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $transactionTimestamp = app_now();

            $creditApplied = 0.0;
            $cashAmount = round($amount, 2);

            if ($useReferralCredit) {
                $availableCredit = getAvailableReferralCreditBalance($pdo, $patientId);
                $creditApplied = round(min($availableCredit, $serviceAmount), 2);
                $cashAmount = round($serviceAmount - $creditApplied, 2);
            } elseif (isset($body['service_amount'])) {
                $cashAmount = round($serviceAmount, 2);
            }

            if ($cashAmount < 0) {
                throw new Exception('El monto final en caja no es valido');
            }

            if ($cashAmount <= 0 && $creditApplied <= 0) {
                throw new Exception('Ingresa un monto valido');
            }

            if ($packageId > 0) {
                $package = pdoQuery(
                    $pdo,
                    "SELECT id, patient_id, name, total_amount, amount_paid
                     FROM packages
                     WHERE id = ?",
                    [$packageId]
                )->fetch();

                if (!$package || (int)$package['patient_id'] !== $patientId) {
                    throw new Exception('Paquete no valido para este paciente');
                }

                $newAmountPaid = round((float)$package['amount_paid'] + $serviceAmount, 2);
                $packageTotalAmount = round((float)$package['total_amount'], 2);
                if ($packageTotalAmount > 0 && $newAmountPaid > $packageTotalAmount) {
                    throw new Exception('El abono excede el saldo pendiente del paquete');
                }

                if ($description === 'Pago recibido' || $description === 'Pago de sesion') {
                    $description = 'Abono a paquete: ' . $package['name'];
                }
            }

            if ($cashAmount > 0) {
                $potentialDuplicates = findPotentialDuplicatePayments(
                    $pdo,
                    $patientId,
                    $cashAmount,
                    $paymentMethod,
                    $description,
                    $packageId
                );

                if (!$confirmDuplicate && !empty($potentialDuplicates)) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    echo json_encode([
                        'success' => false,
                        'duplicate_warning' => true,
                        'error' => 'Ya existe un pago muy parecido registrado hoy para este paciente.',
                        'duplicates' => array_map(static function ($row) {
                            return [
                                'id' => (int)($row['id'] ?? 0),
                                'amount' => round((float)($row['amount'] ?? 0), 2),
                                'transaction_date' => (string)($row['transaction_date'] ?? ''),
                                'description' => (string)($row['description'] ?? ''),
                                'payment_method' => (string)($row['payment_method'] ?? ''),
                                'package_id' => (int)($row['package_id'] ?? 0),
                            ];
                        }, $potentialDuplicates),
                    ]);
                    exit;
                }
            }

            if ($packageId > 0) {
                pdoQuery($pdo, "UPDATE packages SET amount_paid = ? WHERE id = ?", [$newAmountPaid, $packageId]);
            }

            $paymentTransactionId = 0;
            if ($cashAmount > 0) {
                pdoQuery(
                    $pdo,
                    "INSERT INTO transactions (patient_id, type, amount, transaction_date, description, payment_method)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$patientId, $type, $cashAmount, $transactionTimestamp, $description, $paymentMethod]
                );
                $paymentTransactionId = (int)$pdo->lastInsertId();
            }

            if ($packageId > 0 && $paymentTransactionId > 0) {
                pdoQuery(
                    $pdo,
                    "INSERT INTO package_payment_links (transaction_id, package_id, applied_amount, applied_by)
                     VALUES (?, ?, ?, ?)",
                    [$paymentTransactionId, $packageId, $serviceAmount, $userId]
                );
                syncPatientPackagePayments($pdo, $patientId);
            }

            if ($creditApplied > 0) {
                $creditDescription = 'Saldo por referidos aplicado';
                if ($description !== '') {
                    $creditDescription .= ': ' . $description;
                }

                pdoQuery(
                    $pdo,
                    "INSERT INTO transactions (patient_id, type, amount, transaction_date, description, payment_method)
                     VALUES (?, 'referral_credit_applied', ?, ?, ?, 'Saldo')",
                    [$patientId, -1 * $creditApplied, $transactionTimestamp, $creditDescription]
                );

                applyReferralCreditBalance($pdo, $patientId, $creditApplied, $paymentTransactionId ?: null);
            }

            $rewardResult = null;
            if ($cashAmount > 0 && $paymentTransactionId > 0) {
                $rewardResult = createReferralRewardFromPayment($pdo, $patientId, $paymentTransactionId, $cashAmount);
            }
            syncReferralRewardsForPatient($pdo, $patientId);

            $pdo->commit();
            appLog($pdo, 'payment.create', 'transaction', (string)$paymentTransactionId, [
                'patient_id' => $patientId,
                'service_amount' => $serviceAmount,
                'cash_amount' => $cashAmount,
                'credit_applied' => $creditApplied,
                'package_id' => $packageId > 0 ? $packageId : null,
                'payment_method' => $paymentMethod,
                'confirmed_duplicate' => $confirmDuplicate ? 1 : 0,
            ]);
            echo json_encode([
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'id' => $paymentTransactionId,
                'cash_amount' => $cashAmount,
                'credit_applied' => $creditApplied,
                'reward' => $rewardResult
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'PUT':
        if (!hasPermission($pdo, $userId, $userRole, 'edit_payment')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sin permiso para ajustar pagos']);
            exit;
        }

        if (($body['action'] ?? '') !== 'assign_existing_to_package') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Accion no valida']);
            exit;
        }

            $transactionId = (int)($body['transaction_id'] ?? 0);
        $packageId = (int)($body['package_id'] ?? 0);
        $applyAmountInput = isset($body['apply_amount']) ? round((float)$body['apply_amount'], 2) : null;

        if ($transactionId <= 0 || $packageId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Transaccion y paquete son obligatorios']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $transaction = pdoQuery(
                $pdo,
                "SELECT id, patient_id, amount
                 FROM transactions
                 WHERE id = ?
                 LIMIT 1",
                [$transactionId]
            )->fetch();
            if (!$transaction) {
                throw new Exception('La transaccion no existe');
            }

            $txnAmount = round((float)$transaction['amount'], 2);
            if ($txnAmount <= 0) {
                throw new Exception('Solo se pueden asignar pagos positivos');
            }
            $applyAmount = $applyAmountInput !== null ? $applyAmountInput : $txnAmount;
            if ($applyAmount <= 0) {
                throw new Exception('El monto a aplicar debe ser mayor a cero');
            }
            if ($applyAmount > $txnAmount) {
                throw new Exception('El monto a aplicar no puede ser mayor al monto del pago');
            }

            $alreadyLinked = pdoQuery(
                $pdo,
                "SELECT id FROM package_payment_links WHERE transaction_id = ? LIMIT 1",
                [$transactionId]
            )->fetch();
            if ($alreadyLinked) {
                throw new Exception('Este pago ya fue asignado a un paquete');
            }

            $package = pdoQuery(
                $pdo,
                "SELECT id, patient_id, name, total_amount, amount_paid
                 FROM packages
                 WHERE id = ?
                 LIMIT 1",
                [$packageId]
            )->fetch();
            if (!$package) {
                throw new Exception('El paquete no existe');
            }

            if ((int)$package['patient_id'] !== (int)$transaction['patient_id']) {
                throw new Exception('El pago y el paquete no pertenecen al mismo paciente');
            }

            $totalAmount = round((float)$package['total_amount'], 2);
            $amountPaid = round((float)$package['amount_paid'], 2);
            $pending = max(0, round($totalAmount - $amountPaid, 2));
            if ($pending <= 0) {
                throw new Exception('Este paquete ya no tiene saldo pendiente');
            }

            if ($applyAmount > $pending) {
                throw new Exception('El pago excede el saldo pendiente del paquete');
            }

            $newAmountPaid = round($amountPaid + $applyAmount, 2);
            pdoQuery($pdo, "UPDATE packages SET amount_paid = ? WHERE id = ?", [$newAmountPaid, $packageId]);
            pdoQuery(
                $pdo,
                "INSERT INTO package_payment_links (transaction_id, package_id, applied_amount, applied_by)
                 VALUES (?, ?, ?, ?)",
                [$transactionId, $packageId, $applyAmount, $userId]
            );
            syncPatientPackagePayments($pdo, (int)$transaction['patient_id']);

            $existingDesc = trim((string)pdoQuery($pdo, "SELECT description FROM transactions WHERE id = ?", [$transactionId])->fetchColumn());
            $tag = 'Abono a paquete: ' . $package['name'];
            $newDesc = $existingDesc;
            if ($newDesc === '') {
                $newDesc = $tag;
            } elseif (stripos($newDesc, $tag) === false) {
                $newDesc .= ' | ' . $tag;
            }
            pdoQuery($pdo, "UPDATE transactions SET description = ? WHERE id = ?", [$newDesc, $transactionId]);

            $pdo->commit();
            appLog($pdo, 'payment.assign_to_package', 'transaction', (string)$transactionId, [
                'package_id' => $packageId,
                'applied_amount' => $applyAmount,
                'patient_id' => (int)$transaction['patient_id'],
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Pago asignado al paquete',
                'applied_amount' => $applyAmount,
                'package_id' => $packageId
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        if (!hasPermission($pdo, $userId, $userRole, 'delete_payment')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sin permiso para eliminar pagos']);
            exit;
        }

        $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            exit;
        }

        if ($userRole === 'receptionist') {
            $check = pdoQuery($pdo, "SELECT transaction_date FROM transactions WHERE id = ?", [$id])->fetch();
            if (!$check) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Transaccion no encontrada']);
                exit;
            }

            $txnDate = date('Y-m-d', strtotime($check['transaction_date']));
            if ($txnDate !== date('Y-m-d')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'La secretaria solo puede borrar pagos del dia actual']);
                exit;
            }
        }

        try {
            $pdo->beginTransaction();

            $transaction = pdoQuery(
                $pdo,
                "SELECT id, patient_id, amount, description
                 FROM transactions
                 WHERE id = ?
                 LIMIT 1",
                [$id]
            )->fetch();
            if (!$transaction) {
                throw new Exception('Transaccion no encontrada');
            }

            $link = pdoQuery(
                $pdo,
                "SELECT package_id, applied_amount
                 FROM package_payment_links
                 WHERE transaction_id = ?
                 LIMIT 1",
                [$id]
            )->fetch();

            if ($link) {
                $pkg = pdoQuery($pdo, "SELECT amount_paid FROM packages WHERE id = ? LIMIT 1", [(int)$link['package_id']])->fetch();
                if ($pkg) {
                    $newAmountPaid = max(0, round((float)$pkg['amount_paid'] - (float)$link['applied_amount'], 2));
                    pdoQuery($pdo, "UPDATE packages SET amount_paid = ? WHERE id = ?", [$newAmountPaid, (int)$link['package_id']]);
                }
                pdoQuery($pdo, "DELETE FROM package_payment_links WHERE transaction_id = ?", [$id]);
            }

            pdoQuery($pdo, "DELETE FROM referral_rewards WHERE payment_transaction_id = ?", [$id]);
            pdoQuery($pdo, "DELETE FROM transactions WHERE id = ?", [$id]);
            syncPatientPackagePayments($pdo, (int)$transaction['patient_id']);
            $pdo->commit();
            appLog($pdo, 'payment.delete', 'transaction', (string)$id, [
                'patient_id' => (int)$transaction['patient_id'],
                'amount' => round((float)$transaction['amount'], 2),
                'description' => (string)($transaction['description'] ?? '')
            ]);
            echo json_encode(['success' => true, 'message' => 'Transaccion eliminada']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage() ?: 'No se pudo eliminar la transaccion']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
        break;
}
