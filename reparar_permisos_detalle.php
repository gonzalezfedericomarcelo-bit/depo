<?php
// Archivo: reparar_permisos_detalle.php
require 'db.php';

echo "<h1>🛠️ Generando Permisos Granulares...</h1>";

try {
    // 1. Limpiar tabla de permisos (Para empezar limpio)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE permisos; TRUNCATE TABLE rol_permisos; SET FOREIGN_KEY_CHECKS = 1;");

    // 2. Insertar Permisos DETALLADOS
    $sql = "INSERT INTO permisos (clave, nombre, categoria) VALUES 
    
    -- --- CATEGORÍA: ACCESO GENERAL ---
    ('acceso_admin', 'Acceso Total al Sistema (Super Admin)', '1. Sistema'),
    ('ver_dashboard', 'Ver Panel de Control (Dashboard)', '1. Sistema'),
    ('ver_notificaciones', 'Ver y Recibir Notificaciones', '1. Sistema'),

    -- --- CATEGORÍA: SERVICIO (USUARIO COMÚN) ---
    ('solicitar_insumos', 'Solicitar Insumos Médicos (Crear Pedido)', '2. Servicios - Acciones'),
    ('solicitar_suministros', 'Solicitar Suministros Grales (Crear Pedido)', '2. Servicios - Acciones'),
    ('confirmar_recepcion', 'Confirmar Recepción de Pedidos (Cerrar Circuito)', '2. Servicios - Acciones'),
    ('ver_mis_pedidos', 'Ver Mis Pedidos Solicitados', '2. Servicios - Vistas'),

    -- --- CATEGORÍA: FLUJO DE SUMINISTROS (LOGÍSTICA Y DEPÓSITO) ---
    ('aprobar_suministros_logistica', 'Aprobar Solicitud Suministros (Paso 1: Logística)', '3. Flujo Suministros'),
    ('recibir_orden_suministros', 'Recibir Orden Aprobada (Paso 2: Depósito da OK)', '3. Flujo Suministros'),
    ('realizar_entrega_suministros', 'Realizar Entrega Física Suministros (Paso 3: Depósito)', '3. Flujo Suministros'),
    ('ver_todos_pedidos_suministros', 'Ver Todos los Pedidos de Suministros (Historial)', '3. Flujo Suministros'),

    -- --- CATEGORÍA: FLUJO DE INSUMOS MÉDICOS ---
    ('aprobar_insumos_encargado', 'Revisión Inicial Insumos (Paso 1: Encargado)', '4. Flujo Insumos Médicos'),
    ('aprobar_insumos_director', 'Autorización Final (Paso 2: Director Médico)', '4. Flujo Insumos Médicos'),
    ('realizar_entrega_insumos', 'Realizar Entrega Física Insumos (Paso 3: Encargado)', '4. Flujo Insumos Médicos'),
    ('ver_todos_pedidos_insumos', 'Ver Todos los Pedidos de Insumos (Historial)', '4. Flujo Insumos Médicos'),

    -- --- CATEGORÍA: GESTIÓN DE STOCK (ABM) ---
    ('gestion_stock_insumos', 'Gestionar Stock Insumos (Altas/Bajas/Editar)', '5. Gestión Stock'),
    ('gestion_stock_suministros', 'Gestionar Stock Suministros (Altas/Bajas/Editar)', '5. Gestión Stock'),
    ('ver_reportes_stock', 'Ver Reportes y Auditoría de Stock', '5. Gestión Stock'),

    -- --- CATEGORÍA: COMPRAS (ADQUISICIÓN) ---
    ('gestion_compras_insumos', 'Gestión Compras Insumos (Subir OC)', '6. Compras'),
    ('gestion_compras_suministros', 'Gestión Compras Suministros (Subir OC)', '6. Compras');
    ";

    $pdo->exec($sql);
    
    // 3. Asignar permiso admin al rol 1 (Administrador) por defecto para no bloquearte
    $pdo->exec("INSERT INTO rol_permisos (id_rol, id_permiso) SELECT 1, id FROM permisos");

    echo "<h3 style='color:green'>✅ Permisos creados correctamente.</h3>";
    echo "<p>Ahora ve a <b>Configuración > Roles y Permisos</b> y asigna los interruptores.</p>";
    echo "<a href='admin_roles.php' class='btn btn-primary'>Ir a Asignar Roles</a>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>