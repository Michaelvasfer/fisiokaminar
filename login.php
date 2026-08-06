<?php
// login.php - Access Point
session_start();
require_once 'db.php';
require_once 'includes/csrf.php';
ensureAuditSchema($pdo);

$error = '';
ensureCsrfToken();

try {
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('must_change_password', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (Exception $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfRequest();
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        // Try searching by email
        $stmt = pdoQuery($pdo, "SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
        $user = $stmt->fetch();

        // If not found by email, try searching by DNI/User (for patients)
        if (!$user) {
            $stmt = pdoQuery($pdo, "SELECT * FROM users WHERE dni = ? LIMIT 1", [$email]);
            $user = $stmt->fetch();
        }

        if ($user) {
            $authenticated = false;
            $mustChangePassword = (int)($user['must_change_password'] ?? 0) === 1;
            // Primary check: password_verify
            if (password_verify($password, $user['password'])) {
                $authenticated = true;
            } 
            // Acceso temporal para pacientes solo en primer ingreso
            else if ($user['role'] === 'patient' && $mustChangePassword) {
                $dni = (string)($user['dni'] ?? '');
                if ($dni !== '' && ($password === $dni || password_verify($dni, $user['password']))) {
                    $authenticated = true;
                }
            }
            // Compatibilidad: si el paciente aún usa DNI como clave antigua, lo dejamos entrar una vez
            else if ($user['role'] === 'patient') {
                $dni = (string)($user['dni'] ?? '');
                if ($dni !== '' && ($password === $dni || password_verify($dni, $user['password']))) {
                    $authenticated = true;
                    $mustChangePassword = true;
                    try {
                        pdoQuery($pdo, "UPDATE users SET must_change_password = 1 WHERE id = ?", [$user['id']]);
                    } catch (Exception $e) {
                    }
                }
            }

            if ($authenticated) {
                if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                    $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['must_change_password'] = $mustChangePassword;
                    appLog($pdo, 'auth.login', 'user', (string)$user['id'], [
                        'role' => $user['role'],
                        'must_change_password' => $mustChangePassword,
                    ], [
                        'user_id' => (int)$user['id'],
                        'user_name' => $user['name'],
                        'user_role' => $user['role'],
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ]);
                    
                    // Redirect based on role
                    if ($user['role'] === 'patient') {
                        header("Location: patient_portal.php");
                    } elseif ($user['role'] === 'referrer') {
                        header("Location: referrer_portal.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                }
            } else {
                $error = 'Email/DNI o contraseña incorrectos.';
            }
        } else {
            $error = 'Usuario no encontrado.';
        }
    } else {
        $error = 'Por favor, complete todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | KaminarFisio</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" href="favicon-192.png" sizes="192x192" type="image/png">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="favicon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="favicon-192.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#00BCD4">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="KaminarFisio">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding-bottom: 0;
            background-color: var(--primary-color);
        }
        .login-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 400px;
            box-shadow: var(--shadow-lg);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
        }
        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-color);
        }
        .login-logo .material-icons-outlined {
            font-size: 3rem;
        }
    </style>
</head>
<body class="theme-therapist"> <!-- Default blue theme for login -->
    <div class="login-card animate-fade-in">
        <div class="login-logo">
            <span class="material-icons-outlined">medical_services</span>
            <h1>KaminarFisio</h1>
        </div>
        
        <?php if($error): ?>
            <div style="background-color: #fee2e2; color: #991b1b; padding: 0.75rem; border-radius: var(--radius-md); margin-bottom: 1rem; text-align: center; font-size: 0.875rem;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="email">Usuario (Email o DNI)</label>
                <input type="text" id="email" name="email" class="form-control" required placeholder="Ingresa tu email o DNI">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Tu contraseña">
            </div>
            <button type="submit" class="btn-primary mt-4">Ingresar</button>
        </form>
        
        <div style="margin-top:1.5rem; text-align:center; font-size:0.75rem; color:var(--text-muted); border-top:1px solid var(--border-color); padding-top:1rem;">
            <p>¿Eres paciente? Usa tu <strong>DNI</strong> como usuario. Si es tu primer ingreso, tu clave temporal también será tu DNI y luego deberás cambiarla.</p>
        </div>
    </div>
</body>
</html>
