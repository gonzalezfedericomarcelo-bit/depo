<?php
// Archivo: generar_pdf_oc.php
// Propósito: PDF Orden de Compra Oficial (Con totales)

require 'db.php';
require 'fpdf/fpdf.php';

if (!isset($_GET['id'])) die("Falta ID");
$id_oc = $_GET['id'];

// 1. Obtener Cabecera
$stmt = $pdo->prepare("SELECT oc.*, u.nombre_completo as creador FROM ordenes_compra oc JOIN usuarios u ON oc.id_usuario_creador = u.id WHERE oc.id = ?");
$stmt->execute([$id_oc]);
$oc = $stmt->fetch();
if (!$oc) die("OC no encontrada");

// 2. Obtener Ítems
$stmtItems = $pdo->prepare("SELECT * FROM ordenes_compra_items WHERE id_oc = ?");
$stmtItems->execute([$id_oc]);
$items = $stmtItems->fetchAll();

// --- CLASE PDF ---
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',16); $this->SetTextColor(33,37,41);
        $this->Cell(0,10, iconv('UTF-8', 'ISO-8859-1', 'Policlínica ACTIS'),0,1,'R');
        $this->SetFont('Arial','B',14); $this->SetTextColor(13,110,253);
        $this->Cell(0,10, iconv('UTF-8', 'ISO-8859-1', 'ORDEN DE COMPRA'),0,1,'R');
        $this->Ln(5);
        $this->SetDrawColor(13,110,253); $this->SetLineWidth(1);
        $this->Line(10, 35, 200, 35);
        $this->Ln(10);
    }
    function Footer() {
        $this->SetY(-15); $this->SetFont('Arial','I',8); $this->SetTextColor(128);
        $this->Cell(0,10, iconv('UTF-8', 'ISO-8859-1', 'Página ').$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// INFO CABECERA
$pdf->SetFont('Arial','B',10); $pdf->SetTextColor(0);
$pdf->Cell(30,7,'NRO. ORDEN:',0,0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(70,7, iconv('UTF-8', 'ISO-8859-1', $oc['numero_oc']),0,0);

$pdf->SetFont('Arial','B',10);
$pdf->Cell(30,7,'FECHA:',0,0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(60,7, date('d/m/Y H:i', strtotime($oc['fecha_creacion'])),0,1);

$pdf->SetFont('Arial','B',10);
$pdf->Cell(30,7,'GENERADO POR:',0,0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(70,7, iconv('UTF-8', 'ISO-8859-1', $oc['creador']),0,0);

$pdf->SetFont('Arial','B',10);
$pdf->Cell(30,7,'DESTINO:',0,0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(60,7, iconv('UTF-8', 'ISO-8859-1', $oc['servicio_destino']),0,1);

$pdf->Ln(10);

// TABLA DE ÍTEMS
$pdf->SetFillColor(240,240,240); $pdf->SetDrawColor(200); $pdf->SetLineWidth(0.2);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(110,8,'PRODUCTO / DETALLE',1,0,'L',true);
$pdf->Cell(20,8,'CANT.',1,0,'C',true);
$pdf->Cell(30,8,'PRECIO UNIT.',1,0,'R',true);
$pdf->Cell(30,8,'SUBTOTAL',1,1,'R',true);

$pdf->SetFont('Arial','',9);
$total_general = 0;

foreach($items as $i) {
    $subtotal = $i['cantidad_aprobada_compra'] * $i['precio_unitario'];
    $total_general += $subtotal;
    
    // Limpiar nombre
    $nombre = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $i['descripcion_producto']);
    
    // Altura dinámica si el nombre es largo
    $nb = $pdf->WordWrap($nombre, 110);
    
    $pdf->Cell(110,8,$nombre,1,0,'L');
    $pdf->Cell(20,8,$i['cantidad_aprobada_compra'],1,0,'C');
    $pdf->Cell(30,8,'$ '.number_format($i['precio_unitario'],2),1,0,'R');
    $pdf->Cell(30,8,'$ '.number_format($subtotal,2),1,1,'R');
}

// TOTAL
$pdf->SetFont('Arial','B',11);
$pdf->Cell(160,10,'TOTAL GENERAL',1,0,'R');
$pdf->SetFillColor(13,110,253); $pdf->SetTextColor(255);
$pdf->Cell(30,10,'$ '.number_format($total_general,2),1,1,'R',true);

$pdf->Output('I', 'OC_'.$oc['numero_oc'].'.pdf');

// Función auxiliar para WordWrap (si no la tiene FPDF base)
// Agregada al vuelo para evitar errores
class FPDF_Extend extends FPDF {
    function WordWrap(&$text, $maxwidth){
        // Simplificado para este caso: cortar texto
        if($this->GetStringWidth($text) > $maxwidth) {
            $text = substr($text, 0, 60) . '...';
        }
        return 1; 
    }
}
?>