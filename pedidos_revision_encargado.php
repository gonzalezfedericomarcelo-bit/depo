<?php
// Archivo: pedidos_revision_encargado.php
// Propósito: Paso 1 - Encargado Insumos revisa y deriva al Director Médico
require 'db.php';
session_start();

$roles = $_SESSION['user_roles'] ?? [];
if (!in_array('Encargado Depósito Insumos', $roles) && !in_array('Administrador', $roles)) {
    die("Acceso denegado.");
}

$id_pedido = $_GET['id'] ?? 0;
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();
        
        // 1. Actualizar cantidades aprobadas (pre-filtro del encargado)
        foreach ($_POST['aprobado'] as $id_item => $cant_aprob) {
            $pdo->prepare("UPDATE pedidos_items SET cantidad_aprobada = :cant WHERE id = :id")
                ->execute([':cant' => $cant_aprob, ':id' => $id_item]);
        }

        // 2. Buscar Paso 2 (revision_director)
        $stmtFlujo = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_insumos' AND nombre_estado = 'revision_director' LIMIT 1");
        $stmtFlujo->execute();
        $siguiente = $stmtFlujo->fetch();

        // 3. Avanzar flujo
        $sql = "UPDATE pedidos_servicio SET estado = :est, paso_actual_id = :pid WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':est' => $siguiente['nombre_estado'], ':pid' => $siguiente['id'], ':id' => $id_pedido]);

        // 4. Notificar al Director Médico
        $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?, ?, ?)")
            ->execute([$siguiente['id_rol_responsable'], "Solicitud Insumos revisada por Encargado (ID #$id_pedido). Requiere su aprobación.", "pedidos_ver.php?id=" . $id_pedido]);

        $pdo->commit();
        header("Location: dashboard.php?msg=enviado_director");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>";
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$pedido = $pdo->query("SELECT p.*, u.nombre_completo FROM pedidos_servicio p JOIN usuarios u ON p.id_usuario_solicitante = u.id WHERE p.id = $id_pedido")->fetch();
$items = $pdo->query("SELECT pi.*, im.nombre, im.stock_actual FROM pedidos_items pi JOIN insumos_medicos im ON pi.id_insumo = im.id WHERE pi.id_pedido = $id_pedido")->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Revisión Inicial (Encargado) #<?php echo $id_pedido; ?></h1>
    <h5 class="text-primary"><?php echo htmlspecialchars($pedido['servicio_solicitante']); ?></h5>
    <?php echo $mensaje; ?>

    <form method="POST">
        <div class="card mb-4 mt-3 border-primary">
            <div class="card-header bg-primary text-white">Verificación de Stock y Cantidades</div>
            <div class="card-body">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr><th>Insumo</th><th>Stock Depósito</th><th>Solicitado</th><th>Aprobar para Director</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it['nombre']); ?></td>
                            <td class="fw-bold"><?php echo $it['stock_actual']; ?></td>
                            <td><?php echo $it['cantidad_solicitada']; ?></td>
                            <td>
                                <input type="number" name="aprobado[<?php echo $it['id']; ?>]" class="form-control fw-bold text-primary" value="<?php echo $it['cantidad_solicitada']; ?>" min="0">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary fw-bold">VALIDAR Y ENVIAR AL DIRECTOR</button>
            </div>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>