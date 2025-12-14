<?php
// Archivo: pedidos_solicitud_interna.php
// Propósito: Pedidos AUTÓNOMOS de Insumos (Normal/Urgente/Extra) - SIN CAMPAÑAS

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

if (!tienePermiso('solicitar_insumos')) {
    echo "<div class='container mt-5 alert alert-danger'>⛔ Acceso Restringido.</div>"; include 'includes/footer.php'; exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();
        
        // Flujo normal
        $primerPaso = $pdo->query("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_insumos' ORDER BY paso_orden ASC LIMIT 1")->fetch();
        if(!$primerPaso) throw new Exception("Flujo no configurado");

        // Insertar Cabecera (Sin id_planificacion)
        $sql = "INSERT INTO pedidos_servicio (tipo_insumo, proceso_origen, id_usuario_solicitante, servicio_solicitante, estado, paso_actual_id, prioridad, frecuencia_compra) 
                VALUES ('insumos_medicos', 'movimiento_insumos', :uid, :serv, :est, :paso, :prio, :freq)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $_SESSION['user_id'], 
            ':serv'=> $_SESSION['user_data']['servicio'], 
            ':est' => $primerPaso['nombre_estado'], 
            ':paso'=> $primerPaso['id'],
            ':prio'=> $_POST['prioridad'],
            ':freq'=> ($_POST['prioridad'] == 'Normal') ? $_POST['frecuencia'] : null // Solo si es normal aplica frecuencia reposición
        ]);
        $id_pedido = $pdo->lastInsertId();

        // Items
        $stmtItem = $pdo->prepare("INSERT INTO pedidos_items (id_pedido, id_insumo, detalle_personalizado, cantidad_solicitada) VALUES (:id, :ids, :det, :cant)");
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $it) {
                if ($it['cantidad'] > 0) {
                    $id_sum = !empty($it['id']) ? $it['id'] : null;
                    $det = !empty($it['detalle']) ? $it['detalle'] : null;
                    $stmtItem->execute([':id'=>$id_pedido, ':ids'=>$id_sum, ':det'=>$det, ':cant'=>$it['cantidad']]);
                }
            }
        }

        // Notificar Encargado
        $msg = "Nuevo Pedido Insumos (" . $_POST['prioridad'] . "): " . $_SESSION['user_data']['servicio'];
        $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")
            ->execute([$primerPaso['id_rol_responsable'], $msg, "pedidos_ver.php?id=$id_pedido"]);

        $pdo->commit();
        echo "<script>window.location='dashboard.php?msg=pedido_enviado';</script>";

    } catch (Exception $e) { $pdo->rollBack(); echo "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>"; }
}

$insumos = $pdo->query("SELECT * FROM insumos_medicos WHERE stock_actual > 0 ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4 text-primary">Solicitud de Stock (Autónoma)</h1>
    <p class="text-muted">Utilice este formulario para pedidos de reposición diaria, urgencias o extraordinarios.</p>
    
    <form method="POST">
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white fw-bold">1. Datos del Pedido</div>
            <div class="card-body">
                <label class="fw-bold d-block mb-2">Prioridad:</label>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="prioridad" id="p_norm" value="Normal" checked onclick="toggleFreq(true)">
                    <label class="btn btn-outline-primary" for="p_norm">Normal (Reposición)</label>

                    <input type="radio" class="btn-check" name="prioridad" id="p_urg" value="Urgente" onclick="toggleFreq(false)">
                    <label class="btn btn-outline-warning text-dark fw-bold" for="p_urg">🔥 Urgente</label>

                    <input type="radio" class="btn-check" name="prioridad" id="p_ext" value="Extraordinaria" onclick="toggleFreq(false)">
                    <label class="btn btn-outline-danger fw-bold" for="p_ext">⚠️ Extraordinaria</label>
                </div>

                <div class="mt-3" id="divFreq">
                    <label class="fw-bold">Frecuencia de Uso:</label>
                    <select name="frecuencia" class="form-select w-auto d-inline-block">
                        <option>Diaria</option><option>Semanal</option><option>Mensual</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card mb-4 border-primary shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between">
                <span class="fw-bold text-primary">2. Ítems a Solicitar</span>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRowStock()">+ Catálogo</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="addRowManual()">+ Manual</button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 align-middle"><tbody id="bodyItems"></tbody></table>
                <div id="empty-msg" class="text-center p-4 text-muted">Agregue ítems al pedido.</div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary btn-lg fw-bold px-5">ENVIAR AL DEPÓSITO</button>
            </div>
        </div>
    </form>
</div>

<div id="opts" style="display:none;"><option value="">-- Seleccionar --</option><?php foreach($insumos as $s) echo "<option value='{$s['id']}'>".htmlspecialchars($s['nombre'])." (Disp: {$s['stock_actual']})</option>"; ?></div>

<script>
function toggleFreq(show) { document.getElementById('divFreq').style.display = show ? 'block' : 'none'; }
let rc = 0;
function check(){ document.getElementById('empty-msg').style.display = (document.getElementById('bodyItems').children.length === 0) ? 'block' : 'none'; }
function addRowStock() { rc++; document.getElementById('bodyItems').insertAdjacentHTML('beforeend', `<tr><td><select name="items[${rc}][id]" class="form-select select-search">${document.getElementById('opts').innerHTML}</select></td><td width="120"><input type="number" name="items[${rc}][cantidad]" class="form-control text-center fw-bold" placeholder="Cant" min="1"></td><td width="50"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();check()">X</button></td></tr>`); check(); }
function addRowManual() { rc++; document.getElementById('bodyItems').insertAdjacentHTML('beforeend', `<tr class="table-warning"><td><input type="text" name="items[${rc}][detalle]" class="form-control" placeholder="Escriba el insumo..." required></td><td width="120"><input type="number" name="items[${rc}][cantidad]" class="form-control text-center fw-bold" placeholder="Cant" min="1"></td><td width="50"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();check()">X</button></td></tr>`); check(); }
</script>
<?php include 'includes/footer.php'; ?>