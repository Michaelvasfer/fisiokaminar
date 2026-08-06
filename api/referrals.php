<?php
session_start();
header('Content-Type: application/json');

require_once '../db.php';
require_once '../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    verifyCsrfRequest();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

ensureReferralSchema($pdo);
ensureAuditSchema($pdo);

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function canAccessPatientReferralSummary($requestedPatientId, $sessionUserId, $sessionRole) {
    if ((int)$requestedPatientId <= 0) {
        return false;
    }

    if (in_array($sessionRole, ['admin', 'receptionist', 'therapist'], true)) {
        return true;
    }

    return $sessionRole === 'patient' && (int)$requestedPatientId === (int)$sessionUserId;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['balance_for_patient'])) {
            $patientId = (int)$_GET['balance_for_patient'];
            if (!canAccessPatientReferralSummary($patientId, $userId, $userRole)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                exit;
            }

            $summary = getReferralCreditSummary($pdo, $patientId);
            echo json_encode(['success' => true, 'summary' => $summary]);
            exit;
        }

        if (isset($_GET['patient_id'])) {
            $patientId = (int)$_GET['patient_id'];
            if (!canAccessPatientReferralSummary($patientId, $userId, $userRole)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Sin permiso']);
                exit;
            }

            $summary = getReferralCreditSummary($pdo, $patientId);
            $referralSource = pdoQuery(
                $pdo,
                "SELECT
                    r.referrer_kind,
                    r.referrer_user_id,
                    r.percent_snapshot,
                    r.reward_mode,
                    u.name AS referrer_name
                 FROM referrals r
                 JOIN users u ON u.id = r.referrer_user_id
                 WHERE r.referred_patient_id = ?
                 LIMIT 1",
                [$patientId]
            )->fetch();

            $referredPatients = pdoQuery(
                $pdo,
                "SELECT
                    u.id,
                    u.name,
                    u.patient_code,
                    COUNT(rr.id) AS rewards_count,
                    COALESCE(SUM(rr.generated_amount), 0) AS total_generated,
                    COALESCE(SUM(rr.remaining_amount), 0) AS total_remaining
                 FROM referrals r
                 JOIN users u ON u.id = r.referred_patient_id
                 LEFT JOIN referral_rewards rr ON rr.referral_id = r.id
                 WHERE r.referrer_user_id = ?
                   AND r.referrer_kind = 'patient'
                 GROUP BY u.id, u.name, u.patient_code
                 ORDER BY u.name ASC",
                [$patientId]
            )->fetchAll();

            echo json_encode([
                'success' => true,
                'summary' => $summary,
                'referral_source' => $referralSource ?: null,
                'referred_patients' => $referredPatients
            ]);
            exit;
        }

        if (!isset($_GET['dashboard'])) {
            echo json_encode(['success' => true, 'summary' => null]);
            exit;
        }

        if ($userRole === 'referrer') {
            $totals = pdoQuery(
                $pdo,
                "SELECT
                    COUNT(DISTINCT r.referred_patient_id) AS total_referred,
                    COALESCE(SUM(CASE WHEN rr.status = 'pending' THEN rr.remaining_amount ELSE 0 END), 0) AS pending_total,
                    COALESCE(SUM(CASE WHEN rr.status = 'paid' THEN rr.generated_amount ELSE 0 END), 0) AS paid_total,
                    COALESCE(SUM(rr.generated_amount), 0) AS total_generated
                 FROM referrals r
                 LEFT JOIN referral_rewards rr ON rr.referral_id = r.id
                 WHERE r.referrer_user_id = ?
                   AND r.referrer_kind = 'referrer'",
                [$userId]
            )->fetch();

            $patients = pdoQuery(
                $pdo,
                "SELECT
                    u.id,
                    u.name,
                    u.patient_code,
                    COALESCE(SUM(CASE WHEN rr.status = 'pending' THEN rr.remaining_amount ELSE 0 END), 0) AS pending_commission,
                    COALESCE(SUM(CASE WHEN rr.status = 'paid' THEN rr.generated_amount ELSE 0 END), 0) AS paid_commission,
                    COALESCE(SUM(rr.generated_amount), 0) AS total_commission,
                    MAX(rr.generated_at) AS last_reward_at
                 FROM referrals r
                 JOIN users u ON u.id = r.referred_patient_id
                 LEFT JOIN referral_rewards rr ON rr.referral_id = r.id
                 WHERE r.referrer_user_id = ?
                   AND r.referrer_kind = 'referrer'
                 GROUP BY u.id, u.name, u.patient_code
                 ORDER BY last_reward_at DESC, u.name ASC",
                [$userId]
            )->fetchAll();

            echo json_encode([
                'success' => true,
                'summary' => [
                    'total_referred' => (int)($totals['total_referred'] ?? 0),
                    'pending_total' => round((float)($totals['pending_total'] ?? 0), 2),
                    'paid_total' => round((float)($totals['paid_total'] ?? 0), 2),
                    'total_generated' => round((float)($totals['total_generated'] ?? 0), 2),
                ],
                'patients' => $patients
            ]);
            exit;
        }

        if ($userRole === 'patient') {
            $summary = getReferralCreditSummary($pdo, $userId);
            $rows = pdoQuery(
                $pdo,
                "SELECT
                    u.id,
                    u.name,
                    u.patient_code,
                    COALESCE(SUM(rr.generated_amount), 0) AS total_generated,
                    COALESCE(SUM(rr.remaining_amount), 0) AS total_available
                 FROM referrals r
                 JOIN users u ON u.id = r.referred_patient_id
                 LEFT JOIN referral_rewards rr ON rr.referral_id = r.id
                 WHERE r.referrer_user_id = ?
                   AND r.referrer_kind = 'patient'
                 GROUP BY u.id, u.name, u.patient_code
                 ORDER BY u.name ASC",
                [$userId]
            )->fetchAll();

            echo json_encode([
                'success' => true,
                'summary' => $summary,
                'patients' => $rows
            ]);
            exit;
        }

        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Sin permiso']);
        break;

    case 'POST':
        if ($userRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Solo admin']);
            exit;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = trim($body['action'] ?? '');

        if ($action === 'mark_paid') {
            $rewardId = (int)($body['reward_id'] ?? 0);
            if ($rewardId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Solicitud invalida']);
                exit;
            }

            pdoQuery(
                $pdo,
                "UPDATE referral_rewards
                 SET status = 'paid', settled_at = NOW(), remaining_amount = 0
                 WHERE id = ? AND reward_mode = 'cash'",
                [$rewardId]
            );

            appLog($pdo, 'referral_reward.mark_paid', 'referral_reward', (string)$rewardId);
            echo json_encode(['success' => true, 'message' => 'Comision marcada como pagada']);
            exit;
        }

        if ($action === 'recalculate_patient_rewards') {
            $patientId = (int)($body['patient_id'] ?? 0);
            $retroactive = !empty($body['retroactive']);

            if ($patientId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Paciente invalido']);
                exit;
            }

            $referral = pdoQuery(
                $pdo,
                "SELECT id, referrer_kind, referrer_user_id
                 FROM referrals
                 WHERE referred_patient_id = ?
                   AND status = 'active'
                 LIMIT 1",
                [$patientId]
            )->fetch();

            if (!$referral) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Este paciente no tiene un referido activo']);
                exit;
            }

            $created = syncReferralRewardsForPatient($pdo, $patientId, ['retroactive' => $retroactive]);
            $summary = $referral['referrer_kind'] === 'referrer'
                ? pdoQuery(
                    $pdo,
                    "SELECT
                        COALESCE(SUM(CASE WHEN status = 'pending' THEN remaining_amount ELSE 0 END), 0) AS pending_total,
                        COALESCE(SUM(CASE WHEN status = 'paid' THEN generated_amount ELSE 0 END), 0) AS paid_total,
                        COALESCE(SUM(generated_amount), 0) AS total_generated
                     FROM referral_rewards
                     WHERE beneficiary_user_id = ?
                       AND reward_mode = 'cash'",
                    [(int)$referral['referrer_user_id']]
                )->fetch()
                : getReferralCreditSummary($pdo, (int)$referral['referrer_user_id']);

            appLog($pdo, 'referral_reward.recalculate', 'patient', (string)$patientId, [
                'retroactive' => $retroactive ? 1 : 0,
                'created_rewards' => $created,
                'referrer_user_id' => (int)$referral['referrer_user_id'],
                'referrer_kind' => (string)$referral['referrer_kind'],
            ]);

            echo json_encode([
                'success' => true,
                'message' => $created > 0
                    ? 'Se recalcularon ' . $created . ' comision(es)'
                    : 'No habia comisiones nuevas por generar',
                'created_rewards' => $created,
                'summary' => $summary
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Solicitud invalida']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
        break;
}
