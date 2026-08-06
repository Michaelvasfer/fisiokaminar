<?php
require_once 'db.php';
ensureReferralSchema($pdo);

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'referrer') {
    header("Location: index.php");
    exit;
}

$pageTitle = 'Mis Referidos';
require_once 'includes/header.php';

$summary = [
    'total_referred' => 0,
    'pending_total' => 0.0,
    'paid_total' => 0.0,
    'total_generated' => 0.0,
];

$referredPatients = [];

try {
    $referredIds = pdoQuery(
        $pdo,
        "SELECT referred_patient_id
         FROM referrals
         WHERE referrer_user_id = ?
           AND referrer_kind = 'referrer'
           AND status = 'active'",
        [$userId]
    )->fetchAll();

    foreach ($referredIds as $refRow) {
        syncReferralRewardsForPatient($pdo, (int)($refRow['referred_patient_id'] ?? 0));
    }
} catch (Exception $e) {
}

try {
    $summary = pdoQuery(
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
    )->fetch() ?: $summary;
} catch (Exception $e) {
}

try {
    $referredPatients = pdoQuery(
        $pdo,
        "SELECT
            u.id,
            u.name,
            u.patient_code,
            r.created_at AS referred_at,
            COALESCE(SUM(CASE WHEN rr.status = 'pending' THEN rr.remaining_amount ELSE 0 END), 0) AS pending_commission,
            COALESCE(SUM(CASE WHEN rr.status = 'paid' THEN rr.generated_amount ELSE 0 END), 0) AS paid_commission,
            COALESCE(SUM(rr.generated_amount), 0) AS total_commission,
            MAX(rr.generated_at) AS last_reward_at
         FROM referrals r
         JOIN users u ON u.id = r.referred_patient_id
         LEFT JOIN referral_rewards rr ON rr.referral_id = r.id
         WHERE r.referrer_user_id = ?
           AND r.referrer_kind = 'referrer'
         GROUP BY u.id, u.name, u.patient_code, r.created_at
         ORDER BY last_reward_at DESC, r.created_at DESC",
        [$userId]
    )->fetchAll();
} catch (Exception $e) {
}
?>

<div class="animate-fade-in delay-100">
    <div style="padding:1rem;background:linear-gradient(135deg, #ecfeff 0%, #f8fafc 100%);border-radius:var(--radius-lg);margin:0.75rem;box-shadow:var(--shadow-sm);border:1px solid #bae6fd;">
        <div style="font-size:0.78rem;font-weight:800;letter-spacing:0.04em;text-transform:uppercase;color:var(--primary-dark);margin-bottom:0.35rem;">Portal de Referidos</div>
        <h1 style="margin:0 0 0.35rem 0;"><?= htmlspecialchars($userName) ?></h1>
        <p class="text-sm text-muted" style="margin:0;">Aquí puedes revisar tus referidos, tus comisiones generadas y el saldo pendiente por cobrar.</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin:0.75rem;">
        <div class="metric-card">
            <div class="metric-label">Referidos Activos</div>
            <div class="metric-value"><?= (int)($summary['total_referred'] ?? 0) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Pendiente</div>
            <div class="metric-value">S/ <?= number_format((float)($summary['pending_total'] ?? 0), 2) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Cobrado</div>
            <div class="metric-value">S/ <?= number_format((float)($summary['paid_total'] ?? 0), 2) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Histórico</div>
            <div class="metric-value">S/ <?= number_format((float)($summary['total_generated'] ?? 0), 2) ?></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-icons-outlined" style="vertical-align:middle;color:var(--primary-color);font-size:1.1rem;">groups</span>
                Mis Pacientes Referidos
            </h2>
        </div>

        <div class="list-group">
            <?php if (count($referredPatients) > 0): ?>
                <?php foreach ($referredPatients as $row): ?>
                <div class="card-list-row">
                    <div class="card-list-content">
                        <div class="card-list-title"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="card-list-subtitle">
                            <?= htmlspecialchars($row['patient_code'] ?: 'Paciente registrado') ?>
                            · Referido el <?= !empty($row['referred_at']) ? date('d/m/Y', strtotime($row['referred_at'])) : '-' ?>
                        </div>
                        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.4rem;font-size:0.74rem;">
                            <span style="background:#eff6ff;color:#1d4ed8;padding:0.2rem 0.5rem;border-radius:999px;font-weight:700;">
                                Pendiente S/ <?= number_format((float)$row['pending_commission'], 2) ?>
                            </span>
                            <span style="background:#f0fdf4;color:#166534;padding:0.2rem 0.5rem;border-radius:999px;font-weight:700;">
                                Cobrado S/ <?= number_format((float)$row['paid_commission'], 2) ?>
                            </span>
                            <span style="background:#f8fafc;color:#334155;padding:0.2rem 0.5rem;border-radius:999px;font-weight:700;">
                                Total S/ <?= number_format((float)$row['total_commission'], 2) ?>
                            </span>
                        </div>
                    </div>
                    <div style="font-size:0.72rem;color:var(--text-muted);text-align:right;min-width:72px;">
                        <?= !empty($row['last_reward_at']) ? 'Último movimiento<br>' . date('d/m/Y', strtotime($row['last_reward_at'])) : 'Aún sin comisión' ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding:1rem;color:var(--text-muted);font-size:0.85rem;text-align:center;">
                    Todavía no tienes pacientes referidos registrados.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
