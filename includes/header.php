<?php
// includes/header.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'csrf.php';

$pageTitle = isset($pageTitle) ? $pageTitle : 'KaminarFisio';
$userRole  = $_SESSION['role'] ?? 'patient';
$userName  = $_SESSION['name'] ?? 'Usuario';
$userId    = $_SESSION['user_id'] ?? 0;
ensureCsrfToken();
require_once 'auth_helper.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> | KaminarFisio</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=1.1">
    <link rel="icon" href="favicon-192.png" sizes="192x192" type="image/png">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="shortcut icon" href="favicon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="favicon-192.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#00BCD4">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="KaminarFisio">
    <meta name="csrf-token" content="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <script src="js/sync.js"></script>
</head>
<?php $themeClass = "theme-" . $userRole; ?>
<body class="<?= $themeClass ?>">
    <div class="container">
        <header class="app-header animate-fade-in">
            <div class="logo">
                <span class="material-icons-outlined">medical_services</span>
                <span>KaminarFisio</span>
            </div>
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <span class="text-sm font-medium hide-mobile" style="color:var(--primary-dark);"><?= htmlspecialchars($userName) ?></span>

                <!-- BotÃ³n Admin solo para admin -->
                <?php if($userRole === 'admin'): ?>
                <a href="admin.php" title="Panel de Admin" style="display:flex;align-items:center;color:var(--primary-color);">
                    <span class="material-icons-outlined">admin_panel_settings</span>
                </a>
                <?php endif; ?>

                <!-- Cambiar contraseÃ±a (todos los roles) -->
                <button onclick="openModal('modalCambiarPassword')" title="Cambiar contrasena"
                    style="background:none;border:none;cursor:pointer;display:flex;align-items:center;color:rgba(255,255,255,0.85);">
                    <span class="material-icons-outlined">lock_reset</span>
                </button>

                <a href="logout.php" style="display:flex;align-items:center;" title="Cerrar sesion">
                    <span class="material-icons-outlined text-muted" style="cursor:pointer;color:rgba(255,255,255,0.85);">logout</span>
                </a>
            </div>
        </header>
        <main class="mt-4">
