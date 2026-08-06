<?php
session_start();
header('Content-Type: application/json');

require_once '../db.php';
require_once '../includes/csrf.php';

verifyCsrfRequest();
ensureFixedExpenseSchema($pdo);
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    try {
        $rows = pdoQuery(
            $pdo,
            "SELECT * FROM fixed_expenses ORDER BY is_active DESC, amount DESC, name ASC"
        )->fetchAll();

        echo json_encode(['success' => true, 'items' => $rows]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudieron cargar los gastos fijos']);
    }
    exit;
}

if ($method === 'POST') {
    $name = app_text(trim($body['name'] ?? ''));
    $category = app_text(trim($body['category'] ?? 'fijo'));
    $amount = round((float)($body['amount'] ?? 0), 2);
    $dueDay = (int)($body['due_day'] ?? 0);
    $notes = app_text(trim($body['notes'] ?? ''));
    $isActive = !empty($body['is_active']) ? 1 : 0;

    if ($name === '' || $amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nombre y monto son obligatorios']);
        exit;
    }

    if ($dueDay < 1 || $dueDay > 31) {
        $dueDay = null;
    }

    try {
        pdoQuery(
            $pdo,
            "INSERT INTO fixed_expenses (name, category, amount, due_day, is_active, notes)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$name, $category, $amount, $dueDay, $isActive, $notes ?: null]
        );
        $newId = (int)$pdo->lastInsertId();
        appLog($pdo, 'fixed_expense.create', 'fixed_expense', (string)$newId, [
            'name' => $name,
            'category' => $category,
            'amount' => $amount,
            'due_day' => $dueDay,
            'is_active' => $isActive,
        ]);

        echo json_encode(['success' => true, 'message' => 'Gasto fijo registrado']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No se pudo registrar el gasto fijo']);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID inv&aacute;lido']);
        exit;
    }

    try {
        pdoQuery($pdo, "DELETE FROM fixed_expenses WHERE id = ?", [$id]);
        appLog($pdo, 'fixed_expense.delete', 'fixed_expense', (string)$id);
        echo json_encode(['success' => true, 'message' => 'Gasto fijo eliminado']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No se pudo eliminar el gasto fijo']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'M&eacute;todo no permitido']);
