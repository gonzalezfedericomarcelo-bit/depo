<?php
// Archivo: generar_pdf_planificacion.php
// Propósito: Generar PDF de Campaña (Global o Por Servicio) estilo GDE

require 'db.php';
require 'fpdf/fpdf.php';

if (!isset($_GET['id'])) die("Falta ID");
$id_plan = $_GET['id'];
$filtro_servicio = $_GET['servicio'] ?? null; // Si viene null, es GLOBAL. Si trae nombre, filtra.

// 1. OBTENER CABECERA
$stmt = $pdo->prepare("SELECT p.*, u.nombre_completo as creador 
                       FROM compras_planificaciones p 
                       JOIN usuarios u ON p.creado_por = u.id 
                       WHERE p.id = ?");
$stmt->execute([$id_plan]);
$plan = $stmt->fetch();
if (!$plan) die("Plan no encontrado");

// Determinar tablas según tipo
$tabla_items = ($plan['tipo_insumo'] == 'insumos') ? 'insumos_medicos' : 'suministros_generales';
$titulo_tipo = ($plan['tipo_insumo'] == 'insumos') ? 'INSUMOS MÉDICOS' : 'SUMINISTROS GENERALES';

// 2. OBTENER ÍTEMS (Filtrados o Totales)
$sql = "SELECT ps.servicio_solicitante, 
               COALESCE(t.nombre, pi.detalle_personalizado) as descripcion, 
               pi.cantidad_solicitada 
        FROM pedidos_items pi
        JOIN pedidos_servicio ps ON pi.id_pedido = ps.id
        LEFT JOIN $tabla_items t ON pi.id_insumo = t.id  -- (El campo id_insumo se usa para ambos en la logica anterior o id_suministro, ajustamos abajo)
        WHERE ps.id_planificacion = :id";

// Ajuste de JOIN según tipo (en tu BD usas columnas distintas en pedidos_items)
if ($plan['tipo_insumo'] == 'suministros') {
    $sql = str_replace("pi.id_insumo = t.id", "pi.id_suministro = t.id", $sql);
}

if ($filtro_servicio) {
    $sql .= " AND ps.servicio_solicitante = :serv";
}

$sql .= " ORDER BY ps.servicio_solicitante, descripcion";

$stmtItems = $pdo->prepare($sql);
$params = [':id' => $id_plan];
if ($filtro_servicio) $params[':serv'] = $filtro_servicio;
$stmtItems->execute($params);
$items = $stmtItems->fetchAll();

// --- CLASE PDF GDE ---
class PDF_Plan extends FPDF {
    public $titulo_doc;
    public $subtitulo_doc;
    public $frecuencia;

    function Header() {
        $watermark = __DIR__ . '/assets/img/logo_watermark_gris.png';
        if (file_exists($watermark)) $this->Image($watermark, 45, 80, 120, 0, 'PNG');
        
        $logo = __DIR__ . '/assets/img/logo.png';
        if (file_exists($logo)) $this->Image($logo, 10, 10, 30);

        $this->SetXY(45, 15);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(80);
        $this->MultiCell(120, 5, utf8_decode("\"2025 - Año de la Reconstrucción\""), 0, 'C');
        
        $this->SetX(45);
        $this->SetFont('Arial', '', 8);
        $this->MultiCell(120, 4, utf8_decode("Policlínica ACTIS - Gestión de Compras"), 0, 'C');

        // Datos Derecha
        $this->SetXY(165, 10);
        $this->SetTextColor(0);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(35, 6, 'PLANIFICACION', 0, 1, 'R');
        $this->SetX(165);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(35, 5, utf8_decode($this->frecuencia), 0, 1, 'R');

        $this->SetY(35);
        $this->SetDrawColor(50);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
        
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode($this->titulo_doc), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, utf8_decode($this->subtitulo_doc), 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ').$this->PageNo().'/{nb} - Generado por Sistema ACTIS', 0, 0, 'C');
    }

    function TablaHeader() {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(230);
        $this->Cell(140, 7, utf8_decode('DETALLE DEL ÍTEM'), 1, 0, 'L', true);
        $this->Cell(50, 7, utf8_decode('CANTIDAD'), 1, 1, 'C', true);
    }
}

$pdf = new PDF_Plan();
$pdf->titulo_doc = "SOLICITUD DE " . $titulo_tipo;
$pdf->frecuencia = strtoupper($plan['frecuencia_cobertura']); // EJ: TRIMESTRAL
$pdf->subtitulo_doc = $filtro_servicio ? "SERVICIO: " . strtoupper($filtro_servicio) : "CONSOLIDADO GENERAL (TODOS LOS SERVICIOS)";

$pdf->AliasNbPages();
$pdf->AddPage();

// INFO EXTRA
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(30, 6, 'Campaña:', 0, 0);
$pdf->Cell(100, 6, utf8_decode($plan['titulo']), 0, 1);
$pdf->Cell(30, 6, 'Fecha Cierre:', 0, 0);
$pdf->Cell(100, 6, date('d/m/Y H:i', strtotime($plan['fecha_fin'])), 0, 1);
$pdf->Ln(5);

// TABLA
$pdf->TablaHeader();
$pdf->SetFont('Arial', '', 9);

$total_items = 0;
$current_servicio = '';

foreach ($items as $it) {
    // Si es reporte global, agrupar visualmente por servicio
    if (!$filtro_servicio && $it['servicio_solicitante'] != $current_servicio) {
        $current_servicio = $it['servicio_solicitante'];
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(245);
        $pdf->Cell(190, 7, utf8_decode(">> " . $current_servicio), 1, 1, 'L', true);
        $pdf->SetFont('Arial', '', 9);
    }

    $pdf->Cell(140, 6, utf8_decode($it['descripcion']), 1);
    $pdf->Cell(50, 6, $it['cantidad_solicitada'], 1, 1, 'C');
    $total_items += $it['cantidad_solicitada'];
}

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(140, 8, 'TOTAL DE UNIDADES SOLICITADAS', 1, 0, 'R');
$pdf->Cell(50, 8, $total_items, 1, 1, 'C');

$pdf->Output('I', 'Planificacion_' . $id_plan . '.pdf');
?>