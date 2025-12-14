<?php
// Archivo: db.php
date_default_timezone_set('America/Argentina/Buenos_Aires');
setlocale(LC_TIME, 'es_AR.UTF-8', 'es_AR', 'esp');

$host = 'localhost';
$db   = 'u415354546_deposito';
$user = 'u415354546_deposito';
$pass = 'Fmg35911@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '-03:00'"); 
} catch (\PDOException $e) {
    die("Error de Conexión: " . $e->getMessage());
}

// FUNCIONES DE SEGURIDAD
if (!function_exists('tienePermiso')) {
    function tienePermiso($clave) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $user_id = $_SESSION['user_id'] ?? 0;
        if ($user_id == 0) return false;

        // Cache para velocidad
        static $cache = [];
        if (isset($cache[$clave])) return $cache[$clave];

        global $pdo;
        // Consulta exacta: Usuario -> Rol -> Permiso
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rol_permisos rp 
                               JOIN permisos p ON rp.id_permiso = p.id 
                               JOIN usuario_roles ur ON rp.id_rol = ur.id_rol 
                               WHERE ur.id_usuario = ? AND p.clave = ?");
        $stmt->execute([$user_id, $clave]);
        $tiene = $stmt->fetchColumn() > 0;
        
        $cache[$clave] = $tiene;
        return $tiene;
    }
}

if (!function_exists('obtenerIdRolPorPermiso')) {
    function obtenerIdRolPorPermiso($clave) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT rp.id_rol FROM rol_permisos rp JOIN permisos p ON rp.id_permiso = p.id WHERE p.clave = ? LIMIT 1");
        $stmt->execute([$clave]);
        return $stmt->fetchColumn();
    }
}
?>