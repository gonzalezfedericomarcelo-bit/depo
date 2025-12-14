<?php
// Archivo: perfil.php
// Propósito: Perfil con Firma Full Screen y Corrección de Scroll Móvil

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

$id_user = $_SESSION['user_id'];
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();
        
        // 1. Password
        if (!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET password=? WHERE id=?")->execute([$pass, $id_user]);
        }

        // 2. Usuario
        if (!empty($_POST['usuario'])) {
            $u_new = trim($_POST['usuario']);
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
            $chk->execute([$u_new, $id_user]);
            if ($chk->fetch()) throw new Exception("El usuario ya existe.");
            
            $pdo->prepare("UPDATE usuarios SET usuario=? WHERE id=?")->execute([$u_new, $id_user]);
            $_SESSION['user_login'] = $u_new;
        }

        // 3. Firma
        if (!empty($_POST['firma_base64'])) {
            $data_uri = $_POST['firma_base64'];
            $encoded_image = explode(",", $data_uri)[1];
            $decoded_image = base64_decode($encoded_image);
            
            $filename = 'firma_' . $id_user . '_' . time() . '.png';
            $path = 'uploads/firmas/' . $filename;
            
            if(!is_dir('uploads/firmas')) mkdir('uploads/firmas', 0777, true);
            
            if (file_put_contents($path, $decoded_image)) {
                $pdo->prepare("UPDATE usuarios SET firma_digital=? WHERE id=?")->execute([$path, $id_user]);
            }
        }

        $pdo->commit();
        $mensaje = '<div class="alert alert-success shadow-sm border-0">✅ Datos guardados correctamente.</div>';

    } catch (Exception $e) {
        $pdo->rollBack(); 
        $mensaje = '<div class="alert alert-danger shadow-sm border-0">Error: '.$e->getMessage().'</div>';
    }
}

$u = $pdo->query("SELECT * FROM usuarios WHERE id=$id_user")->fetch();
?>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

<style>
    /* Bloquea el scroll cuando tocas el canvas (CRÍTICO PARA MÓVIL) */
    canvas {
        touch-action: none; 
    }
    
    .firma-preview-box {
        border: 2px dashed #ccc;
        background-color: #f8f9fa;
        min-height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }
</style>

<div class="container-fluid px-4">
    <h1 class="mt-4 text-dark">Mi Perfil</h1>
    <?php echo $mensaje; ?>

    <form method="POST" id="formPerfil">
        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary">Datos de Cuenta</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="fw-bold small">Nombre</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($u['nombre_completo']); ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold small">Usuario (Login)</label>
                            <input type="text" name="usuario" class="form-control fw-bold" value="<?php echo htmlspecialchars($u['usuario']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold small">Contraseña</label>
                            <input type="password" name="password" class="form-control" placeholder="Escriba para cambiarla">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-primary">Firma Digital</h6>
                        <?php if($u['firma_digital']): ?><span class="badge bg-success">Configurada</span><?php endif; ?>
                    </div>
                    <div class="card-body text-center">
                        
                        <label class="small text-muted fw-bold mb-2 align-self-start d-block">Firma Actual / Nueva:</label>
                        <div class="firma-preview-box mb-3">
                            <img id="img-preview" src="<?php echo $u['firma_digital'] ? $u['firma_digital'].'?t='.time() : ''; ?>" 
                                 style="max-height: 80px; max-width: 100%; display: <?php echo $u['firma_digital'] ? 'block' : 'none'; ?>;">
                            <span id="txt-preview" class="text-muted small fst-italic" style="display: <?php echo $u['firma_digital'] ? 'none' : 'block'; ?>;">
                                Sin firma cargada
                            </span>
                        </div>

                        <button type="button" class="btn btn-dark w-100 py-3 shadow-sm mb-2 fw-bold" data-bs-toggle="modal" data-bs-target="#modalFirma">
                            <i class="fas fa-pen-nib me-2"></i> FIRMAR EN PANTALLA COMPLETA
                        </button>
                        <small class="text-muted d-block">Recomendado: Gire el celular para mayor comodidad.</small>

                        <input type="hidden" name="firma_base64" id="firma_hidden">
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end mb-5">
            <button type="submit" class="btn btn-primary fw-bold px-5 btn-lg shadow">GUARDAR CAMBIOS</button>
        </div>
    </form>
</div>

<div class="modal fade" id="modalFirma" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-signature me-2"></i> Dibuje su Firma</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-light d-flex align-items-center justify-content-center" style="position: relative; overflow: hidden;">
                
                <div id="signature-pad-wrapper" style="width: 95%; height: 80%; background: #fff; border: 2px dashed #333; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                    <canvas id="signature-pad" style="width: 100%; height: 100%; display: block;"></canvas>
                    
                    <div style="position: absolute; bottom: 25%; left: 5%; right: 5%; border-bottom: 2px solid #000; opacity: 0.3; pointer-events: none;"></div>
                    <div style="position: absolute; bottom: 5%; right: 5%; color: #999; font-size: 0.8rem; pointer-events: none;">Firme sobre la línea</div>
                </div>

            </div>
            <div class="modal-footer justify-content-between bg-white">
                <button type="button" class="btn btn-outline-danger" id="clear-btn"><i class="fas fa-trash me-2"></i>Borrar</button>
                <div class="text-end">
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success fw-bold px-4" id="save-signature-btn"><i class="fas fa-check me-2"></i> Usar esta Firma</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var wrapper = document.getElementById('signature-pad-wrapper');
    var canvas = document.getElementById('signature-pad');
    var signaturePad;

    // Inicializar al abrir el modal (para asegurar dimensiones correctas)
    var myModal = document.getElementById('modalFirma');
    
    myModal.addEventListener('shown.bs.modal', function () {
        if (!signaturePad) {
            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)', // Negro Absoluto
                minWidth: 2,
                maxWidth: 4
            });
        }
        resizeCanvas();
    });

    // AJUSTE DE TAMAÑO INTELIGENTE (No borra la firma al scrollear o girar)
    function resizeCanvas() {
        if(!signaturePad) return;

        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        
        // 1. Guardar trazos actuales
        var data = signaturePad.toData();

        // 2. Redimensionar
        canvas.width = wrapper.offsetWidth * ratio;
        canvas.height = wrapper.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);

        // 3. Restaurar trazos (Así no se borra al girar pantalla)
        signaturePad.clear(); // Limpia el bitmap técnico
        signaturePad.fromData(data); // Redibuja los vectores guardados
    }

    window.addEventListener("resize", resizeCanvas);

    // Botón Borrar
    document.getElementById('clear-btn').addEventListener('click', function () {
        if(signaturePad) signaturePad.clear();
    });

    // Botón "Usar esta Firma"
    document.getElementById('save-signature-btn').addEventListener('click', function () {
        if (signaturePad.isEmpty()) {
            alert("Por favor dibuje su firma.");
        } else {
            // Guardar en input hidden
            var dataURL = signaturePad.toDataURL('image/png');
            document.getElementById('firma_hidden').value = dataURL;
            
            // Actualizar vista previa
            document.getElementById('img-preview').src = dataURL;
            document.getElementById('img-preview').style.display = 'block';
            document.getElementById('txt-preview').style.display = 'none';
            
            // Cerrar modal
            var modalInstance = bootstrap.Modal.getInstance(myModal);
            modalInstance.hide();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>