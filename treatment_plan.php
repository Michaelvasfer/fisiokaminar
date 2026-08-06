<?php
// treatment_plan.php - Treatment Plan
require_once 'db.php';

$pageTitle = 'Plan de Tratamiento';
$patientId = isset($_GET['id']) ? (int)$_GET['id'] : 4; // Default to Alex Johnson (id: 4)

// Fetch patient
$stmt = pdoQuery($pdo, "SELECT * FROM users WHERE id = ?", [$patientId]);
$patient = $stmt->fetch();

// Fetch plan
$stmt = pdoQuery($pdo, "SELECT * FROM treatment_plans WHERE patient_id = ? LIMIT 1", [$patientId]);
$plan = $stmt->fetch();

// Fetch objectives
$objectives = [];
// Fetch exercises
$exercises = [];

if ($plan) {
    $stmt = pdoQuery($pdo, "SELECT * FROM objectives WHERE plan_id = ?", [$plan['id']]);
    $objectives = $stmt->fetchAll();
    
    $stmt = pdoQuery($pdo, "SELECT * FROM exercises WHERE plan_id = ?", [$plan['id']]);
    $exercises = $stmt->fetchAll();
}

require_once 'includes/header.php';
?>

<!-- Treatment Plan Content -->
<div class="animate-fade-in delay-100">
    <?php if(!$plan): ?>
        <p class="text-center mt-8">No hay un plan de tratamiento activo para este paciente.</p>
    <?php else: ?>
        <!-- Plan Title & Patient -->
        <div class="mb-6 text-center">
            <h1 class="text-primary mb-2"><?= htmlspecialchars($plan['title']) ?></h1>
            <p class="font-medium text-lg mb-1"><?= htmlspecialchars($patient['name']) ?></p>
            <p class="text-muted text-sm">ID Paciente: <?= htmlspecialchars($patient['patient_code']) ?></p>
        </div>

        <!-- Duration & Next Eval -->
        <div class="metrics-grid mb-6">
            <div class="metric-card">
                <div class="metric-label mb-2">Duración</div>
                <div class="text-lg font-bold"><?= $plan['duration_weeks'] ?> Semanas</div>
                <div class="text-xs text-muted mt-1">Semana <?= $plan['current_week'] ?> en progreso</div>
            </div>
            <div class="metric-card">
                <div class="metric-label mb-2">Próx. Evaluación</div>
                <div class="text-lg font-bold"><?= date('d/m/Y', strtotime($plan['next_eval_date'])) ?></div>
                <?php 
                    $daysInterval = (new DateTime($plan['next_eval_date']))->diff(new DateTime())->days;
                ?>
                <div class="text-xs text-muted mt-1">En <?= $daysInterval ?> días</div>
            </div>
        </div>

        <!-- Objectives & Progress -->
        <h2 class="card-title mb-4">Objetivos y Progreso</h2>
        <div class="card mb-6">
            <?php foreach($objectives as $obj): ?>
                <div class="mb-4">
                    <div class="progress-header">
                        <span><?= htmlspecialchars($obj['metric_name']) ?></span>
                        <span class="text-primary font-bold"><?= $obj['progress_percentage'] ?>%</span>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $obj['progress_percentage'] ?>%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-xs text-muted mt-2">
                        <span>Actual: <?= htmlspecialchars($obj['current_value']) ?></span>
                        <span>Meta: <?= htmlspecialchars($obj['goal_value']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Active Plan Exercises -->
        <h2 class="card-title mb-4">Ejercicios del Plan</h2>
        <div class="list-group mb-8">
            <?php foreach($exercises as $ex): ?>
                <div class="list-item">
                    <div class="list-item-icon" style="background: none; color: var(--text-main);">
                        <span class="material-icons-outlined">play_circle_outline</span>
                    </div>
                    <div class="list-item-content">
                        <div class="list-item-title m-0"><?= htmlspecialchars($ex['name']) ?></div>
                    </div>
                    <div class="list-item-action">
                        <span class="material-icons-outlined">info</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
