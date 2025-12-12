<?php
// Archivo: suministros_stock.php
require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

if (!tienePermiso('gestion_stock_suministros') && !tienePermiso('ver_stock_suministros')) {
    echo "<div class='alert alert-danger m-4'>⛔ Acceso Denegado.</div>"; include 'includes/footer.php'; exit;
}
$puede_editar = tienePermiso('gestion_stock_suministros');
$mensaje = "";

// GUARDAR
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'crear' && $puede_editar) {
    try {
        $stmt = $pdo->prepare("INSERT INTO suministros_generales (codigo, nombre, descripcion, unidad_medida, stock_actual, stock_minimo) VALUES (:c, :n, :d, :u, :s, :m)");
        $stmt->execute([':c'=>$_POST['codigo'], ':n'=>$_POST['nombre'], ':d'=>$_POST['descripcion'], ':u'=>$_POST['unidad_medida'], ':s'=>$_POST['stock_actual'], ':m'=>$_POST['stock_minimo']]);
        $mensaje = '<div class="alert alert-success alert-dismissible fade show">✅ Creado.</div>';
    } catch (Exception $e) { $mensaje = '<div class="alert alert-danger">Error: '.$e->getMessage().'</div>'; }
}

// BUSQUEDA
$busqueda = $_GET['q'] ?? '';
$filtro = $_GET['filtro'] ?? '';

$sql = "SELECT * FROM suministros_generales WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE :q OR codigo LIKE :q)";
    $params[':q'] = "%$busqueda%";
}
if ($filtro == 'critico') {
    $sql .= " AND stock_actual <= stock_minimo";
}
$sql .= " ORDER BY nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Stock Suministros</h1>
    <?php echo $mensaje; ?>

    <div class="card mb-4 bg-light border-0">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-6">
                    <form method="GET" class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Buscar por nombre o código..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <a href="?filtro=critico" class="btn btn-outline-danger me-2 <?php echo ($filtro=='critico')?'active':''; ?>"><i class="fas fa-exclamation-triangle"></i> Solo Bajos</a>
                    <?php if ($puede_editar): ?>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevo"><i class="fas fa-plus"></i> Nuevo</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-dark"><tr><th>Cód</th><th>Nombre</th><th>Stock</th><th>Mín</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <?php 
                            $esBajo = $it['stock_actual'] <= $it['stock_minimo'];
                            $estado = $esBajo ? '<span class="badge bg-danger">BAJO</span>' : '<span class="badge bg-success">OK</span>';
                        ?>
                        <tr class="<?php echo $esBajo ? 'table-danger' : ''; ?>">
                            <td><?php echo htmlspecialchars($it['codigo']); ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($it['nombre']); ?></td>
                            <td class="fw-bold text-center fs-5"><?php echo $it['stock_actual']; ?></td>
                            <td><?php echo $it['stock_minimo']; ?></td>
                            <td class="text-center"><?php echo $estado; ?></td>
                            <td class="text-center">
                                <?php if ($puede_editar): ?>
                                    <a href="suministros_editar.php?id=<?php echo $it['id']; ?>" class="btn btn-sm btn-link"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($puede_editar): ?>
<div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-success text-white"><h5>Nuevo Suministro</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="accion" value="crear">
                <div class="mb-2"><input type="text" name="nombre" class="form-control" placeholder="Nombre" required></div>
                <div class="row mb-2">
                    <div class="col"><input type="text" name="codigo" class="form-control" placeholder="Código"></div>
                    <div class="col"><input type="text" name="unidad_medida" class="form-control" placeholder="Unidad"></div>
                </div>
                <div class="row">
                    <div class="col"><input type="number" name="stock_actual" class="form-control" placeholder="Stock" required></div>
                    <div class="col"><input type="number" name="stock_minimo" class="form-control" placeholder="Mín" required></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success">Guardar</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>