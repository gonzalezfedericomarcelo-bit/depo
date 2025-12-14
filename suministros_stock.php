<?php
// Archivo: suministros_stock.php
// Propósito: Gestión de Stock Suministros Generales (Buscador Full + Crear)

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// 1. PERMISOS
if (!tienePermiso('gestion_stock_suministros') && !tienePermiso('ver_stock_suministros')) {
    echo "<div class='container mt-4'><div class='alert alert-danger shadow-sm'>⛔ Acceso Denegado.</div></div>";
    include 'includes/footer.php'; exit;
}

$puede_editar = tienePermiso('gestion_stock_suministros');
$mensaje = "";

// 2. LÓGICA DE CREAR (INSERT) - COMPLETA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'crear') {
    if ($puede_editar) {
        try {
            // SQL Insertar Suministros
            $sqlInsert = "INSERT INTO suministros_generales 
                          (codigo, nombre, descripcion, unidad_medida, stock_actual, stock_minimo) 
                          VALUES (:c, :n, :d, :u, :s, :m)";
            
            $stmt = $pdo->prepare($sqlInsert);
            $stmt->execute([
                ':c' => $_POST['codigo'],
                ':n' => $_POST['nombre'],
                ':d' => $_POST['descripcion'] ?? '',
                ':u' => $_POST['unidad_medida'],
                ':s' => $_POST['stock_actual'],
                ':m' => $_POST['stock_minimo']
            ]);
            
            $mensaje = '<div class="alert alert-success alert-dismissible fade show">✅ Suministro creado correctamente. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (Exception $e) {
            $mensaje = '<div class="alert alert-danger alert-dismissible fade show">Error al crear: '.$e->getMessage().' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }
}

// 3. BUSCADOR Y FILTROS PHP
$busqueda = $_GET['q'] ?? '';
$filtro = $_GET['filtro'] ?? '';

$sql = "SELECT * FROM suministros_generales WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE :q OR codigo LIKE :q OR descripcion LIKE :q)";
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
    <h1 class="mt-4">Stock Suministros Generales</h1>
    <?php echo $mensaje; ?>

    <div class="card mb-4 bg-light border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <form method="GET" class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="q" id="liveSearchInput" class="form-control border-start-0 ps-0" 
                               placeholder="Buscar por nombre, código..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>" autocomplete="off">
                        <button class="btn btn-warning fw-bold text-dark" type="submit">Buscar</button>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <a href="?filtro=critico" class="btn btn-outline-danger me-2 fw-bold <?php echo ($filtro=='critico')?'active':''; ?>">
                        <i class="fas fa-exclamation-triangle me-1"></i> Ver Bajos
                    </a>
                    <?php if ($puede_editar): ?>
                        <button class="btn btn-warning fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevo">
                            <i class="fas fa-plus me-1"></i> Nuevo Suministro
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" id="tablaStock">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Descripción del Suministro</th>
                            <th class="text-center">Stock</th>
                            <th class="text-center">Mínimo</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($items) > 0): ?>
                            <?php foreach ($items as $it): ?>
                            <?php 
                                $esBajo = $it['stock_actual'] <= $it['stock_minimo'];
                                $claseFila = $esBajo ? 'table-warning bg-opacity-25' : '';
                                $estadoBadge = $esBajo ? '<span class="badge bg-danger">BAJO STOCK</span>' : '<span class="badge bg-success">OPTIMO</span>';
                            ?>
                            <tr class="<?php echo $claseFila; ?>">
                                <td class="text-muted small fw-bold"><?php echo htmlspecialchars($it['codigo']); ?></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($it['nombre']); ?></td>
                                <td class="text-center fs-5 fw-bold"><?php echo $it['stock_actual']; ?></td>
                                <td class="text-center text-muted"><?php echo $it['stock_minimo']; ?></td>
                                <td class="text-center"><?php echo $estadoBadge; ?></td>
                                <td class="text-center">
                                    <?php if ($puede_editar): ?>
                                        <a href="suministros_editar.php?id=<?php echo $it['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No se encontraron suministros.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($puede_editar): ?>
<div class="modal fade" id="modalNuevo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-box-open me-2"></i> Nuevo Suministro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <input type="hidden" name="accion" value="crear">
                
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Nombre / Artículo *</label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Ej: Resma A4 75g">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Código</label>
                        <input type="text" name="codigo" class="form-control" placeholder="Ej: PAP-001">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label small text-muted">Descripción Técnica</label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Detalles...">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Unidad Medida</label>
                        <input type="text" name="unidad_medida" class="form-control" placeholder="Ej: Unidad, Paquete">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Stock Inicial *</label>
                        <input type="number" name="stock_actual" class="form-control" required value="0" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-danger">Stock Mínimo *</label>
                        <input type="number" name="stock_minimo" class="form-control" required value="10" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning fw-bold px-4">Guardar Suministro</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('liveSearchInput');
    const tableRows = document.querySelectorAll('#tablaStock tbody tr');

    if(searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if(text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>