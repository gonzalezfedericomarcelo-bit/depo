<?php
// Archivo: bandeja_gestion_dinamica.php
// Propósito: Motor de Procesos Suministros/Insumos

require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] != 'POST') { header("Location: dashboard.php"); exit; }

$id_pedido = $_POST['id_pedido'];
$accion = $_POST['accion'];
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Obtener datos
    $stmt = $pdo->prepare("SELECT * FROM pedidos_servicio WHERE id = :id");
    $stmt->execute([':id' => $id_pedido]);
    $pedido = $stmt->fetch();

    // 1. ENCARGADO RECIBE (Paso 2 -> 3)
    if ($accion == 'confirmar_recepcion_solicitud') {
        // Buscar paso "en_preparacion"
        $stmtNext = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = :proc AND nombre_estado = 'en_preparacion'");
        $stmtNext->execute([':proc' => $pedido['nombre_proceso']]);
        $siguiente = $stmtNext->fetch();

        $pdo->prepare("UPDATE pedidos_servicio SET estado = :est, paso_actual_id = :pid WHERE id = :id")
            ->execute([':est'=>$siguiente['nombre_estado'], ':pid'=>$siguiente['id'], ':id'=>$id_pedido]);

        // Notificar Usuario
        $msj = "Tu pedido fue aprobado y está en preparación.";
        $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, mensaje, url_destino) VALUES (?,?,?)")
            ->execute([$pedido['id_usuario_solicitante'], $msj, "pedidos_ver.php?id=$id_pedido"]);
    }

    // 2. ENCARGADO ENTREGA (Paso 3 -> 4)
    elseif ($accion == 'realizar_entrega') {
        // Buscar paso "listo_para_retirar"
        $stmtNext = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = :proc AND nombre_estado = 'listo_para_retirar'");
        $stmtNext->execute([':proc' => $pedido['nombre_proceso']]);
        $siguiente = $stmtNext->fetch();

        $pdo->prepare("UPDATE pedidos_servicio SET estado = :est, paso_actual_id = :pid WHERE id = :id")
            ->execute([':est'=>$siguiente['nombre_estado'], ':pid'=>$siguiente['id'], ':id'=>$id_pedido]);

        // Notificar Usuario
        $msj = "📦 ¡Tu pedido está LISTO! Pasa por Depósito a retirarlo y confirmar.";
        $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, mensaje, url_destino) VALUES (?,?,?)")
            ->execute([$pedido['id_usuario_solicitante'], $msj, "pedidos_ver.php?id=$id_pedido"]);
    }

    // 3. USUARIO CONFIRMA (Fin -> Stock -> PDF)
    elseif ($accion == 'confirmar_retiro_usuario') {
        
        // A. Crear registro ENTREGA
        $stmtEnt = $pdo->prepare("INSERT INTO entregas (tipo_origen, id_usuario_responsable, solicitante_nombre, solicitante_area) VALUES (:tipo, :user, :nom, :area)");
        $tipo_origen = ($pedido['tipo_insumo'] == 'insumos_medicos') ? 'insumos' : 'suministros';
        
        // Buscamos nombre solicitante
        $stmtSol = $pdo->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
        $stmtSol->execute([$pedido['id_usuario_solicitante']]);
        $nom_sol = $stmtSol->fetchColumn();

        $stmtEnt->execute([':tipo'=>$tipo_origen, ':user'=>$user_id, ':nom'=>$nom_sol, ':area'=>$pedido['servicio_solicitante']]);
        $id_entrega = $pdo->lastInsertId();

        // B. Descontar Stock
        $stmtItems = $pdo->prepare("SELECT * FROM pedidos_items WHERE id_pedido = :id");
        $stmtItems->execute([':id' => $id_pedido]);
        $items = $stmtItems->fetchAll();

        $tabla_stock = ($pedido['tipo_insumo'] == 'insumos_medicos') ? 'insumos_medicos' : 'suministros_generales';
        $col_id = ($pedido['tipo_insumo'] == 'insumos_medicos') ? 'id_insumo' : 'id_suministro';

        foreach ($items as $item) {
            $cantidad = $item['cantidad_aprobada'] ?? $item['cantidad_solicitada'];
            if ($cantidad > 0) {
                // Descuento
                $sqlStock = "UPDATE $tabla_stock SET stock_actual = stock_actual - :cant WHERE id = :id";
                $pdo->prepare($sqlStock)->execute([':cant'=>$cantidad, ':id'=>$item[$col_id]]);
                // Item entrega
                $pdo->prepare("INSERT INTO entregas_items (id_entrega, $col_id, cantidad) VALUES (?, ?, ?)")->execute([$id_entrega, $item[$col_id], $cantidad]);
                // Item pedido
                $pdo->prepare("UPDATE pedidos_items SET cantidad_entregada = :cant WHERE id = :id")->execute([':cant'=>$cantidad, ':id'=>$item['id']]);
            }
        }

        // C. Finalizar
        $pdo->prepare("UPDATE pedidos_servicio SET estado = 'finalizado_proceso', paso_actual_id = NULL, fecha_entrega_real = NOW(), id_entrega_generada = :ide WHERE id = :id")
            ->execute([':ide'=>$id_entrega, ':id'=>$id_pedido]);
        
        // Notificar Cierre
        $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, mensaje, url_destino) VALUES (?,?,?)")
            ->execute([$user_id, "Circuito cerrado exitosamente.", "pedidos_ver.php?id=$id_pedido"]);
    }

    $pdo->commit();
    header("Location: pedidos_ver.php?id=$id_pedido&msg=ok");

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>