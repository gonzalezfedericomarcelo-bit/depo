<?php
// Archivo: actualizar_db_areas.php
require 'db.php';

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h1>🔧 Configuración de Áreas Dinámicas</h1>";

try {
    // Crear tabla recursiva (Padre -> Hijo)
    $sql = "CREATE TABLE IF NOT EXISTS areas_servicios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        id_padre INT DEFAULT NULL,
        FOREIGN KEY (id_padre) REFERENCES areas_servicios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✅ Tabla 'areas_servicios' creada correctamente.</p>";

    // Insertar datos de ejemplo SOLO si está vacía
    $check = $pdo->query("SELECT COUNT(*) FROM areas_servicios")->fetchColumn();
    if ($check == 0) {
        // Ejemplo: Dirección
        $pdo->exec("INSERT INTO areas_servicios (nombre, id_padre) VALUES ('Dirección', NULL)");
        $id_dir = $pdo->lastInsertId();
        $pdo->exec("INSERT INTO areas_servicios (nombre, id_padre) VALUES ('Dirección Médica', $id_dir)");
        $pdo->exec("INSERT INTO areas_servicios (nombre, id_padre) VALUES ('Dirección Administrativa', $id_dir)");

        // Ejemplo: Guardia
        $pdo->exec("INSERT INTO areas_servicios (nombre, id_padre) VALUES ('Guardia', NULL)");
        $id_gua = $pdo->lastInsertId();
        $pdo->exec("INSERT INTO areas_servicios (nombre, id_padre) VALUES ('Adultos', $id_gua)");
        $pdo->exec("INSERT INTO areas_servicios (nombre, id_padre) VALUES ('Pediatría', $id_gua)");
        
        echo "<p style='color:blue'>ℹ️ Se cargaron datos de ejemplo iniciales.</p>";
    }

    echo "<hr><a href='admin_areas.php'>Ir al Gestor de Áreas</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Error</h2><p>" . $e->getMessage() . "</p>";
}
echo "</div>";
?>