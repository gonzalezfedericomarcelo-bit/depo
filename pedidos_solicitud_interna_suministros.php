<?php
// Archivo: pedidos_solicitud_interna_suministros.php
// Propósito: Solicitud Suministros (Interfaz Moderna: Catálogo + Manual + Campañas)

require 'db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// VERIFICACIÓN DE PERMISO
if (!tienePermiso('solicitar_suministros')) {
    die("<div class='container mt-5'><div class='alert alert-danger shadow-sm'>
            <h4 class='alert-heading'><i class='fas fa-lock'></i> Acceso Restringido</h4>
            <p>No tienes permiso activado para: <strong>Solicitar Suministros Generales</strong>.</p>
         </div></div>");
}

// BUSCAR CAMPAÑAS ACTIVAS
$hoy = date('Y-m-d');
$stmtCamp = $pdo->prepare("SELECT * FROM compras_planificaciones WHERE estado = 'abierta' AND fecha_fin >= :hoy");
$stmtCamp->execute([':hoy' => $hoy]);
$campanas = $stmtCamp->fetchAll();

// VALIDAR DUPLICADOS DE CAMPAÑA
$ya_solicito = false;
$id_campana = $_GET['campana'] ?? null;
if ($id_campana) {
    $stmtCheck = $pdo->prepare("SELECT id FROM pedidos_servicio WHERE id_planificacion = ? AND id_usuario_solicitante = ?");
    $stmtCheck->execute([$id_campana, $_SESSION['user_id']]);
    if ($stmtCheck->fetch()) $ya_solicito = true;
}

// PROCESAR FORMULARIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$ya_solicito) {
    try {
        $pdo->beginTransaction();

        $stmtFlujo = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = 'movimiento_suministros' ORDER BY paso_orden ASC LIMIT 1");
        $stmtFlujo->execute();
        $primerPaso = $stmtFlujo->fetch();

        if (!$primerPaso) throw new Exception("El flujo 'movimiento_suministros' no está configurado.");

        $id_plan_post = !empty($_POST['id_planificacion']) ? $_POST['id_planificacion'] : null;

        // Insertar Cabecera
        $sql = "INSERT INTO pedidos_servicio (tipo_insumo, proceso_origen, id_usuario_solicitante, servicio_solicitante, estado, paso_actual_id, id_planificacion) 
                VALUES ('suministros', 'movimiento_suministros', :uid, :serv, :est, :paso, :plan)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $_SESSION['user_id'], 
            ':serv' => $_SESSION['user_data']['servicio'] ?? 'Sin Servicio',
            ':est' => $primerPaso['nombre_estado'], 
            ':paso' => $primerPaso['id'],
            ':plan' => $id_plan_post
        ]);
        $id_pedido = $pdo->lastInsertId();

        // Insertar Ítems
        $stmtItem = $pdo->prepare("INSERT INTO pedidos_items (id_pedido, id_suministro, detalle_personalizado, cantidad_solicitada) VALUES (:idp, :ids, :det, :cant)");
        
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $it) {
                $id_sum = !empty($it['id']) ? $it['id'] : null;
                $det = !empty($it['detalle']) ? $it['detalle'] : null;
                $cant = $it['cantidad'];

                if ($cant > 0 && ($id_sum || $det)) {
                    $stmtItem->execute([
                        ':idp' => $id_pedido,
                        ':ids' => $id_sum,
                        ':det' => $det,
                        ':cant' => $cant
                    ]);
                }
            }
        }

        // Notificar
        $msj = "Nueva solicitud Suministros: " . ($_SESSION['user_data']['servicio'] ?? 'Usuario');
        if ($id_plan_post) $msj .= " (Campaña)";
        
        $pdo->prepare("INSERT INTO notificaciones (id_rol_destino, mensaje, url_destino) VALUES (?, ?, ?)")
            ->execute([$primerPaso['id_rol_responsable'], $msj, "pedidos_ver.php?id=" . $id_pedido]);

        $pdo->commit();
        echo "<script>window.location='dashboard.php?msg=solicitud_enviada';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger m-4'>Error: ".$e->getMessage()."</div>";
    }
}

// Cargar catálogo
$suministros = $pdo->query("SELECT * FROM suministros_generales WHERE stock_actual > 0 ORDER BY nombre ASC")->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Solicitar Suministros</h1>
    
    <form method="GET" id="formCampana">
        <?php if(count($campanas) > 0): ?>
        <div class="alert alert-info shadow-sm mb-4 border-info">
            <label class="fw-bold"><i class="fas fa-bullhorn me-2"></i> Campañas Activas:</label>
            <select name="campana" class="form-select fw-bold border-info mt-1" onchange="document.getElementById('formCampana').submit()">
                <option value="">-- Pedido Normal (Reposición) --</option>
                <?php foreach($campanas as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($id_campana == $c['id'])?'selected':''; ?>>
                        📌 <?php echo htmlspecialchars($c['titulo']); ?> (Cierra: <?php echo date('d/m', strtotime($c['fecha_fin'])); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($ya_solicito): ?>
        <div class="alert alert-success text-center p-5 shadow">
            <h1><i class="fas fa-check-circle display-4 mb-3"></i></h1>
            <h4>¡Ya participaste en esta campaña!</h4>
            <p>Tu solicitud fue enviada correctamente. Espera novedades de Logística.</p>
            <a href="dashboard.php" class="btn btn-secondary mt-3">Volver al Inicio</a>
        </div>
    <?php else: ?>

    <form method="POST">
        <input type="hidden" name="id_planificacion" value="<?php echo $id_campana; ?>">
        
        <div class="card mb-4 shadow-sm border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <span class="fw-bold">Armar Pedido</span>
                <div>
                    <button type="button" class="btn btn-sm btn-light border text-dark fw-bold me-2" onclick="agregarFilaStock()">
                        <i class="fas fa-list me-1"></i> + Catálogo
                    </button>
                    <button type="button" class="btn btn-sm btn-dark text-warning fw-bold" onclick="agregarFilaManual()">
                        <i class="fas fa-pen me-1"></i> + Manual
                    </button>
                </div>
            </div>
            
            <div class="card-body p-0">
                <table class="table table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Artículo / Detalle</th>
                            <th width="150" class="text-center">Cantidad</th>
                            <th width="50"></th>
                        </tr>
                    </thead>
                    <tbody id="bodyItems"></tbody>
                </table>

                <div id="empty-msg" class="text-center p-5 text-muted">
                    <i class="fas fa-cart-arrow-down fa-2x mb-2 opacity-50"></i>
                    <p class="mb-0">Utiliza los botones de arriba para agregar artículos a tu pedido.</p>
                </div>
            </div>
            
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-warning fw-bold btn-lg shadow">
                    <i class="fas fa-paper-plane me-2"></i> ENVIAR SOLICITUD
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<div id="optionsStock" style="display:none;">
    <option value="">-- Seleccionar del Catálogo --</option>
    <?php foreach($suministros as $s) { echo "<option value='".$s['id']."'>".htmlspecialchars($s['nombre'])." (Disp: ".$s['stock_actual'].")</option>"; } ?>
</div>

<script>
    let rowCount = 0;
    const tbody = document.getElementById('bodyItems');
    const msg = document.getElementById('empty-msg');

    function checkEmpty() {
        msg.style.display = (tbody.children.length === 0) ? 'block' : 'none';
    }

    // Agregar fila de Catálogo
    function agregarFilaStock() {
        rowCount++;
        let tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="items[${rowCount}][id]" class="form-select select-search fw-bold text-dark" required>
                    ${document.getElementById('optionsStock').innerHTML}
                </select>
            </td>
            <td>
                <input type="number" name="items[${rowCount}][cantidad]" class="form-control text-center fw-bold" placeholder="0" min="1" value="1">
            </td>
            <td>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove(); checkEmpty();">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        checkEmpty();
    }

    // Agregar fila Manual
    function agregarFilaManual() {
        rowCount++;
        let tr = document.createElement('tr');
        tr.className = "table-warning"; // Color diferente para distinguir
        tr.innerHTML = `
            <td>
                <input type="text" name="items[${rowCount}][detalle]" class="form-control" placeholder="Escribe aquí el producto nuevo o especial..." required>
                <div class="form-text text-dark fst-italic ms-1"><i class="fas fa-star me-1"></i> Pedido especial fuera de catálogo</div>
            </td>
            <td>
                <input type="number" name="items[${rowCount}][cantidad]" class="form-control text-center fw-bold" placeholder="0" min="1" value="1">
            </td>
            <td>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove(); checkEmpty();">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        checkEmpty();
    }

    // Inicializar estado
    checkEmpty();
</script>

<?php include 'includes/footer.php'; ?>