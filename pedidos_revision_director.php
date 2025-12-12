<?php
// Archivo: pedidos_revision_director.php
// Propósito: Paso 2 - Director aprueba y devuelve al Encargado (Paso 3)
require 'db.php';
session_start();

$roles = $_SESSION['user_roles'] ?? [];
if (!in_array('Director Médico', $roles) && !in_array('Administrador', $roles)) {
    die("Acceso denegado.");
}

$id_pedido = $_GET['id'] ?? 0;
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();
        $obs = $_POST['observaciones_director'];
        
        // 1. Actualizar cantidades finales (Decisión médica)
        foreach ($_POST['aprobado'] as $id_item => $cant_aprob) {
            $pdo->prepare("UPDATE pedidos_items SET cantidad_aprobada = :cant WHERE id = :id")
                ->execute([':cant' => $cant_aprob, ':id' => $id_item]);
        }

        // 2. Buscar Paso 3 (pendiente_preparacion)
        $stmtFlujo = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_insumos' AND nombre_estado = 'pendiente_preparacion' LIMIT 1");
        $stmtFlujo->execute();
        $siguiente = $stmtFlujo->fetch();

        // 3. Actualizar Cabecera
        $sql = "UPDATE pedidos_servicio SET 
                estado = :est, 
                paso_actual_id = :pid,
                fecha_aprobacion_director = NOW(), 
                id_director_aprobador = :dir, 
                observaciones_director = :obs 
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':est' => $siguiente['nombre_estado'], ':pid' => $siguiente['id'], ':dir' => $_SESSION['user_id'], ':obs' => $obs, ':id' => $id_pedido]);

        // 4. NOTIFICACIONES
        
        // A. Al Usuario: "Aprobado, espere preparación"
        $stmtUsr = $pdo->prepare("SELECT id_usuario_solicitante FROM pedidos_servicio WHERE id = ?");
        $stmtUsr->execute([$id_pedido]);
        $id_solicitante = $stmtUsr->fetchColumn();
        
        $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, mensaje, url_destino) VALUES (?, ?, ?)")
            ->execute([$id_solicitante, "✅ Tu pedido fue aprobado por el Director Médico. Está en espera de preparación.", "pedidos_ver.php?id=" . $id_pedido]);

        // B. Al Encargado de Insumos: "Proceda al despacho"
        $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?, ?, ?)")
            ->execute([$siguiente['id_rol_responsable'], "Director Médico aprobó pedido #$id_pedido. Proceder con el movimiento.", "pedidos_ver.php?id=" . $id_pedido]);

        $pdo->commit();
        header("Location: dashboard.php?msg=aprobado_director");
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
    <h1 class="mt-4">Aprobación Director Médico #<?php echo $id_pedido; ?></h1>
    <h4 class="text-primary"><?php echo htmlspecialchars($pedido['servicio_solicitante']); ?></h4>
    <?php echo $mensaje; ?>

    <form method="POST">
        <div class="card mb-4 mt-3 shadow">
            <div class="card-header bg-success text-white fw-bold">Decisión Final de Cantidades</div>
            <div class="card-body">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr><th>Insumo</th><th>Solicitado</th><th>Autorizado</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it['nombre']); ?></td>
                            <td><?php echo $it['cantidad_solicitada']; ?></td>
                            <td>
                                <input type="number" name="aprobado[<?php echo $it['id']; ?>]" class="form-control fw-bold text-success" value="<?php echo $it['cantidad_aprobada'] ?? $it['cantidad_solicitada']; ?>" min="0">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="mt-3">
                    <label>Observaciones:</label>
                    <textarea name="observaciones_director" class="form-control"></textarea>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success fw-bold">APROBAR Y NOTIFICAR</button>
            </div>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>