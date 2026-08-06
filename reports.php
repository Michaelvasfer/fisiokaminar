<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once 'db.php';
ensureExpenseSchema($pdo);
ensureFixedExpenseSchema($pdo);

$userRole = $_SESSION['role'] ?? '';
if ($userRole !== 'admin') {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Reportes';
require_once 'includes/header.php';
?>

<div class="animate-fade-in delay-100">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem;">
        <div>
            <h1 style="margin:0;">Reportes y Analíticas</h1>
            <p class="text-sm text-muted" style="margin:0.3rem 0 0;">Vision gerencial y de inteligencia de negocios.</p>
        </div>
        <div style="display:flex;align-items:center;gap:0.55rem;font-size:0.85rem;color:var(--text-muted);background:var(--surface);padding:0.45rem 0.8rem;border-radius:var(--radius-full);border:1px solid var(--border-color);">
            <span class="material-icons-outlined" style="font-size:0.95rem;vertical-align:middle;">calendar_today</span>
            <label for="financeMonthPicker" style="font-weight:600;">Mes</label>
            <input type="month" id="financeMonthPicker" value="<?= date('Y-m') ?>" onchange="handleFinanceMonthChange()" style="border:none;background:transparent;color:inherit;font:inherit;outline:none;">
        </div>
    </div>

    <div class="tabs-container" style="display:flex;gap:1.5rem;border-bottom:1px solid var(--border-color);margin-bottom:1.5rem;">
        <div id="btn-tab-finanzas" class="tab-btn active" onclick="switchTab('finanzas')">Finanzas</div>
        <div id="btn-tab-bi" class="tab-btn" onclick="switchTab('bi')">Inteligencia (BI)</div>
    </div>

    <!-- TAB FINANZAS -->
    <div id="tab-finanzas" class="tab-content" style="display:block;">
        <div class="metrics-grid mb-4">
            <div class="metric-card">
                <div class="metric-label" id="summaryIncomeLabel">Ingresos del mes</div>
                <div class="metric-value" id="summaryIncome" style="color:var(--success);">S/ 0.00</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Gastos variables</div>
                <div class="metric-value" id="summaryExpenses" style="color:var(--danger);">S/ 0.00</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Gastos fijos</div>
                <div class="metric-value" id="summaryFixed">S/ 0.00</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Resultado operativo</div>
                <div class="metric-value" id="summaryResult">S/ 0.00</div>
            </div>
        </div>

        <div class="card mb-4" style="overflow:hidden;">
            <div style="padding:1rem;background:linear-gradient(135deg,#ecfeff 0%,#f8fafc 100%);border-bottom:1px solid var(--border-color);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <div style="font-size:0.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:var(--primary-dark);margin-bottom:0.35rem;">Punto de equilibrio</div>
                        <div style="font-size:1.15rem;font-weight:800;" id="breakEvenTitle">Aun no cubres tus gastos fijos</div>
                        <div class="text-sm text-muted" style="margin-top:0.3rem;" id="breakEvenSubtitle">Faltan S/ 0.00 para cubrir los gastos fijos del mes.</div>
                    </div>
                    <button type="button" class="btn-action-sm" onclick="openModal('modalNuevoGastoFijo')">+ Gasto fijo</button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0.85rem;margin-top:1rem;">
                    <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                        <div class="text-sm text-muted">Cobertura fija</div>
                        <div id="fixedCoverageValue" style="font-size:1.35rem;font-weight:800;">0%</div>
                    </div>
                    <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                        <div class="text-sm text-muted">Falta por cubrir</div>
                        <div id="remainingCoverageValue" style="font-size:1.35rem;font-weight:800;">S/ 0.00</div>
                    </div>
                    <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                        <div class="text-sm text-muted">Utilidad actual</div>
                        <div id="utilityStatusValue" style="font-size:1.35rem;font-weight:800;">En proceso</div>
                    </div>
                </div>
                <div style="margin-top:0.9rem;height:12px;background:#dbe4ea;border-radius:999px;overflow:hidden;">
                    <div id="breakEvenBar" style="height:100%;width:0%;background:linear-gradient(90deg,#f59e0b,#fbbf24);border-radius:999px;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.45rem;font-size:0.82rem;">
                    <span class="text-muted">Cobertura de gastos fijos</span>
                    <strong id="breakEvenPercent">0%</strong>
                </div>
            </div>
        </div>

        <div class="card mb-4" style="overflow:hidden;">
            <div style="padding:1rem;background:linear-gradient(135deg,#fff7ed 0%,#ffffff 100%);border-bottom:1px solid var(--border-color);">
                <div>
                    <div style="font-size:0.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#c2410c;margin-bottom:0.35rem;">Gestion de compromisos</div>
                    <div style="font-size:1.15rem;font-weight:800;" id="cashCommitmentTitle">Caja reservada para pagos fijos</div>
                    <div class="text-sm text-muted" style="margin-top:0.3rem;" id="cashCommitmentSubtitle">Aqui veras cuanto de la caja en efectivo ya esta comprometido para sueldos, alquiler y otros pagos fijos.</div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0.85rem;margin-top:1rem;" class="cash-commitment-grid">
                    <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                        <div class="text-sm text-muted">Caja sistema</div>
                        <div id="commitmentCashAvailable" style="font-size:1.35rem;font-weight:800;">S/ 0.00</div>
                    </div>
                    <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                        <div class="text-sm text-muted">Proximo pago</div>
                        <div id="commitmentNextPaymentName" style="font-size:1rem;font-weight:800;">Sin pagos</div>
                        <div id="commitmentNextPaymentAmount" style="font-size:0.95rem;font-weight:700;color:var(--text-muted);margin-top:0.2rem;">S/ 0.00</div>
                    </div>
                    <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                        <div class="text-sm text-muted">Falta para asumirlo</div>
                        <div id="commitmentNextGapValue" style="font-size:1.35rem;font-weight:800;">S/ 0.00</div>
                    </div>
                    <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                        <div class="text-sm text-muted">Pendiente total</div>
                        <div id="commitmentPendingValue" style="font-size:1.35rem;font-weight:800;">S/ 0.00</div>
                    </div>
                </div>
                <div style="margin-top:0.9rem;height:12px;background:#dbe4ea;border-radius:999px;overflow:hidden;">
                    <div id="commitmentCoverageBar" style="height:100%;width:0%;background:linear-gradient(90deg,#f59e0b,#f97316);border-radius:999px;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.45rem;font-size:0.82rem;gap:0.75rem;flex-wrap:wrap;">
                    <span class="text-muted" id="commitmentCoverageText">Cobertura de caja para compromisos pendientes</span>
                    <strong id="commitmentCoveragePercent">0%</strong>
                </div>
            </div>
            <div id="cashCommitmentsList"></div>
        </div>

        <div class="finance-charts-grid" style="display:grid;grid-template-columns:1.2fr 0.8fr;gap:1rem;margin-bottom:1rem;">
            <div class="card mb-0" style="min-width:0;">
                <div class="card-header">
                    <h2 class="card-title" id="dailyBalanceTitle">Ingresos vs Gastos del mes</h2>
                </div>
                <div style="padding:1rem;min-width:0;">
                    <canvas id="chartDailyBalance" height="220"></canvas>
                </div>
            </div>
            <div class="card mb-0" style="min-width:0;">
                <div class="card-header">
                    <h2 class="card-title" id="expenseCategoryTitle">Gastos por categoria</h2>
                </div>
                <div style="padding:1rem;min-width:0;overflow:hidden;">
                    <canvas id="chartExpenseCategories" height="220"></canvas>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title" id="monthlyBalanceTitle">Balance mensual <?= date('Y') ?></h2>
            </div>
            <div style="padding:1rem;">
                <canvas id="chartMonthlyBalance" height="220"></canvas>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="card mb-0">
                <div class="card-header">
                    <h2 class="card-title" id="dailySummaryTitle">Resumen diario</h2>
                </div>
                <div id="tableDailyBalance"></div>
            </div>
            <div class="card mb-0">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;">
                    <h2 class="card-title">Gastos fijos</h2>
                    <button type="button" class="btn-action-sm" onclick="openModal('modalNuevoGastoFijo')">+ Nuevo</button>
                </div>
                <div id="fixedExpensesList"></div>
            </div>
        </div>
    </div> <!-- END TAB FINANZAS -->

    <!-- TAB INTELIGENCIA -->
    <div id="tab-bi" class="tab-content" style="display:none;">
        
        <!-- BI Filters -->
        <div style="display:flex; gap:1rem; margin-bottom:1.5rem; background:var(--surface); padding:1rem; border-radius:12px; border:1px solid var(--border-color); align-items:flex-end; flex-wrap:wrap;">
            <div style="flex:1; min-width:140px;">
                <label class="text-sm text-muted" style="display:block; margin-bottom:0.3rem; font-weight:600;">Desde</label>
                <input type="date" id="biFilterDateFrom" class="form-control">
            </div>
            <div style="flex:1; min-width:140px;">
                <label class="text-sm text-muted" style="display:block; margin-bottom:0.3rem; font-weight:600;">Hasta</label>
                <input type="date" id="biFilterDateTo" class="form-control">
            </div>
            <div style="flex:1; min-width:200px;">
                <label class="text-sm text-muted" style="display:block; margin-bottom:0.3rem; font-weight:600;">Filtro por Fisioterapeuta</label>
                <select id="biFilterTherapist" class="form-control" onchange="initBI()">
                    <option value="">Todos los terapeutas</option>
                </select>
            </div>
            <div style="flex:1; min-width:200px;">
                <label class="text-sm text-muted" style="display:block; margin-bottom:0.3rem; font-weight:600;">Filtro por Diagnóstico</label>
                <select id="biFilterDiagnosis" class="form-control" onchange="initBI()">
                    <option value="">Todos los diagnósticos</option>
                </select>
            </div>
            <div>
                <button type="button" class="btn-action-sm" onclick="initBI()" style="padding:0.6rem 1.2rem;">
                    <span class="material-icons-outlined" style="font-size:1rem; vertical-align:middle; margin-right:4px;">filter_list</span>Aplicar Filtros
                </button>
            </div>
        </div>

        <!-- KPIs Rápidos -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
            <div style="background:linear-gradient(135deg, #fdf4ff 0%, #ffffff 100%); border:1px solid #fae8ff; border-radius:12px; padding:1.2rem; display:flex; align-items:center; gap:1rem;">
                <div style="background:#f0abfc; color:#fff; width:48px; height:48px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                    <span class="material-icons-outlined">payments</span>
                </div>
                <div>
                    <div class="text-xs text-muted" style="font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Valor Promedio por Paciente (LTV)</div>
                    <div id="biLtvValue" style="font-size:1.6rem; font-weight:800; color:#86198f;">S/ 0.00</div>
                </div>
            </div>
            
            <div style="background:linear-gradient(135deg, #eff6ff 0%, #ffffff 100%); border:1px solid #dbeafe; border-radius:12px; padding:1.2rem; display:flex; align-items:center; gap:1rem;">
                <div style="background:#60a5fa; color:#fff; width:48px; height:48px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                    <span class="material-icons-outlined">sentiment_dissatisfied</span>
                </div>
                <div>
                    <div class="text-xs text-muted" style="font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Dolor Promedio al Ingreso (EVA)</div>
                    <div id="biEvaValue" style="font-size:1.6rem; font-weight:800; color:#1e40af;">0 / 10</div>
                </div>
            </div>
        </div>

        <!-- Fila 1 de Graficos -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
            
            <div class="card mb-0" style="background:linear-gradient(135deg, #fff7ed 0%, #ffffff 100%);">
                <div class="card-header border-0">
                    <h2 class="card-title" style="color:#c2410c;"><span class="material-icons-outlined" style="vertical-align:middle;margin-right:4px;">donut_large</span> Adherencia al Tratamiento</h2>
                    <p class="text-xs text-muted" style="margin:0;">¿Cuántos terminan completamente su plan de sesiones?</p>
                </div>
                <div style="padding:1rem;display:flex;align-items:center;justify-content:center;">
                    <div style="position:relative;width:100%;max-width:240px;">
                        <canvas id="chartAdherence"></canvas>
                    </div>
                </div>
            </div>

            <div class="card mb-0" style="background:linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);">
                <div class="card-header border-0">
                    <h2 class="card-title" style="color:#15803d;"><span class="material-icons-outlined" style="vertical-align:middle;margin-right:4px;">volunteer_activism</span> Tasa de Retención</h2>
                    <p class="text-xs text-muted" style="margin:0;">Pct de pacientes que vuelven vs. los que vienen sola 1 vez</p>
                </div>
                <div style="padding:1rem;display:flex;align-items:center;justify-content:center;">
                    <div style="position:relative;width:100%;max-width:240px;">
                        <canvas id="chartRetention"></canvas>
                    </div>
                </div>
            </div>

            <div class="card mb-0" style="background:linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);">
                <div class="card-header border-0">
                    <h2 class="card-title" style="color:#1d4ed8;"><span class="material-icons-outlined" style="vertical-align:middle;margin-right:4px;">health_and_safety</span> Top Diagnósticos</h2>
                    <p class="text-xs text-muted" style="margin:0;">¿Por qué vienen más a la clínica?</p>
                </div>
                <div style="padding:1rem;">
                    <canvas id="chartDiagnoses" height="220"></canvas>
                </div>
            </div>
        </div>

        <!-- Fila 2 de Graficos: Paquetes y Cancelaciones -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
            <div class="card mb-0" style="background:linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);">
                <div class="card-header border-0">
                    <h2 class="card-title" style="color:#0f172a;"><span class="material-icons-outlined" style="vertical-align:middle;margin-right:4px;">shopping_bag</span> Paquetes vs Sueltas</h2>
                    <p class="text-xs text-muted" style="margin:0;">Distribución de ingresos por tipo de venta</p>
                </div>
                <div style="padding:1rem;display:flex;align-items:center;justify-content:center;">
                    <div style="position:relative;width:100%;max-width:240px;">
                        <canvas id="chartPackages"></canvas>
                    </div>
                </div>
            </div>

            <div class="card mb-0" style="background:linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);">
                <div class="card-header border-0">
                    <h2 class="card-title" style="color:#b91c1c;"><span class="material-icons-outlined" style="vertical-align:middle;margin-right:4px;">event_busy</span> Ausentismo (Cancelaciones)</h2>
                    <p class="text-xs text-muted" style="margin:0;">Porcentaje de citas que fracasaron</p>
                </div>
                <div style="padding:1rem;display:flex;align-items:center;justify-content:center;">
                    <div style="position:relative;width:100%;max-width:240px;">
                        <canvas id="chartCancellations"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila 3 y 4 de Graficos Normales -->
        <div class="card mb-4" style="background:linear-gradient(135deg, #fdf4ff 0%, #ffffff 100%);">
            <div class="card-header border-0">
                <h2 class="card-title" style="color:#86198f;"><span class="material-icons-outlined" style="vertical-align:middle;margin-right:4px;">groups</span> Demografía de Pacientes</h2>
                <p class="text-xs text-muted" style="margin:0;">¿A qué grupos de edad estás atrayendo más?</p>
            </div>
            <div style="padding:1rem;">
                <canvas id="chartDemographics" height="220"></canvas>
            </div>
        </div>

        <div class="card mb-4" style="background:linear-gradient(135deg, #faf5ff 0%, #ffffff 100%);">
            <div class="card-header border-0">
                <h2 class="card-title" style="color:#7e22ce;"><span class="material-icons-outlined" style="vertical-align:middle;margin-right:4px;">psychology</span> Desempeño por Fisioterapeuta</h2>
                <p class="text-xs text-muted" style="margin:0;">Analiza quién logra mayor fidelidad con sus pacientes de 1ra vez.</p>
            </div>
            <div style="padding:1rem;">
                <canvas id="chartTherapists" height="250"></canvas>
            </div>
        </div>

        <div class="card mb-4" style="background:linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);">
            <div class="card-header border-0">
                <h2 class="card-title" style="color:#b45309;"><span class="material-icons-outlined" style="vertical-align:middle;margin-right:4px;">local_fire_department</span> Mapa de Calor: Ocupación por Horarios</h2>
                <p class="text-xs text-muted" style="margin:0;">Colores más oscuros indican mayor ocupación histórica. Busca los espacios claros para potenciar promociones o "Happy hours" de fisioterapia.</p>
            </div>
            <div style="padding:1.5rem;overflow-x:auto;">
                <div id="biHeatmapContainer" style="display:grid;grid-template-columns:auto repeat(14, 1fr);gap:4px;min-width:600px;">
                    <!-- Generado por JS -->
                </div>
            </div>
        </div>
    </div> <!-- END TAB INTELIGENCIA -->

</div>

<div class="modal-overlay" id="modalNuevoGastoFijo">
    <div class="modal-sheet" style="max-width:560px;">
        <button class="modal-close" onclick="closeModal('modalNuevoGastoFijo')">&times;</button>
        <h3 class="modal-title" style="display:flex;align-items:center;gap:0.55rem;margin-bottom:1rem;">
            <span class="material-icons-outlined" style="color:#ef4444;font-size:1.2rem;">receipt_long</span>
            Registrar Gasto Fijo
        </h3>
        <form id="formFixedExpense" onsubmit="saveFixedExpense(event)">
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0.85rem;">
                <div class="form-group" style="grid-column:1 / -1;">
                    <label>Nombre *</label>
                    <input type="text" id="fixed_name" class="form-control" placeholder="Ej: Alquiler local, Sueldo recepcion" required>
                </div>
                <div class="form-group">
                    <label>Categoria</label>
                    <select id="fixed_category" class="form-control">
                        <option value="alquiler">Alquiler</option>
                        <option value="sueldos">Sueldos</option>
                        <option value="luz">Luz</option>
                        <option value="agua">Agua</option>
                        <option value="internet">Internet</option>
                        <option value="servicios">Servicios</option>
                        <option value="otros">Otros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto mensual *</label>
                    <input type="number" step="0.01" min="0.01" id="fixed_amount" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Dia de pago</label>
                    <input type="number" min="1" max="31" id="fixed_due_day" class="form-control" placeholder="5">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select id="fixed_active" class="form-control">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1 / -1;">
                    <label>Notas</label>
                    <textarea id="fixed_notes" class="form-control" rows="3" placeholder="Detalle opcional"></textarea>
                </div>
                <div style="grid-column:1 / -1;">
                    <button type="submit" class="btn-primary" id="btnSaveFixedExpense">Guardar Gasto Fijo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.tab-btn {
    padding: 0.5rem 0.25rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}
.tab-btn:hover {
    color: var(--primary-color);
}
.tab-btn.active {
    color: var(--primary-color);
    border-bottom: 2px solid var(--primary-color);
}

.heatmap-cell {
    border-radius: 4px;
    background: #f1f5f9;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    color: #475569;
    font-weight: 500;
    cursor: default;
    transition: transform 0.2s;
}
.heatmap-cell:hover {
    transform: scale(1.1);
    z-index: 10;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}
.heatmap-label-row { font-size:0.75rem; color:var(--text-muted); font-weight:600; display:flex; align-items:center; justify-content:flex-end; padding-right:0.5rem; }
.heatmap-label-col { font-size:0.7rem; color:var(--text-muted); font-weight:600; text-align:center; margin-bottom:4px; }

@media (max-width: 1100px) {
    .finance-charts-grid {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 860px) {
    .metrics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .cash-commitment-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
    .animate-fade-in > div[style*="grid-template-columns:1.2fr 0.8fr"],
    .animate-fade-in > div[style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
@media (max-width: 640px) {
    .cash-commitment-grid {
        grid-template-columns: 1fr !important;
    }
    .commitment-row {
        flex-direction: column;
    }
}
.report-empty {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--text-muted);
}
.fixed-expense-row {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:0.75rem;
    padding:0.9rem 1rem;
    border-top:1px solid var(--border-color);
}
.fixed-expense-row:first-child {
    border-top:none;
}
.commitment-row {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:0.9rem;
    padding:0.95rem 1rem;
    border-top:1px solid var(--border-color);
}
.commitment-row:first-child {
    border-top:none;
}
.commitment-status-pill {
    display:inline-flex;
    align-items:center;
    gap:0.3rem;
    padding:0.22rem 0.55rem;
    border-radius:999px;
    font-size:0.75rem;
    font-weight:700;
}
.commitment-actions {
    display:flex;
    gap:0.45rem;
    flex-wrap:wrap;
    justify-content:flex-end;
}
.btn-commitment {
    border:1px solid var(--border-color);
    background:#fff;
    border-radius:999px;
    padding:0.45rem 0.75rem;
    font-size:0.8rem;
    font-weight:700;
    cursor:pointer;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const reportsCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const MONTHES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
let reportsData = null;
let biLoaded = false;
let selectedFinanceMonth = '';

function getSelectedFinanceMonth() {
    const value = String(document.getElementById('financeMonthPicker')?.value || '').trim();
    return /^\d{4}-\d{2}$/.test(value) ? value : '<?= date('Y-m') ?>';
}

function getMonthLabel(ym) {
    const [year, month] = String(ym || '').split('-');
    const idx = Number(month) - 1;
    if (!year || idx < 0 || idx > 11) return ym || '';
    return MONTHES[idx] + ' ' + year;
}

function handleFinanceMonthChange() {
    initReports();
}

function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('btn-tab-' + tabId).classList.add('active');
    
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.getElementById('tab-' + tabId).style.display = 'block';

    if (tabId === 'bi' && !biLoaded) {
        initBI();
    }
}

async function initBI() {
    const dateFrom = document.getElementById('biFilterDateFrom').value;
    const dateTo = document.getElementById('biFilterDateTo').value;
    const therapistId = document.getElementById('biFilterTherapist').value;
    const diagnosis = document.getElementById('biFilterDiagnosis').value;
    
    const queryParams = new URLSearchParams();
    if(dateFrom) queryParams.append('date_from', dateFrom);
    if(dateTo) queryParams.append('date_to', dateTo);
    if(therapistId) queryParams.append('therapist_id', therapistId);
    if(diagnosis) queryParams.append('diagnosis', diagnosis);

    try {
        const res = await fetch('api/analytics.php?' + queryParams.toString());
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Error');
        
        if (!biLoaded) {
            // Llenar selects de filtros la primera vez
            const thSelect = document.getElementById('biFilterTherapist');
            json.filterOptions.therapists.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id; opt.textContent = t.name;
                thSelect.appendChild(opt);
            });
            const diagSelect = document.getElementById('biFilterDiagnosis');
            json.filterOptions.diagnoses.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.title; opt.textContent = d.title;
                diagSelect.appendChild(opt);
            });
            // Re-aplicar valores seleccionados en caso de re-render
            if (therapistId) thSelect.value = therapistId;
            if (diagnosis) diagSelect.value = diagnosis;
            biLoaded = true;
        }
        
        renderBIAdherence(json.adherence);
        renderBIRetention(json.retention);
        renderBIDiagnoses(json.diagnoses);
        renderBITherapists(json.therapists);
        renderBIHeatmap(json.heatmap);
        
        // Nuevos
        document.getElementById('biLtvValue').textContent = 'S/ ' + Number(json.ltv.ltv || 0).toFixed(2);
        document.getElementById('biEvaValue').textContent = Number(json.eva.initial_eva || 0).toFixed(1) + ' / 10';
        
        renderBIPackages(json.packages);
        renderBICancellations(json.cancellations);
        renderBIDemographics(json.demographics);
    } catch(e) {
        showToast('No se pudo cargar la vista de inteligencia', 'error');
    }
}

function renderBIPackages(data) {
    const ctx = document.getElementById('chartPackages').getContext('2d');
    destroyChartIfNeeded('chartPackages');
    
    let pack = 0; let single = 0;
    data.forEach(d => {
        if(d.type === 'package_purchase') pack = Number(d.total_vol);
        else single = Number(d.total_vol);
    });

    if(pack===0 && single===0) return;

    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Paquetes', 'Sesión Suelta'],
            datasets: [{
                data: [pack, single],
                backgroundColor: ['#8b5cf6', '#cbd5e1']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
}

function renderBICancellations(data) {
    const ctx = document.getElementById('chartCancellations').getContext('2d');
    destroyChartIfNeeded('chartCancellations');
    
    let comp = 0; let canc = 0; let sched = 0;
    data.forEach(d => {
        if(d.status === 'completed') comp = Number(d.count);
        if(d.status === 'cancelled') canc = Number(d.count);
        if(d.status === 'scheduled') sched = Number(d.count);
    });

    if(comp===0 && canc===0 && sched===0) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Canceladas', 'Completadas', 'Programadas'],
            datasets: [{
                data: [canc, comp, sched],
                backgroundColor: ['#ef4444', '#10b981', '#3b82f6'],
                cutout: '70%', borderWidth:0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
}

function renderBIDemographics(data) {
    const ctx = document.getElementById('chartDemographics').getContext('2d');
    destroyChartIfNeeded('chartDemographics');
    if(!data) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['0-17 años', '18-35 años', '36-50 años', '51-65 años', '+65 años'],
            datasets: [{
                label: 'Pacientes',
                data: [data['0-17'], data['18-35'], data['36-50'], data['51-65'], data['Over 65']],
                backgroundColor: '#d946ef', borderRadius: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
}

function renderBIAdherence(data) {
    const ctx = document.getElementById('chartAdherence').getContext('2d');
    destroyChartIfNeeded('chartAdherence');
    
    // Si todos estan en 0, no mostrar
    if (data.terminado === 0 && data.mitad === 0 && data.abandono === 0) {
        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
        ctx.font = '14px sans-serif'; ctx.fillStyle = '#666'; ctx.textAlign = 'center';
        ctx.fillText('Sin datos para este filtro', ctx.canvas.width / 2, ctx.canvas.height / 2);
        return;
    }

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completado (100%)', 'Mitad (50-99%)', 'Abandonado (<50%)'],
            datasets: [{
                data: [data.terminado, data.mitad, data.abandono],
                backgroundColor: ['#22c55e', '#eab308', '#ef4444'],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }
            }
        }
    });
}

function renderBIRetention(data) {
    const retained = Number(data.retained) || 0;
    const dropped = Number(data.dropped) || 0;
    const ctx = document.getElementById('chartRetention').getContext('2d');
    destroyChartIfNeeded('chartRetention');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Regresaron (>1 sesión)', 'No regresaron (1 sesión)'],
            datasets: [{
                data: [retained, dropped],
                backgroundColor: ['#10b981', '#f43f5e'],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }
            }
        }
    });
}
function renderBIDiagnoses(data) {
    const ctx = document.getElementById('chartDiagnoses').getContext('2d');
    destroyChartIfNeeded('chartDiagnoses');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(d => d.diagnosis),
            datasets: [{
                label: 'Citas atendidas',
                data: data.map(d => d.occurrences),
                backgroundColor: '#3b82f6',
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
}

function renderBITherapists(data) {
    const ctx = document.getElementById('chartTherapists').getContext('2d');
    destroyChartIfNeeded('chartTherapists');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(t => t.therapist_name),
            datasets: [
                {
                    label: 'Pacientes Retenidos',
                    data: data.map(t => t.retained),
                    backgroundColor: '#10b981',
                    borderRadius: 4
                },
                {
                    label: 'No Regresaron',
                    data: data.map(t => t.dropped),
                    backgroundColor: '#fb7185',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true },
                y: { stacked: true }
            }
        }
    });
}

function renderBIHeatmap(data) {
    // 1=Sun, 2=Mon... 7=Sat
    const daysStr = ['', 'Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    // Filter out Sunday if not needed, let's include 1 to 6 (Monday to Saturday broadly) 
    // Wait, Hostgator mysql DAYOFWEEK: 1=Sun, 7=Sat. Let's just create rows.
    
    // Configura horas desde 8 hasta 21
    const minHour = 8;
    const maxHour = 21;
    const colsCount = maxHour - minHour + 1;
    
    const container = document.getElementById('biHeatmapContainer');
    let html = '';
    
    // Header Row (Horas)
    html += '<div style="grid-column: 1 / -1; display:grid; grid-template-columns: auto repeat('+colsCount+', 1fr); gap:4px; margin-bottom:4px;">';
    html += '<div style="width:60px;"></div>'; // empty for day labels
    for(let h=minHour; h<=maxHour; h++) {
        html += '<div class="heatmap-label-col">'+h+':00</div>';
    }
    html += '</div>';

    // Find max occurrences to colorize correctly
    let maxOccur = 0;
    data.forEach(d => { if(Number(d.occurrences) > maxOccur) maxOccur = Number(d.occurrences); });

    // Days: Lunes=2, Martes=3... Sabado=7, Domingo=1
    const dOrder = [2, 3, 4, 5, 6, 7]; // omitimos domingo si queremos, pero lo agregamos al final para el que trabaja domingo
    dOrder.push(1);

    for(let i=0; i<dOrder.length; i++) {
        let sqlDay = dOrder[i];
        html += '<div class="heatmap-label-row" style="width:60px;">' + daysStr[sqlDay].substring(0,3) + '</div>';
        
        for(let h=minHour; h<=maxHour; h++) {
            const cell = data.find(d => Number(d.day_of_week) === sqlDay && Number(d.hour_of_day) === h);
            const qty = cell ? Number(cell.occurrences) : 0;
            
            // Color map (b45309 = de ambar a naranja/rojo)
            let opacity = qty === 0 ? 0 : 0.15 + (0.85 * (qty / Math.max(maxOccur, 1)));
            let bgStr = qty === 0 ? '#f1f5f9' : `rgba(245, 158, 11, ${opacity})`; // ambar
            let colorStr = qty > (maxOccur/2) ? '#fff' : '#92400e';
            let cursorStr = qty === 0 ? '' : 'title="'+qty+' citas"';
            
            html += `<div class="heatmap-cell" style="background:${bgStr};color:${colorStr};" ${cursorStr}>${qty === 0 ? '' : qty}</div>`;
        }
    }
    
    // Apply grid structure inline because column counts might change
    container.style.gridTemplateColumns = `80px repeat(${colsCount}, 1fr)`;
    container.innerHTML = html;
}

// initReports es la original de finanzas
async function initReports() {
    try {
        selectedFinanceMonth = getSelectedFinanceMonth();
        const params = new URLSearchParams({ month: selectedFinanceMonth });
        const res = await fetch('api/reports.php?' + params.toString());
        const json = await res.json();
        if (!json.success) {
            showToast(json.error || 'No se pudieron cargar los reportes', 'error');
            return;
        }
        reportsData = json;
        selectedFinanceMonth = String(json.current_month || selectedFinanceMonth);
        renderSummary(json.summary);
        renderDailyBalance(json.daily_income, json.daily_expenses);
        renderMonthlyBalance(json.monthly_income, json.monthly_expenses);
        renderExpenseCategories(json.expense_categories);
        renderDailyTable(json.daily_income, json.daily_expenses);
        renderFixedExpenses(json.fixed_expenses);
        renderCashCommitments(json.cash_commitments, json.cash_summary);
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

function formatMoney(value) {
    return 'S/ ' + Number(value || 0).toFixed(2);
}

function renderSummary(summary) {
    const monthLabel = getMonthLabel(selectedFinanceMonth);
    document.getElementById('summaryIncomeLabel').textContent = 'Ingresos de ' + monthLabel;
    document.getElementById('dailyBalanceTitle').textContent = 'Ingresos vs Gastos de ' + monthLabel;
    document.getElementById('expenseCategoryTitle').textContent = 'Gastos por categoria de ' + monthLabel;
    document.getElementById('dailySummaryTitle').textContent = 'Resumen diario de ' + monthLabel;
    document.getElementById('monthlyBalanceTitle').textContent = 'Balance mensual ' + String((selectedFinanceMonth || '').split('-')[0] || '');

    document.getElementById('summaryIncome').textContent = formatMoney(summary.income_month);
    document.getElementById('summaryExpenses').textContent = formatMoney(summary.expenses_month);
    document.getElementById('summaryFixed').textContent = formatMoney(summary.fixed_expenses_month);
    const resultEl = document.getElementById('summaryResult');
    resultEl.textContent = formatMoney(summary.operating_result);
    resultEl.style.color = summary.operating_result >= 0 ? 'var(--success)' : 'var(--danger)';

    const progress = Math.min(100, Number(summary.break_even_progress || 0));
    document.getElementById('breakEvenBar').style.width = progress + '%';
    document.getElementById('breakEvenPercent').textContent = progress.toFixed(1) + '%';
    document.getElementById('fixedCoverageValue').textContent = progress.toFixed(1) + '%';
    document.getElementById('remainingCoverageValue').textContent = formatMoney(summary.remaining_to_break_even);

    const title = document.getElementById('breakEvenTitle');
    const subtitle = document.getElementById('breakEvenSubtitle');
    const bar = document.getElementById('breakEvenBar');
    const utility = document.getElementById('utilityStatusValue');

    if (summary.is_profitable) {
        title.textContent = 'Ya estas ganando en ' + monthLabel;
        subtitle.textContent = 'Tus ingresos ya cubrieron gastos fijos y gastos variables.';
        bar.style.background = 'linear-gradient(90deg,#16a34a,#22c55e)';
        document.getElementById('breakEvenPercent').style.color = '#15803d';
        utility.textContent = 'En verde';
        utility.style.color = '#15803d';
    } else if (Number(summary.remaining_to_break_even) <= 0) {
        title.textContent = 'Ya cubriste tus gastos fijos en ' + monthLabel;
        subtitle.textContent = 'Ahora cada ingreso adicional ayuda a mejorar tu utilidad.';
        bar.style.background = 'linear-gradient(90deg,#0284c7,#06b6d4)';
        document.getElementById('breakEvenPercent').style.color = '#0369a1';
        utility.textContent = 'Cubriste fijos';
        utility.style.color = '#0369a1';
    } else {
        title.textContent = 'Aun no cubres tus gastos fijos de ' + monthLabel;
        subtitle.textContent = 'Faltan ' + formatMoney(summary.remaining_to_break_even) + ' para cubrir los gastos fijos del mes seleccionado.';
        bar.style.background = 'linear-gradient(90deg,#f59e0b,#fbbf24)';
        document.getElementById('breakEvenPercent').style.color = 'inherit';
        utility.textContent = 'En proceso';
        utility.style.color = '#92400e';
    }
}

function destroyChartIfNeeded(canvasId) {
    const chart = Chart.getChart(canvasId);
    if (chart) chart.destroy();
}

function renderDailyBalance(incomeRows, expenseRows) {
    const incomeMap = Object.fromEntries(incomeRows.map(r => [r.date, Number(r.total)]));
    const expenseMap = Object.fromEntries(expenseRows.map(r => [r.date, Number(r.total)]));
    const allDates = Array.from(new Set([...Object.keys(incomeMap), ...Object.keys(expenseMap)])).sort();
    const ctx = document.getElementById('chartDailyBalance').getContext('2d');
    destroyChartIfNeeded('chartDailyBalance');

    if (!allDates.length) {
        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
        ctx.font = '14px sans-serif';
        ctx.fillStyle = '#666';
        ctx.textAlign = 'center';
        ctx.fillText('No hay movimientos en ' + getMonthLabel(selectedFinanceMonth), ctx.canvas.width / 2, ctx.canvas.height / 2);
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: allDates.map(d => d.split('-')[2]),
            datasets: [
                { label: 'Ingresos', data: allDates.map(d => incomeMap[d] || 0), backgroundColor: '#14b8a6', borderRadius: 6 },
                { label: 'Gastos', data: allDates.map(d => expenseMap[d] || 0), backgroundColor: '#ef4444', borderRadius: 6 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderMonthlyBalance(incomeRows, expenseRows) {
    const incomeMap = Object.fromEntries(incomeRows.map(r => [String(r.month_num), Number(r.total)]));
    const expenseMap = Object.fromEntries(expenseRows.map(r => [String(r.month_num), Number(r.total)]));
    const labels = Array.from({ length: 12 }, (_, i) => String(i + 1));
    const ctx = document.getElementById('chartMonthlyBalance').getContext('2d');
    destroyChartIfNeeded('chartMonthlyBalance');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.map(m => MONTHES[Number(m) - 1]),
            datasets: [
                { label: 'Ingresos', data: labels.map(m => incomeMap[m] || 0), borderColor: '#14b8a6', backgroundColor: 'rgba(20,184,166,0.10)', fill: false, tension: 0.25 },
                { label: 'Gastos', data: labels.map(m => expenseMap[m] || 0), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.10)', fill: false, tension: 0.25 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderExpenseCategories(rows) {
    const ctx = document.getElementById('chartExpenseCategories').getContext('2d');
    destroyChartIfNeeded('chartExpenseCategories');
    if (!rows.length) {
        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
        ctx.font = '14px sans-serif';
        ctx.fillStyle = '#666';
        ctx.textAlign = 'center';
        ctx.fillText('Sin gastos cargados en ' + getMonthLabel(selectedFinanceMonth), ctx.canvas.width / 2, ctx.canvas.height / 2);
        return;
    }
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: rows.map(r => r.category),
            datasets: [{
                data: rows.map(r => Number(r.total)),
                backgroundColor: ['#ef4444', '#f97316', '#eab308', '#3b82f6', '#8b5cf6', '#14b8a6', '#64748b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderDailyTable(incomeRows, expenseRows) {
    const container = document.getElementById('tableDailyBalance');
    const incomeMap = Object.fromEntries(incomeRows.map(r => [r.date, Number(r.total)]));
    const expenseMap = Object.fromEntries(expenseRows.map(r => [r.date, Number(r.total)]));
    const allDates = Array.from(new Set([...Object.keys(incomeMap), ...Object.keys(expenseMap)])).sort().reverse();

    if (!allDates.length) {
        container.innerHTML = '<div class="report-empty">Sin movimientos en ' + getMonthLabel(selectedFinanceMonth) + '</div>';
        return;
    }

    container.innerHTML = allDates.map(date => {
        const income = incomeMap[date] || 0;
        const expense = expenseMap[date] || 0;
        const balance = income - expense;
        return `
            <div class="table-row" style="grid-template-columns:1fr auto auto auto; padding:0.85rem 1rem;">
                <span style="font-weight:700;">${date.split('-').reverse().join('/')}</span>
                <span style="color:#16a34a;font-weight:700;">${formatMoney(income)}</span>
                <span style="color:#dc2626;font-weight:700;">${formatMoney(expense)}</span>
                <span style="color:${balance >= 0 ? '#16a34a' : '#dc2626'};font-weight:800;">${formatMoney(balance)}</span>
            </div>
        `;
    }).join('');
}

function renderFixedExpenses(items) {
    const container = document.getElementById('fixedExpensesList');
    if (!items.length) {
        container.innerHTML = '<div class="report-empty">Aun no has registrado gastos fijos</div>';
        return;
    }

    container.innerHTML = items.map(item => `
        <div class="fixed-expense-row" id="fixed-expense-${item.id}">
            <div>
                <div style="font-weight:800;">${item.name}</div>
                <div class="text-sm text-muted">${item.category || 'fijo'}${item.due_day ? ' · dia ' + item.due_day : ''}${Number(item.is_active) === 1 ? ' · activo' : ' · inactivo'}</div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:0.5rem;">
                <strong>${formatMoney(item.amount)}</strong>
                <button type="button" onclick="deleteFixedExpense(${item.id})" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0;">
                    <span class="material-icons-outlined" style="font-size:1rem;">delete</span>
                </button>
            </div>
        </div>
    `).join('');
}

function getCommitmentStatusMeta(item) {
    if (item.status === 'paid') {
        return { label: 'Pagado', bg: '#dcfce7', color: '#166534' };
    }
    if (item.is_overdue) {
        return { label: 'Vencido', bg: '#fee2e2', color: '#991b1b' };
    }
    if (item.status === 'deferred') {
        return { label: 'Pospuesto', bg: '#ffedd5', color: '#9a3412' };
    }
    return { label: 'Pendiente', bg: '#e0f2fe', color: '#075985' };
}

function escapeHtml(value) {
    return String(value || '').replace(/[&<>"]/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[char]));
}

function renderCashCommitments(data, cashSummary) {
    const summary = data?.summary || {};
    const items = Array.isArray(data?.items) ? data.items : [];
    const nextPayment = summary.next_payment || null;

    document.getElementById('commitmentCashAvailable').textContent = formatMoney(summary.cash_available ?? cashSummary?.system_cash ?? 0);
    document.getElementById('commitmentPendingValue').textContent = formatMoney(summary.pending || 0);
    document.getElementById('commitmentNextGapValue').textContent = formatMoney(summary.next_payment_gap || 0);
    document.getElementById('commitmentNextPaymentName').textContent = nextPayment ? String(nextPayment.name || '') : 'Sin pagos';
    document.getElementById('commitmentNextPaymentAmount').textContent = nextPayment
        ? `${formatMoney(nextPayment.amount)} · vence ${String(nextPayment.due_date || '').split('-').reverse().join('/')}`
        : 'S/ 0.00';

    const gapEl = document.getElementById('commitmentNextGapValue');
    gapEl.style.color = Number(summary.next_payment_gap || 0) > 0 ? '#b91c1c' : '#15803d';

    const coverage = Math.max(0, Math.min(100, Number(summary.next_payment_coverage_percent || 0)));
    document.getElementById('commitmentCoverageBar').style.width = coverage + '%';
    document.getElementById('commitmentCoveragePercent').textContent = coverage.toFixed(1) + '%';

    const title = document.getElementById('cashCommitmentTitle');
    const subtitle = document.getElementById('cashCommitmentSubtitle');
    const coverageText = document.getElementById('commitmentCoverageText');
    const bar = document.getElementById('commitmentCoverageBar');

    if (!nextPayment) {
        title.textContent = 'No hay proximos pagos pendientes';
        subtitle.textContent = 'Todos los compromisos cercanos ya estan pagados o no hay gastos fijos activos.';
        coverageText.textContent = 'Cobertura del proximo pago';
        bar.style.background = 'linear-gradient(90deg,#16a34a,#22c55e)';
    } else if (Number(summary.next_payment_gap || 0) <= 0) {
        title.textContent = 'La caja ya alcanza para asumir el proximo pago';
        subtitle.textContent = `Hoy ya puedes cubrir ${String(nextPayment.name || '')} por ${formatMoney(nextPayment.amount)} sin esperar mas caja.`;
        coverageText.textContent = 'Cobertura de caja del proximo pago';
        bar.style.background = 'linear-gradient(90deg,#0284c7,#06b6d4)';
    } else {
        title.textContent = 'La caja aun no alcanza para el proximo pago';
        subtitle.textContent = `Falta ${formatMoney(summary.next_payment_gap || 0)} para poder asumir ${String(nextPayment.name || '')} de ${formatMoney(nextPayment.amount)}.`;
        coverageText.textContent = 'Cobertura de caja del proximo pago';
        bar.style.background = 'linear-gradient(90deg,#f59e0b,#f97316)';
    }

    const container = document.getElementById('cashCommitmentsList');
    if (!items.length) {
        container.innerHTML = '<div class="report-empty">No hay compromisos activos para este periodo</div>';
        return;
    }

    container.innerHTML = items.map(item => {
        const meta = getCommitmentStatusMeta(item);
        const dueText = item.planned_due_date ? item.planned_due_date.split('-').reverse().join('/') : 'Sin fecha';
        const paidText = item.paid_at ? item.paid_at.replace(' ', ' · ') : '';

        return `
            <div class="commitment-row" id="commitment-${item.id}">
                <div style="min-width:0;">
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                        <div style="font-weight:800;">${escapeHtml(item.name)}</div>
                        <span class="commitment-status-pill" style="background:${meta.bg};color:${meta.color};">${meta.label}</span>
                    </div>
                    <div class="text-sm text-muted" style="margin-top:0.25rem;">
                        ${escapeHtml(item.category || 'fijo')} · vence ${dueText}${item.payment_method ? ' · ' + escapeHtml(item.payment_method) : ''}${paidText ? ' · ' + escapeHtml(paidText) : ''}
                    </div>
                    ${item.notes ? `<div class="text-sm text-muted" style="margin-top:0.25rem;">${escapeHtml(item.notes)}</div>` : ''}
                </div>
                <div style="text-align:right;min-width:220px;">
                    <div style="font-size:1rem;font-weight:800;margin-bottom:0.55rem;">${formatMoney(item.amount)}</div>
                    <div class="commitment-actions">
                        ${item.status !== 'paid' ? `<button type="button" class="btn-commitment" style="color:#166534;border-color:#bbf7d0;" onclick="markCommitmentPaid(${item.id}, '${item.cycle_month}')">Registrar pago</button>` : ''}
                        ${item.status === 'pending' ? `<button type="button" class="btn-commitment" style="color:#9a3412;border-color:#fed7aa;" onclick="updateCommitmentStatus(${item.id}, '${item.cycle_month}', 'mark_deferred')">Posponer</button>` : ''}
                        ${item.status === 'deferred' ? `<button type="button" class="btn-commitment" style="color:#075985;border-color:#bae6fd;" onclick="updateCommitmentStatus(${item.id}, '${item.cycle_month}', 'mark_pending')">Volver a pendiente</button>` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

async function markCommitmentPaid(fixedExpenseId, cycleMonth) {
    const paymentMethod = prompt('Metodo de pago del gasto fijo:', 'Efectivo');
    if (paymentMethod === null) return;

    const notes = prompt('Observacion opcional para este pago:', '');
    if (!confirm('Se registrara este gasto fijo como pagado y se descontara de caja/gastos. Deseas continuar?')) return;

    try {
        const res = await fetch('api/fixed_expense_cycles.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': reportsCsrfToken},
            body: JSON.stringify({
                action: 'mark_paid',
                fixed_expense_id: fixedExpenseId,
                cycle_month: cycleMonth,
                payment_method: paymentMethod || 'Efectivo',
                notes: notes || ''
            })
        });
        const json = await res.json();
        if (json.success) {
            showToast(json.message || 'Pago registrado', 'success');
            initReports();
        } else {
            showToast(json.error || 'No se pudo registrar el pago', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function updateCommitmentStatus(fixedExpenseId, cycleMonth, action) {
    const notes = prompt(action === 'mark_deferred' ? 'Motivo del retraso o posposicion:' : 'Observacion opcional:', '');
    if (notes === null) return;

    try {
        const res = await fetch('api/fixed_expense_cycles.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': reportsCsrfToken},
            body: JSON.stringify({
                action,
                fixed_expense_id: fixedExpenseId,
                cycle_month: cycleMonth,
                notes
            })
        });
        const json = await res.json();
        if (json.success) {
            showToast(json.message || 'Compromiso actualizado', 'success');
            initReports();
        } else {
            showToast(json.error || 'No se pudo actualizar el compromiso', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function saveFixedExpense(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveFixedExpense');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const data = {
        name: document.getElementById('fixed_name').value,
        category: document.getElementById('fixed_category').value,
        amount: document.getElementById('fixed_amount').value,
        due_day: document.getElementById('fixed_due_day').value,
        notes: document.getElementById('fixed_notes').value,
        is_active: document.getElementById('fixed_active').value === '1'
    };

    try {
        const res = await fetch('api/fixed_expenses.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': reportsCsrfToken},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            showToast('Gasto fijo registrado', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showToast(json.error || 'No se pudo registrar el gasto fijo', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar Gasto Fijo';
    }
}

async function deleteFixedExpense(id) {
    if (!confirm('Eliminar este gasto fijo?')) return;
    try {
        const res = await fetch('api/fixed_expenses.php', {
            method: 'DELETE',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': reportsCsrfToken},
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
            showToast('Gasto fijo eliminado', 'success');
            document.getElementById('fixed-expense-' + id)?.remove();
            setTimeout(() => location.reload(), 350);
        } else {
            showToast(json.error || 'No se pudo eliminar el gasto fijo', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

document.addEventListener('DOMContentLoaded', initReports);
</script>

<?php require_once 'includes/footer.php'; ?>
