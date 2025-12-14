<?php
// Archivo: actualizar_horarios_db.php
require 'db.php';

echo "<h1>🔧 Actualización de Horarios</h1>";

try {
    // Convertimos la columna fecha_fin a DATETIME para permitir hora exacta
    $pdo->exec("ALTER TABLE compras_planificaciones MODIFY COLUMN fecha_fin DATETIME NOT NULL");
    
    // Convertimos fecha_inicio también por consistencia
    $pdo->exec("ALTER TABLE compras_planificaciones MODIFY COLUMN fecha_inicio DATETIME NOT NULL");

    echo "<p style='color:green'>✅ Tablas actualizadas para soportar Fecha y Hora exacta.</p>";
    echo "<a href='dashboard.php'>Volver al Dashboard</a>";

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>