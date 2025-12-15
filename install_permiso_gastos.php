<?php
// Archivo: install_permiso_gastos.php
require 'db.php';

try {
    // Insertamos el permiso en la categoría 'Dashboard: Finanzas'
    $sql = "INSERT IGNORE INTO permisos (clave, nombre, categoria) 
            VALUES ('ver_gastos_detallados', 'Ver Analíticas Financieras Avanzadas (Gastos, Tortas, Estadísticas)', 'Dashboard: Finanzas')";
    
    $pdo->exec($sql);
    
    echo "<h2 style='color:green'>✅ Interruptor Financiero Instalado.</h2>";
    echo "<p>Ahora ve a <b>Admin Roles</b>. En la sección 'Dashboard: Finanzas' verás la nueva opción.</p>";
    echo "<a href='admin_roles.php'>Ir a Configurar Roles</a>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>