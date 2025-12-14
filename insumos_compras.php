<?php
// Archivo: insumos_compras.php
// Propósito: Órdenes Insumos (Buscador arreglado: busca productos y Nº OC)

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// 1. PERMISOS
$ver_todas = tienePermiso('ver_oc_insumos_todas');
$ver_propias = tienePermiso('ver_oc_insumos_propias');

if (!$ver_todas && !$ver_propias) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>⛔ Acceso Denegado.</div></div>";
    include 'includes/footer.php'; exit;
}

// 2. BUSCADOR PHP (Recibe el dato del Dashboard)
$busqueda = $_GET['q'] ?? '';

// 3. CONSULTA PODEROSA (JOIN con ítems para encontrar productos)
$sql = "SELECT DISTINCT oc.*, u.nombre_completo as creador 
        FROM ordenes_compra oc 
        JOIN usuarios u ON oc.id_usuario_creador = u.id 
        LEFT JOIN ordenes_compra_items oci ON oc.id = oci.id_oc
        WHERE oc.tipo_origen = 'insumos'";

$params = [];

// Filtro de Visibilidad
if (!$ver_todas && $ver_propias) {
    $sql .= " AND oc.servicio_destino = :serv";
    $params[':serv'] = $_SESSION['user_data']['servicio'];
}

// Filtro de Búsqueda (Aquí estaba el problema)
if (!empty($busqueda)) {
    $sql .= " AND (oc.numero_oc LIKE :q OR oci.descripcion_producto LIKE :q)";
    $params[':q'] = "%$busqueda%";
}

$sql .= " ORDER BY oc.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Órdenes de Compra (Insumos)</h1>
    
    <div class="card mb-4 bg-light border-0 shadow-sm">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <form method="GET" class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                        <input type="text" name="q" id="searchInput" class="form-control" 
                               placeholder="Escriba para filtrar (N° OC o Producto)..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>" autocomplete="off">
                        <button class="btn btn-primary" type="submit">Buscar en DB</button>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <?php if (tienePermiso('gestion_compras_insumos')): ?>
                        <a href="insumos_oc_crear.php" class="btn btn-success shadow-sm fw-bold">
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
                <table class="table table-bordered table-hover align-middle mb-0" id="tablaResultados">
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
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($oc['servicio_destino']); ?></span></td>
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
                                        <a href="insumos_oc_ver.php?id=<?php echo $oc['id']; ?>" class="btn btn-sm btn-outline-primary fw-bold">Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron órdenes.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#tablaResultados tbody tr');

    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>