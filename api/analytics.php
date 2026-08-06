<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../db.php';

$userRole = $_SESSION['role'] ?? '';
if ($userRole !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // FECHAS GLOBALES
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = '';
    if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = '';

    $appDateJoin = "";
    $appDateCondition = "";
    $histDateCondition = "";
    $planDateCondition = "";
    $txDateCondition = "";
    $appParams = [];

    if ($dateFrom && $dateTo) {
        $appDateCondition = " AND appointment_date >= ? AND appointment_date <= ? ";
        $histDateCondition = " AND DATE(created_at) >= ? AND DATE(created_at) <= ? ";
        $planDateCondition = " AND start_date >= ? AND start_date <= ? ";
        $txDateCondition = " AND DATE(transaction_date) >= ? AND DATE(transaction_date) <= ? ";
        $appParams = [$dateFrom, $dateTo];
    } else if ($dateFrom) {
        $appDateCondition = " AND appointment_date >= ? ";
        $histDateCondition = " AND DATE(created_at) >= ? ";
        $planDateCondition = " AND start_date >= ? ";
        $txDateCondition = " AND DATE(transaction_date) >= ? ";
        $appParams = [$dateFrom];
    } else if ($dateTo) {
        $appDateCondition = " AND appointment_date <= ? ";
        $histDateCondition = " AND DATE(created_at) <= ? ";
        $planDateCondition = " AND start_date <= ? ";
        $txDateCondition = " AND DATE(transaction_date) <= ? ";
        $appParams = [$dateTo];
    }

    // 1. Tasa de Retención
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN appointment_count > 1 THEN 1 ELSE 0 END) as retained,
            SUM(CASE WHEN appointment_count = 1 THEN 1 ELSE 0 END) as dropped
        FROM (
            SELECT patient_id, COUNT(id) as appointment_count
            FROM appointments
            WHERE status != 'cancelled' $appDateCondition
            GROUP BY patient_id
        ) as pc
    ");
    $stmt->execute($appParams);
    $retentionData = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fisioterapeutas (Desempeño)
    // Se toma al terapeuta de las citas y se cuenta cuantos de sus pacientes volvieron vs se quedaron en 1 sesion general
    $stmt = $pdo->query("
        SELECT 
            u.name as therapist_name,
            COUNT(DISTINCT a.patient_id) as total_patients,
            SUM(CASE WHEN p_counts.total > 1 THEN 1 ELSE 0 END) as retained,
            SUM(CASE WHEN p_counts.total = 1 THEN 1 ELSE 0 END) as dropped
        FROM (
            SELECT patient_id, COUNT(id) as total
            FROM appointments
            WHERE status != 'cancelled'
            GROUP BY patient_id
        ) p_counts
        JOIN appointments a ON a.patient_id = p_counts.patient_id
        JOIN users u ON a.therapist_id = u.id AND u.role = 'therapist'
        GROUP BY u.id, u.name
    ");
    $therapistsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Deducimos el porcentaje para cada uno desde PHP para evitar valores duplicados por multi-terapeuta
    $cleanTherapists = [];
    foreach($therapistsData as $t) {
        // En consultas complejas con join a appointments sin DISTINCT en dropped/retained, 
        // sum(case) contara lineas de appointments. 
        // Hagamos un fallback simple si el retained da mas grande que total_patients:
        $ret = (int)$t['retained'];
        $drop = (int)$t['dropped'];
        // normalizamos:
        $cleanTherapists[] = [
            'name' => $t['therapist_name'],
            'total' => (int)$t['total_patients'],
            // evitamos numeros absurdos si el sql join multiplicó
            'retained' => $ret,
            'dropped' => $drop
        ];
    }
    
    // Una query mas segura para fisioterapeuta: Asignar al paciente su PRIMER terapeuta
    $therapistDateCondition = $appDateCondition;
    $therapistParams = $appParams;
    if (!$dateFrom && !$dateTo) {
        // Por defecto si no hay filtros, respeta el "que empiece hoy" que pidió el usuario
        $therapistDateCondition = " AND appointment_date >= CURDATE() ";
        $therapistParams = [];
    }

    // Hay que duplicar los parametros si usamos same bindings in first_app and pc
    $doubleParams = array_merge($therapistParams, $therapistParams);

    $stmt = $pdo->prepare("
        SELECT 
            u.name as therapist_name,
            COUNT(DISTINCT ft.patient_id) as total_patients,
            CAST(SUM(CASE WHEN pc.appointment_count > 1 THEN 1 ELSE 0 END) AS UNSIGNED) as retained,
            CAST(SUM(CASE WHEN pc.appointment_count = 1 THEN 1 ELSE 0 END) AS UNSIGNED) as dropped
        FROM (
            SELECT patient_id, MIN(id) as first_appointment_id
            FROM appointments
            WHERE status != 'cancelled' $therapistDateCondition
            GROUP BY patient_id
        ) first_app
        JOIN appointments ft ON ft.id = first_app.first_appointment_id
        JOIN users u ON ft.therapist_id = u.id AND u.role = 'therapist'
        JOIN (
            SELECT patient_id, COUNT(id) as appointment_count
            FROM appointments
            WHERE status != 'cancelled' $therapistDateCondition
            GROUP BY patient_id
        ) pc ON pc.patient_id = ft.patient_id
        GROUP BY u.id, u.name
    ");
    $stmt->execute($doubleParams);
    $therapistsDataSafe = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Mapa de calor (Días y Horas)
    // 1=Domingo, 2=Lunes, ... 7=Sabado
    $stmt = $pdo->prepare("
        SELECT 
            DAYOFWEEK(appointment_date) as day_of_week,
            HOUR(start_time) as hour_of_day,
            COUNT(*) as occurrences
        FROM appointments
        WHERE status != 'cancelled' $appDateCondition
        GROUP BY day_of_week, hour_of_day
    ");
    $stmt->execute($appParams);
    $heatmapData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Tratamientos/Diagnósticos más comunes (desde Historia Clínica y Plan de Tratamiento)
    $doublePlanParams = array_merge($appParams, $appParams);
    $stmt = $pdo->prepare("
        SELECT diagnosis, COUNT(*) as occurrences 
        FROM (
            SELECT medical_diagnosis as diagnosis 
            FROM clinical_histories 
            WHERE medical_diagnosis IS NOT NULL AND TRIM(medical_diagnosis) != '' $histDateCondition
            
            UNION ALL
            
            SELECT title as diagnosis 
            FROM treatment_plans 
            WHERE title IS NOT NULL AND TRIM(title) != '' $planDateCondition
        ) as combined
        GROUP BY diagnosis
        ORDER BY occurrences DESC 
        LIMIT 5
    ");
    $stmt->execute($doublePlanParams);
    $diagnosesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Adherencia al Tratamiento (Filtros Cruzados)
    $therapistId = isset($_GET['therapist_id']) ? (int)$_GET['therapist_id'] : 0;
    $diagnosisFilter = isset($_GET['diagnosis']) ? trim($_GET['diagnosis']) : '';

    $adhJoin = "";
    $adhWhere = "WHERE p.total_sessions > 0 ";
    // Add date condition to treatment plans
    $adhWhere .= str_replace('start_date', 'p.start_date', $planDateCondition);
    
    $adhParams = $appParams;
    
    if ($therapistId > 0) {
        $adhJoin .= " JOIN patient_sessions ps_filt ON ps_filt.plan_id = p.id 
                      JOIN appointments a_filt ON ps_filt.appointment_id = a_filt.id ";
        $adhWhere .= " AND a_filt.therapist_id = ? ";
        $adhParams[] = $therapistId;
    }
    
    if ($diagnosisFilter !== '') {
        $adhWhere .= " AND p.title = ? ";
        $adhParams[] = $diagnosisFilter;
    }

    $stmtAdh = $pdo->prepare("
        SELECT 
            p.id, p.total_sessions,
            (SELECT COUNT(*) FROM patient_sessions ps_sub WHERE ps_sub.plan_id = p.id AND ps_sub.status = 'completed') as completed
        FROM treatment_plans p
        $adhJoin
        $adhWhere
        GROUP BY p.id
    ");
    $stmtAdh->execute($adhParams);
    $plansAdh = $stmtAdh->fetchAll(PDO::FETCH_ASSOC);

    $adherenceData = [
        'terminado' => 0,
        'mitad' => 0,
        'abandono' => 0
    ];

    foreach($plansAdh as $pl) {
        $tot = (int)$pl['total_sessions'];
        $comp = (int)$pl['completed'];
        if ($tot === 0) continue;
        
        $pct = ($comp / $tot) * 100;
        if ($pct >= 100) $adherenceData['terminado']++;
        else if ($pct >= 50) $adherenceData['mitad']++;
        else $adherenceData['abandono']++;
    }

    // 6. Conversión Paquetes vs Citas Sueltas
    $stmtPack = $pdo->prepare("
        SELECT type, COUNT(*) as count, SUM(ABS(amount)) as total_vol
        FROM transactions
        WHERE type IN ('package_purchase', 'payment_received') $txDateCondition
        GROUP BY type
    ");
    $stmtPack->execute($appParams);
    $packagesData = $stmtPack->fetchAll(PDO::FETCH_ASSOC);

    // 7. Ausentismo / Cancelaciones
    $stmtCanc = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM appointments
        WHERE 1=1 $appDateCondition
        GROUP BY status
    ");
    $stmtCanc->execute($appParams);
    $cancellationsData = $stmtCanc->fetchAll(PDO::FETCH_ASSOC);

    // 8. Dolor EVA (Clínicas completas a nivel global)
    $stmtEva = $pdo->query("
        SELECT AVG(eva_score) as initial_eva 
        FROM clinical_histories 
        WHERE eva_score > 0
    ");
    $evaData = $stmtEva->fetch(PDO::FETCH_ASSOC);

    // 9. Pirámide Demográfica
    // Omitimos fecha porque esto es del universo de usuarios
    $stmtDemo = $pdo->query("
        SELECT 
            SUM(CASE WHEN age < 18 THEN 1 ELSE 0 END) as '0-17',
            SUM(CASE WHEN age BETWEEN 18 AND 35 THEN 1 ELSE 0 END) as '18-35',
            SUM(CASE WHEN age BETWEEN 36 AND 50 THEN 1 ELSE 0 END) as '36-50',
            SUM(CASE WHEN age BETWEEN 51 AND 65 THEN 1 ELSE 0 END) as '51-65',
            SUM(CASE WHEN age > 65 THEN 1 ELSE 0 END) as 'Over 65'
        FROM users
        WHERE role = 'patient' AND age IS NOT NULL
    ");
    $demographicsData = $stmtDemo->fetch(PDO::FETCH_ASSOC);

    // 10. LTV Promedio
    $stmtLtv = $pdo->prepare("
        SELECT SUM(ABS(amount)) / NULLIF(COUNT(DISTINCT patient_id), 0) as ltv
        FROM transactions
        WHERE 1=1 $txDateCondition
    ");
    $stmtLtv->execute($appParams);
    $ltvData = $stmtLtv->fetch(PDO::FETCH_ASSOC);

    // Datos para llenar los filtros en la interfaz
    $filterOptions = [
        'therapists' => $pdo->query("SELECT id, name FROM users WHERE role = 'therapist' AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC),
        'diagnoses' => $pdo->query("SELECT DISTINCT title FROM treatment_plans WHERE title IS NOT NULL AND TRIM(title) != '' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC)
    ];

    echo json_encode([
        'success' => true,
        'retention' => $retentionData,
        'therapists' => $therapistsDataSafe,
        'heatmap' => $heatmapData,
        'diagnoses' => $diagnosesData,
        'adherence' => $adherenceData,
        'packages' => $packagesData,
        'cancellations' => $cancellationsData,
        'eva' => $evaData,
        'demographics' => $demographicsData,
        'ltv' => $ltvData,
        'filterOptions' => $filterOptions
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
