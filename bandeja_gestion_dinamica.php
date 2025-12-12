<?php
// Archivo: bandeja_gestion_dinamica.php
// Propósito: Motor de Procesos UNIFICADO (Insumos y Suministros)

require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] != 'POST') { header("Location: dashboard.php"); exit; }

$id_pedido = $_POST['id_pedido'];
$accion = $_POST['accion'];
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM pedidos_servicio WHERE id = :id");
    $stmt->execute([':id' => $id_pedido]);
    $pedido = $stmt->fetch();
    if (!$pedido) throw new Exception("Pedido no encontrado");
    $nombre_proceso = $pedido['proceso_origen']; 

    /* ======================================================
       LÓGICA INSUMOS MÉDICOS (Encargado Insumos)
       ====================================================== */
    
    // 1. INSUMOS: RECIBIR AUTORIZACIÓN (Paso 3 -> 4)
    if ($accion == 'confirmar_recepcion_insumos') {
        $stmtNext = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = :proc AND nombre_estado = 'en_preparacion'");
        $stmtNext->execute([':proc' => $nombre_proceso]);
        $siguiente = $stmtNext->fetch();

        if ($siguiente) {
            $pdo->prepare("UPDATE pedidos_servicio SET estado = :est, paso_actual_id = :pid WHERE id = :id")
                ->execute([':est'=>$siguiente['nombre_estado'], ':pid'=>$siguiente['id'], ':id'=>$id_pedido]);
            // No se notifica nada especial al usuario aquí, sigue esperando.
        }
    }

    // 2. INSUMOS: ENTREGAR Y NOTIFICAR (Paso 4 -> 5)
    elseif ($accion == 'realizar_entrega_insumos') {
        $stmtNext = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = :proc AND nombre_estado = 'listo_para_retirar'");
        $stmtNext->execute([':proc' => $nombre_proceso]);
        $siguiente = $stmtNext->fetch();

        if ($siguiente) {
            // Guardamos al usuario que preparó (id_usuario_entrega)
            $pdo->prepare("UPDATE pedidos_servicio SET estado = :est, paso_actual_id = :pid, id_usuario_entrega = :user WHERE id = :id")
                ->execute([':est'=>$siguiente['nombre_estado'], ':pid'=>$siguiente['id'], ':user'=>$user_id, ':id'=>$id_pedido]);

            // Notificación al Usuario
            $msj = "📦 TUS INSUMOS ESTÁN LISTOS. Por favor pasa por Depósito a retirar y confirmar.";
            $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, mensaje, url_destino) VALUES (?,?,?)")
                ->execute([$pedido['id_usuario_solicitante'], $msj, "pedidos_ver.php?id=$id_pedido"]);
        }
    }


    /* ======================================================
       LÓGICA SUMINISTROS (Encargado Suministros) - YA EXISTÍA
       ====================================================== */
    elseif ($accion == 'confirmar_recepcion_solicitud') { // Recibir Suministros
        $stmtNext = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = :proc AND nombre_estado = 'en_preparacion'");
        $stmtNext->execute([':proc' => $nombre_proceso]);
        $siguiente = $stmtNext->fetch();
        if ($siguiente) {
            $pdo->prepare("UPDATE pedidos_servicio SET estado = :est, paso_actual_id = :pid WHERE id = :id")
                ->execute([':est'=>$siguiente['nombre_estado'], ':pid'=>$siguiente['id'], ':id'=>$id_pedido]);
            $msj = "✅ Tu solicitud fue aprobada, pero está en espera de que el depósito prepare los suministros.";
            $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, mensaje, url_destino) VALUES (?,?,?)")
                ->execute([$pedido['id_usuario_solicitante'], $msj, "pedidos_ver.php?id=$id_pedido"]);
        }
    }
    elseif ($accion == 'realizar_entrega') { // Entregar Suministros
        $stmtNext = $pdo->prepare("SELECT * FROM config_flujos WHERE nombre_proceso = :proc AND nombre_estado = 'listo_para_retirar'");
        $stmtNext->execute([':proc' => $nombre_proceso]);
        $siguiente = $stmtNext->fetch();
        if ($siguiente) {
            $pdo->prepare("UPDATE pedidos_servicio SET estado = :est, paso_actual_id = :pid, id_usuario_entrega = :user WHERE id = :id")
                ->execute([':est'=>$siguiente['nombre_estado'], ':pid'=>$siguiente['id'], ':user'=>$user_id, ':id'=>$id_pedido]);
            $msj = "📦 ¡Tu pedido está listo! Ya puedes pasar a retirar tus suministros.";
            $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, mensaje, url_destino) VALUES (?,?,?)")
                ->execute([$pedido['id_usuario_solicitante'], $msj, "pedidos_ver.php?id=$id_pedido"]);
        }
    }


    /* ======================================================
       CIERRE COMÚN (Usuario confirma retiro)
       ====================================================== */
    elseif ($accion == 'confirmar_retiro_usuario') {
        
        $tipo_origen = ($pedido['tipo_insumo'] == 'insumos_medicos') ? 'insumos' : 'suministros';
        
        // 1. Historial Entrega
        $stmtEnt = $pdo->prepare("INSERT INTO entregas (tipo_origen, id_usuario_responsable, solicitante_nombre, solicitante_area) VALUES (:tipo, :user, :nom, :area)");
        $stmtSol = $pdo->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
        $stmtSol->execute([$pedido['id_usuario_solicitante']]);
        $nom_sol = $stmtSol->fetchColumn();
        $stmtEnt->execute([':tipo'=>$tipo_origen, ':user'=>$user_id, ':nom'=>$nom_sol, ':area'=>$pedido['servicio_solicitante']]);
        $id_entrega = $pdo->lastInsertId();

        // 2. Descuento Stock
        $stmtItems = $pdo->prepare("SELECT * FROM pedidos_items WHERE id_pedido = :id");
        $stmtItems->execute([':id' => $id_pedido]);
        $items = $stmtItems->fetchAll();

        $tabla_stock = ($pedido['tipo_insumo'] == 'insumos_medicos') ? 'insumos_medicos' : 'suministros_generales';
        $col_id = ($pedido['tipo_insumo'] == 'insumos_medicos') ? 'id_insumo' : 'id_suministro';

        foreach ($items as $item) {
            $cantidad = $item['cantidad_aprobada'] ?? $item['cantidad_solicitada'];
            if ($cantidad > 0) {
                $sqlStock = "UPDATE $tabla_stock SET stock_actual = stock_actual - :cant WHERE id = :id";
                $pdo->prepare($sqlStock)->execute([':cant'=>$cantidad, ':id'=>$item[$col_id]]);
                
                $pdo->prepare("INSERT INTO entregas_items (id_entrega, $col_id, cantidad) VALUES (?, ?, ?)")
                    ->execute([$id_entrega, $item[$col_id], $cantidad]);
                
                $pdo->prepare("UPDATE pedidos_items SET cantidad_entregada = :cant WHERE id = :id")
                    ->execute([':cant'=>$cantidad, ':id'=>$item['id']]);
            }
        }

        // 3. Finalizar
        $pdo->prepare("UPDATE pedidos_servicio SET estado = 'finalizado_proceso', paso_actual_id = NULL, fecha_entrega_real = NOW(), id_entrega_generada = :ide WHERE id = :id")
            ->execute([':ide'=>$id_entrega, ':id'=>$id_pedido]);
        
        $pdo->prepare("INSERT INTO notificaciones (id_usuario_destino, mensaje, url_destino) VALUES (?,?,?)")
            ->execute([$user_id, "Proceso finalizado.", "pedidos_ver.php?id=$id_pedido"]);
    }

    $pdo->commit();
    header("Location: pedidos_ver.php?id=$id_pedido&msg=ok");

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>