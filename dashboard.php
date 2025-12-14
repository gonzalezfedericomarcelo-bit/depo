<?php
// Archivo: dashboard.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$uid = $_SESSION['user_id'];
$servicio_user = $_SESSION['user_data']['servicio'];

// --- MAPA DE PERMISOS A PRUEBA DE ERRORES ---
// Verificamos tanto la clave nueva (dash_...) como la vieja (d_...)
// Si el usuario tiene CUALQUIERA de las dos, se muestra el widget.
$mapa = [
    // Finanzas
    'fin_gasto' => ['dash_fin_kpi_gasto', 'd_fin_gasto_mes'],
    'fin_pend'  => ['dash_fin_kpi_pend', 'd_fin_pendientes'],
    'fin_aprob' => ['dash_fin_kpi_aprob', 'd_fin_ahorro'],
    'g_top_ins' => ['dash_fin_graph_top_ins', 'd_chart_top_insumos'],
    'g_top_sum' => ['dash_fin_graph_top_sum', 'd_chart_top_sumin'],
    'g_serv'    => ['dash_fin_graph_servicios', 'd_chart_gasto_serv'],

    // Logística
    'log_stock' => ['dash_log_kpi_stock', 'd_sum_stock_crit'],
    'log_ped'   => ['dash_log_kpi_pedidos', 'd_sum_pedidos_nuevos'],
    'g_dem_sum' => ['dash_log_graph_demanda', 'd_chart_sum_demanda'],
    't_ult_sum' => ['dash_log_table_ultimos', 'd_table_sum_lento'],

    // Farmacia
    'far_stock' => ['dash_far_kpi_stock', 'd_ins_stock_crit'],
    'far_ped'   => ['dash_far_kpi_pedidos', 'd_ins_pedidos_nuevos'],
    'far_venc'  => ['dash_far_kpi_venc', 'd_ins_vencimientos'],
    'g_dem_ins' => ['dash_far_graph_demanda', 'd_chart_ins_demanda'],

    // Dirección
    'dir_firm'  => ['dash_dir_kpi_firmas', 'd_dir_aprob_pend'],
    'g_evo'     => ['dash_dir_graph_evolucion', 'd_chart_evolucion'],

    // Servicios
    'serv_mios' => ['dash_serv_kpi_mios', 'd_serv_mis_pedidos'],
    'serv_alert'=> ['dash_serv_alertas', 'd_serv_campanas'],
    'serv_btn'  => ['dash_serv_accesos', 'solicitar_insumos'], // Si puede solicitar, ve los botones

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

// --- DATOS ---
$D = [];
if ($P['fin_gasto']) $D['gasto'] = $pdo->query("SELECT SUM(oci.precio_unitario*oci.cantidad_solicitada) FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE MONTH(oc.fecha_creacion)=MONTH(CURRENT_DATE()) AND oc.estado!='rechazada'")->fetchColumn() ?: 0;
if ($P['fin_pend']) $D['pend'] = $pdo->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado='pendiente_logistica'")->fetchColumn();
if ($P['fin_aprob']) $D['aprob'] = $pdo->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado IN ('aprobada_logistica','completada')")->fetchColumn();

if ($P['log_stock']) {
    $r = $pdo->query("SELECT COUNT(*) as t, SUM(CASE WHEN stock_actual <= stock_minimo THEN 1 ELSE 0 END) as c FROM suministros_generales")->fetch();
    $D['sum_tot'] = $r['t']; $D['sum_crit'] = $r['c'];
}
if ($P['log_ped']) $D['sum_news'] = $pdo->query("SELECT COUNT(*) FROM pedidos_servicio WHERE tipo_insumo='suministros' AND estado NOT IN ('finalizado_proceso','rechazada','borrador')")->fetchColumn();

if ($P['far_stock']) {
    $r = $pdo->query("SELECT COUNT(*) as t, SUM(CASE WHEN stock_actual <= stock_minimo THEN 1 ELSE 0 END) as c FROM insumos_medicos")->fetch();
    $D['ins_tot'] = $r['t']; $D['ins_crit'] = $r['c'];
}
if ($P['far_ped']) $D['ins_news'] = $pdo->query("SELECT COUNT(*) FROM pedidos_servicio WHERE tipo_insumo='insumos_medicos' AND estado NOT IN ('finalizado_proceso','rechazada','borrador')")->fetchColumn();
if ($P['far_venc']) {
    $h = date('Y-m-d'); $l = date('Y-m-d', strtotime('+30 days'));
    $D['venc'] = $pdo->query("SELECT COUNT(*) FROM insumos_medicos WHERE fecha_vencimiento BETWEEN '$h' AND '$l'")->fetchColumn();
}

if ($P['dir_firm']) $D['firmas'] = $pdo->query("SELECT COUNT(*) FROM compras_planificaciones WHERE estado='cerrada_logistica'")->fetchColumn();

if ($P['serv_mios']) $D['mios'] = $pdo->query("SELECT COUNT(*) FROM pedidos_servicio WHERE id_usuario_solicitante=$uid AND estado NOT IN ('finalizado_proceso','rechazada')")->fetchColumn();
if ($P['serv_alert']) $D['alert'] = $pdo->query("SELECT * FROM compras_planificaciones WHERE estado='abierta' AND fecha_fin >= NOW()")->fetchAll();

if ($P['gen_act']) $D['act'] = $pdo->query("SELECT p.id, p.fecha_solicitud, u.nombre_completo, p.servicio_solicitante, p.estado FROM pedidos_servicio p JOIN usuarios u ON p.id_usuario_solicitante=u.id ORDER BY p.fecha_solicitud DESC LIMIT 6")->fetchAll();

// Gráficos Data
if($P['g_top_ins']) $D['g_top_i'] = $pdo->query("SELECT oci.descripcion_producto as k, SUM(oci.precio_unitario*oci.cantidad_solicitada) as v FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='insumos' AND oc.estado!='rechazada' GROUP BY k ORDER BY v DESC LIMIT 5")->fetchAll();
if($P['g_top_sum']) $D['g_top_s'] = $pdo->query("SELECT oci.descripcion_producto as k, SUM(oci.precio_unitario*oci.cantidad_solicitada) as v FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='suministros' AND oc.estado!='rechazada' GROUP BY k ORDER BY v DESC LIMIT 5")->fetchAll();
if($P['g_dem_ins']) $D['g_dem_i'] = $pdo->query("SELECT descripcion_producto as k, SUM(cantidad_solicitada) as v FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='insumos' GROUP BY k ORDER BY v DESC LIMIT 5")->fetchAll();
if($P['g_dem_sum']) $D['g_dem_s'] = $pdo->query("SELECT descripcion_producto as k, SUM(cantidad_solicitada) as v FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='suministros' GROUP BY k ORDER BY v DESC LIMIT 5")->fetchAll();
?>

<style>
.card-widget {border:none;border-radius:10px;background:#fff;box-shadow:0 4px 6px rgba(0,0,0,0.03);height:100%;position:relative;overflow:hidden;transition:transform 0.2s;}
.card-widget:hover {transform:translateY(-3px);box-shadow:0 8px 15px rgba(0,0,0,0.1);}
.w-icon {width:45px;height:45px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;}
.w-label {font-size:0.75rem;font-weight:700;text-transform:uppercase;margin-bottom:0.2rem;}
.w-value {font-size:1.5rem;font-weight:700;color:#333;margin:0;line-height:1.2;}
</style>

<div class="container-fluid px-4 fade-in">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <div><h3 class="fw-bold text-dark mb-0">Dashboard</h3><small class="text-muted"><?php echo $_SESSION['user_name']; ?></small></div>
        <div class="d-flex gap-2">
            <?php if($P['serv_btn']): ?>
                <a href="pedidos_solicitud_interna.php" class="btn btn-primary btn-sm fw-bold">Insumos</a>
                <a href="pedidos_solicitud_interna_suministros.php" class="btn btn-warning btn-sm fw-bold">Suministros</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <?php if($P['fin_gasto']): ?><div class="col-xl-3 col-md-6"><div class="card-widget"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="w-label text-primary">Inversión</div><div class="w-value">$<?php echo number_format($D['gasto'],0,',','.'); ?></div></div><div class="w-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-wallet"></i></div></div></div></div><?php endif; ?>
        <?php if($P['fin_pend']): ?><div class="col-xl-3 col-md-6"><div class="card-widget"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="w-label text-warning">Pendientes</div><div class="w-value"><?php echo $D['pend']; ?></div></div><div class="w-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-clock"></i></div></div></div></div><?php endif; ?>
        <?php if($P['fin_aprob']): ?><div class="col-xl-3 col-md-6"><div class="card-widget"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="w-label text-success">Aprobadas</div><div class="w-value"><?php echo $D['aprob']; ?></div></div><div class="w-icon bg-success bg-opacity-10 text-success"><i class="fas fa-check"></i></div></div></div></div><?php endif; ?>
        
        <?php if($P['log_stock']): ?><div class="col-xl-3 col-md-6"><div class="card-widget"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="w-label text-danger">Stock Sum.</div><div class="w-value"><?php echo $D['sum_crit']; ?>/<?php echo $D['sum_tot']; ?></div></div><div class="w-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-box"></i></div></div></div></div><?php endif; ?>
        <?php if($P['far_stock']): ?><div class="col-xl-3 col-md-6"><div class="card-widget"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="w-label text-danger">Stock Med.</div><div class="w-value"><?php echo $D['ins_crit']; ?>/<?php echo $D['ins_tot']; ?></div></div><div class="w-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-pills"></i></div></div></div></div><?php endif; ?>
        
        <?php if($P['dir_firm']): ?><div class="col-xl-3 col-md-6"><div class="card-widget"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="w-label text-info">Firmas</div><div class="w-value"><?php echo $D['firmas']; ?></div></div><div class="w-icon bg-info bg-opacity-10 text-info"><i class="fas fa-signature"></i></div></div></div></div><?php endif; ?>
        <?php if($P['serv_mios']): ?><div class="col-xl-3 col-md-6"><div class="card-widget"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="w-label text-success">Mis Pedidos</div><div class="w-value"><?php echo $D['mios']; ?></div></div><div class="w-icon bg-success bg-opacity-10 text-success"><i class="fas fa-user-clock"></i></div></div></div></div><?php endif; ?>
    </div>

    <?php if($P['serv_alert'] && !empty($D['alert'])): ?>
    <div class="alert alert-warning border-warning shadow-sm mb-4">
        <strong><i class="fas fa-bullhorn"></i> Campañas Activas:</strong>
        <?php foreach($D['alert'] as $c) echo "<a href='#' class='btn btn-sm btn-light ms-3'>{$c['titulo']}</a>"; ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if($P['g_top_ins']): ?><div class="col-md-6"><div class="card h-100 shadow-sm"><div class="card-header bg-white fw-bold">Top Costos (Insumos)</div><div class="card-body"><canvas id="c1"></canvas></div></div></div><?php endif; ?>
        <?php if($P['g_top_sum']): ?><div class="col-md-6"><div class="card h-100 shadow-sm"><div class="card-header bg-white fw-bold">Top Costos (Suministros)</div><div class="card-body"><canvas id="c2"></canvas></div></div></div><?php endif; ?>
        <?php if($P['g_dem_ins']): ?><div class="col-md-6"><div class="card h-100 shadow-sm"><div class="card-header bg-white fw-bold">Más Pedidos (Insumos)</div><div class="card-body"><canvas id="c3"></canvas></div></div></div><?php endif; ?>
        <?php if($P['g_dem_sum']): ?><div class="col-md-6"><div class="card h-100 shadow-sm"><div class="card-header bg-white fw-bold">Más Pedidos (Suministros)</div><div class="card-body"><canvas id="c4"></canvas></div></div></div><?php endif; ?>
    </div>
    
    <?php if($P['gen_act']): ?>
    <div class="card mt-4 mb-5 shadow-sm">
        <div class="card-header bg-white fw-bold">Actividad Reciente</div>
        <table class="table mb-0">
            <?php foreach($D['act'] as $a): ?>
            <tr><td>#<?php echo $a['id']; ?></td><td><?php echo $a['nombre_completo']; ?></td><td><?php echo $a['estado']; ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Generador Gráficos
function makeChart(id, lbls, data, color) {
    if(!document.getElementById(id)) return;
    new Chart(document.getElementById(id), {
        type: 'bar', data: { labels: lbls, datasets: [{ data: data, backgroundColor: color }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, responsive: true }
    });
}
<?php if($P['g_top_ins']): ?>makeChart('c1', <?php echo json_encode(array_column($D['g_top_i'],'k')); ?>, <?php echo json_encode(array_column($D['g_top_i'],'v')); ?>, '#4e73df');<?php endif; ?>
<?php if($P['g_top_sum']): ?>makeChart('c2', <?php echo json_encode(array_column($D['g_top_s'],'k')); ?>, <?php echo json_encode(array_column($D['g_top_s'],'v')); ?>, '#f6c23e');<?php endif; ?>
<?php if($P['g_dem_ins']): ?>makeChart('c3', <?php echo json_encode(array_column($D['g_dem_i'],'k')); ?>, <?php echo json_encode(array_column($D['g_dem_i'],'v')); ?>, '#e74a3b');<?php endif; ?>
<?php if($P['g_dem_sum']): ?>makeChart('c4', <?php echo json_encode(array_column($D['g_dem_s'],'k')); ?>, <?php echo json_encode(array_column($D['g_dem_s'],'v')); ?>, '#36b9cc');<?php endif; ?>
</script>
<?php include 'includes/footer.php'; ?>