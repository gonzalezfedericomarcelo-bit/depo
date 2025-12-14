<?php
// Archivo: insumos_planificacion_detalle.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$id_plan = $_GET['id'];
$plan = $pdo->query("SELECT * FROM compras_planificaciones WHERE id=$id_plan AND tipo_insumo='insumos'")->fetch();
if(!$plan) die("<div class='container mt-5'>Plan no encontrado.</div>");

// AUTO-CIERRE
$ahora = date('Y-m-d H:i:s');
if ($plan['estado'] == 'abierta' && $ahora > $plan['fecha_fin']) {
    $pdo->prepare("UPDATE compras_planificaciones SET estado='cerrada_logistica' WHERE id=?")->execute([$id_plan]);
    echo "<script>window.location.reload();</script>"; exit;
}

// PROCESAR ACCIONES (Cerrar, Aprobar, Rechazar, Reabrir)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Cierre manual (Encargado)
    if (isset($_POST['cerrar_encargado'])) {
        $pdo->prepare("UPDATE compras_planificaciones SET estado='cerrada_logistica' WHERE id=?")->execute([$id_plan]);
        // Notificar Director...
        echo "<script>window.location.href='insumos_planificacion_detalle.php?id=$id_plan';</script>"; exit;
    }

    // 2. Aprobación (Director)
    if (isset($_POST['aprobar_director']) && tienePermiso('aprobar_planificacion_director')) {
        $pdo->prepare("UPDATE compras_planificaciones SET estado='aprobada_director', motivo_rechazo=NULL WHERE id=?")->execute([$id_plan]);
        // Notificar Compras...
        echo "<script>window.location.href='insumos_planificacion_detalle.php?id=$id_plan';</script>"; exit;
    }

    // 3. RECHAZO (Director) - NUEVO
    if (isset($_POST['accion']) && $_POST['accion'] == 'rechazar_director' && tienePermiso('aprobar_planificacion_director')) {
        $motivo = $_POST['motivo_rechazo'];
        $pdo->prepare("UPDATE compras_planificaciones SET estado='rechazada', motivo_rechazo=? WHERE id=?")->execute([$motivo, $id_plan]);
        // Notificar Encargado...
        echo "<script>window.location.href='insumos_planificacion_detalle.php?id=$id_plan';</script>"; exit;
    }

    // 4. REABRIR/CORREGIR (Encargado ve el rechazo y lo abre para editar)
    if (isset($_POST['reabrir_campana'])) {
        // Extender fecha si es necesario o solo cambiar estado
        $nueva_fecha = date('Y-m-d H:i:s', strtotime('+2 days')); // Damos 2 dias mas por defecto
        $pdo->prepare("UPDATE compras_planificaciones SET estado='abierta', fecha_fin=? WHERE id=?")->execute([$nueva_fecha, $id_plan]);
        echo "<script>window.location.href='insumos_planificacion_detalle.php?id=$id_plan';</script>"; exit;
    }
}

// CONSULTAS (Desglose y Total)
$sqlDes = "SELECT ps.servicio_solicitante, COALESCE(im.nombre, pi.detalle_personalizado) as item, pi.cantidad_solicitada 
           FROM pedidos_items pi JOIN pedidos_servicio ps ON pi.id_pedido = ps.id LEFT JOIN insumos_medicos im ON pi.id_insumo = im.id 
           WHERE ps.id_planificacion = :id ORDER BY ps.servicio_solicitante, item";
$stmt = $pdo->prepare($sqlDes); $stmt->execute([':id'=>$id_plan]);
$desglose = $stmt->fetchAll(PDO::FETCH_GROUP);

$sqlCon = "SELECT COALESCE(im.nombre, pi.detalle_personalizado) as nombre_item, SUM(pi.cantidad_solicitada) as total 
           FROM pedidos_items pi JOIN pedidos_servicio ps ON pi.id_pedido = ps.id LEFT JOIN insumos_medicos im ON pi.id_insumo = im.id 
           WHERE ps.id_planificacion = :id GROUP BY nombre_item";
$stmt = $pdo->prepare($sqlCon); $stmt->execute([':id'=>$id_plan]);
$total = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <span class="badge bg-primary mb-1"><?php echo strtoupper($plan['frecuencia_cobertura']); ?></span>
            <h2 class="fw-bold m-0">Planificación: <?php echo htmlspecialchars($plan['titulo']); ?></h2>
        </div>
        <div>
            <a href="generar_pdf_planificacion.php?id=<?php echo $id_plan; ?>" target="_blank" class="btn btn-dark shadow-sm">
                <i class="fas fa-file-pdf me-2"></i> Reporte Global PDF
            </a>
            <a href="insumos_planificacion_panel.php" class="btn btn-secondary ms-2">Volver</a>
        </div>
    </div>

    <?php if($plan['estado'] == 'rechazada'): ?>
    <div class="alert alert-danger shadow border-danger" role="alert">
        <h4 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Campaña Rechazada por Dirección</h4>
        <p><strong>Motivo / Bitácora:</strong> <?php echo nl2br(htmlspecialchars($plan['motivo_rechazo'])); ?></p>
        <hr>
        <?php if(tienePermiso('gestionar_planificaciones_medicas')): ?>
            <p class="mb-0">Debe corregir los errores. Puede reabrir la campaña para permitir ediciones.</p>
            <form method="POST" class="mt-2">
                <button type="submit" name="reabrir_campana" class="btn btn-danger fw-bold">
                    <i class="fas fa-unlock me-2"></i> Reabrir Campaña (Extender 48hs)
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card mb-4 p-3 bg-light border shadow-sm d-flex flex-row justify-content-between align-items-center">
        <div>
            <strong>Estado:</strong> <span class="badge bg-secondary fs-6"><?php echo strtoupper(str_replace('_',' ',$plan['estado'])); ?></span>
            <span class="ms-3 text-muted">Cierre: <?php echo date('d/m H:i', strtotime($plan['fecha_fin'])); ?></span>
        </div>
        
        <form method="POST">
            <?php if($plan['estado'] == 'abierta' && tienePermiso('gestionar_planificaciones_medicas')): ?>
                <button type="submit" name="cerrar_encargado" class="btn btn-warning fw-bold">Cerrar Manualmente</button>
            <?php endif; ?>
            
            <?php if($plan['estado'] == 'cerrada_logistica' && tienePermiso('aprobar_planificacion_director')): ?>
                <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#modalRechazo">Rechazar</button>
                <button type="submit" name="aprobar_director" class="btn btn-success fw-bold">Aprobar Compra</button>
            <?php endif; ?>
        </form>
    </div>

    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white fw-bold">1. Desglose por Servicio</div>
        <div class="card-body p-0">
            <div class="accordion accordion-flush" id="accServ">
                <?php if(!$desglose) echo "<div class='p-3 text-center'>Sin datos.</div>"; ?>
                <?php $i=0; foreach($desglose as $serv => $items): $i++; ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c<?php echo $i; ?>">
                                <span class="fw-bold"><?php echo htmlspecialchars($serv); ?></span>
                                <span class="badge bg-secondary ms-2"><?php echo count($items); ?> ítems</span>
                            </button>
                        </h2>
                        <div id="c<?php echo $i; ?>" class="accordion-collapse collapse" data-bs-parent="#accServ">
                            <div class="accordion-body bg-light">
                                <div class="text-end mb-2">
                                    <a href="generar_pdf_planificacion.php?id=<?php echo $id_plan; ?>&servicio=<?php echo urlencode($serv); ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-file-pdf"></i> Imprimir <?php echo htmlspecialchars($serv); ?>
                                    </a>
                                </div>
                                <table class="table table-sm bg-white border">
                                    <thead><tr><th>Ítem</th><th class="text-end">Cant.</th></tr></thead>
                                    <tbody>
                                        <?php foreach($items as $it): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($it['item']); ?></td>
                                            <td class="text-end fw-bold"><?php echo $it['cantidad_solicitada']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white fw-bold">2. Consolidado Total</div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead class="table-dark"><tr><th>Insumo</th><th class="text-center">Total</th></tr></thead>
                <tbody>
                    <?php foreach($total as $t): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($t['nombre_item']); ?></td>
                        <td class="text-center fw-bold fs-5"><?php echo $t['total']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRechazo" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Rechazar Planificación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="accion" value="rechazar_director">
                <div class="mb-3">
                    <label class="fw-bold">Motivo del Rechazo / Bitácora *</label>
                    <textarea name="motivo_rechazo" class="form-control" rows="4" required placeholder="Indique qué debe corregirse..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-danger fw-bold">Confirmar Rechazo</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>