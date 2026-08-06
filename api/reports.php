<?php
session_start();
header('Content-Type: application/json');

require_once '../db.php';
require_once '../includes/csrf.php';

verifyCsrfRequest();
ensureExpenseSchema($pdo);
ensureFixedExpenseSchema($pdo);
ensureCashReconciliationSchema($pdo);
ensureFixedExpenseCycleSchema($pdo);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $month = trim((string)($_GET['month'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }
    $year = (int)substr($month, 0, 4);
    $peTz = new DateTimeZone('America/Lima');
    $todayPe = new DateTimeImmutable('now', $peTz);
    $todayDate = $todayPe->format('Y-m-d');
    $currentMonthPe = $todayPe->format('Y-m');

    $dailyIncome = pdoQuery($pdo, "
        SELECT DATE(transaction_date) AS date, DAY(transaction_date) AS day, SUM(amount) AS total
        FROM transactions
        WHERE amount > 0
          AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
        GROUP BY DATE(transaction_date), DAY(transaction_date)
        ORDER BY DATE(transaction_date) ASC
    ", [$month])->fetchAll();

    $dailyExpenses = pdoQuery($pdo, "
        SELECT DATE(expense_date) AS date, DAY(expense_date) AS day, SUM(amount) AS total
        FROM expenses
        WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?
        GROUP BY DATE(expense_date), DAY(expense_date)
        ORDER BY DATE(expense_date) ASC
    ", [$month])->fetchAll();

    $monthlyIncome = pdoQuery($pdo, "
        SELECT MONTH(transaction_date) AS month_num, SUM(amount) AS total
        FROM transactions
        WHERE amount > 0
          AND YEAR(transaction_date) = ?
        GROUP BY MONTH(transaction_date)
        ORDER BY MONTH(transaction_date) ASC
    ", [$year])->fetchAll();

    $monthlyExpenses = pdoQuery($pdo, "
        SELECT MONTH(expense_date) AS month_num, SUM(amount) AS total
        FROM expenses
        WHERE YEAR(expense_date) = ?
        GROUP BY MONTH(expense_date)
        ORDER BY MONTH(expense_date) ASC
    ", [$year])->fetchAll();

    $expenseCategories = pdoQuery($pdo, "
        SELECT category, SUM(amount) AS total
        FROM expenses
        WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?
        GROUP BY category
        ORDER BY total DESC
    ", [$month])->fetchAll();

    $fixedItems = pdoQuery($pdo, "
        SELECT *
        FROM fixed_expenses
        ORDER BY is_active DESC, amount DESC, name ASC
    ")->fetchAll();

    $cycleRows = pdoQuery($pdo, "
        SELECT fec.*, u.name AS updated_by_name
        FROM fixed_expense_cycles fec
        LEFT JOIN users u ON u.id = fec.updated_by
        WHERE fec.cycle_month = ?
    ", [$month])->fetchAll();
    $cycleMap = [];
    foreach ($cycleRows as $cycleRow) {
        $cycleMap[(int)$cycleRow['fixed_expense_id']] = $cycleRow;
    }

    $currentIncome = array_sum(array_map(fn($row) => (float)$row['total'], $dailyIncome));
    $currentExpense = array_sum(array_map(fn($row) => (float)$row['total'], $dailyExpenses));
    $fixedMonthly = array_sum(array_map(fn($row) => !empty($row['is_active']) ? (float)$row['amount'] : 0, $fixedItems));
    $breakEvenProgress = $fixedMonthly > 0 ? min(100, round(($currentIncome / $fixedMonthly) * 100, 1)) : 100;
    $remainingToBreakEven = max(0, round($fixedMonthly - $currentIncome, 2));
    $isProfitable = $currentIncome > ($fixedMonthly + $currentExpense);
    $operatingResult = round($currentIncome - $currentExpense - $fixedMonthly, 2);
    $cashSummary = getCashLedgerSummary($pdo);
    $currentMonthStart = new DateTimeImmutable($currentMonthPe . '-01', $peTz);
    $nextMonthStart = $currentMonthStart->modify('+1 month');
    $relevantCycleMonths = [
        $currentMonthStart->modify('-1 month')->format('Y-m'),
        $currentMonthStart->format('Y-m'),
        $nextMonthStart->format('Y-m'),
    ];
    $cycleStatusRows = [];
    if ($relevantCycleMonths) {
        $placeholders = implode(',', array_fill(0, count($relevantCycleMonths), '?'));
        $cycleStatusRows = pdoQuery(
            $pdo,
            "SELECT * FROM fixed_expense_cycles WHERE cycle_month IN ($placeholders)",
            $relevantCycleMonths
        )->fetchAll();
    }
    $cycleStatusMap = [];
    foreach ($cycleStatusRows as $row) {
        $cycleStatusMap[(int)$row['fixed_expense_id'] . '|' . (string)$row['cycle_month']] = $row;
    }

    $commitmentItems = [];
    $commitmentTotal = 0.0;
    $commitmentPaid = 0.0;
    $commitmentPending = 0.0;
    $commitmentOverdue = 0.0;

    foreach ($fixedItems as $item) {
        if (empty($item['is_active'])) {
            continue;
        }

        $dueDay = (int)($item['due_day'] ?? 0);
        $monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01', $peTz);
        if (!$monthStart) {
            $monthStart = new DateTimeImmutable($month . '-01', $peTz);
        }
        $lastDay = (int)$monthStart->format('t');
        $normalizedDueDay = $dueDay > 0 ? min($dueDay, $lastDay) : null;
        $plannedDueDate = $normalizedDueDay ? $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), $normalizedDueDay)->format('Y-m-d') : null;

        $cycle = $cycleMap[(int)$item['id']] ?? null;
        $status = $cycle['status'] ?? 'pending';
        $amount = round((float)$item['amount'], 2);
        $isOverdue = false;

        if ($status !== 'paid') {
            if ($month < $currentMonthPe) {
                $isOverdue = true;
            } elseif ($month === $currentMonthPe && $plannedDueDate && $plannedDueDate < $todayDate) {
                $isOverdue = true;
            }
        }

        $commitmentTotal += $amount;
        if ($status === 'paid') {
            $commitmentPaid += $amount;
        } else {
            $commitmentPending += $amount;
            if ($isOverdue) {
                $commitmentOverdue += $amount;
            }
        }

        $commitmentItems[] = [
            'id' => (int)$item['id'],
            'name' => $item['name'],
            'category' => $item['category'],
            'amount' => $amount,
            'due_day' => $normalizedDueDay,
            'planned_due_date' => $plannedDueDate,
            'cycle_month' => $month,
            'status' => $status,
            'is_overdue' => $isOverdue,
            'notes' => $cycle['notes'] ?? $item['notes'],
            'paid_at' => $cycle['paid_at'] ?? null,
            'payment_method' => $cycle['payment_method'] ?? null,
            'expense_id' => isset($cycle['expense_id']) ? (int)$cycle['expense_id'] : null,
            'updated_by_name' => $cycle['updated_by_name'] ?? null,
        ];
    }

    usort($commitmentItems, static function ($a, $b) {
        $aDue = $a['planned_due_date'] ?? '9999-12-31';
        $bDue = $b['planned_due_date'] ?? '9999-12-31';
        if ($aDue === $bDue) {
            return strcmp((string)$a['name'], (string)$b['name']);
        }
        return strcmp($aDue, $bDue);
    });

    $cashCoverage = $commitmentPending > 0 ? min(100, round((((float)$cashSummary['system_cash']) / $commitmentPending) * 100, 1)) : 100;
    $cashGap = round(max(0, $commitmentPending - (float)$cashSummary['system_cash']), 2);
    $cashFreeAfterReserve = round((float)$cashSummary['system_cash'] - $commitmentPending, 2);

    $nextPaymentTarget = null;
    foreach ($fixedItems as $item) {
        if (empty($item['is_active'])) {
            continue;
        }

        $dueDay = (int)($item['due_day'] ?? 0);
        if ($dueDay <= 0) {
            continue;
        }

        $candidateMonths = [$currentMonthStart->modify('-1 month'), $currentMonthStart, $nextMonthStart];
        foreach ($candidateMonths as $candidateMonthStart) {
            $cycleMonth = $candidateMonthStart->format('Y-m');
            $lastDay = (int)$candidateMonthStart->format('t');
            $normalizedDueDay = min($dueDay, $lastDay);
            $dueDate = $candidateMonthStart->setDate((int)$candidateMonthStart->format('Y'), (int)$candidateMonthStart->format('m'), $normalizedDueDay);
            $cycleKey = (int)$item['id'] . '|' . $cycleMonth;
            $cycleRow = $cycleStatusMap[$cycleKey] ?? null;
            $cycleStatus = $cycleRow['status'] ?? 'pending';

            if ($cycleStatus === 'paid') {
                continue;
            }

            $priority = $dueDate->format('Y-m-d') < $todayDate ? 0 : 1;
            $candidate = [
                'fixed_expense_id' => (int)$item['id'],
                'name' => $item['name'],
                'category' => $item['category'],
                'amount' => round((float)$item['amount'], 2),
                'cycle_month' => $cycleMonth,
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => $cycleStatus,
                'priority' => $priority,
            ];

            if ($nextPaymentTarget === null) {
                $nextPaymentTarget = $candidate;
                break;
            }

            $currentKey = [$nextPaymentTarget['priority'], $nextPaymentTarget['due_date'], strtolower((string)$nextPaymentTarget['name'])];
            $candidateKey = [$candidate['priority'], $candidate['due_date'], strtolower((string)$candidate['name'])];
            if ($candidateKey < $currentKey) {
                $nextPaymentTarget = $candidate;
            }
            break;
        }
    }

    $nextPaymentGap = 0.0;
    $nextPaymentCoverage = 100.0;
    if ($nextPaymentTarget) {
        $nextPaymentGap = round(max(0, (float)$nextPaymentTarget['amount'] - (float)$cashSummary['system_cash']), 2);
        $nextPaymentCoverage = (float)$nextPaymentTarget['amount'] > 0
            ? min(100, round((((float)$cashSummary['system_cash']) / (float)$nextPaymentTarget['amount']) * 100, 1))
            : 100;
    }

    echo json_encode([
        'success' => true,
        'current_year' => $year,
        'current_month' => $month,
        'summary' => [
            'income_month' => round($currentIncome, 2),
            'expenses_month' => round($currentExpense, 2),
            'fixed_expenses_month' => round($fixedMonthly, 2),
            'operating_result' => $operatingResult,
            'break_even_progress' => $breakEvenProgress,
            'remaining_to_break_even' => $remainingToBreakEven,
            'is_profitable' => $isProfitable,
        ],
        'daily_income' => $dailyIncome,
        'daily_expenses' => $dailyExpenses,
        'monthly_income' => $monthlyIncome,
        'monthly_expenses' => $monthlyExpenses,
        'expense_categories' => $expenseCategories,
        'fixed_expenses' => $fixedItems,
        'cash_summary' => $cashSummary,
        'cash_commitments' => [
            'items' => $commitmentItems,
            'summary' => [
                'total' => round($commitmentTotal, 2),
                'paid' => round($commitmentPaid, 2),
                'pending' => round($commitmentPending, 2),
                'overdue' => round($commitmentOverdue, 2),
                'cash_available' => round((float)$cashSummary['system_cash'], 2),
                'cash_coverage_percent' => $cashCoverage,
                'cash_gap' => $cashGap,
                'free_after_reserve' => $cashFreeAfterReserve,
                'next_payment' => $nextPaymentTarget,
                'next_payment_gap' => $nextPaymentGap,
                'next_payment_coverage_percent' => $nextPaymentCoverage,
            ],
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
