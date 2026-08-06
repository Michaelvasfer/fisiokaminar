<?php
// paciente_progreso.php - Dashboard de rehabilitacion avanzada y timeline
require_once 'db.php';
$pageTitle = 'Progreso de Rehabilitacion';
require_once 'includes/header.php';

$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$requestedPlanId = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
if (!$patientId && $userRole === 'patient') $patientId = $userId;

if (!$patientId) { header("Location: patients.php"); exit; }

// Obtener el plan solicitado o, en su defecto, el activo
if ($requestedPlanId > 0) {
    $plan = pdoQuery($pdo, "
        SELECT tp.*, u.name as patient_name, u.dni as patient_dni, u.age, u.patient_code
        FROM treatment_plans tp
        JOIN users u ON tp.patient_id = u.id
        WHERE tp.id = ? AND tp.patient_id = ?
        LIMIT 1
    ", [$requestedPlanId, $patientId])->fetch();
} else {
    $plan = pdoQuery($pdo, "
        SELECT tp.*, u.name as patient_name, u.dni as patient_dni, u.age, u.patient_code
        FROM treatment_plans tp
        JOIN users u ON tp.patient_id = u.id
        WHERE tp.patient_id = ? AND tp.status = 'active'
        LIMIT 1
    ", [$patientId])->fetch();
}

if (!$plan) {
    echo "<div class='card text-center' style='padding:3rem;'>
            <span class='material-icons-outlined' style='font-size:4rem;color:var(--text-muted);opacity:0.3;'>clinical_notes</span>
            <h3>No hay un plan activo</h3>
            <p class='text-muted'>Este paciente no tiene un protocolo de tratamiento asignado actualmente.</p>
            <a href='patient_profile.php?id=$patientId' class='btn-primary' style='display:inline-block;margin-top:1rem;'>Volver al Perfil</a>
          </div>";
    require_once 'includes/footer.php';
    exit;
}

$planId = $plan['id'];
$protocolTotalSessions = 0;
if ((int)($plan['protocol_id'] ?? 0) > 0) {
    $protoMeta = pdoQuery($pdo, "SELECT total_sessions FROM treatment_protocols WHERE id = ? LIMIT 1", [(int)$plan['protocol_id']])->fetch();
    $protocolTotalSessions = max(0, (int)($protoMeta['total_sessions'] ?? 0));
}

// Obtener fases del protocolo
$phases = pdoQuery($pdo, "SELECT * FROM protocol_phases WHERE protocol_id = ? ORDER BY step_order ASC, id ASC", [$plan['protocol_id']])->fetchAll();

// Autocorregir protocolos sin fases para que el plan no quede en 0/0.
if (empty($phases) && (int)($plan['protocol_id'] ?? 0) > 0) {
    $fallbackSessions = max(1, $protocolTotalSessions > 0 ? $protocolTotalSessions : (int)($plan['total_sessions'] ?? 10));
    pdoQuery($pdo, "
        INSERT INTO protocol_phases (protocol_id, name, sessions_count, step_order, objectives, activities)
        VALUES (?, 'Fase I', ?, 1, '', '')
    ", [(int)$plan['protocol_id'], $fallbackSessions]);
    $phases = pdoQuery($pdo, "SELECT * FROM protocol_phases WHERE protocol_id = ? ORDER BY step_order ASC, id ASC", [$plan['protocol_id']])->fetchAll();
}

// Obtener sesiones reales del plan
$sessions = pdoQuery($pdo, "SELECT * FROM patient_sessions WHERE plan_id = ? ORDER BY session_number ASC, id ASC", [$planId])->fetchAll();

// Si faltan sesiones por fase (planes antiguos o incompletos), generarlas automaticamente.
$targetTotal = $protocolTotalSessions > 0 ? $protocolTotalSessions : (int)($plan['total_sessions'] ?? 0);
$phaseTargetMeta = buildPlanPhaseTargets($phases, $targetTotal);
$expectedPerPhase = $phaseTargetMeta['targets'] ?? [];
$expectedTotal = (int)($phaseTargetMeta['total'] ?? 0);

$existingPerPhase = [];
$maxSessionNumber = 0;
foreach ($sessions as $sessionItem) {
    $pid = (int)($sessionItem['phase_id'] ?? 0);
    if (!isset($existingPerPhase[$pid])) {
        $existingPerPhase[$pid] = 0;
    }
    $existingPerPhase[$pid]++;
    $maxSessionNumber = max($maxSessionNumber, (int)($sessionItem['session_number'] ?? 0));
}

$inserted = 0;
$nextSessionNumber = $maxSessionNumber + 1;
foreach ($phases as $phase) {
    $phaseId = (int)$phase['id'];
    $phaseName = trim((string)($phase['name'] ?? 'Fase'));
    $target = (int)($expectedPerPhase[$phaseId] ?? 0);
    $current = (int)($existingPerPhase[$phaseId] ?? 0);
    $missing = $target - $current;
    for ($i = 0; $i < $missing; $i++) {
        pdoQuery(
            $pdo,
            "INSERT INTO patient_sessions (plan_id, phase_id, session_number, title, status) VALUES (?, ?, ?, ?, 'pending')",
            [$planId, $phaseId, $nextSessionNumber, $phaseName . ' - Sesion ' . $nextSessionNumber]
        );
        $inserted++;
        $nextSessionNumber++;
    }
}

if ($inserted > 0) {
    $sessions = pdoQuery($pdo, "SELECT * FROM patient_sessions WHERE plan_id = ? ORDER BY session_number ASC, id ASC", [$planId])->fetchAll();
}

$totalSessions = count($sessions);
$completedSessions = count(array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'completed'));
$progressPct = $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100) : 0;

// Mantener treatment_plans sincronizado con el detalle real por sesiones.
if ((int)($plan['total_sessions'] ?? 0) !== $totalSessions || (int)($plan['completed_sessions'] ?? 0) !== $completedSessions) {
    $sync = syncTreatmentPlanFromSessions($pdo, (int)$planId);
    $plan['total_sessions'] = (int)($sync['total_sessions'] ?? $totalSessions);
    $plan['completed_sessions'] = (int)($sync['completed_sessions'] ?? $completedSessions);
    $plan['status'] = (string)($sync['status'] ?? $plan['status']);
}

// Encontrar la sesion actual (primera pendiente)
$currentSession = null;
foreach ($sessions as $s) {
    if (($s['status'] ?? '') === 'pending') {
        $currentSession = $s;
        break;
    }
}
?>

<div class="animate-fade-in">
    <!-- Header del Dashboard -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;font-size:1.5rem;"><?= htmlspecialchars($plan['title']) ?></h1>
            <p class="text-muted" style="margin:5px 0 0 0;">Paciente: <strong><?= htmlspecialchars($plan['patient_name']) ?></strong> | Codigo: <?= htmlspecialchars($plan['patient_code']) ?></p>
        </div>
        <div style="text-align:right;">
            <div class="badge badge-success" style="font-size:0.85rem;padding:0.4rem 0.8rem;">ESTADO: <?= strtoupper($plan['status']) ?></div>
            <p style="font-size:0.8rem;color:var(--text-muted);margin:5px 0 0 0;">Iniciado: <?= date('d/m/Y', strtotime($plan['start_date'])) ?></p>
            <?php if ($userRole === 'admin'): ?>
            <button type="button" onclick="rebuildCurrentPlan()" class="btn-secondary" style="margin-top:0.6rem;padding:0.45rem 0.7rem;font-size:0.74rem;">
                Reconstruir plan
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Metricas Rapidas -->
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div class="card" style="text-align:center;padding:1.5rem;">
            <div style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:0.5rem;">Progreso Total</div>
            <div style="font-size:1.8rem;font-weight:800;color:var(--primary-color);"><?= $progressPct ?>%</div>
            <div style="height:8px;background:var(--border-color);border-radius:99px;overflow:hidden;margin-top:0.8rem;">
                <div style="height:100%;width:<?= $progressPct ?>%;background:var(--primary-color);"></div>
            </div>
        </div>
        <div class="card" style="text-align:center;padding:1.5rem;">
            <div style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:0.5rem;">Sesiones Realizadas</div>
            <div style="font-size:1.8rem;font-weight:800;color:var(--success);"><?= $completedSessions ?> <span style="font-size:1rem;color:var(--text-muted);font-weight:500;">/ <?= $totalSessions ?></span></div>
        </div>
        <div class="card" style="text-align:center;padding:1.5rem;">
            <div style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:0.5rem;">Fase Actual</div>
            <div style="font-size:1.2rem;font-weight:800;color:var(--accent-orange, #f59e0b);">
                <?php
                $activePhaseName = "Finalizado";
                if ($currentSession) {
                    foreach ($phases as $ph) {
                        if ($ph['id'] == $currentSession['phase_id']) {
                            $activePhaseName = $ph['name'];
                            break;
                        }
                    }
                }
                echo htmlspecialchars($activePhaseName);
                ?>
            </div>
        </div>
    </div>

    <!-- Timeline Interactiva -->
    <div class="card mb-8">
        <div class="card-header" style="border-bottom:none;">
            <h2 class="card-title"><span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.5rem;color:var(--primary-color);">timeline</span> L&iacute;nea de Tiempo de Recuperaci&oacute;n</h2>
        </div>
        
        <div style="padding:0 1.5rem 2rem 1.5rem;">
            <?php foreach($phases as $phase): ?>
                <?php 
                    $phaseSessions = array_values(array_filter($sessions, fn($s) => $s['phase_id'] == $phase['id']));
                    usort($phaseSessions, function($a, $b) {
                        $aNum = (int)($a['session_number'] ?? 0);
                        $bNum = (int)($b['session_number'] ?? 0);
                        if ($aNum === $bNum) {
                            return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
                        }
                        return $aNum <=> $bNum;
                    });
                    $phaseDisplayCount = count($phaseSessions);
                    if ($phaseDisplayCount <= 0) {
                        $phaseDisplayCount = (int)($expectedPerPhase[(int)$phase['id']] ?? ($phase['sessions_count'] ?? 0));
                    }
                ?>
                <div class="phase-section" style="margin-bottom:2rem;position:relative;">
                    <h3 style="font-size:0.95rem;font-weight:800;color:var(--primary-dark);display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
                        <span style="width:24px;height:24px;border-radius:50%;background:var(--primary-color);color:white;display:flex;align-items:center;justify-content:center;font-size:0.7rem;"><?= $phase['step_order'] ?></span>
                        <?= htmlspecialchars(app_text($phase['name'])) ?> 
                        <span style="font-weight:400;color:var(--text-muted);font-size:0.8rem;">(<?= $phaseDisplayCount ?> sesiones)</span>
                    </h3>
                    
                    <?php if($phase['objectives'] || $phase['activities']): ?>
                    <div style="background:var(--primary-light); padding:0.75rem; border-radius:var(--radius-sm); margin:0 0 1rem 1.5rem; font-size:0.8rem; border-left:3px solid var(--primary-color);">
                        <?php if($phase['objectives']): ?>
                            <div style="margin-bottom:0.4rem;"><strong>Objetivos:</strong> <?= htmlspecialchars(app_text($phase['objectives'])) ?></div>
                        <?php endif; ?>
                        <?php if($phase['activities']): ?>
                            <div><strong>Tecnicas:</strong> <?= htmlspecialchars(app_text($phase['activities'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display:flex;flex-wrap:wrap;gap:1.5rem;padding-left:1.5rem;border-left:2px dashed var(--border-color);position:relative;">
                        <?php foreach($phaseSessions as $index => $ps): 
                            $isCurrent = $currentSession && $currentSession['id'] == $ps['id'];
                            $isDone = $ps['status'] === 'completed';
                            $color = $isDone ? 'var(--success)' : ($isCurrent ? '#f59e0b' : 'var(--text-muted)');
                            $bg = $isDone ? '#dcfce7' : ($isCurrent ? '#fef3c7' : '#f8fafc');
                            $localSessionNumber = $index + 1;
                            $canInteract = $isDone || $isCurrent;
                        ?>
                        <div class="session-node" <?= $canInteract ? 'onclick="openSessionDetails(' . (int)$ps['id'] . ')"' : '' ?>
                             style="cursor:<?= $canInteract ? 'pointer' : 'not-allowed' ?>;opacity:<?= $canInteract ? '1' : '0.55' ?>;display:flex;flex-direction:column;align-items:center;width:60px;transition:transform 0.2s;">
                            <div style="width:32px;height:32px;border-radius:50%;background:<?= $bg ?>;border:2px solid <?= $color ?>;display:flex;align-items:center;justify-content:center;position:relative;">
                                <span class="material-icons-outlined" style="font-size:1.1rem;color:<?= $color ?>;">
                                    <?= $isDone ? 'check' : ($isCurrent ? 'play_arrow' : 'circle') ?>
                                </span>
                                <?php if($isCurrent): ?>
                                    <div style="position:absolute;top:-5px;right:-5px;width:12px;height:12px;background:#f59e0b;border-radius:50%;border:2px solid white;animation:pulse 2s infinite;"></div>
                                <?php endif; ?>
                            </div>
                            <span style="margin-top:0.4rem;font-size:0.65rem;font-weight:700;color:<?= $color ?>;text-transform:uppercase;">Ses. <?= $localSessionNumber ?></span>
                            <?php if($ps['eva_score'] !== null && $isDone): ?>
                                <span style="font-size:0.6rem;background:<?= $ps['eva_score'] > 5 ? '#fee2e2' : '#f3f4f6' ?>;padding:1px 4px;border-radius:4px;margin-top:2px;">EVA: <?= $ps['eva_score'] ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top:0.85rem;padding-left:1.5rem;">
                        <?php foreach($phaseSessions as $index => $ps): ?>
                        <?php
                            $rowDone = ($ps['status'] ?? '') === 'completed';
                            $rowPending = ($ps['status'] ?? '') === 'pending';
                            $rowStatusLabel = $rowDone ? 'Realizada' : ($rowPending ? 'Pendiente' : 'Cancelada');
                            $rowStatusColor = $rowDone ? '#166534' : ($rowPending ? '#9a3412' : '#991b1b');
                            $rowStatusBg = $rowDone ? '#dcfce7' : ($rowPending ? '#ffedd5' : '#fee2e2');
                            $localSessionNumber = $index + 1;
                            $rowDetail = trim((string)($ps['evolution'] ?? ''));
                            if ($rowDetail === '') {
                                $rowDetail = trim((string)($ps['observations'] ?? ''));
                            }
                        ?>
                        <button
                            type="button"
                            onclick="openSessionDetails(<?= (int)$ps['id'] ?>)"
                            style="width:100%;text-align:left;margin:0 0 0.55rem 0;padding:0.65rem 0.75rem;border:1px solid var(--border-color);border-radius:10px;background:#fff;cursor:pointer;"
                        >
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;">
                                <div style="font-size:0.78rem;font-weight:700;color:var(--text-main);">
                                    <?= htmlspecialchars(app_text((string)($ps['title'] ?? ('Sesion ' . (int)$localSessionNumber)))) ?>
                                </div>
                                <span style="font-size:0.68rem;font-weight:700;padding:0.2rem 0.45rem;border-radius:999px;color:<?= $rowStatusColor ?>;background:<?= $rowStatusBg ?>;">
                                    <?= $rowStatusLabel ?>
                                </span>
                            </div>
                            <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-muted);">
                                <?= $rowDone && !empty($ps['completed_date']) ? 'Completada: ' . date('d/m/Y H:i', strtotime((string)$ps['completed_date'])) : 'Sin fecha de cierre' ?>
                            </div>
                            <div style="margin-top:0.25rem;font-size:0.73rem;color:#334155;">
                                <?= $rowDetail !== '' ? htmlspecialchars(app_text($rowDetail)) : 'Sin detalle clinico registrado aun.' ?>
                            </div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal: Detalle de Sesion -->
<div class="modal-overlay" id="modalSessionDetail">
    <div class="modal-sheet" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="ms_title">Detalle de Sesion</h3>
            <button class="modal-close" onclick="closeModal('modalSessionDetail')"><span class="material-icons-outlined">close</span></button>
        </div>
        <form onsubmit="saveSessionProgress(event)" style="padding:1rem;">
            <input type="hidden" id="ms_id">
            
            <div class="form-group">
                <label>Estado de la Sesion</label>
                <select id="ms_status" class="form-control" required onchange="toggleClinicFields()" <?= $userRole === 'patient' ? 'disabled' : '' ?>>
                    <option value="pending">Pendiente</option>
                    <option value="completed">Realizada</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </div>

            <div id="clinicFields" style="display:none;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label>Dolor (EVA 0-10)</label>
                        <input type="number" id="ms_eva" class="form-control" min="0" max="10" placeholder="0 = Sin dolor" <?= $userRole === 'patient' ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Movilidad / Mejora %</label>
                        <input type="text" id="ms_mobility" class="form-control" placeholder="Ej: +10 grados flexion" <?= $userRole === 'patient' ? 'disabled' : '' ?>>
                    </div>
                </div>

                <div class="form-group">
                    <label>Evolucion / Avance</label>
                    <textarea id="ms_evolution" class="form-control" rows="2" placeholder="..." <?= $userRole === 'patient' ? 'disabled' : '' ?>></textarea>
                </div>
                
                <div class="form-group">
                    <label>Observaciones / Nota Clinica</label>
                    <textarea id="ms_obs" class="form-control" rows="2" placeholder="..." <?= $userRole === 'patient' ? 'disabled' : '' ?>></textarea>
                </div>

                <div class="form-group">
                    <label>Cambios en el Tratamiento (Opcional)</label>
                    <textarea id="ms_changes" class="form-control" rows="1" placeholder="..." <?= $userRole === 'patient' ? 'disabled' : '' ?>></textarea>
                </div>
            </div>

            <?php if ($userRole !== 'patient'): ?>
            <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                <button type="submit" class="btn-primary" id="btnSaveSession" style="flex:1;">Guardar Progreso</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<style>
@keyframes pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}
.session-node:hover { transform: scale(1.1); }
</style>

<script>
function toggleClinicFields() {
    const status = document.getElementById('ms_status').value;
    document.getElementById('clinicFields').style.display = status === 'completed' ? 'block' : 'none';
}

async function openSessionDetails(sessionId) {
    const res = await fetch(`api/patient_sessions.php?id=${sessionId}`);
    const s = await res.json();
    
    document.getElementById('ms_id').value = s.id;
    document.getElementById('ms_title').textContent = s.title;
    document.getElementById('ms_status').value = s.status;
    document.getElementById('ms_eva').value = s.eva_score || 0;
    document.getElementById('ms_mobility').value = s.mobility_notes || '';
    document.getElementById('ms_evolution').value = s.evolution || '';
    document.getElementById('ms_obs').value = s.observations || '';
    document.getElementById('ms_changes').value = s.treatment_changes || '';

    const statusSelect = document.getElementById('ms_status');
    const completedOption = statusSelect ? statusSelect.querySelector('option[value="completed"]') : null;
    if (completedOption) {
        const canComplete = s.can_complete !== false || s.status === 'completed';
        completedOption.disabled = !canComplete;
        if (!canComplete && statusSelect.value === 'completed') {
            statusSelect.value = 'pending';
        }
    }
    
    toggleClinicFields();
    openModal('modalSessionDetail');
}

async function rebuildCurrentPlan() {
    if (!confirm('Se reconstruiran fases/sesiones faltantes del plan actual. Continuar?')) return;

    try {
        const res = await fetch('api/patient_sessions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '')
            },
            body: JSON.stringify({
                action: 'rebuild_plan',
                plan_id: <?= (int)$planId ?>
            })
        });
        const json = await res.json();
        if (json.success) {
            showToast('Plan reconstruido correctamente', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showToast(json.error || 'No se pudo reconstruir el plan', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function saveSessionProgress(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveSession');
    btn.disabled = true; btn.textContent = 'Guardando...';
    
    const data = {
        id: document.getElementById('ms_id').value,
        status: document.getElementById('ms_status').value,
        eva_score: document.getElementById('ms_eva').value,
        mobility_notes: document.getElementById('ms_mobility').value,
        evolution: document.getElementById('ms_evolution').value,
        observations: document.getElementById('ms_obs').value,
        treatment_changes: document.getElementById('ms_changes').value
    };

    try {
        const res = await fetch('api/patient_sessions.php', {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            showToast('Sesion actualizada', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Error: ' + json.error, 'error');
        }
    } catch(e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar Progreso';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
