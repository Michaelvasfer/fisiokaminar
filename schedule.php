<?php
// schedule.php - Agenda y Citas
require_once 'db.php';
ensurePublicIntakeSchema($pdo);
$pageTitle = 'Agenda';
require_once 'includes/header.php';

// Fecha seleccionada (hoy por defecto)
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$displayDate  = date('d/m/Y', strtotime($selectedDate));

// Fetch citas según rol
$stmt = pdoQuery($pdo, "
    SELECT a.*, u.name AS patient_name, u.phone AS patient_phone, t.name AS therapist_name
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    JOIN users t ON a.therapist_id = t.id
    WHERE a.appointment_date = ?
    ORDER BY a.start_time ASC
", [$selectedDate]);
$appointments = $stmt->fetchAll();

// Días de la semana para nav rápida
$weekDays = [];
$mon = new DateTime($selectedDate);
$mon->modify('monday this week');
for ($i = 0; $i < 7; $i++) {
    $d = clone $mon;
    $d->modify("+$i days");
    $weekDays[] = $d;
}
?>

<div class="animate-fade-in delay-100">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h1 style="margin:0;">Agenda</h1>
        <?php if(in_array($userRole, ['admin', 'receptionist'])): ?>
        <button class="btn-primary" style="width:auto;padding:0.5rem 1rem;display:flex;gap:0.4rem;align-items:center;" onclick="openModal('modalNuevaCita')">
            <span class="material-icons-outlined" style="font-size:1.1rem;">event</span>Nueva Cita
        </button>
        <?php endif; ?>
    </div>

    <!-- Navegación de días de la semana -->
    <div class="card mb-4" style="padding:0.75rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
            <a href="schedule.php?date=<?= date('Y-m-d', strtotime($selectedDate . ' -7 days')) ?>" style="color:var(--text-muted);display:flex;">
                <span class="material-icons-outlined">chevron_left</span>
            </a>
            <span style="font-size:0.875rem;font-weight:600;color:var(--text-muted);"><?= date('F Y', strtotime($selectedDate)) ?></span>
            <a href="schedule.php?date=<?= date('Y-m-d', strtotime($selectedDate . ' +7 days')) ?>" style="color:var(--text-muted);display:flex;">
                <span class="material-icons-outlined">chevron_right</span>
            </a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;">
            <?php
            $dayLabels = ['Lu','Ma','Mi','Ju','Vi','Sá','Do'];
            foreach ($weekDays as $i => $d):
                $isSelected = $d->format('Y-m-d') === $selectedDate;
                $isToday    = $d->format('Y-m-d') === date('Y-m-d');
            ?>
            <a href="schedule.php?date=<?= $d->format('Y-m-d') ?>" style="text-decoration:none;">
                <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                    <span style="font-size:0.65rem;color:var(--text-muted);font-weight:500;"><?= $dayLabels[$i] ?></span>
                    <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.875rem;font-weight:<?= $isSelected ? '700' : '500' ?>;background:<?= $isSelected ? 'var(--primary-color)' : ($isToday ? 'var(--primary-light)' : 'transparent') ?>;color:<?= $isSelected ? 'white' : ($isToday ? 'var(--primary-color)' : 'var(--text-main)') ?>;">
                        <?= $d->format('j') ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Lista de citas -->
    <div>
        <h2 id="scheduleCountLabel" style="font-size:1rem;font-weight:600;color:var(--text-muted);margin-bottom:0.75rem;">
            <?= $displayDate ?> — <?= count($appointments) ?> cita(s)
        </h2>

        <div class="list-group" id="appointmentsList" style="<?= count($appointments) > 0 ? '' : 'display:none;' ?>">
            <?php foreach($appointments as $apt): ?>
            <?php
                $statusColor = 'var(--primary-light)';
                $statusText = 'Agendada';
                switch($apt['status']) {
                    case 'completed': $statusColor = '#d1fae5'; $statusText = 'Completada'; break;
                    case 'cancelled': $statusColor = '#fee2e2'; $statusText = 'Cancelada'; break;
                }
            ?>
            <div class="list-item" id="apt-<?= $apt['id'] ?>"
                 style="cursor:pointer;"
                 onclick="window.location='patient_profile.php?id=<?= $apt['patient_id'] ?>'">
                <div class="list-item-icon" style="background:<?= $statusColor ?>;">
                    <span class="material-icons-outlined" style="font-size:1.4rem;">
                        <?= $apt['status'] === 'completed' ? 'check_circle' : ($apt['status'] === 'cancelled' ? 'cancel' : 'person') ?>
                    </span>
                </div>
                <div class="list-item-content">
                    <div class="list-item-title" style="color:var(--primary-color);font-weight:600;"><?= htmlspecialchars($apt['patient_name']) ?></div>
                    <div class="list-item-subtitle">
                        <?= date('h:i A', strtotime($apt['start_time'])) ?> – <?= date('h:i A', strtotime($apt['end_time'])) ?>
                        <?php if($apt['therapist_name']): ?> · <?= htmlspecialchars($apt['therapist_name']) ?><?php endif; ?>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($apt['type']) ?></div>
                    <?php if(($apt['source_channel'] ?? '') === 'public_intake'): ?>
                    <div style="margin-top:4px;">
                        <span class="badge" style="background:#ecfeff;color:#0f766e;font-size:0.68rem;">Ingreso Web / WhatsApp</span>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-end;">
                    <?php if($apt['status'] === 'scheduled'): ?>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                        <!-- WhatsApp Reminder -->
                        <?php 
                            $msgDate = date('d/m', strtotime($apt['appointment_date']));
                            $msgTime = date('h:i A', strtotime($apt['start_time']));
                            $jsName = json_encode($apt['patient_name']);
                            $jsPhone = json_encode($apt['patient_phone']);
                            $jsDate = json_encode($msgDate);
                            $jsTime = json_encode($msgTime);
                            $jsTherapist = json_encode($apt['therapist_name']);
                        ?>
                        <button onclick='event.stopPropagation(); sendReminder(<?= $jsName ?>, <?= $jsPhone ?>, <?= $jsDate ?>, <?= $jsTime ?>, <?= $jsTherapist ?>)'
                            class="btn-whatsapp-sm" title="WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </button>

                        <?php if(in_array($userRole, ['admin','receptionist','therapist'])): ?>
                        <button onclick='event.stopPropagation(); markAppointment(<?= (int)$apt['id'] ?>, "completed")'
                            class="btn-action-sm btn-success">
                            <span class="material-icons-outlined" style="font-size:1.1rem;">check</span> Asistió
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(in_array($userRole, ['admin','receptionist'])): ?>
                    <button onclick='event.stopPropagation(); rescheduleAppointment(<?= (int)$apt['id'] ?>, <?= json_encode(substr($apt['start_time'],0,5)) ?>, <?= json_encode(substr($apt['end_time'],0,5)) ?>)'
                        class="btn-action-sm" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">
                        <span class="material-icons-outlined" style="font-size:1.1rem;">event</span> Reagendar
                    </button>
                    <button onclick='event.stopPropagation(); markAppointment(<?= (int)$apt['id'] ?>, "cancelled")'
                        class="btn-action-sm btn-cancel">
                        <span class="material-icons-outlined" style="font-size:1.1rem;">close</span> Cancelar
                    </button>
                    <?php endif; ?>

                    <?php else: ?>
                    <span class="badge" style="background:<?= $statusColor ?>;font-size:0.7rem;"><?= $statusText ?></span>
                    <?php endif; ?>

                    <?php if($userRole === 'admin'): ?>
                    <button onclick='event.stopPropagation(); deleteAppointment(<?= $apt['id'] ?>)'
                        style="background:none;color:var(--danger);border:none;cursor:pointer;padding:0.25rem;display:flex;align-items:center;" title="Eliminar">
                        <span class="material-icons-outlined" style="font-size:1rem;">delete</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="scheduleEmptyState" style="text-align:center;padding:3rem 0;color:var(--text-muted);<?= count($appointments) > 0 ? 'display:none;' : '' ?>">
            <span class="material-icons-outlined" style="font-size:3rem;display:block;margin-bottom:0.5rem;opacity:0.4;">event_busy</span>
            No hay citas para este día.
            <?php if(in_array($userRole, ['admin','receptionist'])): ?>
            <br><br>
            <button class="btn-primary" style="width:auto;padding:0.5rem 1.25rem;" onclick="openModal('modalNuevaCita')">Agendar cita</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
window.handleAppointmentCreated = (apt) => {
    if (!apt || apt.appointment_date !== '<?= $selectedDate ?>') return;
    const list = document.getElementById('appointmentsList');
    const empty = document.getElementById('scheduleEmptyState');
    const countLabel = document.getElementById('scheduleCountLabel');
    if (!list || !countLabel) return;

    list.style.display = '';
    if (empty) empty.style.display = 'none';

    const countMatch = countLabel.textContent.match(/(\d+)\s+cita/);
    const nextCount = countMatch ? Number(countMatch[1]) + 1 : 1;
    countLabel.textContent = '<?= $displayDate ?> — ' + nextCount + ' cita(s)';

    const wrapper = document.createElement('div');
    wrapper.className = 'list-item';
    wrapper.id = 'apt-' + apt.id;
    wrapper.style.cursor = 'pointer';
    wrapper.onclick = () => { window.location = 'patient_profile.php?id=' + apt.patient_id; };
    wrapper.innerHTML = `
        <div class="list-item-icon" style="background:var(--primary-light);">
            <span class="material-icons-outlined" style="font-size:1.4rem;">person</span>
        </div>
        <div class="list-item-content">
            <div class="list-item-title" style="color:var(--primary-color);font-weight:600;">${apt.patient_name}</div>
            <div class="list-item-subtitle">${formatHour(apt.start_time)} – ${formatHour(apt.end_time)}${apt.therapist_name ? ' · ' + apt.therapist_name : ''}</div>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">${apt.type}</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-end;">
            <span class="badge" style="background:var(--primary-light);font-size:0.7rem;">Agendada</span>
        </div>
    `;
    list.prepend(wrapper);
};

function formatHour(time) {
    const [h, m] = (time || '00:00').split(':');
    let hour = Number(h);
    const suffix = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return String(hour).padStart(2, '0') + ':' + m + ' ' + suffix;
}

window.handleAppointmentStatusChanged = (id, status) => {
    const row = document.getElementById('apt-' + id);
    if (!row) return;

    const iconWrap = row.querySelector('.list-item-icon');
    const icon = iconWrap?.querySelector('.material-icons-outlined');
    const actionsWrap = row.lastElementChild;
    const statusMap = {
        completed: { bg: '#d1fae5', icon: 'check_circle', text: 'Completada' },
        cancelled: { bg: '#fee2e2', icon: 'cancel', text: 'Cancelada' }
    };
    const meta = statusMap[status];
    if (!meta) return;

    if (iconWrap) iconWrap.style.background = meta.bg;
    if (icon) icon.textContent = meta.icon;
    if (actionsWrap) {
        actionsWrap.innerHTML = `<span class="badge" style="background:${meta.bg};font-size:0.7rem;">${meta.text}</span>`;
    }
};

window.handleAppointmentDeleted = (id) => {
    const row = document.getElementById('apt-' + id);
    const list = document.getElementById('appointmentsList');
    const empty = document.getElementById('scheduleEmptyState');
    const countLabel = document.getElementById('scheduleCountLabel');
    if (!row || !list || !countLabel) return;

    row.remove();
    const countMatch = countLabel.textContent.match(/(\d+)\s+cita/);
    const nextCount = Math.max((countMatch ? Number(countMatch[1]) : 1) - 1, 0);
    countLabel.textContent = '<?= $displayDate ?> — ' + nextCount + ' cita(s)';

    if (nextCount === 0) {
        list.style.display = 'none';
        if (empty) empty.style.display = '';
    }
};
</script>

<?php require_once 'includes/footer.php'; ?>
