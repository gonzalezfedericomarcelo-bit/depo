<?php
// Archivo: suministros_planificacion_detalle.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$id_plan = $_GET['id'];
$plan = $pdo->query("SELECT * FROM compras_planificaciones WHERE id=$id_plan")->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // LOGÍSTICA CIERRA
    if (isset($_POST['cerrar_logistica'])) {
        $pdo->prepare("UPDATE compras_planificaciones SET estado='cerrada_logistica' WHERE id=?")->execute([$id_plan]);
        
        $rolDir = obtenerIdRolPorPermiso('aprobar_planificacion_director'); // NO HARDCODE
        if($rolDir) {
            $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")
                ->execute([$rolDir, "Planificación requiere aprobación: ".$plan['titulo'], "suministros_planificacion_detalle.php?id=$id_plan"]);
        }
        echo "<script>window.location.href='suministros_planificacion_detalle.php?id=$id_plan';</script>"; exit;
    }
    
    // DIRECTOR APRUEBA
    if (isset($_POST['aprobar_director']) && tienePermiso('aprobar_planificacion_director')) {
        $pdo->prepare("UPDATE compras_planificaciones SET estado='aprobada_director' WHERE id=?")->execute([$id_plan]);
        
        $rolComp = obtenerIdRolPorPermiso('procesar_compra_precios'); // NO HARDCODE
        if($rolComp) {
            $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")
                ->execute([$rolComp, "Planificación aprobada para comprar: ".$plan['titulo'], "suministros_planificacion_panel.php"]);
        }
        echo "<script>window.location.href='suministros_planificacion_detalle.php?id=$id_plan';</script>"; exit;
    }
}

// CONSOLIDADO (Stock + Manuales)
$sqlCons = "SELECT COALESCE(s.nombre, pi.detalle_personalizado) as nombre, s.codigo, SUM(pi.cantidad_solicitada) as total, IF(s.id IS NULL, 1, 0) as es_manual FROM pedidos_items pi JOIN pedidos_servicio ps ON pi.id_pedido=ps.id LEFT JOIN suministros_generales s ON pi.id_suministro=s.id WHERE ps.id_planificacion=:id GROUP BY nombre";
$consolidado = $pdo->prepare($sqlCons);
$consolidado->execute([':id'=>$id_plan]);
$lista = $consolidado->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Detalle: <?php echo htmlspecialchars($plan['titulo']); ?></h1>
    <div class="card mb-4 p-3 bg-light border">
        <div class="d-flex justify-content-between align-items-center">
            <span class="badge bg-dark fs-6"><?php echo strtoupper($plan['estado']); ?></span>
            <form method="POST">
                <?php if($plan['estado'] == 'abierta' && tienePermiso('gestionar_planificaciones')): ?>
                    <button type="submit" name="cerrar_logistica" class="btn btn-warning fw-bold">Cerrar y Enviar a Director</button>
                <?php endif; ?>
                <?php if($plan['estado'] == 'cerrada_logistica' && tienePermiso('aprobar_planificacion_director')): ?>
                    <button type="submit" name="aprobar_director" class="btn btn-success fw-bold">Aprobar y Enviar a Compras</button>
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
                        <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                        <td class="text-center fw-bold fs-5"><?php echo $c['total']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>