<?php
// Archivo: dashboard.php
// Propósito: Panel de Control (Versión ESTABLE con Fix de Gráficos y Links)

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$uid = $_SESSION['user_id'];
$servicio_user = $_SESSION['user_data']['servicio'];

// --- 1. MAPA DE PERMISOS (Sincronizado con tu BD limpia) ---
$mapa = [
    // Finanzas
    'fin_gasto' => ['dash_fin_kpi_gasto'],
    'fin_pend'  => ['dash_fin_kpi_pend'],
    'fin_aprob' => ['dash_fin_kpi_aprob'],
    'g_top_ins' => ['dash_fin_graph_top_ins'],
    'g_top_sum' => ['dash_fin_graph_top_sum'],
    'g_serv'    => ['dash_fin_graph_servicios'],

    // Logística (Suministros)
    'log_stock' => ['dash_log_kpi_stock'],
    'log_ped'   => ['dash_log_kpi_pedidos'],
    'g_dem_sum' => ['dash_log_graph_demanda'],
    't_ult_sum' => ['dash_log_table_ultimos'],

    // Farmacia (Insumos)
    'far_stock' => ['dash_far_kpi_stock'],
    'far_ped'   => ['dash_far_kpi_pedidos'],
    'far_venc'  => ['dash_far_kpi_venc'],
    'g_dem_ins' => ['dash_far_graph_demanda'],

    // Dirección
    'dir_firm'  => ['dash_dir_kpi_firmas'],
    'g_evo'     => ['dash_dir_graph_evolucion'],

    // Servicios
    'serv_mios' => ['dash_serv_kpi_mios'],
    'serv_alert'=> ['dash_serv_alertas'],
    'serv_btn'  => ['dash_serv_accesos'],

    // General
    'gen_act'   => ['dash_gen_actividad']
];

$P = [];
foreach ($mapa as $alias => $claves) {
    $P[$alias] = false;
    foreach ($claves as $k) {
        if (tienePermiso($k)) { 
            $P[$alias] = true; 
            break; 
        }
    }
}

// --- 2. OBTENCIÓN DE DATOS (Queries Optimizadas) ---
$D = [];

// Finanzas
if ($P['fin_gasto']) $D['gasto'] = $pdo->query("SELECT SUM(oci.precio_unitario*oci.cantidad_solicitada) FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE MONTH(oc.fecha_creacion)=MONTH(CURRENT_DATE()) AND oc.estado!='rechazada'")->fetchColumn() ?: 0;
if ($P['fin_pend']) $D['pend'] = $pdo->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado='pendiente_logistica'")->fetchColumn();
if ($P['fin_aprob']) $D['aprob'] = $pdo->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado IN ('aprobada_logistica','completada')")->fetchColumn();

// Logística
if ($P['log_stock']) {
    $r = $pdo->query("SELECT COUNT(*) as t, SUM(CASE WHEN stock_actual <= stock_minimo THEN 1 ELSE 0 END) as c FROM suministros_generales")->fetch();
    $D['sum_tot'] = $r['t']; $D['sum_crit'] = $r['c'];
}
if ($P['log_ped']) $D['sum_news'] = $pdo->query("SELECT COUNT(*) FROM pedidos_servicio WHERE tipo_insumo='suministros' AND estado NOT IN ('finalizado_proceso','rechazada','borrador')")->fetchColumn();

// Farmacia
if ($P['far_stock']) {
    $r = $pdo->query("SELECT COUNT(*) as t, SUM(CASE WHEN stock_actual <= stock_minimo THEN 1 ELSE 0 END) as c FROM insumos_medicos")->fetch();
    $D['ins_tot'] = $r['t']; $D['ins_crit'] = $r['c'];
}
if ($P['far_ped']) $D['ins_news'] = $pdo->query("SELECT COUNT(*) FROM pedidos_servicio WHERE tipo_insumo='insumos_medicos' AND estado NOT IN ('finalizado_proceso','rechazada','borrador')")->fetchColumn();
if ($P['far_venc']) {
    $h = date('Y-m-d'); $l = date('Y-m-d', strtotime('+30 days'));
    $D['venc'] = $pdo->query("SELECT COUNT(*) FROM insumos_medicos WHERE fecha_vencimiento BETWEEN '$h' AND '$l'")->fetchColumn();
}

// Dirección
if ($P['dir_firm']) $D['firmas'] = $pdo->query("SELECT COUNT(*) FROM compras_planificaciones WHERE estado='cerrada_logistica'")->fetchColumn();

// Servicios
if ($P['serv_mios']) $D['mios'] = $pdo->query("SELECT COUNT(*) FROM pedidos_servicio WHERE id_usuario_solicitante=$uid AND estado NOT IN ('finalizado_proceso','rechazada')")->fetchColumn();
if ($P['serv_alert']) $D['alert'] = $pdo->query("SELECT * FROM compras_planificaciones WHERE estado='abierta' AND fecha_fin >= NOW()")->fetchAll();

// Actividad
if ($P['gen_act']) $D['act'] = $pdo->query("SELECT p.id, p.fecha_solicitud, u.nombre_completo, p.servicio_solicitante, p.estado, p.tipo_insumo FROM pedidos_servicio p JOIN usuarios u ON p.id_usuario_solicitante=u.id ORDER BY p.fecha_solicitud DESC LIMIT 6")->fetchAll();

// Gráficos (Data)
if($P['g_top_ins']) $D['g_top_i'] = $pdo->query("SELECT oci.descripcion_producto as k, SUM(oci.precio_unitario*oci.cantidad_solicitada) as v FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='insumos' AND oc.estado!='rechazada' GROUP BY k ORDER BY v DESC LIMIT 5")->fetchAll();
if($P['g_top_sum']) $D['g_top_s'] = $pdo->query("SELECT oci.descripcion_producto as k, SUM(oci.precio_unitario*oci.cantidad_solicitada) as v FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='suministros' AND oc.estado!='rechazada' GROUP BY k ORDER BY v DESC LIMIT 5")->fetchAll();
if($P['g_dem_ins']) $D['g_dem_i'] = $pdo->query("SELECT descripcion_producto as k, SUM(cantidad_solicitada) as v FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='insumos' GROUP BY k ORDER BY v DESC LIMIT 5")->fetchAll();
if($P['g_dem_sum']) $D['g_dem_s'] = $pdo->query("SELECT descripcion_producto as k, SUM(cantidad_solicitada) as v FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='suministros' GROUP BY k ORDER BY v DESC LIMIT 5")->fetchAll();
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #2af598 0%, #009efd 100%);
        --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%);
        --info-gradient: linear-gradient(120deg, #89f7fe 0%, #66a6ff 100%);
        --card-bg: #ffffff;
    }

    /* Tarjetas Interactivas */
    .kpi-card {
        background: var(--card-bg);
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        text-decoration: none !important;
        display: block;
        height: 100%;
        overflow: hidden;
        position: relative;
    }

    .kpi-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    .kpi-body {
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 2;
        position: relative;
    }

    .kpi-icon-box {
        width: 55px;
        height: 55px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: #fff;
        background: #ccc; /* Fallback */
    }

    /* Variantes de Color de Icono */
    .icon-primary { background: var(--primary-gradient); box-shadow: 0 4px 15px rgba(118, 75, 162, 0.4); }
    .icon-success { background: var(--success-gradient); box-shadow: 0 4px 15px rgba(0, 158, 253, 0.4); }
    .icon-warning { background: var(--warning-gradient); box-shadow: 0 4px 15px rgba(253, 160, 133, 0.4); }
    .icon-danger  { background: linear-gradient(135deg, #ff5f6d 0%, #ffc371 100%); box-shadow: 0 4px 15px rgba(255, 95, 109, 0.4); }
    .icon-info    { background: var(--info-gradient); box-shadow: 0 4px 15px rgba(102, 166, 255, 0.4); }

    .kpi-text h6 {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8898aa;
        margin-bottom: 0.25rem;
        font-weight: 700;
    }

    .kpi-text h3 {
        font-size: 1.75rem;
        font-weight: 800;
        color: #32325d;
        margin: 0;
    }

    /* Gráficos */
    .chart-box {
        background: #fff;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        height: 100%;
        border: 1px solid #f0f2f5;
    }
    
    .chart-header {
        font-weight: 700;
        color: #525f7f;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #f0f2f5;
    }

    /* Solución al Bug Infinito: Contenedor estricto para el Canvas */
    .chart-canvas-wrapper {
        position: relative;
        height: 300px; /* ALTO FIJO: Clave para evitar el loop infinito */
        width: 100%;
    }

    /* Tablas */
    .table-activity td { vertical-align: middle; padding: 1rem 0.75rem; }
    .badge-soft { padding: 5px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
    .badge-soft-success { background-color: #e0fcf4; color: #00dbb5; }
    .badge-soft-warning { background-color: #fff9e6; color: #ffbc00; }
    .badge-soft-danger  { background-color: #feecec; color: #f5365c; }
    .badge-soft-primary { background-color: #ececff; color: #5e72e4; }
</style>

<div class="container-fluid px-4 py-4 fade-in">
    
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Dashboard</h2>
            <p class="text-muted mb-0">Visión general del sistema</p>
        </div>
        <div>
            <?php if($P['serv_btn']): ?>
                <a href="pedidos_solicitud_interna.php" class="btn btn-dark shadow-sm fw-bold px-3 py-2 me-2">
                    <i class="fas fa-pills me-2"></i> Pedir Insumos
                </a>
                <a href="pedidos_solicitud_interna_suministros.php" class="btn btn-light border shadow-sm fw-bold px-3 py-2">
                    <i class="fas fa-box me-2"></i> Pedir Suministros
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if($P['serv_alert'] && !empty($D['alert'])): ?>
    <div class="alert alert-dismissible fade show shadow-sm mb-4" role="alert" style="background: linear-gradient(90deg, #fff3cd 0%, #fff 100%); border-left: 5px solid #ffc107;">
        <div class="d-flex align-items-center">
            <div class="me-3 text-warning"><i class="fas fa-bullhorn fa-2x"></i></div>
            <div>
                <h6 class="alert-heading fw-bold mb-1">¡Campañas de Compra Activas!</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach($D['alert'] as $c): ?>
                        <a href="<?php echo ($c['tipo_insumo'] == 'insumos') ? 'campana_carga_insumos.php?campana='.$c['id'] : 'campana_carga_suministros.php?campana='.$c['id']; ?>" 
                           class="btn btn-sm btn-warning fw-bold text-dark border-0">
                            <?php echo htmlspecialchars($c['titulo']); ?> <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
        
        <?php if($P['fin_gasto']): ?>
        <div class="col-xl-3 col-md-6">
            <a href="suministros_compras.php" class="kpi-card">
                <div class="kpi-body">
                    <div class="kpi-text">
                        <h6>Inversión Mes</h6>
                        <h3>$<?php echo number_format($D['gasto'],0,',','.'); ?></h3>
                    </div>
                    <div class="kpi-icon-box icon-primary"><i class="fas fa-wallet"></i></div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if($P['fin_pend']): ?>
        <div class="col-xl-3 col-md-6">
            <a href="suministros_compras.php?q=pendiente" class="kpi-card">
                <div class="kpi-body">
                    <div class="kpi-text">
                        <h6>OCs Pendientes</h6>
                        <h3><?php echo $D['pend']; ?></h3>
                    </div>
                    <div class="kpi-icon-box icon-warning"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if($P['log_stock']): ?>
        <div class="col-xl-3 col-md-6">
            <a href="suministros_stock.php?filtro=critico" class="kpi-card">
                <div class="kpi-body">
                    <div class="kpi-text">
                        <h6>Stock Bajo (Sum)</h6>
                        <h3 class="text-danger"><?php echo $D['sum_crit']; ?></h3>
                    </div>
                    <div class="kpi-icon-box icon-danger"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if($P['log_ped']): ?>
        <div class="col-xl-3 col-md-6">
            <a href="historial_pedidos.php?tipo=suministros&filtro=pendientes" class="kpi-card">
                <div class="kpi-body">
                    <div class="kpi-text">
                        <h6>Pedidos Nuevos (Sum)</h6>
                        <h3><?php echo $D['sum_news']; ?></h3>
                    </div>
                    <div class="kpi-icon-box icon-info"><i class="fas fa-dolly"></i></div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if($P['far_stock']): ?>
        <div class="col-xl-3 col-md-6">
            <a href="insumos_stock.php?filtro=critico" class="kpi-card">
                <div class="kpi-body">
                    <div class="kpi-text">
                        <h6>Críticos (Med)</h6>
                        <h3 class="text-danger"><?php echo $D['ins_crit']; ?></h3>
                    </div>
                    <div class="kpi-icon-box icon-danger"><i class="fas fa-heartbeat"></i></div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if($P['far_ped']): ?>
        <div class="col-xl-3 col-md-6">
            <a href="historial_pedidos.php?tipo=insumos_medicos&filtro=pendientes" class="kpi-card">
                <div class="kpi-body">
                    <div class="kpi-text">
                        <h6>Pedidos Nuevos (Med)</h6>
                        <h3><?php echo $D['ins_news']; ?></h3>
                    </div>
                    <div class="kpi-icon-box icon-success"><i class="fas fa-file-medical"></i></div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if($P['dir_firm']): ?>
        <div class="col-xl-3 col-md-6">
            <a href="insumos_planificacion_panel.php?estado=cerrada_logistica" class="kpi-card">
                <div class="kpi-body">
                    <div class="kpi-text">
                        <h6>Firmas Pendientes</h6>
                        <h3><?php echo $D['firmas']; ?></h3>
                    </div>
                    <div class="kpi-icon-box icon-warning"><i class="fas fa-signature"></i></div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if($P['serv_mios']): ?>
        <div class="col-xl-3 col-md-6">
            <a href="historial_pedidos.php" class="kpi-card">
                <div class="kpi-body">
                    <div class="kpi-text">
                        <h6>Mis Pedidos</h6>
                        <h3><?php echo $D['mios']; ?></h3>
                    </div>
                    <div class="kpi-icon-box icon-primary"><i class="fas fa-user-clock"></i></div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4 mb-5">
        <?php if($P['g_top_ins']): ?>
        <div class="col-md-6">
            <div class="chart-box">
                <div class="chart-header">Top Costos (Insumos)</div>
                <div class="chart-canvas-wrapper">
                    <canvas id="c1"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['g_top_sum']): ?>
        <div class="col-md-6">
            <div class="chart-box">
                <div class="chart-header">Top Costos (Suministros)</div>
                <div class="chart-canvas-wrapper">
                    <canvas id="c2"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['g_dem_ins']): ?>
        <div class="col-md-6">
            <div class="chart-box">
                <div class="chart-header">Más Solicitados (Insumos)</div>
                <div class="chart-canvas-wrapper">
                    <canvas id="c3"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['g_dem_sum']): ?>
        <div class="col-md-6">
            <div class="chart-box">
                <div class="chart-header">Más Solicitados (Suministros)</div>
                <div class="chart-canvas-wrapper">
                    <canvas id="c4"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if($P['gen_act']): ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="m-0 fw-bold text-dark">Actividad Reciente</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-activity table-hover mb-0">
                <thead class="bg-light text-muted small">
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Solicitante</th>
                        <th>Servicio</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($D['act'] as $a): ?>
                    <tr>
                        <td class="fw-bold text-secondary">#<?php echo $a['id']; ?></td>
                        <td><?php echo date('d/m H:i', strtotime($a['fecha_solicitud'])); ?></td>
                        <td><?php echo htmlspecialchars($a['nombre_completo']); ?></td>
                        <td><?php echo htmlspecialchars($a['servicio_solicitante']); ?></td>
                        <td>
                            <?php if($a['tipo_insumo'] == 'insumos_medicos'): ?>
                                <span class="badge badge-soft-primary">Médico</span>
                            <?php else: ?>
                                <span class="badge badge-soft-warning">Suministro</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                                $st = $a['estado'];
                                if(strpos($st, 'aprobado')!==false) echo '<span class="badge badge-soft-success">Aprobado</span>';
                                elseif(strpos($st, 'pendiente')!==false) echo '<span class="badge badge-soft-warning">Pendiente</span>';
                                elseif($st=='entregado') echo '<span class="badge badge-soft-primary">Entregado</span>';
                                else echo '<span class="badge badge-soft-danger">'.strtoupper(str_replace('_',' ',$st)).'</span>';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Configuración Global Chart.js (Estilo)
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#8898aa';
Chart.defaults.borderColor = '#f0f2f5';

function makeChart(id, lbls, data, colorStart, colorEnd) {
    if(!document.getElementById(id)) return;
    
    var ctx = document.getElementById(id).getContext('2d');
    
    // Gradiente Vertical para las barras
    var gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, colorStart);
    gradient.addColorStop(1, colorEnd);

    new Chart(ctx, {
        type: 'bar', 
        data: { 
            labels: lbls, 
            datasets: [{ 
                data: data, 
                backgroundColor: gradient,
                borderRadius: 4,
                barPercentage: 0.6
            }] 
        },
        options: { 
            indexAxis: 'y', // Gráfico Horizontal (Mejor lectura de nombres largos)
            responsive: true,
            maintainAspectRatio: false, // Ahora seguro gracias al wrapper .chart-canvas-wrapper
            plugins: { 
                legend: { display: false },
                tooltip: { backgroundColor: '#172b4d', padding: 12, cornerRadius: 8 }
            },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { borderDash: [5, 5] }, ticks: { autoSkip: false } }
            }
        }
    });
}

<?php if($P['g_top_ins']): ?>makeChart('c1', <?php echo json_encode(array_column($D['g_top_i'],'k')); ?>, <?php echo json_encode(array_column($D['g_top_i'],'v')); ?>, '#5e72e4', '#825ee4');<?php endif; ?>
<?php if($P['g_top_sum']): ?>makeChart('c2', <?php echo json_encode(array_column($D['g_top_s'],'k')); ?>, <?php echo json_encode(array_column($D['g_top_s'],'v')); ?>, '#fb6340', '#fbb140');<?php endif; ?>
<?php if($P['g_dem_ins']): ?>makeChart('c3', <?php echo json_encode(array_column($D['g_dem_i'],'k')); ?>, <?php echo json_encode(array_column($D['g_dem_i'],'v')); ?>, '#f5365c', '#f56036');<?php endif; ?>
<?php if($P['g_dem_sum']): ?>makeChart('c4', <?php echo json_encode(array_column($D['g_dem_s'],'k')); ?>, <?php echo json_encode(array_column($D['g_dem_s'],'v')); ?>, '#11cdef', '#1171ef');<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>