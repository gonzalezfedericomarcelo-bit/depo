<?php
// Archivo: pedidos_ver.php
require 'db.php';
session_start();
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

if (!isset($_GET['id'])) die("Falta ID");
$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Obtener Pedido
$sql = "SELECT p.*, u.nombre_completo as solicitante, cf.nombre_estado, cf.etiqueta_estado, cf.nombre_proceso 
        FROM pedidos_servicio p 
        LEFT JOIN usuarios u ON p.id_usuario_solicitante = u.id 
        LEFT JOIN config_flujos cf ON p.paso_actual_id = cf.id 
        WHERE p.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$pedido = $stmt->fetch();

// Items
$stmtItems = $pdo->prepare("SELECT pi.*, COALESCE(im.nombre, sg.nombre) as producto
            FROM pedidos_items pi 
            LEFT JOIN insumos_medicos im ON pi.id_insumo = im.id 
            LEFT JOIN suministros_generales sg ON pi.id_suministro = sg.id 
            WHERE pi.id_pedido = :id");
$stmtItems->execute([':id' => $id]);
$items = $stmtItems->fetchAll();

// Adjuntos
$adjuntos = $pdo->query("SELECT * FROM adjuntos WHERE entidad_tipo='pedido_servicio' AND id_entidad=$id")->fetchAll();

$accion_boton = "";
$texto_boton = "";
$usa_modal = false;
$mensaje_estado = "";

// LÓGICA ESPECÍFICA PARA SUMINISTROS
if ($pedido['nombre_proceso'] == 'movimiento_suministros') {
    
    // 1. PENDIENTE DEPÓSITO -> Botón "Recibí Autorización"
    if ($pedido['nombre_estado'] == 'pendiente_deposito' && tienePermiso('recibir_orden_suministros')) {
        $accion_boton = "confirmar_recepcion_solicitud";
        $texto_boton = "✅ Recibí la Solicitud Autorizada (OK)";
        $mensaje_estado = "Confirma para avisar al usuario que el pedido entró en preparación.";
    }
    
    // 2. EN PREPARACIÓN -> Botón "Realizar Entrega"
    elseif ($pedido['nombre_estado'] == 'en_preparacion' && tienePermiso('realizar_entrega_suministros')) {
        $accion_boton = "realizar_entrega";
        $texto_boton = "📦 Realizar Entrega y Notificar";
        $usa_modal = true;
        $mensaje_estado = "Al confirmar, se le avisa al usuario para que retire.";
    }
}

// LOGICA COMÚN: CIERRE USUARIO
if ($pedido['nombre_estado'] == 'listo_para_retirar' && $pedido['id_usuario_solicitante'] == $user_id) {
    if (tienePermiso('confirmar_recepcion')) {
        $accion_boton = "confirmar_retiro_usuario";
        $texto_boton = "👍 Confirmar Recepción Conforme";
        $usa_modal = true;
    }
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h3>Solicitud #<?php echo $pedido['id']; ?></h3>
        <a href="historial_pedidos.php" class="btn btn-secondary">Volver</a>
    </div>

    <form action="bandeja_gestion_dinamica.php" method="POST">
        <input type="hidden" name="id_pedido" value="<?php echo $pedido['id']; ?>">
        <input type="hidden" name="accion" value="<?php echo $accion_boton; ?>">

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-3 shadow-sm border-0">
                    <div class="card-header bg-dark text-white">Estado</div>
                    <div class="card-body">
                        <h5 class="card-title text-primary fw-bold"><?php echo $pedido['etiqueta_estado']; ?></h5>
                        <p class="small text-muted">Paso: <?php echo $pedido['nombre_estado']; ?></p>
                        <hr>

                        <?php if ($mensaje_estado): ?>
                            <div class="alert alert-info small py-2 mb-3">
                                <?php echo $mensaje_estado; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($accion_boton): ?>
                            <?php if ($usa_modal): ?>
                                <button type="button" class="btn btn-success w-100 fw-bold py-3" data-bs-toggle="modal" data-bs-target="#modalConfirmacion">
                                    <?php echo $texto_boton; ?>
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary w-100 fw-bold py-3">
                                    <?php echo $texto_boton; ?>
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-light text-center border">
                                <?php if($pedido['estado'] == 'finalizado_proceso'): ?>
                                    <span class="text-success fw-bold">Proceso Finalizado</span>
                                <?php else: ?>
                                    Esperando acción de otro rol.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($pedido['estado'] == 'finalizado_proceso' && $pedido['id_entrega_generada']): ?>
                            <hr>
                            <a href="generar_pdf_entrega_suministros.php?id=<?php echo $pedido['id_entrega_generada']; ?>" target="_blank" class="btn btn-danger w-100 fw-bold">
                                <i class="fas fa-file-pdf me-2"></i> CONSTANCIA
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">Ítems</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light"><tr><th>Producto</th><th class="text-center">Cant.</th></tr></thead>
                            <tbody>
                                <?php foreach($items as $i): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i['producto']); ?></td>
                                    <td class="text-center fw-bold fs-5 text-primary"><?php echo $i['cantidad_aprobada'] ?? $i['cantidad_solicitada']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalConfirmacion" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white"><h5 class="modal-title">Confirmar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><p class="fs-5">¿Estás seguro de continuar?</p></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success fw-bold">CONFIRMAR</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>