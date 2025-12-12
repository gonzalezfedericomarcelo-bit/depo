<?php
// Archivo: registro.php
// Propósito: Formulario de registro con campo Usuario

require 'db.php';

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $usuario = trim($_POST['usuario']); // Nuevo campo
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $pass2 = $_POST['confirm_password'];
    
    // Campos
    $destino = $_POST['destino'];
    $servicio = $_POST['servicio'];
    $grado = $_POST['grado_militar'];
    $rol_serv = $_POST['rol_servicio'];
    $telefono = $_POST['telefono'];
    $interno = $_POST['interno'];

    try {
        // 1. Validaciones
        if ($pass != $pass2) throw new Exception("Las contraseñas no coinciden.");
        
        // 2. Validar duplicidad de correo
        $stmtEmail = $pdo->prepare("SELECT id FROM usuarios WHERE email = :e");
        $stmtEmail->execute([':e' => $email]);
        if ($stmtEmail->fetch()) throw new Exception("El correo ya está registrado.");

        // 3. Validar duplicidad de usuario (si se ingresó uno)
        if (!empty($usuario)) {
            $stmtUser = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :u");
            $stmtUser->execute([':u' => $usuario]);
            if ($stmtUser->fetch()) throw new Exception("El nombre de usuario '$usuario' ya está en uso.");
        }

        // 4. Validar Responsable único
        if ($rol_serv == 'Responsable') {
            $stmtCheck = $pdo->prepare("SELECT count(*) FROM usuarios WHERE servicio = :serv AND rol_en_servicio = 'Responsable' AND activo = 1");
            $stmtCheck->execute([':serv' => $servicio]);
            if ($stmtCheck->fetchColumn() > 0) {
                throw new Exception("Ya existe un Responsable activo para el servicio de $servicio.");
            }
        }

        // 5. Insertar
        $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO usuarios (nombre_completo, usuario, email, password, destino, servicio, grado_militar, rol_en_servicio, telefono, numero_interno, activo, validado_por_admin) 
                VALUES (:nom, :user, :mail, :pass, :dest, :serv, :grado, :rol, :tel, :int, 1, 0)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nom' => $nombre, ':user' => !empty($usuario) ? $usuario : null, 
            ':mail' => $email, ':pass' => $pass_hash,
            ':dest' => $destino, ':serv' => $servicio, ':grado' => $grado,
            ':rol' => $rol_serv, ':tel' => $telefono, ':int' => $interno
        ]);

        // 6. Notificar
        $stmtRol = $pdo->query("SELECT id FROM roles WHERE nombre = 'Administrador' LIMIT 1");
        $idRolAdmin = $stmtRol->fetchColumn();

        if ($idRolAdmin) {
            $msj = "Nuevo registro: $nombre ($servicio). Requiere validación.";
            $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?, ?, ?)")
                ->execute([$idRolAdmin, $msj, 'admin_usuarios.php']);
        }

        $mensaje = '<div class="alert alert-success">✅ Solicitud enviada. El administrador debe aprobar tu cuenta. <a href="index.php" class="fw-bold">Volver al Login</a></div>';

    } catch (Exception $e) {
        $mensaje = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Policlínica ACTIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="card shadow-lg my-4" style="max-width: 700px; width: 100%;">
        <div class="card-header bg-primary text-white text-center py-3">
            <h4 class="mb-0">Solicitud de Acceso</h4>
            <small>Personal de Servicios</small>
        </div>
        <div class="card-body p-4">
            <?php echo $mensaje; ?>
            <form method="POST">
                <h6 class="text-primary mb-3">Datos Personales</h6>
                <div class="row mb-3">
                    <div class="col-md-6"><label class="form-label small fw-bold">Nombre Completo *</label><input type="text" name="nombre" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Usuario (Opcional)</label><input type="text" name="usuario" class="form-control" placeholder="Ej: ggonzalez"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6"><label class="form-label small fw-bold">Correo Electrónico *</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Grado / Cargo</label><input type="text" name="grado_militar" class="form-control"></div>
                </div>
                
                <div class="row mb-3">
                     <div class="col-md-6"><label class="form-label small fw-bold">Teléfono *</label><input type="text" name="telefono" class="form-control" required></div>
                </div>

                <hr>
                <h6 class="text-primary mb-3">Datos del Servicio</h6>
                <div class="row mb-3">
                    <div class="col-md-6"><label class="form-label small fw-bold">Destino *</label><input type="text" name="destino" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">N° Interno</label><input type="text" name="interno" class="form-control"></div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Servicio *</label>
                        <select name="servicio" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <option value="Laboratorio">Laboratorio</option>
                            <option value="Odontología">Odontología</option>
                            <option value="Guardia">Guardia</option>
                            <option value="Internación">Internación</option>
                            <option value="Rayos">Rayos</option>
                            <option value="Enfermería">Enfermería</option>
                            <option value="Farmacia">Farmacia</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Rol en el Servicio *</label>
                        <select name="rol_servicio" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <option value="Responsable">Responsable de Pedidos</option>
                            <option value="Personal">Personal (Sin pedidos)</option>
                        </select>
                    </div>
                </div>

                <hr>
                <div class="row mb-4">
                    <div class="col-md-6"><label class="form-label small fw-bold">Contraseña *</label><input type="password" name="password" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Confirmar *</label><input type="password" name="confirm_password" class="form-control" required></div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">ENVIAR SOLICITUD</button>
            </form>
        </div>
        <div class="card-footer text-center bg-white border-0 pb-4">
            <a href="index.php" class="text-decoration-none">← Volver al Login</a>
        </div>
    </div>
</body>
</html>