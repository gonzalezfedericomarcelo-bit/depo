<?php
// Archivo: campana_carga_suministros.php
// Propósito: Interfaz de CARGA DE CAMPAÑA PARA SUMINISTROS (Guardado parcial y cierre)

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

if (!tienePermiso('solicitar_suministros')) { die("Acceso denegado."); }

$id_campana = $_GET['campana'] ?? 0;
if(!$id_campana) {
    // Listado de campañas
    $hoy = date('Y-m-d');
    $campanas = $pdo->query("SELECT * FROM compras_planificaciones WHERE estado='abierta' AND tipo_insumo='suministros' AND fecha_fin >= '$hoy'")->fetchAll();
    echo "<div class='container px-4 mt-4'><h1>Seleccione Campaña Suministros</h1><div class='list-group mt-3'>";
    foreach($campanas as $c) {
        echo "<a href='?campana={$c['id']}' class='list-group-item list-group-item-action d-flex justify-content-between align-items-center'>
                <div><strong>{$c['titulo']}</strong><br><small class='text-muted'>Cobertura: {$c['frecuencia_cobertura']}</small></div>
                <span class='badge bg-danger'>Cierra: ".date('d/m/Y', strtotime($c['fecha_fin']))."</span>
              </a>";
    }
    echo "</div></div>";
    include 'includes/footer.php'; exit;
}

// DATOS CAMPAÑA
$campana = $pdo->query("SELECT * FROM compras_planificaciones WHERE id=$id_campana")->fetch();
if(!$campana) die("Campaña no existe");

$hoy = date('Y-m-d');
$vencida = ($hoy > $campana['fecha_fin']);

// PEDIDO EXISTENTE
$sqlPed = "SELECT * FROM pedidos_servicio WHERE id_planificacion = ? AND id_usuario_solicitante = ?";
$stmtPed = $pdo->prepare($sqlPed);
$stmtPed->execute([$id_campana, $_SESSION['user_id']]);
$pedido = $stmtPed->fetch();

$finalizado = ($pedido && $pedido['estado'] != 'en_carga');

// GUARDAR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$vencida && !$finalizado) {
    try {
        $pdo->beginTransaction();
        $accion = $_POST['accion'];
        
        if (!$pedido) {
            $stmtInsert = $pdo->prepare("INSERT INTO pedidos_servicio (tipo_insumo, proceso_origen, id_usuario_solicitante, servicio_solicitante, estado, id_planificacion) VALUES ('suministros', 'movimiento_suministros', ?, ?, 'en_carga', ?)");
            $stmtInsert->execute([$_SESSION['user_id'], $_SESSION['user_data']['servicio'], $id_campana]);
            $id_pedido = $pdo->lastInsertId();
        } else {
            $id_pedido = $pedido['id'];
        }

        $pdo->prepare("DELETE FROM pedidos_items WHERE id_pedido = ?")->execute([$id_pedido]);

        $stmtItem = $pdo->prepare("INSERT INTO pedidos_items (id_pedido, id_suministro, detalle_personalizado, cantidad_solicitada) VALUES (?, ?, ?, ?)");
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $it) {
                if ($it['cantidad'] > 0) {
                    $id_stock = !empty($it['id']) ? $it['id'] : null;
                    $det = !empty($it['detalle']) ? $it['detalle'] : null;
                    $stmtItem->execute([$id_pedido, $id_stock, $det, $it['cantidad']]);
                }
            }
        }

        if ($accion == 'finalizar') {
            $primerPaso = $pdo->query("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_suministros' ORDER BY paso_orden ASC LIMIT 1")->fetch();
            $pdo->prepare("UPDATE pedidos_servicio SET estado = ?, paso_actual_id = ?, fecha_solicitud = NOW() WHERE id = ?")
                ->execute([$primerPaso['nombre_estado'], $primerPaso['id'], $id_pedido]);
            
            $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")
                ->execute([$primerPaso['id_rol_responsable'], "Pedido Campaña Suministros FINALIZADO: ".$_SESSION['user_data']['servicio'], "pedidos_ver.php?id=$id_pedido"]);
        }

        $pdo->commit();
        header("Location: campana_carga_suministros.php?campana=$id_campana&msg=" . ($accion=='finalizar'?'fin':'guardado'));
        exit;

    } catch (Exception $e) { $pdo->rollBack(); echo "<script>alert('Error: {$e->getMessage()}');</script>"; }
}

$items_actuales = [];
if ($pedido) {
    $items_actuales = $pdo->query("SELECT * FROM pedidos_items WHERE id_pedido = " . $pedido['id'])->fetchAll();
}
$catalogo = $pdo->query("SELECT * FROM suministros_generales WHERE stock_actual > 0 ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4">
        <div>
            <h1>📦 <?php echo htmlspecialchars($campana['titulo']); ?></h1>
            <span class="badge bg-success">Cobertura: <?php echo $campana['frecuencia_cobertura']; ?></span>
            <?php if($vencida): ?>
                <span class="badge bg-danger">VENCIDA (Cerró el <?php echo date('d/m', strtotime($campana['fecha_fin'])); ?>)</span>
            <?php else: ?>
                <span class="badge bg-success">ABIERTA hasta <?php echo date('d/m/Y', strtotime($campana['fecha_fin'])); ?></span>
            <?php endif; ?>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Salir</a>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg']=='fin'): ?>
        <div class="alert alert-success mt-4">✅ <strong>¡Pedido Finalizado!</strong> Tu solicitud ha sido enviada.</div>
    <?php elseif($finalizado): ?>
        <div class="alert alert-info mt-4">ℹ️ Ya has finalizado y enviado tu pedido. Solo lectura.</div>
    <?php elseif($vencida): ?>
        <div class="alert alert-danger mt-4">⛔ Plazo de carga finalizado.</div>
    <?php endif; ?>

    <?php if (!$finalizado && !$vencida): ?>
    <form method="POST" id="formCampaña" class="mt-4">
        <div class="card shadow-sm border-success">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong class="text-success">Mi Pedido Suministros (Borrador)</strong>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="addStock()">+ Catálogo</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="addManual()">+ Manual</button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 align-middle">
                    <thead class="table-light"><tr><th>Ítem</th><th width="150">Cantidad</th><th width="50"></th></tr></thead>
                    <tbody id="listaItems">
                        <?php 
                        $idx = 0;
                        foreach($items_actuales as $it): $idx++; 
                            $es_manual = empty($it['id_suministro']);
                        ?>
                        <tr class="<?php echo $es_manual ? 'table-warning' : ''; ?>">
                            <td>
                                <?php if(!$es_manual): ?>
                                    <select name="items[<?php echo $idx; ?>][id]" class="form-select">
                                        <?php foreach($catalogo as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id']==$it['id_suministro'])?'selected':''; ?>>
                                                <?php echo htmlspecialchars($cat['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" name="items[<?php echo $idx; ?>][detalle]" class="form-control" value="<?php echo htmlspecialchars($it['detalle_personalizado']); ?>">
                                <?php endif; ?>
                            </td>
                            <td><input type="number" name="items[<?php echo $idx; ?>][cantidad]" class="form-control text-center fw-bold" value="<?php echo $it['cantidad_solicitada']; ?>"></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="empty" class="p-4 text-center text-muted" style="<?php echo ($idx>0)?'display:none':''; ?>">
                    Tu pedido está vacío. Comienza a agregar ítems.
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <button type="submit" name="accion" value="guardar_parcial" class="btn btn-secondary">
                    <i class="fas fa-save me-2"></i> Guardar Parcial
                </button>
                <button type="submit" name="accion" value="finalizar" class="btn btn-success fw-bold">
                    <i class="fas fa-check-circle me-2"></i> FINALIZAR Y ENVIAR
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<div id="opsCat" style="display:none;">
    <option value="">-- Seleccionar --</option>
    <?php foreach($catalogo as $c) echo "<option value='{$c['id']}'>".htmlspecialchars($c['nombre'])."</option>"; ?>
</div>

<script>
let count = <?php echo $idx; ?>;
function check(){ document.getElementById('empty').style.display = (document.getElementById('listaItems').children.length==0)?'block':'none'; }
function addStock(){ count++; document.getElementById('listaItems').insertAdjacentHTML('beforeend', `<tr><td><select name="items[${count}][id]" class="form-select">${document.getElementById('opsCat').innerHTML}</select></td><td><input type="number" name="items[${count}][cantidad]" class="form-control" value="1"></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();check()">X</button></td></tr>`); check(); }
function addManual(){ count++; document.getElementById('listaItems').insertAdjacentHTML('beforeend', `<tr class="table-warning"><td><input type="text" name="items[${count}][detalle]" class="form-control" placeholder="Descripción..."></td><td><input type="number" name="items[${count}][cantidad]" class="form-control" value="1"></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();check()">X</button></td></tr>`); check(); }
</script>
<?php include 'includes/footer.php'; ?>