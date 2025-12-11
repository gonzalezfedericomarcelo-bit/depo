<?php
// Archivo: suministros_entrega_nueva.php
// Propósito: Entrega de Suministros con Áreas Dinámicas (Lee de la Base de Datos)

require 'db.php';
session_start();

$mensaje = "";

// 1. PROCESAMIENTO DEL FORMULARIO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (empty($_POST['items']) || empty($_POST['firma_base64'])) {
            throw new Exception("Debe seleccionar ítems y firmar la entrega.");
        }

        // Construir el Área/Servicio final combinando Padre e Hijo
        $area_final = $_POST['area_padre'];
        if (!empty($_POST['area_hijo'])) {
            $area_final .= " - " . $_POST['area_hijo'];
        }

        $pdo->beginTransaction();

        // A. Insertar Cabecera de Entrega
        $stmt = $pdo->prepare("INSERT INTO entregas (tipo_origen, id_usuario_responsable, solicitante_nombre, solicitante_area, firma_solicitante_data) VALUES ('suministros', :user, :solic_nom, :solic_area, :firma)");
        $stmt->execute([
            ':user' => $_SESSION['user_id'],
            ':solic_nom' => $_POST['solicitante_nombre'],
            ':solic_area' => $area_final, // Guardamos la combinación real
            ':firma' => $_POST['firma_base64']
        ]);
        $id_entrega = $pdo->lastInsertId();

        // B. Procesar Ítems y Descontar Stock
        $stmtItem = $pdo->prepare("INSERT INTO entregas_items (id_entrega, id_suministro, cantidad) VALUES (:id_ent, :id_sum, :cant)");
        $stmtStock = $pdo->prepare("UPDATE suministros_generales SET stock_actual = stock_actual - :cant WHERE id = :id AND stock_actual >= :cant_check");
        $stmtCheck = $pdo->prepare("SELECT stock_actual, nombre FROM suministros_generales WHERE id = :id");

        foreach ($_POST['items'] as $item) {
            $id_suministro = $item['id_suministro'];
            $cantidad = $item['cantidad'];

            if ($id_suministro && $cantidad > 0) {
                // Chequear stock
                $stmtCheck->execute(['id' => $id_suministro]);
                $dato_stock = $stmtCheck->fetch();

                if ($dato_stock['stock_actual'] < $cantidad) {
                    throw new Exception("Stock insuficiente para: " . $dato_stock['nombre']);
                }

                // Restar
                $stmtStock->execute([':cant' => $cantidad, ':id' => $id_suministro, ':cant_check' => $cantidad]);
                
                if ($stmtStock->rowCount() == 0) {
                    throw new Exception("Error al actualizar stock de: " . $dato_stock['nombre']);
                }

                $stmtItem->execute([':id_ent' => $id_entrega, ':id_sum' => $id_suministro, ':cant' => $cantidad]);
            }
        }

        $pdo->commit();
        // Redirigir para imprimir PDF
        header("Location: suministros_entregas.php?msg=exito&new_id=" . $id_entrega);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/navbar.php';

// Cargar Suministros Disponibles
$stmtSum = $pdo->query("SELECT id, nombre, codigo, stock_actual FROM suministros_generales WHERE stock_actual > 0 ORDER BY nombre ASC");
$lista_suministros = $stmtSum->fetchAll();

// --- CARGAR ESTRUCTURA DE ÁREAS DESDE LA BASE DE DATOS (IGUAL QUE EN INSUMOS) ---
$areas_raw = $pdo->query("SELECT * FROM areas_servicios ORDER BY id_padre ASC, nombre ASC")->fetchAll();
$estructura_json = [];

foreach ($areas_raw as $row) {
    if ($row['id_padre'] == NULL) {
        // Es un Área Padre
        $estructura_json[$row['nombre']] = [];
        // Buscar sus hijos (Servicios)
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
    <h1 class="mt-4">Nueva Entrega Suministros</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="suministros_entregas.php">Historial</a></li>
        <li class="breadcrumb-item active">Registrar Salida</li>
    </ol>

    <?php echo $mensaje; ?>

    <form method="POST" action="" id="formEntrega">
        <div class="row">
            <div class="col-lg-4">
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-user me-1"></i> Datos del Solicitante
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre y Apellido</label>
                            <input type="text" name="solicitante_nombre" class="form-control" required placeholder="Ej: María López">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Área Principal</label>
                            <select name="area_padre" id="area_padre" class="form-select" required onchange="actualizarHijos()">
                                <option value="">Seleccione Área...</option>
                                </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Servicio / Detalle (Opcional)</label>
                            <select name="area_hijo" id="area_hijo" class="form-select" disabled>
                                <option value="">-- General / Solo Padre --</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Firma Digital</label>
                            <div class="border rounded bg-light text-center">
                                <canvas id="signature-pad" width="300" height="200" style="touch-action: none;"></canvas>
                            </div>
                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-signature">Borrar Firma</button>
                            </div>
                            <input type="hidden" name="firma_base64" id="firma_base64">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-boxes me-1"></i> Artículos a Retirar</span>
                        <button type="button" class="btn btn-light btn-sm text-dark" onclick="agregarFila()">
                            <i class="fas fa-plus"></i> Agregar Ítem
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th width="70%">Artículo (Stock Disponible)</th>
                                        <th width="20%">Cantidad</th>
                                        <th width="10%"></th>
                                    </tr>
                                </thead>
                                <tbody id="contenedor-items">
                                    </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-check-circle me-2"></i> Confirmar Entrega
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // 1. ESTRUCTURA DINÁMICA (Traída de la DB con PHP)
    const estructuraAreas = <?php echo json_encode($estructura_json); ?>;

    const selectPadre = document.getElementById('area_padre');
    const selectHijo = document.getElementById('area_hijo');

    // Llenar el selector Padre
    Object.keys(estructuraAreas).forEach(area => {
        let opt = document.createElement('option');
        opt.value = area;
        opt.innerHTML = area;
        selectPadre.appendChild(opt);
    });

    function actualizarHijos() {
        // Reiniciar hijo
        selectHijo.innerHTML = '<option value="">-- General / Solo Padre --</option>';
        selectHijo.disabled = true;

        const valPadre = selectPadre.value;
        // Si hay hijos definidos para este padre, habilitar y llenar
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

    // 2. FIRMA DIGITAL
    var canvas = document.getElementById('signature-pad');
    var signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgba(255, 255, 255, 0)',
        penColor: 'rgb(0, 0, 0)'
    });

    document.getElementById('clear-signature').addEventListener('click', function () {
        signaturePad.clear();
    });

    document.getElementById('formEntrega').addEventListener('submit', function (e) {
        if (signaturePad.isEmpty()) {
            e.preventDefault();
            alert("Por favor, solicite la firma del usuario.");
            return;
        }
        document.getElementById('firma_base64').value = signaturePad.toDataURL('image/png');
    });

    // 3. ÍTEMS DINÁMICOS
    let contador = 1;
    const opcionesSum = `
        <option value="" data-stock="0">-- Seleccione --</option>
        <?php foreach ($lista_suministros as $s): ?>
            <option value="<?php echo $s['id']; ?>" data-stock="<?php echo $s['stock_actual']; ?>">
                <?php echo str_replace('"', '', htmlspecialchars($s['nombre'])) . " (Disp: " . $s['stock_actual'] . ")"; ?>
            </option>
        <?php endforeach; ?>
    `;

    function agregarFila() {
        const tbody = document.getElementById('contenedor-items');
        const tr = document.createElement('tr');
        tr.classList.add('fila-item');
        tr.innerHTML = `
            <td>
                <select name="items[${contador}][id_suministro]" class="form-select select-suministro" onchange="actualizarMaximo(this)" required>
                    ${opcionesSum}
                </select>
            </td>
            <td>
                <input type="number" name="items[${contador}][cantidad]" class="form-control" required min="1" max="1" placeholder="Cant.">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarFila(this)"><i class="fas fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
        contador++;
    }

    // Iniciar con 1 fila
    agregarFila();

    function eliminarFila(btn) {
        btn.closest('tr').remove();
    }

    function actualizarMaximo(select) {
        const opcion = select.options[select.selectedIndex];
        const stock = opcion.getAttribute('data-stock');
        const inputCant = select.closest('tr').querySelector('input[type="number"]');
        
        if (stock) {
            inputCant.max = stock;
            inputCant.placeholder = "Máx " + stock;
            if (parseInt(inputCant.value) > parseInt(stock)) {
                inputCant.value = stock;
            }
        }
    }
</script>

<?php include 'includes/footer.php'; ?>