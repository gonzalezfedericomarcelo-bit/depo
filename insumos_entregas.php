<?php
// Archivo: insumos_entregas.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$roles = $_SESSION['user_roles'] ?? [];
$puede_entregar = in_array('Administrador', $roles) || in_array('Encargado Depósito Insumos', $roles) || in_array('Auxiliar', $roles);

$sql = "SELECT e.*, u.nombre_completo as responsable 
        FROM entregas e 
        JOIN usuarios u ON e.id_usuario_responsable = u.id 
        WHERE e.tipo_origen = 'insumos' 
        ORDER BY e.fecha_entrega DESC";
$entregas = $pdo->query($sql)->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Entregas de Insumos</h1>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'exito' && isset($_GET['new_id'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow mb-4" role="alert">
        <h4 class="alert-heading"><i class="fas fa-check-circle"></i> ¡Entrega Exitosa!</h4>
        <p>La entrega se registró correctamente en el sistema y se descontó el stock.</p>
        <hr>
        <div class="d-flex justify-content-between align-items-center">
            <span>Puedes descargar el comprobante firmado aquí:</span>
            <a href="generar_pdf_entrega.php?id=<?php echo $_GET['new_id']; ?>" target="_blank" class="btn btn-dark fw-bold">
                <i class="fas fa-file-pdf me-2"></i> DESCARGAR COMPROBANTE PDF
            </a>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="fas fa-truck-loading me-1"></i> Historial de Salidas</div>
            <?php if ($puede_entregar): ?>
                <a href="insumos_entrega_nueva.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nueva Entrega / Retiro</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr><th>ID</th><th>Fecha</th><th>Solicitante</th><th>Área</th><th>Entregado Por</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entregas as $ent): ?>
                        <tr>
                            <td><?php echo $ent['id']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($ent['fecha_entrega'])); ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($ent['solicitante_nombre']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($ent['solicitante_area']); ?></span></td>
                            <td><?php echo htmlspecialchars($ent['responsable']); ?></td>
                            <td class="text-center">
                                <a href="generar_pdf_entrega.php?id=<?php echo $ent['id']; ?>" target="_blank" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>