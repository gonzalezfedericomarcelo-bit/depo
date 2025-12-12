<?php
// Archivo: pedidos_ver.php
// Propósito: Visualizar pedido y mostrar botones DINÁMICOS para AMBOS flujos
require 'db.php';
session_start();
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

if (!isset($_GET['id'])) die("Falta ID");
$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Consultas
$sql = "SELECT p.*, u.nombre_completo as solicitante, cf.nombre_estado, cf.etiqueta_estado, cf.nombre_proceso 
        FROM pedidos_servicio p 
        LEFT JOIN usuarios u ON p.id_usuario_solicitante = u.id 
        LEFT JOIN config_flujos cf ON p.paso_actual_id = cf.id 
        WHERE p.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$pedido = $stmt->fetch();

$stmtItems = $pdo->prepare("SELECT pi.*, COALESCE(im.nombre, sg.nombre) as producto
            FROM pedidos_items pi 
            LEFT JOIN insumos_medicos im ON pi.id_insumo = im.id 
            LEFT JOIN suministros_generales sg ON pi.id_suministro = sg.id 
            WHERE pi.id_pedido = :id");
$stmtItems->execute([':id' => $id]);
$items = $stmtItems->fetchAll();

$adjuntos = $pdo->query("SELECT * FROM adjuntos WHERE entidad_tipo='pedido_servicio' AND id_entidad=$id")->fetchAll();

// VARIABLES UI
$accion_boton = "";
$texto_boton = "";
$usa_modal = false;
$mensaje_estado = "";
$es_link_externo = false; 
$link_externo = "";

// ==========================================
// 1. FLUJO SUMINISTROS (Logística -> Depósito)
// ==========================================
if ($pedido['nombre_proceso'] == 'movimiento_suministros') {
    if ($pedido['nombre_estado'] == 'revision_logistica' && tienePermiso('aprobar_suministros_logistica')) {
        $es_link_externo = true;
        $link_externo = "pedidos_revision_logistica.php?id=" . $id;
        $texto_boton = "🔍 Revisar y Autorizar (Logística)";
    }
    elseif ($pedido['nombre_estado'] == 'pendiente_deposito' && tienePermiso('recibir_orden_suministros')) {
        $accion_boton = "confirmar_recepcion_solicitud";
        $texto_boton = "📥 Recibí la solicitud autorizada";
    }
    elseif ($pedido['nombre_estado'] == 'en_preparacion' && tienePermiso('realizar_entrega_suministros')) {
        $accion_boton = "realizar_entrega";
        $texto_boton = "📦 Entregar pedido y notificar";
        $usa_modal = true;
    }
}

// ==========================================
// 2. FLUJO INSUMOS MÉDICOS (Encargado -> Director -> Encargado)
// ==========================================
if ($pedido['nombre_proceso'] == 'movimiento_insumos') {
    
    // Paso 1: Encargado Revisa
    if ($pedido['nombre_estado'] == 'revision_encargado' && tienePermiso('aprobar_insumos_encargado')) {
        $es_link_externo = true;
        $link_externo = "pedidos_revision_encargado.php?id=" . $id;
        $texto_boton = "🔍 Revisar Stock y Pasar a Director";
        $mensaje_estado = "Verifica stock antes de enviar al Director Médico.";
    }
    
    // Paso 2: Director Aprueba
    elseif ($pedido['nombre_estado'] == 'revision_director' && tienePermiso('aprobar_insumos_director')) {
        $es_link_externo = true;
        $link_externo = "pedidos_revision_director.php?id=" . $id;
        $texto_boton = "👨‍⚕️ Revisión Director Médico";
        $mensaje_estado = "Autorización final de cantidades.";
    }
    
    // Paso 3: Encargado Recibe (Nuevo botón)
    elseif ($pedido['nombre_estado'] == 'pendiente_preparacion' && tienePermiso('realizar_entrega_insumos')) {
        $accion_boton = "confirmar_recepcion_insumos"; // Acción específica en bandeja
        $texto_boton = "📥 Recibí la autorización del Director";
        $mensaje_estado = "Confirmar recepción para iniciar preparación.";
    }

    // Paso 4: Encargado Entrega
    elseif ($pedido['nombre_estado'] == 'en_preparacion' && tienePermiso('realizar_entrega_insumos')) {
        $accion_boton = "realizar_entrega_insumos"; // Acción específica en bandeja
        $texto_boton = "📦 Entregar pedido y notificar";
        $usa_modal = true;
        $mensaje_estado = "Llama al usuario para retirar.";
    }
}

// ==========================================
// 3. CIERRE USUARIO (Común)
// ==========================================
if ($pedido['nombre_estado'] == 'listo_para_retirar' && $pedido['id_usuario_solicitante'] == $user_id) {
    if (tienePermiso('confirmar_recepcion')) {
        $accion_boton = "confirmar_retiro_usuario";
        $texto_boton = "👍 Recibir conforme";
        $usa_modal = true;
        $mensaje_estado = "Confirma aquí para finalizar y descontar stock.";
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
                    <div class="card-header bg-dark text-white">Estado Actual</div>
                    <div class="card-body">
                        <h5 class="card-title text-primary fw-bold"><?php echo $pedido['etiqueta_estado']; ?></h5>
                        <p class="small text-muted mb-2">Fase: <?php echo $pedido['nombre_estado']; ?></p>
                        
                        <?php if ($mensaje_estado): ?>
                            <div class="alert alert-info small py-2 mb-3 border-info">
                                <i class="fas fa-info-circle"></i> <?php echo $mensaje_estado; ?>
                            </div>
                        <?php endif; ?>

                        <hr>

                        <?php if ($es_link_externo): ?>
                            <a href="<?php echo $link_externo; ?>" class="btn btn-warning w-100 fw-bold py-3 text-dark border-dark">
                                <?php echo $texto_boton; ?>
                            </a>

                        <?php elseif ($accion_boton): ?>
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
                            <div class="p-3 bg-light text-center border rounded">
                                <span class="text-muted">Esperando acción de otro usuario.</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($pedido['estado'] == 'finalizado_proceso' && $pedido['id_entrega_generada']): ?>
                            <hr>
                            <a href="generar_pdf_entrega.php?id=<?php echo $pedido['id_entrega_generada']; ?>" target="_blank" class="btn btn-danger w-100 fw-bold">
                                <i class="fas fa-file-pdf me-2"></i> CONSTANCIA PDF
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-light fw-bold">Detalle del Pedido</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light"><tr><th>PRODUCTO</th><th class="text-center">CANT. APROBADA</th></tr></thead>
                            <tbody>
                                <?php foreach($items as $i): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i['producto']); ?></td>
                                    <td class="text-center fw-bold fs-5 text-primary">
                                        <?php echo isset($i['cantidad_aprobada']) ? $i['cantidad_aprobada'] : $i['cantidad_solicitada']; ?>
                                    </td>
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
                    <div class="modal-header bg-success text-white"><h5 class="modal-title">Confirmar Acción</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><p class="fs-5">¿Estás seguro de realizar esta acción?</p></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success fw-bold">SI, CONFIRMAR</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>