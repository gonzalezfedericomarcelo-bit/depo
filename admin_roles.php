<?php
// Archivo: admin_roles.php
// Propósito: Gestión de Roles (VERSIÓN CORREGIDA - SIN CONFLICTO DE BOTONES)

require 'db.php';
session_start();

// Verificación de permiso
if (!tienePermiso('gestionar_roles')) { header("Location: dashboard.php"); exit; }

$mensaje = "";
$rol_seleccionado = null;
$permisos_asignados = [];

// 1. PROCESAR GUARDADO / ELIMINADO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    try {
        $pdo->beginTransaction();

        // ACCIÓN: GUARDAR
        if ($_POST['accion'] == 'guardar') {
            $nombre = trim($_POST['nombre']);
            $descripcion = trim($_POST['descripcion'] ?? '');
            $id_rol = !empty($_POST['id_rol']) ? $_POST['id_rol'] : null;

            if (empty($nombre)) throw new Exception("El nombre es obligatorio.");

            if ($id_rol) {
                // Editar existente
                $stmt = $pdo->prepare("UPDATE roles SET nombre = :n, descripcion = :d WHERE id = :i");
                $stmt->execute([':n'=>$nombre, ':d'=>$descripcion, ':i'=>$id_rol]);
            } else {
                // Crear nuevo
                $chk = $pdo->prepare("SELECT id FROM roles WHERE nombre = ?");
                $chk->execute([$nombre]);
                if($chk->fetch()) throw new Exception("El rol ya existe.");

                $stmt = $pdo->prepare("INSERT INTO roles (nombre, descripcion) VALUES (:n, :d)");
                $stmt->execute([':n'=>$nombre, ':d'=>$descripcion]);
                $id_rol = $pdo->lastInsertId();
            }

            // Guardar Permisos (Borrar viejos -> Insertar nuevos)
            $pdo->prepare("DELETE FROM rol_permisos WHERE id_rol = ?")->execute([$id_rol]);
            
            if (isset($_POST['permisos']) && is_array($_POST['permisos'])) {
                $stmtP = $pdo->prepare("INSERT INTO rol_permisos (id_rol, id_permiso) VALUES (?, ?)");
                foreach ($_POST['permisos'] as $p_id) {
                    $stmtP->execute([$id_rol, $p_id]);
                }
            }
            
            $mensaje = '<div class="alert alert-success">✅ Rol guardado correctamente.</div>';
            $_GET['editar'] = $id_rol; // Mantenerse en el rol editado
        }

        // ACCIÓN: ELIMINAR
        if ($_POST['accion'] == 'eliminar') {
            $id_borrar = $_POST['id_rol'];
            if($id_borrar == 1) throw new Exception("No puedes borrar al Administrador Principal.");
            
            $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$id_borrar]);
            
            $mensaje = '<div class="alert alert-warning">🗑️ Rol eliminado correctamente.</div>';
            $id_rol = null;
            unset($_GET['editar']);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = '<div class="alert alert-danger">Error: '.$e->getMessage().'</div>';
    }
}

// 2. CARGAR DATOS PARA EDICIÓN
if (isset($_GET['editar'])) {
    $id_editar = $_GET['editar'];
    $stmtRol = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmtRol->execute([$id_editar]);
    $rol_seleccionado = $stmtRol->fetch();

    if ($rol_seleccionado) {
        $stmtPerms = $pdo->prepare("SELECT id_permiso FROM rol_permisos WHERE id_rol = ?");
        $stmtPerms->execute([$id_editar]);
        $permisos_asignados = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// Cargar Listas
$roles = $pdo->query("SELECT * FROM roles ORDER BY nombre ASC")->fetchAll();
$permisos_raw = $pdo->query("SELECT * FROM permisos ORDER BY categoria ASC, nombre ASC")->fetchAll();
$permisos_agrupados = [];
foreach($permisos_raw as $p) {
    $cat = $p['categoria'] ?? 'General';
    $permisos_agrupados[$cat][] = $p;
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><i class="fas fa-user-shield"></i> Gestión de Roles</h1>
    <?php echo $mensaje; ?>

    <div class="row">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span>Roles</span>
                    <a href="admin_roles.php" class="btn btn-sm btn-success fw-bold">+ Nuevo</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($roles as $r): ?>
                        <?php $activo = (isset($rol_seleccionado['id']) && $rol_seleccionado['id'] == $r['id']) ? 'active' : ''; ?>
                        <a href="admin_roles.php?editar=<?php echo $r['id']; ?>" class="list-group-item list-group-item-action <?php echo $activo; ?>">
                            <?php echo htmlspecialchars($r['nombre']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card shadow border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="m-0">
                        <?php echo $rol_seleccionado ? 'Editando: ' . htmlspecialchars($rol_seleccionado['nombre']) : 'Creando Nuevo Rol'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    
                    <form method="POST" id="formGuardar">
                        <input type="hidden" name="accion" value="guardar">
                        <input type="hidden" name="id_rol" id="input_id_rol" value="<?php echo $rol_seleccionado['id'] ?? ''; ?>">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">Nombre del Rol</label>
                                <input type="text" name="nombre" class="form-control fw-bold" required 
                                       value="<?php echo htmlspecialchars($rol_seleccionado['nombre'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label>Descripción</label>
                                <input type="text" name="descripcion" class="form-control" 
                                       value="<?php echo htmlspecialchars($rol_seleccionado['descripcion'] ?? ''); ?>">
                            </div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <h6 class="text-primary fw-bold">Permisos Asignados</h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-dark" onclick="checkAll(true)">Marcar Todo</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="checkAll(false)">Desmarcar</button>
                            </div>
                        </div>

                        <div class="accordion" id="accPermisos">
                            <?php $i=0; foreach ($permisos_agrupados as $cat => $items): $i++; ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button <?php echo $i>1?'collapsed':''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#c<?php echo $i; ?>">
                                            <?php echo htmlspecialchars($cat); ?> 
                                            <span class="badge bg-secondary ms-2"><?php echo count($items); ?></span>
                                        </button>
                                    </h2>
                                    <div id="c<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i==1?'show':''; ?>" data-bs-parent="#accPermisos">
                                        <div class="accordion-body">
                                            <div class="row">
                                                <?php foreach ($items as $p): ?>
                                                    <?php $checked = in_array($p['id'], $permisos_asignados) ? 'checked' : ''; ?>
                                                    <div class="col-md-6 mb-2">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input chk-perm" type="checkbox" name="permisos[]" 
                                                                   value="<?php echo $p['id']; ?>" id="p<?php echo $p['id']; ?>" <?php echo $checked; ?>>
                                                            <label class="form-check-label small" for="p<?php echo $p['id']; ?>">
                                                                <?php echo htmlspecialchars($p['nombre']); ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4 text-end border-top pt-3 d-flex justify-content-between align-items-center">
                            
                            <?php if($rol_seleccionado && $rol_seleccionado['id'] != 1): ?>
                                <button type="button" class="btn btn-danger" onclick="confirmarBorrado()">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            <?php else: ?>
                                <div></div> <?php endif; ?>
                            
                            <div>
                                <a href="admin_roles.php" class="btn btn-secondary me-2">Cancelar</a>
                                <button type="submit" class="btn btn-primary fw-bold px-4">
                                    <i class="fas fa-save me-2"></i> Guardar Cambios
                                </button>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="formEliminarSeguro" style="display:none;">
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id_rol" id="id_rol_delete">
</form>

<script>
// Marcar/Desmarcar todos los checkboxes
function checkAll(estado) {
    document.querySelectorAll('.chk-perm').forEach(c => c.checked = estado);
}

// Función de borrado seguro que usa el formulario oculto
function confirmarBorrado() {
    // Obtenemos el ID del rol actual
    var idRol = document.getElementById('input_id_rol').value;
    
    if (!idRol) return;

    if(confirm('¿ESTÁ SEGURO?\n\nEsta acción eliminará el rol permanentemente y quitará el acceso a los usuarios que lo tengan.')) {
        // Pasamos el ID al formulario oculto y lo enviamos
        document.getElementById('id_rol_delete').value = idRol;
        document.getElementById('formEliminarSeguro').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>