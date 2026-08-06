        </main>
    </div> <!-- .container finish -->

    <!-- Toast notification -->
    <div class="toast" id="appToast"></div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         MODAL: Cambiar Mi Contraseña (todos los roles)
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="modal-overlay" id="modalCambiarPassword">
        <div class="modal-sheet">
            <div class="modal-header">
                <h3 class="modal-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.2rem;">lock_reset</span> Cambiar Contraseña</h3>
                <button class="modal-close" onclick="closeModal('modalCambiarPassword')"><span class="material-icons-outlined">close</span></button>
            </div>
            <form id="formCambiarPassword" onsubmit="submitCambiarPassword(event)">
                <div class="form-group">
                    <label for="cp_current">Contraseña actual *</label>
                    <input type="password" id="cp_current" class="form-control" placeholder="Tu contraseña actual" required>
                </div>
                <div class="form-group">
                    <label for="cp_new">Nueva contraseña *</label>
                    <input type="password" id="cp_new" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="cp_confirm">Confirmar contraseña *</label>
                    <input type="password" id="cp_confirm" class="form-control" placeholder="Repite la nueva contraseña" required>
                </div>
                <button type="submit" class="btn-primary mt-4" id="btnSubmitPassword">
                    <span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar Contraseña
                </button>
            </form>
        </div>
    </div>

    <?php
    // Pre-cargar datos para modales
    $modalPatients   = [];
    $modalTherapists = [];
    $modalReferrers  = [];
    if (in_array($userRole, ['admin', 'receptionist', 'therapist'])) {
        try {
            $modalPatients   = pdoQuery($pdo, "SELECT id, name, dni FROM users WHERE role = 'patient' ORDER BY name ASC")->fetchAll();
            $modalTherapists = pdoQuery($pdo, "SELECT id, name FROM users WHERE role = 'therapist' AND is_active = 1 ORDER BY name ASC")->fetchAll();
            $modalReferrers  = pdoQuery($pdo, "SELECT id, name, email FROM users WHERE role = 'referrer' ORDER BY name ASC")->fetchAll();
        } catch(Exception $e) {}
    }
    ?>

    <?php if(in_array($userRole, ['admin', 'therapist'])): ?>
    <div class="modal-overlay" id="modalQuickNote">
        <div class="modal-sheet">
            <div class="modal-header">
                <h3 class="modal-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.2rem;">edit_note</span> Nota Rápida</h3>
                <button class="modal-close" onclick="closeModal('modalQuickNote')"><span class="material-icons-outlined">close</span></button>
            </div>
            <form id="formQuickNote" onsubmit="submitQuickNote(event)">
                <div class="form-group">
                    <label>Paciente *</label>
                    <div style="position:relative;">
                        <span id="qn_patient_search_icon" class="material-icons-outlined" style="position:absolute;left:0.75rem;top:12px;font-size:1.1rem;color:var(--text-muted);">search</span>
                        <input type="text" id="qn_patient_search" class="form-control" placeholder="Buscar por nombre o DNI..." style="padding-left:2.5rem;" oninput="searchPatientQuick(this.value, 'qn')">
                        <input type="hidden" id="qn_patient" name="patient_id" required>
                        <input type="hidden" id="qn_appointment_id" name="appointment_id">
                        <div id="qn_patient_results" class="search-results-floating" style="display:none;"></div>
                    </div>
                    <div id="qn_patient_selected" style="margin-top:0.5rem;display:none;">
                        <div style="display:flex;align-items:center;justify-content:space-between;background:var(--primary-light);padding:0.5rem 1rem;border-radius:var(--radius-md);border:1px solid var(--primary-color);">
                            <span id="selected_patient_name_qn" style="font-weight:600;color:var(--primary-dark);font-size:0.9rem;"></span>
                            <button type="button" onclick="clearSelectedPatient('qn')" style="background:none;border:none;color:var(--primary-dark);cursor:pointer;display:flex;"><span class="material-icons-outlined" style="font-size:1rem;">close</span></button>
                        </div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label for="qn_note_kind">Tipo de nota</label>
                        <select id="qn_note_kind" class="form-control" onchange="updateQuickNoteTitle()">
                            <option value="Seguimiento de plan">Seguimiento de plan</option>
                            <option value="Terapia individual">Terapia individual</option>
                            <option value="Nota de sesión">Nota de sesión</option>
                            <option value="Incidencia clínica">Incidencia clínica</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="qn_session_date">Fecha</label>
                        <input type="date" id="qn_session_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="qn_title">Título</label>
                    <input type="text" id="qn_title" class="form-control" value="Nota de sesión">
                </div>
                <div class="form-group">
                    <label for="qn_notes">Observación clínica *</label>
                    <textarea id="qn_notes" class="form-control" rows="5" placeholder="Escribe una evolución breve, dolor EVA, respuesta al tratamiento o indicación..." required></textarea>
                </div>
                <div id="qn_context_hint" style="margin-top:0.25rem;font-size:0.8rem;color:var(--text-muted);"></div>
                <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modalQuickNote')" style="flex:1;">Cancelar</button>
                    <button type="submit" class="btn-primary" id="btnSubmitQuickNote" style="flex:1;">Guardar Nota</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if(in_array($userRole, ['admin', 'receptionist', 'therapist'])): ?>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         MODAL: Nuevo Paciente
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="modal-overlay" id="modalNuevoPaciente">
        <div class="modal-sheet">
            <div class="modal-header">
                <h3 class="modal-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.2rem;">person_add</span> Nuevo Paciente</h3>
                <button class="modal-close" onclick="closeModal('modalNuevoPaciente')"><span class="material-icons-outlined">close</span></button>
            </div>
            <form id="formNuevoPaciente" onsubmit="submitNuevoPaciente(event)">
                <div class="form-group">
                    <label for="np_name">Nombre completo *</label>
                    <input type="text" id="np_name" name="name" class="form-control" placeholder="Ej: Juan Pérez" required>
                </div>
                <div class="form-group">
                    <label for="np_dni">DNI *</label>
                    <input type="text" id="np_dni" name="dni" class="form-control" placeholder="Número de DNI" required>
                </div>
                <div class="form-group">
                    <label for="np_email">Correo electrónico (opcional)</label>
                    <input type="email" id="np_email" name="email" class="form-control" placeholder="Ej: juan@email.com">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label for="np_age">Edad</label>
                        <input type="number" id="np_age" name="age" class="form-control" placeholder="35" min="1" max="120">
                    </div>
                    <div class="form-group">
                        <label for="np_phone">Teléfono *</label>
                        <input type="tel" id="np_phone" name="phone" class="form-control" placeholder="999888777" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="np_referrer_kind">Referido por</label>
                    <select id="np_referrer_kind" class="form-control" onchange="toggleNewPatientReferrerFields()">
                        <option value="">Sin referido</option>
                        <option value="patient">Paciente actual</option>
                        <option value="referrer">Jaladora externa</option>
                    </select>
                </div>
                <div class="form-group" id="np_referrer_patient_group" style="display:none;">
                    <label for="np_referrer_patient_id">Paciente que refiere</label>
                    <select id="np_referrer_patient_id" class="form-control">
                        <option value="">Seleccionar paciente</option>
                        <?php foreach($modalPatients as $mp): ?>
                        <option value="<?= (int)$mp['id'] ?>"><?= htmlspecialchars($mp['name']) ?><?= !empty($mp['dni']) ? ' · DNI ' . htmlspecialchars($mp['dni']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="np_referrer_external_group" style="display:none;">
                    <label for="np_referrer_external_id">Jaladora</label>
                    <select id="np_referrer_external_id" class="form-control">
                        <option value="">Seleccionar jaladora</option>
                        <?php foreach($modalReferrers as $mr): ?>
                        <option value="<?= (int)$mr['id'] ?>"><?= htmlspecialchars($mr['name']) ?><?= !empty($mr['email']) ? ' · ' . htmlspecialchars($mr['email']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary mt-4" id="btnSubmitPaciente">
                    <span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar Paciente
                </button>
            </form>
        </div>
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         MODAL: Nueva Cita
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="modal-overlay" id="modalNuevaCita">
        <div class="modal-sheet">
            <div class="modal-header">
                <h3 class="modal-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.2rem;">event</span> Nueva Cita</h3>
                <button class="modal-close" onclick="closeModal('modalNuevaCita')"><span class="material-icons-outlined">close</span></button>
            </div>
            <form id="formNuevaCita" onsubmit="submitNuevaCita(event)">
                <div class="form-group">
                    <label>Paciente Id *</label>
                    <div style="position:relative;">
                        <span id="cp_patient_search_icon" class="material-icons-outlined" style="position:absolute;left:0.75rem;top:12px;font-size:1.1rem;color:var(--text-muted);">search</span>
                        <input type="text" id="nc_patient_search" class="form-control" placeholder="Buscar por nombre o DNI..." style="padding-left:2.5rem;" oninput="searchPatientQuick(this.value)">
                        <input type="hidden" id="nc_patient" name="patient_id" required>
                        
                        <!-- Lista de resultados flotante -->
                        <div id="nc_patient_results" class="search-results-floating" style="display:none;"></div>
                    </div>
                    <!-- Chip de paciente seleccionado -->
                    <div id="nc_patient_selected" style="margin-top:0.5rem;display:none;">
                        <div style="display:flex;align-items:center;justify-content:space-between;background:var(--primary-light);padding:0.5rem 1rem;border-radius:var(--radius-md);border:1px solid var(--primary-color);">
                            <span id="selected_patient_name" style="font-weight:600;color:var(--primary-dark);font-size:0.9rem;"></span>
                            <button type="button" onclick="clearSelectedPatient()" style="background:none;border:none;color:var(--primary-dark);cursor:pointer;display:flex;"><span class="material-icons-outlined" style="font-size:1rem;">close</span></button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nc_date">FechaCita</label>
                    <div style="position:relative;">
                        <input type="date" id="nc_date" name="appointment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="nc_start">Hora de la cita</label>
                    <div style="position:relative;">
                        <input type="time" id="nc_start" name="start_time" class="form-control" required step="1800" min="08:00" max="19:30" onchange="handleAppointmentTimeChange(this.value)">
                        <span class="material-icons-outlined" style="position:absolute;right:0.75rem;top:12px;font-size:1.1rem;color:var(--text-muted);pointer-events:none;">schedule</span>
                    </div>
                    <div class="time-helper-text">Horario de atención: 8:00 AM a 7:30 PM</div>
                    <div class="time-slots-grid" id="nc_time_slots"></div>
                </div>

                <div class="form-group">
                    <label>EstadoCita</label>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;">
                        <button type="button" class="btn-status-toggle active" data-status="scheduled" onclick="setStatus('scheduled', this)">Pendiente</button>
                        <button type="button" class="btn-status-toggle" data-status="completed" onclick="setStatus('completed', this)">Asistió</button>
                        <button type="button" class="btn-status-toggle" data-status="cancelled" onclick="setStatus('cancelled', this)">No asistió</button>
                    </div>
                    <input type="hidden" id="nc_status" name="status" value="scheduled">
                </div>

                <div style="display:none;">
                    <input type="time" id="nc_end" name="end_time" value="10:00">
                    <select id="nc_therapist" name="therapist_id">
                         <?php foreach($modalTherapists as $t): ?>
                         <option value="<?= $t['id'] ?>" <?= $t['id'] == $userId ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                         <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="nc_type">Tipo de sesión</label>
                    <input type="text" id="nc_type" name="type" class="form-control" placeholder="Ej: Rehabilitación Lumbar" value="Sesión General">
                </div>

                <div style="display:flex;gap:1rem;margin-top:2rem;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modalNuevaCita')" style="flex:1;">Cancelar</button>
                    <button type="submit" class="btn-primary" id="btnSubmitCita" style="flex:1;">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         MODAL: Registrar Pago
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="modal-overlay" id="modalCobrarPago">
        <div class="modal-sheet">
            <div class="modal-header">
                <h3 class="modal-title"><span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.2rem;">payments</span> Registrar Pago</h3>
                <button class="modal-close" onclick="closeModal('modalCobrarPago')"><span class="material-icons-outlined">close</span></button>
            </div>
            <form id="formCobrarPago" onsubmit="submitCobrarPago(event)">
                <div class="form-group">
                    <label>Paciente Id *</label>
                    <div style="position:relative;">
                        <span class="material-icons-outlined" style="position:absolute;left:0.75rem;top:12px;font-size:1.1rem;color:var(--text-muted);">search</span>
                        <input type="text" id="cp_patient_search" class="form-control" placeholder="Buscar por nombre o DNI..." style="padding-left:2.5rem;" oninput="searchPatientQuick(this.value, 'cp')">
                        <input type="hidden" id="cp_patient" name="patient_id" required>
                        
                        <!-- Lista de resultados flotante -->
                        <div id="cp_patient_results" class="search-results-floating" style="display:none;"></div>
                    </div>
                    <!-- Chip de paciente seleccionado -->
                    <div id="cp_patient_selected" style="margin-top:0.5rem;display:none;">
                        <div style="display:flex;align-items:center;justify-content:space-between;background:var(--primary-light);padding:0.5rem 1rem;border-radius:var(--radius-md);border:1px solid var(--primary-color);">
                            <span id="selected_patient_name_cp" style="font-weight:600;color:var(--primary-dark);font-size:0.9rem;"></span>
                            <button type="button" onclick="clearSelectedPatient('cp')" style="background:none;border:none;color:var(--primary-dark);cursor:pointer;display:flex;"><span class="material-icons-outlined" style="font-size:1rem;">close</span></button>
                        </div>
                    </div>
                <div id="cp_credit_summary" style="display:none;margin:0.25rem 0 1rem;padding:0.85rem;border:1px solid #bfdbfe;border-radius:var(--radius-md);background:#eff6ff;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                        <div>
                            <div style="font-size:0.72rem;color:#1d4ed8;text-transform:uppercase;font-weight:700;">Saldo por referidos</div>
                            <div style="font-size:1rem;font-weight:800;color:#1e3a8a;">S/ <span id="cp_credit_available">0.00</span></div>
                        </div>
                        <label style="display:flex;align-items:center;gap:0.45rem;font-size:0.82rem;font-weight:600;color:#1e40af;cursor:pointer;">
                            <input type="checkbox" id="cp_use_referral_credit" onchange="updatePaymentTotals()"> Usar saldo disponible
                        </label>
                    </div>
                    <div id="cp_credit_breakdown" style="display:none;margin-top:0.6rem;font-size:0.78rem;color:#1e3a8a;">
                        Se aplicará <strong>S/ <span id="cp_credit_to_apply">0.00</span></strong> y se cobrará en caja <strong>S/ <span id="cp_cash_to_collect">0.00</span></strong>.
                    </div>
                </div>
                </div>
                <div class="form-group">
                    <label for="cp_amount">Monto del servicio (S/) *</label>
                    <input type="number" id="cp_amount" name="amount" class="form-control" placeholder="0.00" step="0.01" min="0.01" required oninput="updatePaymentTotals()">
                </div>
                <div class="form-group">
                    <label for="cp_method">Método de pago *</label>
                    <select id="cp_method" name="payment_method" class="form-control" required>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia bancaria</option>
                        <option value="Tarjeta">Tarjeta de crédito/débito</option>
                        <option value="Yape">Yape</option>
                        <option value="Plin">Plin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cp_package_id">Aplicar a paquete</label>
                    <select id="cp_package_id" name="package_id" class="form-control">
                        <option value="">-- Pago libre / por sesión --</option>
                    </select>
                    <div id="cp_package_help" style="display:none;margin-top:0.5rem;font-size:0.78rem;color:var(--text-muted);"></div>
                    <button type="button" id="cp_create_package_btn" onclick="openCreatePackageFromPayment()" class="btn-secondary" style="display:none;margin-top:0.6rem;padding:0.7rem 0.9rem;">
                        Asignar paquete al paciente
                    </button>
                </div>
                <div class="form-group">
                    <label for="cp_description">Descripción *</label>
                    <input type="text" id="cp_description" name="description" class="form-control" placeholder="Ej: Sesión #5, Paquete 10 sesiones..." required value="Pago de sesión">
                </div>
                <button type="submit" class="btn-primary mt-4" id="btnSubmitPago">
                    <span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">check_circle</span> Confirmar Pago
                </button>
            </form>
        </div>
    </div>

    <?php endif; ?>

    <!-- FAB â€” opciones se despliegan de abajo hacia arriba -->
    <?php if(in_array($userRole, ['admin', 'receptionist'])): ?>
    <div class="fab-container">
        <!-- Opciones primero â†’ aparecen ENCIMA del botÃ³n -->
        <div class="fab-options" id="fabOptions">
            <a href="attendance.php" class="fab-option">
                <span class="fab-label">Marcar Asistencia</span>
                <div class="fab-icon"><span class="material-icons-outlined">fact_check</span></div>
            </a>
            <a href="#" class="fab-option" onclick="openModal('modalNuevoPaciente'); closeFab(); return false;">
                <span class="fab-label">Nuevo Paciente</span>
                <div class="fab-icon"><span class="material-icons-outlined">person_add</span></div>
            </a>
            <a href="#" class="fab-option" onclick="openModal('modalNuevaCita'); closeFab(); return false;">
                <span class="fab-label">Nueva Cita</span>
                <div class="fab-icon"><span class="material-icons-outlined">event</span></div>
            </a>
            <a href="#" class="fab-option" onclick="openModal('modalCobrarPago'); closeFab(); return false;">
                <span class="fab-label">Cobrar Pago</span>
                <div class="fab-icon"><span class="material-icons-outlined">payments</span></div>
            </a>
        </div>
        <!-- BotÃ³n + abajo -->
        <button class="fab" id="mainFab" title="Acciones rápidas">
            <span class="material-icons-outlined">add</span>
        </button>
    </div>
    <?php endif; ?>

    <?php if($userRole === 'therapist'): ?>
    <div class="fab-container">
        <div class="fab-options" id="fabOptions">
            <a href="#" class="fab-option" onclick="openQuickNoteModal(); closeFab(); return false;">
                <span class="fab-label">Nota rápida</span>
                <div class="fab-icon"><span class="material-icons-outlined">edit_note</span></div>
            </a>
            <a href="attendance.php" class="fab-option">
                <span class="fab-label">Asistencia</span>
                <div class="fab-icon"><span class="material-icons-outlined">fact_check</span></div>
            </a>
            <a href="patients.php" class="fab-option">
                <span class="fab-label">Buscar paciente</span>
                <div class="fab-icon"><span class="material-icons-outlined">person_search</span></div>
            </a>
            <a href="#" class="fab-option" onclick="openModal('modalNuevaCita'); closeFab(); return false;">
                <span class="fab-label">Nueva cita</span>
                <div class="fab-icon"><span class="material-icons-outlined">event</span></div>
            </a>
        </div>
        <button class="fab" id="mainFab" title="Acciones clínicas rápidas">
            <span class="material-icons-outlined">add</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Bottom Navigation -->
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <nav class="bottom-nav">
        <a href="<?= $userRole === 'referrer' ? 'referrer_portal.php' : 'index.php' ?>" class="nav-item <?= ($currentPage == 'index.php') ? 'active' : '' ?>">
            <i class="material-icons-outlined">grid_view</i>
            <span class="nav-label">Inicio</span>
        </a>

        <?php if(in_array($userRole, ['admin', 'receptionist', 'therapist'])): ?>
        <a href="attendance.php" class="nav-item <?= ($currentPage == 'attendance.php') ? 'active' : '' ?>">
            <i class="material-icons-outlined">fact_check</i>
            <span class="nav-label">Asistencia</span>
        </a>
        <a href="schedule.php" class="nav-item <?= ($currentPage == 'schedule.php') ? 'active' : '' ?>">
            <i class="material-icons-outlined">calendar_today</i>
            <span class="nav-label">Agenda</span>
        </a>
        <a href="patients.php" class="nav-item <?= ($currentPage == 'patients.php' || $currentPage == 'patient_profile.php') ? 'active' : '' ?>">
            <i class="material-icons-outlined">group</i>
            <span class="nav-label">Pacientes</span>
        </a>
        <?php endif; ?>

        <?php if($userRole === 'patient'): ?>
        <a href="paciente_progreso.php" class="nav-item <?= ($currentPage == 'paciente_progreso.php') ? 'active' : '' ?>">
            <i class="material-icons-outlined">timeline</i>
            <span class="nav-label">Mi Tratamiento</span>
        </a>
        <a href="patient_profile.php?id=<?= $userId ?>" class="nav-item <?= ($currentPage == 'patient_profile.php') ? 'active' : '' ?>">
            <i class="material-icons-outlined">person</i>
            <span class="nav-label">Mi Perfil</span>
        </a>
        <?php endif; ?>

        <?php if($userRole === 'referrer'): ?>
        <a href="referrer_portal.php" class="nav-item <?= ($currentPage == 'referrer_portal.php') ? 'active' : '' ?>">
            <i class="material-icons-outlined">group</i>
            <span class="nav-label">Referidos</span>
        </a>
        <?php endif; ?>

        <?php if($userRole === 'admin'): ?>
        <a href="admin_protocols.php" class="nav-item <?= ($currentPage == 'admin_protocols.php') ? 'active' : '' ?>">
            <i class="material-icons-outlined">settings_suggest</i>
            <span class="nav-label">Protocolos</span>
        </a>
        <?php endif; ?>

        <?php if(in_array($userRole, ['admin', 'receptionist'])): ?>
        <a href="financials.php" class="nav-item <?= ($currentPage == 'financials.php') ? 'active' : '' ?>">
            <i class="material-icons-outlined">account_balance_wallet</i>
            <span class="nav-label">Pagos</span>
        </a>
        <?php endif; ?>

        <?php if($userRole === 'admin'): ?>
        <a href="admin.php" class="nav-item <?= ($currentPage == 'admin.php') ? 'active' : '' ?>">
            <i class="material-icons-outlined">admin_panel_settings</i>
            <span class="nav-label">Admin</span>
        </a>
        <?php endif; ?>
    </nav>

    <?php $appJsVersion = @filemtime(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "js" . DIRECTORY_SEPARATOR . "app.js") ?: time(); ?>
    <script src="js/app.js?v=<?= (int)$appJsVersion ?>"></script>
    <script>
    const appCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â• MODAL SYSTEM â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Auto-seleccionar paciente si estamos en su perfil
        if (typeof window.currentPatient !== 'undefined' && window.currentPatient.id) {
            if (id === 'modalNuevaCita') selectPatient(window.currentPatient.id, window.currentPatient.name, 'nc');
            if (id === 'modalCobrarPago') selectPatient(window.currentPatient.id, window.currentPatient.name, 'cp');
        }

        if (id === 'modalNuevaCita') {
            prepareAppointmentTimeOptions(true);
        }
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = '';
        
        // Limpiar selecciÃ³n si es necesario al cerrar? No, mejor dejarlo por si reabre por error.
    }
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    document.addEventListener('DOMContentLoaded', () => {
        try {
            const pendingRedirect = sessionStorage.getItem('pendingNewHxRedirect');
            if (!pendingRedirect) return;

            const currentPath = (window.location.pathname || '').toLowerCase();
            const isAlreadyOnProfile = currentPath.includes('patient_profile.php');
            if (isAlreadyOnProfile) {
                sessionStorage.removeItem('pendingNewHxRedirect');
                return;
            }

            sessionStorage.removeItem('pendingNewHxRedirect');
            window.location.replace(pendingRedirect);
        } catch (e) {}
    });

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â• TOAST â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    function showToast(msg, type = '') {
        const t = document.getElementById('appToast');
        t.textContent = msg;
        t.className = 'toast show ' + type;
        setTimeout(() => { t.className = 'toast'; }, 3500);
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â• FAB â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    const mainFab = document.getElementById('mainFab');
    const fabOptions = document.getElementById('fabOptions');
    function closeFab() {
        if (!fabOptions) return;
        fabOptions.classList.remove('active');
        const icon = mainFab?.querySelector('.material-icons-outlined');
        if (icon) icon.style.transform = 'rotate(0deg)';
    }
    if (mainFab && fabOptions) {
        mainFab.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            fabOptions.classList.toggle('active');
            const icon = mainFab.querySelector('.material-icons-outlined');
            icon.style.transform = fabOptions.classList.contains('active') ? 'rotate(45deg)' : 'rotate(0deg)';
        });
        document.addEventListener('click', function(e) {
            if (!mainFab.contains(e.target) && !fabOptions.contains(e.target)) closeFab();
        });
    }

    function updateQuickNoteTitle() {
        const kind = document.getElementById('qn_note_kind')?.value || 'Nota de sesión';
        const titleInput = document.getElementById('qn_title');
        if (!titleInput) return;
        if (titleInput.dataset.userEdited !== '1') {
            titleInput.value = kind;
        }
    }

    function openQuickNoteModal(patientId = null, patientName = '', options = {}) {
        const titleInput = document.getElementById('qn_title');
        const noteInput = document.getElementById('qn_notes');
        const kindSelect = document.getElementById('qn_note_kind');
        const dateInput = document.getElementById('qn_session_date');
        const aptInput = document.getElementById('qn_appointment_id');
        const hint = document.getElementById('qn_context_hint');

        if (titleInput) {
            titleInput.dataset.userEdited = '0';
            titleInput.oninput = () => { titleInput.dataset.userEdited = '1'; };
        }
        if (noteInput) noteInput.value = '';
        if (dateInput) dateInput.value = '<?= date('Y-m-d') ?>';
        if (aptInput) aptInput.value = options.appointmentId || '';
        if (kindSelect) {
            kindSelect.value = options.hasActivePlan ? 'Seguimiento de plan' : 'Terapia individual';
        }
        if (hint) {
            hint.textContent = options.hasActivePlan
                ? 'Paciente con plan activo. La nota se guardará como seguimiento clínico.'
                : 'Paciente sin plan activo. La nota se guardará como terapia individual.';
        }

        updateQuickNoteTitle();
        clearSelectedPatient('qn');
        if (patientId && patientName) {
            selectPatient(patientId, patientName, 'qn');
        }
        openModal('modalQuickNote');
    }

    async function submitQuickNote(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitQuickNote');
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        const patientId = document.getElementById('qn_patient')?.value;
        const title = document.getElementById('qn_title')?.value?.trim() || document.getElementById('qn_note_kind')?.value || 'Nota de sesión';
        const notes = document.getElementById('qn_notes')?.value?.trim() || '';
        const sessionDate = document.getElementById('qn_session_date')?.value || '<?= date('Y-m-d') ?>';
        const appointmentId = document.getElementById('qn_appointment_id')?.value || '';

        if (!patientId) {
            showToast('Selecciona un paciente', 'error');
            btn.disabled = false;
            btn.textContent = 'Guardar Nota';
            return;
        }
        if (!notes) {
            showToast('Escribe una observación clínica', 'error');
            btn.disabled = false;
            btn.textContent = 'Guardar Nota';
            return;
        }

        try {
            const res = await fetch('api/sessions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': appCsrfToken
                },
                body: JSON.stringify({
                    patient_id: patientId,
                    appointment_id: appointmentId || null,
                    title,
                    notes,
                    session_date: sessionDate
                })
            });
            const json = await res.json();
            if (json.success) {
                showToast('Nota guardada', 'success');
                document.getElementById('formQuickNote')?.reset();
                clearSelectedPatient('qn');
                closeModal('modalQuickNote');
                if (window.location.pathname.includes('patient_profile.php')) {
                    window.location.reload();
                }
            } else {
                showToast(json.error || 'No se pudo guardar la nota', 'error');
            }
        } catch (err) {
            showToast('Error de conexión', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Guardar Nota';
        }
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â• NUEVO PACIENTE â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    async function submitNuevoPaciente(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitPaciente');
        btn.disabled = true; btn.textContent = 'Guardando...';
        const data = {
            name:  document.getElementById('np_name').value.trim(),
            dni:   document.getElementById('np_dni').value.trim(),
            email: document.getElementById('np_email')?.value?.trim() || '',
            birth_date: document.getElementById('np_birth_date')?.value || '',
            age:   document.getElementById('np_age').value,
            phone: document.getElementById('np_phone').value.trim()
        };
        const referrerKind = document.getElementById('np_referrer_kind')?.value || '';
        const referrerUserId = referrerKind === 'patient'
            ? (document.getElementById('np_referrer_patient_id')?.value || '')
            : (document.getElementById('np_referrer_external_id')?.value || '');

        if (referrerKind && !referrerUserId) {
            showToast('✗ Selecciona quién refirió al paciente', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar Paciente';
            return;
        }

        if (referrerKind) {
            data.referrer_kind = referrerKind;
            data.referrer_user_id = parseInt(referrerUserId, 10) || 0;
        }

        if (!/^\d{8}$/.test(data.dni)) {
            showToast('El DNI debe tener 8 dígitos', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar Paciente';
            return;
        }
        if (!/^\d{9}$/.test(data.phone)) {
            showToast('El teléfono debe tener 9 dígitos', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar Paciente';
            return;
        }
        try {
            const res  = await SyncManager.fetch('api/patients.php', { method: 'POST', headers: {'Content-Type':'application/json', 'X-CSRF-Token': appCsrfToken}, body: JSON.stringify(data) });
            const json = await res.json();
            if (json.success) {
                const msg = json.offline ? 'Paciente guardado offline' : 'Paciente "' + json.patient.name + '" creado';
                showToast(msg, json.offline ? 'warning' : 'success');
                closeModal('modalNuevoPaciente');
                document.getElementById('formNuevoPaciente').reset();
                toggleNewPatientReferrerFields();
                
                if (!json.offline) {
                    const patientId = parseInt(((json && json.patient && json.patient.id) ? json.patient.id : 0) || json.id || '0', 10);
                    const fallbackDni = encodeURIComponent(String(data.dni || (json && json.patient && json.patient.dni) || '').trim());
                    if (confirm('¿Deseas completar la Historia Clínica del nuevo paciente ahora?')) {
                        const redirectUrl = patientId
                            ? (json.redirect_profile_url || ('patient_profile.php?id=' + patientId + '&new_hx=1'))
                            : ('patient_profile.php?dni=' + fallbackDni + '&new_hx=1');
                        try {
                            if (patientId) {
                                sessionStorage.setItem('openClinicalHxFor', String(patientId));
                            }
                            sessionStorage.setItem('pendingNewHxRedirect', redirectUrl);
                        } catch (e) {}
                        window.location.assign(redirectUrl);
                    } else if (window.reloadPatients) {
                        window.reloadPatients();
                    } else {
                        window.location.reload();
                    }
                }
            } else { showToast(json.error, 'error'); }
        } catch(err) { showToast('Error de conexión', 'error'); }
        finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar Paciente';
        }
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â• NUEVA CITA â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    async function submitNuevaCita(e) {
        e.preventDefault();
        
        const patientId = document.getElementById('nc_patient').value;
        const date      = document.getElementById('nc_date').value;
        const startTime = document.getElementById('nc_start').value;
        
        if (!patientId) { showToast('Selecciona un paciente', 'error'); return; }

        // Validar inteligencia: no pasado y no domingos
        const now = new Date();
        const dateParts = getAppointmentDateParts(date);
        const selectedDate = new Date(dateParts.year, (dateParts.month || 1) - 1, dateParts.day || 1);
        const dayOfWeek = selectedDate.getDay(); // 0 = Domingo

        if (dayOfWeek === 0) {
            showToast('Los domingos no atendemos', 'error');
            return;
        }

        const dayRelation = compareAppointmentDateToToday(date);
        if (dayRelation < 0) {
             showToast('No se puede agendar en el pasado', 'error');
             return;
        } else if (dayRelation === 0) {
             const currentMinutes = (now.getHours() * 60) + now.getMinutes();
             const [hourPart, minutePart] = (startTime || '00:00').split(':').map(Number);
             const selectedMinutes = (hourPart * 60) + minutePart;
             if (selectedMinutes < currentMinutes) {
                 showToast('La hora seleccionada ya pasó', 'error');
                 return;
             }
        }

        const btn = document.getElementById('btnSubmitCita');
        btn.disabled = true; btn.textContent = 'Agendando...';
        const patientName = document.getElementById('selected_patient_name')?.textContent?.trim() || '';
        const therapistSelect = document.getElementById('nc_therapist');
        const therapistName = therapistSelect?.options[therapistSelect.selectedIndex]?.text || '';
        
        const data = {
            patient_id:       patientId,
            therapist_id:     document.getElementById('nc_therapist').value,
            appointment_date: date,
            start_time:       startTime,
            end_time:         document.getElementById('nc_end').value,
            type:             document.getElementById('nc_type').value,
            status:           document.getElementById('nc_status')?.value || 'scheduled',
            notes:            document.getElementById('nc_notes')?.value || '',
        };
        try {
            const res  = await SyncManager.fetch('api/appointments.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) });
            const json = await res.json();
            if (json.success) {
                const msg = json.offline ? 'Cita agendada offline' : 'Cita agendada exitosamente';
                showToast(msg, json.offline ? 'warning' : 'success');
                closeModal('modalNuevaCita');
                document.getElementById('formNuevaCita').reset();
                clearSelectedPatient('nc');
                if (!json.offline && typeof window.handleAppointmentCreated === 'function') {
                    window.handleAppointmentCreated({
                        id: json.id,
                        patient_id: Number(patientId),
                        patient_name: patientName,
                        therapist_id: Number(data.therapist_id),
                        therapist_name: therapistName,
                        appointment_date: data.appointment_date,
                        start_time: data.start_time,
                        end_time: data.end_time,
                        type: data.type,
                        status: data.status
                    });
                } else if (window.reloadSchedule) window.reloadSchedule();
                else if (window.location.pathname.includes('schedule')) window.location.reload();
            } else { showToast(json.error, 'error'); }
        } catch(err) { showToast('Error de conexión', 'error'); }
        finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">event_available</span> Agendar Cita';
        }
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â• COBRAR PAGO â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    function buildDuplicatePaymentMessage(duplicates) {
        const items = Array.isArray(duplicates) ? duplicates : [];
        if (items.length === 0) {
            return 'Ya existe un pago parecido registrado hoy. ¿Deseas registrarlo nuevamente?';
        }

        const lines = items.map(item => {
            const amount = Number(item.amount || 0).toFixed(2);
            const rawDate = String(item.transaction_date || '');
            const dateLabel = rawDate ? rawDate.replace('T', ' ').slice(0, 16) : 'sin hora';
            const method = String(item.payment_method || 'Sin metodo');
            return `#${item.id} · S/ ${amount} · ${method} · ${dateLabel}`;
        });

        return 'Ya existe un pago muy parecido registrado hoy para este paciente:\n\n'
            + lines.join('\n')
            + '\n\n¿Confirmas que deseas guardar otro pago igual?';
    }

    async function submitCobrarPago(e, options = {}) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitPago');
        btn.disabled = true; btn.textContent = 'Procesando...';
        const serviceAmount = parseFloat(document.getElementById('cp_amount').value || '0');
        const availableBalance = parseFloat(document.getElementById('cp_credit_available')?.dataset?.value || '0');
        const useReferralCredit = !!document.getElementById('cp_use_referral_credit')?.checked;
        const creditApplied = useReferralCredit ? Math.min(serviceAmount, availableBalance) : 0;
        const cashAmount = Math.max(0, serviceAmount - creditApplied);
        if (serviceAmount <= 0) {
            showToast('Ingresa un monto válido', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">check_circle</span> Confirmar Pago';
            return;
        }
        const data = {
            patient_id:     document.getElementById('cp_patient').value,
            amount:         cashAmount.toFixed(2),
            service_amount: serviceAmount.toFixed(2),
            use_referral_credit: useReferralCredit ? 1 : 0,
            payment_method: document.getElementById('cp_method').value,
            package_id:     document.getElementById('cp_package_id')?.value || '',
            description:    document.getElementById('cp_description').value,
            type:           'payment_received',
            confirm_duplicate: options.confirmDuplicate ? 1 : 0
        };
        try {
            const res  = await fetch('api/payments.php', { method: 'POST', headers: {'Content-Type':'application/json', 'X-CSRF-Token': appCsrfToken}, body: JSON.stringify(data) });
            const json = await res.json();
            if (json.success) {
                let msg = json.offline ? 'Pago guardado offline' : 'Cobro registrado';
                if (!json.offline) {
                    const parts = [];
                    if (parseFloat(json.cash_amount || 0) > 0) {
                        parts.push('caja S/ ' + parseFloat(json.cash_amount).toFixed(2));
                    }
                    if (parseFloat(json.credit_applied || 0) > 0) {
                        parts.push('saldo usado S/ ' + parseFloat(json.credit_applied).toFixed(2));
                    }
                    if (parts.length > 0) {
                        msg = 'Cobro registrado: ' + parts.join(' + ');
                    }
                }
                showToast(msg, json.offline ? 'warning' : 'success');
                closeModal('modalCobrarPago');
                document.getElementById('formCobrarPago').reset();
                clearSelectedPatient('cp');
                if (!json.offline) {
                    if (window.reloadFinancials) window.reloadFinancials();
                    else if (window.location.pathname.includes('patient_profile.php')) window.location.reload();
                    else if (window.location.pathname.includes('financials')) window.location.reload();
                }
            } else if (json.duplicate_warning) {
                const confirmed = window.confirm(buildDuplicatePaymentMessage(json.duplicates));
                if (confirmed) {
                    await submitCobrarPago(e, {confirmDuplicate: true});
                    return;
                }
                showToast('Registro cancelado para evitar duplicados', 'warning');
            } else { showToast(json.error, 'error'); }
        } catch(err) { showToast('Error de conexión', 'error'); }
        finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">check_circle</span> Confirmar Pago';
        }
    }

    // CAMBIAR MI CONTRASEÑA
    const mustChangePassword = <?= !empty($_SESSION['must_change_password']) ? 'true' : 'false' ?>;
    async function submitCambiarPassword(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitPassword');
        const newPwd  = document.getElementById('cp_new').value;
        const confirm = document.getElementById('cp_confirm').value;
        if (newPwd !== confirm) { showToast('Las contraseñas no coinciden', 'error'); return; }
        btn.disabled = true; btn.textContent = 'Guardando...';
        try {
            const res  = await fetch('api/change_password.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    current_password: document.getElementById('cp_current').value,
                    new_password: newPwd
                })
            });
            const json = await res.json();
            if (json.success) {
                showToast('Contraseña actualizada exitosamente', 'success');
                closeModal('modalCambiarPassword');
                document.getElementById('formCambiarPassword').reset();
                if (mustChangePassword) {
                    setTimeout(() => window.location.reload(), 800);
                }
            } else { showToast(json.error, 'error'); }
        } catch(err) { showToast('Error de conexión', 'error'); }
        finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-outlined" style="vertical-align:middle;margin-right:0.25rem;font-size:1.1rem;">save</span> Guardar Contraseña';
        }
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â• NEW SEARCH & STATUS SYSTEM â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    if (mustChangePassword) {
        document.addEventListener('DOMContentLoaded', () => {
            const currentLabel = document.querySelector('label[for="cp_current"]');
            const currentInput = document.getElementById('cp_current');
            if (currentLabel) currentLabel.textContent = 'Clave temporal actual *';
            if (currentInput) currentInput.placeholder = 'Si es tu primer ingreso, escribe tu DNI';
            openModal('modalCambiarPassword');
            showToast('Por seguridad, primero debes crear tu nueva contraseña', 'warning');
        });
    }

    const modalPatients = <?= json_encode($modalPatients) ?>;
    const modalReferrers = <?= json_encode($modalReferrers) ?>;
    const APPOINTMENT_START_MINUTES = 8 * 60;
    const APPOINTMENT_END_MINUTES = 19 * 60 + 30;
    const APPOINTMENT_SLOT_MINUTES = 30;

    function sanitizeDigits(input, maxLength) {
        if (!input) return;
        input.value = String(input.value || '').replace(/\D+/g, '').slice(0, maxLength);
    }

    function calculateAgeFromDateString(dateString) {
        if (!dateString) return '';
        const birthDate = new Date(dateString + 'T00:00:00');
        if (Number.isNaN(birthDate.getTime())) return '';

        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        const dayDiff = today.getDate() - birthDate.getDate();

        if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) age -= 1;
        if (age < 0 || age > 120) return '';
        return String(age);
    }

    function syncAgeFromBirthDate(birthInputId, ageInputId) {
        const birthInput = document.getElementById(birthInputId);
        const ageInput = document.getElementById(ageInputId);
        if (!birthInput || !ageInput) return;

        const ageValue = calculateAgeFromDateString(birthInput.value);
        if (ageValue !== '') {
            ageInput.value = ageValue;
            ageInput.readOnly = true;
        } else {
            ageInput.readOnly = false;
        }
    }

    function ensureNewPatientBirthDateField() {
        const ageInput = document.getElementById('np_age');
        if (!ageInput || document.getElementById('np_birth_date')) return;

        const ageGroup = ageInput.closest('.form-group');
        if (!ageGroup || !ageGroup.parentElement) return;

        const birthGroup = document.createElement('div');
        birthGroup.className = 'form-group';
        birthGroup.innerHTML = '<label for="np_birth_date">Fecha de nacimiento</label><input type="date" id="np_birth_date" name="birth_date" class="form-control" max="<?= date('Y-m-d') ?>">';
        ageGroup.parentElement.insertBefore(birthGroup, ageGroup);

        const birthInput = document.getElementById('np_birth_date');
        if (birthInput) {
            birthInput.addEventListener('change', () => syncAgeFromBirthDate('np_birth_date', 'np_age'));
        }
    }

    function toggleNewPatientReferrerFields() {
        const kind = document.getElementById('np_referrer_kind')?.value || '';
        const patientGroup = document.getElementById('np_referrer_patient_group');
        const externalGroup = document.getElementById('np_referrer_external_group');
        const patientSelect = document.getElementById('np_referrer_patient_id');
        const externalSelect = document.getElementById('np_referrer_external_id');

        if (patientGroup) {
            patientGroup.style.display = kind === 'patient' ? 'block' : 'none';
        }
        if (externalGroup) {
            externalGroup.style.display = kind === 'referrer' ? 'block' : 'none';
        }

        if (kind !== 'patient' && patientSelect) {
            patientSelect.value = '';
        }
        if (kind !== 'referrer' && externalSelect) {
            externalSelect.value = '';
        }
    }

    function setupNewPatientFormValidation() {
        ensureNewPatientBirthDateField();

        const dniInput = document.getElementById('np_dni');
        const phoneInput = document.getElementById('np_phone');
        const ageInput = document.getElementById('np_age');
        const emailInput = document.getElementById('np_email');
        const dniGroup = dniInput?.closest('.form-group');
        const phoneGroup = phoneInput?.closest('.form-group');
        const ageGroup = ageInput?.closest('.form-group');
        const birthGroup = document.getElementById('np_birth_date')?.closest('.form-group');
        const emailGroup = emailInput?.closest('.form-group');

        if (emailGroup) {
            emailGroup.remove();
        }

        if (dniGroup && phoneGroup && dniGroup.parentElement) {
            dniGroup.parentElement.insertBefore(phoneGroup, dniGroup.nextSibling);
        }

        if (phoneGroup && birthGroup && phoneGroup.parentElement && phoneGroup.nextSibling !== birthGroup) {
            phoneGroup.parentElement.insertBefore(birthGroup, phoneGroup.nextSibling);
        }

        if (dniInput) {
            dniInput.inputMode = 'numeric';
            dniInput.maxLength = 8;
            dniInput.placeholder = '8 digitos';
            dniInput.addEventListener('input', () => sanitizeDigits(dniInput, 8));
        }

        if (phoneInput) {
            phoneInput.inputMode = 'numeric';
            phoneInput.maxLength = 9;
            phoneInput.placeholder = '9 digitos';
            phoneInput.addEventListener('input', () => sanitizeDigits(phoneInput, 9));
        }

        if (ageInput) {
            ageInput.addEventListener('input', () => {
                const birthInput = document.getElementById('np_birth_date');
                if (!birthInput || !birthInput.value) ageInput.readOnly = false;
            });
        }

        toggleNewPatientReferrerFields();
    }
    
    function searchPatientQuick(query, prefix = 'nc') {
        const resultsDiv = document.getElementById(prefix + '_patient_results');
        if (!query || query.length < 1) {
            resultsDiv.style.display = 'none';
            return;
        }
        
        const term = query.toLowerCase();
        const matches = modalPatients.filter(p => {
            const nameMatch = p.name ? p.name.toLowerCase().includes(term) : false;
            const dniMatch  = p.dni  ? String(p.dni).includes(term) : false;
            return nameMatch || dniMatch;
        }).slice(0, 10);
        
        if (matches.length > 0) {
            let html = matches.map(p => `
                <div class="search-result-item" onclick="selectPatient(${p.id}, '${p.name.replace(/'/g, "\\'")}', '${prefix}')">
                    <div style="font-weight:600;">${p.name}</div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">DNI: ${p.dni || 'â€”'}</div>
                </div>
            `).join('');
            
            // Siempre ofrecer opciÃ³n de agregar si se estÃ¡ buscando
            html += `
                <div class="search-result-item" style="border-top:2px solid var(--border-light); color:var(--primary-color); font-weight:600;" onclick="openModal('modalNuevoPaciente'); closeModal('modalNuevaCita'); closeModal('modalCobrarPago');">
                    <span class="material-icons-outlined" style="font-size:1rem; vertical-align:middle;">person_add</span> Agregar nuevo paciente...
                </div>
            `;
            
            resultsDiv.innerHTML = html;
            resultsDiv.style.display = 'block';
        } else {
            resultsDiv.innerHTML = `
                <div class="search-result-item text-muted">No se encontraron resultados</div>
                <div class="search-result-item" style="color:var(--primary-color); font-weight:600;" onclick="openModal('modalNuevoPaciente'); closeModal('modalNuevaCita'); closeModal('modalCobrarPago');">
                    <span class="material-icons-outlined" style="font-size:1rem; vertical-align:middle;">person_add</span> Agregar nuevo paciente...
                </div>
            `;
            resultsDiv.style.display = 'block';
        }
    }

    function selectPatient(id, name, prefix = 'nc') {
        document.getElementById(prefix + '_patient').value = id;
        
        let nameDisplay = 'selected_patient_name';
        if (prefix === 'cp') nameDisplay = 'selected_patient_name_cp';
        if (prefix === 'qn') nameDisplay = 'selected_patient_name_qn';
        document.getElementById(nameDisplay).textContent = name;
        
        document.getElementById(prefix + '_patient_selected').style.display = 'block';
        document.getElementById(prefix + '_patient_results').style.display = 'none';
        document.getElementById(prefix + '_patient_search').style.display = 'none';
        const searchIcon = document.getElementById(prefix + '_patient_search_icon');
        if (searchIcon) searchIcon.style.display = 'none';

        if (prefix === 'cp') {
            loadPatientPackagesForPayment(id);
            loadPatientReferralCredit(id);
        }
    }

    function clearSelectedPatient(prefix = 'nc') {
        document.getElementById(prefix + '_patient').value = '';
        document.getElementById(prefix + '_patient_search').value = '';
        document.getElementById(prefix + '_patient_search').style.display = 'block';
        document.getElementById(prefix + '_patient_selected').style.display = 'none';
        const searchIcon = document.getElementById(prefix + '_patient_search_icon');
        if (searchIcon) searchIcon.style.display = 'block';
        if (prefix === 'cp') {
            const packageSelect = document.getElementById('cp_package_id');
            const packageHelp = document.getElementById('cp_package_help');
            const createPackageBtn = document.getElementById('cp_create_package_btn');
            if (packageSelect) {
                packageSelect.innerHTML = '<option value="">-- Pago libre / por sesión --</option>';
            }
            if (packageHelp) {
                packageHelp.style.display = 'none';
                packageHelp.textContent = '';
            }
            if (createPackageBtn) createPackageBtn.style.display = 'none';
            const summary = document.getElementById('cp_credit_summary');
            const checkbox = document.getElementById('cp_use_referral_credit');
            const amountInput = document.getElementById('cp_amount');
            if (summary) summary.style.display = 'none';
            if (checkbox) checkbox.checked = false;
            if (amountInput) amountInput.value = '';
            updatePaymentTotals();
        }
    }

    async function loadPatientPackagesForPayment(patientId) {
        const packageSelect = document.getElementById('cp_package_id');
        const packageHelp = document.getElementById('cp_package_help');
        const createPackageBtn = document.getElementById('cp_create_package_btn');
        if (!packageSelect) return;

        packageSelect.innerHTML = '<option value="">Cargando paquetes...</option>';
        if (packageHelp) {
            packageHelp.style.display = 'none';
            packageHelp.textContent = '';
        }
        if (createPackageBtn) createPackageBtn.style.display = 'inline-flex';

        try {
            const res = await fetch('api/packages.php?patient_id=' + patientId);
            const json = await res.json();
            const packages = Array.isArray(json.packages) ? json.packages : [];

            packageSelect.innerHTML = '<option value="">-- Pago libre / por sesión --</option>';
            if (packages.length === 0 && packageHelp) {
                packageHelp.textContent = 'Este paciente todavía no tiene paquetes asignados. Puedes registrar un pago libre o asignarle uno del catálogo.';
                packageHelp.style.display = 'block';
            }
            packages.forEach(pkg => {
                const totalAmount = parseFloat(pkg.total_amount || 0);
                const amountPaid = parseFloat(pkg.amount_paid || 0);
                const pending = Math.max(0, totalAmount - amountPaid);
                const label = totalAmount > 0
                    ? `${pkg.name} · Pendiente S/ ${pending.toFixed(2)}`
                    : `${pkg.name} · ${pkg.unused_sessions}/${pkg.total_sessions} sesiones`;
                packageSelect.innerHTML += `<option value="${pkg.id}">${label}</option>`;
            });
        } catch (e) {
            packageSelect.innerHTML = '<option value="">-- No se pudieron cargar paquetes --</option>';
            if (packageHelp) {
                packageHelp.textContent = 'No se pudieron cargar los paquetes del paciente.';
                packageHelp.style.display = 'block';
            }
        }
    }

    function openCreatePackageFromPayment() {
        const patientId = document.getElementById('cp_patient')?.value;
        if (!patientId) {
            showToast('Selecciona un paciente primero', 'error');
            return;
        }
        window.location.href = `patient_profile.php?id=${patientId}&open_package=1`;
    }

    async function loadPatientReferralCredit(patientId) {
        const summary = document.getElementById('cp_credit_summary');
        const available = document.getElementById('cp_credit_available');
        const checkbox = document.getElementById('cp_use_referral_credit');

        if (!summary || !available) return;

        summary.style.display = 'none';
        if (checkbox) checkbox.checked = false;
        available.textContent = '0.00';
        available.dataset.value = '0';
        updatePaymentTotals();

        try {
            const res = await fetch('api/referrals.php?balance_for_patient=' + patientId);
            const json = await res.json();
            if (!json.success || !json.summary) {
                return;
            }

            const balance = parseFloat(json.summary.available_balance || 0);
            if (balance > 0) {
                available.textContent = balance.toFixed(2);
                available.dataset.value = balance.toFixed(2);
                summary.style.display = 'block';
            }
        } catch (e) {
        }
    }

    function updatePaymentTotals() {
        const amountInput = document.getElementById('cp_amount');
        const summary = document.getElementById('cp_credit_summary');
        const breakdown = document.getElementById('cp_credit_breakdown');
        const available = document.getElementById('cp_credit_available');
        const useCredit = document.getElementById('cp_use_referral_credit');
        const toApply = document.getElementById('cp_credit_to_apply');
        const cashToCollect = document.getElementById('cp_cash_to_collect');

        if (!amountInput || !breakdown || !available || !useCredit || !toApply || !cashToCollect) return;

        const serviceAmount = parseFloat(amountInput.value || '0');
        const availableBalance = parseFloat(available.dataset.value || available.textContent || '0');
        const creditApplied = useCredit.checked ? Math.min(serviceAmount, availableBalance) : 0;
        const cashAmount = Math.max(0, serviceAmount - creditApplied);

        toApply.textContent = creditApplied.toFixed(2);
        cashToCollect.textContent = cashAmount.toFixed(2);

        if (summary && summary.style.display !== 'none' && useCredit.checked && serviceAmount > 0) {
            breakdown.style.display = 'block';
        } else {
            breakdown.style.display = 'none';
        }
    }

    function setStatus(status, btn) {
        document.getElementById('nc_status').value = status;
        document.querySelectorAll('.btn-status-toggle').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#nc_patient_search')) {
            const res = document.getElementById('nc_patient_results');
            if (res) res.style.display = 'none';
        }
    });

    function handleAppointmentTimeChange(startTime) {
        autoFillEndTime(startTime);
        renderAppointmentTimeSlots(startTime);
    }

    function autoFillEndTime(startTime) {
        if (!startTime) return;
        let [hours, minutes] = startTime.split(':').map(Number);
        hours = (hours + 1) % 24;
        const endTime = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
        document.getElementById('nc_end').value = endTime;
    }

    function formatMinutesToTime(totalMinutes) {
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
    }

    function formatTimeLabel(timeValue) {
        const [rawHours, rawMinutes] = timeValue.split(':').map(Number);
        const suffix = rawHours >= 12 ? 'PM' : 'AM';
        const hours = rawHours % 12 || 12;
        return hours + ':' + String(rawMinutes).padStart(2, '0') + ' ' + suffix;
    }

    function getAppointmentDateParts(dateValue) {
        const [year, month, day] = (dateValue || '').split('-').map(Number);
        return { year, month, day };
    }

    function isTodaySelected(dateValue) {
        const today = new Date();
        const { year, month, day } = getAppointmentDateParts(dateValue);
        return year === today.getFullYear() && month === (today.getMonth() + 1) && day === today.getDate();
    }

    function compareAppointmentDateToToday(dateValue) {
        const { year, month, day } = getAppointmentDateParts(dateValue);
        if (!year || !month || !day) return 0;

        const selected = new Date(year, month - 1, day);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (selected.getTime() < today.getTime()) return -1;
        if (selected.getTime() > today.getTime()) return 1;
        return 0;
    }

    function roundUpMinutes(totalMinutes, stepMinutes) {
        return Math.ceil(totalMinutes / stepMinutes) * stepMinutes;
    }

    function getDefaultAppointmentDateValue() {
        const now = new Date();
        const currentMinutes = (now.getHours() * 60) + now.getMinutes();
        const baseDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());

        if (currentMinutes > APPOINTMENT_END_MINUTES) {
            baseDate.setDate(baseDate.getDate() + 1);
        }

        const year = baseDate.getFullYear();
        const month = String(baseDate.getMonth() + 1).padStart(2, '0');
        const day = String(baseDate.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function getSuggestedAppointmentTime(dateValue) {
        if (!dateValue) return '08:00';

        const dayRelation = compareAppointmentDateToToday(dateValue);
        if (dayRelation !== 0) return '08:00';

        const now = new Date();
        let nextMinutes = roundUpMinutes((now.getHours() * 60) + now.getMinutes(), APPOINTMENT_SLOT_MINUTES);

        if (nextMinutes < APPOINTMENT_START_MINUTES) nextMinutes = APPOINTMENT_START_MINUTES;
        if (nextMinutes > APPOINTMENT_END_MINUTES) return '';

        return formatMinutesToTime(nextMinutes);
    }

    function renderAppointmentTimeSlots(selectedTime = '') {
        const container = document.getElementById('nc_time_slots');
        const dateValue = document.getElementById('nc_date')?.value;
        if (!container) return;

        const dayRelation = compareAppointmentDateToToday(dateValue);
        const isToday = dayRelation === 0;
        const now = new Date();
        const currentMinutes = (now.getHours() * 60) + now.getMinutes();

        let html = '';
        for (let minutes = APPOINTMENT_START_MINUTES; minutes <= APPOINTMENT_END_MINUTES; minutes += APPOINTMENT_SLOT_MINUTES) {
            const timeValue = formatMinutesToTime(minutes);
            const isDisabled = dayRelation < 0 || (isToday && minutes < currentMinutes);
            const activeClass = timeValue === selectedTime ? ' active' : '';
            const disabledAttr = isDisabled ? ' disabled' : '';
            html += `<button type="button" class="time-slot-btn${activeClass}" data-time="${timeValue}"${disabledAttr} onclick="selectAppointmentTime('${timeValue}')">${formatTimeLabel(timeValue)}</button>`;
        }

        container.innerHTML = html;
    }

    function selectAppointmentTime(timeValue) {
        const timeInput = document.getElementById('nc_start');
        if (!timeInput) return;
        timeInput.value = timeValue;
        handleAppointmentTimeChange(timeValue);
    }

    function prepareAppointmentTimeOptions(forceSuggested = false) {
        const dateInput = document.getElementById('nc_date');
        const timeInput = document.getElementById('nc_start');
        if (!dateInput || !timeInput) return;

        if (!dateInput.value || forceSuggested) {
            dateInput.value = getDefaultAppointmentDateValue();
        }

        if (forceSuggested || !timeInput.value) {
            const suggestedTime = getSuggestedAppointmentTime(dateInput.value);
            timeInput.value = suggestedTime;
            if (suggestedTime) autoFillEndTime(suggestedTime);
        }

        renderAppointmentTimeSlots(timeInput.value);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const dateInput = document.getElementById('nc_date');
        const timeInput = document.getElementById('nc_start');
        setupNewPatientFormValidation();

        if (dateInput) {
            dateInput.addEventListener('change', () => {
                const dayRelation = compareAppointmentDateToToday(dateInput.value);
                const suggestedTime = getSuggestedAppointmentTime(dateInput.value);
                if (dayRelation < 0) {
                    document.getElementById('nc_start').value = '';
                    document.getElementById('nc_end').value = '';
                } else if (suggestedTime) {
                    document.getElementById('nc_start').value = suggestedTime;
                    autoFillEndTime(suggestedTime);
                } else {
                    document.getElementById('nc_start').value = '';
                    document.getElementById('nc_end').value = '';
                }
                renderAppointmentTimeSlots(document.getElementById('nc_start').value);
            });
        }

        if (timeInput) {
            prepareAppointmentTimeOptions(true);
        }
    });

    function filterSelect(selectId, query) {
        const term = query.toLowerCase();
        const select = document.getElementById(selectId);
        const options = select.querySelectorAll('option');
        options.forEach(opt => {
            if (!opt.value) return; // Skip placeholder
            const text = opt.textContent.toLowerCase();
            const dni  = (opt.dataset.dni || "").toLowerCase();
            const match = text.includes(term) || dni.includes(term);
            opt.style.display = match ? '' : 'none';
        });
    }
    </script>
</body>
</html>

