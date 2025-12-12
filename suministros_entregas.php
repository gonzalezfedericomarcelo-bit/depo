<?php
// Archivo: suministros_entregas.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

if (!tienePermiso('ver_entregas_suministros') && !tienePermiso('realizar_entrega_suministros')) {
    echo "<div class='alert alert-danger m-4'>⛔ Acceso Denegado</div>"; include 'includes/footer.php'; exit;
}

$puede_entregar = tienePermiso('realizar_entrega_suministros');
$busqueda = $_GET['q'] ?? '';

$sql = "SELECT e.*, u.nombre_completo as responsable 
        FROM entregas e 
        JOIN usuarios u ON e.id_usuario_responsable = u.id 
        WHERE e.tipo_origen = 'suministros'";

$params = [];
if (!empty($busqueda)) {
    $sql .= " AND (e.id LIKE :q OR e.solicitante_nombre LIKE :q OR e.solicitante_area LIKE :q)";
    $params[':q'] = "%$busqueda%";
}
$sql .= " ORDER BY e.fecha_entrega DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entregas = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Entregas Suministros</h1>

    <div class="card mb-4 bg-light border-0">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <form method="GET" class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Buscar por ID, Solicitante o Área..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($puede_entregar): ?>
                        <a href="suministros_entrega_nueva.php" class="btn btn-success"><i class="fas fa-plus"></i> Nueva Entrega</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light"><tr><th>ID</th><th>Fecha</th><th>Solicitante</th><th>Área</th><th>Entregado Por</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($entregas as $ent): ?>
                        <tr>
                            <td>#<?php echo $ent['id']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($ent['fecha_entrega'])); ?></td>
                            <td><?php echo htmlspecialchars($ent['solicitante_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($ent['solicitante_area']); ?></td>
                            <td><?php echo htmlspecialchars($ent['responsable']); ?></td>
                            <td class="text-center"><a href="generar_pdf_entrega_suministros.php?id=<?php echo $ent['id']; ?>" target="_blank" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>