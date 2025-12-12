<?php
// Archivo: actualizar_db.php
// Propósito: Ejecutar los cambios de Base de Datos automáticamente sin errores
require 'db.php';

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h1>🔧 Reparación de Base de Datos</h1>";

try {
    // 1. VERIFICAR Y CREAR COLUMNA 'cantidad_recibida'
    // Intentamos seleccionarla para ver si existe
    $columnaExiste = false;
    try {
        $pdo->query("SELECT cantidad_recibida FROM pedidos_items LIMIT 1");
        $columnaExiste = true;
        echo "<p style='color:blue'>ℹ️ La columna 'cantidad_recibida' ya existía. No se realizaron cambios.</p>";
    } catch (Exception $e) {
        $columnaExiste = false;
    }

    if (!$columnaExiste) {
        // Si no existe, la creamos
        $pdo->exec("ALTER TABLE pedidos_items ADD COLUMN cantidad_recibida INT DEFAULT 0");
        echo "<p style='color:green'>✅ Columna 'cantidad_recibida' creada exitosamente.</p>";
    }

    // 2. CONFIGURAR FLUJOS (Borrar anteriores para evitar duplicados)
    $pdo->exec("DELETE FROM config_flujos WHERE nombre_estado = 'recepcion_deposito'");
    echo "<p style='color:orange'>🧹 Limpieza de configuración previa realizada.</p>";

    // 3. INSERTAR LOS PASOS NUEVOS
    // Paso para Insumos Médicos (Rol 4)
    $sqlInsumos = "INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                   VALUES ('adquisicion_insumos', 4, 'recepcion_deposito', 'Recepción en Depósito', 4, 0)";
    $pdo->exec($sqlInsumos);
    
    // Paso para Suministros Generales (Rol 5)
    $sqlSuministros = "INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable, requiere_firma) 
                       VALUES ('adquisicion_suministros', 4, 'recepcion_deposito', 'Recepción en Depósito', 5, 0)";
    $pdo->exec($sqlSuministros);

    echo "<p style='color:green'>✅ Pasos de flujo 'Recepción en Depósito' configurados correctamente.</p>";
    
    echo "<hr>";
    echo "<h2 style='color:green'>🎉 ¡TODO LISTO!</h2>";
    echo "<p>La base de datos está sincronizada. Ahora puedes usar el sistema de recepción.</p>";
    echo "<a href='dashboard.php' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Dashboard</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Error Crítico</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
echo "</div>";
?>