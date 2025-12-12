<?php
// Archivo: admin_usuarios_editar.php
require 'db.php';
session_start();
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// Seguridad: Solo quien tenga permiso de gestionar usuarios
if (!tienePermiso('gestionar_usuarios')) { header("Location: dashboard.php"); exit; }

if (!isset($_GET['id'])) { header("Location: admin_usuarios.php"); exit; }

$id_user = $_GET['id'];
$mensaje = "";

// PROCESAR GUARDADO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();
        
        $nombre  = trim($_POST['nombre']);
        $usuario = trim($_POST['usuario']);
        $email   = trim($_POST['email']);
        $servicio = trim($_POST['servicio']); // Nuevo
        $rol_serv = $_POST['rol_servicio'];   // Nuevo
        $activo  = isset($_POST['activo']) ? 1 : 0;
        
        // Verificar duplicados de usuario (login)
        if (!empty($usuario)) {
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
            $chk->execute([$usuario, $id_user]);
            if ($chk->fetch()) throw new Exception("El nombre de usuario ya está en uso.");
        }

        // 1. ACTUALIZAR DATOS DE USUARIO (Incluyendo Servicio y Rol Local)
        $sql = "UPDATE usuarios SET 
                nombre_completo = :n, 
                usuario = :u, 
                email = :e, 
                servicio = :s, 
                rol_en_servicio = :r, 
                activo = :a 
                WHERE id = :id";
        
        $params = [
            ':n' => $nombre, 
            ':u' => !empty($usuario) ? $usuario : null, 
            ':e' => $email, 
            ':s' => $servicio, 
            ':r' => $rol_serv,
            ':a' => $activo, 
            ':id'=> $id_user
        ];

        // Si cambia password
        if (!empty($_POST['password'])) {
            $sql = "UPDATE usuarios SET nombre_completo=:n, usuario=:u, email=:e, servicio=:s, rol_en_servicio=:r, password=:p, activo=:a WHERE id=:id";
            $params[':p'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        $pdo->prepare($sql)->execute($params);

        // 2. ACTUALIZAR ROLES DEL SISTEMA (Tabla usuario_roles)
        $pdo->prepare("DELETE FROM usuario_roles WHERE id_usuario = ?")->execute([$id_user]);
        if (isset($_POST['roles'])) {
            $stmtR = $pdo->prepare("INSERT INTO usuario_roles (id_usuario, id_rol) VALUES (?, ?)");
            foreach ($_POST['roles'] as $rid) $stmtR->execute([$id_user, $rid]);
        }

        $pdo->commit();
        $mensaje = '<div class="alert alert-success">✅ Datos actualizados correctamente. <a href="admin_usuarios.php">Volver a la lista</a></div>';

    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// OBTENER DATOS ACTUALES
$u = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$u->execute([$id_user]);
$usuario_data = $u->fetch();

$roles = $pdo->query("SELECT * FROM roles ORDER BY nombre ASC")->fetchAll();
$mis_roles = $pdo->query("SELECT id_rol FROM usuario_roles WHERE id_usuario=$id_user")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Editar Usuario</h1>
    <?php echo $mensaje; ?>
    
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white">Datos de Perfil</div>
        <div class="card-body">
            <form method="POST">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold">Nombre Completo</label>
                        <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($usuario_data['nombre_completo']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold">Usuario (Login)</label>
                        <input type="text" name="usuario" class="form-control" value="<?php echo htmlspecialchars($usuario_data['usuario']); ?>" placeholder="Opcional">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($usuario_data['email']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold">Contraseña</label>
                        <input type="password" name="password" class="form-control" placeholder="(Dejar vacío para no cambiar)">
                    </div>
                </div>

                <hr>

                <h5 class="text-secondary mb-3"><i class="fas fa-id-badge me-2"></i> Asignación de Servicio</h5>
                <div class="row mb-3 p-3 bg-light rounded border">
                    <div class="col-md-6">
                        <label class="fw-bold text-primary">Nombre del Servicio / Área</label>
                        <input type="text" name="servicio" class="form-control fw-bold" 
                               value="<?php echo htmlspecialchars($usuario_data['servicio']); ?>" 
                               placeholder="Ej: Laboratorio, Dirección Operativa, etc.">
                        <div class="form-text">Esto define a qué área pertenece (y qué nombre sale en los PDF).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold text-primary">Rol dentro del Servicio</label>
                        <select name="rol_servicio" class="form-select">
                            <option value="">-- Seleccionar --</option>
                            <option value="Responsable" <?php echo ($usuario_data['rol_en_servicio'] == 'Responsable') ? 'selected' : ''; ?>>Responsable (Puede pedir)</option>
                            <option value="Personal" <?php echo ($usuario_data['rol_en_servicio'] == 'Personal') ? 'selected' : ''; ?>>Personal (Solo lectura interna)</option>
                        </select>
                        <div class="form-text">Define si puede iniciar pedidos para su área.</div>
                    </div>
                </div>

                <div class="mb-3 form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="activo" id="act" <?php echo ($usuario_data['activo'])?'checked':''; ?>>
                    <label class="form-check-label fw-bold" for="act">Cuenta Habilitada (Activo)</label>
                </div>

                <hr>

                <h5 class="text-secondary mb-3"><i class="fas fa-user-shield me-2"></i> Roles de Sistema (Permisos)</h5>
                <div class="row">
                    <?php foreach($roles as $r): ?>
                        <div class="col-md-3 mb-2">
                            <div class="card h-100 shadow-sm <?php echo in_array($r['id'], $mis_roles)?'border-success bg-success bg-opacity-10':''; ?>">
                                <div class="card-body py-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="<?php echo $r['id']; ?>" id="rol_<?php echo $r['id']; ?>" <?php echo in_array($r['id'], $mis_roles)?'checked':''; ?>>
                                        <label class="form-check-label fw-bold" for="rol_<?php echo $r['id']; ?>">
                                            <?php echo htmlspecialchars($r['nombre']); ?>
                                        </label>
                                        <div class="small text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($r['descripcion']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-end mt-4">
                    <a href="admin_usuarios.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary btn-lg px-5">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>