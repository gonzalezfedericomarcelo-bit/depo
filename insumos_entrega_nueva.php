<?php
// Archivo: insumos_entrega_nueva.php
// Propósito: Entrega con Áreas Dinámicas desde DB

require 'db.php';
session_start();

$mensaje = "";

// --- PROCESAMIENTO IGUAL QUE ANTES ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (empty($_POST['items']) || empty($_POST['firma_base64'])) {
            throw new Exception("Datos incompletos.");
        }

        // Combinar Área y Servicio
        $area_final = $_POST['area_padre'];
        if (!empty($_POST['area_hijo'])) {
            $area_final .= " - " . $_POST['area_hijo'];
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO entregas (tipo_origen, id_usuario_responsable, solicitante_nombre, solicitante_area, firma_solicitante_data) VALUES ('insumos', :user, :solic_nom, :solic_area, :firma)");
        $stmt->execute([
            ':user' => $_SESSION['user_id'],
            ':solic_nom' => $_POST['solicitante_nombre'],
            ':solic_area' => $area_final,
            ':firma' => $_POST['firma_base64'] 
        ]);
        $id_entrega = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("INSERT INTO entregas_items (id_entrega, id_insumo, cantidad) VALUES (:id_ent, :id_ins, :cant)");
        $stmtStock = $pdo->prepare("UPDATE insumos_medicos SET stock_actual = stock_actual - :cant WHERE id = :id AND stock_actual >= :cant_check");
        $stmtCheck = $pdo->prepare("SELECT stock_actual, nombre FROM insumos_medicos WHERE id = :id");

        foreach ($_POST['items'] as $item) {
            $id_insumo = $item['id_insumo'];
            $cantidad = $item['cantidad'];

            if ($id_insumo && $cantidad > 0) {
                $stmtCheck->execute(['id' => $id_insumo]);
                $insumo_data = $stmtCheck->fetch();

                if ($insumo_data['stock_actual'] < $cantidad) throw new Exception("Stock insuficiente: " . $insumo_data['nombre']);

                $stmtStock->execute([':cant' => $cantidad, ':id' => $id_insumo, ':cant_check' => $cantidad]);
                if ($stmtStock->rowCount() == 0) throw new Exception("Error stock: " . $insumo_data['nombre']);

                $stmtItem->execute([':id_ent' => $id_entrega, ':id_ins' => $id_insumo, ':cant' => $cantidad]);
            }
        }

        $pdo->commit();
        header("Location: insumos_entregas.php?msg=exito&new_id=" . $id_entrega);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// Cargar Insumos
$stmtInsumos = $pdo->query("SELECT id, nombre, stock_actual FROM insumos_medicos WHERE stock_actual > 0 ORDER BY nombre ASC");
$lista_insumos = $stmtInsumos->fetchAll();

// --- CARGAR ESTRUCTURA DE ÁREAS DESDE DB ---
$areas_raw = $pdo->query("SELECT * FROM areas_servicios ORDER BY id_padre ASC, nombre ASC")->fetchAll();
$estructura_json = [];

foreach ($areas_raw as $row) {
    if ($row['id_padre'] == NULL) {
        // Es Padre
        $estructura_json[$row['nombre']] = [];
        // Buscar sus hijos
        foreach ($areas_raw as $sub) {
            if ($sub['id_padre'] == $row['id']) {
                $estructura_json[$row['nombre']][] = $sub['nombre'];
            }
        }
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

<div class="container-fluid px-4">
    <h1 class="mt-4">Nueva Entrega Insumos</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="insumos_entregas.php">Historial</a></li>
        <li class="breadcrumb-item active">Registrar Salida</li>
    </ol>
    <?php echo $mensaje; ?>

    <form method="POST" action="" id="formEntrega">
        <div class="row">
            <div class="col-lg-4">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white fw-bold">Solicitante</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label>Nombre y Apellido</label>
                            <input type="text" name="solicitante_nombre" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Área Principal</label>
                            <select name="area_padre" id="area_padre" class="form-select" required onchange="actualizarHijos()">
                                <option value="">Seleccionar...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Servicio (Opcional)</label>
                            <select name="area_hijo" id="area_hijo" class="form-select" disabled>
                                <option value="">-- General / Solo Padre --</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="fw-bold">Firma</label>
                            <div class="border rounded bg-light text-center">
                                <canvas id="signature-pad" width="300" height="200"></canvas>
                            </div>
                            <div class="text-end mt-1"><button type="button" class="btn btn-sm btn-secondary" id="clear-signature">Limpiar</button></div>
                            <input type="hidden" name="firma_base64" id="firma_base64">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between">
                        <span>Items a Retirar</span>
                        <button type="button" class="btn btn-light btn-sm" onclick="agregarFila()">+ Agregar</button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <thead><tr><th>Insumo</th><th>Cant.</th><th></th></tr></thead>
                            <tbody id="contenedor-items">
                                </tbody>
                        </table>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-success btn-lg">Confirmar Entrega</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // 1. ESTRUCTURA DINÁMICA PHP -> JS
    const estructuraAreas = <?php echo json_encode($estructura_json); ?>;
    
    const selectPadre = document.getElementById('area_padre');
    const selectHijo = document.getElementById('area_hijo');

    // Llenar Padres
    Object.keys(estructuraAreas).forEach(area => {
        let opt = document.createElement('option');
        opt.value = area;
        opt.innerHTML = area;
        selectPadre.appendChild(opt);
    });

    function actualizarHijos() {
        selectHijo.innerHTML = '<option value="">-- General / Solo Padre --</option>';
        selectHijo.disabled = true;
        
        const valPadre = selectPadre.value;
        if (valPadre && estructuraAreas[valPadre] && estructuraAreas[valPadre].length > 0) {
            selectHijo.disabled = false;
            estructuraAreas[valPadre].forEach(serv => {
                let opt = document.createElement('option');
                opt.value = serv;
                opt.innerHTML = serv;
                selectHijo.appendChild(opt);
            });
        }
    }

    // 2. FIRMA
    var canvas = document.getElementById('signature-pad');
    var signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgba(255, 255, 255, 0)' });
    document.getElementById('clear-signature').addEventListener('click', () => signaturePad.clear());
    document.getElementById('formEntrega').addEventListener('submit', (e) => {
        if (signaturePad.isEmpty()) { e.preventDefault(); alert("Firme por favor."); return; }
        document.getElementById('firma_base64').value = signaturePad.toDataURL('image/png');
    });

    // 3. ITEMS
    let contador = 0;
    const opciones = `
        <option value="">Seleccione...</option>
        <?php foreach ($lista_insumos as $in): ?>
            <option value="<?php echo $in['id']; ?>" data-stock="<?php echo $in['stock_actual']; ?>">
                <?php echo str_replace('"', '', htmlspecialchars($in['nombre'])) . " (Disp: " . $in['stock_actual'] . ")"; ?>
            </option>
        <?php endforeach; ?>
    `;

    function agregarFila() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select name="items[${contador}][id_insumo]" class="form-select" required onchange="checkStock(this)">${opciones}</select></td>
            <td><input type="number" name="items[${contador}][cantidad]" class="form-control" required min="1"></td>
            <td><button type="button" class="btn btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-trash"></i></button></td>
        `;
        document.getElementById('contenedor-items').appendChild(tr);
        contador++;
    }
    
    function checkStock(select) {
        const stock = select.options[select.selectedIndex].getAttribute('data-stock');
        const input = select.closest('tr').querySelector('input[type="number"]');
        if(stock) {
            input.max = stock;
            input.placeholder = "Máx " + stock;
        }
    }
    
    // Iniciar con 1 fila
    agregarFila();
</script>
<?php include 'includes/footer.php'; ?>