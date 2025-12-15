<?php
// Archivo: campana_carga_suministros.php
// Propósito: Carga de Suministros (CORREGIDO: Estilos restaurados y lógica de borrador)

// 1. Buffer de salida para evitar errores de headers
ob_start();

require 'db.php';

// IMPORTANTE: Primero la lógica de redirección, ANTES de cargar HTML (Header/Sidebar)
// Si procesamos el POST y redirigimos, no queremos haber pintado el HTML todavía.

if (!tienePermiso('solicitar_suministros')) { 
    die("Acceso denegado."); 
}

$id_campana = $_GET['campana'] ?? 0;
$mensaje_estado = "";

// --- LÓGICA DE PROCESAMIENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar sesión y permisos nuevamente
    if(!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

    try {
        $pdo->beginTransaction();
        
        $accion = $_POST['accion']; // 'guardar_parcial' o 'finalizar'
        $id_campana_post = $_GET['campana'];
        
        // Validaciones básicas de la campaña
        $campana = $pdo->query("SELECT * FROM compras_planificaciones WHERE id=$id_campana_post")->fetch();
        if (!$campana) throw new Exception("Campaña inválida");
        
        $ahora = date('Y-m-d H:i:s');
        if ($ahora > $campana['fecha_fin']) throw new Exception("La campaña ha cerrado.");

        // Buscar pedido existente
        $stmtPed = $pdo->prepare("SELECT * FROM pedidos_servicio WHERE id_planificacion = ? AND id_usuario_solicitante = ?");
        $stmtPed->execute([$id_campana_post, $_SESSION['user_id']]);
        $pedido = $stmtPed->fetch();

        // Validar si ya estaba finalizado
        if ($pedido && $pedido['estado'] != 'en_carga') throw new Exception("Este pedido ya fue enviado anteriormente.");

        // A. Crear o Recuperar ID Pedido
        if (!$pedido) {
            $stmtInsert = $pdo->prepare("INSERT INTO pedidos_servicio (tipo_insumo, proceso_origen, id_usuario_solicitante, servicio_solicitante, estado, id_planificacion) VALUES ('suministros', 'movimiento_suministros', ?, ?, 'en_carga', ?)");
            $stmtInsert->execute([$_SESSION['user_id'], $_SESSION['user_data']['servicio'], $id_campana_post]);
            $id_pedido = $pdo->lastInsertId();
        } else {
            $id_pedido = $pedido['id'];
        }

        // B. Guardar Ítems (Limpiar y reinsertar)
        $pdo->prepare("DELETE FROM pedidos_items WHERE id_pedido = ?")->execute([$id_pedido]);
        $stmtItem = $pdo->prepare("INSERT INTO pedidos_items (id_pedido, id_suministro, detalle_personalizado, cantidad_solicitada) VALUES (?, ?, ?, ?)");
        
        $items_guardados = 0;
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $it) {
                if ($it['cantidad'] > 0) {
                    $id_stock = !empty($it['id']) ? $it['id'] : null;
                    $det = !empty($it['detalle']) ? $it['detalle'] : null;
                    
                    if($id_stock || $det) {
                        $stmtItem->execute([$id_pedido, $id_stock, $det, $it['cantidad']]);
                        $items_guardados++;
                    }
                }
            }
        }

        // C. Acciones
        if ($accion == 'finalizar') {
            if ($items_guardados == 0) throw new Exception("El pedido está vacío.");

            // Avanzar estado
            $primerPaso = $pdo->query("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_suministros' ORDER BY paso_orden ASC LIMIT 1")->fetch();
            $estado_destino = $primerPaso ? $primerPaso['nombre_estado'] : 'pendiente_logistica';
            
            $pdo->prepare("UPDATE pedidos_servicio SET estado = ?, paso_actual_id = ?, fecha_solicitud = NOW() WHERE id = ?")
                ->execute([$estado_destino, $primerPaso['id'] ?? 0, $id_pedido]);
            
            // Notificar
            if($primerPaso['id_rol_responsable']) {
                $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")
                    ->execute([$primerPaso['id_rol_responsable'], "Campaña Suministros RECIBIDA: ".$_SESSION['user_data']['servicio'], "pedidos_ver.php?id=$id_pedido"]);
            }
            $redirect_status = 'sent';

        } else {
            // Guardar Parcial: Forzamos estado 'en_carga'
            $pdo->prepare("UPDATE pedidos_servicio SET estado = 'en_carga' WHERE id = ?")->execute([$id_pedido]);
            $redirect_status = 'saved';
        }

        $pdo->commit();
        
        // Redirección limpia (Evita pantalla blanca y reenvío)
        header("Location: campana_carga_suministros.php?campana=$id_campana_post&status=$redirect_status");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = urlencode($e->getMessage());
        header("Location: campana_carga_suministros.php?campana=$id_campana_post&error=$error");
        exit;
    }
}

// --- FIN LÓGICA PROCESAMIENTO ---
// AHORA SÍ cargamos el HTML (Styles, Nav, Sidebar)
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// Lógica de visualización (GET)
if(!$id_campana) {
    // Selector de Campañas
    $hoy = date('Y-m-d H:i:s');
    $campanas = $pdo->query("SELECT * FROM compras_planificaciones WHERE estado='abierta' AND tipo_insumo='suministros' AND fecha_fin >= '$hoy'")->fetchAll();
    
    echo "<div class='container px-4 mt-4'>
            <h2 class='mb-4 text-warning fw-bold text-dark'><i class='fas fa-boxes me-2'></i> Campañas Disponibles</h2>";
    if(count($campanas) > 0) {
        echo "<div class='list-group shadow-sm'>";
        foreach($campanas as $c) {
            $cierre = date('d/m/Y H:i', strtotime($c['fecha_fin']));
            echo "<a href='?campana={$c['id']}' class='list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3'>
                    <div><h5 class='mb-1 fw-bold'>{$c['titulo']}</h5><small class='text-muted'>Cierra: $cierre hs</small></div>
                    <button class='btn btn-warning btn-sm fw-bold text-dark'>Ingresar <i class='fas fa-arrow-right'></i></button>
                  </a>";
        }
        echo "</div>";
    } else {
        echo "<div class='alert alert-light border text-center py-5'>No hay campañas activas.</div>";
    }
    echo "</div>";
    include 'includes/footer.php'; exit;
}

// Datos Campaña
$campana = $pdo->query("SELECT * FROM compras_planificaciones WHERE id=$id_campana")->fetch();
if(!$campana) { echo "<div class='container mt-4 alert alert-danger'>Campaña no encontrada.</div>"; include 'includes/footer.php'; exit; }

$ahora = date('Y-m-d H:i:s');
$vencida = ($ahora > $campana['fecha_fin']);

// Datos Pedido Actual
$sqlPed = "SELECT * FROM pedidos_servicio WHERE id_planificacion = ? AND id_usuario_solicitante = ?";
$stmtPed = $pdo->prepare($sqlPed);
$stmtPed->execute([$id_campana, $_SESSION['user_id']]);
$pedido = $stmtPed->fetch();

$finalizado = ($pedido && $pedido['estado'] != 'en_carga');

// Items del Pedido
$items_actuales = [];
if ($pedido) {
    $sqlItems = "SELECT pi.*, sg.nombre as nombre_catalogo, sg.unidad_medida 
                 FROM pedidos_items pi 
                 LEFT JOIN suministros_generales sg ON pi.id_suministro = sg.id 
                 WHERE pi.id_pedido = ?";
    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute([$pedido['id']]);
    $items_actuales = $stmtItems->fetchAll();
}

$catalogo = $pdo->query("SELECT * FROM suministros_generales WHERE stock_actual > 0 ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid px-4 fade-in">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="fas fa-box-open me-2 text-warning"></i> <?php echo htmlspecialchars($campana['titulo']); ?></h2>
            <div class="mt-1">
                <?php if($vencida): ?>
                    <span class="badge bg-danger">CERRADA</span>
                <?php else: ?>
                    <span class="badge bg-success">ABIERTA</span>
                    <span class="badge bg-light text-dark border ms-1" id="countdown"></span>
                <?php endif; ?>
            </div>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">Volver</a>
    </div>

    <?php if($finalizado): ?>
        <div class="alert alert-success shadow py-4">
            <h4 class="alert-heading"><i class="fas fa-check-circle"></i> Pedido Enviado</h4>
            <p class="mb-0">Ya has finalizado tu carga. El pedido está en revisión.</p>
        </div>
    <?php elseif($vencida): ?>
        <div class="alert alert-warning shadow py-4">
            <h4 class="alert-heading"><i class="fas fa-clock"></i> Tiempo Finalizado</h4>
            <p>La campaña ha cerrado. No se pueden realizar más cambios.</p>
        </div>
    <?php endif; ?>

    <?php if (!$finalizado && !$vencida): ?>
    <form method="POST" id="formCampaña">
        <div class="row">
            <div class="col-lg-3 order-lg-2 mb-3">
                <div class="card shadow-sm border-0 mb-3 sticky-top" style="top:80px; z-index:1;">
                    <div class="card-header bg-dark text-white fw-bold">Acciones</div>
                    <div class="card-body">
                        <button type="submit" name="accion" value="guardar_parcial" class="btn btn-light border w-100 mb-2 fw-bold text-primary">
                            <i class="fas fa-save me-2"></i> Guardar Borrador
                        </button>
                        <div class="form-text text-center mb-3 small">Guarda sin enviar.</div>
                        <hr>
                        <button type="button" onclick="confirmarEnvio()" class="btn btn-success w-100 fw-bold py-2 shadow-sm">
                            FINALIZAR Y ENVIAR <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                        <input type="hidden" name="accion" id="inputAccion" value="guardar_parcial">
                    </div>
                </div>
                
                <div class="card shadow-sm border-0 bg-light">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Agregar Artículos</h6>
                        <button type="button" class="btn btn-outline-warning text-dark w-100 mb-2 text-start fw-bold" onclick="addStock()">
                            <i class="fas fa-list me-2"></i> Desde Catálogo
                        </button>
                        <button type="button" class="btn btn-outline-dark w-100 text-start" onclick="addManual()">
                            <i class="fas fa-keyboard me-2"></i> Manual
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-9 order-lg-1">
                <div class="card shadow border-0">
                    <div class="card-header bg-warning text-dark fw-bold">
                        <i class="fas fa-clipboard-list me-2"></i> Mi Pedido (Borrador)
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 60%;">Suministro</th>
                                    <th style="width: 25%;" class="text-center">Cantidad</th>
                                    <th style="width: 15%;"></th>
                                </tr>
                            </thead>
                            <tbody id="listaItems">
                                <?php 
                                $idx = 0;
                                if(count($items_actuales) > 0):
                                    foreach($items_actuales as $it): $idx++; 
                                        $es_manual = empty($it['id_suministro']);
                                ?>
                                <tr class="fade-in-row">
                                    <td class="p-3">
                                        <?php if(!$es_manual): ?>
                                            <select name="items[<?php echo $idx; ?>][id]" class="form-select border-warning bg-light">
                                                <option value="<?php echo $it['id_suministro']; ?>" selected>
                                                    <?php echo htmlspecialchars($it['nombre_catalogo']); ?>
                                                </option>
                                                <?php // Optimización: no recargar todo el catalogo en filas existentes ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" name="items[<?php echo $idx; ?>][detalle]" class="form-control" value="<?php echo htmlspecialchars($it['detalle_personalizado']); ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3">
                                        <input type="number" name="items[<?php echo $idx; ?>][cantidad]" class="form-control text-center fw-bold" value="<?php echo $it['cantidad_solicitada']; ?>" min="1">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove(); checkEmpty();"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; 
                                endif; ?>
                            </tbody>
                        </table>
                        
                        <div id="empty-state" class="text-center py-5 text-muted" style="<?php echo ($idx>0)?'display:none':''; ?>">
                            <p>Tu pedido está vacío.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <?php elseif($pedido): ?>
        <div class="card shadow-sm mt-4 border-success">
            <div class="card-header bg-success text-white fw-bold">Resumen de lo enviado</div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Producto</th><th class="text-center">Cantidad</th></tr></thead>
                    <tbody>
                        <?php foreach($items_actuales as $it): ?>
                        <tr>
                            <td class="ps-4"><?php echo htmlspecialchars(!empty($it['nombre_catalogo']) ? $it['nombre_catalogo'] : $it['detalle_personalizado']); ?></td>
                            <td class="text-center fw-bold"><?php echo $it['cantidad_solicitada']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="opsCat" style="display:none;">
    <option value="">-- Seleccionar --</option>
    <?php foreach($catalogo as $c) echo "<option value='{$c['id']}'>".htmlspecialchars($c['nombre'])."</option>"; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let count = <?php echo $idx; ?>;

function checkEmpty(){ 
    const tbody = document.getElementById('listaItems');
    const emptyState = document.getElementById('empty-state');
    emptyState.style.display = (tbody.children.length === 0) ? 'block' : 'none';
}

function addStock(){ 
    count++; 
    const html = `
    <tr class="fade-in-row">
        <td class="p-3">
            <select name="items[${count}][id]" class="form-select border-warning select-search" required>
                ${document.getElementById('opsCat').innerHTML}
            </select>
        </td>
        <td class="p-3"><input type="number" name="items[${count}][cantidad]" class="form-control text-center fw-bold" value="1" min="1"></td>
        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove(); checkEmpty();"><i class="fas fa-trash-alt"></i></button></td>
    </tr>`;
    document.getElementById('listaItems').insertAdjacentHTML('beforeend', html); 
    checkEmpty();
}

function addManual(){ 
    count++; 
    const html = `
    <tr class="fade-in-row table-warning">
        <td class="p-3"><input type="text" name="items[${count}][detalle]" class="form-control" placeholder="Escriba producto..." required></td>
        <td class="p-3"><input type="number" name="items[${count}][cantidad]" class="form-control text-center fw-bold" value="1" min="1"></td>
        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove(); checkEmpty();"><i class="fas fa-trash-alt"></i></button></td>
    </tr>`;
    document.getElementById('listaItems').insertAdjacentHTML('beforeend', html); 
    checkEmpty();
}

function confirmarEnvio() {
    if(document.getElementById('listaItems').children.length === 0) {
        Swal.fire('Atención', 'El pedido está vacío.', 'warning');
        return;
    }
    Swal.fire({
        title: '¿Confirmar Envío?',
        text: "Una vez enviado no podrás editarlo.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Seguir editando'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('inputAccion').value = 'finalizar';
            document.getElementById('formCampaña').submit();
        }
    });
}

// Timer
const fechaFin = new Date("<?php echo $campana['fecha_fin']; ?>").getTime();
if (fechaFin > new Date().getTime()) {
    setInterval(() => {
        const now = new Date().getTime();
        const dist = fechaFin - now;
        if (dist < 0) { document.getElementById("countdown").innerText = "CERRADO"; location.reload(); return; }
        const days = Math.floor(dist / (1000 * 60 * 60 * 24));
        const hours = Math.floor((dist % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((dist % (1000 * 60 * 60)) / (1000 * 60));
        document.getElementById("countdown").innerText = `Quedan: ${days}d ${hours}h ${minutes}m`;
    }, 1000);
}

// Alertas PHP -> JS
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('status') === 'saved') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: 'Borrador Guardado',
            showConfirmButton: false,
            timer: 3000
        });
        window.history.replaceState(null, null, window.location.pathname + "?campana=<?php echo $id_campana; ?>");
    } 
    else if (params.get('status') === 'sent') {
        Swal.fire({
            icon: 'success',
            title: '¡Enviado!',
            text: 'Tu pedido fue enviado a Logística.',
            confirmButtonColor: '#198754'
        });
    }
    else if (params.get('error')) {
        Swal.fire('Error', decodeURIComponent(params.get('error')), 'error');
    }
});
</script>

<style>
.fade-in { animation: fadeIn 0.5s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<?php 
// Cierre de buffer y output final
include 'includes/footer.php'; 
ob_end_flush(); 
?>