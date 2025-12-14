<?php
// Archivo: insumos_entregas.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$es_admin = in_array('Administrador', $_SESSION['user_roles'] ?? []);

if (!tienePermiso('ver_entregas_insumos') && !tienePermiso('realizar_entrega_insumos') && !$es_admin) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>⛔ Acceso Denegado.</div></div>";
    include 'includes/footer.php'; exit;
}

// BORRAR ENTREGA
if (isset($_POST['eliminar_id']) && $es_admin) {
    try {
        $pdo->prepare("DELETE FROM entregas WHERE id = ?")->execute([$_POST['eliminar_id']]);
        echo "<script>window.location='insumos_entregas.php?msg=eliminado';</script>";
    } catch (Exception $e) { echo "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>"; }
}

$sql = "SELECT e.*, u.nombre_completo as responsable 
        FROM entregas e 
        JOIN usuarios u ON e.id_usuario_responsable = u.id 
        WHERE e.tipo_origen = 'insumos' 
        ORDER BY e.fecha_entrega DESC";
$entregas = $pdo->query($sql)->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Entregas de Insumos Médicos</h1>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="fas fa-truck-loading me-1"></i> Historial de Salidas</div>
            <?php if (tienePermiso('realizar_entrega_insumos') || $es_admin): ?>
                <a href="insumos_entrega_nueva.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nueva Entrega / Retiro</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light"><tr><th>ID</th><th>Fecha</th><th>Solicitante</th><th>Área</th><th>Entregado Por</th><th class="text-center">Acciones</th></tr></thead>
                    <tbody>
                        <?php if (count($entregas) > 0): ?>
                            <?php foreach ($entregas as $ent): ?>
                            <tr>
                                <td>#<?php echo $ent['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($ent['fecha_entrega'])); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($ent['solicitante_nombre']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($ent['solicitante_area']); ?></span></td>
                                <td><?php echo htmlspecialchars($ent['responsable']); ?></td>
                                <td class="text-center">
                                    <a href="generar_pdf_entrega.php?id=<?php echo $ent['id']; ?>" target="_blank" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i></a>
                                    <?php if($es_admin): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('ADMIN: ¿Eliminar esta entrega?');">
                                            <input type="hidden" name="eliminar_id" value="<?php echo $ent['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger ms-1"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted">No hay entregas registradas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>