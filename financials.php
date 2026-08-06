<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once 'db.php';
ensureExpenseSchema($pdo);
ensureCashReconciliationSchema($pdo);
ensureFixedExpenseSchema($pdo);
ensureFixedExpenseCycleSchema($pdo);

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'receptionist'], true)) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Caja';
require_once 'includes/header.php';

$sql = "
    SELECT t.*, u.name AS patient_name, u.dni AS patient_dni
    FROM transactions t
    JOIN users u ON t.patient_id = u.id
";

if ($userRole === 'receptionist') {
    $sql .= " WHERE DATE(t.transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) ";
}

$sql .= " ORDER BY t.transaction_date DESC, t.id DESC ";

$allTxns = [];
try {
    $allTxns = pdoQuery($pdo, $sql)->fetchAll();
} catch (Exception $e) {}

$expenses = [];
try {
    $expenses = pdoQuery(
        $pdo,
        "SELECT e.*, u.name AS created_by_name
         FROM expenses e
         LEFT JOIN users u ON u.id = e.created_by
         ORDER BY e.expense_date DESC, e.id DESC"
    )->fetchAll();
} catch (Exception $e) {}

$totalHistorico = array_sum(array_column(array_filter($allTxns, fn($t) => (float)$t['amount'] > 0), 'amount'));
$monthTxns = array_filter($allTxns, fn($t) => (float)$t['amount'] > 0 && date('Y-m', strtotime($t['transaction_date'])) === date('Y-m'));
$totalMes = array_sum(array_column($monthTxns, 'amount'));
$monthExpenses = array_filter($expenses, fn($e) => date('Y-m', strtotime($e['expense_date'])) === date('Y-m'));
$totalExpensesMonth = array_sum(array_column($monthExpenses, 'amount'));
$netMonth = $totalMes - $totalExpensesMonth;
$cashSummary = getCashLedgerSummary($pdo);

$upcomingCashNeeds = [
    'items' => [],
    'overdue_items' => [],
    'upcoming_items' => [],
    'total' => 0.0,
    'gap' => 0.0,
    'coverage_percent' => 100.0,
    'cash_available' => round((float)($cashSummary['system_cash'] ?? 0), 2),
    'overdue_total' => 0.0,
    'upcoming_total' => 0.0,
    'window_label' => '',
];

if ($userRole === 'admin') {
    try {
        $peTz = new DateTimeZone('America/Lima');
        $todayPe = new DateTimeImmutable('now', $peTz);
        $todayStart = $todayPe->setTime(0, 0, 0);
        $windowEnd = $todayPe->modify('+10 days');
        $windowLabel = $todayPe->format('d/m') . ' al ' . $windowEnd->format('d/m');

        $fixedItems = pdoQuery(
            $pdo,
            "SELECT * FROM fixed_expenses WHERE is_active = 1 ORDER BY due_day ASC, name ASC"
        )->fetchAll();

        $candidateMonths = [
            $todayPe->modify('first day of last month'),
            $todayPe->modify('first day of this month'),
            $todayPe->modify('first day of next month'),
        ];
        $candidateMonthKeys = array_values(array_unique(array_map(static fn($date) => $date->format('Y-m'), $candidateMonths)));

        $cycleMap = [];
        if ($candidateMonthKeys) {
            $placeholders = implode(',', array_fill(0, count($candidateMonthKeys), '?'));
            $cycleRows = pdoQuery(
                $pdo,
                "SELECT * FROM fixed_expense_cycles WHERE cycle_month IN ($placeholders)",
                $candidateMonthKeys
            )->fetchAll();
            foreach ($cycleRows as $cycleRow) {
                $cycleMap[(int)$cycleRow['fixed_expense_id'] . '|' . (string)$cycleRow['cycle_month']] = $cycleRow;
            }
        }

        $upcomingItems = [];
        $overdueTotal = 0.0;
        $upcomingTotalOnly = 0.0;
        foreach ($fixedItems as $item) {
            $dueDay = (int)($item['due_day'] ?? 0);
            if ($dueDay <= 0) {
                continue;
            }

            foreach ($candidateMonths as $monthStart) {
                $lastDay = (int)$monthStart->format('t');
                $normalizedDueDay = min($dueDay, $lastDay);
                $dueDate = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), $normalizedDueDay);
                $cycleMonth = $monthStart->format('Y-m');

                if ($dueDate > $windowEnd) {
                    continue;
                }

                $cycleKey = (int)$item['id'] . '|' . $cycleMonth;
                $cycle = $cycleMap[$cycleKey] ?? null;
                if (($cycle['status'] ?? '') === 'paid') {
                    continue;
                }

                $isOverdue = $dueDate < $todayStart;
                if ($isOverdue) {
                    $overdueTotal += round((float)$item['amount'], 2);
                } else {
                    $upcomingTotalOnly += round((float)$item['amount'], 2);
                }

                $upcomingItems[] = [
                    'id' => (int)$item['id'],
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'amount' => round((float)$item['amount'], 2),
                    'due_date' => $dueDate->format('Y-m-d'),
                    'due_label' => $dueDate->format('d/m'),
                    'cycle_month' => $cycleMonth,
                    'status' => $cycle['status'] ?? 'pending',
                    'is_overdue' => $isOverdue,
                ];
                break;
            }
        }

        usort($upcomingItems, static function ($a, $b) {
            if ($a['due_date'] === $b['due_date']) {
                return strcmp((string)$a['name'], (string)$b['name']);
            }
            return strcmp((string)$a['due_date'], (string)$b['due_date']);
        });

        $overdueItems = array_values(array_filter($upcomingItems, static fn($item) => !empty($item['is_overdue'])));
        $futureItems = array_values(array_filter($upcomingItems, static fn($item) => empty($item['is_overdue'])));

        $upcomingTotal = round(array_sum(array_column($upcomingItems, 'amount')), 2);
        $cashAvailable = round((float)($cashSummary['system_cash'] ?? 0), 2);
        $upcomingGap = round(max(0, $upcomingTotal - $cashAvailable), 2);
        $upcomingCoverage = $upcomingTotal > 0 ? min(100, round(($cashAvailable / $upcomingTotal) * 100, 1)) : 100;

        $upcomingCashNeeds = [
            'items' => $upcomingItems,
            'overdue_items' => $overdueItems,
            'upcoming_items' => $futureItems,
            'total' => $upcomingTotal,
            'gap' => $upcomingGap,
            'coverage_percent' => $upcomingCoverage,
            'cash_available' => $cashAvailable,
            'overdue_total' => round($overdueTotal, 2),
            'upcoming_total' => round($upcomingTotalOnly, 2),
            'window_label' => $windowLabel,
        ];
    } catch (Exception $e) {
    }
}

$grouped = [];
foreach ($allTxns as $t) {
    $day = date('Y-m-d', strtotime($t['transaction_date']));
    $grouped[$day][] = $t;
}
?>

<div class="animate-fade-in delay-100">
    <div style="background:var(--surface);padding:0.75rem 1rem;display:flex;justify-content:space-between;align-items:center;position:sticky;top:56px;z-index:9;border-bottom:1px solid var(--border-color);gap:0.5rem;flex-wrap:wrap;">
        <h1 style="margin:0;font-size:1.1rem;">Caja</h1>
        <div style="display:flex;gap:0.45rem;flex-wrap:wrap;">
            <?php if ($userRole === 'admin'): ?>
            <button class="btn-primary" style="width:auto;padding:0.4rem 0.9rem;display:flex;gap:0.35rem;align-items:center;font-size:0.85rem;background:#0f766e;" onclick="openCashSyncModal()">
                <span class="material-icons-outlined" style="font-size:1rem;">sync_alt</span>Sincronizar caja
            </button>
            <?php endif; ?>
            <button class="btn-primary" style="width:auto;padding:0.4rem 0.9rem;display:flex;gap:0.35rem;align-items:center;font-size:0.85rem;" onclick="openModal('modalCobrarPago')">
                <span class="material-icons-outlined" style="font-size:1rem;">payments</span>Cobrar
            </button>
            <button class="btn-primary" style="width:auto;padding:0.4rem 0.9rem;display:flex;gap:0.35rem;align-items:center;font-size:0.85rem;background:#ef4444;" onclick="openModal('modalNuevoGasto')">
                <span class="material-icons-outlined" style="font-size:1rem;">receipt_long</span>Gasto
            </button>
        </div>
    </div>

    <div class="metrics-grid" style="border-bottom:1px solid var(--border-color);">
        <div class="metric-card">
            <div class="metric-label">Ingresos del mes</div>
            <div class="metric-value" style="color:var(--success);">S/ <?= number_format($totalMes, 2) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Gastos del mes</div>
            <div class="metric-value" style="color:var(--danger);">S/ <?= number_format($totalExpensesMonth, 2) ?></div>
        </div>
        <?php if ($userRole === 'admin'): ?>
        <div class="metric-card">
            <div class="metric-label">Balance del mes</div>
            <div class="metric-value" style="color:<?= $netMonth >= 0 ? 'var(--primary-color)' : 'var(--danger)' ?>;">S/ <?= number_format($netMonth, 2) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Caja ajustada (efectivo)</div>
            <div class="metric-value" id="cashSystemValue" style="color:var(--primary-color);">S/ <?= number_format((float)($cashSummary['system_cash'] ?? 0), 2) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Ingresos historicos</div>
            <div class="metric-value">S/ <?= number_format($totalHistorico, 2) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($userRole === 'admin'): ?>
    <div class="card mb-4" style="margin:1rem 1rem 0;overflow:hidden;">
        <div style="padding:1rem;background:linear-gradient(135deg,#eff6ff 0%,#ffffff 100%);border-bottom:1px solid var(--border-color);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.8rem;flex-wrap:wrap;">
                <div>
                    <div style="font-size:0.78rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#1d4ed8;margin-bottom:0.35rem;">Proyeccion de caja</div>
                    <div style="font-size:1.1rem;font-weight:800;" id="cashProjectionTitle">Caja acumulada vs pagos arrastrados y proximos 10 dias</div>
                    <div class="text-sm text-muted" style="margin-top:0.25rem;">
                        Ventana: <?= htmlspecialchars($upcomingCashNeeds['window_label']) ?>. La base es la caja sincronizada acumulada, no el corte mensual.
                    </div>
                </div>
                <div style="padding:0.35rem 0.7rem;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:0.78rem;font-weight:700;">
                    Cobertura <?= number_format((float)$upcomingCashNeeds['coverage_percent'], 1) ?>%
                </div>
            </div>
            <div class="cash-projection-grid" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0.85rem;margin-top:1rem;">
                <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                    <div class="text-sm text-muted">Saldo caja ajustada hoy</div>
                    <div style="font-size:1.4rem;font-weight:800;color:<?= (float)$upcomingCashNeeds['cash_available'] < 0 ? '#b91c1c' : '#0369a1' ?>;">
                        S/ <?= number_format((float)$upcomingCashNeeds['cash_available'], 2) ?>
                    </div>
                </div>
                <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                    <div class="text-sm text-muted">Arrastrado pendiente</div>
                    <div style="font-size:1.4rem;font-weight:800;color:<?= (float)$upcomingCashNeeds['overdue_total'] > 0 ? '#b91c1c' : 'var(--text-main)' ?>;">S/ <?= number_format((float)$upcomingCashNeeds['overdue_total'], 2) ?></div>
                </div>
                <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                    <div class="text-sm text-muted">Proximos 10 dias</div>
                    <div style="font-size:1.4rem;font-weight:800;">S/ <?= number_format((float)$upcomingCashNeeds['upcoming_total'], 2) ?></div>
                </div>
                <div style="background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:0.9rem;">
                    <div class="text-sm text-muted">Falta por cubrir</div>
                    <div style="font-size:1.4rem;font-weight:800;color:<?= (float)$upcomingCashNeeds['gap'] > 0 ? '#b91c1c' : '#15803d' ?>;">
                        S/ <?= number_format((float)$upcomingCashNeeds['gap'], 2) ?>
                    </div>
                </div>
            </div>
            <div style="margin-top:0.9rem;height:10px;background:#dbe4ea;border-radius:999px;overflow:hidden;">
                <div style="height:100%;width:<?= min(100, max(0, (float)$upcomingCashNeeds['coverage_percent'])) ?>%;background:linear-gradient(90deg,<?= (float)$upcomingCashNeeds['gap'] > 0 ? '#f59e0b,#f97316' : '#0284c7,#06b6d4' ?>);border-radius:999px;"></div>
            </div>
        </div>
        <div>
            <?php if (count($upcomingCashNeeds['items']) > 0): ?>
                <?php if (count($upcomingCashNeeds['overdue_items']) > 0): ?>
                <div class="cash-projection-section">
                    <div class="cash-projection-section-title" style="color:#b91c1c;">Vencidos</div>
                    <?php foreach ($upcomingCashNeeds['overdue_items'] as $item): ?>
                    <div class="cash-projection-row">
                        <div style="min-width:0;">
                            <div style="font-weight:800;"><?= htmlspecialchars(app_text($item['name'])) ?></div>
                            <div class="text-sm text-muted">
                                <?= htmlspecialchars(app_text($item['category'] ?: 'fijo')) ?>
                                &middot; vencio <?= htmlspecialchars($item['due_label']) ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800;">S/ <?= number_format((float)$item['amount'], 2) ?></div>
                            <div class="text-sm" style="color:#b91c1c;">Arrastrado pendiente</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (count($upcomingCashNeeds['upcoming_items']) > 0): ?>
                <div class="cash-projection-section">
                    <div class="cash-projection-section-title" style="color:#0369a1;">Proximos 10 dias</div>
                    <?php foreach ($upcomingCashNeeds['upcoming_items'] as $item): ?>
                    <div class="cash-projection-row">
                        <div style="min-width:0;">
                            <div style="font-weight:800;"><?= htmlspecialchars(app_text($item['name'])) ?></div>
                            <div class="text-sm text-muted">
                                <?= htmlspecialchars(app_text($item['category'] ?: 'fijo')) ?>
                                &middot; vence <?= htmlspecialchars($item['due_label']) ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:800;">S/ <?= number_format((float)$item['amount'], 2) ?></div>
                            <div class="text-sm" style="color:var(--text-muted);"><?= ($item['status'] ?? 'pending') === 'deferred' ? 'Pospuesto' : 'Pendiente' ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="padding:1rem;color:var(--text-muted);text-align:center;">
                    No hay pagos fijos arrastrados ni pendientes dentro de los proximos 10 dias.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div style="padding:0.6rem 1rem;background:var(--surface);border-bottom:1px solid var(--border-color);position:sticky;top:98px;z-index:8;">
        <div style="position:relative;">
            <span class="material-icons-outlined" style="position:absolute;left:0.6rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:1.1rem;pointer-events:none;">search</span>
            <input type="search" id="searchPaciente" class="form-control" placeholder="Buscar por nombre o DNI..." style="padding-left:2.2rem;border-radius:var(--radius-full);border:1.5px solid var(--border-color);background:var(--background);" oninput="filterPayments(this.value)">
        </div>
    </div>

    <div id="resultCount" style="padding:0.35rem 1rem;font-size:0.78rem;color:var(--text-muted);background:var(--background);">
        <?= count($allTxns) ?> transacciones
    </div>

    <div class="table-header" style="grid-template-columns:1fr auto auto;top:141px;">
        <span>PACIENTE</span>
        <span style="text-align:right;">PAGO</span>
        <span style="text-align:right;padding-left:0.5rem;">METODO</span>
    </div>

    <div id="paymentsList">
    <?php if (count($allTxns) > 0): ?>
        <?php foreach ($grouped as $day => $dayTxns):
            $dayTotal = array_sum(array_column(array_filter($dayTxns, fn($t) => (float)$t['amount'] > 0), 'amount'));
            $dayLabel = date('j/m/Y', strtotime($day));
            $dayId = 'day-' . str_replace('-', '', $day);
        ?>
        <div class="day-group" id="<?= $dayId ?>">
            <div class="day-group-header">
                <span><?= $dayLabel ?></span>
                <span class="day-group-pill" id="pill-<?= $dayId ?>">S/<?= number_format($dayTotal, 2) ?></span>
            </div>
            <?php foreach ($dayTxns as $txn): $isPos = (float)$txn['amount'] > 0; ?>
            <div class="table-row payment-row" id="txn-<?= (int)$txn['id'] ?>" data-patient="<?= strtolower(htmlspecialchars($txn['patient_name'])) ?>" data-dni="<?= strtolower(htmlspecialchars($txn['patient_dni'] ?? '')) ?>" onclick="window.location='patient_profile.php?id=<?= (int)$txn['patient_id'] ?>'" style="grid-template-columns:1fr auto auto;align-items:center;cursor:pointer;">
                <div style="min-width:0;">
                    <div class="table-cell-main"><?= htmlspecialchars(app_text($txn['patient_name'])) ?></div>
                    <div class="table-cell-sub"><?= htmlspecialchars(app_text($txn['description'] ?? '')) ?></div>
                </div>
                <div style="text-align:right;padding:0 0.4rem;">
                    <span style="font-weight:700;font-size:0.9rem;color:<?= $isPos ? 'var(--text-main)' : 'var(--warning)' ?>">S/<?= number_format(abs((float)$txn['amount']), 2) ?></span>
                </div>
                <div style="text-align:right;display:flex;align-items:center;gap:0.2rem;justify-content:flex-end;">
                    <span style="font-size:0.78rem;color:var(--text-muted);white-space:nowrap;"><?= htmlspecialchars(app_text($txn['payment_method'] ?? 'Efectivo')) ?></span>
                    <?php
                        $isToday = date('Y-m-d', strtotime($txn['transaction_date'])) === date('Y-m-d');
                        if ($userRole === 'admin' || ($userRole === 'receptionist' && $isToday)):
                    ?>
                    <button onclick="event.stopPropagation(); deleteTxn(<?= (int)$txn['id'] ?>)" style="background:none;color:var(--danger);border:none;cursor:pointer;padding:0.1rem;" title="Eliminar">
                        <span class="material-icons-outlined" style="font-size:0.9rem;">delete</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
            <span class="material-icons-outlined" style="font-size:3rem;display:block;margin-bottom:0.5rem;opacity:0.3;">receipt_long</span>
            No hay transacciones registradas aun.
            <br><button class="btn-primary" style="width:auto;padding:0.5rem 1.25rem;margin-top:1rem;" onclick="openModal('modalCobrarPago')">Registrar primer pago</button>
        </div>
    <?php endif; ?>

        <div id="emptySearch" style="display:none;text-align:center;padding:2rem 1rem;color:var(--text-muted);">
            <span class="material-icons-outlined" style="font-size:2.5rem;display:block;opacity:0.3;">search_off</span>
            Sin resultados para "<span id="searchTerm"></span>"
        </div>
    </div>

    <div class="card mb-4" style="margin-top:1rem;">
        <div class="card-header">
            <h2 class="card-title">Gastos Operativos (<?= count($expenses) ?>)</h2>
        </div>
        <?php if (count($expenses) > 0): ?>
        <div class="list-group">
            <?php foreach ($expenses as $expense): ?>
            <div class="list-item" id="expense-<?= (int)$expense['id'] ?>" style="gap:0.85rem;">
                <div class="list-item-icon" style="background:#fee2e2;color:#b91c1c;">
                    <span class="material-icons-outlined">receipt_long</span>
                </div>
                <div class="list-item-content">
                    <div class="list-item-title"><?= htmlspecialchars(app_text($expense['category'])) ?></div>
                    <div class="list-item-subtitle">
                        <?= htmlspecialchars(app_text($expense['description'] ?: 'Sin detalle')) ?>
                        &middot; <?= date('d/m/Y', strtotime($expense['expense_date'])) ?>
                        <?php if (!empty($expense['payment_method'])): ?> &middot; <?= htmlspecialchars(app_text($expense['payment_method'])) ?><?php endif; ?>
                        <?php if (!empty($expense['vendor'])): ?> &middot; <?= htmlspecialchars(app_text($expense['vendor'])) ?><?php endif; ?>
                    </div>
                </div>
                <div class="list-item-action" style="display:flex;align-items:center;gap:0.4rem;">
                    <span style="font-weight:700;color:var(--danger);white-space:nowrap;">-S/ <?= number_format((float)$expense['amount'], 2) ?></span>
                    <button type="button" onclick="deleteExpense(<?= (int)$expense['id'] ?>)" style="background:none;color:var(--danger);border:none;cursor:pointer;padding:0.1rem;" title="Eliminar">
                        <span class="material-icons-outlined" style="font-size:0.95rem;">delete</span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:2rem 1rem;color:var(--text-muted);">
            <span class="material-icons-outlined" style="font-size:2.5rem;display:block;opacity:0.3;">receipt_long</span>
            No hay gastos registrados aun.
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.expense-grid {
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:0.85rem;
}
.expense-grid .form-group {
    margin-bottom:0;
}
.expense-grid-full {
    grid-column:1 / -1;
}
.cash-projection-row {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:0.75rem;
    padding:0.95rem 1rem;
    border-top:1px solid var(--border-color);
}
.cash-projection-row:first-child {
    border-top:none;
}
.cash-projection-section {
    border-top:1px solid var(--border-color);
}
.cash-projection-section:first-child {
    border-top:none;
}
.cash-projection-section-title {
    padding:0.85rem 1rem 0.35rem;
    font-size:0.78rem;
    font-weight:800;
    letter-spacing:0.04em;
    text-transform:uppercase;
}
@media (max-width: 640px) {
    .expense-grid {
        grid-template-columns:1fr;
    }
    .cash-projection-grid {
        grid-template-columns:1fr !important;
    }
    .cash-projection-row {
        flex-direction:column;
    }
}
</style>

<?php if ($userRole === 'admin'): ?>
<div class="modal-overlay" id="modalSyncCash">
    <div class="modal-sheet" style="max-width:620px;">
        <button class="modal-close" onclick="closeModal('modalSyncCash')">&times;</button>
        <h3 class="modal-title" style="display:flex;align-items:center;gap:0.55rem;margin-bottom:1rem;">
            <span class="material-icons-outlined" style="color:#0f766e;font-size:1.2rem;">point_of_sale</span>
            Sincronizar caja en efectivo
        </h3>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0.75rem;margin-bottom:1rem;">
            <div style="border:1px solid var(--border-color);border-radius:16px;padding:0.85rem;background:var(--surface);">
                <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.3rem;">Caja según sistema</div>
                <div id="cashSyncCurrent" style="font-size:1.4rem;font-weight:800;color:var(--primary-color);">S/ 0.00</div>
            </div>
            <div style="border:1px solid var(--border-color);border-radius:16px;padding:0.85rem;background:var(--surface);">
                <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.3rem;">Ajustes acumulados</div>
                <div id="cashSyncAdjustments" style="font-size:1.4rem;font-weight:800;">S/ 0.00</div>
            </div>
        </div>

        <form id="formSyncCash" onsubmit="syncCashBox(event)">
            <div class="form-group">
                <label>Efectivo contado real (S/) *</label>
                <input type="number" step="0.01" min="0" id="sync_counted_cash" class="form-control" required oninput="updateCashSyncPreview()">
                <small style="display:block;margin-top:0.35rem;color:var(--text-muted);">Usa esta opción cuando el efectivo físico no coincide con el saldo del sistema.</small>
            </div>
            <div class="form-group">
                <label>Motivo / observación</label>
                <textarea id="sync_notes" class="form-control" rows="3" placeholder="Ej: faltante por vuelto, ingreso no registrado, corrección de cierre"></textarea>
            </div>

            <div style="border:1px dashed var(--border-color);border-radius:14px;padding:0.85rem 0.9rem;background:var(--background);margin-bottom:1rem;">
                <div style="font-size:0.82rem;color:var(--text-muted);">Ajuste a registrar</div>
                <div id="cashSyncPreview" style="font-size:1.2rem;font-weight:800;margin-top:0.2rem;">S/ 0.00</div>
            </div>

            <button type="submit" class="btn-primary" id="btnSyncCash" style="background:#0f766e;">Guardar sincronizacion</button>
        </form>

        <div style="margin-top:1.2rem;">
            <div style="font-weight:700;margin-bottom:0.6rem;">Ultimas sincronizaciones</div>
            <div id="cashSyncHistory" style="display:grid;gap:0.55rem;"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="modalNuevoGasto">
    <div class="modal-sheet" style="max-width:560px;">
        <button class="modal-close" onclick="closeModal('modalNuevoGasto')">&times;</button>
        <h3 class="modal-title" style="display:flex;align-items:center;gap:0.55rem;margin-bottom:1rem;">
            <span class="material-icons-outlined" style="color:#ef4444;font-size:1.2rem;">receipt_long</span>
            Registrar Gasto
        </h3>
        <form id="formNuevoGasto" onsubmit="saveExpense(event)">
            <div class="expense-grid">
                <div class="form-group expense-grid-full">
                    <label>Categoria *</label>
                    <select id="expense_category" class="form-control" required>
                        <option value="alquiler">Alquiler de local</option>
                        <option value="sueldos">Sueldos</option>
                        <option value="luz">Luz</option>
                        <option value="agua">Agua</option>
                        <option value="internet">Internet</option>
                        <option value="reparaciones">Reparaciones</option>
                        <option value="materiales">Materiales</option>
                        <option value="marketing">Marketing</option>
                        <option value="otros">Otros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto (S/) *</label>
                    <input type="number" step="0.01" min="0.01" id="expense_amount" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" id="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Metodo</label>
                    <select id="expense_method" class="form-control">
                        <option value="Efectivo">Efectivo</option>
                        <option value="Yape">Yape</option>
                        <option value="Plin">Plin</option>
                        <option value="Transferencia">Transferencia</option>
                        <option value="Tarjeta">Tarjeta</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Proveedor</label>
                    <input type="text" id="expense_vendor" class="form-control" placeholder="Ej: casero, planilla, Enel">
                </div>
                <div class="form-group expense-grid-full">
                    <label>Descripcion</label>
                    <textarea id="expense_description" class="form-control" rows="3" placeholder="Detalle del gasto"></textarea>
                </div>
                <div class="expense-grid-full">
                    <button type="submit" class="btn-primary" id="btnSaveExpense">Guardar Gasto</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const financialsCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let cashSyncSummary = <?= json_encode($cashSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let cashSyncHistory = [];

function formatCashValue(amount) {
    const value = Number(amount || 0);
    return 'S/ ' + value.toLocaleString('es-PE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function renderCashSyncSummary() {
    const currentEl = document.getElementById('cashSyncCurrent');
    const adjustmentsEl = document.getElementById('cashSyncAdjustments');
    const systemMetricEl = document.getElementById('cashSystemValue');

    if (currentEl) currentEl.textContent = formatCashValue(cashSyncSummary.system_cash || 0);
    if (adjustmentsEl) {
        adjustmentsEl.textContent = formatCashValue(cashSyncSummary.cash_adjustments || 0);
        adjustmentsEl.style.color = Number(cashSyncSummary.cash_adjustments || 0) < 0 ? 'var(--danger)' : 'var(--success)';
    }
    if (systemMetricEl) systemMetricEl.textContent = formatCashValue(cashSyncSummary.system_cash || 0);
}

function renderCashSyncHistory() {
    const wrap = document.getElementById('cashSyncHistory');
    if (!wrap) return;

    if (!cashSyncHistory.length) {
        wrap.innerHTML = '<div style="padding:0.85rem;border:1px solid var(--border-color);border-radius:14px;color:var(--text-muted);background:var(--background);">Aun no hay sincronizaciones registradas.</div>';
        return;
    }

    wrap.innerHTML = cashSyncHistory.map(item => {
        const adjustment = Number(item.adjustment_amount || 0);
        const createdAt = item.created_at ? new Date(String(item.created_at).replace(' ', 'T')) : null;
        const when = createdAt && !Number.isNaN(createdAt.getTime())
            ? createdAt.toLocaleString('es-PE', {dateStyle:'short', timeStyle:'short'})
            : '';

        return `
            <div style="padding:0.8rem 0.9rem;border:1px solid var(--border-color);border-radius:14px;background:var(--surface);">
                <div style="display:flex;justify-content:space-between;gap:0.8rem;align-items:flex-start;">
                    <div>
                        <div style="font-weight:700;">${formatCashValue(item.counted_cash || 0)}</div>
                        <div style="font-size:0.82rem;color:var(--text-muted);">Sistema antes: ${formatCashValue(item.previous_system_cash || 0)}</div>
                        ${item.notes ? `<div style="font-size:0.82rem;color:var(--text-muted);margin-top:0.25rem;">${String(item.notes).replace(/[&<>"]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]))}</div>` : ''}
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:800;color:${adjustment < 0 ? 'var(--danger)' : 'var(--success)'};">${adjustment >= 0 ? '+' : ''}${formatCashValue(adjustment)}</div>
                        <div style="font-size:0.78rem;color:var(--text-muted);">${when}</div>
                        <div style="font-size:0.78rem;color:var(--text-muted);">${item.reconciled_by_name ? item.reconciled_by_name : 'Administrador'}</div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function updateCashSyncPreview() {
    const input = document.getElementById('sync_counted_cash');
    const preview = document.getElementById('cashSyncPreview');
    if (!input || !preview) return;

    const counted = Number(input.value || 0);
    const adjustment = counted - Number(cashSyncSummary.system_cash || 0);
    preview.textContent = `${adjustment >= 0 ? '+' : ''}${formatCashValue(adjustment)}`;
    preview.style.color = adjustment < 0 ? 'var(--danger)' : 'var(--success)';
}

async function openCashSyncModal() {
    try {
        const res = await fetch('api/cash_reconciliation.php', {
            headers: {'X-CSRF-Token': financialsCsrfToken}
        });
        const json = await res.json();
        if (!json.success) {
            showToast(json.error || 'No se pudo cargar la caja', 'error');
            return;
        }

        cashSyncSummary = json.summary || cashSyncSummary;
        cashSyncHistory = Array.isArray(json.history) ? json.history : [];
        renderCashSyncSummary();
        renderCashSyncHistory();

        const countedInput = document.getElementById('sync_counted_cash');
        const notesInput = document.getElementById('sync_notes');
        if (countedInput) countedInput.value = Number(cashSyncSummary.system_cash || 0).toFixed(2);
        if (notesInput) notesInput.value = '';
        updateCashSyncPreview();
        openModal('modalSyncCash');
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function syncCashBox(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSyncCash');
    const countedInput = document.getElementById('sync_counted_cash');
    const notesInput = document.getElementById('sync_notes');
    if (!btn || !countedInput || !notesInput) return;

    const countedCash = Number(countedInput.value || 0);
    if (countedCash < 0) {
        showToast('Ingresa un monto valido', 'error');
        return;
    }

    if (!confirm('Se ajustara la caja del sistema al efectivo contado. Deseas continuar?')) return;

    btn.disabled = true;
    btn.textContent = 'Sincronizando...';

    try {
        const res = await fetch('api/cash_reconciliation.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': financialsCsrfToken},
            body: JSON.stringify({
                counted_cash: countedCash,
                notes: notesInput.value
            })
        });
        const json = await res.json();
        if (json.success) {
            cashSyncSummary = json.summary || cashSyncSummary;
            showToast(json.message || 'Caja sincronizada', 'success');
            closeModal('modalSyncCash');
            renderCashSyncSummary();
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(json.error || 'No se pudo sincronizar la caja', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar sincronizacion';
    }
}

renderCashSyncSummary();

function filterPayments(query) {
    const q = query.trim().toLowerCase();
    const rows = document.querySelectorAll('.payment-row');
    const dayGroups = document.querySelectorAll('.day-group');
    let total = 0;

    rows.forEach(row => {
        const name = row.dataset.patient || '';
        const dni = row.dataset.dni || '';
        const show = !q || name.includes(q) || dni.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) total++;
    });

    dayGroups.forEach(group => {
        const visibleRows = group.querySelectorAll('.payment-row:not([style*="display: none"]):not([style*="display:none"])');
        group.style.display = visibleRows.length > 0 ? '' : 'none';
    });

    document.getElementById('resultCount').textContent = total + ' transacciones' + (q ? ' (filtrado)' : '');
    document.getElementById('searchTerm').textContent = query;
    document.getElementById('emptySearch').style.display = total === 0 && q ? 'block' : 'none';
}

async function deleteTxn(id) {
    if (!confirm('Eliminar esta transaccion?')) return;
    const res = await fetch('api/payments.php', {
        method:'DELETE',
        headers:{'Content-Type':'application/json', 'X-CSRF-Token': financialsCsrfToken},
        body: JSON.stringify({id})
    });
    const json = await res.json();
    if (json.success) {
        showToast('Transaccion eliminada', 'success');
        document.getElementById('txn-' + id)?.remove();
        filterPayments(document.getElementById('searchPaciente').value);
    } else {
        showToast(json.error || 'No se pudo eliminar la transaccion', 'error');
    }
}

async function saveExpense(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveExpense');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const data = {
        category: document.getElementById('expense_category').value,
        amount: document.getElementById('expense_amount').value,
        expense_date: document.getElementById('expense_date').value,
        payment_method: document.getElementById('expense_method').value,
        vendor: document.getElementById('expense_vendor').value,
        description: document.getElementById('expense_description').value
    };

    try {
        const res = await fetch('api/expenses.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': financialsCsrfToken},
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            showToast('Gasto registrado', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showToast(json.error || 'No se pudo registrar el gasto', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar Gasto';
    }
}

async function deleteExpense(id) {
    if (!confirm('Eliminar este gasto?')) return;
    try {
        const res = await fetch('api/expenses.php', {
            method: 'DELETE',
            headers: {'Content-Type':'application/json', 'X-CSRF-Token': financialsCsrfToken},
            body: JSON.stringify({id})
        });
        const json = await res.json();
        if (json.success) {
            showToast('Gasto eliminado', 'success');
            document.getElementById('expense-' + id)?.remove();
            setTimeout(() => location.reload(), 400);
        } else {
            showToast(json.error || 'No se pudo eliminar el gasto', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

window.reloadFinancials = () => window.location.reload();
</script>

<?php require_once 'includes/footer.php'; ?>
