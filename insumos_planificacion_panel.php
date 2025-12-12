<?php
// Archivo: insumos_planificacion_panel.php
// Propósito: Panel de Campañas para INSUMOS MÉDICOS
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// PERMISOS
$es_encargado = tienePermiso('gestionar_planificaciones_medicas'); // Nuevo permiso
$es_director  = tienePermiso('aprobar_planificacion_director');
$es_compras   = tienePermiso('procesar_compra_precios');

if (!$es_encargado && !$es_director && !$es_compras) {
    echo "<div class='container mt-5 alert alert-danger'>⛔ Acceso Denegado.</div>"; include 'includes/footer.php'; exit;
}

// CREAR CAMPAÑA (Solo Encargado Insumos)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_campana'])) {
    if (!$es_encargado) { die("Acceso denegado."); }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO compras_planificaciones (titulo, fecha_inicio, fecha_fin, creado_por, tipo_insumo) VALUES (:tit, :ini, :fin, :user, 'insumos')");
        $stmt->execute([':tit'=>$_POST['titulo'], ':ini'=>$_POST['fecha_inicio'], ':fin'=>$_POST['fecha_fin'], ':user'=>$_SESSION['user_id']]);
        
        // Notificar a Servicios (Usuarios que pueden pedir insumos)
        $rolSolicitante = obtenerIdRolPorPermiso('solicitar_insumos');
        if ($rolSolicitante) {
            $msg = "📢 Nueva Campaña Médica: " . $_POST['titulo'];
            $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")
                ->execute([$rolSolicitante, $msg, 'pedidos_solicitud_interna.php']);
        }
        $pdo->commit();
        echo "<script>window.location='insumos_planificacion_panel.php?msg=ok';</script>";
    } catch (Exception $e) {
        $pdo->rollBack(); echo "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>";
    }
}

// FILTROS
$busqueda = $_GET['q'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

$sql = "SELECT * FROM compras_planificaciones WHERE tipo_insumo = 'insumos'"; // SOLO INSUMOS
$params = [];

if (!empty($busqueda)) { $sql .= " AND titulo LIKE :q"; $params[':q'] = "%$busqueda%"; }
if (!empty($filtro_estado)) { $sql .= " AND estado = :est"; $params[':est'] = $filtro_estado; }
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$planificaciones = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Planificación de Insumos Médicos</h1>
    
    <div class="card mb-4 shadow-sm border-0 bg-light">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <form method="GET" class="row g-2">
                        <div class="col-md-5"><input type="text" name="q" class="form-control" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>"></div>
                        <div class="col-md-4">
                            <select name="estado" class="form-select" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <option value="abierta" <?php echo ($filtro_estado=='abierta')?'selected':''; ?>>Abierta</option>
                                <option value="cerrada_logistica" <?php echo ($filtro_estado=='cerrada_logistica')?'selected':''; ?>>En Revisión</option>
                                <option value="aprobada_director" <?php echo ($filtro_estado=='aprobada_director')?'selected':''; ?>>En Compras</option>
                            </select>
                        </div>
                        <div class="col-md-3"><button type="submit" class="btn btn-secondary w-100">Filtrar</button></div>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($es_encargado): ?>
                    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalNueva">+ Nueva Campaña</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 border-primary shadow-sm">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Título</th><th>Vigencia</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                    <?php if(count($planificaciones) > 0): ?>
                        <?php foreach($planificaciones as $p): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($p['titulo']); ?></td>
                            <td><?php echo date('d/m', strtotime($p['fecha_inicio']))." al ".date('d/m', strtotime($p['fecha_fin'])); ?></td>
                            <td><span class="badge bg-secondary"><?php echo strtoupper(str_replace('_',' ',$p['estado'])); ?></span></td>
                            <td class="text-end">
                                <?php if ($es_compras && $p['estado'] == 'aprobada_director'): ?>
                                    <a href="insumos_gestion_compras.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-success fw-bold">Procesar Compra</a>
                                <?php elseif ($es_director && $p['estado'] == 'cerrada_logistica'): ?>
                                    <a href="insumos_planificacion_detalle.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-warning text-dark">Revisar</a>
                                <?php elseif ($es_encargado): ?>
                                    <a href="insumos_planificacion_detalle.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">Gestionar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No hay campañas de insumos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($es_encargado): ?>
<div class="modal fade" id="modalNueva" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white"><h5>Nueva Campaña Médica</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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