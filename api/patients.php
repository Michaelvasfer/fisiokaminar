<?php
// api/patients.php — CRUD de pacientes
session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once '../includes/csrf.php';
verifyCsrfRequest();
ensureReferralSchema($pdo);
ensureAuditSchema($pdo);

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userRole = $_SESSION['role'];
$userId   = $_SESSION['user_id'];
$method   = $_SERVER['REQUEST_METHOD'];

// Leer body JSON para PUT/POST
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// --- MIGRACIÓN AUTOMÁTICA (Asegurar columnas DNI y PHONE) ---
try {
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('dni', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN dni VARCHAR(20) DEFAULT NULL AFTER name");
        $pdo->exec("CREATE UNIQUE INDEX idx_dni ON users(dni)");
    }
    if (!in_array('phone', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
    }
    if (!in_array('must_change_password', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (Exception $e) {
    // Si falla la migración automática, lo registramos pero continuamos (puede que ya existan)
}

try {
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('birth_date', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN birth_date DATE DEFAULT NULL AFTER age");
    }
} catch (Exception $e) {}

if (!function_exists('calculateAgeFromBirthDate')) {
    function calculateAgeFromBirthDate($birthDate) {
        if (empty($birthDate)) {
            return null;
        }

        try {
            $birth = new DateTime($birthDate);
            $today = new DateTime('today');
            return $birth->diff($today)->y;
        } catch (Exception $e) {
            return null;
        }
    }
}

switch ($method) {

    // GET: listar todos o uno
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = pdoQuery($pdo, "
                SELECT
                    u.id,
                    u.name,
                    u.dni,
                    u.email,
                    u.age,
                    u.birth_date,
                    u.phone,
                    u.patient_code,
                    r.referrer_kind,
                    r.referrer_user_id
                FROM users u
                LEFT JOIN referrals r ON r.referred_patient_id = u.id AND r.status = 'active'
                WHERE u.id = ? AND u.role = 'patient'
            ", [(int)$_GET['id']]);
            $patient = $stmt->fetch();
            if ($patient) {
                echo json_encode(['success' => true, 'patient' => $patient]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Paciente no encontrado']);
            }
        } else {
            $search = isset($_GET['q']) ? '%' . $_GET['q'] . '%' : '%';
            $stmt = pdoQuery($pdo, "
                SELECT
                    u.id,
                    u.name,
                    u.dni,
                    u.email,
                    u.age,
                    u.birth_date,
                    u.phone,
                    u.patient_code,
                    r.referrer_kind,
                    r.referrer_user_id
                FROM users u
                LEFT JOIN referrals r ON r.referred_patient_id = u.id AND r.status = 'active'
                WHERE u.role = 'patient' AND (u.name LIKE ? OR u.dni LIKE ?)
                ORDER BY u.name ASC
            ", [$search, $search]);
            echo json_encode(['success' => true, 'patients' => $stmt->fetchAll()]);
        }
        break;

    // POST: crear nuevo paciente — Admin y Secretaria
    case 'POST':
        try {
            if (!in_array($userRole, ['admin', 'receptionist'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                exit;
            }
            $name  = trim($body['name'] ?? '');
            $dni   = preg_replace('/\D+/', '', trim($body['dni'] ?? ''));
            $email = trim($body['email'] ?? '');
            $birthDate = trim($body['birth_date'] ?? '');
            $age   = isset($body['age']) && $body['age'] !== '' ? (int)$body['age'] : null;
            $phone = preg_replace('/\D+/', '', trim($body['phone'] ?? ''));
            $referrerKind = trim($body['referrer_kind'] ?? '');
            $referrerUserId = (int)($body['referrer_user_id'] ?? 0);

            if (empty($name) || empty($dni) || empty($phone)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nombre, DNI y Teléfono son obligatorios']);
                exit;
            }

            // Verificar DNI único
            if (!preg_match('/^\d{8}$/', $dni)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El DNI debe tener exactamente 8 digitos']);
                exit;
            }

            if (!preg_match('/^\d{9}$/', $phone)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El telefono debe tener exactamente 9 digitos']);
                exit;
            }

            if ($birthDate !== '') {
                $calculatedAge = calculateAgeFromBirthDate($birthDate);
                if ($calculatedAge === null || $calculatedAge < 0 || $calculatedAge > 120) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'La fecha de nacimiento no es valida']);
                    exit;
                }
                $age = $calculatedAge;
            }

            if ($dni) {
                $stmt = pdoQuery($pdo, "SELECT id FROM users WHERE dni = ?", [$dni]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'El DNI ya está registrado']);
                    exit;
                }
            }

            // Generar código de paciente
            $code = '#PT-' . strtoupper(substr(md5(uniqid()), 0, 6));
            // Contraseña temporal: el mismo DNI
            $hashedPwd = password_hash($dni, PASSWORD_DEFAULT);

            // Si no hay email, usamos un placeholder único
            $finalEmail = $email ?: $dni . '@kaminarfisio.com';

            $pdo->beginTransaction();

            pdoQuery($pdo,
                "INSERT INTO users (role, name, dni, email, password, age, birth_date, patient_code, phone, must_change_password) VALUES ('patient', ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [$name, $dni, $finalEmail, $hashedPwd, $age, $birthDate !== '' ? $birthDate : null, $code, $phone]
            );

            $newId = (int)$pdo->lastInsertId();
            if ($newId <= 0) {
                // Fallback seguro para entornos donde LAST_INSERT_ID se pierde por triggers/auditoria.
                $createdPatient = pdoQuery(
                    $pdo,
                    "SELECT id
                     FROM users
                     WHERE role = 'patient' AND dni = ?
                     ORDER BY id DESC
                     LIMIT 1",
                    [$dni]
                )->fetch();
                $newId = (int)($createdPatient['id'] ?? 0);
            }
            if ($newId <= 0) {
                throw new RuntimeException('No se pudo obtener el ID del paciente creado (ni por LAST_INSERT_ID ni por busqueda DNI)');
            }
            if ($referrerUserId > 0) {
                upsertPatientReferral($pdo, $newId, $referrerUserId, $referrerKind);
            }

            $pdo->commit();
            appLog($pdo, 'patient.create', 'patient', (string)$newId, [
                'name' => $name,
                'dni' => $dni,
                'phone' => $phone,
                'referrer_kind' => $referrerUserId > 0 ? ($referrerKind === 'referrer' ? 'referrer' : 'patient') : null,
                'referrer_user_id' => $referrerUserId > 0 ? $referrerUserId : null
            ]);
            echo json_encode([
                'success' => true,
                'message' => 'Paciente creado exitosamente',
                'id' => $newId,
                'redirect_profile_url' => 'patient_profile.php?id=' . $newId . '&new_hx=1',
                'patient' => [
                    'id' => $newId,
                    'name' => $name,
                    'dni' => $dni,
                    'email' => $finalEmail,
                    'age' => $age,
                    'birth_date' => $birthDate !== '' ? $birthDate : null,
                    'patient_code' => $code,
                    'referrer_kind' => $referrerUserId > 0 ? ($referrerKind === 'referrer' ? 'referrer' : 'patient') : null,
                    'referrer_user_id' => $referrerUserId > 0 ? $referrerUserId : null
                ]
            ]);
        } catch (InvalidArgumentException $err) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $err->getMessage()]);
        } catch (Exception $err) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $err->getMessage()]);
        }
        break;

    // PUT: editar paciente — Admin y Secretaria
    case 'PUT':
        if (!in_array($userRole, ['admin', 'receptionist'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sin permiso']);
            exit;
        }
        $id    = (int)($body['id'] ?? 0);
        $name  = trim($body['name'] ?? '');
        $dni   = preg_replace('/\D+/', '', trim($body['dni'] ?? ''));
        $email = trim($body['email'] ?? '');
        $birthDate = trim($body['birth_date'] ?? '');
        $age   = isset($body['age']) && $body['age'] !== '' ? (int)$body['age'] : null;
        $phone = preg_replace('/\D+/', '', trim($body['phone'] ?? ''));
        $hasReferralData = array_key_exists('referrer_user_id', $body) || array_key_exists('referrer_kind', $body);
        $referrerKind = trim($body['referrer_kind'] ?? '');
        $referrerUserId = (int)($body['referrer_user_id'] ?? 0);

        if (!$id || empty($name) || empty($dni)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datos incompletos (Nombre y DNI requeridos)']);
            exit;
        }

        // Verificar DNI único (excluyendo el actual)
        if (!preg_match('/^\d{8}$/', $dni)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El DNI debe tener exactamente 8 digitos']);
            exit;
        }

        if (!preg_match('/^\d{9}$/', $phone)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El telefono debe tener exactamente 9 digitos']);
            exit;
        }

        if ($birthDate !== '') {
            $calculatedAge = calculateAgeFromBirthDate($birthDate);
            if ($calculatedAge === null || $calculatedAge < 0 || $calculatedAge > 120) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'La fecha de nacimiento no es valida']);
                exit;
            }
            $age = $calculatedAge;
        }

        $stmt = pdoQuery($pdo, "SELECT id FROM users WHERE dni = ? AND id != ?", [$dni, $id]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'El DNI ya está registrado por otro paciente']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            pdoQuery($pdo,
                "UPDATE users SET name = ?, dni = ?, email = ?, age = ?, birth_date = ?, phone = ? WHERE id = ? AND role = 'patient'",
                [$name, $dni, $email, $age, $birthDate !== '' ? $birthDate : null, $phone, $id]
            );

            if ($hasReferralData) {
                upsertPatientReferral($pdo, $id, $referrerUserId, $referrerKind);
            }

            $pdo->commit();
            appLog($pdo, 'patient.update', 'patient', (string)$id, [
                'name' => $name,
                'dni' => $dni,
                'phone' => $phone,
                'referrer_kind' => $hasReferralData ? ($referrerUserId > 0 ? ($referrerKind === 'referrer' ? 'referrer' : 'patient') : null) : 'sin_cambio'
            ]);
            echo json_encode(['success' => true, 'message' => 'Paciente actualizado']);
        } catch (InvalidArgumentException $err) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $err->getMessage()]);
        } catch (Exception $err) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al actualizar el paciente: ' . $err->getMessage()]);
        }
        break;

    // DELETE — Solo Admin
    case 'DELETE':
        if ($userRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sin permiso']);
            exit;
        }
        $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            exit;
        }
        pdoQuery($pdo, "DELETE FROM users WHERE id = ? AND role = 'patient'", [$id]);
        appLog($pdo, 'patient.delete', 'patient', (string)$id);
        echo json_encode(['success' => true, 'message' => 'Paciente eliminado']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
