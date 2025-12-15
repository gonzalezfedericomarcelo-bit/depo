<?php
// Archivo: reparar_flujo_suministros.php
require 'db.php';

echo "<div style='font-family:sans-serif; padding:20px;'>";
echo "<h1>🛠️ Reparación del Flujo de Suministros</h1>";

try {
    // 1. OBTENER ID DEL ROL SUMINISTROS
    $rol_sum = $pdo->query("SELECT id FROM roles WHERE nombre = 'Encargado Depósito Suministros'")->fetchColumn();
    if (!$rol_sum) die("<h3 style='color:red'>❌ Error: No existe el rol 'Encargado Depósito Suministros'.</h3>");

    // 2. LIMPIAR Y RE-CREAR PASOS DEL FLUJO (ID Rol 0 = Solicitante/Cualquiera según contexto)
    $pdo->exec("DELETE FROM config_flujos WHERE nombre_proceso = 'movimiento_suministros'");
    
    // Paso 1: Logística
    $pdo->prepare("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable) VALUES ('movimiento_suministros', 1, 'revision_logistica', 'Revisión Logística', 0)")->execute();
    
    // Paso 2: Depósito Recibe (El que te falta)
    $pdo->prepare("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable) VALUES ('movimiento_suministros', 2, 'pendiente_deposito', 'Pendiente Recepción en Depósito', ?)")->execute([$rol_sum]);
    $id_paso_2 = $pdo->lastInsertId(); // Guardamos este ID

    // Paso 3: Depósito Entrega
    $pdo->prepare("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable) VALUES ('movimiento_suministros', 3, 'en_preparacion', 'En Preparación (Depósito)', ?)")->execute([$rol_sum]);
    
    // Paso 4: Usuario Confirma
    $pdo->prepare("INSERT INTO config_flujos (nombre_proceso, paso_orden, nombre_estado, etiqueta_estado, id_rol_responsable) VALUES ('movimiento_suministros', 4, 'listo_para_retirar', 'Listo para Retirar', 0)")->execute();

    echo "<p style='color:green'>✅ Pasos del flujo re-configurados.</p>";

    // 3. ASIGNAR PERMISOS AL ROL
    $permisos = ['recibir_orden_suministros', 'realizar_entrega_suministros', 'ver_dashboard', 'ver_todos_pedidos_suministros'];
    foreach ($permisos as $clave) {
        $pdo->prepare("INSERT IGNORE INTO permisos (clave, nombre, categoria) VALUES (?, ?, 'Suministros')")->execute([$clave, ucfirst(str_replace('_',' ',$clave))]);
        $id_p = $pdo->query("SELECT id FROM permisos WHERE clave = '$clave'")->fetchColumn();
        $pdo->prepare("INSERT IGNORE INTO rol_permisos (id_rol, id_permiso) VALUES (?, ?)")->execute([$rol_sum, $id_p]);
    }
    echo "<p style='color:green'>✅ Permisos asignados al rol ID $rol_sum.</p>";

    // 4. ARREGLAR PEDIDOS TRABADOS
    // Buscamos pedidos que estén en estado 'pendiente_deposito' pero tengan el paso_actual_id roto o viejo
    $sqlFix = "UPDATE pedidos_servicio 
               SET paso_actual_id = :id_new 
               WHERE nombre_proceso = 'movimiento_suministros' 
               AND (estado = 'pendiente_deposito' OR estado LIKE '%aprobado%')"; // Atrapamos también si quedó como 'aprobado_logistica'
               
    $stmtFix = $pdo->prepare($sqlFix);
    $stmtFix->execute([':id_new' => $id_paso_2]);
    
    if ($stmtFix->rowCount() > 0) {
        echo "<h3 style='color:blue'>🩹 Se corrigieron " . $stmtFix->rowCount() . " pedidos trabados. Ahora apuntan al Paso 2 correctamente.</h3>";
    } else {
        echo "<p>No se encontraron pedidos trabados para corregir automáticamente.</p>";
    }

    echo "<hr><a href='dashboard.php' style='background:#198754; color:white; padding:10px 20px; text-decoration:none;'>Ir al Dashboard y Probar</a>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</div>";
?>