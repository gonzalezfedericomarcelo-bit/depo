<?php
// Archivo: generar_pdf_entrega.php
// Propósito: Constancia Insumos Médicos con 3 Firmas (Solicitante, Depósito, Director)

ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require 'db.php';
if (!file_exists('fpdf/fpdf.php')) { die("Error: Falta la librería FPDF."); }
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

// 1. OBTENER DATOS DE LA ENTREGA (Solicitante)
// Buscamos firma del perfil del solicitante por si no firmó en pantalla
$stmt = $pdo->prepare("SELECT e.*, 
                              u.nombre_completo as responsable, 
                              u.firma_digital as firma_perfil_solicitante 
                       FROM entregas e 
                       JOIN usuarios u ON e.id_usuario_responsable = u.id 
                       WHERE e.id = :id AND e.tipo_origen = 'insumos'");
$stmt->execute(['id' => $id_entrega]);
$entrega = $stmt->fetch();
if (!$entrega) { die("Entrega de Insumos no encontrada."); }

// 2. OBTENER RESPONSABLES (Director Médico y Depósito Insumos)
// Se obtienen del pedido original vinculado a esta entrega
$stmtPed = $pdo->prepare("SELECT 
                            ps.*, 
                            u_dir.nombre_completo as nombre_director, 
                            u_dir.firma_digital as firma_director,
                            u_depo.nombre_completo as nombre_deposito,
                            u_depo.firma_digital as firma_deposito
                          FROM pedidos_servicio ps
                          LEFT JOIN usuarios u_dir ON ps.id_director_aprobador = u_dir.id
                          LEFT JOIN usuarios u_depo ON ps.id_usuario_entrega = u_depo.id
                          WHERE ps.id_entrega_generada = :id");
$stmtPed->execute(['id' => $id_entrega]);
$datos_firmas = $stmtPed->fetch();

// 3. ÍTEMS
$stmtItems = $pdo->prepare("SELECT ei.*, im.nombre as nombre_insumo, im.codigo FROM entregas_items ei JOIN insumos_medicos im ON ei.id_insumo = im.id WHERE ei.id_entrega = :id");
$stmtItems->execute(['id' => $id_entrega]);
$items = $stmtItems->fetchAll();

// CONFIGURACIÓN ARCHIVO
$nombre_archivo = 'Constancia_Insumos_' . $id_entrega . '.pdf';
$ruta_fisica = __DIR__ . '/publicos/pdfs/' . $nombre_archivo;
if (!file_exists(dirname($ruta_fisica))) { mkdir(dirname($ruta_fisica), 0777, true); }

// URL QR
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$url_publica = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/publicos/pdfs/" . $nombre_archivo;

// --- CLASE PDF ---
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 16); $this->SetTextColor(15, 76, 117);
        $this->Cell(0, 10, texto('Policlínica ACTIS'), 0, 1, 'R');
        $this->SetFont('Arial', 'B', 12); $this->SetTextColor(100);
        $this->Cell(0, 6, texto('CONSTANCIA DE ENTREGA - INSUMOS MÉDICOS'), 0, 1, 'R');
        $this->Ln(5);
        $this->SetDrawColor(15, 76, 117); $this->SetLineWidth(0.8);
        $this->Line(10, 32, 200, 32);
        $this->Ln(8);
    }

    function Footer() {
        global $url_publica;
        $this->SetY(-35); // Pie más alto para asegurar espacio
        $this->SetDrawColor(200); $this->SetLineWidth(0.2);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);

        // QR
        $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($url_publica);
        $temp_qr = __DIR__ . '/uploads/temp_qr_' . uniqid() . '.png';
        if (!file_exists(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0777, true);
        
        $qr_content = @file_get_contents($qr_api);
        if ($qr_content) {
            file_put_contents($temp_qr, $qr_content);
            $this->Image($temp_qr, 10, $this->GetY(), 22, 22);
            unlink($temp_qr);
        }

        $this->SetLeftMargin(35);
        $this->SetFont('Arial', '', 7); $this->SetTextColor(100);
        $this->Cell(0, 4, texto('Generado el: ' . date('d/m/Y H:i') . ' | Documento Oficial'), 0, 1, 'L');
        $this->SetY(-15); $this->SetLeftMargin(10);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, texto('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 50); // Margen inferior grande para firmas

// DATOS CABECERA
$pdf->SetFillColor(245); $pdf->SetDrawColor(200);
$pdf->Rect(10, 38, 190, 25, 'FD');
$pdf->SetY(42); $pdf->SetX(15);

$pdf->SetFont('Arial', 'B', 9); $pdf->SetTextColor(50);
$pdf->Cell(25, 6, texto('Operación:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(70, 6, texto('#' . str_pad($entrega['id'], 6, '0', STR_PAD_LEFT)), 0, 0);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 6, texto('Fecha:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(60, 6, date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])), 0, 1);

$pdf->Ln(7); $pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 6, texto('Solicitante:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(70, 6, texto(strtoupper($entrega['solicitante_nombre'])), 0, 0);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 6, texto('Área:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(60, 6, texto($entrega['solicitante_area']), 0, 1);

$pdf->Ln(15);

// TABLA
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(50, 50, 50); $pdf->SetTextColor(255);
$pdf->Cell(30, 8, texto('CÓDIGO'), 0, 0, 'C', true);
$pdf->Cell(130, 8, texto('INSUMO MÉDICO'), 0, 0, 'L', true);
$pdf->Cell(30, 8, texto('CANT.'), 0, 1, 'C', true);

$pdf->SetFont('Arial', '', 9); $pdf->SetTextColor(0); $pdf->SetFillColor(245);
$fill = false;
foreach ($items as $item) {
    $pdf->Cell(30, 7, texto($item['codigo']), 'LR', 0, 'C', $fill);
    $pdf->Cell(130, 7, texto($item['nombre_insumo']), 'LR', 0, 'L', $fill);
    $pdf->Cell(30, 7, texto($item['cantidad']), 'LR', 1, 'C', $fill);
    $pdf->Ln();
    $fill = !$fill;
}
$pdf->Cell(190, 0, '', 'T');

// ==========================================
// SECCIÓN DE FIRMAS (Posicionamiento Absoluto)
// ==========================================

// Asegurar que estamos en una zona limpia o nueva página
if ($pdf->GetY() > 200) {
    $pdf->AddPage();
    $y_base = 40; // Margen superior si es nueva página
} else {
    $y_base = $pdf->GetY() + 15; // Espacio tras la tabla
}

$pdf->SetY($y_base);

// Coordenadas Verticales
$y_fila_1 = $y_base;        // Fila Superior (Solicitante y Depósito)
$y_fila_2 = $y_base + 45;   // Fila Inferior (Director, bien abajo)

// Coordenadas Horizontales
$x_izq = 15;   // Solicitante
$x_der = 140;  // Depósito
$x_cen = 78;   // Director

$ancho_firma = 55;
$alto_img = 20;

// --- 1. FIRMA SOLICITANTE (Izquierda / Arriba) ---
$img_sol = null;
if (!empty($entrega['firma_solicitante_data'])) {
    // Si firmó en pantalla (canvas)
    $img_data = str_replace('data:image/png;base64,', '', $entrega['firma_solicitante_data']);
    $data = base64_decode(str_replace(' ', '+', $img_data));
    $temp_sol = __DIR__ . '/uploads/temp_sol_' . uniqid() . '.png';
    file_put_contents($temp_sol, $data);
    $img_sol = $temp_sol;
} elseif (!empty($entrega['firma_perfil_solicitante'])) {
    // Si no firmó, usamos la de su perfil
    $ruta_perfil = __DIR__ . '/' . $entrega['firma_perfil_solicitante'];
    if (file_exists($ruta_perfil)) {
        $img_sol = $ruta_perfil;
    }
}

if ($img_sol && file_exists($img_sol)) {
    $pdf->Image($img_sol, $x_izq + 10, $y_fila_1 - 15, 35, $alto_img);
    if (strpos($img_sol, 'temp_sol') !== false) unlink($img_sol); // Borrar si es temp
}

$pdf->SetXY($x_izq, $y_fila_1 + 5);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell($ancho_firma, 5, texto('RECIBIÓ CONFORME'), 'T', 1, 'C');
$pdf->SetX($x_izq); $pdf->SetFont('Arial', '', 7); $pdf->Cell($ancho_firma, 4, texto($entrega['solicitante_nombre']), 0, 1, 'C');
$pdf->SetX($x_izq); $pdf->SetFont('Arial', 'I', 6); $pdf->Cell($ancho_firma, 3, texto($entrega['solicitante_area']), 0, 0, 'C');


// --- 2. FIRMA DEPÓSITO INSUMOS (Derecha / Arriba) ---
if (!empty($datos_firmas['firma_deposito'])) {
    $ruta_dep = __DIR__ . '/' . $datos_firmas['firma_deposito'];
    if (file_exists($ruta_dep)) {
        $pdf->Image($ruta_dep, $x_der + 10, $y_fila_1 - 15, 35, $alto_img);
    }
}

$pdf->SetXY($x_der, $y_fila_1 + 5);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell($ancho_firma, 5, texto('ENTREGÓ'), 'T', 1, 'C');
$pdf->SetX($x_der); $pdf->SetFont('Arial', '', 7); 
$nom_dep = !empty($datos_firmas['nombre_deposito']) ? $datos_firmas['nombre_deposito'] : 'Depósito';
$pdf->Cell($ancho_firma, 4, texto($nom_dep), 0, 1, 'C');
$pdf->SetX($x_der); $pdf->SetFont('Arial', 'I', 6); $pdf->Cell($ancho_firma, 3, texto('Encargado Insumos'), 0, 0, 'C');


// --- 3. FIRMA DIRECTOR MÉDICO (Centro / Abajo) ---
if (!empty($datos_firmas['firma_director'])) {
    $ruta_dir = __DIR__ . '/' . $datos_firmas['firma_director'];
    if (file_exists($ruta_dir)) {
        $pdf->Image($ruta_dir, $x_cen + 10, $y_fila_2 - 15, 35, $alto_img);
    }
}

$pdf->SetXY($x_cen, $y_fila_2 + 5);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell($ancho_firma, 5, texto('AUTORIZADO POR'), 'T', 1, 'C');
$pdf->SetX($x_cen); $pdf->SetFont('Arial', '', 7); 
$nom_dir = !empty($datos_firmas['nombre_director']) ? $datos_firmas['nombre_director'] : 'Dirección Médica';
$pdf->Cell($ancho_firma, 4, texto($nom_dir), 0, 1, 'C');
$pdf->SetX($x_cen); $pdf->SetFont('Arial', 'I', 6); $pdf->Cell($ancho_firma, 3, texto('Director Médico'), 0, 0, 'C');

$pdf->Output('F', $ruta_fisica);
$pdf->Output('I', $nombre_archivo);
?>