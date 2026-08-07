<?php
// attendance.php - Control de Asistencia Diaria
require_once 'db.php';
ensurePublicIntakeSchema($pdo);
$pageTitle = 'Asistencia';
require_once 'includes/header.php';

if (!in_array($userRole, ['admin', 'receptionist', 'therapist'])) {
    header("Location: index.php"); exit;
}

$today = date('Y-m-d');

try {
    $columns = $pdo->query("SHOW COLUMNS FROM appointments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('checked_in_at', $columns)) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN checked_in_at DATETIME NULL DEFAULT NULL");
    }
    if (!in_array('checked_in_by', $columns)) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN checked_in_by INT NULL DEFAULT NULL");
    }
    if (!in_array('created_by', $columns)) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN created_by INT NULL DEFAULT NULL");
    }
    if (!in_array('rescheduled_at', $columns)) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN rescheduled_at DATETIME NULL DEFAULT NULL");
    }
} catch(Exception $e) {}

// Estadisticas del dia (Resiliente)
$stats = ['total' => 0, 'pending' => 0, 'attended' => 0, 'absent' => 0];
try {
    $row = pdoQuery($pdo, "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as attended,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as absent
        FROM appointments 
        WHERE appointment_date = ?
    ", [$today])->fetch();
    if ($row) $stats = $row;
} catch(Exception $e) {}

// Citas de hoy (Resiliente)
$appointments = [];
try {
    $appointments = pdoQuery($pdo, "
        SELECT
            a.*,
            u.name as patient_name,
            u.dni as patient_dni,
            t.name as therapist_name,
            t.is_active as therapist_is_active,
            cr.name as created_by_name,
            ci.name as checked_in_by_name
        FROM appointments a
        LEFT JOIN users u ON a.patient_id = u.id
        LEFT JOIN users t ON t.id = a.therapist_id
        LEFT JOIN users cr ON cr.id = a.created_by
        LEFT JOIN users ci ON ci.id = a.checked_in_by
        WHERE a.appointment_date = ? OR DATE(a.rescheduled_at) = ?
        ORDER BY a.start_time DESC
    ", [$today, $today])->fetchAll();
} catch(Exception $e) {}

// Cargar pacientes (Resiliente)
$modalPatients = [];
try {
    $modalPatients = pdoQuery($pdo, "SELECT id, name, dni FROM users WHERE role = 'patient' ORDER BY name ASC")->fetchAll();
} catch(Exception $e) {}

// Cargar fisioterapeutas para asignar al momento de asistencia
$therapists = [];
try {
    $therapists = pdoQuery($pdo, "SELECT id, name FROM users WHERE role = 'therapist' AND is_active = 1 ORDER BY name ASC")->fetchAll();
} catch(Exception $e) {}
?>

<div class="animate-fade-in delay-100">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h1 style="margin:0;">Asistencia</h1>
        <div style="text-align:right;">
             <?php 
                $days = ['Domingo','Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'];
                $months = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                $d = getdate(strtotime($today));
                $dateStr = $days[$d['wday']] . ', ' . $d['mday'] . ' ' . $months[$d['mon']];
             ?>
             <div style="font-size:0.8rem;color:var(--text-muted);font-weight:600;"><?= $dateStr ?></div>
        </div>
    </div>

    <!-- Indicadores Rapidos -->
    <div class="metrics-grid mb-4" style="border-radius:var(--radius-md); overflow:hidden; border:1px solid var(--border-color);">
        <div class="metric-card" style="padding:0.75rem;">
            <div class="metric-value" id="attendanceCountAttended" style="font-size:1.4rem; color:var(--success);"><?= $stats['attended'] ?? 0 ?></div>
            <div class="metric-label" style="font-size:0.65rem;">ASISTIO</div>
        </div>
        <div class="metric-card" style="padding:0.75rem;">
            <div class="metric-value" id="attendanceCountPending" style="font-size:1.4rem; color:var(--warning);"><?= $stats['pending'] ?? 0 ?></div>
            <div class="metric-label" style="font-size:0.65rem;">PENDIENTE</div>
        </div>
        <div class="metric-card" style="padding:0.75rem;">
            <div class="metric-value" id="attendanceCountAbsent" style="font-size:1.4rem; color:var(--danger);"><?= $stats['absent'] ?? 0 ?></div>
            <div class="metric-label" style="font-size:0.65rem;">FALTO</div>
        </div>
        <div class="metric-card" style="padding:0.75rem;">
            <div class="metric-value" id="attendanceCountTotal" style="font-size:1.4rem; color:var(--text-main);"><?= $stats['total'] ?? 0 ?></div>
            <div class="metric-label" style="font-size:0.65rem;">TOTAL</div>
        </div>
    </div>

    <!-- Buscador de Check-in Rapido -->
    <div class="card mb-4">
        <div style="padding:1rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
                <span class="material-icons-outlined" style="color:var(--primary-color);">fact_check</span>
                <span style="font-weight:600;font-size:0.9rem;">Check-in Rapido</span>
            </div>
            <div style="position:relative;">
                <span class="material-icons-outlined" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);font-size:1.1rem;color:var(--text-muted);pointer-events:none;">search</span>
                <input type="text" id="checkin_search" class="form-control" placeholder="Buscar paciente para marcar llegada..." style="padding-left:2.5rem; border-radius:var(--radius-full);" oninput="searchForCheckin(this.value)" autocomplete="off">
                <div id="checkin_results" class="search-results-floating" style="display:none; top:45px; z-index:999;"></div>
            </div>
        </div>
    </div>

    <!-- Lista de Hoy -->
    <div class="card mb-8">
        <div class="card-header">
            <h2 class="card-title">Pacientes de Hoy</h2>
        </div>
        <div class="list-group" id="attendanceList" style="<?= count($appointments) > 0 ? '' : 'display:none;' ?>">
            <?php foreach($appointments as $apt): ?>
            <?php 
                $statusColor = match($apt['status']) {
                    'completed' => ['#d1fae5','#065f46','Completada'],
                    'cancelled' => ['#fee2e2','#991b1b','Cancelada'],
                    default     => ['#e0f2fe','#0369a1','Agendada']
                };
                $esReagendada = !empty($apt['rescheduled_at'])
                    && date('Y-m-d', strtotime($apt['rescheduled_at'])) === $today
                    && $apt['appointment_date'] !== $today;
                $attendanceDisplayName = $apt['therapist_name'] ?? 'Terapeuta';
                if ((int)($apt['therapist_is_active'] ?? 1) !== 1) {
                    $attendanceDisplayName = $apt['created_by_name'] ?: ($apt['checked_in_by_name'] ?: ($userName ?? 'Usuario actual'));
                }
            ?>
            <div class="list-item" id="attendance-apt-<?= $apt['id'] ?>" data-status="<?= htmlspecialchars($apt['status']) ?>" style="padding:0.65rem 1rem; cursor:pointer;" onclick="window.location='patient_profile.php?id=<?= $apt['patient_id'] ?>'">
                <div class="list-item-icon" style="background:<?= $statusColor[0] ?>; color:<?= $statusColor[1] ?>; width:36px; height:36px;">
                    <span class="material-icons-outlined" style="font-size:1rem;">
                         <?= $apt['status'] === 'completed' ? 'check_circle' : ($apt['status'] === 'cancelled' ? 'block' : 'timer') ?>
                    </span>
                </div>
                <div class="list-item-content">
                    <div style="font-size:0.875rem;font-weight:600;color:var(--primary-color);"><?= htmlspecialchars($apt['patient_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);" class="pt-details-therapist">
                        <?= date('h:i A', strtotime($apt['start_time'])) ?> &middot; <?= htmlspecialchars($attendanceDisplayName) ?>
                    </div>
                    <?php if(($apt['source_channel'] ?? '') === 'public_intake'): ?>
                    <div style="margin-top:0.2rem;">
                        <span class="badge" style="background:#ecfeff;color:#0f766e;font-size:0.68rem;">Ingreso Web / WhatsApp</span>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($apt['checked_in_at']) && $apt['status'] === 'completed'): ?>
                    <div class="attendance-checkin-time" style="font-size:0.72rem;color:var(--success);font-weight:600;">
                        Llego: <?= date('h:i A', strtotime($apt['checked_in_at'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="attendance-actions">
                    <?php if($esReagendada): ?>
                    <span style="font-size:0.7rem; font-weight:700; color:#7c3aed;">REAGENDADO → <?= date('d/m', strtotime($apt['appointment_date'])) ?> <?= substr($apt['start_time'],0,5) ?></span>
                    <?php elseif($apt['status'] === 'scheduled'): ?>
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:center;">
                        <button onclick="event.stopPropagation(); openAttendanceModal(<?= $apt['id'] ?>, <?= $apt['therapist_id'] ?: 'null' ?>)" title="Presente"
                            style="background:var(--success); color:white; border:none; width:26px; height:26px; border-radius:50%; cursor:pointer; display:inline-flex; align-items:center; justify-content:center;">
                            <span class="material-icons-outlined" style="font-size:0.95rem;">check</span>
                        </button>
                        <button onclick='event.stopPropagation(); rescheduleAppointment(<?= (int)$apt['id'] ?>, <?= json_encode(substr($apt['start_time'],0,5)) ?>, <?= json_encode(substr($apt['end_time'],0,5)) ?>)' title="Reagendar"
                            style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; width:26px; height:26px; border-radius:50%; cursor:pointer; display:inline-flex; align-items:center; justify-content:center;">
                            <span class="material-icons-outlined" style="font-size:0.9rem;">event</span>
                        </button>
                        <button onclick="event.stopPropagation(); quickAttendance(<?= $apt['id'] ?>, 'cancelled')" title="Falto"
                            style="background:var(--border-color); color:var(--text-muted); border:none; width:26px; height:26px; border-radius:50%; cursor:pointer; display:inline-flex; align-items:center; justify-content:center;">
                            <span class="material-icons-outlined" style="font-size:0.9rem;">close</span>
                        </button>
                    </div>
                    <?php else: ?>
                    <div style="display:flex; gap:0.5rem; align-items:center; justify-content:flex-end;">
                        <span style="font-size:0.7rem; font-weight:700; color:<?= $statusColor[1] ?>;"><?= strtoupper($statusColor[2]) ?></span>
                        <?php if ($userRole === 'admin'): ?>
                        <button onclick="event.stopPropagation(); quickAttendance(<?= $apt['id'] ?>, 'scheduled')" 
                            style="background:#fff7ed; color:#9a3412; border:1px solid #fdba74; padding:0.35rem 0.65rem; border-radius:var(--radius-sm); font-size:0.72rem; font-weight:600; cursor:pointer;">
                            Revertir
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="attendanceEmptyState" style="text-align:center;padding:2rem;color:var(--text-muted);<?= count($appointments) > 0 ? 'display:none;' : '' ?>">
            <span class="material-icons-outlined" style="font-size:3rem;opacity:0.3;display:block;margin-bottom:0.5rem;">event_busy</span>
            No hay citas agendadas para hoy.
        </div>
    </div>
</div>

<!-- Modal Elegir Fisioterapeuta Asistencia -->
<div class="modal-overlay" id="modalSelectTherapist">
    <div class="modal-sheet" style="max-width:400px;text-align:center;">
        <div style="width:50px;height:50px;background:#d1fae5;color:#059669;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
            <span class="material-icons-outlined" style="font-size:24px;">assignment_ind</span>
        </div>
        <h3 style="margin-bottom:0.5rem;color:var(--primary-dark);font-size:1.2rem;">Confirmar Asistencia</h3>
        <p class="text-sm text-muted" style="margin-bottom:1.5rem;">¿Qué fisioterapeuta va a atender al paciente en esta sesión?</p>
        
        <div style="text-align:left;margin-bottom:1.5rem;">
            <label style="font-weight:600;font-size:0.85rem;color:var(--text-main);display:block;margin-bottom:0.4rem;">Fisioterapeuta asignado *</label>
            <select id="select_attendance_therapist" class="form-control" style="font-size:1rem;padding:0.75rem;">
                <?php foreach($therapists as $th): ?>
                    <option value="<?= $th['id'] ?>"><?= htmlspecialchars($th['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <input type="hidden" id="select_attendance_apt_id">
        <input type="hidden" id="select_attendance_patient_id">
        <input type="hidden" id="select_attendance_patient_name">
        <input type="hidden" id="select_attendance_mode" value="put">

        <div style="display:flex;gap:0.5rem;justify-content:center;">
            <button class="btn-action-sm" onclick="closeModal('modalSelectTherapist')" style="flex:1;">Cancelar</button>
            <button class="btn-primary" onclick="confirmQuickAttendance()" style="flex:1;">Confirmar Ingreso</button>
        </div>
    </div>
</div>

<script>
const allPatients = <?= json_encode($modalPatients) ?>;
const todayAppointments = <?= json_encode($appointments) ?>;
const currentAttendanceRole = <?= json_encode($userRole ?? '') ?>;

function searchForCheckin(query) {
    const resultsDiv = document.getElementById('checkin_results');
    if (!query || query.length < 1) {
        resultsDiv.style.display = 'none';
        return;
    }
    
    const term = query.toLowerCase();
    const matches = allPatients.filter(p => {
        const nameMatch = p.name ? p.name.toLowerCase().includes(term) : false;
        const dniMatch  = p.dni ? String(p.dni).includes(term) : false;
        return nameMatch || dniMatch;
    }).slice(0, 8);
    
    if (matches.length > 0) {
        let html = matches.map(p => {
            const hasAptToday = todayAppointments.find(a => a.patient_id == p.id);
            
            if (hasAptToday) {
                if (hasAptToday.status !== 'scheduled') {
                    return `
                        <div class="search-result-item" onclick="walkInAttendance(${p.id}, '${p.name.replace(/'/g, "\\'")}')">
                            <div style="font-weight:600;">${p.name}</div>
                            <div style="font-size:0.75rem;color:var(--success);">Asistio hoy (${hasAptToday.checked_in_at ? hasAptToday.checked_in_at.substring(11,16) : hasAptToday.start_time}) - <span style="color:var(--primary-color)">Marcar otro ingreso</span></div>
                        </div>
                    `;
                }
                return `
                    <div class="search-result-item" onclick="openAttendanceModal(${hasAptToday.id}, ${hasAptToday.therapist_id || null})">
                        <div style="font-weight:600;">${p.name}</div>
                        <div style="font-size:0.75rem;color:var(--primary-color);font-weight:700;">MARCAR ASISTENCIA (Cita ${hasAptToday.start_time})</div>
                    </div>
                `;
            } else {
                return `
                    <div class="search-result-item" onclick="walkInAttendance(${p.id}, '${p.name.replace(/'/g, "\\'")}')">
                        <div style="font-weight:600;">${p.name}</div>
                        <div style="font-size:0.75rem;color:var(--warning);">Ingreso sin cita (Walk-in)</div>
                    </div>
                `;
            }
        }).join('');

        // Opcion nuevo paciente
        html += `
            <div class="search-result-item" style="border-top:1px solid var(--border-color); color:var(--primary-color); font-weight:600;" onclick="openModal('modalNuevoPaciente')">
                <span class="material-icons-outlined" style="font-size:1rem; vertical-align:middle;">person_add</span> Es un paciente nuevo...
            </div>
        `;
        
        resultsDiv.innerHTML = html;
        resultsDiv.style.display = 'block';
    } else {
        resultsDiv.innerHTML = `
            <div class="search-result-item text-muted">No se encontro al paciente</div>
            <div class="search-result-item" style="color:var(--primary-color); font-weight:600;" onclick="openModal('modalNuevoPaciente')">
                <span class="material-icons-outlined" style="font-size:1rem; vertical-align:middle;">person_add</span> Registrar como nuevo...
            </div>
        `;
        resultsDiv.style.display = 'block';
    }
}

// Para cerrar buscador
document.addEventListener('click', (e) => {
    if (!e.target.closest('#checkin_search')) {
        const checkinResults = document.getElementById('checkin_results');
        if(checkinResults) checkinResults.style.display = 'none';
    }
});

function openAttendanceModal(aptId, currentTherapistId) {
    document.getElementById('select_attendance_mode').value = 'put';
    document.getElementById('select_attendance_apt_id').value = aptId;
    if (currentTherapistId) {
        document.getElementById('select_attendance_therapist').value = currentTherapistId;
    }
    openModal('modalSelectTherapist');
}

async function confirmQuickAttendance() {
    const mode = document.getElementById('select_attendance_mode').value;
    const selectEl = document.getElementById('select_attendance_therapist');
    const therapistId = selectEl.value;
    const therapistName = selectEl.options[selectEl.selectedIndex].text;
    
    closeModal('modalSelectTherapist');
    
    if (mode === 'put') {
        const aptId = document.getElementById('select_attendance_apt_id').value;
        try {
            const res = await SyncManager.fetch('api/appointments.php', {
                method: 'PUT',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ id: aptId, status: 'completed', therapist_id: therapistId })
            });
            const json = await res.json();
            if (json.success) {
                const msg = json.offline ? 'Guardado offline' : 'Asignado a ' + therapistName;
                showToast(msg, json.offline ? 'warning' : 'success');
                if (!json.offline) updateAttendanceRow(aptId, 'completed', new Date(), therapistName);
            } else { showToast(json.error, 'error'); }
        } catch(e) { showToast('Error de conexion', 'error'); }
    } else if (mode === 'post') {
        const patientId = document.getElementById('select_attendance_patient_id').value;
        const name = document.getElementById('select_attendance_patient_name').value;
        
        const now = new Date();
        const startTime = now.getHours().toString().padStart(2, '0') + ":" + now.getMinutes().toString().padStart(2, '0');
        const endDate = new Date(now.getTime() + 60 * 60 * 1000);
        const endTime = endDate.getHours().toString().padStart(2, '0') + ":" + endDate.getMinutes().toString().padStart(2, '0');
        
        const data = {
            patient_id: patientId,
            therapist_id: therapistId,
            appointment_date: '<?= $today ?>',
            start_time: startTime,
            end_time: endTime,
            type: 'Ingreso Rapido',
            status: 'completed' 
        };
        
        try {
            const res = await SyncManager.fetch('api/appointments.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (json.success) {
                const msg = json.offline ? 'Ingreso guardado offline' : 'Ingreso registrado con ' + therapistName;
                showToast(msg, json.offline ? 'warning' : 'success');
                if (!json.offline) {
                    appendWalkInAttendance({
                        id: json.id,
                        patient_id: patientId,
                        patient_name: name,
                        therapist_name: therapistName,
                        checked_in_at: new Date().toISOString()
                    });
                }
            } else { showToast(json.error, 'error'); }
        } catch(e) { showToast('Error de conexion', 'error'); }
    }
}

async function quickAttendance(aptId, status) {
    try {
        const res = await SyncManager.fetch('api/appointments.php', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id: aptId, status: status })
        });
        const json = await res.json();
        if (json.success) {
            const msg = json.offline ? 'Guardado offline' : (status === 'scheduled' ? 'Asistencia revertida' : 'Asistencia guardada');
            showToast(msg, json.offline ? 'warning' : 'success');
            if (!json.offline) updateAttendanceRow(aptId, status, new Date());
        } else { showToast(json.error, 'error'); }
    } catch(e) { showToast('Error de conexion', 'error'); }
}

function walkInAttendance(patientId, name) {
    document.getElementById('select_attendance_mode').value = 'post';
    document.getElementById('select_attendance_patient_id').value = patientId;
    document.getElementById('select_attendance_patient_name').value = name;
    openModal('modalSelectTherapist');
}

function updateAttendanceStats(fromStatus, toStatus) {
    const pendingEl = document.getElementById('attendanceCountPending');
    const attendedEl = document.getElementById('attendanceCountAttended');
    const absentEl = document.getElementById('attendanceCountAbsent');
    if (!pendingEl || !attendedEl || !absentEl) return;

    const counts = {
        scheduled: Number(pendingEl.textContent || 0),
        completed: Number(attendedEl.textContent || 0),
        cancelled: Number(absentEl.textContent || 0)
    };

    if (fromStatus && counts[fromStatus] !== undefined) counts[fromStatus] = Math.max(0, counts[fromStatus] - 1);
    if (toStatus && counts[toStatus] !== undefined) counts[toStatus] += 1;

    pendingEl.textContent = counts.scheduled;
    attendedEl.textContent = counts.completed;
    absentEl.textContent = counts.cancelled;
}

function formatAttendanceHour(value) {
    const date = value instanceof Date ? value : new Date(value);
    return date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
}

function updateAttendanceRow(aptId, status, checkedInAt, therapistName = null) {
    const row = document.getElementById('attendance-apt-' + aptId);
    if (!row) return;

    const previousStatus = row.dataset.status || 'scheduled';
    row.dataset.status = status;
    updateAttendanceStats(previousStatus, status);

    const iconWrap = row.querySelector('.list-item-icon');
    const icon = iconWrap?.querySelector('.material-icons-outlined');
    const actions = row.querySelector('.attendance-actions');
    const content = row.querySelector('.list-item-content');
    const statusMap = {
        scheduled: { bg: '#e0f2fe', fg: '#0369a1', icon: 'timer', text: 'AGENDADA' },
        completed: { bg: '#d1fae5', fg: '#065f46', icon: 'check_circle', text: 'COMPLETADA' },
        cancelled: { bg: '#fee2e2', fg: '#991b1b', icon: 'block', text: 'CANCELADA' }
    };
    const meta = statusMap[status];
    if (!meta) return;

    if (iconWrap) {
        iconWrap.style.background = meta.bg;
        iconWrap.style.color = meta.fg;
    }
    if (icon) icon.textContent = meta.icon;

    if (therapistName) {
        const detailsEl = row.querySelector('.pt-details-therapist');
        if (detailsEl) {
            detailsEl.innerHTML = detailsEl.innerHTML.replace(/&middot;.*$/, '&middot; ' + therapistName);
        }
    }

    let checkinEl = row.querySelector('.attendance-checkin-time');
    if (status === 'completed') {
        if (!checkinEl) {
            checkinEl = document.createElement('div');
            checkinEl.className = 'attendance-checkin-time';
            checkinEl.style.fontSize = '0.72rem';
            checkinEl.style.color = 'var(--success)';
            checkinEl.style.fontWeight = '600';
            content.appendChild(checkinEl);
        }
        checkinEl.textContent = 'Llego: ' + formatAttendanceHour(checkedInAt || new Date());
    } else if (checkinEl) {
        checkinEl.remove();
    }

    if (actions) {
        if (status === 'scheduled') {
            const tId = document.getElementById('select_attendance_therapist') ? document.getElementById('select_attendance_therapist').value : 'null';
            actions.innerHTML = `
                <div style="display:flex; gap:0.5rem;">
                    <button onclick="event.stopPropagation(); openAttendanceModal(${aptId}, ${tId})" 
                        style="background:var(--success); color:white; border:none; padding:0.35rem 0.65rem; border-radius:var(--radius-sm); font-size:0.75rem; font-weight:600; cursor:pointer;">
                        Presente
                    </button>
                    <button onclick="event.stopPropagation(); quickAttendance(${aptId}, 'cancelled')" 
                        style="background:var(--border-color); color:var(--text-muted); border:none; padding:0.35rem 0.65rem; border-radius:var(--radius-sm); font-size:0.75rem; font-weight:600; cursor:pointer;">
                        Falto
                    </button>
                </div>
            `;
        } else {
            const revertButton = currentAttendanceRole === 'admin'
                ? `<button onclick="event.stopPropagation(); quickAttendance(${aptId}, 'scheduled')" style="background:#fff7ed; color:#9a3412; border:1px solid #fdba74; padding:0.35rem 0.65rem; border-radius:var(--radius-sm); font-size:0.72rem; font-weight:600; cursor:pointer;">Revertir</button>`
                : '';
            actions.innerHTML = `<div style="display:flex; gap:0.5rem; align-items:center; justify-content:flex-end;"><span style="font-size:0.7rem; font-weight:700; color:${meta.fg};">${meta.text}</span>${revertButton}</div>`;
        }
    }
}

function appendWalkInAttendance(apt) {
    const list = document.getElementById('attendanceList');
    const empty = document.getElementById('attendanceEmptyState');
    const totalEl = document.getElementById('attendanceCountTotal');
    if (!list || !totalEl) return;

    list.style.display = '';
    if (empty) empty.style.display = 'none';
    totalEl.textContent = Number(totalEl.textContent || 0) + 1;
    updateAttendanceStats(null, 'completed');

    const row = document.createElement('div');
    row.className = 'list-item';
    row.id = 'attendance-apt-' + apt.id;
    row.dataset.status = 'completed';
    row.style.padding = '0.65rem 1rem';
    row.style.cursor = 'pointer';
    row.onclick = () => { window.location = 'patient_profile.php?id=' + apt.patient_id; };
    row.innerHTML = `
        <div class="list-item-icon" style="background:#d1fae5; color:#065f46; width:36px; height:36px;">
            <span class="material-icons-outlined" style="font-size:1rem;">check_circle</span>
        </div>
        <div class="list-item-content">
            <div style="font-size:0.875rem;font-weight:600;color:var(--primary-color);">${apt.patient_name}</div>
            <div style="font-size:0.75rem;color:var(--text-muted);">${formatAttendanceHour(apt.checked_in_at)} &middot; ${apt.therapist_name}</div>
            <div class="attendance-checkin-time" style="font-size:0.72rem;color:var(--success);font-weight:600;">Llego: ${formatAttendanceHour(apt.checked_in_at)}</div>
        </div>
        <div class="attendance-actions">
            ${currentAttendanceRole === 'admin' ? `<div style="display:flex; gap:0.5rem; align-items:center; justify-content:flex-end;"><span style="font-size:0.7rem; font-weight:700; color:#065f46;">COMPLETADA</span><button onclick="event.stopPropagation(); quickAttendance(${apt.id}, 'scheduled')" style="background:#fff7ed; color:#9a3412; border:1px solid #fdba74; padding:0.35rem 0.65rem; border-radius:var(--radius-sm); font-size:0.72rem; font-weight:600; cursor:pointer;">Revertir</button></div>` : `<span style="font-size:0.7rem; font-weight:700; color:#065f46;">COMPLETADA</span>`}
        </div>
    `;
    list.prepend(row);
}
</script>

<!-- Modal Reagendar -->
<div id="rescheduleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--card-bg,#fff);border-radius:16px;padding:1.25rem;width:300px;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
    <h3 style="margin:0 0 1rem;font-size:1rem;font-weight:600;">Reagendar cita</h3>
    <label style="display:block;font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;">Nueva fecha</label>
    <input type="date" id="rescheduleDate" style="width:100%;padding:0.55rem;border:1px solid var(--border-color,#e5e7eb);border-radius:10px;margin-bottom:0.75rem;font-size:0.9rem;">
    <label style="display:block;font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;">Hora de inicio</label>
    <input type="time" id="rescheduleTime" style="width:100%;padding:0.55rem;border:1px solid var(--border-color,#e5e7eb);border-radius:10px;margin-bottom:1rem;font-size:0.9rem;">
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
      <button onclick="cerrarReschedule()" style="padding:0.5rem 1rem;border-radius:10px;border:1px solid var(--border-color,#e5e7eb);background:none;cursor:pointer;font-size:0.85rem;">Cancelar</button>
      <button onclick="confirmarReschedule()" style="padding:0.5rem 1rem;border-radius:10px;border:none;background:var(--primary-color,#0d9488);color:#fff;cursor:pointer;font-size:0.85rem;font-weight:600;">Reagendar</button>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
