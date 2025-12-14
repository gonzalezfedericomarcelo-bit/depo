<?php
// Archivo: pedidos_solicitud_interna_suministros.php
// Propósito: Solicitud de Suministros (Clon Visual Exacto de Insumos)

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// 1. VERIFICAR PERMISO
if (!tienePermiso('solicitar_suministros')) {
    echo "<div class='container mt-5 alert alert-danger'>⛔ Acceso Restringido.</div>"; include 'includes/footer.php'; exit;
}

// 2. BUSCAR CAMPAÑAS (Suministros)
$hoy = date('Y-m-d');
$stmtCamp = $pdo->prepare("SELECT * FROM compras_planificaciones WHERE estado = 'abierta' AND fecha_fin >= :hoy AND tipo_insumo = 'suministros'");
$stmtCamp->execute([':hoy' => $hoy]);
$campanas = $stmtCamp->fetchAll();

// 3. VALIDAR SI YA PIDIÓ
$ya_solicito = false;
$id_campana = $_GET['campana'] ?? null;
if ($id_campana) {
    $stmtCheck = $pdo->prepare("SELECT id FROM pedidos_servicio WHERE id_planificacion = ? AND id_usuario_solicitante = ?");
    $stmtCheck->execute([$id_campana, $_SESSION['user_id']]);
    if ($stmtCheck->fetch()) $ya_solicito = true;
}

// 4. PROCESAR EL PEDIDO
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$ya_solicito) {
    try {
        $pdo->beginTransaction();
        
        $primerPaso = $pdo->query("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_suministros' ORDER BY paso_orden ASC LIMIT 1")->fetch();
        $id_plan_post = !empty($_POST['id_planificacion']) ? $_POST['id_planificacion'] : null;
        
        // Datos del Formulario
        $prioridad = $_POST['prioridad'] ?? 'Normal';
        $frecuencia = $_POST['frecuencia'] ?? 'Eventual';

        // Insertar Cabecera
        $sql = "INSERT INTO pedidos_servicio (
                    tipo_insumo, proceso_origen, id_usuario_solicitante, servicio_solicitante, 
                    estado, paso_actual_id, id_planificacion, prioridad, frecuencia_uso
                ) VALUES (
                    'suministros', 'movimiento_suministros', :uid, :serv, 
                    :est, :paso, :plan, :prio, :frec
                )";
        
        $stmt = $pdo->prepare($sql);
        $estado_ini = $primerPaso ? $primerPaso['nombre_estado'] : 'pendiente_aprobacion';
        $paso_ini = $primerPaso ? $primerPaso['id'] : 0;

        $stmt->execute([
            ':uid'  => $_SESSION['user_id'], 
            ':serv' => $_SESSION['user_data']['servicio'], 
            ':est'  => $estado_ini, 
            ':paso' => $paso_ini, 
            ':plan' => $id_plan_post,
            ':prio' => $prioridad,
            ':frec' => $frecuencia
        ]);
        
        $id_pedido = $pdo->lastInsertId();

        // Insertar Ítems (A la tabla pedidos_items usando id_suministro)
        $stmtItem = $pdo->prepare("INSERT INTO pedidos_items (id_pedido, id_suministro, detalle_personalizado, cantidad_solicitada) VALUES (:id, :ids, :det, :cant)");
        
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $it) {
                $id_sum = !empty($it['id']) ? $it['id'] : null;
                $det = !empty($it['detalle']) ? $it['detalle'] : null;
                
                if ($it['cantidad'] > 0) {
                    $stmtItem->execute([':id'=>$id_pedido, ':ids'=>$id_sum, ':det'=>$det, ':cant'=>$it['cantidad']]);
                }
            }
        }

        // Notificación
        $rolDestino = $primerPaso ? $primerPaso['id_rol_responsable'] : obtenerIdRolPorPermiso('gestion_stock_suministros');
        if ($rolDestino) {
            $msg = "Solicitud Suministros ($prioridad): ".$_SESSION['user_data']['servicio'];
            $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?,?,?)")->execute([$rolDestino, $msg, "pedidos_ver.php?id=$id_pedido"]);
        }

        $pdo->commit();
        echo "<script>window.location='dashboard.php?msg=ok';</script>";
        
    } catch (Exception $e) { 
        $pdo->rollBack(); 
        echo "<div class='alert alert-danger'>Error: ".$e->getMessage()."</div>"; 
    }
}

// 5. CARGAR CATÁLOGO (Suministros Generales)
$catalogo = $pdo->query("SELECT * FROM suministros_generales WHERE stock_actual > 0 ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid px-4">
    
    <h1 class="mt-4 text-primary">Solicitud de Suministros (Autónoma)</h1>
    <p class="text-muted mb-4">Utilice este formulario para pedidos de reposición diaria, urgencias o extraordinarios de papelería y limpieza.</p>
    
    <form method="GET" id="formCampana">
        <?php if(count($campanas) > 0): ?>
        <div class="alert alert-info shadow-sm mb-4 border-info">
            <label class="fw-bold"><i class="fas fa-bullhorn me-2"></i> Campañas Activas:</label>
            <select name="campana" class="form-select fw-bold border-info mt-1" onchange="document.getElementById('formCampana').submit()">
                <option value="">-- Pedido Autónomo --</option>
                <?php foreach($campanas as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($id_campana == $c['id'])?'selected':''; ?>><?php echo htmlspecialchars($c['titulo']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($ya_solicito): ?>
        <div class="alert alert-success text-center p-5"><h4>✅ ¡Solicitud enviada para esta campaña!</h4></div>
    <?php else: ?>
        
        <form method="POST">
            <input type="hidden" name="id_planificacion" value="<?php echo $id_campana; ?>">
            <input type="hidden" name="prioridad" id="inputPrioridad" value="Normal">
            
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="m-0 fw-bold">1. Datos del Pedido</h5>
                </div>
                <div class="card-body bg-light">
                    <div class="mb-3">
                        <label class="fw-bold text-dark mb-2">Prioridad:</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary fw-bold px-4" id="btn-normal" onclick="setPrioridad('Normal')">Normal (Reposición)</button>
                            <button type="button" class="btn btn-light border fw-bold px-4 text-dark" id="btn-urgente" onclick="setPrioridad('Urgente')">🔥 Urgente</button>
                            <button type="button" class="btn btn-light border fw-bold px-4 text-danger" id="btn-extra" onclick="setPrioridad('Extraordinaria')">⚠️ Extraordinaria</button>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <label class="fw-bold text-dark mb-1">Frecuencia de Uso:</label>
                            <select name="frecuencia" class="form-select">
                                <option value="Diaria">Diaria</option>
                                <option value="Semanal">Semanal</option>
                                <option value="Mensual">Mensual</option>
                                <option value="Eventual">Eventual</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">2. Ítems a Solicitar</h5>
                    <div>
                        <button type="button" class="btn btn-outline-primary fw-bold me-2" onclick="addRowStock()">+ Catálogo</button>
                        <button type="button" class="btn btn-outline-dark fw-bold" onclick="addRowManual()">+ Manual</button>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <table class="table table-striped mb-0 align-middle">
                        <tbody id="bodyItems"></tbody>
                    </table>
                    <div id="empty-msg" class="text-center p-5 text-muted">
                        Agregue ítems al pedido.
                    </div>
                </div>
                
                <div class="card-footer bg-white text-end p-3 border-top-0">
                    <button type="submit" class="btn btn-primary btn-lg fw-bold px-5">ENVIAR AL DEPÓSITO</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<div id="opts" style="display:none;">
    <option value="">-- Seleccionar Suministro --</option>
    <?php foreach($catalogo as $s) echo "<option value='{$s['id']}'>{$s['nombre']} (" . ($s['unidad_medida'] ?? 'Unid.') . ")</option>"; ?>
</div>

<script>
// Lógica de Prioridad (Visual)
function setPrioridad(valor) {
    document.getElementById('inputPrioridad').value = valor;
    
    // Resetear estilos
    document.getElementById('btn-normal').className = 'btn btn-light border fw-bold px-4 text-dark';
    document.getElementById('btn-urgente').className = 'btn btn-light border fw-bold px-4 text-dark';
    document.getElementById('btn-extra').className = 'btn btn-light border fw-bold px-4 text-danger';
    
    // Aplicar estilo activo
    if(valor === 'Normal') document.getElementById('btn-normal').className = 'btn btn-primary fw-bold px-4';
    if(valor === 'Urgente') document.getElementById('btn-urgente').className = 'btn btn-warning fw-bold px-4 text-dark';
    if(valor === 'Extraordinaria') document.getElementById('btn-extra').className = 'btn btn-danger fw-bold px-4 text-white';
}

// Lógica de Tabla
let rc = 0;
const msg = document.getElementById('empty-msg');

function check(){ 
    msg.style.display = (document.getElementById('bodyItems').children.length === 0) ? 'block' : 'none'; 
}

function addRowStock() { 
    rc++; 
    document.getElementById('bodyItems').insertAdjacentHTML('beforeend', 
    `<tr>
        <td class="p-3">
            <select name="items[${rc}][id]" class="form-select" required>${document.getElementById('opts').innerHTML}</select>
        </td>
        <td width="150" class="p-3">
            <div class="input-group">
                <span class="input-group-text">Cant.</span>
                <input type="number" name="items[${rc}][cantidad]" class="form-control text-center" value="1" min="1">
            </div>
        </td>
        <td width="50" class="p-3 text-center">
            <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="this.closest('tr').remove();check()"><i class="fas fa-trash"></i></button>
        </td>
    </tr>`); 
    check(); 
}

function addRowManual() { 
    rc++; 
    document.getElementById('bodyItems').insertAdjacentHTML('beforeend', 
    `<tr class="table-info">
        <td class="p-3">
            <input type="text" name="items[${rc}][detalle]" class="form-control" placeholder="Especifique nombre del suministro..." required>
        </td>
        <td width="150" class="p-3">
            <div class="input-group">
                <span class="input-group-text">Cant.</span>
                <input type="number" name="items[${rc}][cantidad]" class="form-control text-center" value="1" min="1">
            </div>
        </td>
        <td width="50" class="p-3 text-center">
            <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="this.closest('tr').remove();check()"><i class="fas fa-trash"></i></button>
        </td>
    </tr>`); 
    check(); 
}

check();
</script>

<?php include 'includes/footer.php'; ?>