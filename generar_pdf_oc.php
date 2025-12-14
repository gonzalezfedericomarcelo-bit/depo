<?php
// Archivo: generar_pdf_oc.php
// Propósito: PDF Orden de Compra (QR Público + Guardado Automático en carpeta Pública)

require 'db.php';
require 'fpdf/fpdf.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$usuario_impresion = $_SESSION['user_name'] ?? 'Sistema';

if (!isset($_GET['id'])) die("Error: Falta ID.");
$id_oc = $_GET['id'];

// 1. OBTENER DATOS
$sql = "SELECT 
            oc.*, 
            u_cre.nombre_completo as creador, 
            u_cre.firma_digital as firma_creador,
            u_apr.nombre_completo as aprobador, 
            u_apr.firma_digital as firma_aprobador
        FROM ordenes_compra oc 
        JOIN usuarios u_cre ON oc.id_usuario_creador = u_cre.id 
        LEFT JOIN usuarios u_apr ON oc.id_usuario_aprobador = u_apr.id 
        WHERE oc.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_oc]);
$oc = $stmt->fetch();

if (!$oc) die("Orden no encontrada.");

// Lógica de respaldo para Director (si falta aprobador)
if (empty($oc['firma_aprobador'])) {
    $stmtDir = $pdo->query("SELECT u.nombre_completo, u.firma_digital FROM usuarios u JOIN usuario_roles ur ON u.id = ur.id_usuario JOIN roles r ON ur.id_rol = r.id WHERE r.nombre LIKE '%Director Médico%' LIMIT 1");
    $director = $stmtDir->fetch();
    if ($director) {
        $oc['aprobador'] = $director['nombre_completo'];
        $oc['firma_aprobador'] = $director['firma_digital'];
    }
}

$stmtItems = $pdo->prepare("SELECT * FROM ordenes_compra_items WHERE id_oc = ?");
$stmtItems->execute([$id_oc]);
$items = $stmtItems->fetchAll();

// --- CONFIGURACIÓN DE RUTA PÚBLICA PARA EL QR ---

// 1. Definir nombre de archivo seguro (reemplazar barras por guiones)
$nombre_archivo_seguro = 'OC_' . preg_replace('/[^A-Za-z0-9_\-]/', '-', $oc['numero_oc']) . '.pdf';

// 2. Definir rutas de sistema
$directorio_publico = __DIR__ . '/publicos/oc';
$ruta_archivo_sistema = $directorio_publico . '/' . $nombre_archivo_seguro;

// 3. Crear carpetas si no existen
if (!is_dir(__DIR__ . '/publicos')) mkdir(__DIR__ . '/publicos', 0777, true);
if (!is_dir($directorio_publico)) mkdir($directorio_publico, 0777, true);

// 4. Construir URL Pública para el QR
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
// dirname($_SERVER['PHP_SELF']) devuelve la ruta relativa (ej: /sistema), le pegamos /publicos/oc/
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
// Ajuste por si dirname devuelve barra al final o no
$base_url = rtrim($base_url, '/'); 
$url_qr_publica = $base_url . "/publicos/oc/" . $nombre_archivo_seguro;


// Fecha Español Helper
function fechaEsp($fecha) {
    $meses = ['01'=>'Enero', '02'=>'Febrero', '03'=>'Marzo', '04'=>'Abril', '05'=>'Mayo', '06'=>'Junio', '07'=>'Julio', '08'=>'Agosto', '09'=>'Septiembre', '10'=>'Octubre', '11'=>'Noviembre', '12'=>'Diciembre'];
    return "CABA, " . date('d', strtotime($fecha)) . " de " . $meses[date('m', strtotime($fecha))] . " de " . date('Y', strtotime($fecha));
}

class PDF_GDE extends FPDF {
    public $numero_oc;
    public $url_qr;
    public $user_print;

    function Header() {
        // Marca de Agua
        $watermark = __DIR__ . '/assets/img/logo_watermark_gris.png';
        if (file_exists($watermark)) $this->Image($watermark, 45, 80, 120, 0, 'PNG'); 

        // Logo
        $logo = __DIR__ . '/assets/img/logo.png';
        if (file_exists($logo)) $this->Image($logo, 10, 10, 25); 

        // Títulos (NEGRO)
        $this->SetXY(40, 12);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(0, 0, 0); 
        $this->Cell(0, 5, utf8_decode("\"2025 - Año de la reconstrucción de la nación Argentina\""), 0, 1, 'L');
        $this->SetX(40);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, utf8_decode("Policlínica ACTIS - Sistema de Gestión Integral\nDirección Administrativa y Logística"), 0, 1, 'L');

        // Datos OC
        $this->SetXY(140, 10);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(60, 8, utf8_decode("ORDEN DE COMPRA"), 0, 1, 'R');
        $this->SetX(140);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(60, 6, utf8_decode($this->numero_oc), 0, 1, 'R');
        $this->SetX(140);
        $this->SetFont('Arial', '', 8);
        $this->Cell(60, 5, utf8_decode("Ejemplar Original"), 0, 1, 'R');

        $this->Ln(8);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-30);
        $this->SetDrawColor(0, 0, 0);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);

        // QR (Generado apuntando a la URL PÚBLICA)
        $qr_file = __DIR__ . '/uploads/temp_qr_'.md5($this->url_qr).'.png';
        if(!file_exists(__DIR__.'/uploads')) mkdir(__DIR__.'/uploads', 0777, true);
        
        if(!file_exists($qr_file)) {
            // API QR
            $c = @file_get_contents("https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=".urlencode($this->url_qr));
            if($c) file_put_contents($qr_file, $c);
        }
        if(file_exists($qr_file)) $this->Image($qr_file, 12, $this->GetY(), 20, 20);

        // Legal
        $this->SetLeftMargin(35);
        $this->SetFont('Arial', 'B', 7);
        $this->SetTextColor(0);
        $this->Cell(0, 4, utf8_decode("DOCUMENTO OFICIAL DE GESTIÓN ELECTRÓNICA"), 0, 1, 'L');
        $this->SetFont('Arial', '', 6);
        $this->SetTextColor(80);
        $this->MultiCell(100, 3, utf8_decode("Validación mediante código QR (Acceso Público).\nGenerado por: " . $this->user_print . "\nFecha: " . date('d/m/Y H:i')), 0, 'L');
        
        $this->SetY(-15);
        $this->SetLeftMargin(10);
        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    function TablaHeader() {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(0);
        $this->SetLineWidth(0.1);
        $this->Cell(95, 6, utf8_decode('DETALLE / PRODUCTO'), 1, 0, 'L', true);
        $this->Cell(40, 6, utf8_decode('SERVICIO / ÁREA'), 1, 0, 'L', true);
        $this->Cell(15, 6, utf8_decode('CANT.'), 1, 0, 'C', true);
        $this->Cell(20, 6, utf8_decode('P. UNIT.'), 1, 0, 'R', true);
        $this->Cell(20, 6, utf8_decode('SUBTOTAL'), 1, 1, 'R', true);
    }
}

$pdf = new PDF_GDE('P', 'mm', 'A4');
$pdf->numero_oc = "#" . $oc['numero_oc'];
$pdf->url_qr = $url_qr_publica; // Pasamos la URL pública
$pdf->user_print = $usuario_impresion;
$pdf->AliasNbPages();
$pdf->AddPage();

// CUERPO
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0);
$pdf->MultiCell(0, 5, utf8_decode("Por medio de la presente se detalla la Orden de Compra solicitada en fecha " . date('d/m/Y', strtotime($oc['fecha_creacion'])) . " por el agente " . $oc['creador'] . ", con destino al depósito central."), 0, 'J');
$pdf->Ln(5);

// TABLA
$pdf->TablaHeader();
$pdf->SetFont('Arial', '', 8);
$total = 0;

foreach ($items as $i) {
    $nombre = $i['descripcion_producto'];
    $servicio = $oc['servicio_destino'];
    if (preg_match('/^(.*?) \[(.*?)\]$/', $nombre, $m)) {
        $nombre = $m[1];
        $servicio = $m[2];
    }
    $precio = $i['precio_unitario'];
    $sub = $i['cantidad_aprobada_compra'] * $precio;
    $total += $sub;

    $nombre = substr($nombre, 0, 55);
    $servicio = substr($servicio, 0, 22);

    $pdf->Cell(95, 6, utf8_decode($nombre), 1);
    $pdf->Cell(40, 6, utf8_decode($servicio), 1);
    $pdf->Cell(15, 6, $i['cantidad_aprobada_compra'], 1, 0, 'C');
    $pdf->Cell(20, 6, "$ " . number_format($precio, 2), 1, 0, 'R');
    $pdf->Cell(20, 6, "$ " . number_format($sub, 2), 1, 1, 'R');
}

// TOTAL Y FECHA
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(170, 8, 'TOTAL AUTORIZADO', 1, 0, 'R');
$pdf->Cell(20, 8, "$ " . number_format($total, 2), 1, 1, 'R', true);

$pdf->Ln(2);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode(fechaEsp($oc['fecha_creacion'])), 0, 1, 'R');

$pdf->Ln(5);

// OBSERVACIONES
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 5, 'OBSERVACIONES:', 0, 1);
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(0, 4, utf8_decode($oc['observaciones'] ?? '-'), 0, 'J');

// --- FIRMAS ---
$pdf->Ln(15); 

if ($pdf->GetY() + 35 > 250) $pdf->AddPage();

$y_linea = $pdf->GetY() + 20;
$x_izq = 30;
$x_der = 130;

// Helper para Forzar B/N en Imágenes (Definido arriba en el chat anterior, pero por si acaso PHP puro)
function toGray($path) {
    // Si quieres forzar B/N real, se requiere GD library. 
    // Para simplificar y no romper, usaremos la imagen original. 
    // FPDF no tiene filtros nativos de color para imágenes.
    return $path; 
}

// IMÁGENES
if (!empty($oc['firma_creador']) && file_exists(__DIR__ . '/' . $oc['firma_creador'])) {
    $pdf->Image(__DIR__ . '/' . $oc['firma_creador'], $x_izq + 5, $y_linea - 18, 40, 18);
}
if (!empty($oc['firma_aprobador']) && file_exists(__DIR__ . '/' . $oc['firma_aprobador'])) {
    $pdf->Image(__DIR__ . '/' . $oc['firma_aprobador'], $x_der + 5, $y_linea - 18, 40, 18);
}

// LÍNEAS Y TEXTOS
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetTextColor(0, 0, 0);

$pdf->Line($x_izq, $y_linea, $x_izq + 50, $y_linea);
$pdf->Line($x_der, $y_linea, $x_der + 50, $y_linea);

$pdf->SetY($y_linea + 2);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetX($x_izq); $pdf->Cell(50, 4, 'SOLICITANTE', 0, 0, 'C');
$pdf->SetX($x_der); $pdf->Cell(50, 4, 'AUTORIZADO POR', 0, 1, 'C');

$pdf->Ln(4);
$pdf->SetFont('Arial', '', 7);
$pdf->SetX($x_izq); $pdf->Cell(50, 4, utf8_decode($oc['creador']), 0, 0, 'C');
$pdf->SetX($x_der); $pdf->Cell(50, 4, utf8_decode($oc['aprobador'] ?? ''), 0, 1, 'C');

// Limpieza QR temporal
$files = glob(__DIR__ . '/uploads/temp_qr_*.png');
if($files) foreach($files as $f) if(is_file($f)) unlink($f);

// --- GUARDADO DOBLE ---
// 1. Guardar en carpeta pública (F)
$pdf->Output('F', $ruta_archivo_sistema);

// 2. Mostrar al usuario (I) - Leemos el archivo guardado para servirlo
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombre_archivo_seguro . '"');
readfile($ruta_archivo_sistema);
exit;
?>