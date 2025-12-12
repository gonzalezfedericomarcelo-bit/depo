<?php
// Archivo: suministros_compras.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$ver_todas = tienePermiso('ver_oc_suministros_todas');
$ver_propias = tienePermiso('ver_oc_suministros_propias');

if (!$ver_todas && !$ver_propias) { echo "Acceso Denegado"; include 'includes/footer.php'; exit; }

$busqueda = $_GET['q'] ?? '';

$sql = "SELECT oc.*, u.nombre_completo as creador FROM ordenes_compra oc JOIN usuarios u ON oc.id_usuario_creador = u.id WHERE oc.tipo_origen = 'suministros'";
$params = [];

if (!$ver_todas && $ver_propias) {
    $sql .= " AND oc.servicio_destino = :serv";
    $params[':serv'] = $_SESSION['user_data']['servicio'];
}
if (!empty($busqueda)) {
    $sql .= " AND oc.numero_oc LIKE :q";
    $params[':q'] = "%$busqueda%";
}
$sql .= " ORDER BY oc.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Órdenes de Compra (Suministros)</h1>

    <div class="card mb-4 bg-light border-0">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <form method="GET" class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Buscar por Nº OC..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <?php if (tienePermiso('crear_oc_suministros')): ?>
                        <a href="suministros_oc_crear.php" class="btn btn-success"><i class="fas fa-plus"></i> Nueva Orden</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body p-0">
             <table class="table table-bordered table-hover">
                <thead class="table-light"><tr><th>N° OC</th><th>Destino</th><th>Fecha</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($ordenes as $oc): ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars($oc['numero_oc']); ?></td>
                        <td><?php echo htmlspecialchars($oc['servicio_destino']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($oc['fecha_creacion'])); ?></td>
                        <td><?php echo $oc['estado']; ?></td>
                        <td><a href="suministros_oc_ver.php?id=<?php echo $oc['id']; ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
             </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>