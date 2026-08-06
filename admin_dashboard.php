<?php
// admin_dashboard.php - Inteligencia de Negocio y Métricas
require_once 'db.php';
$pageTitle = 'Dashboard Analítico';
require_once 'includes/header.php';

if ($userRole !== 'admin') {
    header("Location: index.php"); exit;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="animate-fade-in delay-100">
    <div class="page-header" style="background:transparent; border:none; padding:0 1rem 1rem;">
        <h1 class="page-title" style="font-size:1.5rem;">Inteligencia de Negocio</h1>
        <p class="text-sm text-muted">Métricas de rendimiento y retención</p>
    </div>

    <!-- Filtros Rápidos -->
    <div style="padding: 0 1rem; margin-bottom: 1rem;">
        <div class="card" style="margin:0; padding:1rem; display:flex; gap:1rem; flex-wrap:nowrap; overflow-x:auto;">
             <div style="flex-shrink:0;">
                <span class="text-xs text-muted font-bold block mb-1">VENTAS (6M)</span>
                <div class="text-lg font-bold" id="totalVentas6M">Cargando...</div>
             </div>
             <div style="width:1px; background:var(--border-color);"></div>
             <div style="flex-shrink:0;">
                <span class="text-xs text-muted font-bold block mb-1">CRECIMIENTO</span>
                <div class="text-lg font-bold text-success" id="patientGrowthPct">+0%</div>
             </div>
        </div>
    </div>

    <!-- Gráficos Principales -->
    <div class="card mb-4" style="padding:1.25rem;">
        <h3 class="mb-4" style="font-size:0.9rem;">Ingresos vs Crecimiento de Pacientes (6 Meses)</h3>
        <canvas id="mainChart" height="200"></canvas>
    </div>

    <div style="display:grid; grid-template-columns: 1fr; gap:0.75rem;">
        <!-- Alertas de Retención (Punto 3) -->
        <div class="card" style="padding:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:center;" class="mb-4">
                <h3 style="font-size:0.9rem; color:var(--danger);">⚠️ Alerta de Retención</h3>
                <span class="badge badge-danger">Inactivos > 15 días</span>
            </div>
            <div id="retentionList" class="list-group">
                <p class="text-center text-muted py-4">Procesando datos...</p>
            </div>
        </div>

        <!-- Tratamientos Populares -->
        <div class="card" style="padding:1.25rem;">
            <h3 class="mb-4" style="font-size:0.9rem;">Top Tratamientos</h3>
            <div id="treatmentStats">
                <p class="text-center text-muted py-4">Calculando populares...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await fetch('api/stats.php');
        const json = await res.json();
        if (!json.success) {
            document.getElementById('totalVentas6M').textContent = 'S/ 0.00';
            document.getElementById('retentionList').innerHTML = '<p class="text-center text-muted">Error al cargar datos</p>';
            document.getElementById('treatmentStats').innerHTML = '<p class="text-center text-muted">Intenta recargar la página</p>';
            return;
        }

        // Render Chart
        const ctx = document.getElementById('mainChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: json.income.map(i => i.month),
                datasets: [
                    {
                        label: 'Ingresos (S/)',
                        data: json.income.map(i => i.total),
                        borderColor: '#00BCD4',
                        backgroundColor: 'rgba(0, 188, 212, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Nuevos Pacientes',
                        data: json.growth.map(g => g.count),
                        borderColor: '#4CAF50',
                        borderDash: [5, 5],
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
                scales: {
                    y: { beginAtZero: true },
                    y1: { position: 'right', grid: { drawOnChartArea: false }, beginAtZero: true }
                }
            }
        });

        // Totales Rápidos
        const totalV = json.income.reduce((acc, curr) => acc + curr.total, 0);
        document.getElementById('totalVentas6M').textContent = 'S/ ' + totalV.toLocaleString();
        
        // Crecimiento
        const lastMonth = json.growth[json.growth.length - 1].count;
        const prevMonth = json.growth[json.growth.length - 2].count;
        const pct = prevMonth > 0 ? Math.round(((lastMonth - prevMonth) / prevMonth) * 100) : 100;
        document.getElementById('patientGrowthPct').textContent = (pct >= 0 ? '+' : '') + pct + '%';

        // Retention List
        const rList = document.getElementById('retentionList');
        if (json.retention.length > 0) {
            rList.innerHTML = json.retention.map(p => `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.6rem 0; border-bottom:1px solid var(--border-color);">
                    <div>
                        <div style="font-size:0.85rem; font-weight:600;">${p.name}</div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">Última cita: ${new Date(p.last_apt).toLocaleDateString()}</div>
                    </div>
                    <button class="btn-whatsapp-sm" onclick="window.open('https://wa.me/?text=Hola ${p.name}, te extrañamos en KaminarFisio...')">
                        <i class="fab fa-whatsapp"></i>
                    </button>
                </div>
            `).join('');
        } else {
            rList.innerHTML = '<p class="text-center text-muted">¡Excelente! Todos los pacientes están al día.</p>';
        }

        // Treatment Stats
        const tStats = document.getElementById('treatmentStats');
        const totalApts = json.treatments.reduce((acc, curr) => acc + curr.count, 0);
        tStats.innerHTML = json.treatments.map(t => `
            <div class="mb-4">
                <div style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:0.25rem;">
                    <span>${t.type || 'General'}</span>
                    <span class="font-bold">${t.count}</span>
                </div>
                <div style="height:6px; background:var(--border-color); border-radius:99px; overflow:hidden;">
                    <div style="height:100%; width:${(t.count / totalApts * 100)}%; background:var(--primary-color);"></div>
                </div>
            </div>
        `).join('');

    } catch (e) {
        console.error("Dashboard error:", e);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
