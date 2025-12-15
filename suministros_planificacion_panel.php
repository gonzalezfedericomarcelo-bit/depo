<?php
// Archivo: suministros_planificacion_panel.php
// Propósito: Panel Suministros (DINÁMICO: Notifica a todos los roles con el permiso activado)

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// PERMISOS
$es_logistica = tienePermiso('gestionar_planificaciones');
$es_director  = tienePermiso('aprobar_planificacion_director');
$es_compras   = tienePermiso('procesar_compra_precios');
$es_admin     = in_array('Administrador', $_SESSION['user_roles'] ?? []);

if (!$es_logistica && !$es_director && !$es_compras && !$es_admin) {
    echo "<div class='container mt-5 alert alert-danger'>⛔ Acceso Denegado.</div>"; include 'includes/footer.php'; exit;
}

// BORRAR
if (isset($_POST['eliminar_id']) && $es_admin) {
    try {
        $pdo->prepare("DELETE FROM compras_planificaciones WHERE id = ?")->execute([$_POST['eliminar_id']]);
        echo "<script>window.location='suministros_planificacion_panel.php?msg=eliminado';</script>";
    } catch (Exception $e) { echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>"; }
}

// CREAR CAMPAÑA Y NOTIFICAR DINÁMICAMENTE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_campana'])) {
    if (!$es_logistica && !$es_admin) { die("Acceso denegado."); }
    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO compras_planificaciones (titulo, frecuencia_cobertura, fecha_inicio, fecha_fin, creado_por, tipo_insumo, estado) 
                VALUES (:tit, :freq, :ini, :fin, :user, 'suministros', 'abierta')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tit' => $_POST['titulo'],
            ':freq'=> $_POST['frecuencia_cobertura'],
            ':ini' => $_POST['fecha_inicio'],
            ':fin' => $_POST['fecha_cierre'],
            ':user'=> $_SESSION['user_id']
        ]);
        
        // --- NOTIFICACIÓN DINÁMICA (SIN HARDCODE) ---
        // 1. Buscamos TODOS los roles que tengan el permiso 'recibir_avisos_campana'
        $sqlRoles = "SELECT rp.id_rol 
                     FROM rol_permisos rp 
                     JOIN permisos p ON rp.id_permiso = p.id 
                     WHERE p.clave = 'recibir_avisos_campana'";
        $rolesDestino = $pdo->query($sqlRoles)->fetchAll(PDO::FETCH_COLUMN);

        if ($rolesDestino) {
            $msg = "📢 Nueva Campaña Suministros: " . $_POST['titulo'];
            $stmtNoti = $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)");
            
            // 2. Insertamos una notificación para CADA rol encontrado
            foreach ($rolesDestino as $idRol) {
                $stmtNoti->execute([$idRol, $msg, 'campana_carga_suministros.php']);
            }
        }
        // ---------------------------------------------

        $pdo->commit();
        echo "<script>window.location='suministros_planificacion_panel.php?msg=ok';</script>";
    } catch (Exception $e) {
        $pdo->rollBack(); echo "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>";
    }
}

// LISTADO
$busqueda = $_GET['q'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$sql = "SELECT * FROM compras_planificaciones WHERE tipo_insumo = 'suministros'";
if (!empty($busqueda)) { $sql .= " AND titulo LIKE '%$busqueda%'"; }
if (!empty($filtro_estado)) { $sql .= " AND estado = '$filtro_estado'"; }
$sql .= " ORDER BY id DESC";
$planificaciones = $pdo->query($sql)->fetchAll();
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1 class="fw-bold text-success">Planificación Suministros</h1>
        <?php if ($es_logistica || $es_admin): ?>
            <button class="btn btn-success fw-bold shadow" data-bs-toggle="modal" data-bs-target="#modalNueva">
                <i class="fas fa-plus me-2"></i> Nueva Campaña
            </button>
        <?php endif; ?>
    </div>
    
    <div class="card mb-4 border-success shadow-sm">
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Campaña</th>
                        <th>Cobertura</th>
                        <th>Deadline</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($planificaciones) > 0): ?>
                        <?php foreach($planificaciones as $p): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($p['titulo']); ?></td>
                            <td><span class="badge bg-success bg-opacity-25 text-success border border-success"><?php echo htmlspecialchars($p['frecuencia_cobertura'] ?? 'N/A'); ?></span></td>
                            <td>
                                <strong class="text-danger">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($p['fecha_fin'])); ?> hs
                                </strong>
                            </td>
                            <td><span class="badge bg-secondary"><?php echo strtoupper($p['estado']); ?></span></td>
                            <td class="text-end">
                                <?php if ($es_compras && $p['estado'] == 'aprobada_director'): ?>
                                    <a href="suministros_gestion_compras.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-success fw-bold">Procesar</a>
                                <?php elseif ($es_director && $p['estado'] == 'cerrada_logistica'): ?>
                                    <a href="suministros_planificacion_detalle.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-warning text-dark">Revisar</a>
                                <?php elseif ($es_logistica || $es_admin): ?>
                                    <a href="suministros_planificacion_detalle.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">Gestionar</a>
                                <?php endif; ?>
                                
                                <?php if($es_admin): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar?');">
                                        <input type="hidden" name="eliminar_id" value="<?php echo $p['id']; ?>">
                                        <button class="btn btn-sm btn-danger ms-1"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No hay campañas registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($es_logistica || $es_admin): ?>
<div class="modal fade" id="modalNueva" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-success text-white"><h5>Lanzar Campaña Suministros</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="crear_campana" value="1">
                
                <div class="mb-3">
                    <label class="fw-bold">Título</label>
                    <input type="text" name="titulo" class="form-control" required placeholder="Ej: Librería Trimestre 1">
                </div>

                <div class="mb-3">
                    <label class="fw-bold">1. Tipo de Cobertura</label>
                    <select name="frecuencia_cobertura" class="form-select fw-bold text-success">
                        <option value="Semanal">Semanal</option>
                        <option value="Mensual">Mensual</option>
                        <option value="Trimestral">Trimestral</option>
                        <option value="Semestral">Semestral</option>
                        <option value="Anual">Anual</option>
                    </select>
                </div>

                <hr>

                <div class="mb-3">
                    <label class="fw-bold text-dark">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="mb-3 p-3 bg-danger bg-opacity-10 border border-danger rounded">
                    <label class="fw-bold text-danger">2. Fecha y Hora de Vencimiento (Cierre)</label>
                    <input type="datetime-local" name="fecha_cierre" class="form-control border-danger fw-bold" required>
                    <div class="form-text text-danger small">A partir de esta hora exacta, nadie podrá cargar más pedidos.</div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success fw-bold">Publicar Campaña</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>