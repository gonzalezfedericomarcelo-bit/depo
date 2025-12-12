<?php
// Archivo: suministros_recepcion.php
// Propósito: Recepción, Ajuste de Stock y Alta de Productos Nuevos

require 'db.php';
session_start();

if (!isset($_GET['id'])) { header("Location: suministros_compras.php"); exit; }
$id_oc = $_GET['id'];

// 1. ALTA RÁPIDA (Para los productos manuales)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'crear_rapido') {
    try {
        $stmtNew = $pdo->prepare("INSERT INTO suministros_generales (codigo, nombre, descripcion, unidad_medida, stock_actual, stock_minimo) VALUES (:c, :n, 'Alta en recepción', 'unidades', 0, 5)");
        $stmtNew->execute([':c' => $_POST['nuevo_codigo'], ':n' => $_POST['nuevo_nombre']]);
        echo '<div class="alert alert-success">✅ Creado: ' . htmlspecialchars($_POST['nuevo_nombre']) . '</div>';
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// 2. PROCESAR RECEPCIÓN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'recibir') {
    try {
        $pdo->beginTransaction();
        $remito = $_POST['numero_remito'];
        $items_recibidos = $_POST['recibido']; // Array
        
        $detalle_faltantes = "";
        $hubo_faltantes = false;

        foreach ($items_recibidos as $id_item_oc => $datos) {
            $cant_recibida = (int)$datos['cantidad']; // Lo que dice el remito
            $id_suministro = (int)$datos['id_suministro']; // ID real en DB
            
            // Cantidad que Compras dijo que compró
            $stmtOrig = $pdo->prepare("SELECT cantidad_aprobada_compra, descripcion_producto FROM ordenes_compra_items WHERE id = ?");
            $stmtOrig->execute([$id_item_oc]);
            $itemOC = $stmtOrig->fetch();
            $cant_esperada = $itemOC['cantidad_aprobada_compra'];

            if ($id_suministro > 0) {
                // ACTUALIZAR STOCK (LA CLAVE)
                $stmtStock = $pdo->prepare("UPDATE suministros_generales SET stock_actual = stock_actual + :cant WHERE id = :id");
                $stmtStock->execute([':cant' => $cant_recibida, ':id' => $id_suministro]);

                // Actualizar Item OC (Vinculamos el ID real si era manual)
                $stmtItem = $pdo->prepare("UPDATE ordenes_compra_items SET cantidad_recibida = :cant, id_suministro_asociado = :id_sum WHERE id = :id");
                $stmtItem->execute([':cant' => $cant_recibida, ':id_sum' => $id_suministro, ':id' => $id_item_oc]);

                // Registro de diferencias
                if ($cant_recibida != $cant_esperada) {
                    $hubo_faltantes = true;
                    $diff = $cant_esperada - $cant_recibida;
                    $detalle_faltantes .= "Item '{$itemOC['descripcion_producto']}': Esperado $cant_esperada / Recibido $cant_recibida (Dif: $diff). ";
                }
            }
        }

        // Actualizar OC
        $obs = "\n[RECEPCIÓN] Remito: $remito. " . ($detalle_faltantes ? "Diferencias: $detalle_faltantes" : "Todo OK");
        $estado = $hubo_faltantes ? 'recibida_parcial' : 'completada';
        
        $pdo->prepare("UPDATE ordenes_compra SET estado = :est, observaciones = CONCAT(IFNULL(observaciones,''), :obs) WHERE id = :id")
            ->execute([':est' => $estado, ':obs' => $obs, ':id' => $id_oc]);

        $pdo->commit();
        header("Location: suministros_stock.php?msg=recepcion_ok");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$stmtItems = $pdo->prepare("SELECT * FROM ordenes_compra_items WHERE id_oc = :id");
$stmtItems->execute(['id' => $id_oc]);
$items_orden = $stmtItems->fetchAll();

$lista_suministros = $pdo->query("SELECT id, nombre, codigo FROM suministros_generales ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Recepción de Mercadería</h1>
    
    <form method="POST">
        <input type="hidden" name="accion" value="recibir">
        
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between">
                <span>Control Remito vs OC</span>
                <div>
                    <label class="text-white small me-2">N° Remito:</label>
                    <input type="text" name="numero_remito" class="form-control d-inline-block w-auto py-0" required>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Producto (Según OC)</th>
                            <th class="text-center">Cant. Comprada</th>
                            <th>Vincular a Stock</th>
                            <th class="text-center bg-warning bg-opacity-10" width="150">Cant. Real (Remito)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items_orden as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['descripcion_producto']); ?></td>
                            <td class="text-center fw-bold"><?php echo $item['cantidad_aprobada_compra']; ?></td>
                            <td>
                                <select name="recibido[<?php echo $item['id']; ?>][id_suministro]" class="form-select select-search" required>
                                    <option value="">-- Seleccionar ID Stock --</option>
                                    <?php foreach ($lista_suministros as $s): ?>
                                        <?php 
                                            // Pre-seleccionar si ya tiene ID o coincide el nombre
                                            $selected = ($item['id_suministro_asociado'] == $s['id'] || stripos($s['nombre'], $item['descripcion_producto']) !== false) ? 'selected' : ''; 
                                        ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($s['nombre']) . " (" . $s['codigo'] . ")"; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#modalAltaRapida">+ Crear si no existe</button>
                            </td>
                            <td class="bg-warning bg-opacity-10">
                                <input type="number" name="recibido[<?php echo $item['id']; ?>][cantidad]" class="form-control text-center fw-bold" value="<?php echo $item['cantidad_aprobada_compra']; ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary btn-lg">✅ Confirmar Ingreso de Stock</button>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="modalAltaRapida" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white"><h5 class="modal-title">Crear Nuevo Suministro</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear_rapido">
                    <div class="mb-3"><label>Nombre *</label><input type="text" name="nuevo_nombre" class="form-control" required></div>
                    <div class="mb-3"><label>Código</label><input type="text" name="nuevo_codigo" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-success">Guardar</button></div>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>