<?php
// Archivo: suministros_gestion_compras.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php'; 
include 'includes/navbar.php';

if (!tienePermiso('procesar_compra_precios')) die("Acceso denegado");

$id_plan = $_GET['id'];
$plan = $pdo->query("SELECT * FROM compras_planificaciones WHERE id=$id_plan")->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generar_oc'])) {
    try {
        $pdo->beginTransaction();
        
        // Crear OC
        $stmtOC = $pdo->prepare("INSERT INTO ordenes_compra (numero_oc, servicio_destino, tipo_origen, id_usuario_creador, estado, id_planificacion_origen, observaciones) VALUES (:num, 'Stock Central', 'suministros', :user, 'aprobada_logistica', :plan, :obs)");
        $stmtOC->execute([
            ':num' => $_POST['numero_oc'],
            ':user' => $_SESSION['user_id'],
            ':plan' => $id_plan,
            ':obs' => 'OC generada desde planificación #' . $id_plan
        ]);
        $id_oc = $pdo->lastInsertId();

        // Items
        $stmtItem = $pdo->prepare("INSERT INTO ordenes_compra_items (id_oc, descripcion_producto, id_suministro_asociado, cantidad_solicitada, cantidad_aprobada_compra, precio_unitario) VALUES (:oc, :desc, :id_sum, :cant_orig, :cant_real, :precio)");
        
        foreach ($_POST['items'] as $key => $datos) {
            // $key es un hash único, no el ID de suministro, porque puede haber manuales
            $cant_real = $datos['cantidad_compra'];
            $precio = $datos['precio'];
            $nombre = $datos['nombre_producto'];
            $cant_orig = $datos['cantidad_original'];
            $id_suministro_db = !empty($datos['id_suministro_db']) ? $datos['id_suministro_db'] : null;

            if ($cant_real > 0) {
                $stmtItem->execute([
                    ':oc' => $id_oc,
                    ':desc' => $nombre,
                    ':id_sum' => $id_suministro_db, // Puede ser NULL si es manual
                    ':cant_orig' => $cant_orig,
                    ':cant_real' => $cant_real,
                    ':precio' => $precio
                ]);
            }
        }

        // Adjunto
        if (!empty($_FILES['adjunto_oc']['name'])) {
            $path = 'uploads/' . uniqid() . '_' . $_FILES['adjunto_oc']['name'];
            move_uploaded_file($_FILES['adjunto_oc']['tmp_name'], $path);
            $pdo->prepare("INSERT INTO adjuntos (entidad_tipo, id_entidad, ruta_archivo, nombre_original) VALUES ('orden_compra', ?, ?, ?)")->execute(['orden_compra', $id_oc, $path, $_FILES['adjunto_oc']['name']]);
        }

        // Finalizar Planificación
        $pdo->prepare("UPDATE compras_planificaciones SET estado='orden_generada' WHERE id=?")->execute([$id_plan]);

        // Notificar Depósito
        $rolDep = $pdo->query("SELECT id FROM roles WHERE nombre='Encargado Depósito Suministros'")->fetchColumn();
        $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")->execute([$rolDep, "Nueva OC Mayorista Lista para Recibir: ".$plan['titulo'], "suministros_recepcion.php?id=$id_oc"]);

        $pdo->commit();
        echo "<script>window.location='suministros_compras.php';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Error: " . $e->getMessage();
    }
}

// Cargar ítems (Stock y Manuales)
$sqlItems = "
    SELECT 
        COALESCE(s.nombre, pi.detalle_personalizado) as nombre,
        s.id as id_suministro,
        SUM(pi.cantidad_solicitada) as total
    FROM pedidos_items pi
    JOIN pedidos_servicio ps ON pi.id_pedido = ps.id
    LEFT JOIN suministros_generales s ON pi.id_suministro = s.id
    WHERE ps.id_planificacion = :id
    GROUP BY nombre
";
$items = $pdo->prepare($sqlItems);
$items->execute([':id' => $id_plan]);
$lista = $items->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Procesar Compra: <?php echo htmlspecialchars($plan['titulo']); ?></h1>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="card mb-4 shadow">
            <div class="card-header bg-primary text-white">Generar OC</div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6"><label>N° OC</label><input type="text" name="numero_oc" class="form-control" required></div>
                    <div class="col-md-6"><label>Adjunto</label><input type="file" name="adjunto_oc" class="form-control" required></div>
                </div>

                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr><th>Producto</th><th>Solicitado</th><th class="bg-success bg-opacity-10">A Comprar</th><th class="bg-warning bg-opacity-10">Precio ($)</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($lista as $idx => $i): ?>
                        <tr>
                            <td>
                                <?php echo $i['nombre']; ?>
                                <input type="hidden" name="items[<?php echo $idx; ?>][nombre_producto]" value="<?php echo $i['nombre']; ?>">
                                <input type="hidden" name="items[<?php echo $idx; ?>][cantidad_original]" value="<?php echo $i['total']; ?>">
                                <input type="hidden" name="items[<?php echo $idx; ?>][id_suministro_db]" value="<?php echo $i['id_suministro']; ?>">
                            </td>
                            <td class="text-center"><?php echo $i['total']; ?></td>
                            <td class="bg-success bg-opacity-10">
                                <input type="number" name="items[<?php echo $idx; ?>][cantidad_compra]" class="form-control fw-bold" value="<?php echo $i['total']; ?>">
                            </td>
                            <td class="bg-warning bg-opacity-10">
                                <input type="number" name="items[<?php echo $idx; ?>][precio]" class="form-control" step="0.01" required>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end"><button type="submit" name="generar_oc" class="btn btn-primary btn-lg">💾 Confirmar OC</button></div>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>