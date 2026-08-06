<?php
session_start();
header('Content-Type: application/json');

require_once '../db.php';
require_once '../includes/csrf.php';

verifyCsrfRequest();

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

if (!function_exists('cleanupTableExists')) {
    function cleanupTableExists($pdo, $tableName) {
        return (bool) pdoQuery(
            $pdo,
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$tableName]
        )->fetchColumn();
    }
}

$tablesToClean = [
    'patient_sessions',
    'objectives',
    'session_notes',
    'patient_photos',
    'exercises',
    'appointments',
    'transactions',
    'packages',
    'treatment_plans',
    'clinical_histories',
];

try {
    $pdo->beginTransaction();
    $summary = [];

    foreach ($tablesToClean as $table) {
        if (!cleanupTableExists($pdo, $table)) {
            continue;
        }

        $count = (int) pdoQuery($pdo, "SELECT COUNT(*) FROM `$table`")->fetchColumn();
        pdoQuery($pdo, "DELETE FROM `$table`");
        $summary[$table] = $count;

        try {
            $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
        } catch (Exception $e) {
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Datos operativos eliminados',
        'summary' => $summary
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
