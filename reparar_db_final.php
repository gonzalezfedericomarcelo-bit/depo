<?php
// Archivo: reparar_db_final.php
// Propósito: Corrige la tabla para soportar Frecuencia y Horarios Exactos
require 'db.php';

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h1>🔧 Reparación de Base de Datos (Campañas)</h1>";

try {
    // 1. Agregar columna 'frecuencia_cobertura' si no existe
    $col = $pdo->query("SHOW COLUMNS FROM compras_planificaciones LIKE 'frecuencia_cobertura'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE compras_planificaciones ADD COLUMN frecuencia_cobertura VARCHAR(50) DEFAULT 'Trimestral' AFTER titulo");
        echo "<p style='color:green'>✅ Columna <strong>'frecuencia_cobertura'</strong> creada exitosamente.</p>";
    } else {
        echo "<p style='color:blue'>ℹ️ La columna 'frecuencia_cobertura' ya existía.</p>";
    }

    // 2. Convertir fechas a DATETIME (para soportar hora exacta)
    try {
        $pdo->exec("ALTER TABLE compras_planificaciones MODIFY COLUMN fecha_inicio DATETIME NOT NULL");
        $pdo->exec("ALTER TABLE compras_planificaciones MODIFY COLUMN fecha_fin DATETIME NOT NULL");
        echo "<p style='color:green'>✅ Columnas de fecha actualizadas a formato <strong>HORARIO EXACTO</strong>.</p>";
    } catch (Exception $e) {
        echo "<p style='color:orange'>⚠️ Aviso: " . $e->getMessage() . "</p>";
    }

    echo "<hr>";
    echo "<h2 style='color:green'>🎉 ¡LISTO! AHORA PUEDES CREAR LA CAMPAÑA</h2>";
    echo "<a href='dashboard.php' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Error Crítico</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
echo "</div>";
?>