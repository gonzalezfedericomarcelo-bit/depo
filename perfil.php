<?php
// Archivo: perfil.php
require 'db.php';
session_start();
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$id_user = $_SESSION['user_id'];
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();
        
        // Cambio de Password
        if (!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET password=? WHERE id=?")->execute([$pass, $id_user]);
        }

        // Cambio de Usuario (Solo si está vacío o lo permite la política)
        if (!empty($_POST['usuario'])) {
            $u_new = trim($_POST['usuario']);
            // Verificar duplicado
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
            $chk->execute([$u_new, $id_user]);
            if ($chk->fetch()) throw new Exception("El usuario ya existe.");
            
            $pdo->prepare("UPDATE usuarios SET usuario=? WHERE id=?")->execute([$u_new, $id_user]);
        }

        // Firma
        if (!empty($_POST['firma_base64'])) {
            $data = base64_decode(explode(",", $_POST['firma_base64'])[1]);
            $path = 'uploads/firmas/firma_'.$id_user.'_'.time().'.png';
            if(!is_dir('uploads/firmas')) mkdir('uploads/firmas', 0777, true);
            file_put_contents($path, $data);
            $pdo->prepare("UPDATE usuarios SET firma_digital=? WHERE id=?")->execute([$path, $id_user]);
        }

        $pdo->commit();
        $mensaje = '<div class="alert alert-success">✅ Perfil actualizado.</div>';
    } catch (Exception $e) {
        $pdo->rollBack(); $mensaje = '<div class="alert alert-danger">Error: '.$e->getMessage().'</div>';
    }
}

$u = $pdo->query("SELECT * FROM usuarios WHERE id=$id_user")->fetch();
?>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

<div class="container-fluid px-4">
    <h1 class="mt-4">Mi Perfil</h1>
    <?php echo $mensaje; ?>
    <form method="POST" id="formP">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Datos de Cuenta</div>
                    <div class="card-body">
                        <div class="mb-3"><label>Nombre</label><input type="text" class="form-control" value="<?php echo $u['nombre_completo']; ?>" disabled></div>
                        <div class="mb-3"><label>Email</label><input type="text" class="form-control" value="<?php echo $u['email']; ?>" disabled></div>
                        <div class="mb-3">
                            <label>Nombre de Usuario</label>
                            <input type="text" name="usuario" class="form-control fw-bold" value="<?php echo $u['usuario']; ?>" placeholder="Configura tu usuario para login rápido">
                        </div>
                        <div class="mb-3"><label>Nueva Contraseña</label><input type="password" name="password" class="form-control" placeholder="Opcional"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Firma Digital</div>
                    <div class="card-body text-center">
                        <?php if($u['firma_digital']): ?><img src="<?php echo $u['firma_digital']; ?>" height="60" class="d-block mx-auto mb-3"><?php endif; ?>
                        <div class="border rounded"><canvas id="sig" style="width:100%; height:150px;"></canvas></div>
                        <button type="button" class="btn btn-sm btn-secondary mt-2" id="clear">Limpiar</button>
                        <input type="hidden" name="firma_base64" id="firma">
                    </div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary">Guardar Cambios</button>
    </form>
</div>
<script>
    var s = new SignaturePad(document.getElementById('sig'), {backgroundColor:'rgba(255,255,255,0)'});
    document.getElementById('clear').onclick = function(){s.clear()};
    document.getElementById('formP').onsubmit = function(){ if(!s.isEmpty()) document.getElementById('firma').value = s.toDataURL(); }
</script>
<?php include 'includes/footer.php'; ?>