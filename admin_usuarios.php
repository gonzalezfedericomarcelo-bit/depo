<?php
// Archivo: admin_usuarios.php
require 'db.php';
session_start();
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

if (!tienePermiso('gestionar_usuarios')) { header("Location: dashboard.php"); exit; }

$mensaje = "";

// APROBAR
if (isset($_GET['aprobar_id'])) {
    try {
        $id_aprob = $_GET['aprobar_id'];
        $pdo->prepare("UPDATE usuarios SET validado_por_admin = 1 WHERE id = ?")->execute([$id_aprob]);
        
        // Asignar rol Servicio si no tiene
        $rolServ = $pdo->query("SELECT id FROM roles WHERE nombre='Servicio'")->fetchColumn();
        if($rolServ) {
            $pdo->prepare("INSERT IGNORE INTO usuario_roles (id_usuario, id_rol) VALUES (?, ?)")->execute([$id_aprob, $rolServ]);
        }
        
        $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, mensaje, url_destino) VALUES (?, ?, ?)")
            ->execute([$id_aprob, "¡Tu cuenta ha sido aprobada! Ya puedes ingresar.", "dashboard.php"]);
            
        $mensaje = '<div class="alert alert-success">✅ Usuario aprobado.</div>';
    } catch (Exception $e) { $mensaje = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>'; }
}

// CREAR
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'crear') {
    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = :e OR (usuario IS NOT NULL AND usuario = :u)");
        $stmtCheck->execute([':e'=>$_POST['email'], ':u'=>$_POST['usuario']]);
        if ($stmtCheck->fetch()) throw new Exception("Email o Usuario ya registrado.");

        $passHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "INSERT INTO usuarios (nombre_completo, usuario, email, password, servicio, rol_en_servicio, activo, validado_por_admin) VALUES (:n, :u, :e, :p, :s, :r, 1, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':n'=>$_POST['nombre'], ':u'=>!empty($_POST['usuario'])?$_POST['usuario']:null, ':e'=>$_POST['email'], ':p'=>$passHash, ':s'=>$_POST['servicio'], ':r'=>$_POST['rol_servicio']]);
        $id_new = $pdo->lastInsertId();

        // Rol
        $rol_asig = !empty($_POST['rol_sistema']) ? $_POST['rol_sistema'] : $pdo->query("SELECT id FROM roles WHERE nombre='Servicio'")->fetchColumn();
        $pdo->prepare("INSERT INTO usuario_roles (id_usuario, id_rol) VALUES (?,?)")->execute([$id_new, $rol_asig]);

        $mensaje = '<div class="alert alert-success">✅ Usuario creado.</div>';
    } catch (Exception $e) { $mensaje = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>'; }
}

$lista_roles = $pdo->query("SELECT * FROM roles ORDER BY nombre ASC")->fetchAll();
$usuarios = $pdo->query("SELECT u.*, GROUP_CONCAT(r.nombre SEPARATOR ', ') as roles_nombres FROM usuarios u LEFT JOIN usuario_roles ur ON u.id = ur.id_usuario LEFT JOIN roles r ON ur.id_rol = r.id GROUP BY u.id ORDER BY u.validado_por_admin ASC, u.nombre_completo ASC")->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestión de Usuarios</h1>
    <?php echo $mensaje; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Listado de Personal</span>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevo"><i class="fas fa-user-plus"></i> Nuevo</button>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr><th>Nombre</th><th>Usuario</th><th>Email</th><th>Servicio</th><th>Roles</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr class="<?php echo ($u['validado_por_admin'] == 0) ? 'table-warning' : ''; ?>">
                            <td class="fw-bold"><?php echo htmlspecialchars($u['nombre_completo']); ?></td>
                            <td class="text-primary fw-bold"><?php echo htmlspecialchars($u['usuario'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['servicio'] . ' (' . $u['rol_en_servicio'] . ')'); ?></td>
                            <td><small><?php echo $u['roles_nombres']; ?></small></td>
                            <td>
                                <?php if ($u['validado_por_admin'] == 0): ?>
                                    <a href="admin_usuarios.php?aprobar_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-success">Aprobar</a>
                                <?php else: ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php endif; ?>
                            </td>
                            <td><a href="admin_usuarios_editar.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Nuevo Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="accion" value="crear">
                <div class="row mb-3">
                    <div class="col"><label>Nombre *</label><input type="text" name="nombre" class="form-control" required></div>
                    <div class="col"><label>Usuario</label><input type="text" name="usuario" class="form-control"></div>
                </div>
                <div class="mb-3"><label>Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label>Password *</label><input type="password" name="password" class="form-control" required></div>
                <div class="row mb-3">
                    <div class="col"><label>Servicio</label><input type="text" name="servicio" class="form-control"></div>
                    <div class="col"><label>Rol Local</label><select name="rol_servicio" class="form-select"><option>Personal</option><option>Responsable</option></select></div>
                </div>
                <div class="mb-3"><label>Rol Sistema</label>
                    <select name="rol_sistema" class="form-select">
                        <option value="">-- Automático --</option>
                        <?php foreach($lista_roles as $r) echo "<option value='{$r['id']}'>{$r['nombre']}</option>"; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Guardar</button></div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>