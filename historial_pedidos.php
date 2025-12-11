<?php
// Archivo: historial_pedidos.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$user_id = $_SESSION['user_id'];

// 1. CHEQUEO DE PERMISOS
// Si no tiene NINGÚN permiso de ver historial, lo sacamos.
if (!tienePermiso('ver_mis_pedidos') && !tienePermiso('ver_todos_pedidos_insumos') && !tienePermiso('ver_todos_pedidos_suministros')) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>No tienes permiso para ver el historial.</div></div>";
    include 'includes/footer.php'; exit;
}

// 2. CONSTRUIR FILTROS
$where = [];
$params = [];

// Filtro de alcance: ¿Ve todo o solo lo suyo?
// Si NO tiene permisos de "Ver Todos", filtramos por su ID.
if (!tienePermiso('ver_todos_pedidos_insumos') && !tienePermiso('ver_todos_pedidos_suministros')) {
    $where[] = "p.id_usuario_solicitante = :uid";
    $params[':uid'] = $user_id;
}

// Filtro por Tipo (si viene en URL)
if (isset($_GET['tipo']) && !empty($_GET['tipo'])) {
    if ($_GET['tipo'] == 'insumos_medicos') $where[] = "p.tipo_insumo = 'insumos_medicos'";
    if ($_GET['tipo'] == 'suministros') $where[] = "p.tipo_insumo = 'suministros'";
}

// Filtro por Estado
if (isset($_GET['estado'])) {
    $where[] = "p.estado LIKE :est";
    $params[':est'] = "%" . $_GET['estado'] . "%";
}

// Consulta
$sql = "SELECT p.*, u.nombre_completo as solicitante 
        FROM pedidos_servicio p 
        LEFT JOIN usuarios u ON p.id_usuario_solicitante = u.id";

if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY p.fecha_solicitud DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><i class="fas fa-list-alt me-2"></i> Historial de Pedidos</h1>
    
    <div class="card mb-4 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Solicitante</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pedidos) > 0): ?>
                            <?php foreach($pedidos as $p): ?>
                            <tr>
                                <td class="fw-bold">#<?php echo $p['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($p['fecha_solicitud'])); ?></td>
                                <td><?php echo htmlspecialchars($p['solicitante']); ?><br><small class="text-muted"><?php echo htmlspecialchars($p['servicio_solicitante']); ?></small></td>
                                <td>
                                    <?php 
                                        if($p['tipo_insumo'] == 'insumos_medicos') echo '<span class="badge bg-primary">Insumos</span>';
                                        else echo '<span class="badge bg-warning text-dark">Suministros</span>';
                                    ?>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo strtoupper(str_replace('_', ' ', $p['estado'])); ?></span></td>
                                <td class="text-center">
                                    <a href="pedidos_ver.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary fw-bold">Ver Detalles</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron pedidos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>