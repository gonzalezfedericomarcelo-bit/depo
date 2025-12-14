<?php
// Archivo: insumos_gestion_compras.php
// Propósito: Procesar compra médica (ASIGNA AUTOMÁTICAMENTE AL DIRECTOR COMO APROBADOR)

try {
    require 'db.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
} catch (Exception $e) { die("Error sistema: " . $e->getMessage()); }

include 'includes/header.php';
include 'includes/sidebar.php'; 
include 'includes/navbar.php';

if (!tienePermiso('procesar_compra_precios')) die("<div class='container m-5 alert alert-danger'>⛔ Acceso Denegado</div>");

$id_plan = $_GET['id'] ?? 0;

// Obtener datos de la campaña
$stmtPlan = $pdo->prepare("SELECT * FROM compras_planificaciones WHERE id = ? AND tipo_insumo='insumos'");
$stmtPlan->execute([$id_plan]);
$plan = $stmtPlan->fetch();

if(!$plan) die("<div class='container mt-5 alert alert-danger'>Planificación no encontrada.</div>");

// --- PROCESAR EL GUARDADO DE LA OC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar_oc'])) {
    try {
        $pdo->beginTransaction();
        
        // 1. BUSCAR QUIÉN ES EL DIRECTOR (Para asignarle la firma)
        // Buscamos dinámicamente qué usuario tiene el permiso de aprobar estas planificaciones
        $sqlDir = "SELECT u.id FROM usuarios u 
                   JOIN usuario_roles ur ON u.id = ur.id_usuario 
                   JOIN rol_permisos rp ON ur.id_rol = rp.id_rol 
                   JOIN permisos p ON rp.id_permiso = p.id 
                   WHERE p.clave = 'aprobar_planificacion_director' 
                   AND u.activo = 1 
                   LIMIT 1";
        $stmtDir = $pdo->query($sqlDir);
        $id_director = $stmtDir->fetchColumn(); // Obtiene el ID del Director

        // 2. Cabecera OC (Ahora guardamos id_usuario_aprobador)
        $stmtOC = $pdo->prepare("INSERT INTO ordenes_compra (numero_oc, servicio_destino, tipo_origen, id_usuario_creador, id_usuario_aprobador, estado, id_planificacion_origen, observaciones) VALUES (:num, 'Depósito Central', 'insumos', :user, :aprobador, 'aprobada_logistica', :plan, :obs)");
        
        $stmtOC->execute([
            ':num' => $_POST['numero_oc'],
            ':user' => $_SESSION['user_id'],
            ':aprobador' => $id_director ? $id_director : null, // Aquí asignamos al Director
            ':plan' => $id_plan,
            ':obs' => 'Compra masiva campaña #' . $id_plan
        ]);
        $id_oc = $pdo->lastInsertId();

        // 3. Ítems
        $stmtItem = $pdo->prepare("INSERT INTO ordenes_compra_items (id_oc, descripcion_producto, id_insumo_asociado, cantidad_solicitada, cantidad_aprobada_compra, precio_unitario) VALUES (:oc, :desc, :id_ins, :cant_orig, :cant_real, :precio)");
        
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $id_item_pedido => $datos) {
                $cantidad_compra = (int)$datos['cantidad_compra'];
                
                // Limpieza de precio (para asegurar que se guarde bien)
                $p = str_replace(['$', ' '], '', $datos['precio']);
                if (strpos($p, '.') !== false && strpos($p, ',') !== false) $p = str_replace('.', '', $p);
                $p = str_replace(',', '.', $p);
                $precio_final = floatval($p);

                if ($cantidad_compra > 0) {
                    $descripcion_final = $datos['nombre_producto'] . " [" . $datos['servicio'] . "]";
                    
                    $stmtItem->execute([
                        ':oc' => $id_oc,
                        ':desc' => $descripcion_final,
                        ':id_ins' => !empty($datos['id_insumo_db']) ? $datos['id_insumo_db'] : null,
                        ':cant_orig' => $datos['cantidad_original'],
                        ':cant_real' => $cantidad_compra,
                        ':precio' => $precio_final
                    ]);
                }
            }
        }
        
        // 4. Adjunto
        if (!empty($_FILES['adjunto_oc']['name'])) {
            $path = 'uploads/ordenes_compra/' . uniqid() . '_' . basename($_FILES['adjunto_oc']['name']);
            if (!file_exists('uploads/ordenes_compra')) mkdir('uploads/ordenes_compra', 0777, true);
            if (move_uploaded_file($_FILES['adjunto_oc']['tmp_name'], $path)) {
                $pdo->prepare("INSERT INTO adjuntos (entidad_tipo, id_entidad, ruta_archivo, nombre_original) VALUES ('orden_compra', ?, ?, ?)")->execute([$id_oc, $path, $_FILES['adjunto_oc']['name']]);
            }
        }

        // 5. Finalizar Campaña
        $pdo->prepare("UPDATE compras_planificaciones SET estado='orden_generada' WHERE id=?")->execute([$id_plan]);

        // 6. Notificar
        $msg = "OC Médica Generada (Campaña: ".$plan['titulo'].")";
        $rolEnc = obtenerIdRolPorPermiso('recibir_oc_insumos');
        if($rolEnc) $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")->execute([$rolEnc, $msg, "insumos_recepcion.php?id=$id_oc"]);

        $pdo->commit();
        echo "<script>alert('Orden Generada Correctamente'); window.open('generar_pdf_oc.php?id=$id_oc', '_blank'); window.location='insumos_compras.php';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger m-4'>Error: " . $e->getMessage() . "</div>";
    }
}

// CONSULTA DESGLOSADA (SIN GROUP BY PARA VER TODO)
$sql = "SELECT 
            pi.id as id_item_pedido,
            ps.servicio_solicitante,
            COALESCE(im.nombre, pi.detalle_personalizado) as nombre_producto,
            pi.cantidad_solicitada,
            im.id as id_insumo_db
        FROM pedidos_items pi
        JOIN pedidos_servicio ps ON pi.id_pedido = ps.id 
        LEFT JOIN insumos_medicos im ON pi.id_insumo = im.id 
        WHERE ps.id_planificacion = :id 
        ORDER BY ps.servicio_solicitante ASC, nombre_producto ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id_plan]);
$filas = $stmt->fetchAll();

$resumen_total = [];
?>

<div class="container-fluid px-4 mb-5">
    <h1 class="mt-4 text-primary"><i class="fas fa-file-invoice-dollar"></i> Procesar Compra: <?php echo htmlspecialchars($plan['titulo']); ?></h1>
    
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="generar_oc" value="1">
        
        <div class="card mb-4 shadow-sm border-primary">
            <div class="card-header bg-primary text-white fw-bold">1. Datos de Facturación</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">N° Factura / Orden Compra *</label>
                        <input type="text" name="numero_oc" class="form-control form-control-lg" required placeholder="Ej: OC-2025-XXX">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Adjuntar Comprobante (PDF/Img) *</label>
                        <input type="file" name="adjunto_oc" class="form-control form-control-lg" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 shadow border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="m-0 fw-bold text-dark"><i class="fas fa-list me-2"></i> 2. Detalle de Solicitudes (Ajuste de Precios)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="text-uppercase small text-muted">
                                <th style="width: 40%">Producto / Detalle</th>
                                <th style="width: 15%" class="text-center">Solicitado</th>
                                <th style="width: 20%" class="text-center bg-success bg-opacity-10">Cant. a Comprar</th>
                                <th style="width: 25%" class="text-center bg-warning bg-opacity-10">Precio Unit. ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $servicio_actual = '';
                            
                            if (count($filas) > 0):
                                foreach ($filas as $f): 
                                    // AGRUPACIÓN VISUAL
                                    if ($f['servicio_solicitante'] != $servicio_actual) {
                                        $servicio_actual = $f['servicio_solicitante'];
                                        echo "<tr class='table-secondary text-uppercase fw-bold'><td colspan='4' class='ps-3 py-2 text-dark'><i class='fas fa-building me-2'></i> $servicio_actual</td></tr>";
                                    }

                                    // ACUMULAR
                                    $nombre_prod = $f['nombre_producto'];
                                    if (!isset($resumen_total[$nombre_prod])) $resumen_total[$nombre_prod] = 0;
                                    $resumen_total[$nombre_prod] += $f['cantidad_solicitada'];
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php echo htmlspecialchars($f['nombre_producto']); ?>
                                        <input type="hidden" name="items[<?php echo $f['id_item_pedido']; ?>][nombre_producto]" value="<?php echo htmlspecialchars($f['nombre_producto']); ?>">
                                        <input type="hidden" name="items[<?php echo $f['id_item_pedido']; ?>][servicio]" value="<?php echo htmlspecialchars($f['servicio_solicitante']); ?>">
                                        <input type="hidden" name="items[<?php echo $f['id_item_pedido']; ?>][cantidad_original]" value="<?php echo $f['cantidad_solicitada']; ?>">
                                        <input type="hidden" name="items[<?php echo $f['id_item_pedido']; ?>][id_insumo_db]" value="<?php echo $f['id_insumo_db']; ?>">
                                    </td>
                                    <td class="text-center fw-bold text-secondary">
                                        <?php echo $f['cantidad_solicitada']; ?>
                                    </td>
                                    <td class="bg-success bg-opacity-10 p-1">
                                        <input type="number" name="items[<?php echo $f['id_item_pedido']; ?>][cantidad_compra]" 
                                               class="form-control text-center fw-bold border-success" 
                                               value="<?php echo $f['cantidad_solicitada']; ?>" min="0">
                                    </td>
                                    <td class="bg-warning bg-opacity-10 p-1">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-warning">$</span>
                                            <input type="text" name="items[<?php echo $f['id_item_pedido']; ?>][precio]" 
                                                   class="form-control text-end border-warning fw-bold" 
                                                   placeholder="0.00" required>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endforeach; 
                            else:
                            ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No hay solicitudes en esta campaña.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (count($resumen_total) > 0): ?>
        <div class="card mb-4 shadow border-dark">
            <div class="card-header bg-dark text-white fw-bold">
                <i class="fas fa-calculator me-2"></i> 3. Resumen Global de Necesidades
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 table-sm">
                    <thead>
                        <tr>
                            <th>Producto Unificado</th>
                            <th class="text-center">Total Solicitado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($resumen_total as $prod => $total): ?>
                        <tr>
                            <td class="fw-bold text-primary ps-3"><?php echo htmlspecialchars($prod); ?></td>
                            <td class="text-center fw-bold fs-5"><?php echo $total; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-5">
            <a href="insumos_planificacion_panel.php" class="btn btn-secondary btn-lg me-2">Cancelar</a>
            <button type="submit" class="btn btn-success btn-lg fw-bold shadow-lg px-5">
                <i class="fas fa-check-double me-2"></i> CONFIRMAR Y GENERAR ORDEN
            </button>
        </div>

    </form>
</div>
<?php include 'includes/footer.php'; ?>