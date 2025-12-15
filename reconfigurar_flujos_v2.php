<?php
// Archivo: reconfigurar_flujos_v2.php
// Propósito: Configurar los flujos EXACTOS solicitados por el usuario
require 'db.php';

echo "<h1>⚙️ Reconfigurando Flujos de Trabajo...</h1>";

try {
    // 1. Limpiar flujos anteriores para evitar conflictos
    $pdo->exec("DELETE FROM config_flujos WHERE nombre_proceso IN ('movimiento_suministros', 'movimiento_insumos')");
    
    // --- FLUJO 1: MOVIMIENTO SUMINISTROS ---
    // Paso 1: Logística revisa/aprueba
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_suministros', 1, 'revision_logistica', 'Revisión Logística', 3, 0)");
    
    // Paso 2: Encargado Suministros da el OK de recepción ("Recibí solicitud")
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_suministros', 2, 'pendiente_deposito', 'Pendiente Recepción Depósito', 5, 0)");

    // Paso 3: Encargado Suministros prepara y entrega
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_suministros', 3, 'en_preparacion', 'En Preparación', 5, 0)");

    // Paso 4: Usuario confirma recepción (Fin) - Rol 0 = Solicitante
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_suministros', 4, 'listo_para_retirar', 'Listo para Retirar', 0, 1)");


    // --- FLUJO 2: MOVIMIENTO INSUMOS MÉDICOS ---
    // Paso 1: Encargado Insumos revisa/aprueba inicial
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_insumos', 1, 'revision_encargado', 'Revisión Encargado', 4, 0)");

    // Paso 2: Director Médico autoriza
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_insumos', 2, 'revision_director', 'Autorización Director Médico', 7, 1)");

    // Paso 3: Encargado Insumos prepara y entrega (Vuelve del director)
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_insumos', 3, 'en_preparacion', 'En Preparación', 4, 0)");

    // Paso 4: Usuario confirma recepción (Fin)
    $pdo->exec("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                VALUES ('movimiento_insumos', 4, 'listo_para_retirar', 'Listo para Retirar', 0, 1)");

    echo "<h3 style='color:green'>✅ Flujos reconfigurados correctamente.</h3>";
    echo "<p>Ahora los procesos siguen estrictamente tu lógica.</p>";
    echo "<a href='dashboard.php'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>