<?php
// api/change_password.php - Cambio y reseteo de contrasena
require_once '../db.php';
require_once '../includes/csrf.php';
ensureAuditSchema($pdo);

session_start();
header('Content-Type: application/json');
verifyCsrfRequest();

try {
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('must_change_password', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (Exception $e) {
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$targetUserId = (int)($data['user_id'] ?? $userId);
$resetToDni = !empty($data['reset_to_dni']);
$newPassword = trim($data['new_password'] ?? '');

if (!$resetToDni && (!$newPassword || strlen($newPassword) < 6)) {
    echo json_encode(['success' => false, 'error' => 'La contrasena debe tener al menos 6 caracteres']);
    exit;
}

if ($targetUserId !== $userId) {
    $targetUser = pdoQuery($pdo, "SELECT id, role, dni FROM users WHERE id = ?", [$targetUserId])->fetch();
    if (!$targetUser) {
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }

    if ($resetToDni) {
        if (!in_array($userRole, ['admin', 'receptionist'], true)) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para resetear contrasenas']);
            exit;
        }
        if (($targetUser['role'] ?? '') !== 'patient') {
            echo json_encode(['success' => false, 'error' => 'Solo se puede resetear al DNI en cuentas de pacientes']);
            exit;
        }

        $dni = trim((string)($targetUser['dni'] ?? ''));
        if ($dni === '') {
            echo json_encode(['success' => false, 'error' => 'El paciente no tiene DNI registrado']);
            exit;
        }

        $hash = password_hash($dni, PASSWORD_BCRYPT);
        pdoQuery($pdo, "UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?", [$hash, $targetUserId]);
        appLog($pdo, 'user.password.reset_to_dni', 'user', (string)$targetUserId);
        echo json_encode(['success' => true, 'message' => 'Contrasena reseteada al DNI del paciente']);
        exit;
    }

    if ($userRole !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Sin permiso para cambiar contrasenas de otros usuarios']);
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    pdoQuery($pdo, "UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?", [$hash, $targetUserId]);
    appLog($pdo, 'user.password.admin_change', 'user', (string)$targetUserId);
    echo json_encode(['success' => true, 'message' => 'Contrasena actualizada']);
    exit;
}

$currentPassword = trim($data['current_password'] ?? '');
if (!$currentPassword) {
    echo json_encode(['success' => false, 'error' => 'Ingresa tu contrasena actual']);
    exit;
}

$row = pdoQuery($pdo, "SELECT password, dni, must_change_password FROM users WHERE id = ?", [$userId])->fetch();
$validCurrentPassword = false;

if ($row && password_verify($currentPassword, $row['password'])) {
    $validCurrentPassword = true;
} elseif (
    $row &&
    (int)($row['must_change_password'] ?? 0) === 1 &&
    !empty($row['dni']) &&
    $currentPassword === (string)$row['dni']
) {
    $validCurrentPassword = true;
}

if (!$validCurrentPassword) {
    echo json_encode(['success' => false, 'error' => 'Contrasena actual incorrecta']);
    exit;
}

$hash = password_hash($newPassword, PASSWORD_BCRYPT);
pdoQuery($pdo, "UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?", [$hash, $userId]);
$_SESSION['must_change_password'] = false;
appLog($pdo, 'user.password.self_change', 'user', (string)$userId);

echo json_encode(['success' => true, 'message' => 'Contrasena actualizada exitosamente']);
