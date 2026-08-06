<?php
// patients.php - Lista de Pacientes
require_once 'db.php';
$pageTitle = 'Pacientes';
require_once 'includes/header.php';

$patientProfileVersion = @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'patient_profile.php') ?: time();

// Todos los roles staff pueden ver pacientes
if (!in_array($userRole, ['admin', 'receptionist', 'therapist'])) {
    header("Location: index.php");
    exit;
}

// Obtener lista inicial de pacientes (Resiliente)
$patients = [];
try {
    $stmt = pdoQuery($pdo, "
        SELECT u.id, u.name, u.dni, u.email, u.age, u.patient_code, u.phone,
               a.id AS today_apt_id, a.start_time AS today_apt_time, a.status AS today_apt_status,
               t.name AS therapist_name
        FROM users u
        LEFT JOIN appointments a ON u.id = a.patient_id AND a.appointment_date = CURDATE() AND a.status = 'scheduled'
        LEFT JOIN users t ON a.therapist_id = t.id
        WHERE u.role = 'patient'
        ORDER BY u.id DESC
    ");
    $patients = $stmt->fetchAll();
} catch(Exception $e) {
    try {
        $stmt = pdoQuery($pdo, "SELECT id, name, dni, phone FROM users WHERE role = 'patient' ORDER BY id DESC");
        $patients = $stmt->fetchAll();
    } catch(Exception $e2) {
        $patients = [];
    }
}
?>

<div class="animate-fade-in delay-100">
    <!-- Encabezado -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <div>
            <h1 style="margin:0;">Mis Pacientes</h1>
            <p class="text-xs text-muted" style="margin:0;">Gestiona tus pacientes y su asistencia diaria</p>
        </div>
        <?php if(in_array($userRole, ['admin', 'receptionist'])): ?>
        <button class="btn-primary" style="width:auto; padding:0.6rem 1.25rem; display:flex; gap:0.5rem; align-items:center; border-radius:var(--radius-md);" onclick="openModal('modalNuevoPaciente')">
            <span class="material-icons-outlined" style="font-size:1.2rem;">person_add</span>
            <span style="font-weight:600;">Nuevo</span>
        </button>
        <?php endif; ?>
    </div>

    <!-- Buscador Premium -->
    <div style="margin-bottom:1.5rem;">
        <div style="position:relative; filter: drop-shadow(var(--shadow-sm));">
            <span class="material-icons-outlined" style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--primary-color); pointer-events:none;">search</span>
            <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre o DNI..." 
                   style="padding-left:2.8rem; height:48px; border-radius:var(--radius-lg); border: 2px solid transparent; background: white;" 
                   oninput="filterPatients(this.value)">
        </div>
    </div>

    <!-- Lista Tipo Card Premium -->
    <div id="patientList" style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom: 100px;">
        <?php if(count($patients) > 0): ?>
            <?php foreach($patients as $p): ?>
            <div class="patient-card list-item" 
                 id="patient-row-<?= $p['id'] ?>"
                 data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
                 data-dni="<?= strtolower(htmlspecialchars($p['dni'] ?? '')) ?>">
                
                <div style="display:flex; align-items:center; gap:1rem; cursor:pointer; flex: 1;" onclick="window.location='patient_profile.php?id=<?= $p['id'] ?>&v=<?= (int)$patientProfileVersion ?>'">
                    <div style="width:48px; height:48px; background:var(--primary-light); color:var(--primary-dark); border-radius:12px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.1rem; flex-shrink:0;">
                        <?= app_substr($p['name'], 0, 1) ?>
                    </div>
                    <div style="overflow:hidden;">
                        <div style="font-weight:700; font-size:0.95rem; color:var(--text-main); margin-bottom:2px; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">
                            <?= htmlspecialchars($p['name']) ?>
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:0.5rem;">
                            <span>DNI: <?= htmlspecialchars($p['dni'] ?? '—') ?></span>
                            <?php if($p['phone']): ?>
                            <span style="display:flex; align-items:center; gap:2px;">
                                <span class="material-icons-outlined" style="font-size:0.8rem;">phone</span>
                                <?= htmlspecialchars($p['phone']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:0.6rem;">
                    <!-- Botón WhatsApp -->
                    <?php if($p['phone']): ?>
                    <?php 
                        $waDate = date('d/m');
                        $waTime = $p['today_apt_time'] ? date('h:i A', strtotime($p['today_apt_time'])) : '--:--';
                        $waTherapist = $p['therapist_name'] ?? 'KaminarFisio';
                    ?>
                    <button onclick="event.stopPropagation(); sendReminder('<?= addslashes($p['name']) ?>', '<?= $p['phone'] ?>', '<?= $waDate ?>', '<?= $waTime ?>', '<?= addslashes($waTherapist) ?>')" 
                            class="btn-whatsapp-sm" title="Escribir por WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </button>
                    <?php endif; ?>

                    <a href="patient_profile.php?id=<?= $p['id'] ?>&v=<?= (int)$patientProfileVersion ?>" class="text-muted" style="padding:0.5rem;">
                        <span class="material-icons-outlined" style="font-size:1.2rem; opacity:0.3;">chevron_right</span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; padding:3rem 0;">
                <span class="material-icons-outlined" style="font-size:4rem; color:var(--border-color); opacity:0.5;">people_outline</span>
                <p class="text-muted" style="margin-top:1rem;">No hay pacientes registrados aún.</p>
            </div>
        <?php endif; ?>
    </div>
    <div id="noResultsMsg" style="text-align:center; padding:3rem 0; display:none;">
        <span class="material-icons-outlined" style="font-size:4rem; color:var(--border-color); opacity:0.5;">person_search</span>
        <p class="text-muted" style="margin-top:1rem;">No se encontraron pacientes que coincidan.</p>
    </div>
</div>

<script>
// Filtrado en vivo
function filterPatients(q) {
    const items = document.querySelectorAll('#patientList .list-item');
    const term = q.toLowerCase();
    let visible = 0;
    items.forEach(item => {
        const match = item.dataset.name.includes(term) || item.dataset.dni.includes(term);
        item.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('noResultsMsg').style.display = visible === 0 && q ? 'block' : 'none';
}

// Recargar lista tras agregar paciente
window.reloadPatients = function() {
    window.location.reload();
};
</script>

<?php require_once 'includes/footer.php'; ?>
