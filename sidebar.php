<?php
// Archivo: includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);

// Función auxiliar de permisos
if (!function_exists('tienePermiso')) {
    function tienePermiso($clave) {
        if (in_array('Administrador', $_SESSION['user_roles'] ?? [])) return true;
        global $pdo;
        $user_id = $_SESSION['user_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rol_permisos rp JOIN permisos p ON rp.id_permiso=p.id JOIN usuario_roles ur ON rp.id_rol=ur.id_rol WHERE ur.id_usuario=? AND p.clave=?");
        $stmt->execute([$user_id, $clave]);
        return $stmt->fetchColumn() > 0;
    }
}
$my_roles = $_SESSION['user_roles'] ?? [];
?>

<nav id="sidebar" class="sidebar d-none d-md-block bg-dark text-white">
    <div class="brand p-3 text-center border-bottom border-secondary">
        <h4 class="m-0"><i class="fas fa-hospital-symbol me-2"></i>ACTIS</h4>
        <small class="text-muted">Gestión Integral</small>
    </div>

    <ul class="list-unstyled components p-2">
        <li><div class="section-title">General</div></li>
        <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt me-2"></i> Inicio</a></li>

        <li><div class="section-title text-warning">Mi Servicio</div></li>
        <?php if (tienePermiso('solicitar_insumos')): ?>
            <li><a href="pedidos_solicitud_interna.php" class="<?php echo ($current_page == 'pedidos_solicitud_interna.php') ? 'active' : ''; ?>"><i class="fas fa-hand-holding-medical me-2"></i> Pedir Insumos</a></li>
        <?php endif; ?>
        <?php if (tienePermiso('solicitar_suministros')): ?>
            <li><a href="pedidos_solicitud_interna_suministros.php" class="<?php echo ($current_page == 'pedidos_solicitud_interna_suministros.php') ? 'active' : ''; ?>"><i class="fas fa-dolly me-2"></i> Pedir Suministros</a></li>
        <?php endif; ?>

        <li><div class="section-title">Consultas</div></li>
        <?php if (tienePermiso('ver_mis_pedidos') || tienePermiso('ver_todos_pedidos_insumos')): ?>
            <li><a href="historial_pedidos.php?tipo=insumos_medicos"><i class="fas fa-list-alt me-2"></i> Historial Insumos</a></li>
        <?php endif; ?>
        <?php if (tienePermiso('ver_mis_pedidos') || tienePermiso('ver_todos_pedidos_suministros')): ?>
            <li><a href="historial_pedidos.php?tipo=suministros"><i class="fas fa-clipboard-list me-2"></i> Historial Suministros</a></li>
        <?php endif; ?>

        <?php if (tienePermiso('gestion_stock_insumos')): ?>
            <li><div class="section-title">Depósito Insumos</div></li>
            <li><a href="insumos_stock.php"><i class="fas fa-pills me-2"></i> Stock</a></li>
            <li><a href="insumos_entregas.php"><i class="fas fa-truck-loading me-2"></i> Entregas</a></li>
        <?php endif; ?>

        <?php if (tienePermiso('gestion_stock_suministros')): ?>
            <li><div class="section-title">Depósito Suministros</div></li>
            <li><a href="suministros_stock.php"><i class="fas fa-boxes me-2"></i> Stock</a></li>
            <li><a href="suministros_entregas.php"><i class="fas fa-dolly me-2"></i> Entregas</a></li>
        <?php endif; ?>

        <?php if (in_array('Administrador', $my_roles)): ?>
            <li><div class="section-title">Configuración</div></li>
            <li><a href="admin_roles.php" class="<?php echo ($current_page == 'admin_roles.php') ? 'active' : ''; ?>"><i class="fas fa-user-shield me-2"></i> Roles y Permisos</a></li>
            
            <li><a href="admin_flujos.php" class="<?php echo ($current_page == 'admin_flujos.php') ? 'active' : ''; ?>"><i class="fas fa-project-diagram me-2"></i> Flujos de Trabajo</a></li>
            
            <li><a href="admin_areas.php" class="<?php echo ($current_page == 'admin_areas.php') ? 'active' : ''; ?>"><i class="fas fa-sitemap me-2"></i> Áreas y Servicios</a></li>
            <li><a href="admin_usuarios.php" class="<?php echo ($current_page == 'admin_usuarios.php') ? 'active' : ''; ?>"><i class="fas fa-users-cog me-2"></i> Usuarios</a></li>
            <li><a href="admin_auditoria.php" class="<?php echo ($current_page == 'admin_auditoria.php') ? 'active' : ''; ?>"><i class="fas fa-shield-alt me-2"></i> Auditoría</a></li>
            <li><a href="admin_sistema.php" class="<?php echo ($current_page == 'admin_sistema.php') ? 'active' : ''; ?>"><i class="fas fa-cogs me-2"></i> Sistema</a></li>
        <?php endif; ?>

        <li class="mt-4 border-top border-secondary pt-2">
            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a>
        </li>
    </ul>
</nav>