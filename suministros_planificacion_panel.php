<?php
// Archivo: suministros_planificacion_panel.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// PERMISOS
$es_logistica = tienePermiso('gestionar_planificaciones');
$es_director  = tienePermiso('aprobar_planificacion_director');
$es_compras   = tienePermiso('procesar_compra_precios');

if (!$es_logistica && !$es_director && !$es_compras) {
    echo "<div class='container mt-5'><div class='alert alert-danger shadow-sm'>⛔ Acceso Denegado.</div></div>";
    include 'includes/footer.php'; exit;
}

// CREAR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_campana'])) {
    if (!$es_logistica) { die("Acceso denegado."); }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO compras_planificaciones (titulo, fecha_inicio, fecha_fin, creado_por) VALUES (:tit, :ini, :fin, :user)");
        $stmt->execute([':tit'=>$_POST['titulo'], ':ini'=>$_POST['fecha_inicio'], ':fin'=>$_POST['fecha_fin'], ':user'=>$_SESSION['user_id']]);
        
        // Notificar
        $rolSolicitante = obtenerIdRolPorPermiso('solicitar_suministros');
        if ($rolSolicitante) {
            $msg = "📢 Nueva Campaña: " . $_POST['titulo'];
            $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")
                ->execute([$rolSolicitante, $msg, 'pedidos_solicitud_interna_suministros.php']);
        }
        $pdo->commit();
        echo "<script>window.location='suministros_planificacion_panel.php?msg=ok';</script>";
    } catch (Exception $e) {
        $pdo->rollBack(); echo "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>";
    }
}

// --- FILTROS Y BÚSQUEDA ---
$busqueda = $_GET['q'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

$sql = "SELECT * FROM compras_planificaciones WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND titulo LIKE :q";
    $params[':q'] = "%$busqueda%";
}
if (!empty($filtro_estado)) {
    $sql .= " AND estado = :est";
    $params[':est'] = $filtro_estado;
}
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$planificaciones = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Planificación de Adquisiciones</h1>
    
    <div class="card mb-4 shadow-sm border-0 bg-light">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <form method="GET" class="row g-2">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Buscar por título..." value="<?php echo htmlspecialchars($busqueda); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="estado" class="form-select" onchange="this.form.submit()">
                                <option value="">Todos los Estados</option>
                                <option value="abierta" <?php echo ($filtro_estado=='abierta')?'selected':''; ?>>Abierta</option>
                                <option value="cerrada_logistica" <?php echo ($filtro_estado=='cerrada_logistica')?'selected':''; ?>>En Revisión</option>
                                <option value="aprobada_director" <?php echo ($filtro_estado=='aprobada_director')?'selected':''; ?>>En Compras</option>
                                <option value="orden_generada" <?php echo ($filtro_estado=='orden_generada')?'selected':''; ?>>Finalizada</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-secondary w-100">Filtrar</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($es_logistica): ?>
                    <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNueva">
                        <i class="fas fa-plus me-2"></i> Nueva Convocatoria
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 border-primary shadow-sm">
        <div class="card-header bg-primary text-white">Resultados</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Título</th><th>Vigencia</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if(count($planificaciones) > 0): ?>
                            <?php foreach($planificaciones as $p): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($p['titulo']); ?></td>
                                <td>
                                    <small class="text-muted d-block">Inicio: <?php echo date('d/m/Y', strtotime($p['fecha_inicio'])); ?></small>
                                    <span class="text-danger fw-bold">Cierre: <?php echo date('d/m/Y', strtotime($p['fecha_fin'])); ?></span>
                                </td>
                                <td>
                                    <?php 
                                        $badges = ['abierta'=>'bg-success', 'cerrada_logistica'=>'bg-warning text-dark', 'aprobada_director'=>'bg-info text-dark', 'en_compras'=>'bg-primary', 'orden_generada'=>'bg-dark'];
                                        $lbl = ($p['estado'] == 'aprobada_director') ? 'Lista para Compras' : strtoupper(str_replace('_',' ',$p['estado']));
                                        echo "<span class='badge ".$badges[$p['estado']]."'>$lbl</span>";
                                    ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($es_compras && $p['estado'] == 'aprobada_director'): ?>
                                        <a href="suministros_gestion_compras.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-success fw-bold">Procesar Compra</a>
                                    <?php elseif ($es_director && $p['estado'] == 'cerrada_logistica'): ?>
                                        <a href="suministros_planificacion_detalle.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-warning text-dark">Revisar</a>
                                    <?php elseif ($es_logistica): ?>
                                        <a href="suministros_planificacion_detalle.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">Gestionar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No se encontraron campañas con ese criterio.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($es_logistica): ?>
<div class="modal fade" id="modalNueva" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Nueva Convocatoria</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="crear_campana" value="1">
                <div class="mb-3"><label>Título</label><input type="text" name="titulo" class="form-control" required></div>
                <div class="row">
                    <div class="col"><label>Inicio</label><input type="date" name="fecha_inicio" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="col"><label>Cierre</label><input type="date" name="fecha_fin" class="form-control" required></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Lanzar</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>