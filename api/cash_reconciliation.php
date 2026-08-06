<?php
session_start();
header('Content-Type: application/json');

require_once '../db.php';
require_once '../includes/csrf.php';

verifyCsrfRequest();
ensureCashReconciliationSchema($pdo);
ensureExpenseSchema($pdo);
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    try {
        $summary = getCashLedgerSummary($pdo);
        $rows = pdoQuery(
            $pdo,
            "SELECT cr.*, u.name AS reconciled_by_name
             FROM cash_reconciliations cr
             LEFT JOIN users u ON u.id = cr.reconciled_by
             ORDER BY cr.created_at DESC, cr.id DESC
             LIMIT 8"
        )->fetchAll();

        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'history' => $rows,
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo cargar la conciliacion de caja']);
    }
    exit;
}

if ($method === 'POST') {
    $countedCash = round((float)($body['counted_cash'] ?? 0), 2);
    $notes = app_text(trim((string)($body['notes'] ?? '')));

    if ($countedCash < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'La caja real no puede ser negativa']);
        exit;
    }

    try {
        $summary = getCashLedgerSummary($pdo);
        $previousSystemCash = round((float)($summary['system_cash'] ?? 0), 2);
        $adjustmentAmount = round($countedCash - $previousSystemCash, 2);

        pdoQuery(
            $pdo,
            "INSERT INTO cash_reconciliations (previous_system_cash, counted_cash, adjustment_amount, notes, reconciled_by)
             VALUES (?, ?, ?, ?, ?)",
            [$previousSystemCash, $countedCash, $adjustmentAmount, $notes !== '' ? $notes : null, $userId]
        );

        $newId = (int)$pdo->lastInsertId();
        appLog($pdo, 'cash.reconcile', 'cash_reconciliation', (string)$newId, [
            'previous_system_cash' => $previousSystemCash,
            'counted_cash' => $countedCash,
            'adjustment_amount' => $adjustmentAmount,
        ]);

        echo json_encode([
            'success' => true,
            'message' => abs($adjustmentAmount) < 0.01
                ? 'La caja ya estaba sincronizada'
                : 'Caja sincronizada correctamente',
            'adjustment_amount' => $adjustmentAmount,
            'summary' => getCashLedgerSummary($pdo),
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo sincronizar la caja']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
