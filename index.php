<?php
// index.php - Dashboard Principal
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (isset($_SESSION['role']) && $_SESSION['role'] === 'referrer') {
    header("Location: referrer_portal.php");
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="refresh" content="0;url=referrer_portal.php">
        <title>Redirigiendo...</title>
        <script>
            window.location.replace('referrer_portal.php');
        </script>
    </head>
    <body></body>
    </html>
    <?php
    exit;
}
require_once 'db.php';
$pageTitle = 'Inicio';
require_once 'includes/header.php';

// ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©tricas reales con Blindaje Total ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚ÂÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬
$activePatients = 0;
$todayAptCount  = 0;
$monthIncome    = 0.0;
$totalIncome    = 0.0;
$todayAppointments = [];
$recentTxns = [];
$nextAppointment = null;
$myExercises = [];
$myPayments = [];
$myPlan = null;

try {
    $res = pdoQuery($pdo, "SELECT COUNT(*) as c FROM users WHERE role = 'patient'")->fetch();
    $activePatients = ($res && isset($res['c'])) ? $res['c'] : 0;
} catch(Exception $e) {}

try {
    $res = pdoQuery($pdo, "SELECT COUNT(*) as c FROM appointments WHERE appointment_date = CURDATE()")->fetch();
    $todayAptCount = ($res && isset($res['c'])) ? $res['c'] : 0;
} catch(Exception $e) {}

try {
    $res = pdoQuery($pdo, "SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE amount > 0 AND MONTH(transaction_date) = MONTH(NOW()) AND YEAR(transaction_date) = YEAR(NOW())")->fetch();
    $monthIncome = (float)(($res && isset($res['total'])) ? $res['total'] : 0);
} catch(Exception $e) {}

try {
    $res = pdoQuery($pdo, "SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE amount > 0")->fetch();
    $totalIncome = (float)(($res && isset($res['total'])) ? $res['total'] : 0);
} catch(Exception $e) {}

// Citas de hoy segÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºn rol
if ($userRole === 'patient') {
    // Datos especÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­ficos para el PACIENTE
    try {
        $todayStmt = pdoQuery($pdo, "
            SELECT a.*, t.name AS therapist_name
            FROM appointments a
            JOIN users t ON a.therapist_id = t.id
            WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled'
            ORDER BY a.appointment_date ASC, a.start_time ASC
            LIMIT 1
        ", [$userId]);
        $nextAppointment = $todayStmt->fetch();
    } catch(Exception $e) {}

    // Consulta resiliente de ejercicios (evita fallar si no existen columnas nuevas aÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºn)
    try {
        $exercisesStmt = pdoQuery($pdo, "SELECT * FROM exercises WHERE patient_id = ? ORDER BY id DESC LIMIT 5", [$userId]);
        $myExercises = $exercisesStmt->fetchAll();
    } catch(Exception $e) {
        $myExercises = [];
    }

    try {
        $paymentsStmt = pdoQuery($pdo, "
            SELECT * FROM transactions WHERE patient_id = ? 
            ORDER BY transaction_date DESC LIMIT 5
        ", [$userId]);
        $myPayments = $paymentsStmt->fetchAll();
    } catch(Exception $e) {}

    // Plan de Tratamiento del paciente
    $myPlan = null;
    try {
        $myPlan = pdoQuery($pdo, "SELECT * FROM treatment_plans WHERE patient_id = ? ORDER BY id DESC LIMIT 1", [$userId])->fetch();
    } catch(Exception $e) {}

} elseif ($userRole === 'therapist') {
    try {
        $stmt = pdoQuery($pdo, "
            SELECT a.*, u.name, t.name AS therapist_name
            FROM appointments a
            JOIN users u ON a.patient_id  = u.id
            JOIN users t ON a.therapist_id = t.id
            WHERE a.appointment_date = CURDATE() AND a.status = 'scheduled'
            ORDER BY a.start_time DESC
        ");
        $todayAppointments = $stmt->fetchAll();
    } catch(Exception $e) {}

    $therapistActivePatients = 0;
    $therapistTodayAttended = 0;
    $therapistActivePlans = 0;
    $therapistRecentPatients = [];
    $therapistAlerts = [];
    $therapistTodayPatients = [];

    try {
        $res = pdoQuery($pdo, "
            SELECT COUNT(*) as c
            FROM users
            WHERE role = 'patient'
        ")->fetch();
        $therapistActivePatients = (int)($res['c'] ?? 0);
    } catch(Exception $e) {}

    try {
        $res = pdoQuery($pdo, "
            SELECT COUNT(*) as c
            FROM appointments
            WHERE appointment_date = CURDATE() AND status = 'completed'
        ")->fetch();
        $therapistTodayAttended = (int)($res['c'] ?? 0);
    } catch(Exception $e) {}

    try {
        $res = pdoQuery($pdo, "
            SELECT COUNT(*) as c
            FROM treatment_plans tp
            WHERE tp.status = 'active'
        ")->fetch();
        $therapistActivePlans = (int)($res['c'] ?? 0);
    } catch(Exception $e) {}

    try {
        $therapistRecentPatients = pdoQuery($pdo, "
            SELECT DISTINCT u.id, u.name, MAX(a.appointment_date) as last_appointment
            FROM appointments a
            JOIN users u ON u.id = a.patient_id
            GROUP BY u.id, u.name
            ORDER BY last_appointment DESC
            LIMIT 5
        ")->fetchAll();
    } catch(Exception $e) {}

    try {
        $therapistTodayPatients = pdoQuery($pdo, "
            SELECT
                u.id,
                u.name,
                MIN(a.start_time) AS first_time,
                COUNT(*) AS sessions_count,
                MAX(CASE WHEN tp.status = 'active' THEN 1 ELSE 0 END) AS has_active_plan
            FROM appointments a
            JOIN users u ON u.id = a.patient_id
            LEFT JOIN treatment_plans tp ON tp.patient_id = a.patient_id
            WHERE a.appointment_date = CURDATE() AND a.status = 'scheduled'
            GROUP BY u.id, u.name
            ORDER BY first_time ASC
        ")->fetchAll();
    } catch(Exception $e) {}

    try {
        $therapistAlerts = pdoQuery($pdo, "
            SELECT ch.id, ch.patient_id, u.name AS patient_name, ch.reason_location, ch.eva_score, ch.created_at
            FROM clinical_histories ch
            JOIN users u ON u.id = ch.patient_id
            LEFT JOIN treatment_plans tp ON tp.clinical_history_id = ch.id
            WHERE tp.id IS NULL OR ch.eva_score >= 7
            ORDER BY ch.created_at DESC
            LIMIT 5
        ")->fetchAll();
    } catch(Exception $e) {}

} else {
    // Admin / Receptionist
    try {
        $stmt = pdoQuery($pdo, "
            SELECT a.*, u.name, t.name AS therapist_name
            FROM appointments a
            JOIN users u ON a.patient_id  = u.id
            JOIN users t ON a.therapist_id = t.id
            WHERE a.appointment_date = CURDATE() AND a.status = 'scheduled'
            ORDER BY a.start_time DESC
        ");
        $todayAppointments = $stmt->fetchAll();
    } catch(Exception $e) {}
    
    // ÃƒÆ’Ã†â€™Ãƒâ€¦Ã‚Â¡ltimas transacciones globales
    try {
        $recentTxns = pdoQuery($pdo, "
            SELECT t.*, u.name AS patient_name
            FROM transactions t
            JOIN users u ON t.patient_id = u.id
            ORDER BY t.transaction_date DESC
            LIMIT 6
        ")->fetchAll();
    } catch(Exception $e) {}
}
?>

<div class="animate-fade-in delay-100">

    <?php if($userRole === 'patient'): ?>
        <!-- VISTA EXCLUSIVA PARA PACIENTES -->
        <div style="margin-bottom: 1.5rem;">
            <h1 style="margin:0;">Hola, <?= explode(' ', $userName)[0] ?></h1>
            <p class="text-sm text-muted">Bienvenido a tu resumen de fisioterapia</p>
        </div>

        <!-- Mi Plan de Tratamiento -->
        <div class="card mb-6" style="border-left: 4px solid #f59e0b;">
            <div class="card-header">
                <h2 class="card-title">
                    <span class="material-icons-outlined" style="vertical-align:middle;color:#f59e0b;font-size:1.1rem;">assignment_turned_in</span>
                    Mi Plan de Tratamiento
                </h2>
                <?php if($myPlan): ?>
                    <a href="paciente_progreso.php" class="btn-action-sm btn-outline" style="white-space:nowrap;padding:0.3rem 0.6rem;background:white;color:var(--primary-color);border:1px solid var(--primary-color); height:28px;">
                         Ver Detalle
                    </a>
                <?php endif; ?>
            </div>
            <div style="padding: 1.25rem;">
                <?php if($myPlan): ?>
                    <?php 
                        $totalSess = (int)($myPlan['total_sessions'] ?? 0);
                        $compSess  = (int)($myPlan['completed_sessions'] ?? 0);
                        $pct = ($totalSess > 0) ? round(($compSess / $totalSess) * 100) : 0;
                    ?>
                    <div style="margin-bottom:0.75rem;">
                        <div style="font-weight:700; font-size:1rem;"><?= htmlspecialchars($myPlan['title']) ?></div>
                        <div style="color:var(--text-muted); font-size:0.85rem;">
                            Progreso: <?= $compSess ?> de <?= $totalSess ?> sesiones
                        </div>
                    </div>
                    <div style="height:8px;background:var(--border-color);border-radius:99px;overflow:hidden;position:relative;">
                        <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg, #f59e0b, #fbbf24);border-radius:99px;transition:width 1s;"></div>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="margin:0;">A&uacute;n no tienes un plan de tratamiento activo asignado.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mis Ejercicios -->
        <div class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">Mis Tareas / Ejercicios</h2>
            </div>
            <div class="list-group">
                <?php if(count($myExercises) > 0): ?>
                    <?php foreach($myExercises as $ex): ?>
                        <div class="list-item">
                            <div class="list-item-icon" style="background:#e0f2fe; color:#0369a1;">
                                <span class="material-icons-outlined">fitness_center</span>
                            </div>
                            <div class="list-item-content">
                                <div class="list-item-title"><?= htmlspecialchars($ex['title']) ?></div>
                                <div class="list-item-subtitle"><?= htmlspecialchars($ex['description']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center" style="padding:1.5rem 0;">A&uacute;n no tienes ejercicios asignados.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mis Pagos Recientes -->
        <div class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">Mis Pagos Recientes</h2>
            </div>
            <div class="list-group">
                <?php if(count($myPayments) > 0): ?>
                    <?php foreach($myPayments as $txn): ?>
                        <div class="list-item">
                            <div class="list-item-icon" style="background:#d1fae5; color:#065f46;">
                                <span class="material-icons-outlined">check_circle</span>
                            </div>
                            <div class="list-item-content">
                                <div class="list-item-title">S/ <?= number_format($txn['amount'], 2) ?></div>
                                <div class="list-item-subtitle"><?= htmlspecialchars(app_text($txn['description'] ?? '')) ?> &middot; <?= date('d/m/Y', strtotime($txn['transaction_date'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center" style="padding:1.5rem 0;">No se registran pagos en tu historial.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif($userRole === 'therapist'): ?>
        <div style="margin-bottom:1.1rem;">
            <h1 style="margin:0;">Panel Cl&iacute;nico</h1>
            <p class="text-sm text-muted">Acciones r&aacute;pidas de tu jornada y seguimiento de pacientes.</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0.85rem;margin-bottom:1rem;">
            <div class="metric-card">
                <div class="metric-value"><?= count($therapistTodayPatients) ?></div>
                <div class="metric-label">Pacientes de Hoy</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $therapistTodayAttended ?></div>
                <div class="metric-label">Asistieron</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= count($therapistAlerts) ?></div>
                <div class="metric-label">Alertas</div>
            </div>
        </div>

        <div class="card mb-4" style="overflow:hidden;">
            <div style="padding:1rem 1rem 0.9rem;background:linear-gradient(135deg,#ecfeff 0%,#f8fafc 100%);border-bottom:1px solid var(--border-color);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <div style="font-size:0.78rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:var(--primary-dark);margin-bottom:0.3rem;">Jornada de hoy</div>
                        <div style="font-size:1.25rem;font-weight:800;color:var(--text-color);">
                            <?= count($therapistTodayPatients) > 0 ? 'Atiendes ' . count($therapistTodayPatients) . ' pacientes hoy' : 'Hoy no tienes pacientes pendientes' ?>
                        </div>
                        <div class="text-sm text-muted" style="margin-top:0.35rem;">
                            <?= $therapistTodayAttended > 0 ? $therapistTodayAttended . ' ya asistieron' : 'Tu agenda est&aacute; libre por ahora' ?>
                        </div>
                    </div>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        <a href="attendance.php" class="btn-action-sm" style="text-decoration:none;">Asistencia</a>
                        <a href="schedule.php" class="btn-action-sm btn-outline" style="text-decoration:none;">Agenda</a>
                    </div>
                </div>
            </div>
            <div class="list-group">
                <?php if(count($therapistTodayPatients) > 0): ?>
                    <?php foreach($therapistTodayPatients as $todayPatient): ?>
                    <div class="list-item" style="gap:0.85rem;">
                        <div class="list-item-icon" style="background:#ecfeff;color:var(--primary-dark);">
                            <span class="material-icons-outlined">personal_injury</span>
                        </div>
                        <div class="list-item-content">
                            <div class="list-item-title"><?= htmlspecialchars(app_text($todayPatient['name'] ?? '')) ?></div>
                            <div class="list-item-subtitle">
                                Primera cita: <?= date('h:i A', strtotime($todayPatient['first_time'])) ?> &middot; <?= (int)($todayPatient['sessions_count']) ?> <?= (int)($todayPatient['sessions_count']) === 1 ? 'sesi&oacute;n' : 'sesiones' ?>
                            </div>
                        </div>
                        <div class="list-item-action" style="display:flex;align-items:center;gap:0.45rem;flex-wrap:wrap;justify-content:flex-end;">
                            <span class="badge <?= (int)$todayPatient['has_active_plan'] === 1 ? 'badge-success' : 'badge-warning' ?>">
                                <?= (int)$todayPatient['has_active_plan'] === 1 ? 'Con plan' : 'Sin plan' ?>
                            </span>
                            <button type="button" class="btn-action-sm btn-outline" onclick="openQuickNoteModal(<?= (int)$todayPatient['id'] ?>, '<?= htmlspecialchars(addslashes($todayPatient['name'] ?? ''), ENT_QUOTES) ?>', { hasActivePlan: <?= (int)$todayPatient['has_active_plan'] === 1 ? 'true' : 'false' ?> })">
                                + Nota
                            </button>
                            <a href="patient_profile.php?id=<?= (int)$todayPatient['id'] ?>" class="btn-action-sm" style="text-decoration:none;">Ver</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center" style="padding:1.25rem 0;">No tienes pacientes pendientes para hoy.</p>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1.2fr 0.8fr;gap:1rem;margin-bottom:1rem;">
            <div class="card mb-0">
                <div class="card-header">
                    <h2 class="card-title">Pendientes Cl&iacute;nicos</h2>
                    <a href="patients.php" class="text-sm font-medium" style="color:var(--primary-color);">Abrir pacientes</a>
                </div>
                <div class="list-group">
                    <?php if(count($therapistAlerts) > 0): ?>
                        <?php foreach($therapistAlerts as $alert): ?>
                        <a href="patient_profile.php?id=<?= (int)$alert['patient_id'] ?>" class="list-item" style="text-decoration:none;">
                            <div class="list-item-icon" style="background:#fff7ed;color:#ea580c;">
                                <span class="material-icons-outlined">warning</span>
                            </div>
                            <div class="list-item-content">
                                <div class="list-item-title"><?= htmlspecialchars(app_text($alert['patient_name'] ?? '')) ?></div>
                                <div class="list-item-subtitle">
                                    <?= htmlspecialchars(app_text($alert['reason_location'] ?? '')) ?>
                                    <?php if((int)$alert['eva_score'] >= 7): ?> &middot; EVA <?= (int)$alert['eva_score'] ?>/10<?php else: ?> &middot; Sin plan enlazado<?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center" style="padding:1.25rem 0;">No tienes alertas cl&iacute;nicas pendientes.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header">
                    <h2 class="card-title">Acciones R&aacute;pidas</h2>
                </div>
                <div style="display:grid;gap:0.75rem;padding:1rem;">
                    <button type="button" onclick="openQuickNoteModal()" class="quick-access-btn" style="display:flex;align-items:center;gap:0.6rem;padding:0.9rem;border:none;border-radius:var(--radius-md);background:#eff6ff;border:1px solid #bfdbfe;cursor:pointer;">
                        <span class="material-icons-outlined" style="color:#2563eb;">edit_note</span>
                        <span style="font-weight:700;color:#1e3a8a;">Nota r&aacute;pida</span>
                    </button>
                    <a href="attendance.php" class="quick-access-btn" style="display:flex;align-items:center;gap:0.6rem;padding:0.9rem;border-radius:var(--radius-md);background:#ecfeff;border:1px solid #bae6fd;text-decoration:none;">
                        <span class="material-icons-outlined" style="color:var(--primary-color);">fact_check</span>
                        <span style="font-weight:700;">Asistencia</span>
                    </a>
                    <a href="patients.php" class="quick-access-btn" style="display:flex;align-items:center;gap:0.6rem;padding:0.9rem;border-radius:var(--radius-md);background:#f0fdf4;border:1px solid #bbf7d0;text-decoration:none;">
                        <span class="material-icons-outlined" style="color:#16a34a;">group</span>
                        <span style="font-weight:700;">Buscar paciente</span>
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- VISTA PARA PERSONAL (ADMIN, RECEPCION, TERAPEUTA) -->
        <div class="metrics-grid mb-6">
            <div class="metric-card">
                <div class="metric-value"><?= number_format($activePatients) ?></div>
                <div class="metric-label">Pacientes</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $todayAptCount ?></div>
                <div class="metric-label">Citas Hoy</div>
            </div>
            <?php if($userRole === 'admin'): ?>
            <div class="metric-card" style="grid-column: span 2;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div class="metric-label">Ingresos del mes</div>
                        <div class="metric-value" style="font-size:1.5rem;color:var(--success);">
                            S/ <?= number_format($monthIncome, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Citas de Hoy -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title" id="todayAppointmentsCount">Citas de Hoy (<?= count($todayAppointments) ?>)</h2>
                <?php if(in_array($userRole, ['admin','receptionist'])): ?>
                <button onclick="openModal('modalNuevaCita')" style="background:var(--primary-color);color:white;border:none;border-radius:var(--radius-sm);padding:0.3rem 0.6rem;cursor:pointer;font-size:0.8rem;font-weight:600;display:flex;align-items:center;gap:0.2rem;">
                    <span class="material-icons-outlined" style="font-size:0.9rem;">add</span>Nueva
                </button>
                <?php endif; ?>
            </div>
            <div class="list-group" id="todayAppointmentsList" style="<?= count($todayAppointments) > 0 ? '' : 'display:none;' ?>">
                <?php foreach($todayAppointments as $apt): ?>
                <a href="patient_profile.php?id=<?= $apt['patient_id'] ?>" class="list-item" id="apt-<?= $apt['id'] ?>" style="text-decoration:none;">
                    <div class="list-item-icon">
                        <span class="material-icons-outlined">person</span>
                    </div>
                    <div class="card-list-content">
                        <div class="list-item-title"><?= htmlspecialchars($apt['name']) ?></div>
                        <div class="list-item-subtitle"><?= htmlspecialchars($apt['type']) ?></div>
                    </div>
                    <div class="list-item-action text-sm font-medium">
                        <?= date('h:i A', strtotime($apt['start_time'])) ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <p class="text-muted text-center" id="todayAppointmentsEmpty" style="padding:1.5rem 0;<?= count($todayAppointments) > 0 ? 'display:none;' : '' ?>">No hay citas pendientes para hoy.</p>
        </div>

        <!-- Historial de Pagos Recientes -->
        <?php if(in_array($userRole, ['admin','receptionist']) && count($recentTxns) > 0): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title">Pagos Recientes</h2>
                <a href="financials.php" style="font-size:0.8rem;color:var(--primary-color);font-weight:600;">Ver todos &rarr;</a>
            </div>
            <div class="list-group">
                <?php foreach($recentTxns as $txn): ?>
                <div class="card-list-row">
                    <div class="list-item-icon" style="background:#d1fae5; color:#065f46;">
                        <span class="material-icons-outlined" style="font-size:1rem;">payments</span>
                    </div>
                    <div class="list-item-content">
                        <div class="card-list-title"><?= htmlspecialchars($txn['patient_name']) ?></div>
                        <div class="list-item-subtitle" style="font-size:0.75rem;"><?= htmlspecialchars(app_text($txn['description'] ?? '')) ?> &middot; S/ <?= number_format($txn['amount'], 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<script>
window.handleAppointmentCreated = (apt) => {
    if (!apt || apt.appointment_date !== '<?= date('Y-m-d') ?>') return;
    const countLabel = document.getElementById('todayAppointmentsCount');
    const list = document.getElementById('todayAppointmentsList');
    const empty = document.getElementById('todayAppointmentsEmpty');
    if (!countLabel || !list) return;

    const countMatch = countLabel.textContent.match(/\((\d+)\)/);
    const nextCount = countMatch ? Number(countMatch[1]) + 1 : 1;
    countLabel.textContent = 'Citas de Hoy (' + nextCount + ')';
    list.style.display = '';
    if (empty) empty.style.display = 'none';

    const item = document.createElement('a');
    item.href = 'patient_profile.php?id=' + apt.patient_id;
    item.className = 'list-item';
    item.style.textDecoration = 'none';
    item.innerHTML = `
        <div class="list-item-icon">
            <span class="material-icons-outlined">person</span>
        </div>
        <div class="list-item-content">
            <div class="list-item-title">${apt.patient_name}</div>
            <div class="list-item-subtitle">${apt.type}</div>
        </div>
        <div class="list-item-action text-sm font-medium">
            ${formatDashboardHour(apt.start_time)}
        </div>
    `;
    list.prepend(item);
};

function formatDashboardHour(time) {
    const [h, m] = (time || '00:00').split(':');
    let hour = Number(h);
    const suffix = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return String(hour).padStart(2, '0') + ':' + m + ' ' + suffix;
}

window.handleAppointmentStatusChanged = (id, status) => {
    if (status === 'completed' || status === 'cancelled') {
        window.handleAppointmentDeleted(id);
    }
};

window.handleAppointmentDeleted = (id) => {
    const list = document.getElementById('todayAppointmentsList');
    const empty = document.getElementById('todayAppointmentsEmpty');
    const countLabel = document.getElementById('todayAppointmentsCount');
    if (!list || !countLabel) return;

    const row = document.getElementById('apt-' + id);
    if (row) row.remove();

    const countMatch = countLabel.textContent.match(/\((\d+)\)/);
    const nextCount = Math.max((countMatch ? Number(countMatch[1]) : 1) - 1, 0);
    countLabel.textContent = 'Citas de Hoy (' + nextCount + ')';

    if (nextCount === 0) {
        list.style.display = 'none';
        if (empty) empty.style.display = '';
    }
};
</script>

<?php require_once 'includes/footer.php'; ?>

