<?php
// patient_portal.php - Mi espacio de salud (Point 2)
require_once 'db.php';
ensureReferralSchema($pdo);
$pageTitle = 'Mi Espacio de Salud';
require_once 'includes/header.php';

if ($userRole !== 'patient') {
    header("Location: index.php"); exit;
}

$patientId = $_SESSION['user_id'];

// --- MIGRACIÓN AUTOMÁTICA EN PORTAL (Asegurar tablas) ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS session_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        therapist_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        notes TEXT,
        session_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS exercises (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL DEFAULT 0,
        therapist_id INT NOT NULL DEFAULT 0,
        title VARCHAR(255) NOT NULL DEFAULT '',
        frequency VARCHAR(255) DEFAULT '',
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(Exception $e) {}

// Próximas citas
$nextApts = [];
try {
    $nextApts = pdoQuery($pdo, "
        SELECT a.*, u.name AS therapist_name FROM appointments a
        JOIN users u ON a.therapist_id = u.id
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled'
        ORDER BY a.appointment_date ASC LIMIT 3
    ", [$patientId])->fetchAll();
} catch(Exception $e) {}

// Mis Ejercicios
$exercises = [];
try {
    $exercises = pdoQuery($pdo, "SELECT * FROM exercises WHERE patient_id = ? AND is_active = 1 ORDER BY id DESC", [$patientId])->fetchAll();
} catch(Exception $e) {}

// Mis Sesiones / Notas
$sessionNotes = [];
try {
    $sessionNotes = pdoQuery($pdo, "
        SELECT s.*, u.name AS therapist_name FROM session_notes s
        JOIN users u ON s.therapist_id = u.id
        WHERE s.patient_id = ? ORDER BY s.session_date DESC
    ", [$patientId])->fetchAll();
} catch(Exception $e) {}

// Mis Pagos
$payments = [];
try {
    $payments = pdoQuery($pdo, "SELECT * FROM transactions WHERE patient_id = ? ORDER BY transaction_date DESC", [$patientId])->fetchAll();
} catch(Exception $e) {}

$referralCreditSummary = [
    'total_generated' => 0.0,
    'available_balance' => 0.0,
    'used_balance' => 0.0,
];
$myReferredPatients = [];
try {
    $referralCreditSummary = getReferralCreditSummary($pdo, $patientId);
    $myReferredPatients = pdoQuery(
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
} catch(Exception $e) {}

// Historial completo de citas
$allApts = [];
try {
    $allApts = pdoQuery($pdo, "
        SELECT a.*, u.name AS therapist_name FROM appointments a
        JOIN users u ON a.therapist_id = u.id
        WHERE a.patient_id = ? ORDER BY a.appointment_date DESC, a.start_time DESC
    ", [$patientId])->fetchAll();
} catch(Exception $e) {}

// Mis Fotos de Evolución (Point 4)
$photos = [];
try {
    $photos = pdoQuery($pdo, "SELECT * FROM patient_photos WHERE patient_id = ? ORDER BY created_at DESC", [$patientId])->fetchAll();
} catch(Exception $e) {}
?>

<div class="animate-fade-in delay-100">
    <div style="padding: 1rem; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); color:white; border-radius:var(--radius-lg); margin-bottom: 1.5rem; box-shadow: var(--shadow-md);">
        <h1 style="color:white; margin:0; font-size:1.2rem;">¡Hola, <?= htmlspecialchars($_SESSION['name'] ?? 'Paciente') ?>!</h1>
        <p style="margin:0; font-size:0.8rem; opacity:0.9;">Aquí tienes el resumen de tu tratamiento.</p>
    </div>

    <!-- Próximas Citas -->
    <div class="card mb-4" style="border-left: 4px solid var(--primary-color);">
        <div class="card-header">
            <h2 class="card-title text-sm">Mis Próximas Citas</h2>
        </div>
        <div class="list-group">
            <?php if(count($nextApts) > 0): ?>
                <?php foreach($nextApts as $apt): ?>
                <div class="card-list-row">
                    <div class="card-list-content">
                        <div class="card-list-title"><?= date('d/m/Y', strtotime($apt['appointment_date'])) ?></div>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= date('h:i A', strtotime($apt['start_time'])) ?> · <?= htmlspecialchars($apt['therapist_name']) ?></div>
                    </div>
                    <span class="badge badge-primary">Confirmada</span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted py-4" style="font-size:0.85rem;">No tienes citas programadas.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mis Tareas: Ejercicios -->
    <div class="card mb-4" style="border-left: 4px solid var(--success);">
        <div class="card-header">
            <h2 class="card-title text-sm"><span class="material-icons-outlined" style="vertical-align:middle; color:var(--success);">fitness_center</span> Mis Tareas: Ejercicios</h2>
        </div>
        <div style="padding:1rem;">
            <?php if(count($exercises) > 0): ?>
                <?php foreach($exercises as $ex): ?>
                <div style="background:var(--background); padding:0.75rem; border-radius:var(--radius-md); margin-bottom:0.6rem; border:1px solid var(--border-color);">
                    <div style="font-weight:700; font-size:0.875rem; color:var(--primary-dark);"><?= htmlspecialchars($ex['title']) ?></div>
                    <div style="font-size:0.8rem; margin-top:0.2rem; color:var(--text-muted);"><?= htmlspecialchars($ex['frequency']) ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted py-2" style="font-size:0.8rem;">No hay ejercicios asignados aún.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mis Sesiones Clínicas -->
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title text-sm"><span class="material-icons-outlined" style="vertical-align:middle; color:var(--primary-color);">description</span> Resumen de mis Sesiones</h2>
        </div>
        <div style="padding:1rem;">
            <?php if(count($sessionNotes) > 0): ?>
                <?php foreach($sessionNotes as $note): ?>
                <div style="padding:0.75rem 0; border-bottom:1px solid var(--border-color);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.25rem;">
                        <span style="font-weight:700; font-size:0.85rem;"><?= htmlspecialchars($note['title']) ?></span>
                        <span style="font-size:0.7rem; color:var(--text-muted);"><?= date('d/m/Y', strtotime($note['session_date'])) ?></span>
                    </div>
                    <div style="font-size:0.8rem; line-height:1.4; color:var(--text-main);"><?= nl2br(htmlspecialchars($note['notes'])) ?></div>
                    <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.3rem;">Fisio: <?= htmlspecialchars($note['therapist_name']) ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted py-4" style="font-size:0.8rem;">Aún no hay notas de tus sesiones.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mis Pagos -->
    <div class="card mb-4" style="border-left: 4px solid var(--success);">
        <div class="card-header">
            <h2 class="card-title text-sm"><span class="material-icons-outlined" style="vertical-align:middle; color:var(--success);">payments</span> Mis Pagos realizados</h2>
        </div>
        <div class="list-group">
            <?php if(count($payments) > 0): ?>
                <?php foreach($payments as $p): $isPos = $p['amount'] > 0; ?>
                <div class="card-list-row">
                    <div class="card-list-content">
                        <div class="card-list-title"><?= htmlspecialchars($p['description']) ?></div>
                        <div style="font-size:0.7rem; color:var(--text-muted);"><?= date('d/m/Y', strtotime($p['transaction_date'])) ?> · <?= htmlspecialchars($p['payment_method']) ?></div>
                    </div>
                    <span style="font-weight:700; color:<?= $isPos ? 'var(--success)' : 'var(--text-muted)' ?>;">
                        S/ <?= number_format(abs($p['amount']), 2) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted py-4" style="font-size:0.8rem;">No hay pagos registrados.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4" style="border-left:4px solid #2563eb;">
        <div class="card-header">
            <h2 class="card-title text-sm">
                <span class="material-icons-outlined" style="vertical-align:middle; color:#2563eb;">savings</span>
                Mis Referidos
            </h2>
        </div>
        <div style="padding:1rem;">
            <div style="display:grid;grid-template-columns:repeat(3, minmax(0, 1fr));gap:0.6rem;margin-bottom:1rem;">
                <div style="padding:0.75rem;border:1px solid #dbeafe;border-radius:var(--radius-md);background:#eff6ff;">
                    <div style="font-size:0.68rem;color:#1d4ed8;text-transform:uppercase;font-weight:800;">Disponible</div>
                    <div style="font-size:1rem;font-weight:800;color:#1e3a8a;">S/ <?= number_format((float)$referralCreditSummary['available_balance'], 2) ?></div>
                </div>
                <div style="padding:0.75rem;border:1px solid #d1fae5;border-radius:var(--radius-md);background:#f0fdf4;">
                    <div style="font-size:0.68rem;color:#15803d;text-transform:uppercase;font-weight:800;">Acumulado</div>
                    <div style="font-size:1rem;font-weight:800;color:#166534;">S/ <?= number_format((float)$referralCreditSummary['total_generated'], 2) ?></div>
                </div>
                <div style="padding:0.75rem;border:1px solid #e2e8f0;border-radius:var(--radius-md);background:#f8fafc;">
                    <div style="font-size:0.68rem;color:#475569;text-transform:uppercase;font-weight:800;">Usado</div>
                    <div style="font-size:1rem;font-weight:800;color:#0f172a;">S/ <?= number_format((float)$referralCreditSummary['used_balance'], 2) ?></div>
                </div>
            </div>

            <?php if(count($myReferredPatients) > 0): ?>
                <?php foreach($myReferredPatients as $refPatient): ?>
                <div style="padding:0.75rem 0;border-top:1px solid var(--border-color);">
                    <div style="font-weight:700;font-size:0.85rem;"><?= htmlspecialchars($refPatient['name']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($refPatient['patient_code'] ?: 'Paciente referido') ?></div>
                    <div style="font-size:0.75rem;color:#1d4ed8;font-weight:700;margin-top:0.2rem;">
                        Crédito generado S/ <?= number_format((float)$refPatient['total_generated'], 2) ?>
                        <?php if((float)$refPatient['total_available'] > 0): ?>
                        · Disponible S/ <?= number_format((float)$refPatient['total_available'], 2) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <p class="text-center text-muted py-2" style="font-size:0.8rem;">Cuando refieras pacientes nuevos, tu saldo aparecerá aquí.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mi Historial de Citas -->
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title text-sm"><span class="material-icons-outlined" style="vertical-align:middle; color:var(--primary-color);">event_available</span> Historial de Citas</h2>
        </div>
        <div class="list-group">
            <?php if(count($allApts) > 0): ?>
                <?php foreach($allApts as $apt): 
                    $isPast = strtotime($apt['appointment_date']) < strtotime('today');
                ?>
                <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-weight:600; font-size:0.85rem; color:<?= $isPast ? 'var(--text-muted)' : 'var(--text-main)' ?>;"><?= date('d/m/Y', strtotime($apt['appointment_date'])) ?></div>
                        <div style="font-size:0.7rem; color:var(--text-muted);"><?= date('h:i A', strtotime($apt['start_time'])) ?> · <?= htmlspecialchars($apt['therapist_name']) ?></div>
                    </div>
                    <span style="font-size:0.7rem; font-weight:600; color:var(--text-muted);"><?= ucfirst($apt['status']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mi Galería de Evolución -->
    <div class="card mb-8">
        <div class="card-header">
            <h2 class="card-title text-sm"><span class="material-icons-outlined" style="vertical-align:middle; color:var(--primary-color);">photo_library</span> Mi Galería de Evolución</h2>
        </div>
        <div style="padding:1rem;">
            <?php if(count($photos) > 0): ?>
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:0.5rem;">
                <?php foreach($photos as $ph): ?>
                <div style="aspect-ratio:1/1; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--border-color);">
                    <img src="<?= htmlspecialchars($ph['photo_path']) ?>" style="width:100%; height:100%; object-fit:cover;" onclick="window.open(this.src)">
                    <div style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.5); color:white; font-size:0.5rem; padding:2px; text-align:center;">
                        <?= date('d/m/y', strtotime($ph['created_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-center text-muted py-3" style="font-size:0.8rem;">Pronto verás aquí tus fotos de evolución.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
