<?php
// admin.php - Panel de Administracion (solo admin)
require_once 'db.php';
ensureReferralSchema($pdo);
ensurePackagesSchema($pdo);
ensureAuditSchema($pdo);

try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('custom_permissions', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN custom_permissions TINYINT(1) DEFAULT 0");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        permission_key VARCHAR(50) NOT NULL,
        UNIQUE KEY idx_user_perm (user_id, permission_key),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

$pageTitle = 'Panel de Admin';
require_once 'includes/header.php';

if ($userRole !== 'admin') {
    header("Location: index.php");
    exit;
}

$totalPatients = pdoQuery($pdo, "SELECT COUNT(*) as c FROM users WHERE role='patient'")->fetch()['c'];
$totalStaff = pdoQuery($pdo, "SELECT COUNT(*) as c FROM users WHERE role IN ('admin','receptionist','therapist','referrer')")->fetch()['c'];
$todayApts = pdoQuery($pdo, "SELECT COUNT(*) as c FROM appointments WHERE appointment_date = CURDATE()")->fetch()['c'];
$monthIncome = pdoQuery($pdo, "SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE amount > 0 AND MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW())")->fetch()['total'];
$pendingApts = pdoQuery($pdo, "SELECT COUNT(*) as c FROM appointments WHERE status='scheduled' AND appointment_date >= CURDATE()")->fetch()['c'];
$pendingReferralCash = 0;

$staffUsers = pdoQuery($pdo, "SELECT * FROM users WHERE role IN ('admin','receptionist','therapist','referrer') ORDER BY role, name ASC")->fetchAll();
$patients = pdoQuery($pdo, "SELECT id, name, dni, phone, patient_code FROM users WHERE role = 'patient' ORDER BY name ASC LIMIT 150")->fetchAll();
$packageTemplates = pdoQuery($pdo, "SELECT id, name, total_sessions, total_amount, is_active FROM package_templates ORDER BY is_active DESC, total_sessions ASC, total_amount ASC, name ASC")->fetchAll();
$referralRewards = [];
$auditLogs = [];
$backupRuns = [];
$lastBackup = null;

if (!function_exists('humanAuditAction')) {
    function humanAuditAction($actionKey) {
        $map = [
            'auth.login' => 'Inicio de sesion',
            'auth.logout' => 'Cierre de sesion',
            'user.create' => 'Usuario creado',
            'user.update' => 'Usuario actualizado',
            'user.delete' => 'Usuario eliminado',
            'user.permissions.update' => 'Permisos actualizados',
            'user.password.reset_to_dni' => 'Clave reseteada al DNI',
            'user.password.admin_change' => 'Clave cambiada por admin',
            'user.password.self_change' => 'Clave actualizada',
            'patient.create' => 'Paciente creado',
            'patient.update' => 'Paciente actualizado',
            'patient.delete' => 'Paciente eliminado',
            'appointment.create' => 'Cita creada',
            'appointment.update' => 'Cita actualizada',
            'appointment.delete' => 'Cita eliminada',
            'payment.create' => 'Pago registrado',
            'payment.delete' => 'Pago eliminado',
            'expense.create' => 'Gasto registrado',
            'expense.delete' => 'Gasto eliminado',
            'fixed_expense.create' => 'Gasto fijo registrado',
            'fixed_expense.delete' => 'Gasto fijo eliminado',
            'package_template.create' => 'Paquete base creado',
            'package.create' => 'Paquete asignado',
            'package.update' => 'Paquete actualizado',
            'package.delete' => 'Paquete eliminado',
            'protocol.create' => 'Protocolo creado',
            'protocol.update' => 'Protocolo actualizado',
            'protocol.delete' => 'Protocolo eliminado',
            'protocol.assign' => 'Plan de tratamiento asignado',
            'clinical_history.create' => 'Historia clinica creada',
            'session_note.create' => 'Nota clinica guardada',
            'session_note.delete' => 'Nota clinica eliminada',
            'patient_session.update' => 'Seguimiento de sesion actualizado',
            'exercise.assign' => 'Ejercicio asignado',
            'exercise.remove' => 'Ejercicio quitado',
            'photo.upload' => 'Foto de evolucion subida',
            'referral_reward.mark_paid' => 'Comision de referido pagada',
            'backup.run' => 'Backup ejecutado',
            'backup.error' => 'Backup con error',
            'db.insert' => 'Registro creado',
            'db.update' => 'Registro actualizado',
            'db.delete' => 'Registro eliminado',
        ];

        return $map[$actionKey] ?? ucwords(str_replace(['.', '_'], ' ', (string)$actionKey));
    }
}

try {
    $pendingReferralCash = (float)(pdoQuery($pdo, "SELECT COALESCE(SUM(remaining_amount), 0) AS total FROM referral_rewards WHERE reward_mode = 'cash' AND status = 'pending'")->fetch()['total'] ?? 0);
    $referralRewards = pdoQuery($pdo, "
        SELECT
            rr.id,
            rr.generated_amount,
            rr.remaining_amount,
            rr.status,
            rr.generated_at,
            beneficiary.name AS beneficiary_name,
            source_patient.name AS source_patient_name
        FROM referral_rewards rr
        JOIN users beneficiary ON beneficiary.id = rr.beneficiary_user_id
        JOIN users source_patient ON source_patient.id = rr.source_patient_id
        WHERE rr.reward_mode = 'cash'
        ORDER BY (rr.status = 'pending') DESC, rr.generated_at DESC
        LIMIT 80
    ")->fetchAll();
} catch (Exception $e) {}

try {
    $auditLogs = pdoQuery($pdo, "
        SELECT id, user_name, user_role, action_key, entity_type, entity_id, details_json, created_at
        FROM audit_logs
        ORDER BY created_at DESC, id DESC
        LIMIT 40
    ")->fetchAll();
} catch (Exception $e) {}

try {
    $backupRuns = pdoQuery($pdo, "
        SELECT id, run_type, status, backup_file, file_size_bytes, notes, started_at, finished_at
        FROM backup_runs
        ORDER BY started_at DESC, id DESC
        LIMIT 20
    ")->fetchAll();
    $lastBackup = $backupRuns[0] ?? null;
} catch (Exception $e) {}
?>

<div class="animate-fade-in delay-100">
    <h1 style="margin-bottom:1.5rem;">Panel de Admin</h1>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1.5rem;">
        <div class="metric-card">
            <div class="metric-label">Pacientes</div>
            <div class="metric-value"><?= $totalPatients ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Citas Hoy</div>
            <div class="metric-value"><?= $todayApts ?></div>
        </div>
        <div class="metric-card" style="cursor:pointer;" onclick="window.location='admin_protocols.php'">
            <div class="metric-label">Protocolos</div>
            <div class="metric-value">Config</div>
        </div>
        <div class="metric-card" style="grid-column:span 2;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div class="metric-label">Ingresos este mes</div>
                    <div class="metric-value" style="font-size:1.5rem;">S/ <?= number_format($monthIncome, 2) ?></div>
                </div>
                <a href="reports.php" class="btn-primary" style="width:auto;padding:0.4rem 0.8rem;font-size:0.8rem;display:flex;align-items:center;gap:0.25rem;">
                    <span class="material-icons-outlined" style="font-size:1rem;">analytics</span> Ver Reportes
                </a>
            </div>
        </div>
    </div>


    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:#2563eb;font-size:1.1rem;">share</span>
                Comisiones de Referidos
            </h2>
            <div style="font-size:0.85rem;font-weight:700;color:#1d4ed8;">Pendiente S/ <?= number_format($pendingReferralCash, 2) ?></div>
        </div>

        <?php if (count($referralRewards) > 0): ?>
        <div class="list-group">
            <?php foreach ($referralRewards as $reward): ?>
            <div class="list-item" style="padding:0.75rem;">
                <div class="list-item-icon" style="width:42px;height:42px;background:#eff6ff;color:#1d4ed8;font-size:1rem;">
                    <span class="material-icons-outlined" style="font-size:1.1rem;">paid</span>
                </div>
                <div class="list-item-content">
                    <div style="font-size:0.875rem;font-weight:700;"><?= htmlspecialchars($reward['beneficiary_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">
                        Referido: <?= htmlspecialchars(app_text($reward['source_patient_name'] ?? '')) ?> &middot; <?= date('d/m/Y', strtotime($reward['generated_at'])) ?>
                    </div>
                    <div style="font-size:0.78rem;font-weight:700;color:#1d4ed8;margin-top:0.2rem;">
                        Comisi&oacute;n S/ <?= number_format((float)$reward['generated_amount'], 2) ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:0.4rem;">
                    <?php if ($reward['status'] === 'pending'): ?>
                    <span style="font-size:0.72rem;font-weight:700;background:#eff6ff;color:#1d4ed8;padding:0.2rem 0.55rem;border-radius:999px;">
                        Pendiente
                    </span>
                    <button onclick="markReferralRewardPaid(<?= (int)$reward['id'] ?>)" class="btn-action-sm btn-success" style="height:30px;width:auto;padding:0 0.75rem;">
                        Marcar pagado
                    </button>
                    <?php else: ?>
                    <span style="font-size:0.72rem;font-weight:700;background:#f0fdf4;color:#15803d;padding:0.2rem 0.55rem;border-radius:999px;">
                        Pagado
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="padding:1rem;color:var(--text-muted);font-size:0.85rem;">Todav&iacute;a no hay comisiones externas generadas.</div>
        <?php endif; ?>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:#f59e0b;font-size:1.1rem;">inventory_2</span>
                Cat&aacute;logo de Paquetes
            </h2>
        </div>
        <div style="padding:1rem;border-bottom:1px solid var(--border-color);background:var(--background);">
            <form onsubmit="createPackageTemplate(event)">
                <div style="display:grid;grid-template-columns:1.4fr 0.8fr 0.8fr;gap:0.75rem;">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" id="pt_name" class="form-control" placeholder="Paquete 10 sesiones" required>
                    </div>
                    <div class="form-group">
                        <label>Sesiones</label>
                        <input type="number" id="pt_sessions" class="form-control" value="10" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Monto total</label>
                        <input type="number" id="pt_amount" class="form-control" value="350" min="0.01" step="0.01" required>
                    </div>
                </div>
                <button type="submit" class="btn-primary" id="btnCreateTemplate">
                    <span class="material-icons-outlined" style="vertical-align:middle;font-size:1rem;">save</span> Guardar Paquete Base
                </button>
            </form>
        </div>
        <?php if (count($packageTemplates) > 0): ?>
        <div class="list-group">
            <?php foreach ($packageTemplates as $template): ?>
            <div class="list-item" style="padding:0.75rem;">
                <div class="list-item-icon" style="width:42px;height:42px;background:#fff7ed;color:#b45309;font-size:1rem;">
                    <span class="material-icons-outlined" style="font-size:1.1rem;">redeem</span>
                </div>
                <div class="list-item-content">
                    <div style="font-size:0.88rem;font-weight:700;"><?= htmlspecialchars($template['name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">
                        <?= (int)$template['total_sessions'] ?> sesiones &middot; S/ <?= number_format((float)$template['total_amount'], 2) ?>
                    </div>
                </div>
                <div>
                    <span style="font-size:0.72rem;font-weight:700;background:<?= $template['is_active'] ? '#f0fdf4' : '#f8fafc' ?>;color:<?= $template['is_active'] ? '#15803d' : '#64748b' ?>;padding:0.2rem 0.55rem;border-radius:999px;">
                        <?= $template['is_active'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="padding:1rem;color:var(--text-muted);font-size:0.85rem;">Todav&iacute;a no hay paquetes base creados.</div>
        <?php endif; ?>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.1rem;">manage_accounts</span>
                Usuarios del Sistema
            </h2>
            <button onclick="document.getElementById('newUserForm').classList.toggle('hidden-form')"
                style="background:var(--primary-color);color:white;border:none;border-radius:var(--radius-sm);padding:0.4rem 0.75rem;cursor:pointer;display:flex;align-items:center;gap:0.25rem;font-size:0.8rem;font-weight:600;">
                <span class="material-icons-outlined" style="font-size:0.9rem;">person_add</span>Nuevo
            </button>
        </div>

        <div id="newUserForm" class="hidden-form" style="background:var(--background);padding:1rem;border-radius:var(--radius-md);margin-bottom:1rem;">
            <form onsubmit="createUser(event)">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" id="nu_name" class="form-control" placeholder="Nombre completo" required>
                    </div>
                    <div class="form-group">
                        <label>Rol *</label>
                        <select id="nu_role" class="form-control" required>
                            <option value="receptionist">Secretaria</option>
                            <option value="therapist">Fisioterapeuta</option>
                            <option value="referrer">Jaladora</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Correo *</label>
                    <input type="email" id="nu_email" class="form-control" placeholder="correo@fisioapp.com" required>
                </div>
                <div class="form-group">
                    <label>Contrasena inicial</label>
                    <input type="text" id="nu_password" class="form-control" placeholder="password" value="password">
                </div>
                <button type="submit" class="btn-primary" id="btnCreateUser">
                    <span class="material-icons-outlined" style="vertical-align:middle;font-size:1rem;">save</span> Crear Usuario
                </button>
            </form>
        </div>

        <div class="list-group">
            <?php foreach ($staffUsers as $u): ?>
            <?php
                switch ($u['role']) {
                    case 'admin':
                        $roleLabel = ['Admin', '#7c3aed', '#f5f3ff'];
                        break;
                    case 'receptionist':
                        $roleLabel = ['Secretaria', '#0d9488', '#f0fdfa'];
                        break;
                    case 'therapist':
                        $roleLabel = ['Fisioterapeuta', '#1975d2', '#e3f2fd'];
                        break;
                    case 'referrer':
                        $roleLabel = ['Jaladora', '#b45309', '#fff7ed'];
                        break;
                    default:
                        $roleLabel = ['-', '#64748b', '#f8fafc'];
                        break;
                }
            ?>
            <div class="list-item" id="staff-<?= $u['id'] ?>" style="padding:0.75rem;">
                <div class="list-item-icon" style="width:42px;height:42px;background:<?= $roleLabel[2] ?>;color:<?= $roleLabel[1] ?>;font-size:1.1rem;">
                    <?= app_upper(app_substr($u['name'], 0, 1)) ?>
                </div>
                <div class="list-item-content">
                    <div style="font-size:0.875rem;font-weight:600; opacity: <?= $u['is_active'] ? '1' : '0.5' ?>;">
                        <?= htmlspecialchars($u['name']) ?>
                        <?php if (!$u['is_active']): ?><span style="font-size:0.65rem; color:var(--danger); border:1px solid; padding:0 0.2rem; border-radius:3px; margin-left:0.3rem;">INACTIVO</span><?php endif; ?>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
                </div>
                <div style="display:flex;align-items:center;gap:0.4rem;">
                    <span style="font-size:0.7rem;font-weight:600;background:<?= $roleLabel[2] ?>;color:<?= $roleLabel[1] ?>;padding:0.2rem 0.5rem;border-radius:99px;"><?= $roleLabel[0] ?></span>
                    <button onclick="toggleUserStatus(<?= $u['id'] ?>, <?= $u['is_active'] ?>)"
                        style="background:none;color:<?= $u['is_active'] ? 'var(--success)' : 'var(--text-muted)' ?>;border:none;cursor:pointer;padding:0.2rem;"
                        title="<?= $u['is_active'] ? 'Desactivar' : 'Activar' ?>">
                        <span class="material-icons-outlined" style="font-size:1rem;"><?= $u['is_active'] ? 'toggle_on' : 'toggle_off' ?></span>
                    </button>
                    <button onclick="openChangePwd(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')"
                        style="background:none;color:var(--primary-color);border:none;cursor:pointer;padding:0.2rem;" title="Cambiar contrasena">
                        <span class="material-icons-outlined" style="font-size:1rem;">key</span>
                    </button>
                    <button onclick="openUserPermissions(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')"
                        style="background:none;color:#f59e0b;border:none;cursor:pointer;padding:0.2rem;" title="Permisos Especiales">
                        <span class="material-icons-outlined" style="font-size:1rem;">security</span>
                    </button>
                    <?php if ($u['id'] != $userId): ?>
                    <button onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')"
                        style="background:none;color:var(--danger);border:none;cursor:pointer;padding:0.2rem;" title="Eliminar">
                        <span class="material-icons-outlined" style="font-size:1rem;">delete</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:var(--danger);font-size:1.1rem;">group_remove</span>
                Pacientes
            </h2>
        </div>

        <?php if (count($patients) > 0): ?>
        <div class="list-group">
            <?php foreach ($patients as $p): ?>
            <div class="list-item" id="patient-<?= (int)$p['id'] ?>" style="padding:0.75rem;">
                <div class="list-item-icon" style="width:42px;height:42px;background:#eff6ff;color:#2563eb;font-size:1.1rem;">
                    <?= app_upper(app_substr($p['name'], 0, 1)) ?>
                </div>
                <div class="list-item-content">
                    <div style="font-size:0.875rem;font-weight:600;">
                        <?= htmlspecialchars(app_text($p['name'] ?? '')) ?>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">
                        DNI: <?= htmlspecialchars($p['dni'] ?? '-') ?>
                        <?php if (!empty($p['phone'])): ?> &middot; Tel: <?= htmlspecialchars($p['phone']) ?><?php endif; ?>
                        <?php if (!empty($p['patient_code'])): ?> &middot; <?= htmlspecialchars(app_text($p['patient_code'] ?? '')) ?><?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:0.4rem;">
                    <a href="patient_profile.php?id=<?= (int)$p['id'] ?>" class="btn-action-sm btn-outline" style="height:32px;width:auto;padding:0 0.75rem;">
                        Ver
                    </a>
                    <button onclick="deletePatient(<?= (int)$p['id'] ?>, '<?= addslashes($p['name']) ?>')" style="background:none;color:var(--danger);border:none;cursor:pointer;padding:0.2rem;" title="Eliminar paciente">
                        <span class="material-icons-outlined" style="font-size:1rem;">delete</span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="padding:1rem;color:var(--text-muted);font-size:0.85rem;">No hay pacientes registrados.</div>
        <?php endif; ?>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:#0f766e;font-size:1.1rem;">history</span>
                Trazabilidad
            </h2>
            <div style="font-size:0.78rem;color:var(--text-muted);">Ultimos 40 movimientos</div>
        </div>
        <?php if (count($auditLogs) > 0): ?>
        <div class="list-group">
            <?php foreach ($auditLogs as $log): ?>
            <?php
                $details = [];
                if (!empty($log['details_json'])) {
                    $decoded = json_decode($log['details_json'], true);
                    if (is_array($decoded)) {
                        $details = $decoded;
                    }
                }
                $detailSummary = [];
                foreach ($details as $key => $value) {
                    if ($value === null || $value === '' || is_array($value) || is_object($value)) {
                        continue;
                    }
                    $detailSummary[] = $key . ': ' . $value;
                    if (count($detailSummary) >= 2) {
                        break;
                    }
                }
            ?>
            <div class="list-item" style="padding:0.75rem;">
                <div class="list-item-icon" style="width:42px;height:42px;background:#ecfeff;color:#0f766e;font-size:1rem;">
                    <span class="material-icons-outlined" style="font-size:1.1rem;">fact_check</span>
                </div>
                <div class="list-item-content">
                    <div style="font-size:0.85rem;font-weight:700;"><?= htmlspecialchars(humanAuditAction($log['action_key'] ?? '')) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">
                        <?= htmlspecialchars(app_text($log['user_name'] ?? 'Sistema')) ?>
                        <?php if (!empty($log['user_role'])): ?> &middot; <?= htmlspecialchars(app_text($log['user_role'])) ?><?php endif; ?>
                        <?php if (!empty($log['entity_type'])): ?> &middot; <?= htmlspecialchars(app_text($log['entity_type'])) ?> <?= htmlspecialchars((string)($log['entity_id'] ?? '')) ?><?php endif; ?>
                    </div>
                    <?php if ($detailSummary): ?>
                    <div style="font-size:0.74rem;color:#475569;margin-top:0.2rem;"><?= htmlspecialchars(implode(' | ', $detailSummary)) ?></div>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.74rem;font-weight:600;color:var(--text-muted);white-space:nowrap;">
                    <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="padding:1rem;color:var(--text-muted);font-size:0.85rem;">Aun no hay movimientos auditados.</div>
        <?php endif; ?>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:#7c3aed;font-size:1.1rem;">backup</span>
                Backups
            </h2>
            <button onclick="runManualBackup()" id="btnRunBackup"
                style="background:#7c3aed;color:white;border:none;border-radius:var(--radius-sm);padding:0.45rem 0.8rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;font-size:0.8rem;font-weight:700;">
                <span class="material-icons-outlined" style="font-size:1rem;">save</span> Ejecutar ahora
            </button>
        </div>
        <div style="padding:1rem;border-bottom:1px solid var(--border-color);background:var(--background);">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                <div class="metric-card" style="padding:0.85rem;">
                    <div class="metric-label">Ultimo backup</div>
                    <div style="font-size:0.95rem;font-weight:800;color:var(--text-main);">
                        <?= $lastBackup ? date('d/m/Y H:i', strtotime($lastBackup['started_at'])) : 'Sin registros' ?>
                    </div>
                </div>
                <div class="metric-card" style="padding:0.85rem;">
                    <div class="metric-label">Estado</div>
                    <div style="font-size:0.95rem;font-weight:800;color:<?= ($lastBackup && ($lastBackup['status'] ?? '') === 'success') ? '#15803d' : '#b91c1c' ?>;">
                        <?= $lastBackup ? ucfirst($lastBackup['status']) : 'Pendiente' ?>
                    </div>
                </div>
            </div>
            <div style="font-size:0.76rem;color:var(--text-muted);margin-top:0.75rem;">
                Backup diario listo para programar con PHP CLI en <code>tasks/backup_daily.php</code>. Cada ejecucion limpia backups y trazabilidad mayores a 60 dias.
            </div>
        </div>
        <?php if (count($backupRuns) > 0): ?>
        <div class="list-group">
            <?php foreach ($backupRuns as $backup): ?>
            <div class="list-item" style="padding:0.75rem;">
                <div class="list-item-icon" style="width:42px;height:42px;background:#f5f3ff;color:#7c3aed;font-size:1rem;">
                    <span class="material-icons-outlined" style="font-size:1.1rem;">folder_zip</span>
                </div>
                <div class="list-item-content">
                    <div style="font-size:0.85rem;font-weight:700;"><?= htmlspecialchars($backup['backup_file'] ?: 'Sin archivo') ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">
                        <?= ucfirst($backup['run_type']) ?> &middot; <?= date('d/m/Y H:i', strtotime($backup['started_at'])) ?>
                        <?php if (!empty($backup['file_size_bytes'])): ?> &middot; <?= number_format(((float)$backup['file_size_bytes']) / 1024, 1) ?> KB<?php endif; ?>
                    </div>
                    <?php if (!empty($backup['notes'])): ?>
                    <div style="font-size:0.74rem;color:#475569;margin-top:0.2rem;"><?= htmlspecialchars(app_text($backup['notes'])) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <span style="font-size:0.72rem;font-weight:700;background:<?= ($backup['status'] === 'success') ? '#f0fdf4' : (($backup['status'] === 'running') ? '#eff6ff' : '#fef2f2') ?>;color:<?= ($backup['status'] === 'success') ? '#15803d' : (($backup['status'] === 'running') ? '#1d4ed8' : '#b91c1c') ?>;padding:0.2rem 0.55rem;border-radius:999px;">
                        <?= ucfirst($backup['status']) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="padding:1rem;color:var(--text-muted);font-size:0.85rem;">Aun no hay backups ejecutados.</div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal: Permisos de Usuario -->
<div class="modal-overlay" id="modalPermissions">
    <div class="modal-sheet" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title"><span class="material-icons-outlined" style="vertical-align:middle;color:#f59e0b">security</span> Permisos Especiales</h3>
            <button class="modal-close" onclick="closeModal('modalPermissions')"><span class="material-icons-outlined">close</span></button>
        </div>
        <p id="permUserName" style="font-weight:600;color:var(--text-muted);margin-bottom:1rem;font-size:0.9rem;"></p>

        <div style="background:#fff7ed; padding:0.75rem; border-radius:var(--radius-sm); border:1px solid #ffedd5; margin-bottom:1rem;">
            <label style="display:flex; align-items:center; gap:0.5rem; font-weight:700; color:#9a3412; cursor:pointer;">
                <input type="checkbox" id="perm_custom_enabled"> Habilitar Permisos Personalizados
            </label>
            <p style="font-size:0.7rem; color:#c2410c; margin:0.25rem 0 0 1.5rem;">Si se activa, el usuario tendra estos permisos en lugar de los de su rol base.</p>
        </div>

        <div id="perms_list" style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-bottom:1.5rem;">
            <div style="grid-column: span 2; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); padding-top: 0.5rem;">Citas</div>
            <label><input type="checkbox" class="perm-check" value="view_apt"> Ver Citas</label>
            <label><input type="checkbox" class="perm-check" value="add_apt"> Agregar Citas</label>
            <label><input type="checkbox" class="perm-check" value="edit_apt"> Modificar Citas</label>
            <label><input type="checkbox" class="perm-check" value="delete_apt"> Borrar Citas</label>

            <div style="grid-column: span 2; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); padding-top: 0.5rem;">Pagos</div>
            <label><input type="checkbox" class="perm-check" value="view_payment"> Ver Pagos</label>
            <label><input type="checkbox" class="perm-check" value="add_payment"> Agregar Pagos</label>
            <label><input type="checkbox" class="perm-check" value="edit_payment"> Editar Pagos</label>
            <label><input type="checkbox" class="perm-check" value="delete_payment"> Borrar Pagos</label>

            <div style="grid-column: span 2; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); padding-top: 0.5rem;">Pacientes / Clinica</div>
            <label><input type="checkbox" class="perm-check" value="view_patient"> Ver Pacientes</label>
            <label><input type="checkbox" class="perm-check" value="edit_patient"> Editar Pacientes</label>
            <label><input type="checkbox" class="perm-check" value="add_clinical_hx"> Historia Clinica</label>
            <label><input type="checkbox" class="perm-check" value="add_note"> Agregar Notas</label>
        </div>

        <button class="btn-primary" id="btnSavePermissions" onclick="saveUserPermissions()">
            <span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar Permisos
        </button>
    </div>
</div>

<!-- Modal: Cambiar Clave de Usuario -->
<div class="modal-overlay" id="modalAdminPwd">
    <div class="modal-sheet" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color)">key</span> Cambiar Clave</h3>
            <button class="modal-close" onclick="closeModal('modalAdminPwd')"><span class="material-icons-outlined">close</span></button>
        </div>
        <p id="adminPwdUserName" style="font-weight:600;color:var(--text-muted);margin-bottom:1rem;font-size:0.9rem;"></p>

        <div class="form-group">
            <label for="admin_new_pwd">Nueva clave</label>
            <input type="password" id="admin_new_pwd" class="form-control" placeholder="Minimo 6 caracteres" minlength="6" required>
        </div>

        <div class="form-group">
            <label for="admin_confirm_pwd">Confirmar clave</label>
            <input type="password" id="admin_confirm_pwd" class="form-control" placeholder="Repite la nueva clave" minlength="6" required>
        </div>

        <button class="btn-primary" id="btnAdminPwd" onclick="submitAdminPwd()">
            <span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar
        </button>
    </div>
</div>

<script>
const adminCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let _permUserId = null;

async function createPackageTemplate(e) {
    e.preventDefault();
    const btn = document.getElementById('btnCreateTemplate');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const data = {
        action: 'create_template',
        name: document.getElementById('pt_name').value.trim(),
        total_sessions: parseInt(document.getElementById('pt_sessions').value || '0', 10),
        total_amount: parseFloat(document.getElementById('pt_amount').value || '0')
    };

    try {
        const res = await fetch('api/packages.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': adminCsrfToken},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            showToast('Paquete base creado', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showToast(json.error || 'No se pudo crear el paquete base', 'error');
        }
    } catch (e2) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class=\"material-icons-outlined\" style=\"vertical-align:middle;font-size:1rem;\">save</span> Guardar Paquete Base';
    }
}


async function markReferralRewardPaid(rewardId) {
    if (!confirm('\u00BFMarcar esta comisi\u00F3n como pagada?')) return;

    try {
        const res = await fetch('api/referrals.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminCsrfToken
            },
            body: JSON.stringify({
                reward_id: rewardId,
                action: 'mark_paid'
            })
        });

        const json = await res.json();
        if (json.success) {
            showToast('Comisi\u00F3n marcada como pagada', 'success');
            setTimeout(() => location.reload(), 600);
            return;
        }

        showToast(json.error || 'No se pudo actualizar la comisi\u00F3n', 'error');
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function openUserPermissions(id, name) {
    _permUserId = id;
    document.getElementById('permUserName').textContent = 'Usuario: ' + name;
    document.querySelectorAll('.perm-check').forEach(cb => cb.checked = false);
    document.getElementById('perm_custom_enabled').checked = false;

    try {
        const res = await fetch(`api/permissions.php?user_id=${id}`);
        const json = await res.json();
        if (json.success) {
            document.getElementById('perm_custom_enabled').checked = json.custom_enabled;
            json.permissions.forEach(pk => {
                const cb = document.querySelector(`.perm-check[value="${pk}"]`);
                if (cb) cb.checked = true;
            });
        }
    } catch (e) {
        showToast('Error al cargar permisos', 'error');
    }

    openModal('modalPermissions');
}

async function saveUserPermissions() {
    const btn = document.getElementById('btnSavePermissions');
    const perms = Array.from(document.querySelectorAll('.perm-check:checked')).map(cb => cb.value);
    const customEnabled = document.getElementById('perm_custom_enabled').checked ? 1 : 0;

    btn.disabled = true;
    btn.textContent = 'Guardando...';
    try {
        const res = await fetch('api/permissions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': adminCsrfToken},
            body: JSON.stringify({
                user_id: _permUserId,
                permissions: perms,
                custom_enabled: customEnabled
            })
        });
        const json = await res.json();
        if (json.success) {
            showToast('Permisos actualizados', 'success');
            closeModal('modalPermissions');
        } else {
            showToast(json.error, 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar Permisos';
    }
}

let _changePwdUserId = null;
function openChangePwd(id, name) {
    _changePwdUserId = id;
    document.getElementById('adminPwdUserName').textContent = 'Usuario: ' + name;
    document.getElementById('admin_new_pwd').value = '';
    document.getElementById('admin_confirm_pwd').value = '';
    openModal('modalAdminPwd');
}

async function submitAdminPwd() {
    const newPwd = document.getElementById('admin_new_pwd').value;
    const confirm = document.getElementById('admin_confirm_pwd').value;
    if (newPwd.length < 6) { showToast('Minimo 6 caracteres', 'error'); return; }
    if (newPwd !== confirm) { showToast('Las contrasenas no coinciden', 'error'); return; }
    const btn = document.getElementById('btnAdminPwd');
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    try {
        const res = await fetch('api/change_password.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': adminCsrfToken},
            body: JSON.stringify({ user_id: _changePwdUserId, new_password: newPwd })
        });
        const json = await res.json();
        if (json.success) {
            showToast('Contrasena actualizada', 'success');
            closeModal('modalAdminPwd');
        } else {
            showToast(json.error, 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar';
    }
}

async function createUser(e) {
    e.preventDefault();
    const btn = document.getElementById('btnCreateUser');
    btn.disabled = true;
    btn.textContent = 'Creando...';
    const data = {
        name: document.getElementById('nu_name').value,
        email: document.getElementById('nu_email').value,
        role: document.getElementById('nu_role').value,
        password: document.getElementById('nu_password').value || 'password',
    };
    try {
        const res = await fetch('api/users.php', { method:'POST', headers:{'Content-Type':'application/json', 'X-CSRF-Token': adminCsrfToken}, body: JSON.stringify(data) });
        const raw = await res.text();
        let json = null;
        try {
            json = JSON.parse(raw);
        } catch (parseError) {
            throw new Error(raw || 'Respuesta invalida del servidor');
        }
        if (json.success) {
            showToast('Usuario creado exitosamente', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(json.error || 'No se pudo crear el usuario', 'error');
        }
    } catch (e) {
        showToast((e && e.message) ? e.message : 'Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Crear Usuario';
    }
}

async function toggleUserStatus(id, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    const action = newStatus ? 'activar' : 'desactivar';
    if (!confirm('Seguro que deseas ' + action + ' este usuario?')) return;
    try {
        const res = await fetch('api/users.php', {
            method:'PUT',
            headers:{'Content-Type':'application/json', 'X-CSRF-Token': adminCsrfToken},
            body: JSON.stringify({id, is_active: newStatus})
        });
        const json = await res.json();
        if (json.success) {
            showToast('Usuario ' + (newStatus ? 'activado' : 'desactivado'), 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(json.error, 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function deleteUser(id, name) {
    if (!confirm('Eliminar al usuario "' + name + '"? Si tiene agenda o historial, el sistema lo desactivara para proteger la informacion.')) return;
    try {
        const res = await fetch('api/users.php', { method:'DELETE', headers:{'Content-Type':'application/json', 'X-CSRF-Token': adminCsrfToken}, body: JSON.stringify({id}) });
        const json = await res.json();
        if (json.success) {
            showToast(json.message || 'Usuario eliminado', 'success');
            if (json.protected) {
                setTimeout(() => location.reload(), 900);
            } else {
                document.getElementById('staff-' + id)?.remove();
            }
        } else {
            showToast(json.error, 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function deletePatient(id, name) {
    if (!confirm('Eliminar al paciente "' + name + '"? Se borrar\u00E1n tambi\u00E9n sus citas, historias, pagos, planes y archivos relacionados.')) return;
    try {
        const res = await fetch('api/patients.php', {
            method:'DELETE',
            headers:{'Content-Type':'application/json', 'X-CSRF-Token': adminCsrfToken},
            body: JSON.stringify({id})
        });
        const json = await res.json();
        if (json.success) {
            showToast('Paciente eliminado', 'success');
            document.getElementById('patient-' + id)?.remove();
        } else {
            showToast(json.error || 'No se pudo eliminar el paciente', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function runManualBackup() {
    const btn = document.getElementById('btnRunBackup');
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-outlined" style="font-size:1rem;">sync</span> Ejecutando...';
    try {
        const res = await fetch('api/backups.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': adminCsrfToken},
            body: JSON.stringify({})
        });
        const json = await res.json();
        if (json.success) {
            showToast('Backup generado', 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(json.error || 'No se pudo generar el backup', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-outlined" style="font-size:1rem;">save</span> Ejecutar ahora';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
