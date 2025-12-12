<?php
// Archivo: pedidos_solicitud_interna.php (Insumos Médicos)
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

if (!tienePermiso('solicitar_insumos')) {
    echo "<div class='container mt-5 alert alert-danger'>⛔ Acceso Restringido.</div>"; include 'includes/footer.php'; exit;
}

// BUSCAR CAMPAÑAS INSUMOS
$hoy = date('Y-m-d');
$stmtCamp = $pdo->prepare("SELECT * FROM compras_planificaciones WHERE estado = 'abierta' AND fecha_fin >= :hoy AND tipo_insumo = 'insumos'");
$stmtCamp->execute([':hoy' => $hoy]);
$campanas = $stmtCamp->fetchAll();

// VALIDAR DUPLICADOS
$ya_solicito = false;
$id_campana = $_GET['campana'] ?? null;
if ($id_campana) {
    $stmtCheck = $pdo->prepare("SELECT id FROM pedidos_servicio WHERE id_planificacion = ? AND id_usuario_solicitante = ?");
    $stmtCheck->execute([$id_campana, $_SESSION['user_id']]);
    if ($stmtCheck->fetch()) $ya_solicito = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$ya_solicito) {
    try {
        $pdo->beginTransaction();
        $primerPaso = $pdo->query("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_insumos' ORDER BY paso_orden ASC LIMIT 1")->fetch();
        $id_plan_post = !empty($_POST['id_planificacion']) ? $_POST['id_planificacion'] : null;

        $sql = "INSERT INTO pedidos_servicio (tipo_insumo, proceso_origen, id_usuario_solicitante, servicio_solicitante, estado, paso_actual_id, id_planificacion) VALUES ('insumos_medicos', 'movimiento_insumos', :uid, :serv, :est, :paso, :plan)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid'=>$_SESSION['user_id'], ':serv'=>$_SESSION['user_data']['servicio'], ':est'=>$primerPaso['nombre_estado'], ':paso'=>$primerPaso['id'], ':plan'=>$id_plan_post]);
        $id_pedido = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("INSERT INTO pedidos_items (id_pedido, id_insumo, detalle_personalizado, cantidad_solicitada) VALUES (:id, :ids, :det, :cant)");
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $it) {
                $id_sum = !empty($it['id']) ? $it['id'] : null;
                $det = !empty($it['detalle']) ? $it['detalle'] : null;
                if ($it['cantidad'] > 0) $stmtItem->execute([':id'=>$id_pedido, ':ids'=>$id_sum, ':det'=>$det, ':cant'=>$it['cantidad']]);
            }
        }

        $msg = "Nueva Solicitud Insumos: ".$_SESSION['user_data']['servicio'];
        if ($id_plan_post) $msg .= " (Campaña)";
        $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")->execute([$primerPaso['id_rol_responsable'], $msg, "pedidos_ver.php?id=$id_pedido"]);

        $pdo->commit();
        echo "<script>window.location='dashboard.php?msg=ok';</script>";
    } catch (Exception $e) { $pdo->rollBack(); echo "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>"; }
}

$insumos = $pdo->query("SELECT * FROM insumos_medicos WHERE stock_actual > 0 ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Solicitud Insumos Médicos</h1>
    
    <form method="GET" id="formCampana">
        <?php if(count($campanas) > 0): ?>
        <div class="alert alert-info shadow-sm mb-4 border-info">
            <label class="fw-bold"><i class="fas fa-bullhorn me-2"></i> Campañas Médicas Activas:</label>
            <select name="campana" class="form-select fw-bold border-info mt-1" onchange="document.getElementById('formCampana').submit()">
                <option value="">-- Pedido Normal (Urgente) --</option>
                <?php foreach($campanas as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($id_campana == $c['id'])?'selected':''; ?>><?php echo htmlspecialchars($c['titulo']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($ya_solicito): ?>
        <div class="alert alert-success text-center p-5"><h4>¡Ya enviaste tu pedido para esta campaña!</h4></div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="id_planificacion" value="<?php echo $id_campana; ?>">
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <span>Armar Pedido</span>
                    <div>
                        <button type="button" class="btn btn-sm btn-light text-primary fw-bold me-2" onclick="addRowStock()">+ Catálogo</button>
                        <button type="button" class="btn btn-sm btn-dark text-info fw-bold" onclick="addRowManual()">+ Manual</button>
                    </div>
                </div>
                <div class="card-body p-0"><table class="table table-striped mb-0"><tbody id="bodyItems"></tbody></table>
                <div id="empty-msg" class="text-center p-5 text-muted">Utiliza los botones de arriba para agregar insumos.</div>
                </div>
                <div class="card-footer text-end"><button type="submit" class="btn btn-primary fw-bold">Enviar</button></div>
            </div>
        </form>
    <?php endif; ?>
</div>

<div id="opts" style="display:none;"><option value="">-- Seleccionar --</option><?php foreach($insumos as $s) echo "<option value='{$s['id']}'>{$s['nombre']}</option>"; ?></div>
<script>
let rc = 0;
const msg = document.getElementById('empty-msg');
function check(){ msg.style.display = (document.getElementById('bodyItems').children.length === 0) ? 'block' : 'none'; }
function addRowStock() { rc++; document.getElementById('bodyItems').insertAdjacentHTML('beforeend', `<tr><td><select name="items[${rc}][id]" class="form-select" required>${document.getElementById('opts').innerHTML}</select></td><td width="100"><input type="number" name="items[${rc}][cantidad]" class="form-control" value="1"></td><td width="50"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();check()">X</button></td></tr>`); check(); }
function addRowManual() { rc++; document.getElementById('bodyItems').insertAdjacentHTML('beforeend', `<tr class="table-info"><td><input type="text" name="items[${rc}][detalle]" class="form-control" placeholder="Insumo Especial..." required></td><td width="100"><input type="number" name="items[${rc}][cantidad]" class="form-control" value="1"></td><td width="50"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();check()">X</button></td></tr>`); check(); }
check();
</script>
<?php include 'includes/footer.php'; ?>