<?php
// Archivo: db.php
// Propósito: Conexión DB, Configuración Regional y Funciones Globales

// 1. CONFIGURACIÓN DE ZONA HORARIA (ARGENTINA)
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
    
    // 2. FORZAR HORARIO ARGENTINA EN LA BASE DE DATOS TAMBIÉN
    $pdo->exec("SET time_zone = '-03:00'"); 
    
} catch (\PDOException $e) {
    echo "<h1>❌ Error de Conexión</h1>";
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// FUNCIONES DE SEGURIDAD (GLOBALES)
// Se mantienen intactas como pediste

if (!function_exists('tienePermiso')) {
    function tienePermiso($clave) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        // Superusuario
        if (in_array('Administrador', $_SESSION['user_roles'] ?? [])) return true;

        global $pdo;
        $user_id = $_SESSION['user_id'] ?? 0;
        
        // Verificar permiso
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rol_permisos rp JOIN permisos p ON rp.id_permiso=p.id JOIN usuario_roles ur ON rp.id_rol=ur.id_rol WHERE ur.id_usuario=? AND p.clave=?");
        $stmt->execute([$user_id, $clave]);
        return $stmt->fetchColumn() > 0;
    }
}

if (!function_exists('obtenerIdRolPorPermiso')) {
    function obtenerIdRolPorPermiso($clave_permiso) {
        global $pdo;
        // Busca qué rol tiene asignado este permiso (para enviar notificaciones dinámicas)
        $stmt = $pdo->prepare("SELECT rp.id_rol FROM rol_permisos rp JOIN permisos p ON rp.id_permiso = p.id WHERE p.clave = ? LIMIT 1");
        $stmt->execute([$clave_permiso]);
        return $stmt->fetchColumn();
    }
}
?>