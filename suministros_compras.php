<?php
// Archivo: suministros_compras.php
// Propósito: Listado de OC Suministros (BUSCADOR CORREGIDO: Solución error HY093)

// 1. INICIALIZACIÓN
try {
    require 'db.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
} catch (Exception $e) { die("Error de conexión: " . $e->getMessage()); }

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// 2. PERMISOS
$ver_todas = tienePermiso('ver_oc_suministros_todas');
$ver_propias = tienePermiso('ver_oc_suministros_propias');

if (!$ver_todas && !$ver_propias) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>⛔ Acceso Denegado.</div></div>";
    include 'includes/footer.php'; exit;
}

// 3. LÓGICA DE BÚSQUEDA (CORREGIDA)
$busqueda = $_GET['q'] ?? '';
$ordenes = [];
$error_db = "";

try {
    // Consulta Base
    $sql = "SELECT oc.*, u.nombre_completo as creador 
            FROM ordenes_compra oc 
            JOIN usuarios u ON oc.id_usuario_creador = u.id 
            WHERE oc.tipo_origen = 'suministros'";
    
    $params = [];

    // Filtro de Visibilidad
    if (!$ver_todas && $ver_propias) {
        $sql .= " AND oc.servicio_destino = :serv";
        $params[':serv'] = $_SESSION['user_data']['servicio'];
    }

    // Filtro de Búsqueda (SOLUCIÓN HY093)
    if (!empty($busqueda)) {
        // Usamos :q1 y :q2 para evitar el conflicto de parámetros duplicados
        $sql .= " AND (
                    oc.numero_oc LIKE :q1 
                    OR EXISTS (
                        SELECT 1 FROM ordenes_compra_items oci 
                        WHERE oci.id_oc = oc.id 
                        AND oci.descripcion_producto LIKE :q2
                    )
                  )";
        $term = "%$busqueda%";
        $params[':q1'] = $term;
        $params[':q2'] = $term;
    }

    $sql .= " ORDER BY oc.fecha_creacion DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ordenes = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_db = "Error crítico en DB: " . $e->getMessage();
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Órdenes de Compra (Suministros)</h1>
    
    <?php if ($error_db): ?>
        <div class="alert alert-danger shadow-sm border-0">
            <i class="fas fa-bug me-2"></i> <?php echo $error_db; ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4 bg-light border-0 shadow-sm">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <form method="GET" class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" 
                               placeholder="Buscar N° OC o Producto (ej: Resma)..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>" autocomplete="off">
                        <button class="btn btn-warning fw-bold text-dark" type="submit">Buscar</button>
                        <?php if(!empty($busqueda)): ?>
                            <a href="suministros_compras.php" class="btn btn-secondary" title="Limpiar"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <?php if (tienePermiso('gestion_compras_suministros') || tienePermiso('crear_oc_suministros')): ?>
                        <a href="suministros_oc_crear.php" class="btn btn-dark shadow-sm">
                            <i class="fas fa-plus me-1"></i> Nueva Orden Manual
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
                                    <td class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($oc['numero_oc']); ?>
                                        <?php if(!empty($busqueda) && stripos($oc['numero_oc'], $busqueda) === false): ?>
                                            <span class="badge bg-info text-dark ms-2" style="font-size: 0.7rem;">Contiene: <?php echo htmlspecialchars($busqueda); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo htmlspecialchars($oc['servicio_destino'] ?? 'Central'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($oc['fecha_creacion'])); ?></td>
                                    <td>
                                        <?php 
                                            $st = $oc['estado'];
                                            $cls = 'bg-secondary';
                                            if($st == 'aprobada_logistica' || $st == 'completada') $cls = 'bg-success';
                                            if($st == 'rechazada') $cls = 'bg-danger';
                                            if($st == 'pendiente_logistica') $cls = 'bg-warning text-dark';
                                            echo "<span class='badge $cls'>" . strtoupper(str_replace('_', ' ', $st)) . "</span>";
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="suministros_oc_ver.php?id=<?php echo $oc['id']; ?>" class="btn btn-sm btn-outline-dark fw-bold">
                                            <i class="fas fa-eye me-1"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <div class="mb-2"><i class="fas fa-search fa-2x opacity-25"></i></div>
                                    No se encontraron órdenes que coincidan con "<strong><?php echo htmlspecialchars($busqueda); ?></strong>".
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>