-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 12-12-2025 a las 19:25:48
-- Versión del servidor: 11.8.3-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u415354546_deposito`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `adjuntos`
--

CREATE TABLE `adjuntos` (
  `id` int(11) NOT NULL,
  `entidad_tipo` enum('orden_compra','entrega','usuario','pedido_servicio') NOT NULL,
  `id_entidad` int(11) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `fecha_subida` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `adjuntos`
--

INSERT INTO `adjuntos` (`id`, `entidad_tipo`, `id_entidad`, `ruta_archivo`, `nombre_original`, `fecha_subida`) VALUES
(1, 'orden_compra', 6, 'uploads/ordenes_compra/693c3b9be6aa4_dashboard.php', 'dashboard.php', '2025-12-12 15:58:19'),
(2, 'orden_compra', 7, 'uploads/ordenes_compra/693c3d31ce6e8_pedidos_solicitud_interna_suministros.php', 'pedidos_solicitud_interna_suministros.php', '2025-12-12 16:05:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas_servicios`
--

CREATE TABLE `areas_servicios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `id_padre` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `areas_servicios`
--

INSERT INTO `areas_servicios` (`id`, `nombre`, `id_padre`) VALUES
(7, 'ACTIS', NULL),
(8, 'Laboratorio', 7),
(9, 'Odontología', 7),
(10, 'Seguridad e higiene', 7),
(11, 'Demanda espontanea', 7),
(12, 'Ginecología', 7),
(13, 'Psicología', 7),
(14, 'CM Martelli', NULL),
(15, 'CM Morón', NULL),
(16, 'CM Temperley', NULL),
(17, 'CM Quilmes', NULL),
(18, 'CM Martínez', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras_planificaciones`
--

CREATE TABLE `compras_planificaciones` (
  `id` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado` enum('abierta','cerrada_logistica','aprobada_director','en_compras','orden_generada','finalizada') DEFAULT 'abierta',
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `tipo_insumo` enum('insumos','suministros') NOT NULL DEFAULT 'suministros'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `compras_planificaciones`
--

INSERT INTO `compras_planificaciones` (`id`, `titulo`, `fecha_inicio`, `fecha_fin`, `estado`, `creado_por`, `fecha_creacion`, `tipo_insumo`) VALUES
(1, 'AÑO 2025 DICIEMRBE', '2025-12-12', '2026-01-12', 'aprobada_director', 2, '2025-12-12 13:24:36', 'suministros'),
(2, '20225', '2025-12-12', '2026-02-04', 'orden_generada', 1, '2025-12-12 13:50:17', 'insumos'),
(3, 'FEDE2025', '2025-12-12', '2026-01-12', 'orden_generada', 7, '2025-12-12 15:28:28', 'insumos');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `config_flujos`
--

CREATE TABLE `config_flujos` (
  `id` int(11) NOT NULL,
  `nombre_proceso` varchar(50) NOT NULL,
  `paso_orden` int(11) NOT NULL,
  `nombre_estado` varchar(50) NOT NULL,
  `etiqueta_estado` varchar(100) NOT NULL,
  `id_rol_responsable` int(11) NOT NULL,
  `requiere_firma` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `config_flujos`
--

INSERT INTO `config_flujos` (`id`, `nombre_proceso`, `paso_orden`, `nombre_estado`, `etiqueta_estado`, `id_rol_responsable`, `requiere_firma`) VALUES
(1, 'adquisicion_insumos', 1, 'revision_encargado', 'Revisión Encargado Insumos', 4, 0),
(2, 'adquisicion_insumos', 2, 'aprobacion_director', 'Aprobación Director Médico', 7, 1),
(3, 'adquisicion_insumos', 3, 'gestion_compras', 'Gestión de Compras', 2, 0),
(7, 'adquisicion_suministros', 1, 'revision_encargado', 'Centralización Encargado', 5, 0),
(8, 'adquisicion_suministros', 2, 'aprobacion_operativo', 'Aprobación Dir. Operativo', 8, 1),
(9, 'adquisicion_suministros', 3, 'gestion_compras', 'Gestión de Compras', 2, 0),
(16, 'adquisicion_insumos', 4, 'recepcion_deposito', 'Recepción en Depósito', 4, 0),
(17, 'adquisicion_suministros', 4, 'recepcion_deposito', 'Recepción en Depósito', 5, 0),
(42, 'movimiento_suministros', 1, 'revision_logistica', 'Revisión Logística', 3, 0),
(43, 'movimiento_suministros', 2, 'pendiente_deposito', 'Pendiente Recepción en Depósito', 5, 0),
(44, 'movimiento_suministros', 3, 'en_preparacion', 'En Preparación (Depósito)', 5, 0),
(45, 'movimiento_suministros', 4, 'listo_para_retirar', 'Listo para Retirar (Confirmar)', 12, 0),
(46, 'movimiento_insumos', 1, 'revision_encargado', 'Revisión Encargado Insumos', 4, 0),
(47, 'movimiento_insumos', 2, 'revision_director', 'Aprobación Director Médico', 7, 0),
(48, 'movimiento_insumos', 3, 'pendiente_preparacion', 'Pendiente Preparación (Depósito)', 4, 0),
(49, 'movimiento_insumos', 4, 'en_preparacion', 'En Preparación', 4, 0),
(50, 'movimiento_insumos', 5, 'listo_para_retirar', 'Listo para Retirar', 12, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `config_procesos`
--

CREATE TABLE `config_procesos` (
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `id_rol_iniciador` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `config_procesos`
--

INSERT INTO `config_procesos` (`codigo`, `nombre`, `id_rol_iniciador`) VALUES
('adquisicion_insumos', 'Adquisición Insumos Médicos', 0),
('adquisicion_suministros', 'Adquisición Suministros Grales', 0),
('movimiento_insumos', 'Movimiento Interno Insumos', 0),
('movimiento_suministros', 'Movimiento Interno Suministros', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entregas`
--

CREATE TABLE `entregas` (
  `id` int(11) NOT NULL,
  `tipo_origen` enum('insumos','suministros') NOT NULL,
  `id_usuario_responsable` int(11) NOT NULL,
  `solicitante_nombre` varchar(100) NOT NULL,
  `solicitante_area` varchar(100) NOT NULL,
  `firma_solicitante_data` longtext DEFAULT NULL,
  `fecha_entrega` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entregas`
--

INSERT INTO `entregas` (`id`, `tipo_origen`, `id_usuario_responsable`, `solicitante_nombre`, `solicitante_area`, `firma_solicitante_data`, `fecha_entrega`) VALUES
(1, 'insumos', 1, 'Super Admin', 'Sin Servicio', NULL, '2025-12-12 12:10:17'),
(2, 'suministros', 8, 'Jefe de Laboratorio', 'Laboratorio', NULL, '2025-12-12 13:18:56'),
(3, 'insumos', 8, 'Jefe de Laboratorio', 'Laboratorio', NULL, '2025-12-12 13:47:43'),
(4, 'insumos', 8, 'Jefe de Laboratorio', 'Laboratorio', NULL, '2025-12-12 15:25:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entregas_items`
--

CREATE TABLE `entregas_items` (
  `id` int(11) NOT NULL,
  `id_entrega` int(11) NOT NULL,
  `id_insumo` int(11) DEFAULT NULL,
  `id_suministro` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entregas_items`
--

INSERT INTO `entregas_items` (`id`, `id_entrega`, `id_insumo`, `id_suministro`, `cantidad`) VALUES
(1, 1, 4, NULL, 1),
(2, 2, NULL, 1, 30),
(3, 3, 3, NULL, 20),
(4, 4, 3, NULL, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_ajustes`
--

CREATE TABLE `historial_ajustes` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo_origen` enum('insumo','suministro') NOT NULL,
  `id_item` int(11) NOT NULL,
  `stock_anterior` int(11) NOT NULL,
  `stock_nuevo` int(11) NOT NULL,
  `fecha_cambio` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_ajustes`
--

INSERT INTO `historial_ajustes` (`id`, `id_usuario`, `tipo_origen`, `id_item`, `stock_anterior`, `stock_nuevo`, `fecha_cambio`) VALUES
(1, 7, 'insumo', 2, 50, 20, '2025-12-12 13:48:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insumos_medicos`
--

CREATE TABLE `insumos_medicos` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `unidad_medida` varchar(50) DEFAULT 'unidades',
  `stock_actual` int(11) DEFAULT 0,
  `stock_minimo` int(11) DEFAULT 5,
  `fecha_vencimiento` date DEFAULT NULL,
  `lote` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `insumos_medicos`
--

INSERT INTO `insumos_medicos` (`id`, `codigo`, `nombre`, `descripcion`, `unidad_medida`, `stock_actual`, `stock_minimo`, `fecha_vencimiento`, `lote`, `updated_at`) VALUES
(1, 'MED-001', 'Paracetamol 500mg', 'Analgésico y antipirético', 'cajas', 100, 20, '2026-12-01', 'LOT-9988', '2025-12-12 11:10:00'),
(2, 'MED-002', 'Ibuprofeno 600mg', 'Antiinflamatorio no esteroideo', 'cajas', 20, 15, '2025-08-15', 'LOT-1122', '2025-12-12 13:48:36'),
(3, 'MED-003', 'Gasas Estériles 10x10', 'Sobres individuales', 'unidades', 375, 100, '2027-01-01', 'GAS-001', '2025-12-12 15:25:28'),
(4, 'MED-004', 'Jeringas 5ml', 'Sin aguja, descartables', 'unidades', 234, 50, '2028-05-20', 'JER-555', '2025-12-12 12:10:17'),
(5, 'MED-005', 'Agua Oxigenada 10vol', 'Frasco 250ml', 'litros', 23, 5, '2025-11-30', 'OXI-22', '2025-12-12 11:10:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `id_usuario_destino` int(11) DEFAULT NULL,
  `id_rol_destino` int(11) DEFAULT NULL,
  `mensaje` varchar(255) NOT NULL,
  `url_destino` varchar(255) DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--

INSERT INTO `notificaciones` (`id`, `id_usuario_destino`, `id_rol_destino`, `mensaje`, `url_destino`, `leida`, `fecha_creacion`) VALUES
(1, NULL, 4, 'Nueva solicitud de Insumos: Usuario', 'pedidos_ver.php?id=1', 1, '2025-12-12 11:39:10'),
(2, NULL, 7, 'Solicitud Insumos revisada por Encargado (ID #1). Requiere su aprobación.', 'pedidos_ver.php?id=1', 1, '2025-12-12 12:05:26'),
(3, 1, NULL, '✅ Tu pedido fue aprobado por el Director Médico. Está en espera de preparación.', 'pedidos_ver.php?id=1', 1, '2025-12-12 12:08:27'),
(4, NULL, 4, 'Director Médico aprobó pedido #1. Proceder con el movimiento.', 'pedidos_ver.php?id=1', 1, '2025-12-12 12:08:27'),
(5, 1, NULL, '📦 TUS INSUMOS ESTÁN LISTOS. Por favor pasa por Depósito a retirar y confirmar.', 'pedidos_ver.php?id=1', 1, '2025-12-12 12:09:46'),
(6, 1, NULL, 'Proceso finalizado.', 'pedidos_ver.php?id=1', 1, '2025-12-12 12:10:17'),
(7, NULL, 3, 'Nueva solicitud Suministros: Laboratorio', 'pedidos_ver.php?id=2', 0, '2025-12-12 13:14:47'),
(8, NULL, 5, 'Nueva solicitud aprobada por Logística (ID #2). Requiere recepción.', 'pedidos_ver.php?id=2', 1, '2025-12-12 13:16:15'),
(9, 8, NULL, '✅ Tu solicitud fue aprobada, pero está en espera de que el depósito prepare los suministros.', 'pedidos_ver.php?id=2', 1, '2025-12-12 13:16:54'),
(10, 8, NULL, '📦 ¡Tu pedido está listo! Ya puedes pasar a retirar tus suministros.', 'pedidos_ver.php?id=2', 1, '2025-12-12 13:17:50'),
(11, 8, NULL, 'Proceso finalizado.', 'pedidos_ver.php?id=2', 1, '2025-12-12 13:18:56'),
(12, NULL, 1, '📢 Nueva Campaña: AÑO 2025 DICIEMRBE', 'pedidos_solicitud_interna_suministros.php', 1, '2025-12-12 13:24:36'),
(13, NULL, 4, 'Nueva Solicitud Insumos: Laboratorio', 'pedidos_ver.php?id=3', 1, '2025-12-12 13:45:28'),
(14, NULL, 7, 'Solicitud Insumos revisada por Encargado (ID #3). Requiere su aprobación.', 'pedidos_ver.php?id=3', 1, '2025-12-12 13:46:52'),
(15, 8, NULL, '✅ Tu pedido fue aprobado por el Director Médico. Está en espera de preparación.', 'pedidos_ver.php?id=3', 0, '2025-12-12 13:47:07'),
(16, NULL, 4, 'Director Médico aprobó pedido #3. Proceder con el movimiento.', 'pedidos_ver.php?id=3', 1, '2025-12-12 13:47:07'),
(17, 8, NULL, '📦 TUS INSUMOS ESTÁN LISTOS. Por favor pasa por Depósito a retirar y confirmar.', 'pedidos_ver.php?id=3', 1, '2025-12-12 13:47:25'),
(18, 8, NULL, 'Proceso finalizado.', 'pedidos_ver.php?id=3', 0, '2025-12-12 13:47:43'),
(19, NULL, 1, '📢 Nueva Campaña Médica: 20225', 'pedidos_solicitud_interna.php', 1, '2025-12-12 13:50:17'),
(20, NULL, 4, 'Nueva Solicitud Insumos: Root (Campaña)', 'pedidos_ver.php?id=4', 0, '2025-12-12 15:07:15'),
(21, NULL, 7, 'Planificación requiere aprobación: 20225', 'suministros_planificacion_detalle.php?id=2', 0, '2025-12-12 15:07:49'),
(22, NULL, 2, 'Planificación aprobada para comprar: 20225', 'suministros_planificacion_panel.php', 1, '2025-12-12 15:07:52'),
(23, NULL, 7, 'Planificación requiere aprobación: AÑO 2025 DICIEMRBE', 'suministros_planificacion_detalle.php?id=1', 0, '2025-12-12 15:12:43'),
(24, NULL, 2, 'Planificación aprobada para comprar: AÑO 2025 DICIEMRBE', 'suministros_planificacion_panel.php', 1, '2025-12-12 15:12:46'),
(25, NULL, 4, 'Nueva Solicitud Insumos: Laboratorio', 'pedidos_ver.php?id=5', 1, '2025-12-12 15:21:26'),
(26, NULL, 7, 'Solicitud Insumos revisada por Encargado (ID #5). Requiere su aprobación.', 'pedidos_ver.php?id=5', 1, '2025-12-12 15:22:04'),
(27, 8, NULL, '✅ Tu pedido fue aprobado por el Director Médico. Está en espera de preparación.', 'pedidos_ver.php?id=5', 1, '2025-12-12 15:23:46'),
(28, NULL, 4, 'Director Médico aprobó pedido #5. Proceder con el movimiento.', 'pedidos_ver.php?id=5', 1, '2025-12-12 15:23:46'),
(29, 8, NULL, '📦 TUS INSUMOS ESTÁN LISTOS. Por favor pasa por Depósito a retirar y confirmar.', 'pedidos_ver.php?id=5', 1, '2025-12-12 15:25:06'),
(30, 8, NULL, 'Proceso finalizado.', 'pedidos_ver.php?id=5', 0, '2025-12-12 15:25:28'),
(31, NULL, 1, '📢 Nueva Campaña Médica: FEDE2025', 'pedidos_solicitud_interna.php', 1, '2025-12-12 15:28:28'),
(32, NULL, 4, 'Nueva Solicitud Insumos: Laboratorio (Campaña)', 'pedidos_ver.php?id=6', 1, '2025-12-12 15:29:48'),
(33, NULL, 4, 'Nueva Solicitud Insumos: Odontologia (Campaña)', 'pedidos_ver.php?id=7', 1, '2025-12-12 15:39:25'),
(34, NULL, 7, 'Campaña Médica requiere aprobación: FEDE2025', 'insumos_planificacion_detalle.php?id=3', 0, '2025-12-12 15:40:13'),
(35, NULL, 2, 'Campaña Médica aprobada: FEDE2025', 'insumos_gestion_compras.php?id=3', 1, '2025-12-12 15:41:01'),
(36, NULL, 4, 'OC Generada (Campaña: FEDE2025)', 'insumos_recepcion.php?id=6', 1, '2025-12-12 15:58:19'),
(37, NULL, 4, 'OC Médica Generada (Campaña: 20225)', 'insumos_recepcion.php?id=7', 0, '2025-12-12 16:05:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_compra`
--

CREATE TABLE `ordenes_compra` (
  `id` int(11) NOT NULL,
  `numero_oc` varchar(50) NOT NULL,
  `servicio_destino` varchar(100) DEFAULT NULL,
  `tipo_origen` enum('insumos','suministros') NOT NULL,
  `id_usuario_creador` int(11) NOT NULL,
  `estado` enum('PENDIENTE','APROBADO','PARCIAL','COMPLETADO') DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_aprobacion` datetime DEFAULT NULL,
  `id_usuario_aprobador` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `id_planificacion_origen` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ordenes_compra`
--

INSERT INTO `ordenes_compra` (`id`, `numero_oc`, `servicio_destino`, `tipo_origen`, `id_usuario_creador`, `estado`, `fecha_creacion`, `fecha_aprobacion`, `id_usuario_aprobador`, `observaciones`, `id_planificacion_origen`) VALUES
(6, '154343', 'Depósito Central', 'insumos', 3, '', '2025-12-12 15:58:19', NULL, NULL, 'Compra masiva campaña #3', 3),
(7, '3243354354', 'Depósito Central', 'insumos', 3, '', '2025-12-12 16:05:05', NULL, NULL, 'Compra masiva campaña #2', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_compra_items`
--

CREATE TABLE `ordenes_compra_items` (
  `id` int(11) NOT NULL,
  `id_oc` int(11) NOT NULL,
  `descripcion_producto` varchar(200) NOT NULL,
  `cantidad_solicitada` int(11) NOT NULL,
  `cantidad_recibida` int(11) DEFAULT 0,
  `precio_unitario` decimal(10,2) DEFAULT 0.00,
  `id_insumo_asociado` int(11) DEFAULT NULL,
  `id_suministro_asociado` int(11) DEFAULT NULL,
  `cantidad_aprobada_compra` int(11) DEFAULT NULL,
  `precio_real_unitario` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ordenes_compra_items`
--

INSERT INTO `ordenes_compra_items` (`id`, `id_oc`, `descripcion_producto`, `cantidad_solicitada`, `cantidad_recibida`, `precio_unitario`, `id_insumo_asociado`, `id_suministro_asociado`, `cantidad_aprobada_compra`, `precio_real_unitario`) VALUES
(12, 6, 'ALCOHOL GEL  [Laboratorio]', 500, 0, 500.00, NULL, NULL, 500, 0.00),
(13, 6, 'Agua Oxigenada 10vol [Laboratorio]', 800, 0, 1000.00, 5, NULL, 800, 0.00),
(14, 6, 'Ibuprofeno 600mg [Laboratorio]', 500, 0, 2000.00, 2, NULL, 400, 0.00),
(15, 6, 'Paracetamol 500mg [Odontologia]', 1000, 0, 1000.00, 1, NULL, 1000, 0.00),
(16, 7, 'Ibuprofeno 600mg [Root]', 1, 0, 500.00, 2, NULL, 1, 0.00),
(17, 7, 'Ibuprofeno 600mg [Root]', 1, 0, 150.00, 2, NULL, 2, 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos_items`
--

CREATE TABLE `pedidos_items` (
  `id` int(11) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `id_insumo` int(11) DEFAULT NULL,
  `id_suministro` int(11) DEFAULT NULL,
  `cantidad_solicitada` int(11) NOT NULL,
  `cantidad_aprobada` int(11) DEFAULT NULL,
  `cantidad_entregada` int(11) DEFAULT 0,
  `cantidad_recibida` int(11) DEFAULT 0,
  `detalle_personalizado` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `pedidos_items`
--

INSERT INTO `pedidos_items` (`id`, `id_pedido`, `id_insumo`, `id_suministro`, `cantidad_solicitada`, `cantidad_aprobada`, `cantidad_entregada`, `cantidad_recibida`, `detalle_personalizado`) VALUES
(1, 1, 4, NULL, 1, 1, 1, 0, NULL),
(2, 2, NULL, 1, 50, 30, 30, 0, NULL),
(3, 3, 3, NULL, 20, 20, 20, 0, NULL),
(4, 4, 2, NULL, 1, NULL, 0, 0, NULL),
(5, 4, 2, NULL, 1, NULL, 0, 0, NULL),
(6, 5, 3, NULL, 5, 5, 5, 0, NULL),
(7, 6, 2, NULL, 500, NULL, 0, 0, NULL),
(8, 6, 5, NULL, 800, NULL, 0, 0, NULL),
(9, 6, NULL, NULL, 500, NULL, 0, 0, 'ALCOHOL GEL '),
(10, 7, 1, NULL, 1000, NULL, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos_servicio`
--

CREATE TABLE `pedidos_servicio` (
  `id` int(11) NOT NULL,
  `tipo_insumo` enum('insumos_medicos','suministros') NOT NULL,
  `id_usuario_solicitante` int(11) NOT NULL,
  `servicio_solicitante` varchar(100) NOT NULL,
  `prioridad` enum('Normal','Urgente','Extraordinaria') DEFAULT NULL,
  `frecuencia_compra` enum('Mensual','Trimestral','Semestral','Anual') DEFAULT NULL,
  `fecha_solicitud` timestamp NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente_director','aprobado_director','pendiente_logistica','aprobado_logistica','entregado','rechazado','finalizado_proceso','esperando_entrega') DEFAULT 'pendiente_director',
  `fecha_aprobacion_director` datetime DEFAULT NULL,
  `fecha_aprobacion_logistica` datetime DEFAULT NULL,
  `id_director_aprobador` int(11) DEFAULT NULL,
  `id_logistica_aprobador` int(11) DEFAULT NULL,
  `fecha_entrega_real` datetime DEFAULT NULL,
  `id_usuario_entrega` int(11) DEFAULT NULL,
  `observaciones_director` text DEFAULT NULL,
  `observaciones_logistica` text DEFAULT NULL,
  `observaciones_entrega` text DEFAULT NULL,
  `paso_actual_id` int(11) DEFAULT NULL,
  `proceso_origen` varchar(50) DEFAULT 'movimiento_insumos',
  `id_entrega_generada` int(11) DEFAULT NULL,
  `id_planificacion` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `pedidos_servicio`
--

INSERT INTO `pedidos_servicio` (`id`, `tipo_insumo`, `id_usuario_solicitante`, `servicio_solicitante`, `prioridad`, `frecuencia_compra`, `fecha_solicitud`, `estado`, `fecha_aprobacion_director`, `fecha_aprobacion_logistica`, `id_director_aprobador`, `id_logistica_aprobador`, `fecha_entrega_real`, `id_usuario_entrega`, `observaciones_director`, `observaciones_logistica`, `observaciones_entrega`, `paso_actual_id`, `proceso_origen`, `id_entrega_generada`, `id_planificacion`) VALUES
(1, 'insumos_medicos', 1, 'Sin Servicio', NULL, NULL, '2025-12-12 11:39:10', 'finalizado_proceso', '2025-12-12 09:08:27', NULL, 6, NULL, '2025-12-12 09:10:17', 7, '', NULL, NULL, NULL, 'movimiento_insumos', 1, NULL),
(2, 'suministros', 8, 'Laboratorio', NULL, NULL, '2025-12-12 13:14:47', 'finalizado_proceso', NULL, '2025-12-12 10:16:15', NULL, 2, '2025-12-12 10:18:56', 4, NULL, '', NULL, NULL, 'movimiento_suministros', 2, NULL),
(3, 'insumos_medicos', 8, 'Laboratorio', NULL, NULL, '2025-12-12 13:45:28', 'finalizado_proceso', '2025-12-12 10:47:07', NULL, 6, NULL, '2025-12-12 10:47:43', 7, '', NULL, NULL, NULL, 'movimiento_insumos', 3, NULL),
(4, 'insumos_medicos', 1, 'Root', NULL, NULL, '2025-12-12 15:07:15', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 46, 'movimiento_insumos', NULL, 2),
(5, 'insumos_medicos', 8, 'Laboratorio', NULL, NULL, '2025-12-12 15:21:26', 'finalizado_proceso', '2025-12-12 12:23:46', NULL, 6, NULL, '2025-12-12 12:25:28', 7, 'SIN NOVEDAD', NULL, NULL, NULL, 'movimiento_insumos', 4, NULL),
(6, 'insumos_medicos', 8, 'Laboratorio', NULL, NULL, '2025-12-12 15:29:48', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 46, 'movimiento_insumos', NULL, 3),
(7, 'insumos_medicos', 10, 'Odontologia', NULL, NULL, '2025-12-12 15:39:25', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 46, 'movimiento_insumos', NULL, 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id` int(11) NOT NULL,
  `clave` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `categoria` varchar(50) DEFAULT 'General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id`, `clave`, `nombre`, `categoria`) VALUES
(1, 'acceso_admin', 'Acceso Total al Sistema (Super Admin)', '1. Sistema'),
(2, 'ver_dashboard', 'Ver Panel de Control (Dashboard)', '1. Sistema'),
(3, 'ver_notificaciones', 'Ver y Recibir Notificaciones', '1. Sistema'),
(4, 'solicitar_insumos', 'Solicitar Insumos Médicos (Crear Pedido)', '2. Servicios - Acciones'),
(5, 'solicitar_suministros', 'Solicitar Suministros Grales (Crear Pedido)', '2. Servicios - Acciones'),
(6, 'confirmar_recepcion', 'Confirmar Recepción de Pedidos (Cerrar Circuito)', '2. Servicios - Acciones'),
(7, 'ver_mis_pedidos', 'Ver Mis Pedidos Solicitados', '2. Servicios - Vistas'),
(8, 'aprobar_suministros_logistica', 'Aprobar Solicitud Suministros (Paso 1: Logística)', '3. Flujo Suministros'),
(9, 'recibir_orden_suministros', 'Recibir Orden Aprobada (Paso 2: Depósito da OK)', '3. Flujo Suministros'),
(10, 'realizar_entrega_suministros', 'Realizar Entrega Física Suministros (Paso 3: Depósito)', '3. Flujo Suministros'),
(11, 'ver_todos_pedidos_suministros', 'Ver Todos los Pedidos de Suministros (Historial)', '3. Flujo Suministros'),
(12, 'aprobar_insumos_encargado', 'Revisión Inicial Insumos (Paso 1: Encargado)', '4. Flujo Insumos Médicos'),
(13, 'aprobar_insumos_director', 'Autorización Final (Paso 2: Director Médico)', '4. Flujo Insumos Médicos'),
(14, 'realizar_entrega_insumos', 'Realizar Entrega Física Insumos (Paso 3: Encargado)', '4. Flujo Insumos Médicos'),
(15, 'ver_todos_pedidos_insumos', 'Ver Todos los Pedidos de Insumos (Historial)', '4. Flujo Insumos Médicos'),
(16, 'gestion_stock_insumos', 'Gestionar Stock Insumos (Altas/Bajas/Editar)', '5. Gestión Stock'),
(17, 'gestion_stock_suministros', 'Gestionar Stock Suministros (Altas/Bajas/Editar)', '5. Gestión Stock'),
(18, 'ver_reportes_stock', 'Ver Reportes y Auditoría de Stock', '5. Gestión Stock'),
(19, 'gestion_compras_insumos', 'Gestión Compras Insumos (Subir OC)', '6. Compras'),
(20, 'gestion_compras_suministros', 'Gestión Compras Suministros (Subir OC)', '6. Compras'),
(35, 'ver_stock_insumos', 'Ver Stock Insumos (Solo Lectura)', '5. Gestión Stock'),
(36, 'ver_stock_suministros', 'Ver Stock Suministros (Solo Lectura)', '5. Gestión Stock'),
(37, 'ver_entregas_insumos', 'Ver Historial Entregas Insumos', '5. Gestión Stock'),
(38, 'ver_entregas_suministros', 'Ver Historial Entregas Suministros', '5. Gestión Stock'),
(39, 'ver_menu_configuracion', 'Ver Menú Configuración en Navbar', '1. Sistema'),
(40, 'gestionar_roles', 'Acceso a ABM de Roles y Permisos', '1. Sistema'),
(41, 'gestionar_usuarios', 'Acceso a ABM de Usuarios', '1. Sistema'),
(42, 'gestionar_areas', 'Acceso a ABM de Áreas y Servicios', '1. Sistema'),
(43, 'configurar_flujos', 'Configurar Flujos de Trabajo', '1. Sistema'),
(44, 'configurar_sistema', 'Herramientas de Sistema (Mantenimiento)', '1. Sistema'),
(45, 'ver_auditoria', 'Ver Logs de Auditoría', '1. Sistema'),
(46, 'aprobar_oc_insumos', 'Aprobar/Rechazar OC Insumos (Director)', '6. Compras'),
(47, 'aprobar_oc_suministros', 'Aprobar/Rechazar OC Suministros (Logística)', '6. Compras'),
(48, 'recibir_oc_insumos', 'Ingresar Mercadería OC Insumos (Depósito)', '6. Compras'),
(49, 'recibir_oc_suministros', 'Ingresar Mercadería OC Suministros (Depósito)', '6. Compras'),
(50, 'realizar_entrega_manual_insumos', 'Registrar Salida Manual Insumos', '5. Gestión Stock'),
(51, 'realizar_entrega_manual_suministros', 'Registrar Salida Manual Suministros', '5. Gestión Stock'),
(52, 'gestionar_planificaciones', 'Crear y Consolidar Planificaciones (Logística)', '6. Compras'),
(53, 'aprobar_planificacion_director', 'Aprobar Planificación Global (Director)', '6. Compras'),
(54, 'procesar_compra_precios', 'Procesar Compra y Precios (Compras)', '6. Compras'),
(55, 'ver_oc_insumos_todas', 'Ver Todas las OC Insumos', '6. Compras'),
(56, 'ver_oc_suministros_todas', 'Ver Todas las OC Suministros', '6. Compras'),
(57, 'gestionar_planificaciones_medicas', 'Crear Campañas Insumos (Enc. Insumos)', '6. Compras');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Administrador', 'Control total del sistema y gestión de permisos'),
(2, 'Compras', 'Generación de Órdenes de Compra'),
(3, 'Encargado Logística', 'Aprobación de OC y supervisión'),
(4, 'Encargado Depósito Insumos', 'Recepción y gestión de Insumos Médicos'),
(5, 'Encargado Depósito Suministros', 'Recepción y gestión de Suministros Generales'),
(6, 'Auxiliar', 'Ayuda en gestión y entregas'),
(7, 'Director Médico', 'Autoriza Órdenes de Compra de Insumos Médicos'),
(8, 'Director Operativo', 'Aprueba adquisiciones de Suministros Generales'),
(12, 'Servicio', 'Usuario solicitante de insumos/suministros');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permisos`
--

CREATE TABLE `rol_permisos` (
  `id_rol` int(11) NOT NULL,
  `id_permiso` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol_permisos`
--

INSERT INTO `rol_permisos` (`id_rol`, `id_permiso`) VALUES
(1, 1),
(12, 1),
(1, 2),
(2, 2),
(3, 2),
(4, 2),
(5, 2),
(7, 2),
(12, 2),
(1, 3),
(2, 3),
(3, 3),
(4, 3),
(5, 3),
(7, 3),
(12, 3),
(1, 4),
(12, 4),
(1, 5),
(12, 5),
(1, 6),
(12, 6),
(1, 7),
(4, 7),
(5, 7),
(12, 7),
(1, 8),
(3, 8),
(1, 9),
(5, 9),
(1, 10),
(5, 10),
(1, 11),
(2, 11),
(3, 11),
(5, 11),
(1, 12),
(4, 12),
(1, 13),
(7, 13),
(1, 14),
(4, 14),
(1, 15),
(2, 15),
(4, 15),
(7, 15),
(1, 16),
(4, 16),
(1, 17),
(5, 17),
(1, 18),
(1, 19),
(2, 19),
(1, 20),
(2, 20),
(3, 20),
(1, 35),
(4, 35),
(7, 35),
(1, 36),
(3, 36),
(5, 36),
(1, 37),
(4, 37),
(7, 37),
(1, 38),
(3, 38),
(5, 38),
(1, 39),
(1, 40),
(1, 41),
(1, 42),
(1, 43),
(1, 44),
(1, 45),
(7, 46),
(3, 47),
(4, 48),
(5, 49),
(4, 50),
(5, 51),
(3, 52),
(7, 53),
(2, 54),
(2, 55),
(2, 56),
(3, 56),
(4, 57);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `suministros_generales`
--

CREATE TABLE `suministros_generales` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `unidad_medida` varchar(50) DEFAULT 'unidades',
  `stock_actual` int(11) DEFAULT 0,
  `stock_minimo` int(11) DEFAULT 5,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `suministros_generales`
--

INSERT INTO `suministros_generales` (`id`, `codigo`, `nombre`, `descripcion`, `unidad_medida`, `stock_actual`, `stock_minimo`, `updated_at`) VALUES
(1, 'OF-001', 'Resma A4 75g', 'Papel para impresora, marca líder', 'paquetes', 170, 10, '2025-12-12 13:18:56'),
(2, 'LIM-001', 'Lavandina Concentrada', 'Bidón de 5 Litros', 'litros', 952, 2, '2025-12-12 11:10:44'),
(3, 'OF-002', 'Bolígrafos Azules', 'Caja x 50 unidades', 'cajas', 14, 1, '2025-12-12 11:10:46'),
(4, 'LIM-002', 'Detergente Industrial', 'Desengrasante potente', 'litros', 4, 5, '2025-12-12 11:10:55'),
(5, 'OF-003', 'Tóner HP 85A', 'Cartucho original negro', 'unidades', 85, 1, '2025-12-12 11:11:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre_completo` varchar(100) NOT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `destino` varchar(100) DEFAULT NULL,
  `servicio` varchar(100) DEFAULT NULL,
  `grado_militar` varchar(100) DEFAULT NULL,
  `rol_en_servicio` varchar(50) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `numero_interno` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `firma_digital` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `validado_por_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre_completo`, `usuario`, `email`, `destino`, `servicio`, `grado_militar`, `rol_en_servicio`, `telefono`, `numero_interno`, `password`, `firma_digital`, `activo`, `validado_por_admin`, `created_at`) VALUES
(1, 'Super Admin', 'admin', 'admin@actis.com', NULL, 'Root', NULL, 'Responsable', NULL, NULL, '$2y$10$LNsOLB5Hzq7sZ.iqD1H0IOHfDeqzTYCG.iPr9k4vNchmKC4fVVVTO', 'uploads/firmas/firma_1_1764967110.png', 1, 1, '2025-12-05 12:01:01'),
(2, 'SM I Marcelo Cañete', 'logistica', 'logistica@actis.com', NULL, 'Encargado de Logística', NULL, 'Responsable', NULL, NULL, '$2y$10$ANIfLPybyGDI5eOu7vqqgugKjvDBjhyADiH0.dtj1Kb7PDgSyhOT2', 'uploads/firmas/firma_2_1765545788.png', 1, 1, '2025-12-05 14:11:36'),
(3, 'COMPRAS', 'compras', 'compras@actis.com', NULL, 'Compras', NULL, 'Responsable', NULL, NULL, '$2y$10$1w7s2/TX3Ua5SsZiiQguXu47STNEDHtZbz/iIRolfR9pSR6Bz7Wwi', 'uploads/firmas/firma_user_3_1764946491.png', 1, 1, '2025-12-05 14:12:43'),
(4, 'SUMINISTROS', 'suministros', 'suministros@actis.com', NULL, 'Encargado Depósito Suministros', NULL, 'Responsable', NULL, NULL, '$2y$10$zB0EZqRNnGNTMdzAv75EFuzwa0T8qOGLGgtwLVCTTA/I8AmezCQS.', 'uploads/firmas/firma_4_1765499664.png', 1, 1, '2025-12-05 15:26:00'),
(6, 'DIRECTOR MEDICO', 'dirmed', 'dirmed@actis.com', NULL, 'Director Médico', NULL, 'Responsable', NULL, NULL, '$2y$10$8lCcLiScXrcYKL2aB7su6e0wA.fWfqi/fMijxYUmO1Rv7EgcSnwBe', 'uploads/firmas/firma_6_1765541171.png', 1, 1, '2025-12-05 16:35:22'),
(7, 'INSUMOS MEDICOS', 'insumos', 'insumos@actis.com', NULL, 'Encargada Insumos Médicos', NULL, 'Responsable', NULL, NULL, '$2y$10$2w.vlljgTvuUWHFm23Cdc.oj.dPa9Zoi7YTp1jaMGdcI5W2vrtMBm', 'uploads/firmas/firma_7_1765502079.png', 1, 1, '2025-12-05 16:36:41'),
(8, 'Jefe de Laboratorio', 'laboratorio', 'labo@gmail.com', 'ACTIS', 'Laboratorio', 'SG', 'Responsable', '1166116861', '', '$2y$10$8XxViGn9eT4m.dzKImzvVupWK6rAY4TBke1KU5oPK8ggP1hKuM8Ym', 'uploads/firmas/firma_8_1765500434.png', 1, 1, '2025-12-10 14:55:57'),
(9, 'DIRECTOR OPERATIVO', 'dirop', 'dirop@actis.com', NULL, '', NULL, 'Responsable', NULL, NULL, '$2y$10$ccOL7yA2BBC2K6l8T1IFRe9PZSy9YJ0h7PVZvkn0nLtNRVzhmRpt2', NULL, 1, 1, '2025-12-11 14:10:20'),
(10, 'Jefe de Odontología', 'odonto', 'odonto@actis.com', NULL, 'Odontologia', NULL, 'Responsable', NULL, NULL, '$2y$10$X961.LwyVHJkCiUmW5Q5pefxbxMt6ygKHhFI953t8FpXIq62EOVcC', NULL, 1, 1, '2025-12-11 15:54:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_roles`
--

CREATE TABLE `usuario_roles` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario_roles`
--

INSERT INTO `usuario_roles` (`id_usuario`, `id_rol`) VALUES
(1, 1),
(3, 2),
(2, 3),
(7, 4),
(4, 5),
(6, 7),
(8, 12),
(9, 12),
(10, 12);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `adjuntos`
--
ALTER TABLE `adjuntos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `areas_servicios`
--
ALTER TABLE `areas_servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_padre` (`id_padre`);

--
-- Indices de la tabla `compras_planificaciones`
--
ALTER TABLE `compras_planificaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `config_flujos`
--
ALTER TABLE `config_flujos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `config_procesos`
--
ALTER TABLE `config_procesos`
  ADD PRIMARY KEY (`codigo`);

--
-- Indices de la tabla `entregas`
--
ALTER TABLE `entregas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario_responsable` (`id_usuario_responsable`);

--
-- Indices de la tabla `entregas_items`
--
ALTER TABLE `entregas_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_entrega` (`id_entrega`),
  ADD KEY `id_insumo` (`id_insumo`),
  ADD KEY `id_suministro` (`id_suministro`);

--
-- Indices de la tabla `historial_ajustes`
--
ALTER TABLE `historial_ajustes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `insumos_medicos`
--
ALTER TABLE `insumos_medicos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario_destino` (`id_usuario_destino`),
  ADD KEY `id_rol_destino` (`id_rol_destino`);

--
-- Indices de la tabla `ordenes_compra`
--
ALTER TABLE `ordenes_compra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario_creador` (`id_usuario_creador`),
  ADD KEY `id_usuario_aprobador` (`id_usuario_aprobador`);

--
-- Indices de la tabla `ordenes_compra_items`
--
ALTER TABLE `ordenes_compra_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_oc` (`id_oc`),
  ADD KEY `id_insumo_asociado` (`id_insumo_asociado`),
  ADD KEY `id_suministro_asociado` (`id_suministro_asociado`);

--
-- Indices de la tabla `pedidos_items`
--
ALTER TABLE `pedidos_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pedido` (`id_pedido`);

--
-- Indices de la tabla `pedidos_servicio`
--
ALTER TABLE `pedidos_servicio`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario_solicitante` (`id_usuario_solicitante`),
  ADD KEY `id_planificacion` (`id_planificacion`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD PRIMARY KEY (`id_rol`,`id_permiso`),
  ADD KEY `id_permiso` (`id_permiso`);

--
-- Indices de la tabla `suministros_generales`
--
ALTER TABLE `suministros_generales`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `usuario_roles`
--
ALTER TABLE `usuario_roles`
  ADD PRIMARY KEY (`id_usuario`,`id_rol`),
  ADD KEY `id_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `adjuntos`
--
ALTER TABLE `adjuntos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `areas_servicios`
--
ALTER TABLE `areas_servicios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `compras_planificaciones`
--
ALTER TABLE `compras_planificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `config_flujos`
--
ALTER TABLE `config_flujos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT de la tabla `entregas`
--
ALTER TABLE `entregas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `entregas_items`
--
ALTER TABLE `entregas_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `historial_ajustes`
--
ALTER TABLE `historial_ajustes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `insumos_medicos`
--
ALTER TABLE `insumos_medicos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `ordenes_compra`
--
ALTER TABLE `ordenes_compra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `ordenes_compra_items`
--
ALTER TABLE `ordenes_compra_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `pedidos_items`
--
ALTER TABLE `pedidos_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `pedidos_servicio`
--
ALTER TABLE `pedidos_servicio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `suministros_generales`
--
ALTER TABLE `suministros_generales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `areas_servicios`
--
ALTER TABLE `areas_servicios`
  ADD CONSTRAINT `areas_servicios_ibfk_1` FOREIGN KEY (`id_padre`) REFERENCES `areas_servicios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `entregas`
--
ALTER TABLE `entregas`
  ADD CONSTRAINT `entregas_ibfk_1` FOREIGN KEY (`id_usuario_responsable`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `entregas_items`
--
ALTER TABLE `entregas_items`
  ADD CONSTRAINT `entregas_items_ibfk_1` FOREIGN KEY (`id_entrega`) REFERENCES `entregas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entregas_items_ibfk_2` FOREIGN KEY (`id_insumo`) REFERENCES `insumos_medicos` (`id`),
  ADD CONSTRAINT `entregas_items_ibfk_3` FOREIGN KEY (`id_suministro`) REFERENCES `suministros_generales` (`id`);

--
-- Filtros para la tabla `historial_ajustes`
--
ALTER TABLE `historial_ajustes`
  ADD CONSTRAINT `historial_ajustes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notificaciones_ibfk_2` FOREIGN KEY (`id_rol_destino`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ordenes_compra`
--
ALTER TABLE `ordenes_compra`
  ADD CONSTRAINT `ordenes_compra_ibfk_1` FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `ordenes_compra_ibfk_2` FOREIGN KEY (`id_usuario_aprobador`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `ordenes_compra_items`
--
ALTER TABLE `ordenes_compra_items`
  ADD CONSTRAINT `ordenes_compra_items_ibfk_1` FOREIGN KEY (`id_oc`) REFERENCES `ordenes_compra` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ordenes_compra_items_ibfk_2` FOREIGN KEY (`id_insumo_asociado`) REFERENCES `insumos_medicos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ordenes_compra_items_ibfk_3` FOREIGN KEY (`id_suministro_asociado`) REFERENCES `suministros_generales` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD CONSTRAINT `rol_permisos_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rol_permisos_ibfk_2` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuario_roles`
--
ALTER TABLE `usuario_roles`
  ADD CONSTRAINT `usuario_roles_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_roles_ibfk_2` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
