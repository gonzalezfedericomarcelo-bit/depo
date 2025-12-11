<?php
// Archivo: api_notificaciones.php
// Propósito: Backend para consultar notificaciones activas

require 'db.php';
session_start();

header('Content-Type: application/json');

// Si no está logueado, devolver vacío
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'items' => []]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Obtener los IDs de los roles del usuario actual
    $stmtRoles = $pdo->prepare("SELECT id_rol FROM usuario_roles WHERE id_usuario = :id");
    $stmtRoles->execute(['id' => $user_id]);
    $roles_ids = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

    // Preparar la cláusula para roles (si tiene roles)
    $clausula_roles = "";
    if (!empty($roles_ids)) {
        // Sanitizar ids para evitar inyección (aunque sean ints)
        $roles_list = implode(',', array_map('intval', $roles_ids));
        $clausula_roles = "OR id_rol_destino IN ($roles_list)";
    }

    // 2. Consultar Notificaciones NO LEÍDAS (Para Mí Usuario O Para Mis Roles)
    $sql = "SELECT * FROM notificaciones 
            WHERE leida = 0 
            AND (id_usuario_destino = :uid $clausula_roles)
            ORDER BY fecha_creacion DESC LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $user_id]);
    $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Devolver respuesta
    echo json_encode([
        'count' => count($notificaciones),
        'latest' => !empty($notificaciones) ? $notificaciones[0] : null,
        'items' => $notificaciones
    ]);

} catch (Exception $e) {
    // En caso de error, devolver vacío para no romper el JS
    echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
}
?>