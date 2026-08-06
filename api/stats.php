<?php
// api/stats.php - Data for charts
session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once '../includes/csrf.php';
verifyCsrfRequest();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Solo administradores pueden ver estadísticas']);
    exit;
}

// Table compatibility
try {
    $pdo->exec("ALTER TABLE users ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
} catch(Exception $e) {}

try {
    // Get income by month for the last 6 months
    $incomeData = [];
    $patientGrowth = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = date('Y-m-01', strtotime("-$i months"));
        $monthName = date('M', strtotime($date));
        $year = date('Y', strtotime($date));
        $month = date('m', strtotime($date));

        // Income
        $stmt = pdoQuery($pdo, "
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM transactions 
            WHERE amount > 0 
            AND MONTH(transaction_date) = ? 
            AND YEAR(transaction_date) = ?
        ", [$month, $year]);
        $row = $stmt->fetch();
        $incomeData[] = ['month' => $monthName, 'total' => (float)$row['total']];

        // Patient Growth
        $stmtG = pdoQuery($pdo, "
            SELECT COUNT(*) as count 
            FROM users 
            WHERE role = 'patient' 
            AND MONTH(created_at) = ? 
            AND YEAR(created_at) = ?
        ", [$month, $year]);
        $rowG = $stmtG->fetch();
        $patientGrowth[] = ['month' => $monthName, 'count' => (int)$rowG['count']];
    }

    // Top Treatments
    $stmtT = pdoQuery($pdo, "
        SELECT type, COUNT(*) as count 
        FROM appointments 
        GROUP BY type 
        ORDER BY count DESC 
        LIMIT 5
    ");
    $topTreatments = $stmtT->fetchAll();

    // Retention Alert
    $stmtR = pdoQuery($pdo, "
        SELECT u.id, u.name, MAX(a.appointment_date) as last_apt
        FROM users u
        INNER JOIN appointments a ON u.id = a.patient_id
        WHERE u.role = 'patient'
        GROUP BY u.id
        HAVING last_apt < DATE_SUB(CURDATE(), INTERVAL 15 DAY)
        ORDER BY last_apt DESC
        LIMIT 10
    ");
    $retentionAlerts = $stmtR->fetchAll();

    echo json_encode([
        'success' => true, 
        'income' => $incomeData,
        'growth' => $patientGrowth,
        'treatments' => $topTreatments,
        'retention' => $retentionAlerts
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
