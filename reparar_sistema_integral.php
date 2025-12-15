<?php
// Archivo: reparar_sistema_integral.php
// Propósito: Sincronizar IDs reales de roles con los flujos y asignar permisos faltantes.
require 'db.php';

echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #ccc; background: #f9f9f9;'>";
echo "<h1>🛠️ Reparación Integral del Sistema</h1>";

try {
    // 1. OBTENER IDs REALES DE LOS ROLES (Esto es lo que estaba fallando)
    $roles_clave = [
        'logistica' => 'Encargado Logística',
        'suministros' => 'Encargado Depósito Suministros',
        'insumos' => 'Encargado Depósito Insumos',
        'director' => 'Director Médico'
    ];

    $ids_reales = [];
    foreach ($roles_clave as $clave => $nombre) {
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE nombre = ?");
        $stmt->execute([$nombre]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $ids_reales[$clave] = $id;
            echo "<p>✅ Rol encontrado: <strong>$nombre</strong> tiene el ID <strong>$id</strong></p>";
        } else {
            die("<h3 style='color:red'>❌ Error Crítico: No existe el rol '$nombre'. Créalo en Admin Roles primero.</h3>");
        }
    }

    // 2. CORREGIR FLUJOS CON LOS IDs REALES
    echo "<hr><h3>⚙️ Sincronizando Flujos de Trabajo...</h3>";
    
    // Suministros: Logística (1) -> Suministros (2) -> Suministros (3) -> Usuario (4)
    $pdo->prepare("UPDATE config_flujos SET id_rol_responsable = ? WHERE nombre_proceso = 'movimiento_suministros' AND paso_orden = 1")
        ->execute([$ids_reales['logistica']]);
    
    // Aquí estaba el error: asignamos el ID real del encargado al paso 2 y 3
    $pdo->prepare("UPDATE config_flujos SET id_rol_responsable = ? WHERE nombre_proceso = 'movimiento_suministros' AND paso_orden IN (2, 3)")
        ->execute([$ids_reales['suministros']]);

    // Insumos: Insumos (1) -> Director (2) -> Insumos (3) -> Usuario (4)
    $pdo->prepare("UPDATE config_flujos SET id_rol_responsable = ? WHERE nombre_proceso = 'movimiento_insumos' AND paso_orden IN (1, 3)")
        ->execute([$ids_reales['insumos']]);
        
    $pdo->prepare("UPDATE config_flujos SET id_rol_responsable = ? WHERE nombre_proceso = 'movimiento_insumos' AND paso_orden = 2")
        ->execute([$ids_reales['director']]);

    echo "<p style='color:green'>✅ Flujos actualizados con los responsables correctos.</p>";

    // 3. ASIGNAR PERMISOS FALTANTES (Blindaje)
    echo "<hr><h3>🔑 Forzando Permisos...</h3>";
    
    $permisos_necesarios = [
        $ids_reales['suministros'] => ['recibir_orden_suministros', 'realizar_entrega_suministros', 'ver_dashboard'],
        $ids_reales['insumos'] => ['aprobar_insumos_encargado', 'realizar_entrega_insumos', 'ver_dashboard']
    ];

    foreach ($permisos_necesarios as $id_rol => $claves) {
        foreach ($claves as $clave) {
            // Asegurar que permiso existe
            $pdo->prepare("INSERT IGNORE INTO permisos (clave, nombre, categoria) VALUES (?, ?, 'Sistema')")->execute([$clave, ucfirst($clave)]);
            $id_permiso = $pdo->query("SELECT id FROM permisos WHERE clave = '$clave'")->fetchColumn();
            
            // Asignar
            $pdo->prepare("INSERT IGNORE INTO rol_permisos (id_rol, id_permiso) VALUES (?, ?)")->execute([$id_rol, $id_permiso]);
        }
    }
    echo "<p style='color:green'>✅ Permisos críticos re-asignados.</p>";

    // 4. DIAGNÓSTICO DEL ÚLTIMO PEDIDO
    echo "<hr><h3>🔎 Estado del Último Pedido</h3>";
    $pedido = $pdo->query("SELECT * FROM pedidos_servicio ORDER BY id DESC LIMIT 1")->fetch();
    
    if ($pedido) {
        echo "Pedido #{$pedido['id']} | Estado: <strong>{$pedido['estado']}</strong><br>";
        
        // Si está trabado en pendiente_deposito, verificar el paso
        if ($pedido['estado'] == 'pendiente_deposito' || strpos($pedido['estado'], 'pendiente') !== false) {
            // Forzar actualización del paso actual al correcto
            $paso_correcto = $pdo->query("SELECT id FROM config_flujos WHERE nombre_proceso = 'movimiento_suministros' AND nombre_estado = 'pendiente_deposito'")->fetchColumn();
            if ($paso_correcto) {
                $pdo->prepare("UPDATE pedidos_servicio SET paso_actual_id = ? WHERE id = ?")->execute([$paso_correcto, $pedido['id']]);
                echo "<p style='color:blue'>ℹ️ Se actualizó el puntero del pedido al paso correcto (ID $paso_correcto).</p>";
            }
        }
    }

    echo "<hr><br><a href='dashboard.php' style='background-color:#198754; color:white; padding:15px 30px; text-decoration:none; font-size:18px; border-radius:5px;'>👉 VOLVER Y PROBAR AHORA</a>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
echo "</div>";
?>