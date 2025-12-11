<?php
// Archivo: db.php
// Propósito: Conexión centralizada a la base de datos usando PDO.

// Credenciales
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
} catch (\PDOException $e) {
    // Si falla, mostramos el error
    echo "<h1>❌ Error de Conexión</h1>";
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
// NOTA: NO AGREGAR EL CIERRE DE PHP AQUÍ PARA EVITAR ERRORES EN PDFS