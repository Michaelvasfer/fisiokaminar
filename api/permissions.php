<?php
// api/permissions.php - Gestión de permisos de usuario
session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once '../includes/csrf.php';
verifyCsrfRequest();
ensureAuditSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']); exit;
}

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Solo el administrador puede gestionar permisos']); exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// Migración de seguridad: Asegurar que la tabla y columna existen
try {
    // 1. Columna custom_permissions
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('custom_permissions', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN custom_permissions TINYINT(1) DEFAULT 0");
    }

    // 2. Tabla user_permissions
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        permission_key VARCHAR(50) NOT NULL,
        UNIQUE KEY idx_user_perm (user_id, permission_key),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

switch ($method) {
    case 'GET':
        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) { echo json_encode(['success' => false, 'error' => 'ID de usuario requerido']); exit; }
        
        $stmt = pdoQuery($pdo, "SELECT permission_key FROM user_permissions WHERE user_id = ?", [$userId]);
        $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $customFlag = pdoQuery($pdo, "SELECT custom_permissions FROM users WHERE id = ?", [$userId])->fetchColumn();
        
        echo json_encode(['success' => true, 'permissions' => $perms, 'custom_enabled' => (bool)$customFlag]);
        break;

    case 'POST':
        $userId = (int)($body['user_id'] ?? 0);
        $perms  = $body['permissions'] ?? []; // Array de strings
        $custom = isset($body['custom_enabled']) ? (int)$body['custom_enabled'] : 1;

        if (!$userId) { echo json_encode(['success' => false, 'error' => 'ID de usuario requerido']); exit; }

        try {
            $pdo->beginTransaction();
            
            // Actualizar flag en tabla users
            pdoQuery($pdo, "UPDATE users SET custom_permissions = ? WHERE id = ?", [$custom, $userId]);
            
            // Reemplazar permisos
            pdoQuery($pdo, "DELETE FROM user_permissions WHERE user_id = ?", [$userId]);
            foreach ($perms as $pk) {
                pdoQuery($pdo, "INSERT INTO user_permissions (user_id, permission_key) VALUES (?, ?)", [$userId, $pk]);
            }
            
            $pdo->commit();
            appLog($pdo, 'user.permissions.update', 'user', (string)$userId, [
                'custom_enabled' => $custom,
                'permissions_count' => count($perms),
            ]);
            echo json_encode(['success' => true, 'message' => 'Permisos actualizados']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
