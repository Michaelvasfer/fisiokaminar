<?php
// patient_profile.php - Perfil completo y editable del paciente
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

register_shutdown_function(static function () {
    $fatal = error_get_last();
    if (!$fatal) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    if (!in_array((int)($fatal['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $isPrivileged = in_array($_SESSION['role'] ?? '', ['admin', 'receptionist'], true);
    $title = 'Error al abrir el perfil del paciente';
    $message = $isPrivileged
        ? ('Detalle tecnico: ' . ($fatal['message'] ?? 'Sin detalle') . ' en ' . basename((string)($fatal['file'] ?? '')) . ':' . (int)($fatal['line'] ?? 0))
        : 'No se pudo abrir el perfil del paciente. Intenta nuevamente.';

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>KaminarFisio</title>';
    echo '<style>body{font-family:Inter,Arial,sans-serif;background:#f5f8fb;color:#102a43;margin:0;padding:24px}.box{max-width:760px;margin:8vh auto;background:#fff;border:1px solid #d9e7ef;border-radius:20px;padding:22px;box-shadow:0 14px 36px rgba(16,42,67,.08)}h1{margin:0 0 10px;font-size:1.25rem}p{margin:0;line-height:1.6;color:#486581}.meta{margin-top:14px;font-size:.92rem;color:#7b8794}</style></head><body>';
    echo '<div class="box"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><div class="meta">Si este mensaje aparece despues de una actualizacion, sube el ultimo archivo corregido y vuelve a intentar.</div></div>';
    echo '</body></html>';
});

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

require_once 'db.php';
try {
    ensurePackagesSchema($pdo);
} catch (Throwable $e) {
    error_log('patient_profile ensurePackagesSchema failed: ' . $e->getMessage());
}
try {
    ensureReferralSchema($pdo);
} catch (Throwable $e) {
    error_log('patient_profile ensureReferralSchema failed: ' . $e->getMessage());
}

$userRole = $_SESSION['role'] ?? 'patient';
$userId = (int)($_SESSION['user_id'] ?? 0);
$autoOpenClinicalHx = isset($_GET['new_hx']) && (string)$_GET['new_hx'] === '1';
$hasActivePackage = false;

if (!in_array($userRole, ['admin', 'receptionist', 'therapist', 'patient'])) {
    header("Location: index.php");
    exit;
}

// Soporte de fallback por DNI para flujos de alta reciente.
$dniLookup = trim((string)($_GET['dni'] ?? ''));
$dniLookup = preg_replace('/\D+/', '', $dniLookup);

// Si no se pasa ID, intentar resolver por DNI; si no, listar pacientes para redirigir
$patientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$patientId) {
    if ($dniLookup !== '') {
        $patientByDni = pdoQuery($pdo, "SELECT id FROM users WHERE dni = ? AND role = 'patient' LIMIT 1", [$dniLookup])->fetch();
        if ($patientByDni) {
            $redirectParams = ['id=' . (int)$patientByDni['id']];
            if (isset($_GET['new_hx'])) {
                $redirectParams[] = 'new_hx=' . rawurlencode((string)$_GET['new_hx']);
            }
            if (isset($_GET['protocol_id'])) {
                $redirectParams[] = 'protocol_id=' . rawurlencode((string)$_GET['protocol_id']);
            }
            if (isset($_GET['open_assign_protocol'])) {
                $redirectParams[] = 'open_assign_protocol=' . rawurlencode((string)$_GET['open_assign_protocol']);
            }
            if (isset($_GET['open_package'])) {
                $redirectParams[] = 'open_package=' . rawurlencode((string)$_GET['open_package']);
            }
            header("Location: patient_profile.php?" . implode('&', $redirectParams));
            exit;
        }
    }

    header("Location: patients.php");
    exit;
}

// Seguridad: un paciente SOLO puede ver su propio ID
if ($userRole === 'patient' && $userId != $patientId) {
    header("Location: patient_profile.php?id=" . $userId);
    exit;
}

$patient = pdoQuery($pdo, "SELECT * FROM users WHERE id = ? AND role = 'patient'", [$patientId])->fetch();
if (!$patient) {
    header("Location: patients.php");
    exit;
}

$referralPatientsCatalog = [];
$referralReferrersCatalog = [];
if (in_array($userRole, ['admin', 'receptionist'], true)) {
    try {
        $referralPatientsCatalog = pdoQuery(
            $pdo,
            "SELECT id, name, dni FROM users WHERE role = 'patient' AND id != ? ORDER BY name ASC",
            [$patientId]
        )->fetchAll();
    } catch (Throwable $e) {}

    try {
        $referralReferrersCatalog = pdoQuery(
            $pdo,
            "SELECT id, name, email FROM users WHERE role = 'referrer' ORDER BY name ASC"
        )->fetchAll();
    } catch (Throwable $e) {}
}

$pageTitle = 'Perfil del Paciente';
require_once 'includes/header.php';
?>
<script>
window.currentPatient = <?= json_encode([
    'id' => $patientId,
    'name' => (string)($patient['name'] ?? '')
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.canAssignPackage = true;
<?php if ($autoOpenClinicalHx): ?>
document.body.style.overflow = 'hidden';
<?php endif; ?>
</script>
<?php

// Preparacion de estructuras de notas clinicas (se crean desde BD si la tabla existe)
$nextApts = [];
try {
    $nextApts = pdoQuery($pdo, "
        SELECT a.*, u.name AS therapist_name FROM appointments a
        JOIN users u ON a.therapist_id = u.id
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled'
        ORDER BY a.appointment_date ASC, a.start_time ASC LIMIT 3
    ", [$patientId])->fetchAll();
} catch(Throwable $e) {}

// Notas de sesion (se crean desde BD si la tabla existe)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS session_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        therapist_id INT NOT NULL,
        appointment_id INT DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        notes TEXT,
        session_date DATE NOT NULL,
        created_at DATETIME DEFAULT NOW(),
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(Throwable $e) {}

$sessionNotes = [];
try {
    $sessionNotes = pdoQuery($pdo, "
        SELECT s.*, u.name AS therapist_name FROM session_notes s
        JOIN users u ON s.therapist_id = u.id
        WHERE s.patient_id = ? ORDER BY s.session_date DESC LIMIT 10
    ", [$patientId])->fetchAll();
} catch(Throwable $e) {}

// Paquetes activos
$packages = [];
$packageTemplates = [];
try {
    syncPatientPackagePayments($pdo, $patientId);
    $packages = pdoQuery($pdo, "SELECT * FROM packages WHERE patient_id = ? ORDER BY purchase_date DESC", [$patientId])->fetchAll();
} catch(Throwable $e) {}
try {
    $packageTemplates = pdoQuery($pdo, "SELECT id, name, total_sessions, total_amount FROM package_templates WHERE is_active = 1 ORDER BY total_sessions ASC, total_amount ASC, name ASC")->fetchAll();
} catch(Throwable $e) {}

$packageFinancialSummary = [
    'total_amount' => 0.0,
    'amount_paid' => 0.0,
    'pending_amount' => 0.0,
    'total_sessions' => 0,
    'unused_sessions' => 0,
];

foreach ($packages as &$packageItem) {
    $packageItem['total_amount'] = isset($packageItem['total_amount']) ? (float)$packageItem['total_amount'] : 0.0;
    $packageItem['amount_paid'] = isset($packageItem['amount_paid']) ? (float)$packageItem['amount_paid'] : 0.0;
    $packageItem['pending_amount'] = max(0, $packageItem['total_amount'] - $packageItem['amount_paid']);
    $packageItem['used_sessions'] = max(0, (int)$packageItem['total_sessions'] - (int)$packageItem['unused_sessions']);

    $packageFinancialSummary['total_amount'] += $packageItem['total_amount'];
    $packageFinancialSummary['amount_paid'] += $packageItem['amount_paid'];
    $packageFinancialSummary['pending_amount'] += $packageItem['pending_amount'];
    $packageFinancialSummary['total_sessions'] += (int)$packageItem['total_sessions'];
    $packageFinancialSummary['unused_sessions'] += (int)$packageItem['unused_sessions'];
}
unset($packageItem);

foreach ($packages as $packageItem) {
    $unusedSessions = (int)($packageItem['unused_sessions'] ?? 0);
    $pendingAmount = max(0, (float)($packageItem['total_amount'] ?? 0) - (float)($packageItem['amount_paid'] ?? 0));
    if ($unusedSessions > 0 || $pendingAmount > 0.009) {
        $hasActivePackage = true;
        break;
    }
}
?>
<script>
window.canAssignPackage = <?= $hasActivePackage ? 'false' : 'true' ?>;
</script>
<?php

// Plan de tratamiento
$plan = null;
try {
    $planColumns = $pdo->query("SHOW COLUMNS FROM treatment_plans")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('clinical_history_id', $planColumns)) {
        $pdo->exec("ALTER TABLE treatment_plans ADD COLUMN clinical_history_id INT NULL DEFAULT NULL");
    }
    $plan = pdoQuery($pdo, "
        SELECT tp.*, ch.reason_location AS history_reason_location, ch.created_at AS history_created_at
        FROM treatment_plans tp
        LEFT JOIN clinical_histories ch ON ch.id = tp.clinical_history_id
        WHERE tp.patient_id = ?
        ORDER BY (tp.status = 'active') DESC, tp.id DESC
        LIMIT 1
    ", [$patientId])->fetch();
} catch(Throwable $e) {}

$latestPublicIntake = null;
$latestPublicIntakePhotos = [];
try {
    $latestPublicIntake = pdoQuery($pdo, "
        SELECT *
        FROM lead_intakes
        WHERE patient_id = ?
        ORDER BY id DESC
        LIMIT 1
    ", [$patientId])->fetch();

    if ($latestPublicIntake) {
        $latestPublicIntake['answers'] = json_decode((string)($latestPublicIntake['answers_json'] ?? ''), true) ?: [];
        $latestPublicIntake['result'] = json_decode((string)($latestPublicIntake['result_json'] ?? ''), true) ?: [];
        $latestPublicIntake['match_fields'] = json_decode((string)($latestPublicIntake['matched_fields_json'] ?? ''), true) ?: [];
        $latestPublicIntakePhotos = pdoQuery($pdo, "
            SELECT file_path, original_name, caption
            FROM lead_intake_photos
            WHERE intake_id = ?
            ORDER BY id DESC
            LIMIT 4
        ", [(int)$latestPublicIntake['id']])->fetchAll();
        unset($latestPublicIntake['answers_json'], $latestPublicIntake['result_json'], $latestPublicIntake['matched_fields_json']);
    }
} catch (Throwable $e) {}

// 1. Fotos de evolucion
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        therapist_id INT NOT NULL,
        title VARCHAR(255),
        photo_path VARCHAR(512) NOT NULL,
        category ENUM('before', 'after', 'progress', 'exam') DEFAULT 'progress',
        created_at DATETIME DEFAULT NOW(),
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(Throwable $e) {}

$photos = [];
try {
    $photos = pdoQuery($pdo, "SELECT * FROM patient_photos WHERE patient_id = ? ORDER BY created_at DESC", [$patientId])->fetchAll();
} catch(Throwable $e) {}

// 2. Ejercicios (Resiliente)
$exercises = [];
try {
    $exercises = pdoQuery($pdo, "SELECT * FROM exercises WHERE patient_id = ? AND is_active = 1 ORDER BY created_at DESC", [$patientId])->fetchAll();
} catch(Throwable $e) {
    try {
        $exercises = pdoQuery($pdo, "SELECT * FROM exercises WHERE patient_id = ? ORDER BY id DESC", [$patientId])->fetchAll();
    } catch(Throwable $e2) {
        $exercises = [];
    }
}

// TODAS las citas (historial completo)
$allApts = [];
try {
    $allApts = pdoQuery($pdo, "
        SELECT a.*, u.name AS therapist_name FROM appointments a
        JOIN users u ON a.therapist_id = u.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.start_time DESC
    ", [$patientId])->fetchAll();
} catch(Throwable $e) {}

// Historial de pagos del paciente
$patientTxns = [];
$totalPagado = 0;
try {
    $patientTxns = pdoQuery($pdo, "SELECT * FROM transactions WHERE patient_id = ? ORDER BY transaction_date DESC, id DESC", [$patientId])->fetchAll();
    foreach($patientTxns as $txn) { if($txn['amount'] > 0) $totalPagado += $txn['amount']; }
} catch(Throwable $e) {}

// Avatar inicial
$initial = $patient['name'] ? app_upper(app_substr($patient['name'], 0, 1)) : '?';
// 3. Historias clinicas (NUEVO)
$histories = [];
try {
    $histories = pdoQuery($pdo, "
        SELECT ch.*,
               tp.id AS linked_plan_id,
               tp.title AS linked_plan_title,
               tp.status AS linked_plan_status,
               tp.completed_sessions AS linked_plan_completed_sessions,
               tp.total_sessions AS linked_plan_total_sessions
        FROM clinical_histories ch
        LEFT JOIN treatment_plans tp ON tp.id = (
            SELECT tp2.id
            FROM treatment_plans tp2
            WHERE tp2.clinical_history_id = ch.id
            ORDER BY tp2.id DESC
            LIMIT 1
        )
        WHERE ch.patient_id = ?
        ORDER BY ch.created_at DESC, tp.id DESC
    ", [$patientId])->fetchAll();
} catch(Throwable $e) {}

$canEdit = hasPermission($pdo, $userId, $userRole, 'edit_patient');
$canNote = hasPermission($pdo, $userId, $userRole, 'add_note');
$canAddApt = hasPermission($pdo, $userId, $userRole, 'add_apt');
$canAddPayment = hasPermission($pdo, $userId, $userRole, 'add_payment');
$canDeletePayment = hasPermission($pdo, $userId, $userRole, 'delete_payment');
$canClinical = hasPermission($pdo, $userId, $userRole, 'add_clinical_hx');
$canManagePatientEdit = $canEdit && in_array($userRole, ['admin', 'receptionist'], true);

$referralCreditSummary = [
    'total_generated' => 0.0,
    'available_balance' => 0.0,
    'used_balance' => 0.0,
];
$patientReferralSource = null;
$patientReferredPatients = [];

try {
    syncReferralRewardsForPatient($pdo, $patientId);
    $referralCreditSummary = getReferralCreditSummary($pdo, $patientId);
    $patientReferralSource = pdoQuery(
        $pdo,
        "SELECT
            r.referrer_kind,
            r.referrer_user_id,
            r.percent_snapshot,
            r.reward_mode,
            u.name AS referrer_name
         FROM referrals r
         JOIN users u ON u.id = r.referrer_user_id
         WHERE r.referred_patient_id = ?
           AND r.status = 'active'
         LIMIT 1",
        [$patientId]
    )->fetch();

    $patientReferredPatients = pdoQuery(
        $pdo,
        "SELECT
            u.id,
            u.name,
            u.patient_code,
            COALESCE(SUM(rr.generated_amount), 0) AS total_generated,
            COALESCE(SUM(rr.remaining_amount), 0) AS total_available
         FROM referrals r
         JOIN users u ON u.id = r.referred_patient_id
         LEFT JOIN referral_rewards rr ON rr.referral_id = r.id
         WHERE r.referrer_user_id = ?
           AND r.referrer_kind = 'patient'
         GROUP BY u.id, u.name, u.patient_code
         ORDER BY u.name ASC",
        [$patientId]
    )->fetchAll();
} catch (Throwable $e) {}
?>

<div class="animate-fade-in delay-100">

    <!-- Header del perfil -->
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
        <a href="patients.php" style="color:var(--text-muted);display:flex;align-items:center;" title="Volver a Pacientes">
            <span class="material-icons-outlined" style="font-size:1.4rem;">arrow_back</span>
        </a>
        <div style="width:64px;height:64px;border-radius:50%;background:var(--primary-light);color:var(--primary-color);display:flex;align-items:center;justify-content:center;font-size:1.75rem;font-weight:700;flex-shrink:0;">
            <?= $initial ?>
        </div>
        <div style="flex:1;min-width:0;">
            <h1 style="margin:0;font-size:1.4rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($patient['name']) ?></h1>
            <p style="margin:0;font-size:0.8rem;color:var(--text-muted);">
                DNI: <?= htmlspecialchars($patient['dni'] ?? '-') ?> &middot;
                <?= htmlspecialchars($patient['patient_code'] ?? '-') ?>
                <?php if($patient['age']): ?> &middot; <?= $patient['age'] ?> a&ntilde;os<?php endif; ?>
            </p>
        </div>
        <?php if($canManagePatientEdit): ?>
        <button onclick="toggleEditForm()" style="background:var(--primary-light);color:var(--primary-color);border:none;border-radius:var(--radius-md);padding:0.5rem 0.75rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;font-size:0.875rem;font-weight:600;">
            <span class="material-icons-outlined" style="font-size:1rem;">edit</span>Editar
        </button>
        <?php endif; ?>
    </div>

    <!-- Formulario de edici&oacute;n (oculto por defecto) -->
    <?php if($canManagePatientEdit): ?>
    <div id="editForm" style="display:none;">
        <div class="card mb-4" style="border:2px solid var(--primary-color);">
            <div class="card-header">
                <h2 class="card-title">Editar Datos del Paciente</h2>
                <button onclick="toggleEditForm()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);">
                    <span class="material-icons-outlined">close</span>
                </button>
            </div>
            <form onsubmit="savePatient(event)">
                <input type="hidden" id="ep_id" value="<?= $patient['id'] ?>">
                <div class="form-group">
                    <label>Nombre completo *</label>
                    <input type="text" id="ep_name" class="form-control" value="<?= htmlspecialchars($patient['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>DNI *</label>
                    <input type="text" id="ep_dni" class="form-control" value="<?= htmlspecialchars($patient['dni'] ?? '') ?>" required inputmode="numeric" maxlength="8">
                </div>
                <div class="form-group">
                    <label>Correo electr&oacute;nico</label>
                    <input type="email" id="ep_email" class="form-control" value="<?= htmlspecialchars($patient['email']) ?>">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label>Edad</label>
                        <input type="number" id="ep_age" class="form-control" value="<?= $patient['age'] ?>" min="1" max="120">
                    </div>
                    <div class="form-group">
                        <label>Tel&eacute;fono *</label>
                        <input type="tel" id="ep_phone" class="form-control" value="<?= htmlspecialchars($patient['phone'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>C&oacute;digo</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($patient['patient_code'] ?? '') ?>" disabled style="opacity:0.6;">
                </div>
                <div class="form-group">
                    <label for="ep_referrer_kind">Referido por</label>
                    <select id="ep_referrer_kind" class="form-control" onchange="toggleEditPatientReferrerFields()">
                        <option value="">Sin referido</option>
                        <option value="patient" <?= (($patientReferralSource['referrer_kind'] ?? '') === 'patient') ? 'selected' : '' ?>>Paciente actual</option>
                        <option value="referrer" <?= (($patientReferralSource['referrer_kind'] ?? '') === 'referrer') ? 'selected' : '' ?>>Jaladora externa</option>
                    </select>
                </div>
                <div class="form-group" id="ep_referrer_patient_group" style="display:none;">
                    <label for="ep_referrer_patient_id">Paciente que refiere</label>
                    <select id="ep_referrer_patient_id" class="form-control">
                        <option value="">Seleccionar paciente</option>
                        <?php foreach($referralPatientsCatalog as $rp): ?>
                        <option value="<?= (int)$rp['id'] ?>" <?= (($patientReferralSource['referrer_kind'] ?? '') === 'patient' && (int)($patientReferralSource['referrer_user_id'] ?? 0) === (int)$rp['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rp['name']) ?><?= !empty($rp['dni']) ? ' Â· DNI ' . htmlspecialchars($rp['dni']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="ep_referrer_external_group" style="display:none;">
                    <label for="ep_referrer_external_id">Jaladora</label>
                    <select id="ep_referrer_external_id" class="form-control">
                        <option value="">Seleccionar jaladora</option>
                        <?php foreach($referralReferrersCatalog as $rr): ?>
                        <option value="<?= (int)$rr['id'] ?>" <?= (($patientReferralSource['referrer_kind'] ?? '') === 'referrer' && (int)($patientReferralSource['referrer_user_id'] ?? 0) === (int)$rr['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rr['name']) ?><?= !empty($rr['email']) ? ' Â· ' . htmlspecialchars($rr['email']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary" id="btnSavePatient">
                    <span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1rem;">save</span>Guardar Cambios
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Botones de acci&oacute;n r&aacute;pida -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1.5rem;">
        <?php if($canAddApt): ?>
        <button onclick="openModal('modalNuevaCita')"
            style="padding:0.75rem;background:var(--primary-light);color:var(--primary-color);border:1px solid rgba(0,0,0,0.05);border-radius:var(--radius-md);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;font-weight:600;font-size:0.875rem;">
            <span class="material-icons-outlined" style="font-size:1rem;">event</span>Nueva Cita
        </button>
        <?php endif; ?>

        <?php if($canAddPayment && in_array($userRole, ['admin', 'receptionist'])): ?>
        <button onclick="openModal('modalCobrarPago')"
            style="padding:0.75rem;background:#ecfdf5;color:#065f46;border:1px solid rgba(0,0,0,0.05);border-radius:var(--radius-md);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;font-weight:600;font-size:0.875rem;">
            <span class="material-icons-outlined" style="font-size:1rem;">payments</span>Cobrar
        </button>
        <?php endif; ?>

        <?php if($userRole === 'admin'): ?>
        <button onclick="openProtocolModal(null, '', '', true)"
            style="padding:0.75rem;background:#fff7ed;color:#b45309;border:1px solid rgba(0,0,0,0.05);border-radius:var(--radius-md);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;font-weight:600;font-size:0.875rem;">
            <span class="material-icons-outlined" style="font-size:1rem;">sync_alt</span>Cambiar Plan
        </button>
        <?php endif; ?>

        <?php if($canClinical): ?>
        <button onclick="openClinicalModal()"
            style="padding:0.75rem;background:#fff7ed;color:#9a3412;border:1px solid rgba(0,0,0,0.05);border-radius:var(--radius-md);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;font-weight:600;font-size:0.875rem;grid-column: span 2;">
            <span class="material-icons-outlined" style="font-size:1.1rem;">assignment</span>Historia Cl&iacute;nica
        </button>
        <?php endif; ?>
        <?php if(in_array($userRole, ['admin', 'receptionist'])): ?>
        <button onclick="resetPatientPassword()"
            style="padding:0.75rem;background:#fef2f2;color:#b91c1c;border:1px solid rgba(0,0,0,0.05);border-radius:var(--radius-md);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;font-weight:600;font-size:0.875rem;grid-column: span 2;">
            <span class="material-icons-outlined" style="font-size:1rem;">lock_reset</span>Resetear Clave al DNI
        </button>
        <?php endif; ?>
    </div>

    <?php if($latestPublicIntake): ?>
    <?php
        $intakeAnswers = $latestPublicIntake['answers'] ?? [];
        $intakeResult = $latestPublicIntake['result'] ?? [];
        $intakeFlags = array_values(array_filter((array)($intakeAnswers['red_flags'] ?? [])));
        $intakeMatches = is_array($latestPublicIntake['match_fields'] ?? null) ? $latestPublicIntake['match_fields'] : [];
        $intakeMatchLabels = [];
        if (!empty($intakeMatches['dni'])) $intakeMatchLabels[] = 'DNI';
        if (!empty($intakeMatches['phone'])) $intakeMatchLabels[] = 'telefono';
    ?>
    <div class="card mb-4" style="border-left:4px solid #0f766e;">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:#0f766e;font-size:1.1rem;">chat</span>
                Ingreso Web / WhatsApp
            </h2>
            <span class="badge" style="background:#ecfeff;color:#0f766e;">
                <?= htmlspecialchars(app_text($latestPublicIntake['status'] ?? 'draft')) ?>
            </span>
        </div>
        <div style="padding:1rem;">
            <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.7rem;">
                Registrado el <?= !empty($latestPublicIntake['created_at']) ? date('d/m/Y h:i A', strtotime($latestPublicIntake['created_at'])) : '-' ?>
            </div>
            <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.7rem;line-height:1.5;">
                <?php if(!empty($latestPublicIntake['phone'])): ?>
                    <strong style="color:var(--text-main);">Telefono reportado:</strong> <?= htmlspecialchars(app_text($latestPublicIntake['phone'])) ?>
                <?php endif; ?>
                <?php if(!empty($latestPublicIntake['phone']) && !empty($latestPublicIntake['dni'])): ?>&middot; <?php endif; ?>
                <?php if(!empty($latestPublicIntake['dni'])): ?>
                    <strong style="color:var(--text-main);">DNI reportado:</strong> <?= htmlspecialchars(app_text($latestPublicIntake['dni'])) ?>
                <?php endif; ?>
            </div>
            <?php if(count($intakeMatchLabels) > 0): ?>
            <div style="padding:0.8rem;border:1px solid #f59e0b;border-radius:var(--radius-md);background:#fffbeb;color:#92400e;font-size:0.8rem;font-weight:600;margin-bottom:0.8rem;">
                Posible coincidencia detectada por <?= htmlspecialchars(implode(' y ', $intakeMatchLabels)) ?>.
                Este ingreso se aislo en un paciente nuevo por seguridad y conviene verificar identidad antes de fusionar historiales.
            </div>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0.75rem;margin-bottom:0.9rem;">
                <div style="padding:0.8rem;border:1px solid var(--border-color);border-radius:var(--radius-md);background:#f8fafc;">
                    <div style="font-size:0.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);">Zona</div>
                    <div style="font-size:0.95rem;font-weight:700;color:var(--text-main);"><?= htmlspecialchars(app_text($intakeAnswers['pain_area'] ?? 'No especificada')) ?></div>
                </div>
                <div style="padding:0.8rem;border:1px solid var(--border-color);border-radius:var(--radius-md);background:#f8fafc;">
                    <div style="font-size:0.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);">EVA</div>
                    <div style="font-size:0.95rem;font-weight:700;color:var(--text-main);"><?= (int)($intakeAnswers['pain_score'] ?? 0) ?>/10</div>
                </div>
            </div>
            <div style="font-size:0.82rem;color:var(--text-main);line-height:1.55;margin-bottom:0.7rem;">
                <strong>Limitacion:</strong>
                <?= htmlspecialchars(app_text($intakeAnswers['main_limitation'] ?? 'No especificada')) ?>
            </div>
            <div style="font-size:0.82rem;color:var(--text-main);line-height:1.55;margin-bottom:0.7rem;">
                <strong>Plan sugerido:</strong>
                <?= htmlspecialchars(app_text($intakeResult['recommended_plan_name'] ?? 'Evaluacion inicial personalizada')) ?>
                &middot; <?= (int)($intakeResult['suggested_sessions'] ?? 0) ?> sesiones sugeridas
            </div>
            <?php if(count($intakeFlags) > 0 || !empty($intakeResult['needs_trauma_eval'])): ?>
            <div style="padding:0.8rem;border:1px solid #fcd34d;border-radius:var(--radius-md);background:#fffbeb;color:#92400e;font-size:0.8rem;font-weight:600;">
                <?= !empty($intakeResult['needs_trauma_eval']) ? 'Se sugirio revisar traumatologia antes o junto con terapia.' : 'Se registraron alertas en el ingreso.' ?>
                <?php if(count($intakeFlags) > 0): ?>
                <div style="margin-top:0.35rem;font-weight:500;">
                    <?= htmlspecialchars(app_text(implode(', ', $intakeFlags))) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if(count($latestPublicIntakePhotos) > 0): ?>
            <div style="margin-top:0.8rem;">
                <div style="font-size:0.74rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:0.45rem;">Fotos enviadas</div>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                    <?php foreach($latestPublicIntakePhotos as $intakePhoto): ?>
                    <a href="<?= htmlspecialchars($intakePhoto['file_path']) ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.45rem 0.7rem;border-radius:999px;background:#f0fdfa;border:1px solid #99f6e4;color:#0f766e;font-size:0.76rem;font-weight:700;text-decoration:none;">
                        <span class="material-icons-outlined" style="font-size:0.95rem;">photo</span>
                        <?= htmlspecialchars(app_text($intakePhoto['original_name'] ?: ($intakePhoto['caption'] ?: 'Foto enviada'))) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pr&oacute;ximas citas -->
    <?php if(count($nextApts) > 0): ?>
    <div class="card mb-4" id="packages-card">
        <div class="card-header">
            <h2 class="card-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.1rem;">upcoming</span> Pr&oacute;ximas Citas</h2>
        </div>
        <div class="list-group">
            <?php foreach($nextApts as $apt): ?>
            <div class="card-list-row">
                <div class="card-list-content">
                    <div style="font-size:0.875rem;font-weight:600;"><?= date('d/m/Y', strtotime($apt['appointment_date'])) ?> &middot; <?= date('h:i A', strtotime($apt['start_time'])) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($apt['type']) ?> &middot; <?= htmlspecialchars($apt['therapist_name']) ?></div>
                    <?php if(($apt['source_channel'] ?? '') === 'public_intake'): ?>
                    <div style="margin-top:0.25rem;">
                        <span class="badge" style="background:#ecfeff;color:#0f766e;">Ingreso Web / WhatsApp</span>
                    </div>
                    <?php endif; ?>
                </div>
                <span class="badge badge-primary">Agendada</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Paquetes -->
    <?php if(in_array($userRole, ['admin', 'receptionist']) && (count($packages) > 0 || count($packageTemplates) > 0)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.1rem;">inventory_2</span> Paquetes</h2>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <?php if(in_array($userRole, ['admin', 'receptionist'])): ?>
                <button
                    onclick="togglePackageAssignmentForm()"
                    class="btn-action-sm btn-success"
                    style="height:28px;<?= $hasActivePackage ? 'opacity:0.6;cursor:not-allowed;' : '' ?>"
                    <?= $hasActivePackage ? 'disabled title="Ya existe un paquete activo"' : '' ?>
                >
                    <span class="material-icons-outlined" style="font-size:0.9rem;">add</span>Asignar
                </button>
                <?php endif; ?>
                <a href="financials.php?patient_id=<?= $patientId ?>" style="font-size:0.8rem;color:var(--primary-color);font-weight:600;">Ver todos &rarr;</a>
            </div>
        </div>
        <div style="padding:1rem 1rem 0;">
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0.75rem;">
                <div style="padding:0.85rem;border:1px solid var(--border-color);border-radius:var(--radius-md);background:#f8fafc;">
                    <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Valor paquetes</div>
                    <div style="font-size:1.1rem;font-weight:800;color:var(--text-main);">S/ <?= number_format($packageFinancialSummary['total_amount'], 2) ?></div>
                </div>
                <div style="padding:0.85rem;border:1px solid #bbf7d0;border-radius:var(--radius-md);background:#f0fdf4;">
                    <div style="font-size:0.72rem;color:#15803d;text-transform:uppercase;font-weight:700;">Abonado</div>
                    <div style="font-size:1.1rem;font-weight:800;color:#166534;">S/ <?= number_format($packageFinancialSummary['amount_paid'], 2) ?></div>
                </div>
                <div style="padding:0.85rem;border:1px solid #fecaca;border-radius:var(--radius-md);background:#fef2f2;">
                    <div style="font-size:0.72rem;color:#b91c1c;text-transform:uppercase;font-weight:700;">Pendiente</div>
                    <div style="font-size:1.1rem;font-weight:800;color:#991b1b;">S/ <?= number_format($packageFinancialSummary['pending_amount'], 2) ?></div>
                </div>
            </div>
        </div>
        <?php if(in_array($userRole, ['admin', 'receptionist'])): ?>
        <?php if($hasActivePackage): ?>
        <div style="padding:0.75rem 1rem;background:#fff7ed;border-bottom:1px solid #fed7aa;color:#9a3412;font-size:0.83rem;font-weight:600;">
            Ya existe un paquete activo para este paciente. Completa sesiones y saldo pendiente antes de asignar otro.
        </div>
        <?php endif; ?>
        <div id="addPackageForm" class="hidden-form" style="padding:1rem;background:var(--primary-light);border-bottom:1px solid var(--border-color);">
            <form id="formPackage" onsubmit="savePackage(event)">
                <div class="form-group">
                    <label>Paquete base</label>
                    <select id="pkg_template_id" class="form-control" onchange="updatePackageTemplatePreview()" required>
                        <option value="">Selecciona un paquete base</option>
                        <?php foreach($packageTemplates as $template): ?>
                        <option value="<?= (int)$template['id'] ?>" data-name="<?= htmlspecialchars($template['name'], ENT_QUOTES) ?>" data-sessions="<?= (int)$template['total_sessions'] ?>" data-amount="<?= number_format((float)$template['total_amount'], 2, '.', '') ?>">
                            <?= htmlspecialchars($template['name']) ?> - <?= (int)$template['total_sessions'] ?> sesiones - S/ <?= number_format((float)$template['total_amount'], 2) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="margin-top:0.45rem;font-size:0.76rem;color:var(--text-muted);">
                        Admin define los paquetes base. Aqui se crean y solo se asignan al paciente.
                    </div>
                    <input type="hidden" id="pkg_name" value="">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label>Total de sesiones</label>
                        <input type="number" id="pkg_sessions" class="form-control" value="0" min="1" required readonly>
                    </div>
                    <div class="form-group">
                        <label>Fecha de compra</label>
                        <input type="date" id="pkg_purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label>Monto total (S/)</label>
                        <input type="number" id="pkg_total_amount" class="form-control" value="0" min="0" step="0.01" required readonly>
                    </div>
                    <div class="form-group">
                        <label>Abono inicial (S/)</label>
                        <input type="number" id="pkg_amount_paid" class="form-control" value="0" min="0" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label>M&eacute;todo de pago del abono inicial</label>
                    <select id="pkg_payment_method" class="form-control">
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia bancaria</option>
                        <option value="Tarjeta">Tarjeta</option>
                        <option value="Yape">Yape</option>
                        <option value="Plin">Plin</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" id="btnSavePackage">Asignar Paquete</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="list-group">
            <?php foreach($packages as $pkg): ?>
            <div class="card-list-row">
                <div class="card-list-content">
                    <div class="card-list-title"><?= htmlspecialchars($pkg['name']) ?></div>
                    <div class="card-list-subtitle">
                        Sesiones disponibles: <strong><?= (int)$pkg['unused_sessions'] ?></strong> de <?= (int)$pkg['total_sessions'] ?>
                        <?php if(((float)($pkg['total_amount'] ?? 0)) > 0): ?>
                         &middot; Abonado S/ <?= number_format((float)$pkg['amount_paid'], 2) ?> de S/ <?= number_format((float)$pkg['total_amount'], 2) ?>
                        <?php endif; ?>
                    </div>
                    <?php if(((float)($pkg['total_amount'] ?? 0)) > 0): ?>
                    <div style="font-size:0.72rem;color:<?= ((float)$pkg['pending_amount'] > 0) ? '#b91c1c' : '#15803d' ?>;font-weight:700;margin-top:0.2rem;">
                        <?= ((float)$pkg['pending_amount'] > 0) ? 'Saldo pendiente: S/ ' . number_format((float)$pkg['pending_amount'], 2) : 'Paquete pagado completamente' ?>
                    </div>
                    <?php if(((float)$pkg['pending_amount'] > 0) && in_array($userRole, ['admin', 'receptionist'])): ?>
                    <button
                        type="button"
                        onclick="assignExistingPaymentToPackage(<?= (int)$pkg['id'] ?>, <?= number_format((float)$pkg['pending_amount'], 2, '.', '') ?>)"
                        class="btn-secondary"
                        style="margin-top:0.45rem;padding:0.35rem 0.55rem;font-size:0.72rem;width:auto;"
                    >
                        Asignar pago ya generado
                    </button>
                    <?php endif; ?>
                    <?php if($userRole === 'admin'): ?>
                    <button
                        type="button"
                        onclick="changePackageTemplate(<?= (int)$pkg['id'] ?>)"
                        class="btn-secondary"
                        style="margin-top:0.35rem;padding:0.35rem 0.55rem;font-size:0.72rem;width:auto;"
                    >
                        Cambiar paquete
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <!-- Barra de progreso de sesiones -->
                <div style="width:80px;">
                    <div style="height:6px;background:var(--border-color);border-radius:99px;overflow:hidden;">
                        <?php 
                            $pkgPct = ($pkg['total_sessions'] > 0) ? round(($pkg['unused_sessions']/$pkg['total_sessions'])*100) : 0;
                        ?>
                        <div style="height:100%;width:<?= $pkgPct ?>%;background:var(--primary-color);border-radius:99px;"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if($patientReferralSource || (float)$referralCreditSummary['total_generated'] > 0 || count($patientReferredPatients) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title"><span class="material-icons-outlined" style="vertical-align:middle;color:#2563eb;font-size:1.1rem;">share</span> Referidos</h2>
        </div>
        <div style="padding:1rem;">
            <?php if($patientReferralSource): ?>
            <div style="padding:0.85rem;border:1px solid #dbeafe;border-radius:var(--radius-md);background:#eff6ff;margin-bottom:1rem;">
                <div style="font-size:0.72rem;color:#1d4ed8;text-transform:uppercase;font-weight:800;">Paciente referido por</div>
                <div style="font-size:1rem;font-weight:800;color:#1e3a8a;"><?= htmlspecialchars($patientReferralSource['referrer_name']) ?></div>
                <div style="font-size:0.78rem;color:#1e40af;">
                    <?= $patientReferralSource['referrer_kind'] === 'referrer' ? 'Jaladora externa' : 'Paciente' ?>
                    &middot; Comisi&oacute;n activa <?= number_format((float)$patientReferralSource['percent_snapshot'], 0) ?>%
                </div>
                <?php if($userRole === 'admin'): ?>
                <div style="display:flex;gap:0.45rem;flex-wrap:wrap;margin-top:0.7rem;">
                    <button type="button" onclick="recalculateReferralRewards(false)" class="btn-secondary" style="padding:0.4rem 0.7rem;font-size:0.74rem;width:auto;">
                        Recalcular comisiÃ³n
                    </button>
                    <button type="button" onclick="recalculateReferralRewards(true)" class="btn-secondary" style="padding:0.4rem 0.7rem;font-size:0.74rem;width:auto;background:#fff7ed;border:1px solid #fdba74;color:#9a3412;">
                        ComisiÃ³n retroactiva
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(3, minmax(0, 1fr));gap:0.7rem;margin-bottom:1rem;">
                <div style="padding:0.8rem;border:1px solid #dbeafe;border-radius:var(--radius-md);background:#eff6ff;">
                    <div style="font-size:0.68rem;color:#1d4ed8;text-transform:uppercase;font-weight:800;">Saldo disponible</div>
                    <div style="font-size:1rem;font-weight:800;color:#1e3a8a;">S/ <?= number_format((float)$referralCreditSummary['available_balance'], 2) ?></div>
                </div>
                <div style="padding:0.8rem;border:1px solid #d1fae5;border-radius:var(--radius-md);background:#f0fdf4;">
                    <div style="font-size:0.68rem;color:#15803d;text-transform:uppercase;font-weight:800;">Generado</div>
                    <div style="font-size:1rem;font-weight:800;color:#166534;">S/ <?= number_format((float)$referralCreditSummary['total_generated'], 2) ?></div>
                </div>
                <div style="padding:0.8rem;border:1px solid #e2e8f0;border-radius:var(--radius-md);background:#f8fafc;">
                    <div style="font-size:0.68rem;color:#475569;text-transform:uppercase;font-weight:800;">Usado</div>
                    <div style="font-size:1rem;font-weight:800;color:#0f172a;">S/ <?= number_format((float)$referralCreditSummary['used_balance'], 2) ?></div>
                </div>
            </div>

            <?php if(count($patientReferredPatients) > 0): ?>
                <?php foreach($patientReferredPatients as $refPatient): ?>
                <div style="padding:0.75rem 0;border-top:1px solid var(--border-color);">
                    <div style="font-weight:700;font-size:0.85rem;"><?= htmlspecialchars($refPatient['name']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($refPatient['patient_code'] ?: 'Paciente referido') ?></div>
                    <div style="font-size:0.75rem;color:#1d4ed8;font-weight:700;margin-top:0.2rem;">
                        Cr&eacute;dito generado S/ <?= number_format((float)$refPatient['total_generated'], 2) ?>
                        <?php if((float)$refPatient['total_available'] > 0): ?>
                        &middot; Disponible S/ <?= number_format((float)$refPatient['total_available'], 2) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php elseif(!$patientReferralSource): ?>
            <p class="text-muted text-center" style="font-size:0.8rem;">A&uacute;n no se registran movimientos de referidos para este paciente.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- HISTORIAS CLINICAS -->
    <!-- HISTORIAS CLINICAS (NEW) -->
    <!-- /HISTORIAS CLINICAS -->
    <div class="card mb-4" id="clinical-histories">
        <div class="card-header">
            <h2 class="card-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.1rem;">history_edu</span> Historia Cl&iacute;nica</h2>
            <?php if($canNote): ?>
            <button onclick="openClinicalModal()" class="btn-action-sm btn-success" style="height:28px;">
                <span class="material-icons-outlined" style="font-size:0.9rem;">add</span>Nuevo
            </button>
            <?php endif; ?>
        </div>
        <div style="padding:1rem;">
            <?php if(count($histories) > 0): ?>
                <?php foreach($histories as $hx): ?>
                <div class="card-list-row">
                    <div class="card-list-content">
                        <div class="card-list-title"><?= htmlspecialchars(app_text($hx['reason_location'] ?? '')) ?></div>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= date('d/m/Y', strtotime($hx['created_at'])) ?> &middot; EVA <?= $hx['eva_score'] ?>/10</div>
                        <?php if(!empty($hx['linked_plan_id'])): ?>
                        <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--primary-dark);font-weight:600;">
                            Plan asociado: <?= htmlspecialchars(app_text($hx['linked_plan_title'] ?? '')) ?>
                            <span style="color:var(--text-muted);font-weight:500;">&middot; <?= (int)($hx['linked_plan_completed_sessions'] ?? 0) ?>/<?= (int)($hx['linked_plan_total_sessions'] ?? 0) ?> sesiones</span>
                        </div>
                        <?php else: ?>
                        <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-muted);">Sin plan enlazado todavia</div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <?php if(!empty($hx['linked_plan_id'])): ?>
                        <a href="paciente_progreso.php?patient_id=<?= $patientId ?>&plan_id=<?= (int)$hx['linked_plan_id'] ?>" class="btn-action-sm btn-outline" style="height:28px; font-size:0.7rem;white-space:nowrap;">Ver Plan</a>
                        <?php else: ?>
                        <button onclick='openProtocolModal(<?= (int)$hx['id'] ?>, <?= htmlspecialchars(json_encode(app_text($hx['reason_location'] ?? '')), ENT_QUOTES, "UTF-8") ?>)' class="btn-action-sm btn-primary" style="height:28px; font-size:0.7rem;white-space:nowrap;">Asignar Plan</button>
                        <?php endif; ?>
                        <button onclick='viewHistory(<?= json_encode($hx) ?>)' class="btn-action-sm btn-outline" style="height:28px; font-size:0.7rem;">Ver Detalles</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center" style="font-size:0.8rem; padding:1rem;">Sin historias cl&iacute;nicas registradas.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- SESIONES CLINICAS -->
    <!-- SESIONES CLINICAS (Point 2) -->
    <!-- /SESIONES CLINICAS -->
    <div class="card mb-4" id="sessions-notes-card" style="border-left: 4px solid var(--primary-color);">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.1rem;">description</span>
                Sesiones y Notas
            </h2>
            <?php if($canNote && $userRole !== 'patient'): ?>
            <button onclick="document.getElementById('addNoteForm').classList.toggle('hidden-form')"
                class="btn-action-sm btn-success" style="height:28px;">
                <span class="material-icons-outlined" style="font-size:1rem;">add</span>Nota
            </button>
            <?php endif; ?>
        </div>

        <?php if($canNote): ?>
        <div id="addNoteForm" class="hidden-form" style="padding:1rem;background:var(--primary-light);border-bottom:1px solid var(--border-color);">
            <form id="formSessionNote" onsubmit="saveNote(event)">
                <input type="hidden" id="note_patient_id" value="<?= $patientId ?>">
                <div class="form-group">
                    <label>T&iacute;tulo / Motivo</label>
                    <input type="text" id="note_title" class="form-control" placeholder="Ej: Control Postura" required>
                </div>
                <div class="form-group">
                    <label>Observaciones terap&eacute;uticas</label>
                    <textarea id="note_text" class="form-control" rows="3" placeholder="Describe los hallazgos y avances..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" id="note_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <button type="submit" class="btn-primary" id="btnSaveNote">Guardar Nota</button>
            </form>
        </div>
        <?php endif; ?>

        <div style="padding:1rem;">
            <?php if(count($sessionNotes) > 0): ?>
                <?php foreach($sessionNotes as $note): ?>
                <div id="note-<?= $note['id'] ?>" style="padding:0.75rem 0; border-bottom:1px solid var(--border-color);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.25rem;">
                        <span style="font-weight:700; font-size:0.9rem; color:var(--primary-dark);"><?= htmlspecialchars($note['title']) ?></span>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:0.7rem; color:var(--text-muted);"><?= date('d/m/Y', strtotime($note['session_date'])) ?></span>
                            <?php if($canNote): ?>
                            <button onclick="deleteNote(<?= $note['id'] ?>)" style="background:none; border:none; cursor:pointer; color:var(--text-muted);">
                                <span class="material-icons-outlined" style="font-size:0.85rem;">delete</span>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if(!empty($plan['history_reason_location'])): ?>
                        <div style="font-size:0.75rem;color:var(--primary-dark);margin-top:0.35rem;font-weight:600;">
                            Vinculado a historia: <?= htmlspecialchars($plan['history_reason_location']) ?>
                            <span style="color:var(--text-muted);font-weight:500;"> &middot; <?= date('d/m/y', strtotime($plan['history_created_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:0.85rem; line-height:1.4; color:var(--text-main);"><?= nl2br(htmlspecialchars($note['notes'])) ?></div>
                    <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.4rem;">Registrado por: <?= htmlspecialchars($note['therapist_name']) ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center" style="font-size:0.8rem; padding:1rem;">No hay sesiones registradas a&uacute;n.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- GALERIA -->
    <!-- GALERIA (Point 4) -->
    <!-- /GALERIA -->
    <div class="card mb-4" id="gallery-evolution-card">
        <div class="card-header">
            <h2 class="card-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.1rem;">photo_library</span> Galer&iacute;a de Evoluci&oacute;n</h2>
            <?php if($canNote && $userRole !== 'patient'): ?>
            <button onclick="document.getElementById('addPhotoForm').classList.toggle('hidden-form')"
                class="btn-action-sm btn-success" style="height:28px;">
                <span class="material-icons-outlined" style="font-size:0.9rem;">add_a_photo</span>Subir
            </button>
            <?php endif; ?>
        </div>

        <div id="addPhotoForm" class="hidden-form" style="padding:1rem;background:var(--primary-light);border-bottom:1px solid var(--border-color);">
             <form id="formPhoto" onsubmit="uploadPhoto(event)" enctype="multipart/form-data">
                <input type="hidden" name="patient_id" value="<?= $patientId ?>">
                <div class="form-group">
                    <label>T&iacute;tulo de la foto</label>
                    <input type="text" name="title" class="form-control" placeholder="Ej: Postura Inicial">
                </div>
                <div class="form-group">
                    <label>Archivo (Imagen)</label>
                    <input type="file" name="photo" class="form-control" accept="image/*" required>
                </div>
                <button type="submit" class="btn-primary" id="btnUploadPhoto">Guardar Foto</button>
             </form>
        </div>

        <div style="padding:1rem;">
            <?php if(count($photos) > 0): ?>
            <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:0.5rem;">
                <?php foreach($photos as $ph): ?>
                <div style="position:relative;aspect-ratio:1/1;border-radius:var(--radius-sm);overflow:hidden;border:1px solid var(--border-color);">
                    <img src="<?= htmlspecialchars($ph['photo_path']) ?>" style="width:100%;height:100%;object-fit:cover;" onclick="window.open(this.src)">
                    <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.5);color:white;font-size:0.6rem;padding:2px;text-align:center;">
                        <?= htmlspecialchars($ph['title'] ?: date('d/m/y', strtotime($ph['created_at']))) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted text-center" style="font-size:0.8rem;">No hay fotos de evoluci&oacute;n.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- EJERCICIOS RECOMENDADOS (Point 2) -->
    <!-- EJERCICIOS RECOMENDADOS -->
    <!-- /EJERCICIOS RECOMENDADOS -->
    <div class="card mb-4" id="home-exercises-card">
        <div class="card-header">
            <h2 class="card-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.1rem;">fitness_center</span> Ejercicios en Casa</h2>
            <?php if($canNote): ?>
            <button onclick="document.getElementById('addExerciseForm').classList.toggle('hidden-form')"
                class="btn-action-sm btn-success" style="height:28px;">
                <span class="material-icons-outlined" style="font-size:0.95rem;">add</span>Asignar
            </button>
            <?php endif; ?>
        </div>

        <div id="addExerciseForm" class="hidden-form" style="padding:1rem;background:var(--primary-light);">
             <form id="formExercise" onsubmit="saveExercise(event)">
                <input type="hidden" id="exercise_patient_id" value="<?= $patientId ?>">
                <div class="form-group">
                    <label>Nombre del ejercicio</label>
                    <input type="text" id="exercise_title" class="form-control" placeholder="Ej: Estiramiento Lumbar" required>
                </div>
                <div class="form-group">
                    <label>Frecuencia / Repeticiones</label>
                    <input type="text" id="exercise_frequency" class="form-control" placeholder="Ej: 3 series de 10 reps, 2 veces al d&iacute;a">
                </div>
                <input type="hidden" id="exercise_description" value="">
                <button type="submit" class="btn-primary" id="btnSaveExercise">Asignar Ejercicio</button>
             </form>
        </div>

        <div style="padding:1rem;">
            <?php if(count($exercises) > 0): ?>
            <?php foreach($exercises as $ex): ?>
            <div style="padding:0.75rem 0;border-bottom:1px solid var(--border-color);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                    <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($ex['title']) ?></div>
                    <button onclick="deleteExercise(<?= $ex['id'] ?>)" class="text-muted" style="background:none;border:none;cursor:pointer;"><span class="material-icons-outlined" style="font-size:0.9rem;">delete</span></button>
                </div>
                <div style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($ex['frequency']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="text-muted text-center" style="font-size:0.8rem;">No se han asignado ejercicios a&uacute;n.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- PLAN DE TRATAMIENTO (Enhanced) -->
    <!-- PLAN DE TRATAMIENTO -->
    <!-- /PLAN DE TRATAMIENTO -->
    <div class="card mb-4" id="treatment-plan-card" style="border-left: 4px solid #f59e0b;">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:#f59e0b;font-size:1.1rem;">assignment_turned_in</span>
                Plan de Tratamiento
            </h2>
            <?php if(!$plan && in_array($userRole, ['admin', 'therapist', 'receptionist']) && $userRole !== 'patient'): ?>
                <button onclick="openProtocolModal()" class="btn-action-sm btn-primary" style="height:28px;">
                    <span class="material-icons-outlined" style="font-size:0.95rem;">rocket_launch</span> Asignar
                </button>
            <?php endif; ?>
            <?php if($plan && $userRole === 'admin'): ?>
                <button onclick="openProtocolModal(null, '', '', true)" class="btn-action-sm btn-outline" style="height:28px;background:white;color:#f59e0b;border:1px solid #f59e0b;">
                    <span class="material-icons-outlined" style="font-size:0.95rem;">sync_alt</span> Cambiar plan
                </button>
            <?php endif; ?>
        </div>

        <div style="padding:1rem;">
            <?php if($plan): ?>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;gap:1rem;">
                    <div>
                        <div style="font-weight:700;font-size:1.1rem;color:var(--text-main);"><?= htmlspecialchars(app_text($plan['title'] ?? 'Plan General')) ?></div>
                        <div style="font-size:0.8rem;color:var(--text-muted);">
                            Sesi&oacute;n <strong><?= (int)($plan['completed_sessions'] ?? 0) ?></strong> de <?= (int)($plan['total_sessions'] ?? 10) ?> &middot;
                            Iniciado: <?= date('d/m/y', strtotime($plan['start_date'] ?? $patient['created_at'])) ?>
                        </div>
                        <?php if(!empty($plan['history_reason_location'])): ?>
                        <div style="font-size:0.75rem;color:var(--primary-dark);margin-top:0.35rem;font-weight:600;">
                            Vinculado a historia: <?= htmlspecialchars(app_text($plan['history_reason_location'] ?? '')) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <a href="paciente_progreso.php?patient_id=<?= $patientId ?>&plan_id=<?= (int)$plan['id'] ?>" class="btn-action-sm btn-outline" style="white-space:nowrap;padding:0.3rem 0.6rem;background:white;color:var(--primary-color);border:1px solid var(--primary-color);">
                        <span class="material-icons-outlined" style="font-size:1rem;vertical-align:middle;">timeline</span> Ver Seguimiento
                    </a>
                </div>

                <!-- Barra de progreso SENSATA (por sesiones) -->
                <?php 
                    $totalSess = (int)($plan['total_sessions'] ?? 0);
                    $compSess  = (int)($plan['completed_sessions'] ?? 0);
                    $pct = ($totalSess > 0) ? round(($compSess / $totalSess) * 100) : 0;
                    $pct = min(100, $pct);
                ?>
                <div style="height:10px;background:var(--border-color);border-radius:99px;overflow:hidden;margin-bottom:1.5rem;position:relative;">
                    <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg, #f59e0b, #fbbf24);border-radius:99px;transition:width 1s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                </div>

                <!-- Fases del protocolo si existen -->
                <?php 
                    $assignedPhases = [];
                    $protoId = $plan['protocol_id'] ?? null;
                    if ($protoId) {
                        try {
                            $assignedPhases = pdoQuery($pdo, "
                                SELECT
                                    pp.*,
                                    COUNT(ps.id) AS actual_sessions,
                                    SUM(CASE WHEN ps.status = 'completed' THEN 1 ELSE 0 END) AS actual_completed_sessions
                                FROM protocol_phases pp
                                LEFT JOIN patient_sessions ps
                                    ON ps.phase_id = pp.id
                                   AND ps.plan_id = ?
                                WHERE pp.protocol_id = ?
                                GROUP BY pp.id
                                ORDER BY pp.step_order ASC, pp.id ASC
                            ", [(int)$plan['id'], $protoId])->fetchAll();
                        } catch(Throwable $e) {}
                    }
                ?>
                
                <?php if(count($assignedPhases) > 0): ?>
                    <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:1rem;">
                        <?php 
                        $runningSum = 0;
                        foreach($assignedPhases as $ph): 
                            $phaseSize = (int)($ph['actual_sessions'] ?? 0);
                            if ($phaseSize <= 0) {
                                $phaseSize = max(0, (int)($ph['sessions_count'] ?? 0));
                            }
                            $startFrom = $runningSum + 1;
                            $endAt = $runningSum + $phaseSize;
                            $runningSum += $phaseSize;
                            
                            $isCompleted = $phaseSize > 0 && (int)$plan['completed_sessions'] >= $endAt;
                            $isActive = $phaseSize > 0 && (int)$plan['completed_sessions'] >= $startFrom && (int)$plan['completed_sessions'] < $endAt;
                            
                            $bg = $isCompleted ? '#dcfce7' : ($isActive ? '#fef3c7' : '#f8fafc');
                            $border = $isCompleted ? '#86efac' : ($isActive ? '#fcd34d' : '#e2e8f0');
                            $iconColor = $isCompleted ? '#16a34a' : ($isActive ? '#d97706' : '#94a3b8');
                        ?>
                        <div style="display:flex; align-items:center; gap:0.75rem; padding:0.6rem 0.8rem; border-radius:var(--radius-md); background:<?= $bg ?>; border:1px solid <?= $border ?>;">
                            <span class="material-icons-outlined" style="color:<?= $iconColor ?>; font-size:1.2rem;">
                                <?= $isCompleted ? 'check_circle' : ($isActive ? 'play_circle' : 'radio_button_unchecked') ?>
                            </span>
                            <div style="flex:1;">
                                <div style="font-size:0.85rem; font-weight:700; color:var(--text-main);"><?= htmlspecialchars(app_text($ph['name'] ?? '')) ?></div>
                                <div style="font-size:0.7rem; color:var(--text-muted); margin-bottom:0.25rem;">Fase de tratamiento &middot; <?= $phaseSize ?> sesiones</div>
                                
                                <?php if(!empty($ph['objectives'])): ?>
                                    <div style="font-size:0.75rem; color:var(--primary-dark); font-weight:600; margin-top:0.25rem;">
                                        <span class="material-icons-outlined" style="font-size:0.8rem; vertical-align:middle;">target</span> Objetivos: <?= htmlspecialchars(app_text($ph['objectives'] ?? '')) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if(!empty($ph['activities'])): ?>
                                    <div style="font-size:0.75rem; color:var(--text-main); margin-top:0.1rem;">
                                        <span class="material-icons-outlined" style="font-size:0.8rem; vertical-align:middle;">handyman</span> M&eacute;todos: <?= htmlspecialchars(app_text($ph['activities'] ?? '')) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if($isActive): ?>
                                <span style="font-size:0.65rem; font-weight:800; background:#d97706; color:white; padding:2px 6px; border-radius:4px; text-transform:uppercase;">En curso</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align:center;padding:1.5rem;color:var(--text-muted);font-size:0.85rem;background:#fff9f2;border-radius:var(--radius-md);border:1px dashed #f59e0b;">
                    <span class="material-icons-outlined" style="font-size:2.5rem;display:block;margin-bottom:0.5rem;opacity:0.2;color:#f59e0b;">history_edu</span>
                    Este paciente a&uacute;n no tiene un protocolo activo.
                    <?php if(in_array($userRole, ['admin', 'therapist'])): ?>
                        <div style="margin-top:0.5rem;"><button onclick="openProtocolModal()" class="btn-primary" style="padding:0.4rem 0.8rem;width:auto;font-size:0.8rem;">Asignar ahora</button></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- HISTORIAL DE CITAS (todas) -->
    <!-- HISTORIAL DE CITAS -->
    <!-- /HISTORIAL DE CITAS -->
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.1rem;">event_note</span>
                Historial de Citas (<?= count($allApts) ?>)
            </h2>
        </div>
        <div class="list-group">
            <?php foreach($allApts as $apt):
                $isPast = strtotime($apt['appointment_date']) < strtotime('today');
                $statusColor = ['#e0f2fe','#0369a1','Agendada'];
                $appointmentType = app_text($apt['type'] ?? '');
                $appointmentTherapist = app_text($apt['therapist_name'] ?? '');
                switch($apt['status']) {
                    case 'completed': $statusColor = ['#d1fae5','#065f46','Completada']; break;
                    case 'cancelled': $statusColor = ['#fee2e2','#991b1b','Cancelada']; break;
                }
            ?>
            <div class="card-list-row">
                <div class="card-list-content">
                    <div class="card-list-title" style="color:<?= $isPast ? 'var(--text-muted)' : 'var(--text-main)' ?>;">
                        <?= date('d/m/Y', strtotime($apt['appointment_date'])) ?> &middot; <?= date('h:i A', strtotime($apt['start_time'])) ?>
                    </div>
                    <div class="card-list-subtitle">
                        <?= htmlspecialchars($appointmentType) ?> &middot; <?= htmlspecialchars($appointmentTherapist) ?>
                    </div>
                </div>
                <span style="font-size:0.7rem;font-weight:600;background:<?= $statusColor[0] ?>;color:<?= $statusColor[1] ?>;padding:0.2rem 0.55rem;border-radius:99px;white-space:nowrap;">
                    <?= $statusColor[2] ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- HISTORIAL DE PAGOS -->
    <!-- HISTORIAL DE PAGOS -->
    <!-- /HISTORIAL DE PAGOS -->
    <?php if(in_array($userRole, ['admin', 'receptionist']) && count($patientTxns) > 0): ?>
    <div class="card mb-8">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:var(--success);font-size:1.1rem;">receipt_long</span>
                Pagos (<?= count($patientTxns) ?>)
            </h2>
            <span style="font-weight:700;color:var(--success);font-size:0.9rem;">S/ <?= number_format($totalPagado, 2) ?> total</span>
        </div>
        <div class="list-group">
            <?php foreach($patientTxns as $txn): $isPos = $txn['amount'] > 0; ?>
            <div class="list-item" id="pt-txn-<?= $txn['id'] ?>" style="padding:0.75rem 1rem;">
                <div class="list-item-icon" style="width:36px;height:36px;background:<?= $isPos ? '#d1fae5' : '#fef3c7' ?>;color:<?= $isPos ? '#065f46' : '#92400e' ?>;">
                    <span class="material-icons-outlined" style="font-size:1rem;"><?= $isPos ? 'arrow_downward' : 'arrow_upward' ?></span>
                </div>
                <div class="list-item-content">
                    <div class="list-item-title" style="font-size:0.85rem;"><?= htmlspecialchars(app_text($txn['description'])) ?></div>
                    <div class="list-item-subtitle" style="font-size:0.72rem;">
                        <?= date('d/m/Y', strtotime($txn['transaction_date'])) ?>
                        <?php if($txn['payment_method']): ?> - <?= htmlspecialchars(app_text($txn['payment_method'])) ?><?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:0.4rem;">
                    <span style="font-weight:700;font-size:0.9rem;color:<?= $isPos ? 'var(--success)' : 'var(--text-muted)' ?>">
                        <?= $isPos ? '+' : '' ?>S/ <?= number_format(abs($txn['amount']), 2) ?>
                    </span>
                    <?php if($canDeletePayment && in_array($userRole, ['admin', 'receptionist'], true)): ?>
                    <button onclick="deletePtTxn(<?= $txn['id'] ?>)" style="background:none;color:var(--danger);border:none;cursor:pointer;padding:0.1rem;" title="Eliminar">
                        <span class="material-icons-outlined" style="font-size:0.9rem;">delete</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif(in_array($userRole, ['admin', 'receptionist'])): ?>
    <div class="card mb-8" style="text-align:center;padding:1.5rem;color:var(--text-muted);">
        <span class="material-icons-outlined" style="font-size:2rem;display:block;opacity:0.3;">payments</span>
        Sin pagos registrados para este paciente.
    </div>
    <?php endif; ?>

</div>

<style>
.hidden-form { display: none !important; }
</style>

<script>
const profileCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function toggleEditForm() {
    const f = document.getElementById('editForm');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

function toggleEditPatientReferrerFields() {
    const kind = document.getElementById('ep_referrer_kind')?.value || '';
    const patientGroup = document.getElementById('ep_referrer_patient_group');
    const externalGroup = document.getElementById('ep_referrer_external_group');
    const patientSelect = document.getElementById('ep_referrer_patient_id');
    const externalSelect = document.getElementById('ep_referrer_external_id');

    if (patientGroup) patientGroup.style.display = kind === 'patient' ? 'block' : 'none';
    if (externalGroup) externalGroup.style.display = kind === 'referrer' ? 'block' : 'none';

    if (kind !== 'patient' && patientSelect) {
        patientSelect.value = '';
    }
    if (kind !== 'referrer' && externalSelect) {
        externalSelect.value = '';
    }
}

async function savePatient(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSavePatient');
    btn.disabled = true; btn.textContent = 'Guardando...';
    const data = {
        id:    document.getElementById('ep_id').value,
        name:  document.getElementById('ep_name').value,
        dni:   document.getElementById('ep_dni').value,
        email: document.getElementById('ep_email').value,
        birth_date: document.getElementById('ep_birth_date')?.value || '',
        age:   document.getElementById('ep_age').value,
        phone: document.getElementById('ep_phone').value,
    };
    const referrerKind = document.getElementById('ep_referrer_kind')?.value || '';
    const referrerUserId = referrerKind === 'patient'
        ? (document.getElementById('ep_referrer_patient_id')?.value || '')
        : (document.getElementById('ep_referrer_external_id')?.value || '');

    data.dni = String(data.dni || '').replace(/\D+/g, '').slice(0, 8);
    data.phone = String(data.phone || '').replace(/\D+/g, '').slice(0, 9);
    if (!/^\d{8}$/.test(data.dni)) {
        showToast('El DNI debe tener 8 digitos', 'error');
        btn.disabled = false; btn.textContent = 'Guardar Cambios';
        return;
    }
    if (!/^\d{9}$/.test(data.phone)) {
        showToast('El telefono debe tener 9 digitos', 'error');
        btn.disabled = false; btn.textContent = 'Guardar Cambios';
        return;
    }
    if (referrerKind && !referrerUserId) {
        showToast('Selecciona quien refirio al paciente', 'error');
        btn.disabled = false; btn.textContent = 'Guardar Cambios';
        return;
    }
    if (document.getElementById('ep_referrer_kind')) {
        data.referrer_kind = referrerKind;
        data.referrer_user_id = referrerKind ? (parseInt(referrerUserId, 10) || 0) : 0;
    }
    try {
        const res  = await fetch('api/patients.php', {
            method:'PUT',
            headers:{
                'Content-Type':'application/json',
                'X-CSRF-Token': profileCsrfToken
            },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) { showToast('Datos actualizados', 'success'); setTimeout(()=>location.reload(),1200); }
        else showToast('Error al actualizar: ' + json.error, 'error');
    } catch(e) { showToast('Error de conexion','error'); }
    finally { btn.disabled = false; btn.textContent = 'Guardar Cambios'; }
}

document.addEventListener('DOMContentLoaded', () => {
    toggleEditPatientReferrerFields();
});

async function recalculateReferralRewards(retroactive = false) {
    const confirmMessage = retroactive
        ? 'Esto intentarÃ¡ generar comisiones faltantes tambiÃ©n para pagos anteriores al registro del referido. Â¿Deseas continuar?'
        : 'Se recalcularÃ¡n las comisiones faltantes de este paciente. Â¿Deseas continuar?';
    if (!confirm(confirmMessage)) return;

    try {
        const res = await fetch('api/referrals.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': profileCsrfToken
            },
            body: JSON.stringify({
                action: 'recalculate_patient_rewards',
                patient_id: <?= $patientId ?>,
                retroactive: retroactive ? 1 : 0
            })
        });
        const json = await res.json();
        if (json.success) {
            showToast(json.message || 'Comisiones recalculadas', 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(json.error || 'No se pudieron recalcular las comisiones', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function savePackage(e) {
    e.preventDefault();
    if (window.canAssignPackage === false) {
        showToast('Ya existe un paquete activo para este paciente', 'error');
        return;
    }
    const btn = document.getElementById('btnSavePackage');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const totalAmount = parseFloat(document.getElementById('pkg_total_amount').value || '0');
    const amountPaid = parseFloat(document.getElementById('pkg_amount_paid').value || '0');
    const totalSessions = parseInt(document.getElementById('pkg_sessions').value || '0', 10);

    if (totalSessions <= 0) {
        showToast('Ingresa un numero valido de sesiones', 'error');
        btn.disabled = false;
        btn.textContent = 'Guardar Paquete';
        return;
    }

    if (amountPaid > totalAmount && totalAmount > 0) {
        showToast('El abono no puede ser mayor al monto total', 'error');
        btn.disabled = false;
        btn.textContent = 'Guardar Paquete';
        return;
    }

    const data = {
        patient_id: <?= $patientId ?>,
        template_id: document.getElementById('pkg_template_id')?.value || '',
        name: document.getElementById('pkg_name').value.trim(),
        total_sessions: totalSessions,
        total_amount: totalAmount,
        amount_paid: amountPaid,
        purchase_date: document.getElementById('pkg_purchase_date').value,
        payment_method: document.getElementById('pkg_payment_method').value
    };

    try {
        const res = await fetch('api/packages.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': profileCsrfToken},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            showToast('Paquete registrado', 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast('No se pudo guardar el paquete: ' + json.error, 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar Paquete';
    }
}

const packageTemplatesCatalog = <?= json_encode(array_values(array_map(function($t){ return ['id'=>(int)$t['id'], 'name'=>(string)$t['name'], 'total_sessions'=>(int)$t['total_sessions'], 'total_amount'=>(float)$t['total_amount']]; }, $packageTemplates)), JSON_UNESCAPED_UNICODE) ?>;
let assignPaymentContext = { packageId: 0, pending: 0, candidates: [] };
let changePackageContext = { packageId: 0 };

async function assignExistingPaymentToPackage(packageId, pendingAmount) {
    const pending = parseFloat(pendingAmount || 0);
    if (!packageId || !(pending > 0)) {
        showToast('Este paquete ya no tiene saldo pendiente', 'error');
        return;
    }

    try {
        const res = await fetch('api/payments.php?patient_id=<?= $patientId ?>');
        const json = await res.json();
        const transactions = Array.isArray(json.transactions) ? json.transactions : [];
        const candidates = transactions
            .filter(t => parseFloat(t.amount || 0) > 0 && !t.linked_package_id)
            .slice(0, 50);

        if (candidates.length === 0) {
            showToast('No hay pagos disponibles para asignar', 'error');
            return;
        }

        assignPaymentContext = { packageId, pending, candidates };
        const select = document.getElementById('aep_transaction_id');
        const maxInfo = document.getElementById('aep_max_info');
        if (!select || !maxInfo) return;

        select.innerHTML = '<option value="">Selecciona un pago</option>';
        candidates.forEach(t => {
            const amount = parseFloat(t.amount || 0);
            const date = String(t.transaction_date || '').slice(0, 10);
            const desc = String(t.description || 'Pago').slice(0, 65);
            select.innerHTML += `<option value="${t.id}" data-amount="${amount.toFixed(2)}">#${t.id} Â· S/ ${amount.toFixed(2)} Â· ${date} Â· ${desc}</option>`;
        });

        maxInfo.textContent = `Saldo pendiente del paquete: S/ ${pending.toFixed(2)}`;
        document.getElementById('aep_apply_amount').value = '';
        openModal('modalAssignExistingPayment');
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

function updateAssignPaymentAmountLimit() {
    const select = document.getElementById('aep_transaction_id');
    const amountInput = document.getElementById('aep_apply_amount');
    const maxInfo = document.getElementById('aep_max_info');
    if (!select || !amountInput || !maxInfo) return;

    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) {
        amountInput.value = '';
        amountInput.max = '';
        return;
    }
    const txnAmount = parseFloat(opt.dataset.amount || '0');
    const maxAllowed = Math.min(txnAmount, assignPaymentContext.pending || 0);
    amountInput.max = maxAllowed.toFixed(2);
    amountInput.value = maxAllowed.toFixed(2);
    maxInfo.textContent = `Maximo a aplicar: S/ ${maxAllowed.toFixed(2)} (Pago S/ ${txnAmount.toFixed(2)})`;
}

async function submitAssignExistingPayment(e) {
    e.preventDefault();
    const txId = parseInt(document.getElementById('aep_transaction_id')?.value || '0', 10);
    const amount = parseFloat(document.getElementById('aep_apply_amount')?.value || '0');
    if (!assignPaymentContext.packageId || !txId || !(amount > 0)) {
        showToast('Completa los datos del pago', 'error');
        return;
    }
    const opt = document.getElementById('aep_transaction_id')?.selectedOptions?.[0];
    const txAmount = parseFloat(opt?.dataset?.amount || '0');
    const maxAllowed = Math.min(txAmount, assignPaymentContext.pending || 0);
    if (amount > maxAllowed + 0.0001) {
        showToast('El monto excede lo permitido para este pago', 'error');
        return;
    }

    try {
        const res = await fetch('api/payments.php', {
            method: 'PUT',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': profileCsrfToken},
            body: JSON.stringify({
                action: 'assign_existing_to_package',
                transaction_id: txId,
                package_id: assignPaymentContext.packageId,
                apply_amount: amount
            })
        });
        const json = await res.json();
        if (json.success) {
            closeModal('modalAssignExistingPayment');
            showToast('Pago asignado al paquete', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(json.error || 'No se pudo asignar el pago', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

function changePackageTemplate(packageId) {
    if (!packageId) return;
    if (!Array.isArray(packageTemplatesCatalog) || packageTemplatesCatalog.length === 0) {
        showToast('No hay paquetes base disponibles', 'error');
        return;
    }

    changePackageContext.packageId = packageId;
    const select = document.getElementById('cpt_template_id');
    const hint = document.getElementById('cpt_template_hint');
    if (!select || !hint) return;

    select.innerHTML = '<option value="">Selecciona paquete base</option>';
    packageTemplatesCatalog.forEach(t => {
        select.innerHTML += `<option value="${t.id}" data-sessions="${t.total_sessions}" data-amount="${Number(t.total_amount).toFixed(2)}">${t.name} - ${t.total_sessions} sesiones - S/ ${Number(t.total_amount).toFixed(2)}</option>`;
    });
    hint.textContent = '';
    openModal('modalChangePackageTemplate');
}

function updateChangePackageHint() {
    const select = document.getElementById('cpt_template_id');
    const hint = document.getElementById('cpt_template_hint');
    if (!select || !hint) return;
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) {
        hint.textContent = '';
        return;
    }
    hint.textContent = `${opt.dataset.sessions || 0} sesiones Â· S/ ${opt.dataset.amount || '0.00'}`;
}

async function submitChangePackageTemplate(e) {
    e.preventDefault();
    const templateId = parseInt(document.getElementById('cpt_template_id')?.value || '0', 10);
    if (!changePackageContext.packageId || !templateId) {
        showToast('Selecciona un paquete base', 'error');
        return;
    }

    try {
        const res = await fetch('api/packages.php', {
            method: 'PUT',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': profileCsrfToken},
            body: JSON.stringify({
                action: 'change_template',
                id: changePackageContext.packageId,
                template_id: templateId
            })
        });
        const json = await res.json();
        if (json.success) {
            closeModal('modalChangePackageTemplate');
            showToast('Paquete cambiado correctamente', 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(json.error || 'No se pudo cambiar el paquete', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

function togglePackageAssignmentForm() {
    if (window.canAssignPackage === false) {
        showToast('No puedes asignar otro paquete mientras exista uno activo', 'error');
        return;
    }
    const form = document.getElementById('addPackageForm');
    if (!form) return;
    form.classList.toggle('hidden-form');
    if (!form.classList.contains('hidden-form')) {
        updatePackageTemplatePreview();
    }
}

function updatePackageTemplatePreview() {
    const select = document.getElementById('pkg_template_id');
    if (!select) return;
    const option = select.options[select.selectedIndex];
    const nameInput = document.getElementById('pkg_name');
    const sessionsInput = document.getElementById('pkg_sessions');
    const amountInput = document.getElementById('pkg_total_amount');
    if (!option || !option.value) {
        if (nameInput) nameInput.value = '';
        if (sessionsInput) sessionsInput.value = 0;
        if (amountInput) amountInput.value = 0;
        return;
    }
    if (nameInput) nameInput.value = option.dataset.name || '';
    if (sessionsInput) sessionsInput.value = option.dataset.sessions || 0;
    if (amountInput) amountInput.value = option.dataset.amount || 0;
}

function setupPatientEditValidation() {
    const dniInput = document.getElementById('ep_dni');
    const phoneInput = document.getElementById('ep_phone');
    const ageInput = document.getElementById('ep_age');

    if (dniInput) {
        dniInput.addEventListener('input', () => {
            dniInput.value = String(dniInput.value || '').replace(/\D+/g, '').slice(0, 8);
        });
    }

    if (phoneInput) {
        phoneInput.inputMode = 'numeric';
        phoneInput.maxLength = 9;
        phoneInput.addEventListener('input', () => {
            phoneInput.value = String(phoneInput.value || '').replace(/\D+/g, '').slice(0, 9);
        });
    }

    if (ageInput && !document.getElementById('ep_birth_date')) {
        const ageGroup = ageInput.closest('.form-group');
        if (ageGroup && ageGroup.parentElement) {
            const birthGroup = document.createElement('div');
            birthGroup.className = 'form-group';
            birthGroup.innerHTML = '<label>Fecha de nacimiento</label><input type="date" id="ep_birth_date" class="form-control" value="<?= htmlspecialchars($patient['birth_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">';
            ageGroup.parentElement.insertBefore(birthGroup, ageGroup);
        }
    }

    const birthInput = document.getElementById('ep_birth_date');
    if (birthInput && ageInput) {
        const sync = () => {
            if (!birthInput.value) {
                ageInput.readOnly = false;
                return;
            }
            const birthDate = new Date(birthInput.value + 'T00:00:00');
            if (Number.isNaN(birthDate.getTime())) return;
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            const dayDiff = today.getDate() - birthDate.getDate();
            if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) age -= 1;
            ageInput.value = age >= 0 ? String(age) : '';
            ageInput.readOnly = true;
        };
        birthInput.addEventListener('change', sync);
        sync();
    }
}

async function resetPatientPassword() {
    if (!confirm('Se reseteara la clave del paciente a su DNI y se le pedira cambiarla en el siguiente ingreso. Continuar?')) return;
    try {
        const res = await fetch('api/change_password.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                user_id: <?= $patientId ?>,
                reset_to_dni: true
            })
        });
        const json = await res.json();
        if (json.success) {
            showToast('Clave reseteada al DNI del paciente', 'success');
        } else {
            showToast('No se pudo resetear la clave: ' + json.error, 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setupPatientEditValidation();
});

async function saveNote(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveNote');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const data = {
        patient_id: document.getElementById('note_patient_id').value,
        title: document.getElementById('note_title').value,
        notes: document.getElementById('note_text').value,
        session_date: document.getElementById('note_date').value,
    };

    try {
        const res = await fetch('api/sessions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();

        if (json.success) {
            showToast('Nota guardada', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(json.error || 'No se pudo guardar la nota', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar Nota';
    }
}

async function deleteNote(id) {
    if (!confirm('Eliminar esta nota?')) return;

    try {
        const res = await fetch('api/sessions.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const json = await res.json();

        if (json.success) {
            showToast('Nota eliminada', 'success');
            document.getElementById('note-' + id)?.remove();
        } else {
            showToast(json.error || 'No se pudo eliminar la nota', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function uploadPhoto(e) {
    e.preventDefault();
    const btn = document.getElementById('btnUploadPhoto');
    btn.disabled = true;
    btn.textContent = 'Subiendo...';
    try {
        const formData = new FormData(document.getElementById('formPhoto'));
        const res = await fetch('api/photos.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            showToast('Foto subida', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(json.error || 'No se pudo subir la foto', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Subir Foto';
    }
}

async function saveExercise(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveExercise');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const data = {
        patient_id: document.getElementById('exercise_patient_id').value,
        title: document.getElementById('exercise_title').value,
        description: document.getElementById('exercise_description').value,
        frequency: document.getElementById('exercise_frequency').value,
    };

    try {
        const res = await fetch('api/exercises.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': profileCsrfToken
            },
            body: JSON.stringify(data)
        });
        const json = await res.json();

        if (json.success) {
            showToast('Ejercicio asignado', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(json.error || 'No se pudo asignar el ejercicio', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Asignar Ejercicio';
    }
}

async function deleteExercise(id) {
    if (!confirm('Quitar este ejercicio?')) return;

    try {
        const res = await fetch('api/exercises.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': profileCsrfToken
            },
            body: JSON.stringify({ id })
        });
        const json = await res.json();

        if (json.success) {
            showToast('Ejercicio quitado', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(json.error || 'No se pudo quitar el ejercicio', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}
</script>

    <script>
    async function openProtocolModal(historyId = null, historyLabel = '', preselectedProtocolId = '') {
        const res = await fetch('api/protocols.php');
        const protocols = await res.json();
        const select = document.getElementById('ap_protocol_id');
        const historyInput = document.getElementById('ap_history_id');
        const subtitle = document.getElementById('assignProtocolSubtitle');
        select.innerHTML = '<option value="">-- Seleccionar protocolo --</option>';
        if (historyInput) historyInput.value = historyId || '';
        if (subtitle) {
            subtitle.textContent = historyId
                ? 'Asignando plan a: ' + historyLabel
                : 'Asignar protocolo general al paciente';
        }
        protocols.forEach(p => {
            select.innerHTML += `<option value="${p.id}">${p.name} (${p.total_sessions} ses.)</option>`;
        });
        select.innerHTML += '<option value="new" style="color:var(--primary-color); font-weight:700;">+ Crear nuevo protocolo...</option>';
        if (preselectedProtocolId) {
            select.value = String(preselectedProtocolId);
        }
        restoreClinicalHxDraft(preselectedProtocolId);
        
        select.onchange = function() {
            if (this.value === 'new') {
                if (confirm('Se abrira una pantalla para crear un protocolo nuevo. Se guardara el borrador actual. Continuar?')) {
                    saveClinicalHxDraft({ protocol_id: '' });
                    try {
                        sessionStorage.setItem('pendingProtocolAssignTo', '<?= $patientId ?>');
                    } catch (e) {}
                    window.location.href = 'admin_protocols.php?assign_to=<?= $patientId ?>';
                } else {
                    this.value = '';
                }
            }
        };
        openModal('modalAssignProtocol');
    }

    // Auto-abrir historia si se viene de registro
document.addEventListener('DOMContentLoaded', () => {
    const profileContainer = document.querySelector('.animate-fade-in.delay-100');
    const historiesCard = document.getElementById('clinical-histories');
    const treatmentPlanCard = document.getElementById('treatment-plan-card');
    const galleryCard = document.getElementById('gallery-evolution-card');

    if (profileContainer && historiesCard && treatmentPlanCard) {
        profileContainer.insertBefore(treatmentPlanCard, historiesCard.nextElementSibling);
    }

    if (profileContainer && galleryCard) {
        profileContainer.appendChild(galleryCard);
    }

    const urlParams = new URLSearchParams(window.location.search);
    try {
        const pendingAssignTo = sessionStorage.getItem('pendingProtocolAssignTo');
        if (pendingAssignTo === '<?= $patientId ?>' && (urlParams.get('open_assign_protocol') === '1' || !urlParams.get('assign_to'))) {
            sessionStorage.removeItem('pendingProtocolAssignTo');
        }
    } catch (e) {}

    let shouldOpenClinicalHx = urlParams.get('new_hx') === '1';
    try {
        shouldOpenClinicalHx = shouldOpenClinicalHx || sessionStorage.getItem('openClinicalHxFor') === '<?= $patientId ?>';
        if (shouldOpenClinicalHx) {
            sessionStorage.removeItem('openClinicalHxFor');
        }
    } catch (e) {}

    if (shouldOpenClinicalHx) {
        try {
            sessionStorage.removeItem('pendingNewHxRedirect');
        } catch (e) {}
        openClinicalModal(urlParams.get('protocol_id') || '');
        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('new_hx');
        cleanUrl.searchParams.delete('protocol_id');
        window.history.replaceState({}, document.title, cleanUrl.pathname + cleanUrl.search);
    }
    if (urlParams.get('open_assign_protocol') === '1') {
        openProtocolModal(null, '', urlParams.get('protocol_id') || '');
        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('open_assign_protocol');
        cleanUrl.searchParams.delete('protocol_id');
        window.history.replaceState({}, document.title, cleanUrl.pathname + cleanUrl.search);
    }
    if (urlParams.get('open_package') === '1') {
        const packageForm = document.getElementById('addPackageForm');
        const packageCard = document.getElementById('packages-card');
        if (packageForm) {
            packageForm.classList.remove('hidden-form');
        }
        if (packageCard) {
            packageCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('open_package');
        window.history.replaceState({}, document.title, cleanUrl.pathname + cleanUrl.search);
    }
});

    function reloadPatientProfileClean() {
        window.location.href = 'patient_profile.php?id=<?= $patientId ?>';
    }

    function getClinicalDraftKey() {
        return 'clinicalHxDraft:<?= $patientId ?>';
    }

    function getClinicalHxFormData() {
        return {
            reason_location: document.getElementById('hx_location')?.value || '',
            evolution_time: document.querySelector('input[name="hx_time"]:checked')?.value || '',
            medical_diagnosis: document.getElementById('hx_dx')?.value || '',
            eva_score: document.getElementById('hx_eva')?.value || '5',
            pain_type: document.querySelector('input[name="hx_pain"]:checked')?.value || '',
            worsens_with: Array.from(document.querySelectorAll('input[name="hx_worsen"]:checked')).map(cb => cb.value),
            mobility: document.querySelector('input[name="hx_mobility"]:checked')?.value || '',
            strength: document.querySelector('input[name="hx_strength"]:checked')?.value || '',
            inflammation: !!document.querySelector('input[name="hx_inflam"]:checked'),
            functional_test: document.getElementById('hx_test')?.value || '',
            main_objective: Array.from(document.querySelectorAll('input[name="hx_obj"]:checked')).map(cb => cb.value),
            indicated_sessions: document.getElementById('hx_sessions')?.value || '10',
            frequency: document.querySelector('input[name="hx_freq"]:checked')?.value || '',
            initial_treatment: Array.from(document.querySelectorAll('input[name="hx_tx"]:checked')).map(cb => cb.value),
            observations: document.getElementById('hx_obs')?.value || '',
            protocol_id: document.getElementById('hx_protocol')?.value || ''
        };
    }

    function saveClinicalHxDraft(extra = {}) {
        try {
            sessionStorage.setItem(getClinicalDraftKey(), JSON.stringify(Object.assign(getClinicalHxFormData(), extra)));
        } catch (e) {}
    }

    function restoreClinicalHxDraft(preselectedProtocolId = '') {
        try {
            const raw = sessionStorage.getItem(getClinicalDraftKey());
            if (!raw) return;
            const draft = JSON.parse(raw);

            if (document.getElementById('hx_location')) document.getElementById('hx_location').value = draft.reason_location || '';
            if (document.getElementById('hx_dx')) document.getElementById('hx_dx').value = draft.medical_diagnosis || '';
            if (document.getElementById('hx_eva')) document.getElementById('hx_eva').value = draft.eva_score || '5';
            if (document.getElementById('hx_test')) document.getElementById('hx_test').value = draft.functional_test || '';
            if (document.getElementById('hx_sessions')) document.getElementById('hx_sessions').value = draft.indicated_sessions || '10';
            if (document.getElementById('hx_obs')) document.getElementById('hx_obs').value = draft.observations || '';

            document.querySelectorAll('input[name="hx_time"]').forEach(input => input.checked = input.value === (draft.evolution_time || ''));
            document.querySelectorAll('input[name="hx_pain"]').forEach(input => input.checked = input.value === (draft.pain_type || ''));
            document.querySelectorAll('input[name="hx_mobility"]').forEach(input => input.checked = input.value === (draft.mobility || ''));
            document.querySelectorAll('input[name="hx_strength"]').forEach(input => input.checked = input.value === (draft.strength || ''));
            document.querySelectorAll('input[name="hx_freq"]').forEach(input => input.checked = input.value === (draft.frequency || ''));
            document.querySelectorAll('input[name="hx_worsen"]').forEach(input => input.checked = Array.isArray(draft.worsens_with) && draft.worsens_with.includes(input.value));
            document.querySelectorAll('input[name="hx_obj"]').forEach(input => input.checked = Array.isArray(draft.main_objective) && draft.main_objective.includes(input.value));
            document.querySelectorAll('input[name="hx_tx"]').forEach(input => input.checked = Array.isArray(draft.initial_treatment) && draft.initial_treatment.includes(input.value));

            const inflamInput = document.querySelector('input[name="hx_inflam"]');
            if (inflamInput) inflamInput.checked = !!draft.inflammation;

            const protocolSelect = document.getElementById('hx_protocol');
            const protocolValue = preselectedProtocolId || draft.protocol_id || '';
            if (protocolSelect && protocolValue) {
                protocolSelect.value = String(protocolValue);
            }
        } catch (e) {}
    }

    function clearClinicalHxDraft() {
        try {
            sessionStorage.removeItem(getClinicalDraftKey());
        } catch (e) {}
    }

    async function openClinicalModal(preselectedProtocolId = '') {
        const select = document.getElementById('hx_protocol');
        if (!select) {
            showToast('No se encontro el formulario de historia clinica', 'error');
            return;
        }

        select.innerHTML = '<option value="">-- No asignar protocolo aun --</option>';
        try {
            const res = await fetch('api/protocols.php');
            const protocols = await res.json();
            if (Array.isArray(protocols)) {
                protocols.forEach(p => {
                    select.innerHTML += `<option value="${p.id}">${p.name}</option>`;
                });
            }
        } catch (e) {
            showToast('Se abrio la historia clinica, pero no se pudieron cargar los protocolos', 'warning');
        }

        select.innerHTML += '<option value="new" style="color:var(--primary-color); font-weight:700;">+ Crear nuevo protocolo...</option>';
        if (preselectedProtocolId) {
            select.value = String(preselectedProtocolId);
        }
        restoreClinicalHxDraft(preselectedProtocolId);
        
        select.onchange = function() {
            if (this.value === 'new') {
                if (confirm('Ser\u00E1s redirigido a la creaci\u00F3n de protocolos. Al finalizar, el nuevo protocolo se asignar\u00E1 a este paciente autom\u00E1ticamente. \u00BFContinuar?')) {
                    saveClinicalHxDraft({ protocol_id: '' });
                    try {
                        sessionStorage.setItem('pendingProtocolAssignTo', '<?= $patientId ?>');
                    } catch (e) {}
                    window.location.href = 'admin_protocols.php?assign_to=<?= $patientId ?>&return_to=clinical';
                } else {
                    this.value = '';
                }
            }
        };
        openModal('modalClinicalHx');
    }

    async function assignProtocol(e) {
        e.preventDefault();
        const protocol_id = document.getElementById('ap_protocol_id').value;
        if (!protocol_id) {
            showToast('Selecciona un protocolo', 'error');
            return;
        }

        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Asignando...';

        try {
            const res = await fetch('api/protocols.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    patient_id: <?= $patientId ?>,
                    protocol_id: protocol_id,
                    clinical_history_id: document.getElementById('ap_history_id')?.value || null
                })
            });
            const json = await res.json();
            if (json.success) {
                showToast(json.message || 'Protocolo asignado correctamente', 'success');
                setTimeout(() => reloadPatientProfileClean(), 1000);
            } else {
                showToast('No se pudo asignar el protocolo: ' + json.error, 'error');
            }
        } catch (error) {
            showToast('Error de conexi\u00F3n', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Asignar al Paciente';
        }
    }

    async function saveClinicalHx(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSaveClinical');
        btn.disabled = true; btn.textContent = 'Guardando...';
        
        const formData = getClinicalHxFormData();
        const data = {
            patient_id: <?= $patientId ?>,
            reason_location: formData.reason_location,
            evolution_time: formData.evolution_time,
            medical_diagnosis: formData.medical_diagnosis,
            eva_score: formData.eva_score || 0,
            pain_type: formData.pain_type,
            worsens_with: formData.worsens_with.join(', '),
            mobility: formData.mobility,
            strength: formData.strength,
            inflammation: formData.inflammation ? 1 : 0,
            functional_test: formData.functional_test,
            main_objective: formData.main_objective.join(', '),
            indicated_sessions: formData.indicated_sessions || 10,
            frequency: formData.frequency,
            initial_treatment: formData.initial_treatment.join(', '),
            observations: formData.observations,
            protocol_id: formData.protocol_id || null
        };

        try {
            const res = await fetch('api/clinical_histories.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': profileCsrfToken
                },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (json.success) {
                clearClinicalHxDraft();
                showToast('Historia guardada y plan asignado', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                saveClinicalHxDraft();
                showToast('No se pudo guardar la historia: ' + json.error, 'error');
            }
        } catch(e) {
            saveClinicalHxDraft();
            showToast('Error de conexi\u00F3n', 'error');
        }
        finally { btn.disabled = false; btn.textContent = 'Finalizar y Asignar Plan'; }
    }

    function viewHistory(hx) {
        let html = `
            <div style="font-size:0.85rem; line-height:1.6;">
                <p><strong>Motivo:</strong> ${hx.reason_location || 'N/A'}</p>
                <p><strong>Evoluci&oacute;n:</strong> ${hx.evolution_time || 'N/A'}</p>
                <p><strong>Diagn&oacute;stico:</strong> ${hx.medical_diagnosis || 'N/A'}</p>
                <hr style="margin:0.5rem 0; border:0; border-top:1px solid var(--border-color);">
                <p><strong>Dolor (EVA):</strong> ${hx.eva_score}/10 &middot; <strong>Tipo:</strong> ${hx.pain_type || 'N/A'}</p>
                <p><strong>Empeora con:</strong> ${hx.worsens_with || 'N/A'}</p>
                <hr style="margin:0.5rem 0; border:0; border-top:1px solid var(--border-color);">
                <p><strong>Movilidad:</strong> ${hx.mobility || 'N/A'} &middot; <strong>Fuerza:</strong> ${hx.strength || 'N/A'}</p>
                <p><strong>Inflamaci&oacute;n:</strong> ${hx.inflammation == 1 ? 'S&iacute;' : 'No'}</p>
                <hr style="margin:0.5rem 0; border:0; border-top:1px solid var(--border-color);">
                <p><strong>Plan sugerido:</strong> ${hx.indicated_sessions} sesiones (${hx.frequency})</p>
                <p><strong>Plan vinculado:</strong> ${hx.linked_plan_title || 'Sin plan enlazado'}</p>
                <p><strong>T&eacute;cnicas Iniciales:</strong> ${hx.initial_treatment || 'N/A'}</p>
                <p><strong>Observaciones:</strong> ${hx.observations || 'Sin observaciones'}</p>
            </div>
        `;
        document.getElementById('hx_detail_content').innerHTML = html;
        openModal('modalHxDetail');
    }

    let protocolCatalog = [];

    function updateProtocolRecommendedPackageCard() {
        const select = document.getElementById('ap_protocol_id');
        const card = document.getElementById('ap_package_hint');
        const title = document.getElementById('ap_package_hint_title');
        const meta = document.getElementById('ap_package_hint_meta');
        const checkboxWrap = document.getElementById('ap_assign_package_wrap');
        const checkbox = document.getElementById('ap_assign_package');
        if (!select || !card || !title || !meta || !checkboxWrap || !checkbox) return;

        const protocol = protocolCatalog.find(item => String(item.id) === String(select.value));
        const pkg = protocol && protocol.recommended_package ? protocol.recommended_package : null;
        if (!pkg) {
            card.style.display = 'none';
            checkboxWrap.style.display = 'none';
            checkbox.checked = false;
            return;
        }

        title.textContent = pkg.name || 'Paquete sugerido';
        meta.textContent = `${pkg.total_sessions} sesiones \u00B7 S/ ${Number(pkg.total_amount || 0).toFixed(2)}`;
        card.style.display = 'block';
        checkboxWrap.style.display = 'block';
        checkbox.checked = true;
    }

    openProtocolModal = async function(historyId = null, historyLabel = '', preselectedProtocolId = '', forceReplace = false) {
        const res = await fetch('api/protocols.php');
        protocolCatalog = await res.json();
        const select = document.getElementById('ap_protocol_id');
        const historyInput = document.getElementById('ap_history_id');
        const forceInput = document.getElementById('ap_force_replace');
        const subtitle = document.getElementById('assignProtocolSubtitle');
        select.innerHTML = '<option value="">-- Seleccionar protocolo --</option>';
        if (historyInput) historyInput.value = historyId || '';
        if (forceInput) forceInput.value = forceReplace ? '1' : '0';
        if (subtitle) {
            subtitle.textContent = forceReplace
                ? 'Reemplazar plan activo del paciente'
                : (historyId
                ? 'Asignando plan a: ' + historyLabel
                : 'Asignar protocolo general al paciente');
        }
        protocolCatalog.forEach(p => {
            select.innerHTML += `<option value="${p.id}">${p.name} (${p.total_sessions} ses.)</option>`;
        });
        select.innerHTML += '<option value="new" style="color:var(--primary-color); font-weight:700;">+ Crear nuevo protocolo...</option>';
        if (preselectedProtocolId) {
            select.value = String(preselectedProtocolId);
        }

        select.onchange = function() {
            if (this.value === 'new') {
                if (confirm('Ser\u00E1s redirigido a la creaci\u00F3n de protocolos. Al finalizar, el nuevo protocolo volver\u00E1 a este paciente. \u00BFContinuar?')) {
                    saveClinicalHxDraft({ protocol_id: '' });
                    try {
                        sessionStorage.setItem('pendingProtocolAssignTo', '<?= $patientId ?>');
                    } catch (e) {}
                    window.location.href = 'admin_protocols.php?assign_to=<?= $patientId ?>';
                    return;
                }
                this.value = '';
            }
            updateProtocolRecommendedPackageCard();
        };

        updateProtocolRecommendedPackageCard();
        openModal('modalAssignProtocol');
    }

    assignProtocol = async function(e) {
        e.preventDefault();
        const protocolId = document.getElementById('ap_protocol_id').value;
        if (!protocolId) {
            showToast('Selecciona un protocolo', 'error');
            return;
        }

        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Asignando...';

        try {
            const res = await fetch('api/protocols.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': profileCsrfToken
                },
                body: JSON.stringify({
                    patient_id: <?= $patientId ?>,
                    protocol_id: protocolId,
                    clinical_history_id: document.getElementById('ap_history_id')?.value || null,
                    assign_recommended_package: document.getElementById('ap_assign_package')?.checked ? 1 : 0,
                    force_replace_plan: document.getElementById('ap_force_replace')?.value === '1' ? 1 : 0
                })
            });
            const json = await res.json();
            if (json.success) {
                showToast(json.message || 'Protocolo asignado correctamente', 'success');
                setTimeout(() => reloadPatientProfileClean(), 1000);
            } else {
                showToast(json.error || 'No se pudo asignar el protocolo', 'error');
            }
        } catch (error) {
            showToast('Error de conexi\u00F3n', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Asignar al Paciente';
        }
    }
    </script>

    <!-- Modal: Asignar Protocolo -->
    <div class="modal-overlay" id="modalAssignProtocol">
        <div class="modal-sheet">
            <div class="modal-header">
                <h3 class="modal-title">Asignar Protocolo de Tratamiento</h3>
                <button class="modal-close" onclick="closeModal('modalAssignProtocol')"><span class="material-icons-outlined">close</span></button>
            </div>
            <form onsubmit="assignProtocol(event)">
                <input type="hidden" id="ap_history_id" value="">
                <input type="hidden" id="ap_force_replace" value="0">
                <div class="form-group">
                    <div id="assignProtocolSubtitle" style="font-size:0.8rem;color:var(--text-muted);">Asignar protocolo general al paciente</div>
                </div>
                <div class="form-group">
                    <label>Elegir Protocolo Est&aacute;ndar</label>
                    <select id="ap_protocol_id" class="form-control" required></select>
                </div>
                <div id="ap_package_hint" style="display:none;margin-top:0.75rem;padding:0.85rem;border:1px solid rgba(14,165,183,0.14);background:#f0fdfa;border-radius:14px;">
                    <div style="font-size:0.72rem;text-transform:uppercase;font-weight:800;letter-spacing:0.06em;color:#0f766e;">Paquete sugerido</div>
                    <div id="ap_package_hint_title" style="margin-top:0.2rem;font-size:0.92rem;font-weight:700;color:#134e4a;"></div>
                    <div id="ap_package_hint_meta" style="font-size:0.78rem;color:#0f766e;"></div>
                </div>
                <div class="form-group" id="ap_assign_package_wrap" style="display:none;margin-top:0.75rem;">
                    <label style="display:flex;align-items:center;gap:0.55rem;font-weight:600;">
                        <input type="checkbox" id="ap_assign_package" checked>
                        Asignar tambi&eacute;n el paquete sugerido al paciente
                    </label>
                </div>
                <button type="submit" class="btn-primary mt-4">Asignar al Paciente</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="modalAssignExistingPayment">
        <div class="modal-sheet">
            <div class="modal-header">
                <h3 class="modal-title">Asignar Pago Generado</h3>
                <button class="modal-close" onclick="closeModal('modalAssignExistingPayment')"><span class="material-icons-outlined">close</span></button>
            </div>
            <form onsubmit="submitAssignExistingPayment(event)" style="padding:1rem;">
                <div class="form-group">
                    <label>Pago registrado</label>
                    <select id="aep_transaction_id" class="form-control" required onchange="updateAssignPaymentAmountLimit()"></select>
                </div>
                <div class="form-group">
                    <label>Monto a aplicar (S/)</label>
                    <input type="number" id="aep_apply_amount" class="form-control" min="0.01" step="0.01" required>
                </div>
                <div id="aep_max_info" style="font-size:0.8rem;color:var(--text-muted);margin-top:0.4rem;"></div>
                <button type="submit" class="btn-primary mt-4">Aplicar Pago al Paquete</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="modalChangePackageTemplate">
        <div class="modal-sheet">
            <div class="modal-header">
                <h3 class="modal-title">Cambiar Tipo de Paquete</h3>
                <button class="modal-close" onclick="closeModal('modalChangePackageTemplate')"><span class="material-icons-outlined">close</span></button>
            </div>
            <form onsubmit="submitChangePackageTemplate(event)" style="padding:1rem;">
                <div class="form-group">
                    <label>Nuevo paquete base</label>
                    <select id="cpt_template_id" class="form-control" required onchange="updateChangePackageHint()"></select>
                </div>
                <div id="cpt_template_hint" style="font-size:0.8rem;color:var(--text-muted);margin-top:0.4rem;"></div>
                <button type="submit" class="btn-primary mt-4">Guardar Cambio</button>
            </form>
        </div>
    </div>

    <!-- Modal: Historia Cl?nica -->
    <div class="modal-overlay<?= $autoOpenClinicalHx ? ' active' : '' ?>" id="modalClinicalHx">
        <div class="modal-sheet" style="max-width:600px; max-height:90vh; overflow-y:auto;">
            <div class="modal-header">
                <h3 class="modal-title">Historia Cl&iacute;nica</h3>
                <button class="modal-close" onclick="closeModal('modalClinicalHx')"><span class="material-icons-outlined">close</span></button>
            </div>
            <form onsubmit="saveClinicalHx(event)" id="formClinicalHx" style="padding:1rem;">
                <!-- 2. Motivo -->
                <div class="form-section-title" style="font-size:0.8rem; font-weight:800; color:var(--primary-color); margin-bottom:0.5rem; text-transform:uppercase;">1. Motivo de Consulta</div>
                <div class="form-group">
                    <label>Localizaci&oacute;n del dolor/limitaci&oacute;n</label>
                    <input type="text" id="hx_location" class="form-control" placeholder="Ej: Hombro derecho, Lumbar..." required>
                </div>
                <div class="form-group">
                    <label>Tiempo de evoluci&oacute;n</label>
                    <div style="display:flex; gap:1rem; font-size:0.85rem; flex-wrap:wrap;">
                        <label><input type="radio" name="hx_time" value="Agudo" required> Agudo (<2 sem)</label>
                        <label><input type="radio" name="hx_time" value="Subagudo"> Subagudo (2-6 sem)</label>
                        <label><input type="radio" name="hx_time" value="Cr&oacute;nico"> Cr&oacute;nico (>6 sem)</label>
                    </div>
                </div>

                <!-- 3. Diagn?stico -->
                <div class="form-group">
                    <label>Diagn&oacute;stico m&eacute;dico (si tiene)</label>
                    <input type="text" id="hx_dx" class="form-control">
                </div>

                <!-- 4. Dolor -->
                <div class="form-section-title" style="font-size:0.8rem; font-weight:800; color:var(--primary-color); margin:1rem 0 0.5rem; text-transform:uppercase;">2. Evaluaci&oacute;n del Dolor</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>Escala EVA (0-10)</label>
                        <input type="number" id="hx_eva" class="form-control" min="0" max="10" value="5">
                    </div>
                    <div class="form-group">
                        <label>Tipo de dolor</label>
                        <div style="font-size:0.8rem;">
                            <label style="display:block;"><input type="radio" name="hx_pain" value="Mec&aacute;nico" checked> Mec&aacute;nico</label>
                            <label style="display:block;"><input type="radio" name="hx_pain" value="Inflamatorio"> Inflamatorio</label>
                            <label style="display:block;"><input type="radio" name="hx_pain" value="Neurop&aacute;tico"> Neurop&aacute;tico</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Empeora con</label>
                    <div style="display:flex; gap:0.5rem; font-size:0.75rem; flex-wrap:wrap;">
                        <label><input type="checkbox" name="hx_worsen" value="Movimiento"> Movimiento</label>
                        <label><input type="checkbox" name="hx_worsen" value="Carga"> Carga</label>
                        <label><input type="checkbox" name="hx_worsen" value="Reposo"> Reposo</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Prueba funcional principal</label>
                    <input type="text" id="hx_test" class="form-control" placeholder="Ej: Prueba de Thompson, Apley...">
                </div>

                <!-- 5. Evaluaci?n Funcional -->
                <div class="form-section-title" style="font-size:0.8rem; font-weight:800; color:var(--primary-color); margin:1rem 0 0.5rem; text-transform:uppercase;">3. Evaluaci&oacute;n Funcional</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>Movilidad</label>
                        <div style="font-size:0.8rem;">
                            <label style="display:block;"><input type="radio" name="hx_mobility" value="Normal"> Normal</label>
                            <label style="display:block;"><input type="radio" name="hx_mobility" value="Limitada" checked> Limitada</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Fuerza / Inflamaci&oacute;n</label>
                        <div style="font-size:0.8rem;">
                            <label style="display:block;"><input type="radio" name="hx_strength" value="Disminuida" checked> Fuerza Disminuida</label>
                            <label style="display:block;"><input type="checkbox" name="hx_inflam" value="1"> Presenta Inflamaci&oacute;n</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Objetivo principal</label>
                    <div style="display:flex; gap:0.5rem; font-size:0.75rem; flex-wrap:wrap;">
                        <label><input type="checkbox" name="hx_obj" value="Reducir dolor"> Reducir dolor</label>
                        <label><input type="checkbox" name="hx_obj" value="Mejorar movilidad"> Mejorar movilidad</label>
                        <label><input type="checkbox" name="hx_obj" value="Recuperaci&oacute;n funcional"> Recuperaci&oacute;n</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Protocolo de inicio</label>
                    <div style="display:flex; gap:0.5rem; font-size:0.75rem; flex-wrap:wrap;">
                        <label><input type="checkbox" name="hx_tx" value="TENS"> TENS</label>
                        <label><input type="checkbox" name="hx_tx" value="US"> US</label>
                        <label><input type="checkbox" name="hx_tx" value="Termoterapia"> Termo</label>
                    </div>
                </div>

                <!-- 6. Objetivos y Plan -->
                <div class="form-section-title" style="font-size:0.8rem; font-weight:800; color:var(--primary-color); margin:1rem 0 0.5rem; text-transform:uppercase;">4. Plan de Tratamiento</div>
                <div class="form-group">
                    <label>Elegir Protocolo (Asignaci&oacute;n Autom&aacute;tica)</label>
                    <select id="hx_protocol" class="form-control"></select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>Sesiones Indicadas</label>
                        <input type="number" id="hx_sessions" class="form-control" value="10">
                    </div>
                    <div class="form-group">
                        <label>Frecuencia</label>
                        <div style="font-size:0.8rem;">
                            <label><input type="radio" name="hx_freq" value="2/semana" checked> 2/sem</label>
                            <label><input type="radio" name="hx_freq" value="3/semana"> 3/sem</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Observaciones Finales</label>
                    <textarea id="hx_obs" class="form-control" rows="2"></textarea>
                </div>

                <button type="submit" id="btnSaveClinical" class="btn-primary mt-4" style="padding:1rem;">Finalizar y Asignar Plan</button>
            </form>
        </div>
    </div>

    <!-- Modal: Detalle Historia Cl?nica -->
    <div class="modal-overlay" id="modalHxDetail">
        <div class="modal-sheet">
            <div class="modal-header">
                <h3 class="modal-title">Detalle de Historia Cl&iacute;nica</h3>
                <button class="modal-close" onclick="closeModal('modalHxDetail')"><span class="material-icons-outlined">close</span></button>
            </div>
            <div id="hx_detail_content" style="padding:1rem;"></div>
            <div style="padding:1rem; text-align:right;">
                <button type="button" class="btn-secondary" onclick="closeModal('modalHxDetail')">Cerrar</button>
            </div>
        </div>
    </div>

    <?php require_once 'includes/footer.php'; ?>


