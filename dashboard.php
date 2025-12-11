<?php
// Archivo: dashboard.php
// Propósito: Panel de Control (Permisos Sincronizados)

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php'; // Carga tienePermiso()
include 'includes/navbar.php';

// 1. SEGURIDAD: Verificar Permiso General
if (!tienePermiso('ver_dashboard')) {
    echo "<div class='container-fluid px-4 mt-4'>
            <div class='alert alert-danger shadow-sm'>
                <h4 class='alert-heading'><i class='fas fa-lock'></i> Acceso Restringido</h4>
                <p>Tu rol no tiene habilitado el permiso para ver el Panel de Control.</p>
            </div>
          </div>";
    include 'includes/footer.php'; exit;
}

// 2. CONFIGURACIÓN DE ALCANCE
// Si tiene permiso de ver TODO (Admin/Encargados) o solo lo suyo
$ver_global = tienePermiso('ver_todos_pedidos_insumos') || tienePermiso('ver_todos_pedidos_suministros');
$user_id = $_SESSION['user_id'];
$sql_filtro = $ver_global ? "1=1" : "p.id_usuario_solicitante = $user_id";

// 3. DATOS KPI (Contadores)
$kpis = ['total'=>0, 'pendientes'=>0, 'aprobados'=>0, 'rechazados'=>0];
$sqlKPI = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado LIKE '%pendiente%' OR estado LIKE '%revision%' OR estado = 'en_preparacion' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado LIKE '%aprobado%' OR estado = 'listo_para_retirar' THEN 1 ELSE 0 END) as aprobados,
            SUM(CASE WHEN estado = 'finalizado_proceso' THEN 1 ELSE 0 END) as finalizados
           FROM pedidos_servicio p WHERE $sql_filtro";
$kpis = $pdo->query($sqlKPI)->fetch(PDO::FETCH_ASSOC);

// 4. GRÁFICOS
// Torta (Estados)
$sqlTorta = "SELECT estado, COUNT(*) as cant FROM pedidos_servicio p WHERE $sql_filtro GROUP BY estado";
$datos_torta = $pdo->query($sqlTorta)->fetchAll(PDO::FETCH_KEY_PAIR);

// Barras (Meses)
$sqlBarras = "SELECT DATE_FORMAT(fecha_solicitud, '%Y-%m') as mes, COUNT(*) as cant 
              FROM pedidos_servicio p 
              WHERE $sql_filtro AND fecha_solicitud >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
              GROUP BY mes ORDER BY mes ASC";
$datos_barras = $pdo->query($sqlBarras)->fetchAll(PDO::FETCH_ASSOC);

// 5. RECIENTES
$sqlRecientes = "SELECT p.*, u.nombre_completo 
                 FROM pedidos_servicio p 
                 JOIN usuarios u ON p.id_usuario_solicitante = u.id 
                 WHERE $sql_filtro 
                 ORDER BY p.fecha_solicitud DESC LIMIT 5";
$recientes = $pdo->query($sqlRecientes)->fetchAll();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <div>
            <h1 class="fw-bold text-primary mb-0">Panel de Control</h1>
            <p class="text-muted mb-0">
                Vista: <?php echo $ver_global ? '<span class="badge bg-danger">GLOBAL (ADMIN/ENCARGADO)</span>' : '<span class="badge bg-success">MI SERVICIO</span>'; ?>
            </p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white h-100 shadow-sm border-0 clickable-card" onclick="window.location='historial_pedidos.php'">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="text-white-50 small fw-bold">TOTAL</div><div class="display-6 fw-bold"><?php echo $kpis['total']; ?></div></div>
                    <i class="fas fa-folder-open fa-3x text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-dark h-100 shadow-sm border-0 clickable-card" onclick="window.location='historial_pedidos.php'">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="text-dark-50 small fw-bold">EN PROCESO</div><div class="display-6 fw-bold"><?php echo $kpis['pendientes']; ?></div></div>
                    <i class="fas fa-clock fa-3x text-dark-50 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white h-100 shadow-sm border-0 clickable-card" onclick="window.location='historial_pedidos.php'">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="text-white-50 small fw-bold">LISTOS RETIRO</div><div class="display-6 fw-bold"><?php echo $kpis['aprobados']; ?></div></div>
                    <i class="fas fa-check-circle fa-3x text-white-50"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white h-100 shadow-sm border-0 clickable-card" onclick="window.location='historial_pedidos.php'">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="text-white-50 small fw-bold">FINALIZADOS</div><div class="display-6 fw-bold"><?php echo $kpis['finalizados']; ?></div></div>
                    <i class="fas fa-archive fa-3x text-white-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Evolución de Pedidos</div>
                <div class="card-body">
                    <canvas id="chartBarras" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Estado Actual</div>
                <div class="card-body">
                    <canvas id="chartTorta" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">Últimos Movimientos</span>
            <a href="historial_pedidos.php" class="btn btn-sm btn-light text-primary fw-bold">Ver Historial Completo</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr><th>ID</th><th>Fecha</th><th>Solicitante</th><th>Estado</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                    <?php if(count($recientes)>0): ?>
                        <?php foreach($recientes as $r): ?>
                        <tr style="cursor: pointer;" onclick="window.location='pedidos_ver.php?id=<?php echo $r['id']; ?>'">
                            <td><span class="badge bg-light text-dark border">#<?php echo $r['id']; ?></span></td>
                            <td><?php echo date('d/m H:i', strtotime($r['fecha_solicitud'])); ?></td>
                            <td><?php echo htmlspecialchars($r['nombre_completo']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo $r['estado']; ?></span></td>
                            <td class="text-end"><i class="fas fa-chevron-right text-muted small"></i></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin movimientos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Datos PHP -> JS
const dataTorta = <?php echo json_encode($datos_torta); ?>;
const dataBarras = <?php echo json_encode($datos_barras); ?>;

// 1. TORTA
if (document.getElementById('chartTorta')) {
    new Chart(document.getElementById('chartTorta').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(dataTorta).map(s => s.replace(/_/g, ' ').toUpperCase()),
            datasets: [{ data: Object.values(dataTorta), backgroundColor: ['#ffc107', '#198754', '#0dcaf0', '#dc3545', '#6c757d'], borderWidth: 1 }]
        },
        options: { responsive: true }
    });
}

// 2. BARRAS
if (document.getElementById('chartBarras')) {
    new Chart(document.getElementById('chartBarras').getContext('2d'), {
        type: 'bar',
        data: {
            labels: dataBarras.map(d => d.mes),
            datasets: [{ label: 'Pedidos', data: dataBarras.map(d => d.cant), backgroundColor: '#0d6efd', borderRadius: 4 }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
}
</script>
<?php include 'includes/footer.php'; ?>