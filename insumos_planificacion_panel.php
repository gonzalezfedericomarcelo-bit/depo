<?php
// Archivo: insumos_planificacion_panel.php
// Propósito: Panel de Campañas INSUMOS (Con Vencimiento por Hora y Selección Manual)

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// PERMISOS
$es_encargado = tienePermiso('gestionar_planificaciones_medicas');
$es_director  = tienePermiso('aprobar_planificacion_director');
$es_compras   = tienePermiso('procesar_compra_precios');
$es_admin     = in_array('Administrador', $_SESSION['user_roles'] ?? []);

if (!$es_encargado && !$es_director && !$es_compras && !$es_admin) {
    echo "<div class='container mt-5 alert alert-danger'>⛔ Acceso Denegado.</div>"; include 'includes/footer.php'; exit;
}

// BORRAR CAMPAÑA
if (isset($_POST['eliminar_id']) && $es_admin) {
    try {
        $pdo->prepare("DELETE FROM compras_planificaciones WHERE id = ?")->execute([$_POST['eliminar_id']]);
        echo "<script>window.location='insumos_planificacion_panel.php?msg=eliminado';</script>";
    } catch (Exception $e) { echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
}

// CREAR CAMPAÑA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_campana'])) {
    if (!$es_encargado && !$es_admin) { die("Acceso denegado."); }
    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO compras_planificaciones (titulo, frecuencia_cobertura, fecha_inicio, fecha_fin, creado_por, tipo_insumo, estado) 
                VALUES (:tit, :freq, :ini, :fin, :user, 'insumos', 'abierta')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tit' => $_POST['titulo'],
            ':freq'=> $_POST['frecuencia_cobertura'], // Trimestral, Anual, etc.
            ':ini' => $_POST['fecha_inicio'], // Fecha de inicio de vigencia
            ':fin' => $_POST['fecha_cierre'], // FECHA Y HORA EXACTA DE CIERRE DE CARGA
            ':user'=> $_SESSION['user_id']
        ]);
        
        // Notificar
        $rolSolicitante = obtenerIdRolPorPermiso('solicitar_insumos');
        if ($rolSolicitante) {
            $msg = "📢 Campaña INSUMOS (" . $_POST['frecuencia_cobertura'] . "): " . $_POST['titulo'];
            $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")
                ->execute([$rolSolicitante, $msg, 'campana_carga_insumos.php']); 
        }
        $pdo->commit();
        echo "<script>window.location='insumos_planificacion_panel.php?msg=ok';</script>";
    } catch (Exception $e) {
        $pdo->rollBack(); echo "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>";
    }
}

// LISTADO
$busqueda = $_GET['q'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$sql = "SELECT * FROM compras_planificaciones WHERE tipo_insumo = 'insumos'";
if (!empty($busqueda)) { $sql .= " AND titulo LIKE '%$busqueda%'"; }
if (!empty($filtro_estado)) { $sql .= " AND estado = '$filtro_estado'"; }
$sql .= " ORDER BY id DESC";
$planificaciones = $pdo->query($sql)->fetchAll();
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1 class="fw-bold text-primary">Planificación Insumos Médicos</h1>
        <?php if ($es_encargado || $es_admin): ?>
            <button class="btn btn-primary fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalNueva">
                <i class="fas fa-plus me-2"></i> Nueva Campaña
            </button>
        <?php endif; ?>
    </div>
    
    <div class="card mb-4 shadow-sm border-0 bg-light">
        <div class="card-body py-3">
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
            </div>
        </div>
    </div>

    <div class="card mb-4 border-primary shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Campaña</th>
                            <th>Cobertura</th>
                            <th>Cierre de Carga (Deadline)</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($planificaciones) > 0): ?>
                            <?php foreach($planificaciones as $p): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($p['titulo']); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($p['frecuencia_cobertura'] ?? 'N/A'); ?></span></td>
                                <td>
                                    <strong class="text-danger">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($p['fecha_fin'])); ?> hs
                                    </strong>
                                </td>
                                <td>
                                    <?php 
                                        $badge = 'bg-secondary';
                                        if($p['estado'] == 'abierta') $badge = 'bg-success';
                                        if($p['estado'] == 'cerrada_logistica') $badge = 'bg-warning text-dark';
                                        echo "<span class='badge $badge'>".strtoupper($p['estado'])."</span>";
                                    ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($es_compras && $p['estado'] == 'aprobada_director'): ?>
                                        <a href="insumos_gestion_compras.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-success fw-bold">Procesar</a>
                                    <?php elseif ($es_director && $p['estado'] == 'cerrada_logistica'): ?>
                                        <a href="insumos_planificacion_detalle.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-warning text-dark">Revisar</a>
                                    <?php elseif ($es_encargado || $es_admin): ?>
                                        <a href="insumos_planificacion_detalle.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">Gestionar</a>
                                    <?php endif; ?>
                                    
                                    <?php if($es_admin): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar?');">
                                            <input type="hidden" name="eliminar_id" value="<?php echo $p['id']; ?>">
                                            <button class="btn btn-sm btn-danger ms-1"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No hay campañas registradas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($es_encargado || $es_admin): ?>
<div class="modal fade" id="modalNueva" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white"><h5>Lanzar Campaña Insumos</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="crear_campana" value="1">
                
                <div class="mb-3">
                    <label class="fw-bold">Título de la Campaña</label>
                    <input type="text" name="titulo" class="form-control" required placeholder="Ej: Compra Anual 2025">
                </div>

                <div class="mb-3">
                    <label class="fw-bold">1. Tipo de Cobertura (Duración)</label>
                    <select name="frecuencia_cobertura" class="form-select fw-bold text-primary">
                        <option value="Semanal">Semanal</option>
                        <option value="Mensual">Mensual</option>
                        <option value="Trimestral">Trimestral</option>
                        <option value="Semestral">Semestral</option>
                        <option value="Anual">Anual</option>
                    </select>
                    <div class="form-text">Indica para cuánto tiempo deben pedir mercadería los servicios.</div>
                </div>

                <hr>

                <div class="mb-3">
                    <label class="fw-bold text-dark">Fecha de Inicio (Vigencia)</label>
                    <input type="date" name="fecha_inicio" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="mb-3 p-3 bg-danger bg-opacity-10 border border-danger rounded">
                    <label class="fw-bold text-danger">2. Fecha y Hora de Vencimiento (Cierre de Carga)</label>
                    <input type="datetime-local" name="fecha_cierre" class="form-control border-danger fw-bold" required>
                    <div class="form-text text-danger small">A partir de esta hora exacta, nadie podrá cargar más pedidos.</div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary fw-bold">Publicar Campaña</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>