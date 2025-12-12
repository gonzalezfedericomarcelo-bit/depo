<?php
// Archivo: insumos_planificacion_detalle.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$id_plan = $_GET['id'];
$plan = $pdo->query("SELECT * FROM compras_planificaciones WHERE id=$id_plan AND tipo_insumo='insumos'")->fetch();
if(!$plan) die("Plan no encontrado o incorrecto.");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ENCARGADO CIERRA
    if (isset($_POST['cerrar_encargado'])) {
        $pdo->prepare("UPDATE compras_planificaciones SET estado='cerrada_logistica' WHERE id=?")->execute([$id_plan]);
        $rolDir = obtenerIdRolPorPermiso('aprobar_planificacion_director');
        if($rolDir) $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")->execute([$rolDir, "Campaña Médica requiere aprobación: ".$plan['titulo'], "insumos_planificacion_detalle.php?id=$id_plan"]);
        echo "<script>window.location.href='insumos_planificacion_detalle.php?id=$id_plan';</script>"; exit;
    }
    // DIRECTOR APRUEBA
    if (isset($_POST['aprobar_director']) && tienePermiso('aprobar_planificacion_director')) {
        $pdo->prepare("UPDATE compras_planificaciones SET estado='aprobada_director' WHERE id=?")->execute([$id_plan]);
        $rolComp = obtenerIdRolPorPermiso('procesar_compra_precios');
        if($rolComp) $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")->execute([$rolComp, "Campaña Médica aprobada: ".$plan['titulo'], "insumos_gestion_compras.php?id=$id_plan"]);
        echo "<script>window.location.href='insumos_planificacion_detalle.php?id=$id_plan';</script>"; exit;
    }
}

// CONSOLIDADO (Insumos Médicos)
$sqlCons = "SELECT COALESCE(s.nombre, pi.detalle_personalizado) as nombre_item, s.codigo, SUM(pi.cantidad_solicitada) as total, IF(s.id IS NULL, 1, 0) as es_manual 
            FROM pedidos_items pi 
            JOIN pedidos_servicio ps ON pi.id_pedido = ps.id 
            LEFT JOIN insumos_medicos s ON pi.id_insumo = s.id 
            WHERE ps.id_planificacion = :id 
            GROUP BY nombre_item";
$consolidado = $pdo->prepare($sqlCons);
$consolidado->execute([':id' => $id_plan]);
$lista = $consolidado->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión Insumos: <?php echo htmlspecialchars($plan['titulo']); ?></h1>
    <div class="card mb-4 p-3 bg-light border">
        <div class="d-flex justify-content-between align-items-center">
            <span class="badge bg-dark fs-6"><?php echo strtoupper($plan['estado']); ?></span>
            <form method="POST">
                <?php if($plan['estado'] == 'abierta' && tienePermiso('gestionar_planificaciones_medicas')): ?>
                    <button type="submit" name="cerrar_encargado" class="btn btn-warning fw-bold">Cerrar y Enviar a Director</button>
                <?php endif; ?>
                <?php if($plan['estado'] == 'cerrada_logistica' && tienePermiso('aprobar_planificacion_director')): ?>
                    <button type="submit" name="aprobar_director" class="btn btn-success fw-bold">✅ Aprobar y Enviar a Compras</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="card shadow">
        <div class="card-header bg-primary text-white">Consolidado</div>
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead class="table-dark"><tr><th>Tipo</th><th>Insumo</th><th class="text-center">Total</th></tr></thead>
                <tbody>
                    <?php foreach($lista as $c): ?>
                    <tr>
                        <td><?php echo ($c['es_manual']) ? '<span class="badge bg-warning text-dark">NUEVO</span>' : '<span class="badge bg-success">STOCK</span>'; ?></td>
                        <td><?php echo htmlspecialchars($c['nombre_item']); ?></td>
                        <td class="text-center fw-bold fs-5"><?php echo $c['total']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>