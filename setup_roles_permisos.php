<?php
// Archivo: setup_roles_permisos.php
// Propósito: Asignar permisos lógicos a los roles estándar automáticamente
require 'db.php';

echo "<h1>🔗 Vinculando Roles con Permisos...</h1>";

try {
    $pdo->exec("TRUNCATE TABLE rol_permisos"); // Limpiar asignaciones previas para evitar duplicados

    // 1. DEFINIR MAPA DE ROLES (Nombre exacto en DB => Array de claves de permisos)
    $configuracion = [
        'Administrador' => ['*'], // * = Todos
        
        'Servicio' => [
            'ver_dashboard', 'ver_notificaciones', 'ver_mis_pedidos', 
            'solicitar_insumos', 'solicitar_suministros', 'confirmar_recepcion'
        ],
        
        'Encargado Logística' => [
            'ver_dashboard', 'ver_notificaciones', 
            'aprobar_suministros_logistica', 'ver_todos_pedidos_suministros',
            'gestion_compras_suministros'
        ],
        
        'Encargado Depósito Suministros' => [
            'ver_dashboard', 'ver_notificaciones', 
            'recibir_orden_suministros', 'realizar_entrega_suministros', 
            'ver_todos_pedidos_suministros', 'gestion_stock_suministros'
        ],
        
        'Encargado Depósito Insumos' => [
            'ver_dashboard', 'ver_notificaciones', 
            'aprobar_insumos_encargado', 'realizar_entrega_insumos', 
            'ver_todos_pedidos_insumos', 'gestion_stock_insumos'
        ],
        
        'Director Médico' => [
            'ver_dashboard', 'ver_notificaciones', 
            'aprobar_insumos_director', 'ver_todos_pedidos_insumos'
        ],
        
        'Compras' => [
            'ver_dashboard', 'ver_notificaciones', 
            'gestion_compras_insumos', 'gestion_compras_suministros',
            'ver_todos_pedidos_insumos', 'ver_todos_pedidos_suministros'
        ]
    ];

    // 2. PROCESAR ASIGNACIÓN
    foreach ($configuracion as $nombre_rol => $permisos_clave) {
        // Buscar ID del Rol
        $stmtRol = $pdo->prepare("SELECT id FROM roles WHERE nombre = ?");
        $stmtRol->execute([$nombre_rol]);
        $id_rol = $stmtRol->fetchColumn();

        if ($id_rol) {
            echo "<p>🔹 Configurando <strong>$nombre_rol</strong> (ID: $id_rol)...</p>";
            
            if ($permisos_clave[0] === '*') {
                // Asignar TODOS los permisos
                $pdo->exec("INSERT INTO rol_permisos (id_rol, id_permiso) SELECT $id_rol, id FROM permisos");
                echo " &nbsp;&nbsp; -> ✅ Acceso Total asignado.<br>";
            } else {
                // Asignar permisos específicos
                foreach ($permisos_clave as $clave) {
                    // Buscar ID del Permiso
                    $stmtPerm = $pdo->prepare("SELECT id FROM permisos WHERE clave = ?");
                    $stmtPerm->execute([$clave]);
                    $id_permiso = $stmtPerm->fetchColumn();
                    
                    if ($id_permiso) {
                        $pdo->prepare("INSERT INTO rol_permisos (id_rol, id_permiso) VALUES (?, ?)")
                            ->execute([$id_rol, $id_permiso]);
                    }
                }
                echo " &nbsp;&nbsp; -> ✅ " . count($permisos_clave) . " permisos asignados.<br>";
            }
        } else {
            echo "<p style='color:red'>⚠️ Rol no encontrado: $nombre_rol</p>";
        }
    }

    echo "<hr><h3 style='color:green'>🎉 Configuración completada.</h3>";
    echo "<p>Ahora el flujo debería funcionar. <a href='dashboard.php'>Volver al Dashboard</a></p>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>