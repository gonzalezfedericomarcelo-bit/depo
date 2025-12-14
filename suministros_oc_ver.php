<?php
// Archivo: suministros_oc_ver.php
// Propósito: Vista de Orden de Compra Suministros (Diseño Profesional)

require 'db.php';
session_start();

if (!isset($_GET['id']) || empty($_GET['id'])) { header("Location: suministros_compras.php"); exit; }
$id_oc = $_GET['id'];
$roles_usuario = $_SESSION['user_roles'] ?? [];
$mensaje = "";

// LÓGICA DE APROBACIÓN (Logística o Admin)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion'])) {
    if (in_array('Encargado Logística', $roles_usuario) || in_array('Administrador', $roles_usuario)) {
        try {
            $pdo->beginTransaction();
            
            $stmtOwner = $pdo->prepare("SELECT id_usuario_creador, numero_oc FROM ordenes_compra WHERE id = :id");
            $stmtOwner->execute(['id' => $id_oc]);
            $datosOC = $stmtOwner->fetch();

            $nuevo_estado = ($_POST['accion'] == 'aprobar') ? 'aprobada_logistica' : 'rechazada';
            
            $stmtUpdate = $pdo->prepare("UPDATE ordenes_compra SET estado = :estado, id_usuario_aprobador = :user, fecha_aprobacion = NOW() WHERE id = :id");
            $stmtUpdate->execute([':estado' => $nuevo_estado, ':user' => $_SESSION['user_id'], ':id' => $id_oc]);

            $msj_creador = ($nuevo_estado == 'aprobada_logistica') 
                ? "✅ Logística APROBÓ la OC Suministros #{$datosOC['numero_oc']}." 
                : "❌ Logística RECHAZÓ la OC Suministros #{$datosOC['numero_oc']}.";
            
            $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, mensaje, url_destino) VALUES (?, ?, ?)")
                ->execute([$datosOC['id_usuario_creador'], $msj_creador, "suministros_oc_ver.php?id=" . $id_oc]);

            if ($nuevo_estado == 'aprobada_logistica') {
                $rolDeposito = obtenerIdRolPorPermiso('recibir_oc_suministros');
                if ($rolDeposito) {
                    $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?, ?, ?)")
                        ->execute([$rolDeposito, "Logística autorizó carga. OC #{$datosOC['numero_oc']}", "suministros_recepcion.php?id=" . $id_oc]);
                }
            }

            $pdo->commit();
            $mensaje = '<div class="alert alert-success shadow-sm border-0 mb-4"><i class="fas fa-check-circle me-2"></i> Orden procesada correctamente.</div>';

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = '<div class="alert alert-danger shadow-sm border-0 mb-4">Error: ' . $e->getMessage() . '</div>';
        }
    } else {
        $mensaje = '<div class="alert alert-danger shadow-sm border-0 mb-4">⛔ Solo Logística puede aprobar.</div>';
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// Consultas
$stmt = $pdo->prepare("SELECT oc.*, u_creador.nombre_completo as creador, u_aprob.nombre_completo as aprobador FROM ordenes_compra oc JOIN usuarios u_creador ON oc.id_usuario_creador = u_creador.id LEFT JOIN usuarios u_aprob ON oc.id_usuario_aprobador = u_aprob.id WHERE oc.id = :id");
$stmt->execute(['id' => $id_oc]);
$orden = $stmt->fetch();

if (!$orden) { echo "<div class='container mt-5 alert alert-danger'>Orden no encontrada.</div>"; include 'includes/footer.php'; exit; }

$stmtItems = $pdo->prepare("SELECT * FROM ordenes_compra_items WHERE id_oc = :id");
$stmtItems->execute(['id' => $id_oc]);
$items = $stmtItems->fetchAll();

$stmtAdj = $pdo->prepare("SELECT * FROM adjuntos WHERE entidad_tipo = 'orden_compra' AND id_entidad = :id");
$stmtAdj->execute(['id' => $id_oc]);
$adjuntos = $stmtAdj->fetchAll();

// Estado visual
$estado_color = 'bg-secondary';
$estado_texto = 'DESCONOCIDO';
switch($orden['estado']) {
    case 'pendiente_logistica': $estado_color = 'bg-warning text-dark'; $estado_texto = 'PENDIENTE APROBACIÓN'; break;
    case 'aprobada_logistica': $estado_color = 'bg-success'; $estado_texto = 'APROBADA / EN CURSO'; break;
    case 'rechazada': $estado_color = 'bg-danger'; $estado_texto = 'RECHAZADA'; break;
    case 'recibida_parcial': $estado_color = 'bg-info text-dark'; $estado_texto = 'RECIBIDO PARCIAL'; break;
    case 'recibida_total': $estado_color = 'bg-primary'; $estado_texto = 'COMPLETADA'; break;
}
?>

<div class="container-fluid px-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0">Orden de Compra #<?php echo htmlspecialchars($orden['numero_oc']); ?></h2>
            <p class="text-muted mb-0">Gestión de Suministros Generales</p>
        </div>
        <div>
            <a href="suministros_compras.php" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left me-1"></i> Volver</a>
            <a href="generar_pdf_oc.php?id=<?php echo $id_oc; ?>" target="_blank" class="btn btn-dark"><i class="fas fa-file-pdf me-1"></i> Descargar PDF</a>
        </div>
    </div>
    
    <?php echo $mensaje; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header <?php echo $estado_color; ?> text-white d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold"><i class="fas fa-info-circle me-2"></i> ESTADO: <?php echo $estado_texto; ?></span>
            
            <?php if ($orden['estado'] == 'pendiente_logistica' && (in_array('Encargado Logística', $roles_usuario) || in_array('Administrador', $roles_usuario))): ?>
            <div>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="accion" value="rechazar">
                    <button type="submit" class="btn btn-light btn-sm text-danger fw-bold me-2" onclick="return confirm('¿Rechazar OC?');">Rechazar</button>
                </form>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="accion" value="aprobar">
                    <button type="submit" class="btn btn-light btn-sm text-success fw-bold" onclick="return confirm('¿Autorizar OC?');">Autorizar Compra</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if (($orden['estado'] == 'aprobada_logistica' || $orden['estado'] == 'recibida_parcial') && (in_array('Encargado Depósito Suministros', $roles_usuario) || in_array('Administrador', $roles_usuario))): ?>
                <a href="suministros_recepcion.php?id=<?php echo $orden['id']; ?>" class="btn btn-light btn-sm text-primary fw-bold">Ir a Recepción <i class="fas fa-arrow-right ms-1"></i></a>
            <?php endif; ?>
        </div>

        <div class="card-body p-4">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="text-success fw-bold text-uppercase mb-3">Policlínica ACTIS</h5>
                    <ul class="list-unstyled text-muted small">
                        <li><strong>Solicitante:</strong> <?php echo htmlspecialchars($orden['creador']); ?></li>
                        <li><strong>Fecha Emisión:</strong> <?php echo date('d/m/Y H:i', strtotime($orden['fecha_creacion'])); ?></li>
                        <?php if($orden['aprobador']): ?>
                            <li><strong>Autorizado por:</strong> <?php echo htmlspecialchars($orden['aprobador']); ?></li>
                            <li><strong>Fecha Aprobación:</strong> <?php echo date('d/m/Y H:i', strtotime($orden['fecha_aprobacion'])); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5 class="text-secondary fw-bold mb-3">Detalles de Entrega</h5>
                    <ul class="list-unstyled text-muted small">
                        <li><strong>Destino Físico:</strong> <?php echo htmlspecialchars($orden['servicio_destino'] ?? 'Stock Central'); ?></li>
                        <li><strong>Notas:</strong> <?php echo nl2br(htmlspecialchars($orden['observaciones'] ?? '-')); ?></li>
                    </ul>
                </div>
            </div>

            <div class="table-responsive mb-4">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="bg-light text-uppercase small text-secondary">
                        <tr>
                            <th style="width: 40%;">Producto / Suministro</th>
                            <th style="width: 25%;">Servicio Solicitante</th>
                            <th class="text-center" style="width: 10%;">Cant.</th>
                            <th class="text-end" style="width: 12%;">Precio Unit.</th>
                            <th class="text-end" style="width: 13%;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        foreach ($items as $item): 
                            // Lógica para separar "Producto [Servicio]"
                            $nombre_producto = $item['descripcion_producto'];
                            $servicio_solicitante = $orden['servicio_destino']; 
                            
                            if (preg_match('/^(.*?) \[(.*?)\]$/', $item['descripcion_producto'], $matches)) {
                                $nombre_producto = $matches[1];
                                $servicio_solicitante = $matches[2];
                            }

                            $precio = $item['precio_unitario'];
                            $subtotal = $item['cantidad_solicitada'] * $precio;
                            $grand_total += $subtotal;
                        ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($nombre_producto); ?></span>
                                    <?php if($item['cantidad_recibida'] > 0): ?>
                                        <br><span class="badge bg-success small mt-1">Recibido: <?php echo $item['cantidad_recibida']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($servicio_solicitante); ?></span></td>
                                <td class="text-center fw-bold"><?php echo $item['cantidad_solicitada']; ?></td>
                                <td class="text-end text-muted font-monospace">$ <?php echo number_format($precio, 2); ?></td>
                                <td class="text-end fw-bold font-monospace text-dark">$ <?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="4" class="text-end fw-bold text-uppercase pt-3">Total General</td>
                            <td class="text-end fw-bold fs-5 text-success pt-3">$ <?php echo number_format($grand_total, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if (count($adjuntos) > 0): ?>
            <div class="card bg-light border-0">
                <div class="card-body py-2">
                    <strong class="text-muted small text-uppercase me-2"><i class="fas fa-paperclip"></i> Adjuntos:</strong>
                    <?php foreach ($adjuntos as $adj): ?>
                        <a href="<?php echo $adj['ruta_archivo']; ?>" target="_blank" class="text-decoration-none me-3">
                            <?php echo $adj['nombre_original']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>