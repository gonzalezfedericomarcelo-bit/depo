<?php
// Archivo: historial_pedidos.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$user_id = $_SESSION['user_id'];
$es_admin = in_array('Administrador', $_SESSION['user_roles'] ?? []);

if (!tienePermiso('ver_mis_pedidos') && !tienePermiso('ver_todos_pedidos_insumos') && !tienePermiso('ver_todos_pedidos_suministros') && !$es_admin) {
    echo "<div class='container mt-4 alert alert-danger'>⛔ Acceso Denegado.</div>"; include 'includes/footer.php'; exit;
}

// BORRAR PEDIDO (Admin)
if (isset($_POST['eliminar_id']) && $es_admin) {
    try {
        $pdo->prepare("DELETE FROM pedidos_servicio WHERE id = ?")->execute([$_POST['eliminar_id']]);
        echo "<script>window.location='historial_pedidos.php?msg=eliminado';</script>";
    } catch (Exception $e) { echo "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>"; }
}

// FILTROS
$busqueda = $_GET['q'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_rapido = $_GET['filtro'] ?? '';

$where = [];
$params = [];

$ve_todos_ins = tienePermiso('ver_todos_pedidos_insumos');
$ve_todos_sum = tienePermiso('ver_todos_pedidos_suministros');

if (!$es_admin) {
    if (!$ve_todos_ins && !$ve_todos_sum) {
        $where[] = "p.id_usuario_solicitante = :uid";
        $params[':uid'] = $user_id;
    } elseif ($ve_todos_ins && !$ve_todos_sum) {
        $where[] = "(p.tipo_insumo = 'insumos_medicos' OR p.id_usuario_solicitante = :uid)";
        $params[':uid'] = $user_id;
    } elseif (!$ve_todos_ins && $ve_todos_sum) {
        $where[] = "(p.tipo_insumo = 'suministros' OR p.id_usuario_solicitante = :uid)";
        $params[':uid'] = $user_id;
    }
}

if (!empty($busqueda)) {
    $where[] = "(p.id LIKE :q OR u.nombre_completo LIKE :q OR p.servicio_solicitante LIKE :q)";
    $params[':q'] = "%$busqueda%";
}
if (!empty($filtro_tipo)) {
    $where[] = "p.tipo_insumo = :tipo";
    $params[':tipo'] = $filtro_tipo;
}
if (!empty($filtro_estado)) {
    $where[] = "p.estado LIKE :est";
    $params[':est'] = "%$filtro_estado%";
}
if ($filtro_rapido == 'pendientes') {
    $where[] = "p.estado NOT IN ('finalizado_proceso', 'rechazada')";
}

$sql = "SELECT p.*, u.nombre_completo as solicitante, cf.etiqueta_estado 
        FROM pedidos_servicio p 
        LEFT JOIN usuarios u ON p.id_usuario_solicitante = u.id
        LEFT JOIN config_flujos cf ON p.paso_actual_id = cf.id";

if (count($where) > 0) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY p.fecha_solicitud DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><i class="fas fa-list-alt me-2"></i> Historial de Pedidos</h1>
    
    <div class="card mb-4 bg-light border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="q" class="form-control" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                <div class="col-md-3">
                    <select name="tipo" class="form-select">
                        <option value="">Todos los Tipos</option>
                        <option value="suministros" <?php echo ($filtro_tipo=='suministros')?'selected':''; ?>>Suministros</option>
                        <option value="insumos_medicos" <?php echo ($filtro_tipo=='insumos_medicos')?'selected':''; ?>>Insumos Médicos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>ID</th><th>Fecha</th><th>Solicitante</th><th>Tipo</th><th>Estado</th><th class="text-center">Acción</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($pedidos) > 0): ?>
                            <?php foreach($pedidos as $p): ?>
                            <tr>
                                <td class="fw-bold">#<?php echo $p['id']; ?></td>
                                <td><?php echo date('d/m H:i', strtotime($p['fecha_solicitud'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($p['servicio_solicitante']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($p['solicitante']); ?></small>
                                </td>
                                <td><?php echo ($p['tipo_insumo']=='insumos_medicos')?'<span class="badge bg-primary">Insumos</span>':'<span class="badge bg-warning text-dark">Suministros</span>'; ?></td>
                                <td><span class="badge bg-secondary"><?php echo !empty($p['etiqueta_estado']) ? $p['etiqueta_estado'] : strtoupper(str_replace('_',' ',$p['estado'])); ?></span></td>
                                <td class="text-center">
                                    <a href="pedidos_ver.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary fw-bold">Ver</a>
                                    <?php if($es_admin): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('ADMIN: ¿Eliminar este pedido?');">
                                            <input type="hidden" name="eliminar_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger ms-1"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No se encontraron resultados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>