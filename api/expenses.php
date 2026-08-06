<?php
session_start();
header('Content-Type: application/json');

require_once '../db.php';
require_once '../includes/csrf.php';

verifyCsrfRequest();
ensureExpenseSchema($pdo);
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userRole = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!in_array($userRole, ['admin', 'receptionist'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso para gestionar gastos']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    try {
        $rows = pdoQuery(
            $pdo,
            "SELECT e.*, u.name AS created_by_name
             FROM expenses e
             LEFT JOIN users u ON u.id = e.created_by
             ORDER BY e.expense_date DESC, e.id DESC"
        )->fetchAll();

        echo json_encode(['success' => true, 'expenses' => $rows]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudieron cargar los gastos']);
    }
    exit;
}

if ($method === 'POST') {
    $category = trim($body['category'] ?? 'otros');
    $amount = round((float)($body['amount'] ?? 0), 2);
    $expenseDate = trim($body['expense_date'] ?? '');
    $description = app_text(trim($body['description'] ?? ''));
    $paymentMethod = app_text(trim($body['payment_method'] ?? 'Efectivo'));
    $vendor = app_text(trim($body['vendor'] ?? ''));

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ingresa un monto válido']);
        exit;
    }

    if ($expenseDate === '') {
        $expenseDate = date('Y-m-d H:i:s');
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
        $expenseDate .= ' 00:00:00';
    }

    try {
        pdoQuery(
            $pdo,
            "INSERT INTO expenses (category, amount, expense_date, description, payment_method, vendor, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$category, $amount, $expenseDate, $description, $paymentMethod, $vendor ?: null, $userId]
        );
        $newId = (int)$pdo->lastInsertId();
        appLog($pdo, 'expense.create', 'expense', (string)$newId, [
            'category' => $category,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'vendor' => $vendor ?: null,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Gasto registrado',
            'id' => $newId
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No se pudo registrar el gasto']);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit;
    }

    try {
        pdoQuery($pdo, "DELETE FROM expenses WHERE id = ?", [$id]);
        appLog($pdo, 'expense.delete', 'expense', (string)$id);
        echo json_encode(['success' => true, 'message' => 'Gasto eliminado']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No se pudo eliminar el gasto']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
