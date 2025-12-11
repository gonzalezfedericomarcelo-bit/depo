<?php
// Archivo: pedidos_solicitud_interna.php
// Propósito: Iniciar flujo 'movimiento_insumos' (Sincronizado)
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php'; 
include 'includes/navbar.php';

// VERIFICACIÓN EXACTA DEL PERMISO DE BASE DE DATOS
if (!tienePermiso('solicitar_insumos')) {
    die("<div class='container mt-5'><div class='alert alert-danger shadow-sm'>
            <h4 class='alert-heading'><i class='fas fa-lock'></i> Acceso Restringido</h4>
            <p>No tienes permiso activado para: <strong>Solicitar Insumos Médicos</strong>.</p>
            <hr><p class='mb-0 small'>Ve a Admin Roles y activa 'Solicitar Insumos' para tu rol.</p>
         </div></div>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();

        // 1. Obtener primer paso del flujo
        $stmtFlujo = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_insumos' ORDER BY paso_orden ASC LIMIT 1");
        $stmtFlujo->execute();
        $primerPaso = $stmtFlujo->fetch();

        if (!$primerPaso) throw new Exception("El flujo 'movimiento_insumos' no está configurado.");

        // 2. Insertar Cabecera
        $sql = "INSERT INTO pedidos_servicio (tipo_insumo, proceso_origen, id_usuario_solicitante, servicio_solicitante, estado, paso_actual_id) 
                VALUES ('insumos_medicos', 'movimiento_insumos', :uid, :serv, :estado, :paso_id)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $_SESSION['user_id'], 
            ':serv' => $_SESSION['user_data']['servicio'] ?? 'Sin Servicio',
            ':estado' => $primerPaso['nombre_estado'], 
            ':paso_id' => $primerPaso['id']
        ]);
        $id_pedido = $pdo->lastInsertId();

        // 3. Insertar Ítems
        if (isset($_POST['insumo_id'])) {
            $stmtItem = $pdo->prepare("INSERT INTO pedidos_items (id_pedido, id_insumo, cantidad_solicitada) VALUES (:idp, :idi, :cant)");
            for ($i = 0; $i < count($_POST['insumo_id']); $i++) {
                if ($_POST['cantidad'][$i] > 0) {
                    $stmtItem->execute([
                        ':idp' => $id_pedido,
                        ':idi' => $_POST['insumo_id'][$i],
                        ':cant' => $_POST['cantidad'][$i]
                    ]);
                }
            }
        }

        // 4. Notificar
        $msj = "Nueva solicitud de Insumos: " . ($_SESSION['user_data']['servicio'] ?? 'Usuario');
        $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?, ?, ?)")
            ->execute([$primerPaso['id_rol_responsable'], $msj, "pedidos_ver.php?id=" . $id_pedido]);

        $pdo->commit();
        echo "<script>window.location='dashboard.php?msg=solicitud_enviada';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger m-4'>Error: ".$e->getMessage()."</div>";
    }
}

$insumos = $pdo->query("SELECT * FROM insumos_medicos WHERE stock_actual > 0 ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Solicitar Insumos Médicos</h1>
    <form method="POST">
        <div class="card mb-4 shadow-sm border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between">
                <span>Selección de Productos</span>
                <button type="button" class="btn btn-light btn-sm text-dark fw-bold" onclick="agregarFila()">+ Agregar</button>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-light"><tr><th>Insumo</th><th width="150">Cantidad</th><th width="50"></th></tr></thead>
                    <tbody id="bodyInsumos"></tbody>
                </table>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary fw-bold btn-lg">Enviar Solicitud</button>
            </div>
        </div>
    </form>
</div>

<div id="insumoOptions" style="display:none;">
    <?php foreach($insumos as $in) { echo "<option value='".$in['id']."'>".htmlspecialchars($in['nombre'])." (Disp: ".$in['stock_actual'].")</option>"; } ?>
</div>

<script>
function agregarFila() {
    var tbody = document.getElementById('bodyInsumos');
    var row = document.createElement('tr');
    row.innerHTML = `<td><select name="insumo_id[]" class="form-select">${document.getElementById('insumoOptions').innerHTML}</select></td><td><input type="number" name="cantidad[]" class="form-control" placeholder="0" min="1"></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>`;
    tbody.appendChild(row);
}
window.onload = agregarFila;
</script>
<?php include 'includes/footer.php'; ?>