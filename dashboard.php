<?php
// Archivo: dashboard.php
// Propósito: DASHBOARD GRANULAR (Controlado 100% desde Admin Roles)

try {
    require 'db.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
} catch (Exception $e) { die("Error sistema: " . $e->getMessage()); }

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$uid = $_SESSION['user_id'];
$servicio_user = $_SESSION['user_data']['servicio'];

// --- 1. MAPA DE PERMISOS (Aquí definimos qué ve el usuario según la DB) ---
// La clave del array es el nombre corto que usamos en el HTML.
// El valor es el nombre real del permiso en la base de datos.
$mapa_permisos = [
    // Finanzas
    'fin_gasto'      => 'dash_fin_kpi_gasto',
    'fin_pend'       => 'dash_fin_kpi_pend',
    'fin_aprob'      => 'dash_fin_kpi_aprob',
    'g_servicios'    => 'dash_fin_graph_servicios',
    'g_top_ins'      => 'dash_fin_graph_top_ins',
    'g_top_sum'      => 'dash_fin_graph_top_sum',
    
    // Logística
    'log_stock'      => 'dash_log_kpi_stock',
    'log_pedidos'    => 'dash_log_kpi_pedidos',
    'g_demanda_sum'  => 'dash_log_graph_demanda',
    't_ultimos_sum'  => 'dash_log_table_ultimos',
    'btn_camp_sum'   => 'dash_log_btn_campana',
    
    // Farmacia
    'far_stock'      => 'dash_far_kpi_stock',
    'far_pedidos'    => 'dash_far_kpi_pedidos',
    'far_venc'       => 'dash_far_kpi_venc',
    'g_demanda_ins'  => 'dash_far_graph_demanda',
    'btn_camp_ins'   => 'dash_far_btn_campana',
    
    // Dirección
    'dir_firmas'     => 'dash_dir_kpi_firmas',
    'g_evolucion'    => 'dash_dir_graph_evolucion',
    
    // Servicios
    'serv_mios'      => 'dash_serv_kpi_mios',
    'serv_alertas'   => 'dash_serv_alertas',
    'serv_accesos'   => 'dash_serv_accesos',
    
    // General
    'gen_actividad'  => 'dash_gen_actividad'
];

$P = []; // Array final de permisos (true/false)
foreach ($mapa_permisos as $alias => $clave_db) {
    // Nota: Si es Admin, forzamos true para que pueda configurar/ver todo al principio
    // Si quieres que el Admin también tenga que activarse los permisos, quita "|| in_array(...)"
    $P[$alias] = tienePermiso($clave_db) || in_array('Administrador', $_SESSION['user_roles']);
}

// --- 2. CARGA DE DATOS (Solo lo necesario) ---
$D = [];

// A. DATOS FINANCIEROS
if ($P['fin_gasto']) {
    $D['gasto_mes'] = $pdo->query("SELECT SUM(oci.precio_unitario*oci.cantidad_solicitada) FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE MONTH(oc.fecha_creacion)=MONTH(CURRENT_DATE()) AND oc.estado!='rechazada'")->fetchColumn() ?: 0;
}
if ($P['fin_pend']) {
    $D['oc_pend'] = $pdo->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado='pendiente_logistica'")->fetchColumn();
}
if ($P['fin_aprob']) {
    $D['oc_aprob'] = $pdo->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado IN ('aprobada_logistica','completada')")->fetchColumn();
}

// B. DATOS LOGÍSTICA
if ($P['log_stock']) {
    $res = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN stock_actual <= stock_minimo THEN 1 ELSE 0 END) as criticos FROM suministros_generales")->fetch(PDO::FETCH_ASSOC);
    $D['sum_total'] = $res['total']; $D['sum_crit'] = $res['criticos'];
}
if ($P['log_pedidos']) {
    $D['sum_ped_nuevos'] = $pdo->query("SELECT COUNT(*) FROM pedidos_servicio WHERE tipo_insumo='suministros' AND estado NOT IN ('finalizado_proceso','rechazada','borrador')")->fetchColumn();
}
if ($P['t_ultimos_sum']) {
    $D['sum_ultimos'] = $pdo->query("SELECT p.id, p.servicio_solicitante, p.fecha_solicitud, p.estado FROM pedidos_servicio p WHERE tipo_insumo='suministros' ORDER BY p.fecha_solicitud DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
}

// C. DATOS FARMACIA
if ($P['far_stock']) {
    $res = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN stock_actual <= stock_minimo THEN 1 ELSE 0 END) as criticos FROM insumos_medicos")->fetch(PDO::FETCH_ASSOC);
    $D['ins_total'] = $res['total']; $D['ins_crit'] = $res['criticos'];
}
if ($P['far_pedidos']) {
    $D['ins_ped_nuevos'] = $pdo->query("SELECT COUNT(*) FROM pedidos_servicio WHERE tipo_insumo='insumos_medicos' AND estado NOT IN ('finalizado_proceso','rechazada','borrador')")->fetchColumn();
}
if ($P['far_venc']) {
    $hoy = date('Y-m-d'); $lim30 = date('Y-m-d', strtotime('+30 days'));
    $D['ins_venc'] = $pdo->query("SELECT COUNT(*) FROM insumos_medicos WHERE fecha_vencimiento BETWEEN '$hoy' AND '$lim30'")->fetchColumn();
}

// D. DIRECCIÓN
if ($P['dir_firmas']) {
    $D['dir_firmas_pend'] = $pdo->query("SELECT COUNT(*) FROM compras_planificaciones WHERE estado='cerrada_logistica'")->fetchColumn();
}

// E. SERVICIOS
if ($P['serv_mios']) {
    $D['mis_pedidos'] = $pdo->query("SELECT COUNT(*) FROM pedidos_servicio WHERE id_usuario_solicitante=$uid AND estado NOT IN ('finalizado_proceso','rechazada')")->fetchColumn();
}
if ($P['serv_alertas']) {
    $hoyFull = date('Y-m-d H:i:s');
    $D['alertas'] = $pdo->query("SELECT * FROM compras_planificaciones WHERE estado='abierta' AND fecha_fin >= '$hoyFull' ORDER BY fecha_fin ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// F. GRÁFICOS (Datos)
if ($P['g_top_ins']) $D['g_top_ins'] = $pdo->query("SELECT oci.descripcion_producto, SUM(oci.precio_unitario*oci.cantidad_solicitada) as total FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='insumos' AND oc.estado!='rechazada' GROUP BY oci.descripcion_producto ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if ($P['g_top_sum']) $D['g_top_sum'] = $pdo->query("SELECT oci.descripcion_producto, SUM(oci.precio_unitario*oci.cantidad_solicitada) as total FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='suministros' AND oc.estado!='rechazada' GROUP BY oci.descripcion_producto ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if ($P['g_servicios']) $D['g_serv'] = $pdo->query("SELECT oc.servicio_destino, SUM(oci.precio_unitario*oci.cantidad_solicitada) as total FROM ordenes_compra oc JOIN ordenes_compra_items oci ON oc.id=oci.id_oc WHERE oc.estado!='rechazada' GROUP BY oc.servicio_destino ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if ($P['g_demanda_ins']) $D['g_vol_ins'] = $pdo->query("SELECT descripcion_producto, SUM(cantidad_solicitada) as total FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='insumos' GROUP BY descripcion_producto ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if ($P['g_demanda_sum']) $D['g_vol_sum'] = $pdo->query("SELECT descripcion_producto, SUM(cantidad_solicitada) as total FROM ordenes_compra_items oci JOIN ordenes_compra oc ON oci.id_oc=oc.id WHERE oc.tipo_origen='suministros' GROUP BY descripcion_producto ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if ($P['g_evolucion']) $D['g_evo'] = $pdo->query("SELECT DATE_FORMAT(fecha_creacion, '%Y-%m') as mes, SUM(precio_unitario*cantidad_recibida) as total FROM ordenes_compra_items WHERE precio_unitario > 0 GROUP BY mes ORDER BY mes DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

// G. ACTIVIDAD GENERAL
if ($P['gen_actividad']) {
    // Si tiene permiso de gestión ve todo, sino solo lo suyo.
    // Como estamos en modo granular, si tiene el permiso 'dash_gen_actividad', asumimos que quiere ver la actividad general.
    // Si quieres restringir, necesitarías otro permiso "ver_actividad_todos". Por ahora, mostramos todo.
    $D['actividad'] = $pdo->query("SELECT p.id, p.fecha_solicitud, u.nombre_completo, p.servicio_solicitante, p.estado, p.tipo_insumo FROM pedidos_servicio p JOIN usuarios u ON p.id_usuario_solicitante=u.id ORDER BY p.fecha_solicitud DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    /* Estilos Dashboard */
    .card-widget {
        border: none; border-radius: 10px; background: #fff;
        box-shadow: 0 4px 6px rgba(0,0,0,0.03); cursor: pointer; transition: transform 0.2s;
        height: 100%; position: relative; overflow: hidden;
    }
    .card-widget:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
    .w-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
    .w-border { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
    
    /* Colores */
    .c-primary { color: #4e73df; } .bg-primary-soft { background: rgba(78,115,223,0.1); } .b-primary { background: #4e73df; }
    .c-success { color: #1cc88a; } .bg-success-soft { background: rgba(28,200,138,0.1); } .b-success { background: #1cc88a; }
    .c-warning { color: #f6c23e; } .bg-warning-soft { background: rgba(246,194,62,0.1); } .b-warning { background: #f6c23e; }
    .c-danger { color: #e74a3b; } .bg-danger-soft { background: rgba(231,74,59,0.1); } .b-danger { background: #e74a3b; }
    .c-info { color: #36b9cc; } .bg-info-soft { background: rgba(54,185,204,0.1); } .b-info { background: #36b9cc; }
    
    .w-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.2rem; }
    .w-value { font-size: 1.5rem; font-weight: 700; color: #333; margin-bottom: 0; line-height: 1.2; }
    .w-sub { font-size: 0.75rem; color: #888; }
</style>

<div class="container-fluid px-4 fade-in">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-0">Panel de Control</h3>
            <p class="text-muted small mb-0"><?php echo htmlspecialchars($_SESSION['user_name']); ?> | <?php echo htmlspecialchars($servicio_user); ?></p>
        </div>
        <div class="d-flex gap-2">
            <?php if($P['serv_accesos']): ?>
                <a href="pedidos_solicitud_interna.php" class="btn btn-primary btn-sm fw-bold shadow-sm"><i class="fas fa-pills me-1"></i> Pedir Insumos</a>
                <a href="pedidos_solicitud_interna_suministros.php" class="btn btn-warning btn-sm fw-bold shadow-sm text-dark"><i class="fas fa-box me-1"></i> Pedir Suministros</a>
            <?php endif; ?>
            <?php if($P['btn_camp_ins']): ?>
                <a href="insumos_planificacion_panel.php" class="btn btn-dark btn-sm fw-bold shadow-sm"><i class="fas fa-plus me-1"></i> Campaña Médica</a>
            <?php endif; ?>
            <?php if($P['btn_camp_sum']): ?>
                <a href="suministros_planificacion_panel.php" class="btn btn-dark btn-sm fw-bold shadow-sm"><i class="fas fa-plus me-1"></i> Campaña Sum.</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 mb-5">
        
        <?php if($P['fin_gasto']): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card-widget" onclick="window.location='insumos_compras.php'">
                <div class="w-border b-primary"></div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><div class="w-label c-primary">Inversión Mes</div><div class="w-value">$ <?php echo number_format($D['gasto_mes'],0,',','.'); ?></div></div>
                    <div class="w-icon bg-primary-soft c-primary"><i class="fas fa-wallet"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['fin_pend']): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card-widget" onclick="window.location='insumos_compras.php?estado=pendiente'">
                <div class="w-border b-warning"></div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><div class="w-label c-warning">OCs Pendientes</div><div class="w-value"><?php echo $D['oc_pend']; ?></div></div>
                    <div class="w-icon bg-warning-soft c-warning"><i class="fas fa-clock"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['fin_aprob']): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card-widget" onclick="window.location='insumos_compras.php'">
                <div class="w-border b-success"></div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><div class="w-label c-success">OCs Aprobadas</div><div class="w-value"><?php echo $D['oc_aprob']; ?></div></div>
                    <div class="w-icon bg-success-soft c-success"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['log_stock']): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card-widget" onclick="window.location='suministros_stock.php?filtro=critico'">
                <div class="w-border b-danger"></div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><div class="w-label c-danger">Stock Bajo (Sum)</div><div class="w-value"><?php echo $D['sum_crit']; ?> <span class="w-sub">/ <?php echo $D['sum_total']; ?></span></div></div>
                    <div class="w-icon bg-danger-soft c-danger"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['log_pedidos']): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card-widget" onclick="window.location='historial_pedidos.php?tipo=suministros&filtro=pendientes'">
                <div class="w-border b-warning"></div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><div class="w-label c-warning">Pedidos Sum.</div><div class="w-value"><?php echo $D['sum_ped_nuevos']; ?></div><div class="w-sub">Pendientes</div></div>
                    <div class="w-icon bg-warning-soft c-warning"><i class="fas fa-dolly"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['far_stock']): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card-widget" onclick="window.location='insumos_stock.php?filtro=critico'">
                <div class="w-border b-danger"></div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><div class="w-label c-danger">Stock Bajo (Med)</div><div class="w-value"><?php echo $D['ins_crit']; ?> <span class="w-sub">/ <?php echo $D['ins_total']; ?></span></div></div>
                    <div class="w-icon bg-danger-soft c-danger"><i class="fas fa-heart-broken"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['far_pedidos']): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card-widget" onclick="window.location='historial_pedidos.php?tipo=insumos_medicos&filtro=pendientes'">
                <div class="w-border b-primary"></div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><div class="w-label c-primary">Pedidos Med.</div><div class="w-value"><?php echo $D['ins_ped_nuevos']; ?></div><div class="w-sub">Pendientes</div></div>
                    <div class="w-icon bg-primary-soft c-primary"><i class="fas fa-notes-medical"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['far_venc']): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card-widget" onclick="window.location='insumos_stock.php?filtro=vencimiento'">
                <div class="w-border b-warning"></div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><div class="w-label c-warning">Vencimientos</div><div class="w-value"><?php echo $D['ins_venc']; ?></div><div class="w-sub">Próximos 30 días</div></div>
                    <div class="w-icon bg-warning-soft c-warning"><i class="fas fa-calendar-times"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['dir_firmas']): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card-widget" onclick="window.location='insumos_planificacion_panel.php'">
                <div class="w-border b-info"></div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><div class="w-label c-info">Firmas Pend.</div><div class="w-value"><?php echo $D['dir_firmas_pend']; ?></div></div>
                    <div class="w-icon bg-info-soft c-info"><i class="fas fa-signature"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['serv_mios']): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card-widget" onclick="window.location='historial_pedidos.php'">
                <div class="w-border b-success"></div>
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div><div class="w-label c-success">Mis Pedidos</div><div class="w-value"><?php echo $D['mis_pedidos']; ?></div><div class="w-sub">En curso</div></div>
                    <div class="w-icon bg-success-soft c-success"><i class="fas fa-paper-plane"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php if($P['serv_alertas'] && !empty($D['alertas'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning border-warning shadow-sm">
                <h5 class="fw-bold"><i class="fas fa-bullhorn me-2"></i> Campañas de Compra Activas</h5>
                <div class="row g-3 mt-2">
                    <?php foreach($D['alertas'] as $c): ?>
                        <?php $link = ($c['tipo_insumo'] == 'insumos') ? 'campana_carga_insumos.php' : 'campana_carga_suministros.php'; ?>
                        <div class="col-md-4">
                            <a href="<?php echo $link."?campana=".$c['id']; ?>" class="btn btn-light w-100 text-start border d-flex justify-content-between align-items-center shadow-sm">
                                <span><?php echo htmlspecialchars($c['titulo']); ?></span>
                                <span class="badge bg-secondary">Participar</span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
        
        <?php if($P['g_top_ins']): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold c-primary">Top 5 Costos: Insumos Médicos</div>
                <div class="card-body"><div style="height: 250px;"><canvas id="chartTopIns"></canvas></div></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['g_top_sum']): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold c-warning">Top 5 Costos: Suministros</div>
                <div class="card-body"><div style="height: 250px;"><canvas id="chartTopSum"></canvas></div></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['g_demanda_ins']): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold c-danger">Más Solicitados (Insumos)</div>
                <div class="card-body"><div style="height: 250px;"><canvas id="chartDemIns"></canvas></div></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['g_demanda_sum']): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold c-info">Más Solicitados (Suministros)</div>
                <div class="card-body"><div style="height: 250px;"><canvas id="chartDemSum"></canvas></div></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['g_servicios']): ?>
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold text-dark">Gasto por Servicio</div>
                <div class="card-body d-flex align-items-center justify-content-center"><div style="height: 250px; width: 100%;"><canvas id="chartServ"></canvas></div></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['g_evolucion']): ?>
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold text-dark">Evolución de Gasto</div>
                <div class="card-body"><div style="height: 250px;"><canvas id="chartEvo"></canvas></div></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($P['t_ultimos_sum'] && !empty($D['sum_ultimos'])): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold c-warning">Últimos Pedidos (Suministros)</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead><tr><th>ID</th><th>Servicio</th><th>Fecha</th><th>Estado</th></tr></thead>
                        <tbody>
                            <?php foreach($D['sum_ultimos'] as $row): ?>
                            <tr onclick="window.location='pedidos_ver.php?id=<?php echo $row['id']; ?>'" style="cursor:pointer;">
                                <td>#<?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['servicio_solicitante']); ?></td>
                                <td><?php echo date('d/m H:i', strtotime($row['fecha_solicitud'])); ?></td>
                                <td><span class="badge bg-secondary"><?php echo $row['estado']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php if($P['gen_actividad'] && !empty($D['actividad'])): ?>
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white border-0 fw-bold">Actividad Reciente del Sistema</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <tbody>
                    <?php foreach($D['actividad'] as $a): ?>
                    <tr onclick="window.location='pedidos_ver.php?id=<?php echo $a['id']; ?>'" style="cursor:pointer;">
                        <td><span class="fw-bold">#<?php echo $a['id']; ?></span></td>
                        <td><?php echo htmlspecialchars($a['nombre_completo']); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($a['servicio_solicitante']); ?></span></td>
                        <td class="text-end"><span class="badge bg-secondary"><?php echo $a['estado']; ?></span></td>
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
    Chart.defaults.font.family = "'Nunito', sans-serif";
    function irA(url, label) { window.location.href = url + '?q=' + encodeURIComponent(label); }

    // G. Top Costo Insumos -> Buscador Insumos
    <?php if($P['g_top_ins'] && !empty($D['g_top_ins'])): ?>
    new Chart(document.getElementById('chartTopIns'), {
        type: 'bar', data: {
            labels: <?php echo json_encode(array_column($D['g_top_ins'], 'descripcion_producto')); ?>,
            datasets: [{ label: '$', data: <?php echo json_encode(array_column($D['g_top_ins'], 'total')); ?>, backgroundColor: '#4e73df', borderRadius:4 }]
        }, options: { indexAxis: 'y', responsive:true, plugins:{legend:{display:false}}, onClick:(e,el)=>{if(el.length) irA('insumos_compras.php',e.chart.data.labels[el[0].index])} }
    });
    <?php endif; ?>

    // G. Top Costo Suministros -> Buscador Suministros
    <?php if($P['g_top_sum'] && !empty($D['g_top_sum'])): ?>
    new Chart(document.getElementById('chartTopSum'), {
        type: 'bar', data: {
            labels: <?php echo json_encode(array_column($D['g_top_sum'], 'descripcion_producto')); ?>,
            datasets: [{ label: '$', data: <?php echo json_encode(array_column($D['g_top_sum'], 'total')); ?>, backgroundColor: '#f6c23e', borderRadius:4 }]
        }, options: { indexAxis: 'y', responsive:true, plugins:{legend:{display:false}}, onClick:(e,el)=>{if(el.length) irA('suministros_compras.php',e.chart.data.labels[el[0].index])} }
    });
    <?php endif; ?>

    // G. Demanda Insumos -> Stock Insumos
    <?php if($P['g_demanda_ins'] && !empty($D['g_vol_ins'])): ?>
    new Chart(document.getElementById('chartDemIns'), {
        type: 'bar', data: {
            labels: <?php echo json_encode(array_column($D['g_vol_ins'], 'descripcion_producto')); ?>,
            datasets: [{ label: 'Uni', data: <?php echo json_encode(array_column($D['g_vol_ins'], 'total')); ?>, backgroundColor: '#e74a3b', borderRadius:4 }]
        }, options: { responsive:true, plugins:{legend:{display:false}}, onClick:(e,el)=>{if(el.length) irA('insumos_stock.php',e.chart.data.labels[el[0].index])} }
    });
    <?php endif; ?>

    // G. Demanda Suministros -> Stock Suministros
    <?php if($P['g_demanda_sum'] && !empty($D['g_vol_sum'])): ?>
    new Chart(document.getElementById('chartDemSum'), {
        type: 'bar', data: {
            labels: <?php echo json_encode(array_column($D['g_vol_sum'], 'descripcion_producto')); ?>,
            datasets: [{ label: 'Uni', data: <?php echo json_encode(array_column($D['g_vol_sum'], 'total')); ?>, backgroundColor: '#36b9cc', borderRadius:4 }]
        }, options: { responsive:true, plugins:{legend:{display:false}}, onClick:(e,el)=>{if(el.length) irA('suministros_stock.php',e.chart.data.labels[el[0].index])} }
    });
    <?php endif; ?>

    // G. Servicios Torta
    <?php if($P['g_servicios'] && !empty($D['g_serv'])): ?>
    new Chart(document.getElementById('chartServ'), {
        type: 'doughnut', data: {
            labels: <?php echo json_encode(array_column($D['g_serv'], 'servicio_destino')); ?>,
            datasets: [{ data: <?php echo json_encode(array_column($D['g_serv'], 'total')); ?>, backgroundColor: ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b'] }]
        }, options: { responsive:true, plugins:{legend:{position:'right'}} }
    });
    <?php endif; ?>

    // G. Evolución
    <?php if($P['g_evolucion'] && !empty($D['g_evo'])): ?>
    new Chart(document.getElementById('chartEvo'), {
        type: 'line', data: {
            labels: <?php echo json_encode(array_reverse(array_column($D['g_evo'], 'mes'))); ?>,
            datasets: [{ label: '$', data: <?php echo json_encode(array_reverse(array_column($D['g_evo'], 'total'))); ?>, borderColor: '#5a5c69', fill:false }]
        }, options: { responsive:true, plugins:{legend:{display:false}} }
    });
    <?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>