<?php
// Archivo: generar_pdf_entrega_suministros.php
// Propósito: Generar PDF entrega Suministros (Público + QR Real)

ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require 'db.php';

if (!file_exists('fpdf/fpdf.php')) { die("Error: Falta fpdf."); }
require 'fpdf/fpdf.php';

session_start();
ob_end_clean();
ob_start();

if (!isset($_GET['id']) || empty($_GET['id'])) { die("Error: Falta el ID."); }
$id_entrega = $_GET['id'];

function texto($str) {
    if (function_exists('mb_convert_encoding')) return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
}

// Consultas
$stmt = $pdo->prepare("SELECT e.*, u.nombre_completo as responsable, u.firma_digital as firma_responsable FROM entregas e JOIN usuarios u ON e.id_usuario_responsable = u.id WHERE e.id = :id AND e.tipo_origen = 'suministros'");
$stmt->execute(['id' => $id_entrega]);
$entrega = $stmt->fetch();

if (!$entrega) { die("Entrega no encontrada."); }

$stmtItems = $pdo->prepare("SELECT ei.*, sg.nombre as nombre_suministro, sg.codigo FROM entregas_items ei JOIN suministros_generales sg ON ei.id_suministro = sg.id WHERE ei.id_entrega = :id");
$stmtItems->execute(['id' => $id_entrega]);
$items = $stmtItems->fetchAll();

// Configuración de Rutas
$nombre_archivo = 'Constancia_Suministros_' . $id_entrega . '.pdf';
$ruta_relativa_carpeta = 'publicos/pdfs/';
$ruta_fisica_carpeta = __DIR__ . '/' . $ruta_relativa_carpeta;
$ruta_fisica_archivo = $ruta_fisica_carpeta . $nombre_archivo;

if (!file_exists($ruta_fisica_carpeta)) {
    mkdir($ruta_fisica_carpeta, 0777, true);
}

// URL Pública para el QR
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['PHP_SELF']);
$path = rtrim($path, '/');
$url_publica_pdf = $protocol . "://" . $host . $path . "/" . $ruta_relativa_carpeta . $nombre_archivo;

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(15, 76, 117);
        $this->Cell(0, 10, texto('Policlínica ACTIS'), 0, 1, 'R');
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, texto('CONSTANCIA DE ENTREGA - SUMINISTROS'), 0, 1, 'R');
        $this->Ln(5);
        $this->SetDrawColor(15, 76, 117);
        $this->SetLineWidth(1);
        $this->Line(10, 32, 200, 32);
        $this->Ln(10);
    }

    function Footer() {
        global $url_publica_pdf, $entrega;
        $this->SetY(-35);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.2);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);

        // QR
        $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($url_publica_pdf);
        $temp_qr = 'uploads/temp_qr_s_' . uniqid() . '.png';
        if (!file_exists('uploads')) mkdir('uploads', 0777, true);
        
        $qr_image = @file_get_contents($qr_api);
        if ($qr_image) {
            file_put_contents($temp_qr, $qr_image);
            $this->Image($temp_qr, 10, $this->GetY() + 2, 25, 25);
            unlink($temp_qr);
        }

        // Textos
        $this->SetLeftMargin(40);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, texto('Sistema desarrollado por Sargento González'), 0, 1, 'L');
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 4, texto('Documento Oficial | Escanee el QR para validar autenticidad.'), 0, 1, 'L');
        $this->SetFont('Arial', 'I', 6);
        $this->SetTextColor(150, 150, 200);
        $this->Cell(0, 4, texto('URL: ' . substr($url_publica_pdf, 0, 60) . '...'), 0, 1, 'L');
        
        $this->SetY(-15);
        $this->SetLeftMargin(10);
        $this->SetTextColor(128, 128, 128);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, texto('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Datos
$pdf->SetFillColor(245, 248, 250); 
$pdf->SetDrawColor(200, 200, 200); 
$pdf->Rect(10, 35, 190, 35, 'FD'); 

$pdf->SetY(38);
$pdf->SetX(15);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell(35, 6, texto('Operación Nº:'), 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 6, texto('#' . str_pad($entrega['id'], 6, '0', STR_PAD_LEFT)), 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 6, texto('Fecha Emisión:'), 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 6, date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])), 0, 1);

$pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, texto('Solicitante:'), 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 7, texto(strtoupper($entrega['solicitante_nombre'])), 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 7, texto('Responsable:'), 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 7, texto($entrega['responsable']), 0, 1);

$pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, texto('Área / Servicio:'), 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(150, 7, texto($entrega['solicitante_area']), 0, 1);

$pdf->Ln(12);

// Tabla
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(15, 76, 117); 
$pdf->SetTextColor(255, 255, 255); 

$w = array(30, 130, 30);
$pdf->Cell($w[0], 8, texto('CÓDIGO'), 0, 0, 'C', true);
$pdf->Cell($w[1], 8, texto('ARTÍCULO / SUMINISTRO'), 0, 0, 'L', true);
$pdf->Cell($w[2], 8, texto('CANTIDAD'), 0, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(240, 240, 240);

$fill = false;
foreach ($items as $item) {
    $pdf->Cell($w[0], 7, texto($item['codigo']), 'LR', 0, 'C', $fill);
    $pdf->Cell($w[1], 7, texto($item['nombre_suministro']), 'LR', 0, 'L', $fill);
    $pdf->Cell($w[2], 7, texto($item['cantidad']), 'LR', 1, 'C', $fill);
    $fill = !$fill;
}
$pdf->Cell(array_sum($w), 0, '', 'T');

$pdf->Ln(15);

// Fecha
$meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
$fecha = strtotime($entrega['fecha_entrega']);
$fecha_texto = "Ciudad Autónoma de Buenos Aires, " . date('d', $fecha) . " de " . $meses[date('n', $fecha)-1] . " de " . date('Y', $fecha);

$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, texto($fecha_texto), 0, 1, 'R');

// Firmas
$pdf->Ln(15);
if ($pdf->GetY() > 220) { $pdf->AddPage(); }

$y_linea = $pdf->GetY() + 25;
$ancho_firma = 60;
$x_izq = 20;  
$x_der = 130; 

// Asegurar carpeta uploads
if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }

// Solicitante
if (!empty($entrega['firma_solicitante_data'])) {
    $temp_file = 'uploads/temp_firm_s_sum_' . $id_entrega . '_' . uniqid() . '.png';
    $img_data = $entrega['firma_solicitante_data'];
    $img_data = str_replace('data:image/png;base64,', '', $img_data);
    $img_data = str_replace(' ', '+', $img_data);
    $data = base64_decode($img_data);
    
    if ($data) {
        file_put_contents($temp_file, $data);
        if (file_exists($temp_file)) {
            $pdf->Image($temp_file, $x_izq + 5, $y_linea - 25, 50, 25);
            unlink($temp_file);
        }
    }
}

// Responsable
if (!empty($entrega['firma_responsable']) && file_exists($entrega['firma_responsable'])) {
    $pdf->Image($entrega['firma_responsable'], $x_der + 5, $y_linea - 25, 50, 25);
}

// Líneas
$pdf->SetY($y_linea); 

// Izq
$pdf->SetX($x_izq);
$pdf->Cell($ancho_firma, 0, '', 'T');
$pdf->Ln(2);
$pdf->SetX($x_izq);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($ancho_firma, 5, texto('RECIBÍ CONFORME'), 0, 1, 'C');
$pdf->SetX($x_izq);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell($ancho_firma, 4, texto($entrega['solicitante_nombre']), 0, 1, 'C');
$pdf->SetX($x_izq);
$pdf->SetFont('Arial', 'I', 7);
$pdf->Cell($ancho_firma, 4, texto('Solicitante / Área'), 0, 0, 'C');

// Der
$pdf->SetY($y_linea); 
$pdf->SetX($x_der);
$pdf->Cell($ancho_firma, 0, '', 'T');
$pdf->Ln(2);
$pdf->SetX($x_der);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($ancho_firma, 5, texto('ENTREGÓ / AUTORIZÓ'), 0, 1, 'C');
$pdf->SetX($x_der);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell($ancho_firma, 4, texto($entrega['responsable']), 0, 1, 'C');
$pdf->SetX($x_der);
$pdf->SetFont('Arial', 'I', 7);
$pdf->Cell($ancho_firma, 4, texto('Logística / Depósito'), 0, 0, 'C');

// GUARDAR Y SALIDA
$pdf->Output('F', $ruta_fisica_archivo);
ob_end_clean();
$pdf->Output('I', $nombre_archivo);
?>