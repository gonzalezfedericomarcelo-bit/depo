<?php
// Archivo: reconfigurar_flujo_exacto.php
// Propósito: Configurar los pasos EXACTOS del relato del usuario
require 'db.php';

echo "<h1>⚙️ Ajustando Flujo de Trabajo...</h1>";

try {
    // Limpiar flujos anteriores de suministros
    $pdo->exec("DELETE FROM config_flujos WHERE nombre_proceso = 'movimiento_suministros'");
    
    // 1. Logística Aprueba
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_suministros', 1, 'revision_logistica', 'Revisión Logística', 0, 0)");
    
    // 2. Depósito Recibe (El botón "Recibí autorización")
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_suministros', 2, 'pendiente_deposito', 'Pendiente Recepción Depósito', 0, 0)");

    // 3. Depósito Prepara (El botón "Hacer entrega" con Modal)
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_suministros', 3, 'en_preparacion', 'En Preparación (Depósito)', 0, 0)");

    // 4. Usuario Retira (El botón "Confirmar")
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_suministros', 4, 'listo_para_retirar', 'Listo para Retirar', 0, 1)");

    echo "<h3 style='color:green'>✅ Flujo Suministros Reconstruido.</h3>";
    echo "<p>Paso 1: Revisión Logística<br>Paso 2: Pendiente Depósito (Botón Recibí)<br>Paso 3: En Preparación (Botón Entrega)<br>Paso 4: Listo para Retirar (Usuario)</p>";
    echo "<a href='dashboard.php'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>