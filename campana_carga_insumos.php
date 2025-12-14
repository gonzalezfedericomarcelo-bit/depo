<?php
// Archivo: campana_carga_insumos.php
// Propósito: Carga de Campaña con fecha/hora límite y edición continua.

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

if (!tienePermiso('solicitar_insumos')) { die("Acceso denegado."); }

$id_campana = $_GET['campana'] ?? 0;
if(!$id_campana) {
    // Listado de campañas
    $hoy = date('Y-m-d H:i:s');
    $campanas = $pdo->query("SELECT * FROM compras_planificaciones WHERE estado='abierta' AND tipo_insumo='insumos' AND fecha_fin >= '$hoy'")->fetchAll();
    echo "<div class='container px-4 mt-4'><h1>Seleccione Campaña</h1><div class='list-group mt-3'>";
    foreach($campanas as $c) {
        echo "<a href='?campana={$c['id']}' class='list-group-item list-group-item-action d-flex justify-content-between align-items-center'>
                <div><strong>{$c['titulo']}</strong><br><small class='text-muted'>Cobertura: {$c['frecuencia_cobertura']}</small></div>
                <span class='badge bg-danger'>Cierra: ".date('d/m/Y H:i', strtotime($c['fecha_fin']))."</span>
              </a>";
    }
    echo "</div></div>";
    include 'includes/footer.php'; exit;
}

// OBTENER DATOS CAMPAÑA
$campana = $pdo->query("SELECT * FROM compras_planificaciones WHERE id=$id_campana")->fetch();
if(!$campana) die("Campaña no existe");

// VERIFICAR VENCIMIENTO (Precisión Minuto)
$ahora = date('Y-m-d H:i:s');
$vencida = ($ahora > $campana['fecha_fin']);

// BUSCAR PEDIDO
$sqlPed = "SELECT * FROM pedidos_servicio WHERE id_planificacion = ? AND id_usuario_solicitante = ?";
$stmtPed = $pdo->prepare($sqlPed);
$stmtPed->execute([$id_campana, $_SESSION['user_id']]);
$pedido = $stmtPed->fetch();

// ESTADO FINALIZADO: Si existe y NO está en carga
// Si está 'en_carga', significa que aún es borrador editable
$finalizado = ($pedido && $pedido['estado'] != 'en_carga');

// PROCESAR GUARDADO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$vencida && !$finalizado) {
    try {
        $pdo->beginTransaction();
        $accion = $_POST['accion']; // 'guardar_parcial' o 'finalizar'
        
        if (!$pedido) {
            $stmtInsert = $pdo->prepare("INSERT INTO pedidos_servicio (tipo_insumo, proceso_origen, id_usuario_solicitante, servicio_solicitante, estado, id_planificacion) VALUES ('insumos_medicos', 'movimiento_insumos', ?, ?, 'en_carga', ?)");
            $stmtInsert->execute([$_SESSION['user_id'], $_SESSION['user_data']['servicio'], $id_campana]);
            $id_pedido = $pdo->lastInsertId();
        } else {
            $id_pedido = $pedido['id'];
        }

        // Reescribir items
        $pdo->prepare("DELETE FROM pedidos_items WHERE id_pedido = ?")->execute([$id_pedido]);
        $stmtItem = $pdo->prepare("INSERT INTO pedidos_items (id_pedido, id_insumo, detalle_personalizado, cantidad_solicitada) VALUES (?, ?, ?, ?)");
        
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $it) {
                if ($it['cantidad'] > 0) {
                    $id_stock = !empty($it['id']) ? $it['id'] : null;
                    $det = !empty($it['detalle']) ? $it['detalle'] : null;
                    $stmtItem->execute([$id_pedido, $id_stock, $det, $it['cantidad']]);
                }
            }
        }

        // Si finaliza, cambia estado y bloquea edición
        if ($accion == 'finalizar') {
            $primerPaso = $pdo->query("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_insumos' ORDER BY paso_orden ASC LIMIT 1")->fetch();
            $pdo->prepare("UPDATE pedidos_servicio SET estado = ?, paso_actual_id = ?, fecha_solicitud = NOW() WHERE id = ?")
                ->execute([$primerPaso['nombre_estado'], $primerPaso['id'], $id_pedido]);
            
            $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")
                ->execute([$primerPaso['id_rol_responsable'], "Pedido Campaña FINALIZADO: ".$_SESSION['user_data']['servicio'], "pedidos_ver.php?id=$id_pedido"]);
        } else {
            // Si es parcial, asegura que siga en 'en_carga'
            $pdo->prepare("UPDATE pedidos_servicio SET estado = 'en_carga' WHERE id = ?")->execute([$id_pedido]);
        }

        $pdo->commit();
        header("Location: campana_carga_insumos.php?campana=$id_campana&msg=" . ($accion=='finalizar'?'fin':'guardado'));
        exit;

    } catch (Exception $e) {
        $pdo->rollBack(); echo "<script>alert('Error: {$e->getMessage()}');</script>";
    }
}

// Cargar items para mostrar
$items_actuales = [];
if ($pedido) {
    $items_actuales = $pdo->query("SELECT * FROM pedidos_items WHERE id_pedido = " . $pedido['id'])->fetchAll();
}
$catalogo = $pdo->query("SELECT * FROM insumos_medicos WHERE stock_actual > 0 ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4">
        <div>
            <h1>📦 <?php echo htmlspecialchars($campana['titulo']); ?></h1>
            <span class="badge bg-primary">Cobertura: <?php echo $campana['frecuencia_cobertura']; ?></span>
            
            <?php if($vencida): ?>
                <span class="badge bg-danger">CERRADA</span>
            <?php else: ?>
                <span class="badge bg-success">ABIERTA hasta <?php echo date('d/m H:i', strtotime($campana['fecha_fin'])); ?> hs</span>
                
                <span class="badge bg-warning text-dark" id="countdown"></span>
            <?php endif; ?>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Salir</a>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg']=='fin'): ?>
        <div class="alert alert-success mt-4">✅ <strong>¡Enviado!</strong> Tu pedido ya fue procesado y no puede editarse.</div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg']=='guardado'): ?>
        <div class="alert alert-info mt-4">💾 <strong>Guardado Parcial.</strong> Puedes seguir editando hasta la fecha de cierre.</div>
    <?php elseif($finalizado): ?>
        <div class="alert alert-secondary mt-4">🔒 Ya enviaste este pedido. Solo lectura.</div>
    <?php elseif($vencida): ?>
        <div class="alert alert-danger mt-4">⛔ El tiempo de carga ha finalizado.</div>
    <?php endif; ?>

    <?php if (!$finalizado && !$vencida): ?>
    <form method="POST" class="mt-4">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-light d-flex justify-content-between">
                <strong class="text-primary">Mi Pedido (Editable)</strong>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addStock()">+ Catálogo</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="addManual()">+ Manual</button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-light"><tr><th>Ítem</th><th width="150">Cantidad</th><th width="50"></th></tr></thead>
                    <tbody id="listaItems">
                        <?php 
                        $idx = 0;
                        foreach($items_actuales as $it): $idx++; 
                            $es_manual = empty($it['id_insumo']);
                        ?>
                        <tr>
                            <td>
                                <?php if(!$es_manual): ?>
                                    <select name="items[<?php echo $idx; ?>][id]" class="form-select">
                                        <?php foreach($catalogo as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id']==$it['id_insumo'])?'selected':''; ?>>
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
            </div>
            <div class="card-footer d-flex justify-content-between">
                <button type="submit" name="accion" value="guardar_parcial" class="btn btn-secondary">
                    <i class="fas fa-save me-2"></i> Guardar y Continuar Luego
                </button>
                <button type="submit" name="accion" value="finalizar" class="btn btn-success fw-bold">
                    <i class="fas fa-paper-plane me-2"></i> FINALIZAR Y ENVIAR
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
// Lógica de JS para agregar filas
let count = <?php echo $idx; ?>;
function addStock(){ count++; document.getElementById('listaItems').insertAdjacentHTML('beforeend', `<tr><td><select name="items[${count}][id]" class="form-select">${document.getElementById('opsCat').innerHTML}</select></td><td><input type="number" name="items[${count}][cantidad]" class="form-control" value="1"></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td></tr>`); }
function addManual(){ count++; document.getElementById('listaItems').insertAdjacentHTML('beforeend', `<tr><td><input type="text" name="items[${count}][detalle]" class="form-control" placeholder="Descripción..."></td><td><input type="number" name="items[${count}][cantidad]" class="form-control" value="1"></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td></tr>`); }

// Cuenta regresiva simple
const fechaFin = new Date("<?php echo $campana['fecha_fin']; ?>").getTime();
if (fechaFin > new Date().getTime()) {
    setInterval(() => {
        const now = new Date().getTime();
        const dist = fechaFin - now;
        if (dist < 0) { document.getElementById("countdown").innerText = "CERRADO"; return; }
        const days = Math.floor(dist / (1000 * 60 * 60 * 24));
        const hours = Math.floor((dist % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((dist % (1000 * 60 * 60)) / (1000 * 60));
        document.getElementById("countdown").innerText = `Quedan: ${days}d ${hours}h ${minutes}m`;
    }, 1000);
}
</script>
<?php include 'includes/footer.php'; ?>