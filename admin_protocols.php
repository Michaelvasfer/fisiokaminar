<?php
// admin_protocols.php - ConfiguraciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n de Protocolos de Tratamiento
require_once 'db.php';
ensureProtocolSchema($pdo);
ensurePackagesSchema($pdo);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userRole = $_SESSION['role'] ?? 'patient';

if (!in_array($userRole, ['admin', 'therapist', 'receptionist'], true)) {
    header("Location: index.php");
    exit;
}

// Obtener protocolos existentes
$protocols = [];
$packageTemplates = [];
try {
    $protocols = pdoQuery($pdo, "SELECT * FROM treatment_protocols ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $protocols = [];
}
try {
    $packageTemplates = pdoQuery($pdo, "SELECT id, name, total_sessions, total_amount FROM package_templates WHERE is_active = 1 ORDER BY total_sessions ASC, total_amount ASC, name ASC")->fetchAll();
} catch (Exception $e) {
    $packageTemplates = [];
}

// Si viene para asignar a un paciente especÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­fico
$assignTo = $_GET['assign_to'] ?? null;
$canManageProtocols = ($userRole === 'admin');
$returnTo = $_GET['return_to'] ?? ($_POST['return_to'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proto_name'])) {
    $protoName = trim($_POST['proto_name'] ?? '');
    $protoDesc = trim($_POST['proto_desc'] ?? '');
    $protoTotal = (int)($_POST['proto_total'] ?? 0);
    $recommendedPackageTemplateId = (int)($_POST['recommended_package_template_id'] ?? 0);
    $assignToPost = (int)($_POST['assign_to'] ?? 0);
    $returnToPost = trim($_POST['return_to'] ?? '');
    $phaseNames = $_POST['phase_name'] ?? [];
    $phaseSessions = $_POST['phase_sessions'] ?? [];
    $phaseObjectives = $_POST['phase_objectives'] ?? [];
    $phaseActivities = $_POST['phase_activities'] ?? [];

    if ($protoName !== '' && $protoTotal > 0) {
        try {
            $pdo->beginTransaction();
            pdoQuery($pdo, "INSERT INTO treatment_protocols (name, description, total_sessions, recommended_package_template_id) VALUES (?, ?, ?, ?)", [
                $protoName,
                $protoDesc,
                $protoTotal,
                $recommendedPackageTemplateId > 0 ? $recommendedPackageTemplateId : null
            ]);
            $protoId = (int)$pdo->lastInsertId();

            foreach ($phaseNames as $index => $phaseNameRaw) {
                $phaseName = trim($phaseNameRaw ?? '');
                $sessionsCount = (int)($phaseSessions[$index] ?? 0);
                $objectives = trim($phaseObjectives[$index] ?? '');
                $activities = trim($phaseActivities[$index] ?? '');

                if ($phaseName === '' || $sessionsCount <= 0) {
                    continue;
                }

                try {
                    pdoQuery($pdo, "INSERT INTO protocol_phases (protocol_id, name, sessions_count, step_order, objectives, activities) VALUES (?, ?, ?, ?, ?, ?)", [
                        $protoId,
                        $phaseName,
                        $sessionsCount,
                        $index + 1,
                        $objectives,
                        $activities
                    ]);
                } catch (Exception $phaseInsertError) {
                    pdoQuery($pdo, "INSERT INTO protocol_phases (protocol_id, name, sessions_count, step_order) VALUES (?, ?, ?, ?)", [
                        $protoId,
                        $phaseName,
                        $sessionsCount,
                        $index + 1
                    ]);
                }
            }

            $pdo->commit();

            if ($assignToPost > 0) {
                if ($returnToPost === 'clinical') {
                    header("Location: patient_profile.php?id=" . $assignToPost . "&new_hx=1&protocol_id=" . $protoId);
                    exit;
                }

                header("Location: patient_profile.php?id=" . $assignToPost . "&open_assign_protocol=1&protocol_id=" . $protoId);
                exit;
            }

            header("Location: admin_protocols.php?saved=1");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}

$pageTitle = 'Protocolos de Tratamiento';
require_once 'includes/header.php';
?>
<script>
const assignToPatientId = (() => {
    const serverAssignTo = <?= json_encode($assignTo ? (int)$assignTo : null) ?>;
    if (serverAssignTo) {
        try {
            sessionStorage.setItem('pendingProtocolAssignTo', String(serverAssignTo));
        } catch (e) {}
        return String(serverAssignTo);
    }

    try {
        const storedAssignTo = sessionStorage.getItem('pendingProtocolAssignTo');
        return storedAssignTo && /^\d+$/.test(storedAssignTo) ? storedAssignTo : null;
    } catch (e) {
        return null;
    }
})();
const protocolReturnTo = <?= json_encode($returnTo ?: null) ?>;
</script>

<div class="animate-fade-in delay-100">
    <div class="protocols-toolbar">
        <div>
            <h1 class="protocols-title">Protocolos</h1>
            <p class="protocols-subtitle"><?= count($protocols) ?> protocolos listos para asignar y editar.</p>
        </div>
        <div class="protocols-hero-actions">
            <div class="protocols-stat">
                <span class="protocols-stat-value"><?= count($protocols) ?></span>
                <span class="protocols-stat-label">activos</span>
            </div>
            <button onclick="document.getElementById('newProtocolForm').classList.toggle('hidden-form')" class="protocols-primary-btn">
                <span class="material-icons-outlined" style="font-size:1.05rem;">add</span> Nuevo
            </button>
        </div>
    </div>

    <!-- Formulario Nuevo Protocolo -->
    <div id="newProtocolForm" class="hidden-form card mb-4 protocol-form-shell">
        <div class="card-header">
            <h2 class="card-title" id="form_title">Crear Nuevo Protocolo Estandar</h2>
        </div>
        <form onsubmit="return handleProtocolSubmit(event)" method="POST" class="protocol-form-body">
            <input type="hidden" id="proto_id" value="">
            <input type="hidden" name="assign_to" value="<?= (int)($assignTo ?? 0) ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
            <div class="form-group">
                <label>Nombre del Protocolo (Ej: Dolor de Rodilla)</label>
                <input type="text" id="proto_name" name="proto_name" class="form-control" required placeholder="Ej: Dolor Lumbar Cronico">
            </div>
            <div class="form-group">
                <label>Descripcion</label>
                <textarea id="proto_desc" name="proto_desc" class="form-control" rows="2" placeholder="Breve explicacion del tratamiento"></textarea>
            </div>
            <div class="form-group">
                <label>Total de Sesiones</label>
                <input type="number" id="proto_total" name="proto_total" class="form-control" value="10" min="1" required>
            </div>
            <div class="form-group">
                <label>Paquete recomendado</label>
                <select id="recommended_package_template_id" name="recommended_package_template_id" class="form-control">
                    <option value="">Sin paquete sugerido</option>
                    <?php foreach($packageTemplates as $template): ?>
                    <option value="<?= (int)$template['id'] ?>">
                        <?= htmlspecialchars($template['name']) ?> - <?= (int)$template['total_sessions'] ?> sesiones - S/ <?= number_format((float)$template['total_amount'], 2) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="phases_container" class="protocol-phases-editor">
                <h4 class="protocol-section-title">Fases del Tratamiento</h4>
                <div class="phase-row protocol-phase-editor">
                    <div class="protocol-phase-grid">
                        <div>
                            <label class="protocol-input-label">Nombre de la Fase</label>
                            <input type="text" name="phase_name[]" class="phase-name form-control" placeholder="Ej: Fase I - Antiinflamatoria" value="Fase I">
                        </div>
                        <div>
                            <label class="protocol-input-label">Sesiones</label>
                            <input type="number" name="phase_sessions[]" class="phase-sessions form-control" value="10">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:0.5rem;">
                        <label style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">Objetivos (Que buscamos?)</label>
                        <textarea name="phase_objectives[]" class="phase-objectives form-control" rows="1" placeholder="Ej: Reducir inflamacion..."></textarea>
                    </div>
                    <div class="form-group" style="margin-top:0.5rem;">
                        <label style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">Actividades / Tecnicas</label>
                        <textarea name="phase_activities[]" class="phase-activities form-control" rows="1" placeholder="Ej: Crioterapia, US..."></textarea>
                    </div>
                </div>
            </div>
            
            <button type="button" onclick="addPhaseRow()" class="protocol-add-phase-btn">
                <span class="material-icons-outlined" style="font-size:1rem;">add</span> Anadir Fase
            </button>

            <div class="protocol-form-actions">
                <button type="submit" class="btn-primary" id="btnSaveProto">Guardar Protocolo</button>
                <button type="button" class="btn-secondary" onclick="document.getElementById('newProtocolForm').classList.toggle('hidden-form')">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- Lista de Protocolos -->
    <div class="protocols-grid">
        <?php foreach($protocols as $p): ?>
        <?php
            $phases = [];
            try {
                $phases = pdoQuery($pdo, "SELECT * FROM protocol_phases WHERE protocol_id = ? ORDER BY step_order ASC", [$p['id']])->fetchAll();
            } catch (Exception $e) {
                $phases = [];
            }
        ?>
        <div class="card protocol-card">
            <div class="protocol-card-top">
                <h3 class="protocol-card-title"><?= htmlspecialchars($p['name']) ?></h3>
                <span class="badge badge-primary protocol-badge"><?= $p['total_sessions'] ?> sesiones</span>
            </div>
            <?php if (!empty(trim((string)$p['description']))): ?>
            <p class="protocol-card-description"><?= htmlspecialchars($p['description']) ?></p>
            <?php endif; ?>
            <?php if (!empty($p['recommended_package_template_id'])): ?>
            <?php
                $recommendedTemplate = null;
                foreach ($packageTemplates as $templateOption) {
                    if ((int)$templateOption['id'] === (int)$p['recommended_package_template_id']) {
                        $recommendedTemplate = $templateOption;
                        break;
                    }
                }
            ?>
            <?php if ($recommendedTemplate): ?>
            <div style="margin-bottom:0.85rem;padding:0.75rem 0.85rem;border:1px solid rgba(14,165,183,0.16);background:#f0fdfa;border-radius:14px;">
                <div style="font-size:0.72rem;text-transform:uppercase;font-weight:800;letter-spacing:0.06em;color:#0f766e;">Paquete recomendado</div>
                <div style="margin-top:0.25rem;font-size:0.88rem;font-weight:700;color:#134e4a;"><?= htmlspecialchars($recommendedTemplate['name']) ?></div>
                <div style="font-size:0.78rem;color:#0f766e;"><?= (int)$recommendedTemplate['total_sessions'] ?> sesiones - S/ <?= number_format((float)$recommendedTemplate['total_amount'], 2) ?></div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <div class="protocol-phase-panel">
                <div class="protocol-phase-heading">Fases</div>
                <?php if (empty($phases)): ?>
                <div class="protocol-phase-empty">Sin fases configuradas aun.</div>
                <?php endif; ?>
                <?php foreach($phases as $ph): ?>
                <div class="protocol-phase-item">
                    <div class="protocol-phase-item-top">
                        <span class="protocol-phase-name"><?= htmlspecialchars($ph['name']) ?></span>
                        <span class="badge protocol-phase-badge"><?= $ph['sessions_count'] ?> ses.</span>
                    </div>
                    <?php if($ph['objectives']): ?>
                        <div class="protocol-phase-meta"><?= htmlspecialchars($ph['objectives']) ?></div>
                    <?php endif; ?>
                    <?php if($ph['activities']): ?>
                        <div class="protocol-phase-note"><?= htmlspecialchars($ph['activities']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="protocol-card-actions">
                <?php if($canManageProtocols): ?>
                <button onclick='editProtocol(<?= json_encode($p) ?>, <?= json_encode($phases) ?>)' class="btn-action-sm btn-outline protocol-edit-btn" style="flex:1; justify-content:center; gap:0.3rem;">
                    <span class="material-icons-outlined" style="font-size:1rem;">edit</span> Editar
                </button>
                <button onclick="deleteProtocol(<?= $p['id'] ?>)" class="btn-action-sm btn-danger-soft protocol-delete-btn" style="width:44px; padding:0; justify-content:center;">
                    <span class="material-icons-outlined" style="font-size:1rem;">delete</span>
                </button>
                <?php else: ?>
                <button onclick="useProtocolForPatient(<?= (int)$p['id'] ?>)" class="btn-action-sm btn-outline protocol-edit-btn" style="flex:1; justify-content:center; gap:0.3rem;">
                    <span class="material-icons-outlined" style="font-size:1rem;">assignment_turned_in</span> Usar Protocolo
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.hidden-form { display:none !important; }

.protocols-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin: 0.5rem 0 1.5rem;
    padding: 0 0 0.5rem;
    border-bottom: 1px solid rgba(148,163,184,0.24);
}

.protocols-title {
    margin: 0;
    font-size: 1.55rem;
    line-height: 1.1;
    color: #0f172a;
}

.protocols-subtitle {
    margin: 0.35rem 0 0;
    max-width: 38rem;
    font-size: 0.92rem;
    color: var(--text-muted);
}

.protocols-hero-actions {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.protocols-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 84px;
    padding: 0.55rem 0.75rem;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid rgba(148,163,184,0.20);
}

.protocols-stat-value {
    font-size: 1.35rem;
    font-weight: 800;
}

.protocols-stat-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
}

.protocols-primary-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: none;
    border-radius: 14px;
    padding: 0.78rem 1rem;
    background: var(--primary-color);
    color: #ffffff;
    font-weight: 800;
    cursor: pointer;
}

.protocol-form-shell {
    margin-top: -0.25rem;
    background: #ffffff;
    border: 1px solid rgba(148,163,184,0.18);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    overflow: hidden;
}

.protocol-form-body {
    padding: 1.15rem;
}

.protocol-phases-editor {
    margin-top: 1rem;
}

.protocol-section-title {
    margin: 0 0 0.8rem;
    font-size: 0.85rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-muted);
}

.protocol-phase-editor {
    margin: 0 0 1rem;
    padding: 0.9rem;
    border-radius: 16px;
    border: 1px solid rgba(148,163,184,0.18);
    background: #f8fafc;
}

.protocol-phase-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.protocol-input-label {
    display: block;
    margin-bottom: 0.35rem;
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.protocol-add-phase-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: none;
    border-radius: 999px;
    padding: 0.65rem 0.95rem;
    background: rgba(14,165,183,0.08);
    color: var(--primary-dark);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    margin-bottom: 1rem;
}

.protocol-form-actions {
    display: flex;
    gap: 0.75rem;
}

.protocols-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.15rem;
}

.protocol-card {
    position: relative;
    overflow: hidden;
    padding: 1rem;
    border-radius: 18px;
    border: 1px solid rgba(148,163,184,0.18);
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.protocol-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.6rem;
}

.protocol-card-title::before {
    content: none;
}

.protocol-card-title {
    margin: 0;
    font-size: 1.15rem;
    line-height: 1.1;
    color: #0f172a;
}

.protocol-badge {
    white-space: nowrap;
    padding: 0.45rem 0.8rem;
    border-radius: 999px;
    box-shadow: inset 0 0 0 1px rgba(14,165,183,0.10);
}

.protocol-card-description {
    margin: 0 0 1rem;
    min-height: 0;
    font-size: 0.86rem;
    color: #64748b;
}

.protocol-phase-panel {
    padding: 0.8rem 0 0;
    border-radius: 0;
    background: transparent;
    border: none;
}

.protocol-phase-heading {
    margin-bottom: 0.45rem;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-muted);
}

.protocol-phase-item {
    padding: 0.6rem 0;
    border-bottom: 1px solid rgba(226,232,240,0.85);
}

.protocol-phase-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.protocol-phase-item-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.protocol-phase-name {
    font-weight: 600;
    color: #111827;
}

.protocol-phase-badge {
    background: rgba(14,165,183,0.12);
    color: var(--primary-dark);
    font-size: 0.72rem;
}

.protocol-phase-meta {
    font-size: 0.76rem;
    color: #475569;
    line-height: 1.45;
}

.protocol-phase-empty {
    padding: 0.6rem 0;
    font-size: 0.78rem;
    color: var(--text-muted);
}

.protocol-phase-note {
    margin-top: 0.15rem;
    font-size: 0.73rem;
    color: var(--text-muted);
}

.protocol-card-actions {
    display: flex;
    gap: 0.6rem;
    margin-top: 1rem;
}

.protocol-edit-btn {
    border-radius: 14px;
    background: #ffffff;
    box-shadow: none;
}

.protocol-delete-btn {
    border-radius: 16px;
}

@media (max-width: 720px) {
    .protocols-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .protocols-hero-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .protocols-stat {
        align-items: center;
    }

    .protocol-phase-grid {
        grid-template-columns: 1fr;
    }

    .protocol-form-actions {
        flex-direction: column;
    }
}
</style>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const canManageProtocols = <?= $canManageProtocols ? 'true' : 'false' ?>;

function buildPhaseRowHtml(phase = {}) {
    return `
        <div class="protocol-phase-grid">
            <div>
                <label class="protocol-input-label">Nombre de la Fase</label>
                <input type="text" name="phase_name[]" class="phase-name form-control" placeholder="Ej: Fase I - Antiinflamatoria" value="${phase.name || ''}">
            </div>
            <div>
                <label class="protocol-input-label">Sesiones</label>
                <input type="number" name="phase_sessions[]" class="phase-sessions form-control" value="${phase.sessions_count || 5}">
            </div>
        </div>
        <div class="form-group" style="margin-top:0.5rem;">
            <label class="protocol-input-label">Objetivos</label>
            <textarea name="phase_objectives[]" class="phase-objectives form-control" rows="1" placeholder="Ej: Reducir dolor, eliminar edema...">${phase.objectives || ''}</textarea>
        </div>
        <div class="form-group" style="margin-top:0.5rem;">
            <label class="protocol-input-label">Actividades / Tecnicas</label>
            <textarea name="phase_activities[]" class="phase-activities form-control" rows="1" placeholder="Ej: Crioterapia, TENS, ultrasonido...">${phase.activities || ''}</textarea>
        </div>
    `;
}

function addPhaseRow() {
    const container = document.getElementById('phases_container');
    const div = document.createElement('div');
    div.className = 'phase-row protocol-phase-editor';
    div.style = 'margin-bottom:1rem; padding:1rem; border:1px solid var(--border-color); background:white;';
    div.innerHTML = `
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
            <div>
                <label style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">Nombre de la Fase</label>
                <input type="text" name="phase_name[]" class="phase-name form-control" placeholder="Ej: Fase I - Antiinflamatoria">
            </div>
            <div>
                <label style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">Sesiones</label>
                <input type="number" name="phase_sessions[]" class="phase-sessions form-control" value="5">
            </div>
        </div>
        <div class="form-group" style="margin-top:0.5rem;">
            <label style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">Objetivos (Que buscamos?)</label>
            <textarea name="phase_objectives[]" class="phase-objectives form-control" rows="1" placeholder="Ej: Reducir dolor, Eliminar edema..."></textarea>
        </div>
        <div class="form-group" style="margin-top:0.5rem;">
            <label style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">Actividades / Tecnicas</label>
            <textarea name="phase_activities[]" class="phase-activities form-control" rows="1" placeholder="Ej: Crioterapia, TENS, Ultrasonido..."></textarea>
        </div>
    `;
    container.appendChild(div);
}

function handleProtocolSubmit(e) {
    const isEditing = !!document.getElementById('proto_id').value;
    if (!isEditing && (assignToPatientId || !canManageProtocols)) {
        const assignInput = document.querySelector('input[name="assign_to"]');
        const returnToInput = document.querySelector('input[name="return_to"]');
        if (assignInput && assignToPatientId) {
            assignInput.value = assignToPatientId;
        }
        if (returnToInput && protocolReturnTo) {
            returnToInput.value = protocolReturnTo;
        }
        return true;
    }

    saveProtocol(e);
    return false;
}

async function saveProtocol(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveProto');
    btn.disabled = true; btn.textContent = 'Guardando...';
    
    const phases = [];
    document.querySelectorAll('.phase-row').forEach(row => {
        const name = row.querySelector('.phase-name').value;
        const sessions = row.querySelector('.phase-sessions').value;
        const objectives = row.querySelector('.phase-objectives').value;
        const activities = row.querySelector('.phase-activities').value;
        
        if (name && sessions) {
            phases.push({ 
                name, 
                sessions_count: sessions,
                objectives: objectives,
                activities: activities
            });
        }
    });

    const id = document.getElementById('proto_id').value;
    const data = {
        id: id,
        name: document.getElementById('proto_name').value,
        description: document.getElementById('proto_desc').value,
        total_sessions: document.getElementById('proto_total').value,
        recommended_package_template_id: document.getElementById('recommended_package_template_id')?.value || '',
        phases: phases
    };

    try {
        const url = 'api/protocols.php';
        const method = id ? 'PUT' : 'POST';
        const res = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(data)
        });
        const raw = await res.text();
        let json = null;
        try {
            json = JSON.parse(raw);
        } catch (parseError) {
            throw new Error(raw || 'Respuesta invalida del servidor');
        }
        if (json.success) {
            showToast('Protocolo guardado', 'success');
            
            // Si venimos referenciados de un paciente, volver al perfil para terminar la asignacion
            if (assignToPatientId && !id) {
                const protoId = json.id || null; // Necesitamos que la API devuelva el ID
                if (protoId) {
                    window.location.href = 'patient_profile.php?id=' + assignToPatientId + '&open_assign_protocol=1&protocol_id=' + protoId;
                    return;
                }
            }
            
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(json.error || 'No se pudo guardar el protocolo', 'error');
        }
    } catch(e) {
        console.error(e);
        if (e && e.message) {
            alert(e.message);
        }
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = id ? 'Actualizar Protocolo' : 'Guardar Protocolo';
    }
}

function editProtocol(proto, phases) {
    if (!canManageProtocols) return;
    document.getElementById('form_title').textContent = 'Editar Protocolo: ' + proto.name;
    document.getElementById('proto_id').value = proto.id;
    document.getElementById('proto_name').value = proto.name;
    document.getElementById('proto_desc').value = proto.description;
    document.getElementById('proto_total').value = proto.total_sessions;
    if (document.getElementById('recommended_package_template_id')) {
        document.getElementById('recommended_package_template_id').value = proto.recommended_package_template_id || '';
    }
    document.getElementById('btnSaveProto').textContent = 'Actualizar Protocolo';
    
    const container = document.getElementById('phases_container');
    // Limpiar fases actuales pero mantener el titulo
    container.innerHTML = '<h4 class="protocol-section-title">Fases del Tratamiento</h4>';
    
    phases.forEach(ph => {
        const div = document.createElement('div');
        div.className = 'phase-row protocol-phase-editor';
        div.style = 'margin-bottom:1rem; padding:1rem; border:1px solid var(--border-color); background:white;';
        div.innerHTML = `
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
                <div>
                    <label style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">Nombre de la Fase</label>
                    <input type="text" name="phase_name[]" class="phase-name form-control" placeholder="Ej: Fase I" value="${ph.name}">
                </div>
                <div>
                    <label style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">Sesiones</label>
                    <input type="number" name="phase_sessions[]" class="phase-sessions form-control" value="${ph.sessions_count}">
                </div>
            </div>
            <div class="form-group" style="margin-top:0.5rem;">
                <label style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">Objetivos</label>
                <textarea name="phase_objectives[]" class="phase-objectives form-control" rows="1">${ph.objectives || ''}</textarea>
            </div>
            <div class="form-group" style="margin-top:0.5rem;">
                <label style="font-size:0.7rem; color:var(--text-muted); font-weight:700;">Actividades</label>
                <textarea name="phase_activities[]" class="phase-activities form-control" rows="1">${ph.activities || ''}</textarea>
            </div>
        `;
        container.appendChild(div);
    });
    
    const form = document.getElementById('newProtocolForm');
    form.classList.remove('hidden-form');
    form.scrollIntoView({ behavior: 'smooth' });
}

async function deleteProtocol(id) {
    if (!canManageProtocols) return;
    if (!confirm('Eliminar este protocolo permanentemente?')) return;
    try {
        const res = await fetch('api/protocols.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({id})
        });
        const json = await res.json();
        if (json.success) {
            showToast('Protocolo eliminado', 'success');
            location.reload();
        }
    } catch(e) { showToast('Error al eliminar', 'error'); }
}

async function useProtocolForPatient(protocolId) {
    if (!assignToPatientId) return;

    try {
        const res = await fetch('api/protocols.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ patient_id: assignToPatientId, protocol_id: protocolId })
        });
        const json = await res.json();
        if (json.success) {
            window.location.href = 'patient_profile.php?id=' + assignToPatientId;
            return;
        }
        showToast(json.error || 'No se pudo asignar el protocolo', 'error');
    } catch(e) { showToast('Error de conexion', 'error'); }
}
</script>

<script>
// Auto-abrir formulario de nuevo protocolo si venimos referenciados de un paciente
document.addEventListener('DOMContentLoaded', function() {
    const assignInput = document.querySelector('input[name="assign_to"]');
    const returnToInput = document.querySelector('input[name="return_to"]');
    if (assignInput && assignToPatientId) {
        assignInput.value = assignToPatientId;
    }
    if (returnToInput && protocolReturnTo) {
        returnToInput.value = protocolReturnTo;
    }

    if (typeof assignToPatientId !== 'undefined' && assignToPatientId) {
        const form = document.getElementById('newProtocolForm');
        if (form) {
            form.classList.remove('hidden-form');
            form.scrollIntoView({ behavior: 'smooth' });
            showToast('Creando protocolo para asignar automaticamente...', 'info');
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
