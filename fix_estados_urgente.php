<?php
// Archivo: fix_estados_urgente.php
require 'db.php';

echo "<h1>🚑 Reparación de Estados de Base de Datos</h1>";

try {
    // Esta consulta AMPLÍA la lista de estados permitidos para incluir 'en_carga'
    $sql = "ALTER TABLE pedidos_servicio 
            MODIFY COLUMN estado 
            ENUM('pendiente_director','aprobado_director','pendiente_logistica','aprobada_logistica','entregado','rechazado','finalizado_proceso','esperando_entrega','en_carga') 
            DEFAULT 'pendiente_director'";
    
    $pdo->exec($sql);
    
    echo "<h2 style='color:green'>✅ ÉXITO: Ahora la base de datos acepta 'en_carga'.</h2>";
    echo "<p>El problema de la pantalla blanca y el envío automático debería desaparecer.</p>";
    echo "<a href='campana_carga_suministros.php' style='font-size:20px; font-weight:bold;'>👉 Volver a Probar la Campaña</a>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ Error: " . $e->getMessage() . "</h2>";
}
?>