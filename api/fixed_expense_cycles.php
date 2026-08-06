<?php
session_start();
header('Content-Type: application/json');

require_once '../db.php';
require_once '../includes/csrf.php';

verifyCsrfRequest();
ensureExpenseSchema($pdo);
ensureFixedExpenseSchema($pdo);
ensureFixedExpenseCycleSchema($pdo);
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

$action = trim((string)($body['action'] ?? ''));
$fixedExpenseId = (int)($body['fixed_expense_id'] ?? 0);
$cycleMonth = trim((string)($body['cycle_month'] ?? ''));
$notes = app_text(trim((string)($body['notes'] ?? '')));

if ($fixedExpenseId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $cycleMonth)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$fixedExpense = pdoQuery($pdo, "SELECT * FROM fixed_expenses WHERE id = ?", [$fixedExpenseId])->fetch();
if (!$fixedExpense) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Gasto fijo no encontrado']);
    exit;
}

$peTz = new DateTimeZone('America/Lima');
$nowPe = new DateTimeImmutable('now', $peTz);
$nowDateTime = $nowPe->format('Y-m-d H:i:s');
$monthStart = new DateTimeImmutable($cycleMonth . '-01', $peTz);
$dueDay = (int)($fixedExpense['due_day'] ?? 0);
$dueDay = $dueDay > 0 ? min($dueDay, (int)$monthStart->format('t')) : null;
$plannedDueDate = $dueDay ? $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), $dueDay)->format('Y-m-d') : null;

$existingCycle = pdoQuery(
    $pdo,
    "SELECT * FROM fixed_expense_cycles WHERE fixed_expense_id = ? AND cycle_month = ? LIMIT 1",
    [$fixedExpenseId, $cycleMonth]
)->fetch();

try {
    if ($action === 'mark_paid') {
        if ($existingCycle && ($existingCycle['status'] ?? '') === 'paid') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Este compromiso ya fue pagado']);
            exit;
        }

        $paymentMethod = app_text(trim((string)($body['payment_method'] ?? 'Efectivo')));
        if ($paymentMethod === '') {
            $paymentMethod = 'Efectivo';
        }

        $description = 'Pago fijo: ' . app_text($fixedExpense['name']) . ' (' . $cycleMonth . ')';
        pdoQuery(
            $pdo,
            "INSERT INTO expenses (category, amount, expense_date, description, payment_method, vendor, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                app_text($fixedExpense['category'] ?? 'otros'),
                round((float)$fixedExpense['amount'], 2),
                $nowDateTime,
                $description,
                $paymentMethod,
                app_text($fixedExpense['name']),
                $userId
            ]
        );
        $expenseId = (int)$pdo->lastInsertId();

        if ($existingCycle) {
            pdoQuery(
                $pdo,
                "UPDATE fixed_expense_cycles
                 SET status = 'paid',
                     planned_due_date = ?,
                     paid_at = ?,
                     expense_id = ?,
                     payment_method = ?,
                     notes = ?,
                     updated_by = ?
                 WHERE id = ?",
                [$plannedDueDate, $nowDateTime, $expenseId, $paymentMethod, $notes !== '' ? $notes : null, $userId, (int)$existingCycle['id']]
            );
            $cycleId = (int)$existingCycle['id'];
        } else {
            pdoQuery(
                $pdo,
                "INSERT INTO fixed_expense_cycles (fixed_expense_id, cycle_month, status, planned_due_date, paid_at, expense_id, payment_method, notes, created_by, updated_by)
                 VALUES (?, ?, 'paid', ?, ?, ?, ?, ?, ?, ?)",
                [$fixedExpenseId, $cycleMonth, $plannedDueDate, $nowDateTime, $expenseId, $paymentMethod, $notes !== '' ? $notes : null, $userId, $userId]
            );
            $cycleId = (int)$pdo->lastInsertId();
        }

        appLog($pdo, 'fixed_expense_cycle.pay', 'fixed_expense_cycle', (string)$cycleId, [
            'fixed_expense_id' => $fixedExpenseId,
            'cycle_month' => $cycleMonth,
            'expense_id' => $expenseId,
        ]);

        echo json_encode(['success' => true, 'message' => 'Pago fijo registrado']);
        exit;
    }

    if ($action === 'mark_deferred' || $action === 'mark_pending') {
        $status = $action === 'mark_deferred' ? 'deferred' : 'pending';

        if ($existingCycle) {
            if (($existingCycle['status'] ?? '') === 'paid' && $status !== 'paid') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Este compromiso ya tiene un pago registrado']);
                exit;
            }

            pdoQuery(
                $pdo,
                "UPDATE fixed_expense_cycles
                 SET status = ?,
                     planned_due_date = ?,
                     notes = ?,
                     updated_by = ?
                 WHERE id = ?",
                [$status, $plannedDueDate, $notes !== '' ? $notes : null, $userId, (int)$existingCycle['id']]
            );
            $cycleId = (int)$existingCycle['id'];
        } else {
            pdoQuery(
                $pdo,
                "INSERT INTO fixed_expense_cycles (fixed_expense_id, cycle_month, status, planned_due_date, notes, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$fixedExpenseId, $cycleMonth, $status, $plannedDueDate, $notes !== '' ? $notes : null, $userId, $userId]
            );
            $cycleId = (int)$pdo->lastInsertId();
        }

        appLog($pdo, 'fixed_expense_cycle.status', 'fixed_expense_cycle', (string)$cycleId, [
            'fixed_expense_id' => $fixedExpenseId,
            'cycle_month' => $cycleMonth,
            'status' => $status,
        ]);

        echo json_encode([
            'success' => true,
            'message' => $status === 'deferred' ? 'Compromiso marcado como pospuesto' : 'Compromiso marcado como pendiente'
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Accion no valida']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se pudo actualizar el compromiso']);
}
