<?php
// Archivo: insumos_compras.php
// Propósito: Listado de OC Insumos con Buscador y Filtros

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// 1. DETECCIÓN DE PERMISOS
$ver_todas = tienePermiso('ver_oc_insumos_todas');
$ver_propias = tienePermiso('ver_oc_insumos_propias');

if (!$ver_todas && !$ver_propias) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>⛔ Acceso Denegado.</div></div>";
    include 'includes/footer.php'; exit;
}

// 2. PARÁMETROS DE BÚSQUEDA
$busqueda = $_GET['q'] ?? '';

// 3. CONSTRUCCIÓN DE CONSULTA
$sql = "SELECT oc.*, u.nombre_completo as creador 
        FROM ordenes_compra oc 
        JOIN usuarios u ON oc.id_usuario_creador = u.id 
        WHERE oc.tipo_origen = 'insumos'";

$params = [];

// Filtro de Visibilidad (Si no puede ver todas, ve solo las de su servicio)
if (!$ver_todas && $ver_propias) {
    $sql .= " AND oc.servicio_destino = :serv";
    $params[':serv'] = $_SESSION['user_data']['servicio'];
}

// Filtro de Búsqueda (N° OC)
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
    <h1 class="mt-4">Órdenes de Compra (Insumos Médicos)</h1>
    
    <div class="card mb-4 bg-light border-0 shadow-sm">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <form method="GET" class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Buscar por Nº OC..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <?php if (tienePermiso('gestion_compras_insumos')): ?>
                        <a href="insumos_oc_crear.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-plus me-1"></i> Nueva Orden
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>N° OC</th>
                            <th>Destino</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ordenes) > 0): ?>
                            <?php foreach ($ordenes as $oc): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($oc['numero_oc']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo htmlspecialchars($oc['servicio_destino'] ?? 'Stock Central'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($oc['fecha_creacion'])); ?></td>
                                    <td>
                                        <?php 
                                            $st = $oc['estado'];
                                            $cls = 'bg-secondary';
                                            if($st == 'aprobada_logistica') $cls = 'bg-success';
                                            if($st == 'rechazada') $cls = 'bg-danger';
                                            if($st == 'pendiente_logistica') $cls = 'bg-warning text-dark';
                                            echo "<span class='badge $cls'>" . strtoupper(str_replace('_', ' ', $st)) . "</span>";
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="insumos_oc_ver.php?id=<?php echo $oc['id']; ?>" class="btn btn-sm btn-outline-primary fw-bold">
                                            <i class="fas fa-eye me-1"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron órdenes de compra.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>