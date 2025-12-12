<?php
// Archivo: admin_areas.php
// Propósito: ABM de Áreas y Servicios (Padre - Hijo)
require 'db.php';
session_start();

if (!tienePermiso('gestionar_areas')) { header("Location: dashboard.php"); exit; }

$mensaje = "";

// PROCESAR ACCIONES
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['crear_area'])) {
            // Crear Padre
            $stmt = $pdo->prepare("INSERT INTO areas_servicios (nombre, id_padre) VALUES (:nom, NULL)");
            $stmt->execute([':nom' => $_POST['nombre']]);
            $mensaje = "<div class='alert alert-success'>Área creada.</div>";
        }
        elseif (isset($_POST['crear_servicio'])) {
            // Crear Hijo
            $stmt = $pdo->prepare("INSERT INTO areas_servicios (nombre, id_padre) VALUES (:nom, :padre)");
            $stmt->execute([':nom' => $_POST['nombre'], ':padre' => $_POST['id_padre']]);
            $mensaje = "<div class='alert alert-success'>Servicio agregado.</div>";
        }
        elseif (isset($_POST['eliminar'])) {
            // Eliminar (Cascada borrará hijos automáticamente por la FK)
            $stmt = $pdo->prepare("DELETE FROM areas_servicios WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            $mensaje = "<div class='alert alert-warning'>Eliminado correctamente.</div>";
        }
        elseif (isset($_POST['editar'])) {
            // Editar Nombre
            $stmt = $pdo->prepare("UPDATE areas_servicios SET nombre = :nom WHERE id = :id");
            $stmt->execute([':nom' => $_POST['nombre'], ':id' => $_POST['id']]);
            $mensaje = "<div class='alert alert-success'>Nombre actualizado.</div>";
        }
    } catch (Exception $e) {
        $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// Obtener Árbol
// 1. Padres
$padres = $pdo->query("SELECT * FROM areas_servicios WHERE id_padre IS NULL ORDER BY nombre ASC")->fetchAll();
// 2. Hijos
$hijos_raw = $pdo->query("SELECT * FROM areas_servicios WHERE id_padre IS NOT NULL ORDER BY nombre ASC")->fetchAll();
$hijos = [];
foreach($hijos_raw as $h) {
    $hijos[$h['id_padre']][] = $h;
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Áreas y Servicios</h1>
    <p class="text-muted">Aquí defines la estructura que aparecerá en los formularios de entrega.</p>
    <?php echo $mensaje; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white fw-bold">Nueva Área Principal</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="input-group">
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: Laboratorio, Guardia..." required>
                            <button type="submit" name="crear_area" class="btn btn-dark">Crear</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <?php foreach ($padres as $p): ?>
                <div class="card mb-3 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center bg-light">
                        <form method="POST" class="d-flex align-items-center flex-grow-1 me-3">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <input type="text" name="nombre" class="form-control fw-bold border-0 bg-transparent" value="<?php echo htmlspecialchars($p['nombre']); ?>">
                            <button type="submit" name="editar" class="btn btn-sm btn-link text-primary"><i class="fas fa-save"></i></button>
                        </form>
                        
                        <form method="POST" onsubmit="return confirm('¿Borrar esta área y TODOS sus servicios?');">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button type="submit" name="eliminar" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                    
                    <div class="card-body">
                        <?php if (isset($hijos[$p['id']])): ?>
                            <div class="row g-2 mb-3">
                                <?php foreach ($hijos[$p['id']] as $h): ?>
                                    <div class="col-md-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white"><i class="fas fa-level-up-alt fa-rotate-90 text-muted"></i></span>
                                            
                                            <form method="POST" class="flex-grow-1 d-flex">
                                                <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
                                                <input type="text" name="nombre" class="form-control border-end-0" value="<?php echo htmlspecialchars($h['nombre']); ?>">
                                                <button type="submit" name="editar" class="btn btn-outline-secondary border-start-0 border-end-0"><i class="fas fa-check"></i></button>
                                            </form>

                                            <form method="POST" onsubmit="return confirm('¿Borrar servicio?');">
                                                <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
                                                <button type="submit" name="eliminar" class="btn btn-outline-danger"><i class="fas fa-times"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="small text-muted fst-italic mb-2">Sin servicios asignados (Solo se seleccionará el área).</p>
                        <?php endif; ?>

                        <form method="POST" class="mt-2 border-top pt-2">
                            <input type="hidden" name="id_padre" value="<?php echo $p['id']; ?>">
                            <div class="input-group input-group-sm w-50">
                                <input type="text" name="nombre" class="form-control" placeholder="Agregar servicio..." required>
                                <button type="submit" name="crear_servicio" class="btn btn-secondary">+</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>