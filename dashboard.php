<?php
// Archivo: dashboard.php
// Propósito: SUPER Dashboard Profesional (Blindado contra errores SQL)

try {
    require 'db.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
} catch (Exception $e) { die("Error sistema: " . $e->getMessage()); }

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// 1. SEGURIDAD
if (!tienePermiso('ver_dashboard')) {
    echo "<div class='container mt-5 alert alert-danger shadow-sm border-0'>⛔ Acceso Restringido.</div>";
    include 'includes/footer.php'; exit;
}

$user_id = $_SESSION['user_id'];
$mi_servicio = $_SESSION['user_data']['servicio'] ?? '';

// --- VARIABLES POR DEFECTO (Evitan pantalla blanca si falla DB) ---
$campanas = ['abiertas'=>0, 'en_proceso'=>0, 'finalizadas'=>0];
$stockSum = ['total'=>0, 'criticos'=>0];
$stockIns = ['total'=>0, 'criticos'=>0];
$pedidosSum = ['pendientes'=>0, 'finalizados'=>0];
$pedidosIns = ['pendientes'=>0, 'finalizados'=>0];
$gastosData = ['labels'=>[], 'data'=>[]];
$topSuministros = [];
$topInsumos = [];
$actividad = [];

// --- CONSULTAS SEGURAS (Try-Catch por bloque) ---

// 1. Campañas
try {
    $campanas = $pdo->query("SELECT SUM(CASE WHEN estado='abierta' THEN 1 ELSE 0 END) as abiertas, SUM(CASE WHEN estado='en_compras' OR estado='aprobada_director' THEN 1 ELSE 0 END) as en_proceso, SUM(CASE WHEN estado='orden_generada' THEN 1 ELSE 0 END) as finalizadas FROM compras_planificaciones")->fetch(PDO::FETCH_ASSOC) ?: $campanas;
} catch(Exception $e){}

// 2. Stocks
try {
    $stockSum = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN stock_actual <= stock_minimo THEN 1 ELSE 0 END) as criticos FROM suministros_generales")->fetch(PDO::FETCH_ASSOC) ?: $stockSum;
} catch(Exception $e){}
try {
    $stockIns = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN stock_actual <= stock_minimo THEN 1 ELSE 0 END) as criticos FROM insumos_medicos")->fetch(PDO::FETCH_ASSOC) ?: $stockIns;
} catch(Exception $e){}

// 3. Pedidos (Función helper)
function safeGetPedidos($pdo, $tipo, $uid) {
    try {
        $sql = "SELECT COUNT(*) as total, 
                SUM(CASE WHEN estado NOT IN ('finalizado_proceso', 'rechazada') THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado='finalizado_proceso' THEN 1 ELSE 0 END) as finalizados
                FROM pedidos_servicio WHERE tipo_insumo = '$tipo'";
        if ($uid) $sql .= " AND id_usuario_solicitante = $uid";
        return $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: ['pendientes'=>0, 'finalizados'=>0];
    } catch (Exception $e) { return ['pendientes'=>0, 'finalizados'=>0]; }
}

// 4. Gastos y Tops (Solo si tiene permisos avanzados)
try {
    // Intentamos cargar gastos. Si falla la columna precio_unitario, esto salta al catch y no rompe la pagina
    $rawG = $pdo->query("SELECT DATE_FORMAT(fecha_creacion, '%Y-%m') as mes, SUM(precio_unitario * cantidad_recibida) as total FROM ordenes_compra_items WHERE precio_unitario > 0 GROUP BY mes ORDER BY mes DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    if($rawG) {
        $gastosData = ['labels'=>array_column(array_reverse($rawG),'mes'), 'data'=>array_column(array_reverse($rawG),'total')];
    }
    
    // Top Suministros
    $topSuministros = $pdo->query("SELECT s.nombre, SUM(pi.cantidad_solicitada) as total FROM pedidos_items pi JOIN suministros_generales s ON pi.id_suministro = s.id GROUP BY s.nombre ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Insumos
    $topInsumos = $pdo->query("SELECT s.nombre, SUM(pi.cantidad_solicitada) as total FROM pedidos_items pi JOIN insumos_medicos s ON pi.id_insumo = s.id GROUP BY s.nombre ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e){}

// 5. Actividad Reciente
try {
    $sqlAct = "SELECT p.id, p.fecha_solicitud, u.nombre_completo, p.servicio_solicitante, p.estado, p.tipo_insumo FROM pedidos_servicio p JOIN usuarios u ON p.id_usuario_solicitante = u.id";
    if (!tienePermiso('ver_todos_pedidos_insumos') && !tienePermiso('ver_todos_pedidos_suministros')) {
        $sqlAct .= " WHERE p.id_usuario_solicitante = $user_id";
    }
    $sqlAct .= " ORDER BY p.fecha_solicitud DESC LIMIT 6";
    $actividad = $pdo->query($sqlAct)->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){}


// --- PERMISOS ---
$ve_compras     = tienePermiso('procesar_compra_precios');
$ve_logistica   = tienePermiso('gestionar_planificaciones');
$ve_insumos     = tienePermiso('gestion_stock_insumos');
$ve_director    = tienePermiso('aprobar_planificacion_director');
$ve_servicio    = tienePermiso('solicitar_insumos'); // Usuario base
$es_admin       = in_array('Administrador', $_SESSION['user_roles']);

if ($es_admin) { $ve_compras = $ve_logistica = $ve_insumos = $ve_director = true; }

// Asignar pedidos según rol
$pedidosSum = safeGetPedidos($pdo, 'suministros', ($ve_servicio && !$ve_logistica)?$user_id:null);
$pedidosIns = safeGetPedidos($pdo, 'insumos_medicos', ($ve_servicio && !$ve_insumos)?$user_id:null);
?>

<style>
    .card-dash { border:none; border-radius:10px; background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.03); transition: all 0.3s ease; }
    .card-dash:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); cursor: pointer; }
    
    .bl-primary { border-left: 5px solid #0d6efd; }
    .bl-success { border-left: 5px solid #198754; }
    .bl-warning { border-left: 5px solid #ffc107; }
    .bl-danger  { border-left: 5px solid #dc3545; background-color: #fffbfb; }
    .bl-info    { border-left: 5px solid #0dcaf0; }

    .icon-circle { width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.2rem; }
    .table-activity td { padding: 12px 10px; vertical-align: middle; }
    .avatar-sm { width:32px; height:32px; background:#e9ecef; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:#495057; font-weight:bold; }
</style>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-0">Hola, <?php echo htmlspecialchars($_SESSION['user_name']); ?> 👋</h3>
            <p class="text-muted small mb-0">Panel de Control</p>
        </div>
        
        <div class="d-flex gap-2">
            <?php if($ve_logistica): ?>
                <a href="suministros_planificacion_panel.php" class="btn btn-sm btn-dark shadow-sm"><i class="fas fa-plus me-1"></i> Nueva Campaña</a>
            <?php endif; ?>
            <?php if($ve_servicio && !$ve_logistica && !$ve_insumos): ?>
                <a href="pedidos_solicitud_interna_suministros.php" class="btn btn-sm btn-warning shadow-sm fw-bold text-dark"><i class="fas fa-box me-1"></i> Pedir Suministros</a>
                <a href="pedidos_solicitud_interna.php" class="btn btn-sm btn-primary shadow-sm fw-bold"><i class="fas fa-pills me-1"></i> Pedir Insumos</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if($ve_logistica || $ve_compras || $ve_director): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-dash bl-info h-100" onclick="window.location='suministros_planificacion_panel.php'">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Planificación</span>
                        <h2 class="mb-0 fw-bold"><?php echo (int)$campanas['en_proceso']; ?></h2>
                        <small class="text-info">En Proceso</small>
                    </div>
                    <div class="icon-circle bg-info bg-opacity-10 text-info"><i class="fas fa-tasks"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-dash bl-success h-100" onclick="window.location='suministros_planificacion_panel.php'">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Abiertas</span>
                        <h2 class="mb-0 fw-bold"><?php echo (int)$campanas['abiertas']; ?></h2>
                        <small class="text-success">Recibiendo Pedidos</small>
                    </div>
                    <div class="icon-circle bg-success bg-opacity-10 text-success"><i class="fas fa-door-open"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-dash bl-primary h-100" onclick="window.location='suministros_compras.php'">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Finalizadas</span>
                        <h2 class="mb-0 fw-bold"><?php echo (int)$campanas['finalizadas']; ?></h2>
                        <small class="text-primary">OC Generadas</small>
                    </div>
                    <div class="icon-circle bg-primary bg-opacity-10 text-primary"><i class="fas fa-file-invoice"></i></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        
        <?php if($ve_logistica || $ve_director): ?>
        <?php $critS = $stockSum['criticos'] > 0; ?>
        <div class="col-md-3">
            <div class="card card-dash h-100 <?php echo $critS?'bl-danger':'bl-success'; ?>" 
                 onclick="window.location='suministros_stock.php<?php echo $critS?'?filtro=critico':''; ?>'">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Stock Suministros</span>
                        <h2 class="mb-0 fw-bold <?php echo $critS?'text-danger':''; ?>"><?php echo (int)$stockSum['total']; ?></h2>
                        <?php if($critS): ?><small class="text-danger fw-bold"><i class="fas fa-exclamation-circle"></i> <?php echo $stockSum['criticos']; ?> Críticos</small><?php endif; ?>
                    </div>
                    <div class="icon-circle <?php echo $critS?'bg-danger text-white':'bg-success bg-opacity-10 text-success'; ?>"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-dash bl-warning h-100" onclick="window.location='historial_pedidos.php?tipo=suministros&filtro=pendientes'">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Pedidos Sum.</span>
                        <h2 class="mb-0 fw-bold text-warning"><?php echo (int)$pedidosSum['pendientes']; ?></h2>
                        <small class="text-muted">Pendientes</small>
                    </div>
                    <div class="icon-circle bg-warning bg-opacity-10 text-warning"><i class="fas fa-inbox"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($ve_insumos || $ve_director): ?>
        <?php $critI = $stockIns['criticos'] > 0; ?>
        <div class="col-md-3">
            <div class="card card-dash h-100 <?php echo $critI?'bl-danger':'bl-primary'; ?>" 
                 onclick="window.location='insumos_stock.php<?php echo $critI?'?filtro=critico':''; ?>'">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Stock Insumos</span>
                        <h2 class="mb-0 fw-bold <?php echo $critI?'text-danger':''; ?>"><?php echo (int)$stockIns['total']; ?></h2>
                        <?php if($critI): ?><small class="text-danger fw-bold"><i class="fas fa-heart-broken"></i> <?php echo $stockIns['criticos']; ?> Críticos</small><?php endif; ?>
                    </div>
                    <div class="icon-circle <?php echo $critI?'bg-danger text-white':'bg-primary bg-opacity-10 text-primary'; ?>"><i class="fas fa-pills"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-chart-line me-2 text-success"></i> Evolución de Gastos</h6>
                </div>
                <div class="card-body pt-0">
                    <?php if(!empty($gastosData['data'])): ?>
                        <div style="height: 250px;"><canvas id="chartGastos"></canvas></div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">Sin datos financieros aún.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-history me-2 text-secondary"></i> Actividad</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-activity mb-0">
                            <tbody>
                                <?php if(count($actividad) > 0): ?>
                                    <?php foreach($actividad as $a): ?>
                                    <tr onclick="window.location='pedidos_ver.php?id=<?php echo $a['id']; ?>'" style="cursor:pointer;">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-2"><?php echo strtoupper(substr($a['nombre_completo'],0,1)); ?></div>
                                                <div>
                                                    <span class="d-block small fw-bold"><?php echo htmlspecialchars($a['servicio_solicitante']); ?></span>
                                                    <span class="d-block x-small text-muted" style="font-size:0.7rem;"><?php echo date('d/m H:i', strtotime($a['fecha_solicitud'])); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <?php 
                                                $st = $a['estado'];
                                                $cls = 'bg-secondary';
                                                if(strpos($st,'aprobado')!==false) $cls='bg-info text-dark';
                                                if($st=='listo_para_retirar') $cls='bg-warning text-dark';
                                                if($st=='finalizado_proceso') $cls='bg-success';
                                                echo "<span class='badge $cls'>".str_replace('_',' ',$st)."</span>";
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td class="text-center py-4 text-muted">Sin actividad.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    <?php if(!empty($gastosData['data'])): ?>
    const ctxGastos = document.getElementById('chartGastos');
    if (ctxGastos) {
        new Chart(ctxGastos, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($gastosData['labels']); ?>,
                datasets: [{
                    label: 'Gastos ($)',
                    data: <?php echo json_encode($gastosData['data']); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { borderDash: [5, 5] } }, x: { grid: { display: false } } } }
        });
    }
    <?php endif; ?>
</script>
<?php include 'includes/footer.php'; ?>