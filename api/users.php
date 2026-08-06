<?php
// api/users.php — CRUD de usuarios (solo admin)
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
    echo json_encode(['success' => false, 'error' => 'Solo el admin puede gestionar usuarios']); exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// Migraciones automáticas: Asegurar tablas y columnas
try {
    // Verificar si las columnas existen antes de intentar agregarlas
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('phone', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
    }
    if (!in_array('is_active', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    }
    if (!in_array('custom_permissions', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN custom_permissions TINYINT(1) DEFAULT 0");
    }
    if (!in_array('must_change_password', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!in_array('patient_code', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN patient_code VARCHAR(50) DEFAULT NULL");
    }

    try {
        $roleColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
        $roleType = strtolower((string)($roleColumn['Type'] ?? ''));
        if ($roleType !== '' && strpos($roleType, "'referrer'") === false) {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','receptionist','therapist','referrer','patient') NOT NULL DEFAULT 'patient'");
        }
    } catch (Exception $e) {
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        permission_key VARCHAR(50) NOT NULL,
        UNIQUE KEY idx_user_perm (user_id, permission_key),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(Exception $e) {
    // Opcional: registrar error si es necesario para depuración interna
}

switch ($method) {

    case 'GET':
        $role = $_GET['role'] ?? null;
        if ($role) {
            $stmt = pdoQuery($pdo, "SELECT id, name, dni, email, role, age, patient_code, is_active FROM users WHERE role = ? ORDER BY name ASC", [$role]);
        } else {
            $stmt = pdoQuery($pdo, "SELECT id, name, dni, email, role, age, patient_code, is_active FROM users ORDER BY role, name ASC");
        }
        echo json_encode(['success' => true, 'users' => $stmt->fetchAll()]);
        break;

    case 'POST':
        try {
        $allowedRoles = ['admin', 'receptionist', 'therapist', 'referrer', 'patient'];
        $role     = trim($body['role'] ?? 'patient');
        $name     = trim($body['name'] ?? '');
        $dni      = trim($body['dni'] ?? '');
        $dniValue = $dni !== '' ? $dni : null;
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? 'password';
        $age      = isset($body['age']) && $body['age'] !== '' ? (int)$body['age'] : null;
        $mustChangePassword = 0;

        if (!in_array($role, $allowedRoles, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Rol no valido']); exit;
        }

        if (!$name || !$email) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nombre y correo son obligatorios']); exit;
        }

        $check = pdoQuery($pdo, "SELECT id FROM users WHERE email = ?", [$email])->fetch();
        if ($check) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'El correo ya está registrado']); exit;
        }

        $code = $role === 'patient' ? '#PT-' . strtoupper(substr(md5(uniqid()), 0, 6)) : null;
        if ($role === 'patient' && !$dni) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El DNI es obligatorio para pacientes']); exit;
        }
        if ($role === 'patient' && ($password === 'password' || $password === '')) {
            $password = $dni;
            $mustChangePassword = 1;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);

        pdoQuery($pdo,
            "INSERT INTO users (role, name, dni, email, password, age, patient_code, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$role, $name, $dniValue, $email, $hash, $age, $code, $mustChangePassword]
        );
        $newId = (int)$pdo->lastInsertId();
        appLog($pdo, 'user.create', 'user', (string)$newId, [
            'role' => $role,
            'name' => $name,
            'email' => $email,
            'must_change_password' => $mustChangePassword
        ]);
        echo json_encode(['success' => true, 'message' => 'Usuario creado', 'id' => $newId]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'No se pudo crear el usuario: ' . $e->getMessage()]);
        }
        break;

    case 'PUT':
        $id       = (int)($body['id'] ?? 0);
        $name     = trim($body['name'] ?? '');
        $dni      = trim($body['dni'] ?? '');
        $dniValue = $dni !== '' ? $dni : null;
        $email    = trim($body['email'] ?? '');
        $age      = isset($body['age']) && $body['age'] !== '' ? (int)$body['age'] : null;
        $isActive = isset($body['is_active']) ? (int)$body['is_active'] : null;

        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'ID requerido']); exit; }

        if ($name && $email) {
            pdoQuery($pdo, "UPDATE users SET name = ?, dni = ?, email = ?, age = ? WHERE id = ?", [$name, $dniValue, $email, $age, $id]);
        }
        if ($isActive !== null) {
            pdoQuery($pdo, "UPDATE users SET is_active = ? WHERE id = ?", [$isActive, $id]);
        }
        if (!empty($body['password'])) {
            $hash = password_hash($body['password'], PASSWORD_DEFAULT);
            pdoQuery($pdo, "UPDATE users SET password = ? WHERE id = ?", [$hash, $id]);
            pdoQuery($pdo, "UPDATE users SET must_change_password = 1 WHERE id = ?", [$id]);
        }
        appLog($pdo, 'user.update', 'user', (string)$id, [
            'name' => $name ?: null,
            'email' => $email ?: null,
            'is_active' => $isActive,
            'password_reset' => !empty($body['password'])
        ]);
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado']);
        break;

    case 'DELETE':
        $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'ID requerido']); exit; }
        if ($id === (int)$_SESSION['user_id']) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'No puedes eliminar tu propia cuenta']); exit;
        }
        $user = pdoQuery($pdo, "SELECT id, role, name, is_active FROM users WHERE id = ? LIMIT 1", [$id])->fetch();
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']); exit;
        }

        $role = (string)($user['role'] ?? '');
        $shouldProtect = in_array($role, ['admin', 'receptionist', 'therapist', 'referrer'], true);
        $appointmentsCount = 0;
        $sessionsCount = 0;
        $notesCount = 0;

        if ($shouldProtect) {
            try {
                $appointmentsCount = (int)(pdoQuery($pdo, "SELECT COUNT(*) FROM appointments WHERE therapist_id = ?", [$id])->fetchColumn() ?: 0);
            } catch (Exception $e) {
            }
            try {
                $sessionsCount = (int)(pdoQuery($pdo, "SELECT COUNT(*) FROM patient_sessions WHERE therapist_id = ?", [$id])->fetchColumn() ?: 0);
            } catch (Exception $e) {
            }
            try {
                $notesCount = (int)(pdoQuery($pdo, "SELECT COUNT(*) FROM session_notes WHERE therapist_id = ?", [$id])->fetchColumn() ?: 0);
            } catch (Exception $e) {
            }
        }

        if ($shouldProtect && ($appointmentsCount > 0 || $sessionsCount > 0 || $notesCount > 0)) {
            pdoQuery($pdo, "UPDATE users SET is_active = 0 WHERE id = ?", [$id]);
            appLog($pdo, 'user.safe_deactivate', 'user', (string)$id, [
                'role' => $role,
                'appointments_count' => $appointmentsCount,
                'sessions_count' => $sessionsCount,
                'notes_count' => $notesCount,
            ]);
            echo json_encode([
                'success' => true,
                'protected' => true,
                'message' => 'El usuario tenia agenda o historial. Se desactivo para proteger la informacion en lugar de eliminarlo.'
            ]);
            exit;
        }

        pdoQuery($pdo, "DELETE FROM users WHERE id = ?", [$id]);
        appLog($pdo, 'user.delete', 'user', (string)$id, ['role' => $role]);
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
