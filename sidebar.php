<?php
// Archivo: includes/sidebar.php
// Propósito: Menú lateral dinámico
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebar" class="sidebar d-none d-md-block bg-dark text-white">
    <div class="brand p-3 text-center border-bottom border-secondary">
        <h4 class="m-0"><i class="fas fa-hospital-symbol me-2"></i>ACTIS</h4>
        <small class="text-muted">Gestión Integral</small>
    </div>

    <ul class="list-unstyled components p-2">
        
        <li><div class="section-title">General</div></li>
        <?php if (tienePermiso('ver_dashboard')): ?>
        <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt me-2"></i> Inicio</a></li>
        <?php endif; ?>

        <?php if (tienePermiso('solicitar_insumos') || tienePermiso('solicitar_suministros')): ?>
            <li><div class="section-title text-warning">Mi Servicio</div></li>
            
            <li>
                <a href="#menuCampanas" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle text-warning fw-bold">
                    <i class="fas fa-bullhorn me-2"></i> Campañas Activas
                </a>
                <ul class="collapse list-unstyled ps-3" id="menuCampanas">
                    <?php if (tienePermiso('solicitar_insumos')): ?>
                        <li><a href="campana_carga_insumos.php"><i class="fas fa-pills me-2"></i> Insumos Médicos</a></li>
                    <?php endif; ?>
                    <?php if (tienePermiso('solicitar_suministros')): ?>
                        <li><a href="campana_carga_suministros.php"><i class="fas fa-box me-2"></i> Suministros Grales</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            
            <li><hr class="dropdown-divider border-secondary my-2"></li>

            <?php if (tienePermiso('solicitar_insumos')): ?>
                <li><a href="pedidos_solicitud_interna.php" class="<?php echo ($current_page == 'pedidos_solicitud_interna.php') ? 'active' : ''; ?>"><i class="fas fa-hand-holding-medical me-2"></i> Pedir Insumos (Stock)</a></li>
            <?php endif; ?>
            <?php if (tienePermiso('solicitar_suministros')): ?>
                <li><a href="pedidos_solicitud_interna_suministros.php" class="<?php echo ($current_page == 'pedidos_solicitud_interna_suministros.php') ? 'active' : ''; ?>"><i class="fas fa-dolly me-2"></i> Pedir Suministros (Stock)</a></li>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (tienePermiso('gestionar_planificaciones') || tienePermiso('gestionar_planificaciones_medicas') || tienePermiso('procesar_compra_precios') || tienePermiso('aprobar_planificacion_director')): ?>
            <li><div class="section-title text-info">Planificación</div></li>
            
            <?php if (tienePermiso('gestionar_planificaciones')): ?>
                <li><a href="suministros_planificacion_panel.php" class="<?php echo ($current_page == 'suministros_planificacion_panel.php') ? 'active' : ''; ?>"><i class="fas fa-tasks me-2"></i> Campañas Suministros</a></li>
            <?php endif; ?>

            <?php if (tienePermiso('gestionar_planificaciones_medicas')): ?>
                <li><a href="insumos_planificacion_panel.php" class="<?php echo ($current_page == 'insumos_planificacion_panel.php') ? 'active' : ''; ?>"><i class="fas fa-file-medical me-2"></i> Campañas Insumos</a></li>
            <?php endif; ?>

            <?php if (tienePermiso('procesar_compra_precios') || tienePermiso('aprobar_planificacion_director')): ?>
                <?php if (!tienePermiso('gestionar_planificaciones')): ?>
                    <li><a href="suministros_planificacion_panel.php"><i class="fas fa-box me-2"></i> Campañas Suministros</a></li>
                <?php endif; ?>
                <?php if (!tienePermiso('gestionar_planificaciones_medicas')): ?>
                    <li><a href="insumos_planificacion_panel.php"><i class="fas fa-pills me-2"></i> Campañas Insumos</a></li>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <li><div class="section-title">Consultas</div></li>
        <?php if (tienePermiso('ver_mis_pedidos') || tienePermiso('ver_todos_pedidos_insumos')): ?>
            <li><a href="historial_pedidos.php?tipo=insumos_medicos"><i class="fas fa-list-alt me-2"></i> Historial Insumos</a></li>
        <?php endif; ?>
        <?php if (tienePermiso('ver_mis_pedidos') || tienePermiso('ver_todos_pedidos_suministros')): ?>
            <li><a href="historial_pedidos.php?tipo=suministros"><i class="fas fa-clipboard-list me-2"></i> Historial Suministros</a></li>
        <?php endif; ?>

        <?php if (tienePermiso('gestion_stock_insumos') || tienePermiso('ver_stock_insumos')): ?>
            <li><div class="section-title">Depósito Insumos</div></li>
            <?php if (tienePermiso('ver_todos_pedidos_insumos')): ?>
                <li><a href="historial_pedidos.php?tipo=insumos_medicos&filtro=pendientes"><i class="fas fa-inbox me-2 text-warning"></i> Solicitudes Nuevas</a></li>
            <?php endif; ?>
            <li><a href="insumos_stock.php" class="<?php echo ($current_page == 'insumos_stock.php') ? 'active' : ''; ?>"><i class="fas fa-pills me-2"></i> Stock</a></li>
            <?php if (tienePermiso('ver_entregas_insumos') || tienePermiso('realizar_entrega_insumos')): ?>
                <li><a href="insumos_entregas.php" class="<?php echo ($current_page == 'insumos_entregas.php') ? 'active' : ''; ?>"><i class="fas fa-truck-loading me-2"></i> Entregas</a></li>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (tienePermiso('gestion_stock_suministros') || tienePermiso('ver_stock_suministros')): ?>
            <li><div class="section-title">Depósito Suministros</div></li>
            <?php if (tienePermiso('ver_todos_pedidos_suministros')): ?>
                <li><a href="historial_pedidos.php?tipo=suministros&filtro=pendientes"><i class="fas fa-inbox me-2 text-warning"></i> Solicitudes Nuevas</a></li>
            <?php endif; ?>
            <li><a href="suministros_stock.php" class="<?php echo ($current_page == 'suministros_stock.php') ? 'active' : ''; ?>"><i class="fas fa-boxes me-2"></i> Stock</a></li>
            <?php if (tienePermiso('ver_entregas_suministros') || tienePermiso('realizar_entrega_suministros')): ?>
                <li><a href="suministros_entregas.php" class="<?php echo ($current_page == 'suministros_entregas.php') ? 'active' : ''; ?>"><i class="fas fa-dolly me-2"></i> Entregas</a></li>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (tienePermiso('gestion_compras_insumos') || tienePermiso('ver_oc_insumos_todas') || tienePermiso('ver_oc_insumos_propias') || tienePermiso('aprobar_oc_insumos')): ?>
            <li><div class="section-title">Compras</div></li>
            <li><a href="insumos_compras.php"><i class="fas fa-file-invoice-dollar me-2"></i> OC Insumos</a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('gestion_compras_suministros') || tienePermiso('ver_oc_suministros_todas') || tienePermiso('ver_oc_suministros_propias') || tienePermiso('aprobar_oc_suministros')): ?>
            <?php if (! (tienePermiso('gestion_compras_insumos') || tienePermiso('ver_oc_insumos_todas') || tienePermiso('ver_oc_insumos_propias') || tienePermiso('aprobar_oc_insumos'))): ?>
                <li><div class="section-title">Compras</div></li>
            <?php endif; ?>
            <li><a href="suministros_compras.php"><i class="fas fa-file-invoice me-2"></i> OC Suministros</a></li>
        <?php endif; ?>

        <?php if (tienePermiso('ver_menu_configuracion')): ?>
            <li><div class="section-title">Configuración</div></li>
            <li><a href="admin_roles.php"><i class="fas fa-user-shield me-2"></i> Roles</a></li>
            <li><a href="admin_usuarios.php"><i class="fas fa-users-cog me-2"></i> Usuarios</a></li>
            <li><a href="admin_sistema.php"><i class="fas fa-cogs me-2"></i> Sistema</a></li>
        <?php endif; ?>

        <li class="mt-4 border-top border-secondary pt-2">
            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a>
        </li>
    </ul>
</nav>