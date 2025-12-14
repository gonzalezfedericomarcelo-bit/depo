<?php
require 'db.php';
echo "<h1>🔧 Actualización de DB (Rechazos)</h1>";
try {
    // Agregamos columna para guardar el motivo del rechazo
    $col = $pdo->query("SHOW COLUMNS FROM compras_planificaciones LIKE 'motivo_rechazo'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE compras_planificaciones ADD COLUMN motivo_rechazo TEXT NULL AFTER estado");
        echo "<p style='color:green'>✅ Columna 'motivo_rechazo' agregada.</p>";
    }
    echo "<a href='dashboard.php'>Volver</a>";
} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
?>