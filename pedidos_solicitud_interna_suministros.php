<?php
// Archivo: pedidos_solicitud_interna_suministros.php
// Propósito: Iniciar flujo 'movimiento_suministros' (Sincronizado)
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// VERIFICACIÓN EXACTA DEL PERMISO
if (!tienePermiso('solicitar_suministros')) {
    die("<div class='container mt-5'><div class='alert alert-danger shadow-sm'>
            <h4 class='alert-heading'><i class='fas fa-lock'></i> Acceso Restringido</h4>
            <p>No tienes permiso activado para: <strong>Solicitar Suministros Generales</strong>.</p>
            <hr><p class='mb-0 small'>Ve a Admin Roles y activa 'Solicitar Suministros' para tu rol.</p>
         </div></div>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();

        $stmtFlujo = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_suministros' ORDER BY paso_orden ASC LIMIT 1");
        $stmtFlujo->execute();
        $primerPaso = $stmtFlujo->fetch();

        if (!$primerPaso) throw new Exception("El flujo 'movimiento_suministros' no está configurado.");

        $sql = "INSERT INTO pedidos_servicio (tipo_insumo, proceso_origen, id_usuario_solicitante, servicio_solicitante, estado, paso_actual_id) 
                VALUES ('suministros', 'movimiento_suministros', :uid, :serv, :estado, :paso_id)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $_SESSION['user_id'], 
            ':serv' => $_SESSION['user_data']['servicio'] ?? 'Sin Servicio',
            ':estado' => $primerPaso['nombre_estado'], 
            ':paso_id' => $primerPaso['id']
        ]);
        $id_pedido = $pdo->lastInsertId();

        if (isset($_POST['suministro_id'])) {
            $stmtItem = $pdo->prepare("INSERT INTO pedidos_items (id_pedido, id_suministro, cantidad_solicitada) VALUES (:idp, :ids, :cant)");
            for ($i = 0; $i < count($_POST['suministro_id']); $i++) {
                if ($_POST['cantidad'][$i] > 0) {
                    $stmtItem->execute([':idp' => $id_pedido, ':ids' => $_POST['suministro_id'][$i], ':cant' => $_POST['cantidad'][$i]]);
                }
            }
        }

        $msj = "Nueva solicitud Suministros: " . ($_SESSION['user_data']['servicio'] ?? 'Usuario');
        $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?, ?, ?)")
            ->execute([$primerPaso['id_rol_responsable'], $msj, "pedidos_ver.php?id=" . $id_pedido]);

        $pdo->commit();
        echo "<script>window.location='dashboard.php?msg=solicitud_enviada';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger m-4'>Error: ".$e->getMessage()."</div>";
    }
}

$suministros = $pdo->query("SELECT * FROM suministros_generales WHERE stock_actual > 0 ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Solicitar Suministros Generales</h1>
    <form method="POST">
        <div class="card mb-4 shadow-sm border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between">
                <span>Selección de Artículos</span>
                <button type="button" class="btn btn-light btn-sm text-dark fw-bold" onclick="agregarFila()">+ Agregar</button>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-light"><tr><th>Artículo</th><th width="150">Cantidad</th><th width="50"></th></tr></thead>
                    <tbody id="bodyItems"></tbody>
                </table>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-warning fw-bold btn-lg">Enviar Solicitud</button>
            </div>
        </div>
    </form>
</div>

<div id="itemOptions" style="display:none;">
    <?php foreach($suministros as $s) { echo "<option value='".$s['id']."'>".htmlspecialchars($s['nombre'])." (Disp: ".$s['stock_actual'].")</option>"; } ?>
</div>

<script>
function agregarFila() {
    var tbody = document.getElementById('bodyItems');
    var row = document.createElement('tr');
    row.innerHTML = `<td><select name="suministro_id[]" class="form-select">${document.getElementById('itemOptions').innerHTML}</select></td><td><input type="number" name="cantidad[]" class="form-control" placeholder="0" min="1"></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>`;
    tbody.appendChild(row);
}
window.onload = agregarFila;
</script>
<?php include 'includes/footer.php'; ?>