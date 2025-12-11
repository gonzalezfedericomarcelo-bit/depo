<?php
// Archivo: admin_flujos.php
// Propósito: Configurar responsables y etiquetas de los pasos del flujo
require 'db.php';
session_start();

if (!in_array('Administrador', $_SESSION['user_roles'] ?? [])) {
    header("Location: dashboard.php"); exit;
}

$mensaje = "";
$proceso_seleccionado = $_GET['proceso'] ?? 'movimiento_suministros';

// GUARDAR CAMBIOS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        foreach ($_POST['pasos'] as $id_paso => $datos) {
            $stmt = $pdo->prepare("UPDATE config_flujos SET id_rol_responsable = :rol, etiqueta_estado = :etiqueta WHERE id = :id");
            $stmt->execute([
                ':rol' => $datos['rol'],
                ':etiqueta' => $datos['etiqueta'],
                ':id' => $id_paso
            ]);
        }
        $pdo->commit();
        $mensaje = '<div class="alert alert-success">Configuración actualizada.</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// Obtener roles para el selector
$roles = $pdo->query("SELECT * FROM roles ORDER BY nombre ASC")->fetchAll();
$roles[] = ['id' => 0, 'nombre' => '--- EL SOLICITANTE (Servicio) ---'];

// Obtener pasos del proceso seleccionado
$stmtPasos = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = :proc ORDER BY paso_orden ASC");
$stmtPasos->execute([':proc' => $proceso_seleccionado]);
$pasos = $stmtPasos->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Editor de Flujos de Trabajo</h1>
    <?php echo $mensaje; ?>

    <div class="btn-group mb-4">
        <a href="?proceso=movimiento_suministros" class="btn btn-outline-warning <?php echo ($proceso_seleccionado=='movimiento_suministros')?'active':''; ?>">Suministros Internos</a>
        <a href="?proceso=movimiento_insumos" class="btn btn-outline-primary <?php echo ($proceso_seleccionado=='movimiento_insumos')?'active':''; ?>">Insumos Médicos</a>
    </div>

    <form method="POST">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">Pasos del Proceso: <strong><?php echo strtoupper(str_replace('_', ' ', $proceso_seleccionado)); ?></strong></div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="50" class="text-center">#</th>
                            <th>Nombre Técnico (Estado)</th>
                            <th>Etiqueta Visible (Para el usuario)</th>
                            <th>Rol Responsable (Quién ejecuta)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pasos as $p): ?>
                        <tr>
                            <td class="text-center fw-bold"><?php echo $p['paso_orden']; ?></td>
                            <td><code><?php echo $p['nombre_estado']; ?></code></td>
                            <td>
                                <input type="text" name="pasos[<?php echo $p['id']; ?>][etiqueta]" class="form-control" value="<?php echo htmlspecialchars($p['etiqueta_estado']); ?>">
                            </td>
                            <td>
                                <select name="pasos[<?php echo $p['id']; ?>][rol]" class="form-select">
                                    <?php foreach($roles as $r): ?>
                                        <option value="<?php echo $r['id']; ?>" <?php echo ($r['id'] == $p['id_rol_responsable']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($r['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-save me-2"></i> Guardar Cambios</button>
            </div>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>