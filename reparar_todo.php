<?php
// Archivo: reparar_todo.php
// Propósito: Reescribir archivos críticos para eliminar errores de sintaxis (Error 500)

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🚑 Taller de Reparación Automática</h1>";

// 1. REPARAR DB.PHP (Sin espacios al final)
$codigo_db = <<<'PHP'
<?php
// Archivo: db.php
// Conexión a Base de Datos - Generado Automáticamente

$host = 'localhost';
$db   = 'u415354546_deposito';
$user = 'u415354546_deposito';
$pass = 'Fmg35911@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("<h1>❌ Error de Conexión a Base de Datos</h1>" . $e->getMessage());
}
// FIN DEL ARCHIVO (Sin cierre PHP para evitar errores de PDF)
PHP;

if (file_put_contents('db.php', $codigo_db)) {
    echo "<p style='color:green'>✅ <b>db.php</b> reescrito correctamente (limpio de errores).</p>";
} else {
    echo "<p style='color:red'>❌ No se pudo escribir <b>db.php</b>. Revisa permisos.</p>";
}

// 2. REPARAR GENERAR_PDF_ENTREGA.PHP (Insumos)
$codigo_pdf_insumos = <<<'PHP'
<?php
// Archivo: generar_pdf_entrega.php
// Generador PDF Insumos - Versión Blindada

// Activar reporte de errores para ver si falla algo, en vez de pantalla blanca
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar buffer para atrapar cualquier salida indeseada
ob_start();

require 'db.php';

// Verificar FPDF antes de incluir
if (!file_exists('fpdf/fpdf.php')) {
    ob_end_clean();
    die("<h1>Error Fatal</h1><p>No se encuentra la carpeta 'fpdf'. Por favor súbela.</p>");
}
require 'fpdf/fpdf.php';

session_start();

// Limpiar buffer antes de generar
ob_end_clean();
ob_start();

if (!isset($_GET['id']) || empty($_GET['id'])) { die("Error: Falta el ID."); }
$id_entrega = $_GET['id'];

// Consultas
$stmt = $pdo->prepare("SELECT e.*, u.nombre_completo as responsable, u.firma_digital as firma_responsable FROM entregas e JOIN usuarios u ON e.id_usuario_responsable = u.id WHERE e.id = :id");
$stmt->execute(['id' => $id_entrega]);
$entrega = $stmt->fetch();

if (!$entrega) { die("Entrega no encontrada."); }

$stmtItems = $pdo->prepare("SELECT ei.*, im.nombre as nombre_insumo, im.codigo FROM entregas_items ei JOIN insumos_medicos im ON ei.id_insumo = im.id WHERE ei.id_entrega = :id");
$stmtItems->execute(['id' => $id_entrega]);
$items = $stmtItems->fetchAll();

// PDF
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10,utf8_decode('CONSTANCIA DE ENTREGA - INSUMOS MÉDICOS'),0,1,'C');
        $this->SetFont('Arial','',10);
        $this->Cell(0,5,utf8_decode('Policlínica ACTIS'),0,1,'C');
        $this->Ln(10);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Datos
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,utf8_decode('Operación #' . $entrega['id']),0,1);
$pdf->Line(10, 35, 200, 35);

$pdf->SetFont('Arial','',10);
$pdf->Ln(5);
$pdf->Cell(40,7,utf8_decode('Entregado por:'),0,0);
$pdf->Cell(60,7,utf8_decode($entrega['responsable']),0,1);
$pdf->Cell(40,7,utf8_decode('Solicitante:'),0,0);
$pdf->Cell(60,7,utf8_decode($entrega['solicitante_nombre']),0,1);
$pdf->Cell(40,7,utf8_decode('Área / Servicio:'),0,0);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(60,7,utf8_decode($entrega['solicitante_area']),0,1);
$pdf->Ln(10);

// Tabla
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(230,230,230);
$pdf->Cell(30,8,utf8_decode('Código'),1,0,'C',true);
$pdf->Cell(130,8,utf8_decode('Insumo'),1,0,'L',true);
$pdf->Cell(30,8,utf8_decode('Cantidad'),1,1,'C',true);

$pdf->SetFont('Arial','',10);
foreach ($items as $item) {
    $pdf->Cell(30,8,utf8_decode($item['codigo']),1,0,'C');
    $pdf->Cell(130,8,utf8_decode($item['nombre_insumo']),1,0,'L');
    $pdf->Cell(30,8,$item['cantidad'],1,1,'C');
}
$pdf->Ln(20);

// Fecha
$meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
$fecha = strtotime($entrega['fecha_entrega']);
$fecha_texto = "CABA, " . date('d', $fecha) . " de " . $meses[date('n', $fecha)-1] . " de " . date('Y', $fecha);
$pdf->SetFont('Arial','I',11);
$pdf->Cell(0,10,utf8_decode($fecha_texto),0,1,'R');
$pdf->Ln(10);

// Firmas
if ($pdf->GetY() > 220) { $pdf->AddPage(); }
$y_firmas = $pdf->GetY();

// Asegurar carpeta uploads para firmas temporales
if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }

if (!empty($entrega['firma_solicitante_data'])) {
    $temp_file = 'uploads/temp_firm_s_' . $id_entrega . '_' . uniqid() . '.png';
    $img_data = $entrega['firma_solicitante_data'];
    $img_data = str_replace('data:image/png;base64,', '', $img_data);
    $img_data = str_replace(' ', '+', $img_data);
    $data = base64_decode($img_data);
    if ($data) {
        file_put_contents($temp_file, $data);
        if (file_exists($temp_file)) {
            $pdf->Image($temp_file, 20, $y_firmas, 50, 30);
            unlink($temp_file);
        }
    }
}
$pdf->SetXY(20, $y_firmas + 35);
$pdf->SetFont('Arial','T',9);
$pdf->Cell(50,5,utf8_decode('_______________________'),0,1,'C');
$pdf->Cell(50,5,utf8_decode('Firma Solicitante'),0,1,'C');

if (!empty($entrega['firma_responsable']) && file_exists($entrega['firma_responsable'])) {
    $pdf->Image($entrega['firma_responsable'], 130, $y_firmas, 50, 30);
}
$pdf->SetXY(130, $y_firmas + 35);
$pdf->Cell(50,5,utf8_decode('_______________________'),0,1,'C');
$pdf->Cell(50,5,utf8_decode('Firma Responsable'),0,1,'C');

// Salida
ob_end_clean();
$pdf->Output('I', 'Entrega_Insumos_'.$entrega['id'].'.pdf');
PHP;

if (file_put_contents('generar_pdf_entrega.php', $codigo_pdf_insumos)) {
    echo "<p style='color:green'>✅ <b>generar_pdf_entrega.php</b> reescrito correctamente.</p>";
} else {
    echo "<p style='color:red'>❌ Error escribiendo PDF Insumos.</p>";
}

// 3. REPARAR GENERAR_PDF_ENTREGA_SUMINISTROS.PHP
$codigo_pdf_sum = str_replace(
    ['entregas_items ei JOIN insumos_medicos im ON ei.id_insumo = im.id', 'im.nombre as nombre_insumo', 'Insumo', 'INSUMOS MÉDICOS', 'generar_pdf_entrega.php'],
    ['entregas_items ei JOIN suministros_generales im ON ei.id_suministro = im.id', 'im.nombre as nombre_insumo', 'Suministro', 'SUMINISTROS', 'generar_pdf_entrega_suministros.php'], 
    $codigo_pdf_insumos
);
$codigo_pdf_sum = str_replace('e.id = :id', "e.id = :id AND e.tipo_origen = 'suministros'", $codigo_pdf_sum);

if (file_put_contents('generar_pdf_entrega_suministros.php', $codigo_pdf_sum)) {
    echo "<p style='color:green'>✅ <b>generar_pdf_entrega_suministros.php</b> reescrito correctamente.</p>";
} else {
    echo "<p style='color:red'>❌ Error escribiendo PDF Suministros.</p>";
}

echo "<hr><h3>🎉 ¡Reparación Finalizada!</h3>";
echo "<p>Si ves los checks verdes (✅), el sistema se ha arreglado solo.</p>";
echo "<a href='generar_pdf_entrega.php?id=2' target='_blank' style='background:blue; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>👉 PROBAR PDF AHORA</a>";
?>