<?php
// Archivo: instalar_permiso_notif.php
require 'db.php';

try {
    // Insertamos el permiso en la categoría 'Sistema' o 'Servicios'
    $sql = "INSERT IGNORE INTO permisos (clave, nombre, categoria) 
            VALUES ('recibir_avisos_campana', 'Recibir Aviso de Nuevas Campañas', '1. Sistema')";
    
    $pdo->exec($sql);
    
    echo "<h2 style='color:green'>✅ Permiso instalado correctamente.</h2>";
    echo "<p>Ahora ve a <b>Admin Roles</b> y actívalo para el rol 'Servicio' (y otros que quieras).</p>";
    echo "<a href='admin_roles.php'>Ir a Configurar Roles</a>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>