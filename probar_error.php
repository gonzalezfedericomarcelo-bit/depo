<?php
// Archivo: probar_error.php
// Propósito: Mostrar errores ocultos que rompen el PDF

// 1. Forzar visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Iniciando Diagnóstico de PDF...</h1>";

// 2. Probar conexión DB
echo "Testing DB include...<br>";
if (file_exists('db.php')) {
    include 'db.php';
    echo "<span style='color:green'>✅ db.php encontrado.</span><br>";
} else {
    die("<span style='color:red'>❌ ERROR FATAL: No se encuentra el archivo 'db.php'.</span>");
}

// 3. Probar librería FPDF
echo "Testing FPDF include...<br>";
// IMPORTANTE: Verifica si la carpeta se llama 'fpdf' o 'FPDF' (respetando mayúsculas)
if (file_exists('fpdf/fpdf.php')) {
    require 'fpdf/fpdf.php';
    echo "<span style='color:green'>✅ Librería FPDF encontrada en 'fpdf/fpdf.php'.</span><br>";
} elseif (file_exists('FPDF/fpdf.php')) {
    require 'FPDF/fpdf.php';
    echo "<span style='color:green'>✅ Librería FPDF encontrada en 'FPDF/fpdf.php'.</span><br>";
} else {
    die("<span style='color:red'>❌ ERROR FATAL: No se encuentra la librería FPDF. ¿Subiste la carpeta 'fpdf'?</span><br>Ruta actual buscada: " . __DIR__ . "/fpdf/fpdf.php");
}

// 4. Probar instanciación
try {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(40, 10, '¡Funciona!');
    echo "<br><br><span style='color:green; font-size:20px'>✅ TODO ESTÁ BIEN TÉCNICAMENTE.</span><br>Si ves esto, el problema no es el servidor, sino un dato específico del pedido.";
} catch (Exception $e) {
    die("<span style='color:red'>❌ Error al crear PDF: " . $e->getMessage() . "</span>");
}
?>