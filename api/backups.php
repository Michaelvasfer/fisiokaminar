<?php
session_start();
header('Content-Type: application/json');

require_once '../db.php';
require_once '../includes/csrf.php';
require_once '../includes/backup_helper.php';

verifyCsrfRequest();
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

try {
    $backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
    $result = runDatabaseBackup($pdo, $backupDir, 'manual', (int)$_SESSION['user_id']);
    echo json_encode([
        'success' => true,
        'message' => 'Backup generado',
        'backup' => [
            'file_name' => $result['file_name'],
            'size_bytes' => $result['size_bytes'],
            'cleanup' => $result['cleanup'] ?? null,
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se pudo generar el backup: ' . $e->getMessage()]);
}
