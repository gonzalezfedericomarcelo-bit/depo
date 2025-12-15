<?php
// Archivo: fix_final_limpieza.php
// Propósito: ELIMINAR duplicados, MIGRAR roles y LIMPIAR nombres de categorías.
require 'db.php';

echo "<h1>🧹 Limpieza Profunda de Permisos</h1>";

try {
    $pdo->beginTransaction();

    // 1. MIGRACIÓN Y ELIMINACIÓN DE DUPLICADOS
    // Si tenés el permiso viejo, te paso el nuevo y borro el viejo para siempre.
    $mapa_migracion = [
        // Finanzas
        'd_fin_gasto_mes' => 'dash_fin_kpi_gasto',
        'd_fin_pendientes' => 'dash_fin_kpi_pend',
        'd_fin_ahorro' => 'dash_fin_kpi_aprob',
        'd_chart_gasto_serv' => 'dash_fin_graph_servicios',
        'd_chart_top_insumos' => 'dash_fin_graph_top_ins',
        'd_chart_top_sumin' => 'dash_fin_graph_top_sum',
        // Insumos
        'd_ins_stock_crit' => 'dash_far_kpi_stock',
        'd_ins_pedidos_nuevos' => 'dash_far_kpi_pedidos',
        'd_ins_vencimientos' => 'dash_far_kpi_venc',
        'd_chart_ins_demanda' => 'dash_far_graph_demanda',
        'd_chart_ins_criticos' => 'dash_far_graph_demanda', // Mapeo aproximado para limpiar
        'd_table_ins_vencer' => 'dash_far_kpi_venc',
        // Suministros
        'd_sum_stock_crit' => 'dash_log_kpi_stock',
        'd_sum_pedidos_nuevos' => 'dash_log_kpi_pedidos',
        'd_sum_movimientos' => 'dash_log_kpi_pedidos',
        'd_chart_sum_demanda' => 'dash_log_graph_demanda',
        'd_chart_sum_categ' => 'dash_log_graph_demanda',
        'd_table_sum_lento' => 'dash_log_table_ultimos',
        // Dirección
        'd_dir_aprob_pend' => 'dash_dir_kpi_firmas',
        'd_chart_evolucion' => 'dash_dir_graph_evolucion',
        // Servicios
        'd_serv_mis_pedidos' => 'dash_serv_kpi_mios',
        'd_serv_campanas' => 'dash_serv_alertas',
        'd_serv_mi_consumo' => 'dash_serv_graph_top',
        'd_serv_actividad' => 'dash_serv_list_ultimos'
    ];

    echo "<h3>1. Migrando y eliminando obsoletos...</h3>";
    foreach ($mapa_migracion as $viejo => $nuevo) {
        // Obtener IDs
        $stmt = $pdo->prepare("SELECT id FROM permisos WHERE clave = ?");
        $stmt->execute([$viejo]);
        $idViejo = $stmt->fetchColumn();
        
        $stmt->execute([$nuevo]);
        $idNuevo = $stmt->fetchColumn();

        if ($idViejo) {
            if ($idNuevo) {
                // Mover roles del viejo al nuevo
                $roles = $pdo->query("SELECT id_rol FROM rol_permisos WHERE id_permiso = $idViejo")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($roles as $r) {
                    $pdo->prepare("INSERT IGNORE INTO rol_permisos (id_rol, id_permiso) VALUES (?, ?)")->execute([$r, $idNuevo]);
                }
            }
            // BORRAR EL VIEJO (De roles y de definición)
            $pdo->exec("DELETE FROM rol_permisos WHERE id_permiso = $idViejo");
            $pdo->exec("DELETE FROM permisos WHERE id = $idViejo");
            echo "<div>🗑️ Eliminado obsoleto: <strong>$viejo</strong></div>";
        }
    }

    // 2. LIMPIEZA DE NOMBRES DE CATEGORÍAS (Adiós números y duplicados)
    echo "<h3>2. Limpiando nombres de categorías...</h3>";
    
    $sqlLimpieza = "
        UPDATE permisos SET categoria = 
        CASE 
            WHEN categoria LIKE '%Finanzas%' THEN 'Dashboard: Finanzas'
            WHEN categoria LIKE '%Insumos%' AND categoria LIKE '%Dash%' THEN 'Dashboard: Insumos'
            WHEN categoria LIKE '%Suministros%' AND categoria LIKE '%Dash%' THEN 'Dashboard: Suministros'
            WHEN categoria LIKE '%Farmacia%' THEN 'Dashboard: Farmacia'
            WHEN categoria LIKE '%Logística%' AND categoria LIKE '%Dash%' THEN 'Dashboard: Logística'
            WHEN categoria LIKE '%Dirección%' AND categoria LIKE '%Dash%' THEN 'Dashboard: Dirección'
            WHEN categoria LIKE '%Servicios%' AND categoria LIKE '%Dash%' THEN 'Dashboard: Servicios'
            WHEN categoria LIKE '%General%' AND categoria LIKE '%Dash%' THEN 'Dashboard: General'
            -- Quitar números de las categorías funcionales
            WHEN categoria LIKE '1. %' THEN SUBSTRING(categoria, 4)
            WHEN categoria LIKE '2. %' THEN SUBSTRING(categoria, 4)
            WHEN categoria LIKE '3. %' THEN SUBSTRING(categoria, 4)
            WHEN categoria LIKE '4. %' THEN SUBSTRING(categoria, 4)
            WHEN categoria LIKE '5. %' THEN SUBSTRING(categoria, 4)
            WHEN categoria LIKE '6. %' THEN SUBSTRING(categoria, 4)
            ELSE categoria
        END
    ";
    $pdo->exec($sqlLimpieza);
    echo "<div>✅ Categorías renombradas y unificadas.</div>";

    // 3. AGREGAR FALTANTES CRÍTICOS (Si no existen, para que funcionen los botones)
    $faltantes = [
        ['hacer_compra_insumos', 'Solicitar COMPRA Insumos Médicos', 'Compras'],
        ['hacer_compra_suministros', 'Solicitar COMPRA Suministros Grales', 'Compras'],
        ['ver_monitoreo_consumo', 'Ver Monitor de Consumo (Auditoría)', 'Sistema'],
        ['crear_oc_insumos', 'Crear OC Insumos (Manual)', 'Compras'],
        ['crear_oc_suministros', 'Crear OC Suministros (Manual)', 'Compras']
    ];
    foreach($faltantes as $f) {
        $pdo->prepare("INSERT IGNORE INTO permisos (clave, nombre, categoria) VALUES (?,?,?)")->execute($f);
    }

    $pdo->commit();
    echo "<hr><h2 style='color:green'>¡LISTO! Sistema limpio.</h2>";
    echo "<p>Ya no verás duplicados, ni números '7', ni cosas obsoletas.</p>";
    echo "<a href='admin_roles.php' class='btn btn-primary'>Ir a Gestionar Roles</a>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
?>