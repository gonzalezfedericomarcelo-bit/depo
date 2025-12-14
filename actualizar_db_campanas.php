<?php
// Archivo: actualizar_db_campanas.php
require 'db.php';

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h1>🔧 Actualización de Estructura de Campañas</h1>";

try {
    // 1. Agregar campo 'frecuencia_cobertura' a compras_planificaciones
    // Esto es para decir "Esta compra cubre 6 meses", independiente de que la carga dure 5 días.
    $col1 = $pdo->query("SHOW COLUMNS FROM compras_planificaciones LIKE 'frecuencia_cobertura'")->fetch();
    if (!$col1) {
        $pdo->exec("ALTER TABLE compras_planificaciones ADD COLUMN frecuencia_cobertura VARCHAR(50) DEFAULT 'Mensual' AFTER titulo");
        echo "<p style='color:green'>✅ Campo 'frecuencia_cobertura' agregado.</p>";
    } else {
        echo "<p style='color:blue'>ℹ️ Campo 'frecuencia_cobertura' ya existía.</p>";
    }

    // 2. Agregar estado 'en_carga' al enum de pedidos_servicio (si es posible en tu motor, sino lo manejamos por soft)
    // Nota: Modificar ENUMs en vivo puede ser complejo, asumiremos que el campo soporta texto o lo forzamos.
    // Intentaremos ampliar la definición del ENUM.
    try {
        $pdo->exec("ALTER TABLE pedidos_servicio MODIFY COLUMN estado ENUM('pendiente_director','aprobado_director','pendiente_logistica','aprobada_logistica','entregado','rechazado','finalizado_proceso','esperando_entrega','en_carga') DEFAULT 'pendiente_director'");
        echo "<p style='color:green'>✅ Estado 'en_carga' agregado a pedidos_servicio.</p>";
    } catch (Exception $e) {
        echo "<p style='color:orange'>⚠️ No se pudo modificar el ENUM automáticamente (quizás ya existe o restricción de hosting). Usaremos lógica compatible.</p>";
    }

    echo "<hr><h3>¡Listo! Base de datos preparada.</h3>";
    echo "<a href='dashboard.php'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Error</h2><p>" . $e->getMessage() . "</p>";
}
echo "</div>";
?>