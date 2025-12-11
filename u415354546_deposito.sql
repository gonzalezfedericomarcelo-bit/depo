-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-12-2025 a las 23:47:05
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
(2, 'pedido_servicio', 3, 'uploads/ordenes_compra/693ad075b35e3_1765368288_MODELO_DE_SOLICITUD_ELEMENTOS.pdf', '1765368288_MODELO_DE_SOLICITUD_ELEMENTOS.pdf', '2025-12-11 14:08:53'),
(3, 'pedido_servicio', 5, 'uploads/ordenes_compra/693aee1fcfd66_MODELO DE SOLICITUD ELEMENTOS.pdf', 'MODELO DE SOLICITUD ELEMENTOS.pdf', '2025-12-11 16:15:27');

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
(38, 'movimiento_insumos', 1, 'revision_encargado', 'Revisión Encargado', 4, 0),
(39, 'movimiento_insumos', 2, 'revision_director', 'Autorización Director', 7, 0),
(40, 'movimiento_insumos', 3, 'en_preparacion', 'En Preparación', 4, 0),
(41, 'movimiento_insumos', 4, 'listo_para_retirar', 'Listo para Retirar', 0, 0),
(42, 'movimiento_suministros', 1, 'revision_logistica', 'Revisión Logística', 0, 0),
(43, 'movimiento_suministros', 2, 'pendiente_deposito', 'Pendiente Recepción en Depósito', 5, 0),
(44, 'movimiento_suministros', 3, 'en_preparacion', 'En Preparación (Depósito)', 5, 0),
(45, 'movimiento_suministros', 4, 'listo_para_retirar', 'Listo para Retirar', 0, 0);

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
(1, 'insumos', 1, 'sffdgfd', 'Quirófano', 'data:image/png;base64,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', '2025-12-10 12:24:04'),
(2, 'insumos', 1, 'Jefe de Ginecologia', 'ACTIS - Ginecología', 'data:image/png;base64,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', '2025-12-11 20:58:41'),
(3, 'suministros', 1, 'Juan Perez', 'ACTIS - Psicología', 'data:image/png;base64,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', '2025-12-11 21:32:02');

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
(1, 1, 5, NULL, 20),
(2, 2, 2, NULL, 10),
(3, 2, 3, NULL, 2),
(4, 3, NULL, 3, 1),
(5, 3, NULL, 1, 20);

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
(1, 4, 'suministro', 1, 55, 50, '2025-12-05 17:54:36'),
(2, 1, 'insumo', 5, 5, 2, '2025-12-10 12:25:58'),
(3, 7, 'insumo', 1, 0, 398, '2025-12-11 16:51:39'),
(4, 7, 'insumo', 5, 0, 0, '2025-12-11 16:51:39');

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
(1, 'MED-001', 'Paracetamol 500mg', 'Analgésico y antipirético', 'cajas', 548, 20, '2026-12-01', 'LOT-9988', '2025-12-11 16:51:39'),
(2, 'MED-002', 'Ibuprofeno 600mg', 'Antiinflamatorio no esteroideo', 'cajas', 70, 15, '2025-08-15', 'LOT-1122', '2025-12-11 20:58:41'),
(3, 'MED-003', 'Gasas Estériles 10x10', 'Sobres individuales', 'unidades', 493, 100, '2027-01-01', 'GAS-001', '2025-12-11 20:58:41'),
(4, 'MED-004', 'Jeringas 5ml', 'Sin aguja, descartables', 'unidades', 300, 50, '2028-05-20', 'JER-555', '2025-12-05 16:49:41'),
(5, 'MED-005', 'Agua Oxigenada 10vol', 'Frasco 250ml', 'litros', 2, 5, '2025-11-30', 'OXI-22', '2025-12-10 12:25:58');

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
(1, NULL, 7, 'Nueva OC Médica #PRUEBA requiere su aprobación.', 'insumos_oc_ver.php?id=11', 1, '2025-12-05 17:13:47'),
(2, 3, NULL, '❌ Director Médico RECHAZÓ la OC #PRUEBA.', 'insumos_oc_ver.php?id=11', 1, '2025-12-05 17:15:05'),
(3, NULL, 7, 'Nueva OC Médica #prueba requiere su aprobación.', 'insumos_oc_ver.php?id=12', 1, '2025-12-05 17:16:06'),
(4, 3, NULL, '❌ Director Médico RECHAZÓ la OC #prueba.', 'insumos_oc_ver.php?id=12', 1, '2025-12-05 17:16:51'),
(5, 3, NULL, '❌ Director Médico RECHAZÓ la OC #prueba.', 'insumos_oc_ver.php?id=12', 0, '2025-12-05 17:21:35'),
(6, NULL, 3, 'Nueva OC Suministros #25/25 pendiente de revisión.', 'suministros_oc_ver.php?id=13', 0, '2025-12-05 17:39:14'),
(7, 3, NULL, '✅ Logística APROBÓ la OC Suministros #25/25.', 'suministros_oc_ver.php?id=13', 0, '2025-12-05 17:39:37'),
(8, NULL, 5, 'Logística autorizó carga. OC #25/25', 'suministros_recepcion.php?id=13', 0, '2025-12-05 17:39:37'),
(9, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:20:35'),
(10, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:05'),
(11, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:06'),
(12, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:06'),
(13, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:06'),
(14, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:06'),
(15, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:06'),
(16, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:07'),
(17, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:07'),
(18, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:07'),
(19, 6, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:07'),
(20, 4, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:18'),
(21, 7, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:20'),
(22, 2, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:22'),
(23, 3, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:51:23'),
(24, NULL, 1, 'Nuevo registro: FEDERICO GONZALEZ (Laboratorio). Requiere validación.', 'admin_usuarios.php', 0, '2025-12-10 14:55:57'),
(25, 8, NULL, '¡Tu cuenta ha sido aprobada! Ya puedes ingresar.', 'dashboard.php', 0, '2025-12-10 14:56:16'),
(26, NULL, 7, 'Nuevo pedido de Insumos: Laboratorio', 'pedidos_revision_director.php?id=1', 0, '2025-12-10 14:57:33'),
(27, NULL, 4, 'Pedido aprobado por Director (ID #1). Listo para despachar.', 'pedidos_despacho.php?id=1', 0, '2025-12-10 14:58:03'),
(28, NULL, 4, 'Nueva Adquisición Solicitada: Laboratorio', 'bandeja_gestion_dinamica.php?id=2', 0, '2025-12-11 13:37:02'),
(29, NULL, 7, 'Solicitud #2 requiere tu revisión (Aprobación Director Médico)', 'bandeja_gestion_dinamica.php?id=2', 0, '2025-12-11 13:37:26'),
(30, NULL, 2, 'Solicitud #2 requiere tu revisión (Gestión de Compras)', 'bandeja_gestion_dinamica.php?id=2', 0, '2025-12-11 13:37:46'),
(31, NULL, 4, 'El Director aprobó el pedido #2. Vuelve a ti.', 'bandeja_gestion_dinamica.php?id=2', 0, '2025-12-11 13:37:46'),
(32, 8, NULL, '📢 OC Generada para Pedido #2. En espera de proveedor.', 'dashboard.php', 0, '2025-12-11 13:43:50'),
(33, NULL, 4, '📢 OC Generada para Pedido #2. En espera de proveedor.', NULL, 0, '2025-12-11 13:43:50'),
(34, NULL, 4, 'Nueva Adquisición Solicitada: Laboratorio', 'bandeja_gestion_dinamica.php?id=3', 0, '2025-12-11 14:08:11'),
(35, NULL, 7, 'Solicitud #3 requiere tu revisión (Aprobación Director Médico)', 'bandeja_gestion_dinamica.php?id=3', 0, '2025-12-11 14:08:35'),
(36, NULL, 2, 'Solicitud #3 requiere tu revisión (Gestión de Compras)', 'bandeja_gestion_dinamica.php?id=3', 0, '2025-12-11 14:08:45'),
(37, 8, NULL, '📢 OC Generada para Pedido #3. En espera de proveedor.', 'dashboard.php', 0, '2025-12-11 14:08:53'),
(38, NULL, 4, '📢 OC Generada para Pedido #3. En espera de proveedor.', NULL, 0, '2025-12-11 14:08:53'),
(39, NULL, 4, 'Solicitud Interna de Laboratorio', 'bandeja_gestion_dinamica.php?id=4', 0, '2025-12-11 14:11:14'),
(40, NULL, 7, 'Solicitud #4 requiere tu revisión (Autorización Director)', 'bandeja_gestion_dinamica.php?id=4', 0, '2025-12-11 14:11:34'),
(41, NULL, 4, 'Solicitud #4 requiere tu revisión (Preparación para Retiro)', 'bandeja_gestion_dinamica.php?id=4', 0, '2025-12-11 14:11:45'),
(42, NULL, 4, 'El Director aprobó el pedido #4. Vuelve a ti.', 'bandeja_gestion_dinamica.php?id=4', 0, '2025-12-11 14:11:45'),
(48, 8, NULL, 'Solicitud #4 requiere tu revisión (Confirmación de Recepción (Servicio))', 'bandeja_gestion_dinamica.php?id=4', 0, '2025-12-11 14:23:16'),
(49, NULL, 4, 'Nueva Adquisición Solicitada: Laboratorio', 'bandeja_gestion_dinamica.php?id=5', 0, '2025-12-11 16:12:28'),
(50, NULL, 7, 'Solicitud #5 requiere tu revisión (Aprobación Director Médico)', 'bandeja_gestion_dinamica.php?id=5', 0, '2025-12-11 16:13:31'),
(51, NULL, 2, 'Solicitud #5 requiere tu revisión (Gestión de Compras)', 'bandeja_gestion_dinamica.php?id=5', 0, '2025-12-11 16:14:12'),
(52, 8, NULL, '📢 OC Generada para Pedido #5. En espera de proveedor.', 'dashboard.php', 0, '2025-12-11 16:15:27'),
(53, NULL, 4, '📢 OC Generada para Pedido #5. En espera de proveedor.', NULL, 0, '2025-12-11 16:15:27'),
(54, 8, NULL, '✅ Tu pedido #5 ha ingresado al stock (Disponible para retiro).', 'historial_pedidos.php', 0, '2025-12-11 16:51:39'),
(55, NULL, 3, 'Solicitud Interna Suministros: Laboratorio', 'bandeja_gestion_dinamica.php?id=6', 0, '2025-12-11 21:48:20'),
(56, NULL, 5, 'Solicitud #6 requiere tu revisión (Preparación Entrega)', 'bandeja_gestion_dinamica.php?id=6', 0, '2025-12-11 21:51:08'),
(57, NULL, 3, 'Solicitud Interna Suministros: Laboratorio', 'bandeja_gestion_dinamica.php?id=7', 0, '2025-12-11 22:08:50'),
(58, NULL, 3, 'Solicitud Interna Suministros: Laboratorio', 'bandeja_gestion_dinamica.php?id=8', 0, '2025-12-11 22:25:42'),
(59, NULL, 3, 'Nueva solicitud Suministros: Laboratorio', 'pedidos_ver.php?id=9', 0, '2025-12-11 22:44:31'),
(60, NULL, NULL, 'Solicitud #9 requiere atención.', 'pedidos_ver.php?id=9', 0, '2025-12-11 22:44:44'),
(61, NULL, 3, 'El Encargado de Suministros recibió la orden #9.', 'pedidos_ver.php?id=9', 0, '2025-12-11 22:54:34'),
(62, 8, NULL, 'Tu pedido fue aprobado y está siendo preparado en depósito.', 'pedidos_ver.php?id=9', 0, '2025-12-11 22:54:34'),
(63, NULL, 3, 'Nueva solicitud Suministros: Laboratorio', 'pedidos_ver.php?id=10', 0, '2025-12-11 23:06:27'),
(64, NULL, NULL, 'Solicitud #10 requiere atención.', 'pedidos_ver.php?id=10', 0, '2025-12-11 23:06:47'),
(66, NULL, 3, 'Nueva solicitud Suministros: Laboratorio', 'pedidos_ver.php?id=12', 0, '2025-12-11 23:21:53'),
(67, NULL, NULL, 'Nueva solicitud aprobada por Logística (ID #12).', 'pedidos_ver.php?id=12', 0, '2025-12-11 23:22:01');

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
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ordenes_compra`
--

INSERT INTO `ordenes_compra` (`id`, `numero_oc`, `servicio_destino`, `tipo_origen`, `id_usuario_creador`, `estado`, `fecha_creacion`, `fecha_aprobacion`, `id_usuario_aprobador`, `observaciones`) VALUES
(1, 'OC-MED-1001', NULL, 'insumos', 1, '', '2025-12-05 16:49:41', '2025-12-05 16:49:41', 1, NULL),
(2, 'OC-MED-1002', NULL, 'insumos', 1, '', '2025-12-05 16:49:41', '2025-12-05 16:49:41', 1, NULL),
(3, 'OC-MED-1003', NULL, 'insumos', 1, '', '2025-12-05 16:49:41', '2025-12-05 16:49:41', 1, NULL),
(4, 'OC-MED-1004', NULL, 'insumos', 1, '', '2025-12-05 16:49:41', '2025-12-05 16:49:41', 1, NULL),
(5, 'OC-MED-1005', NULL, 'insumos', 1, '', '2025-12-05 16:49:41', '2025-12-05 16:49:41', 1, NULL),
(6, 'OC-SUM-2001', NULL, 'suministros', 1, '', '2025-12-05 16:49:41', '2025-12-05 16:49:41', 1, NULL),
(7, 'OC-SUM-2002', NULL, 'suministros', 1, '', '2025-12-05 16:49:41', '2025-12-05 16:49:41', 1, NULL),
(8, 'OC-SUM-2003', NULL, 'suministros', 1, '', '2025-12-05 16:49:41', '2025-12-05 16:49:41', 1, NULL),
(9, 'OC-SUM-2004', NULL, 'suministros', 1, '', '2025-12-05 16:49:41', '2025-12-05 16:49:41', 1, NULL),
(10, 'OC-SUM-2005', NULL, 'suministros', 1, '', '2025-12-05 16:49:41', '2025-12-05 16:49:41', 1, NULL),
(11, 'PRUEBA', NULL, 'insumos', 3, '', '2025-12-05 17:13:47', '2025-12-05 17:15:05', 6, ''),
(12, 'prueba', NULL, 'insumos', 3, '', '2025-12-05 17:16:06', '2025-12-05 17:21:35', 6, ''),
(13, '25/25', NULL, 'suministros', 3, '', '2025-12-05 17:39:14', '2025-12-05 17:39:37', 2, 'xxxx\n[RECIBIDO] Fecha: 2025-12-05 17:40 - Remito: Uwwye - Por: SUMINISTROS');

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
  `precio_estimado` decimal(10,2) DEFAULT NULL,
  `id_insumo_asociado` int(11) DEFAULT NULL,
  `id_suministro_asociado` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ordenes_compra_items`
--

INSERT INTO `ordenes_compra_items` (`id`, `id_oc`, `descripcion_producto`, `cantidad_solicitada`, `cantidad_recibida`, `precio_estimado`, `id_insumo_asociado`, `id_suministro_asociado`) VALUES
(1, 1, 'Paracetamol 500mg (Reposición)', 50, 0, 1500.00, 1, NULL),
(2, 2, 'Ibuprofeno Lote Nuevo', 30, 0, 2200.50, 2, NULL),
(3, 3, 'Gasas y Jeringas', 200, 0, 500.00, 3, NULL),
(4, 3, 'Jeringas 5ml', 100, 0, 350.00, 4, NULL),
(5, 4, 'Agua Oxigenada', 10, 0, 1200.00, 5, NULL),
(6, 5, 'Guantes Latex (Nuevo Item)', 100, 0, 800.00, NULL, NULL),
(7, 6, 'Resmas A4 Autoridad', 20, 0, 6500.00, NULL, 1),
(8, 7, 'Lavandina para pisos', 5, 0, 3000.00, NULL, 2),
(9, 8, 'Lapiceras y Toner', 2, 0, 4500.00, NULL, 3),
(10, 8, 'Tóner HP Reserva', 1, 0, 85000.00, NULL, 5),
(11, 9, 'Detergente Cocina', 10, 0, 2500.00, NULL, 4),
(12, 10, 'Café para oficina (Item Nuevo)', 5, 0, 9000.00, NULL, NULL),
(13, 11, 'PRUEBA', 12, 0, 2.00, NULL, NULL),
(14, 12, 'pruenaa', 1, 0, 2.00, NULL, NULL),
(15, 13, 'remas a4', 10, 10, 14000.00, NULL, 1);

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
  `cantidad_recibida` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `pedidos_items`
--

INSERT INTO `pedidos_items` (`id`, `id_pedido`, `id_insumo`, `id_suministro`, `cantidad_solicitada`, `cantidad_aprobada`, `cantidad_entregada`, `cantidad_recibida`) VALUES
(4, 3, 2, NULL, 100, 100, 0, 0),
(5, 4, 4, NULL, 10, 9, 0, 0),
(6, 5, 1, NULL, 500, 401, 0, 398),
(7, 5, 5, NULL, 10, 0, 0, 0),
(8, 6, NULL, 2, 5, 5, 0, 0),
(9, 7, NULL, 1, 10, NULL, 0, 0),
(10, 8, NULL, 5, 1, NULL, 0, 0),
(11, 9, NULL, 1, 10, 10, 0, 0),
(12, 10, NULL, 1, 10, 9, 0, 0),
(14, 12, NULL, 3, 1, 1, 0, 0);

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
  `id_entrega_generada` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Volcado de datos para la tabla `pedidos_servicio`
--

INSERT INTO `pedidos_servicio` (`id`, `tipo_insumo`, `id_usuario_solicitante`, `servicio_solicitante`, `prioridad`, `frecuencia_compra`, `fecha_solicitud`, `estado`, `fecha_aprobacion_director`, `fecha_aprobacion_logistica`, `id_director_aprobador`, `id_logistica_aprobador`, `fecha_entrega_real`, `id_usuario_entrega`, `observaciones_director`, `observaciones_logistica`, `observaciones_entrega`, `paso_actual_id`, `proceso_origen`, `id_entrega_generada`) VALUES
(3, 'insumos_medicos', 8, 'Laboratorio', 'Normal', 'Mensual', '2025-12-11 14:08:11', 'esperando_entrega', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'movimiento_insumos', NULL),
(4, 'insumos_medicos', 8, 'Laboratorio', NULL, NULL, '2025-12-11 14:11:14', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12, 'movimiento_insumos', NULL),
(5, 'insumos_medicos', 8, 'Laboratorio', 'Normal', 'Mensual', '2025-12-11 16:12:28', 'finalizado_proceso', NULL, NULL, NULL, NULL, '2025-12-11 16:51:39', NULL, NULL, NULL, 'Recepción Proveedor. Remito: 011110. Observaciones: Faltaron 3 unidades del ítem #6. ', NULL, 'adquisicion_insumos', NULL),
(6, 'suministros', 8, 'Laboratorio', NULL, NULL, '2025-12-11 21:48:20', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 11, 'movimiento_suministros', NULL),
(7, 'suministros', 8, 'Laboratorio', NULL, NULL, '2025-12-11 22:08:50', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10, 'movimiento_suministros', NULL),
(8, 'suministros', 8, 'Laboratorio', NULL, NULL, '2025-12-11 22:25:42', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 18, 'movimiento_suministros', NULL),
(9, 'suministros', 8, 'Laboratorio', NULL, NULL, '2025-12-11 22:44:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'movimiento_suministros', NULL),
(10, 'suministros', 8, 'Laboratorio', NULL, NULL, '2025-12-11 23:06:27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'movimiento_suministros', NULL),
(12, 'suministros', 8, 'Laboratorio', NULL, NULL, '2025-12-11 23:21:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'movimiento_suministros', NULL);

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
(20, 'gestion_compras_suministros', 'Gestión Compras Suministros (Subir OC)', '6. Compras');

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
(3, 20);

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
(1, 'OF-001', 'Resma A4 75g', 'Papel para impresora, marca líder', 'paquetes', 30, 10, '2025-12-11 21:32:02'),
(2, 'LIM-001', 'Lavandina Concentrada', 'Bidón de 5 Litros', 'litros', 10, 2, '2025-12-05 16:49:41'),
(3, 'OF-002', 'Bolígrafos Azules', 'Caja x 50 unidades', 'cajas', 4, 1, '2025-12-11 21:32:02'),
(4, 'LIM-002', 'Detergente Industrial', 'Desengrasante potente', 'litros', 20, 5, '2025-12-05 16:49:41'),
(5, 'OF-003', 'Tóner HP 85A', 'Cartucho original negro', 'unidades', 3, 1, '2025-12-05 16:49:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre_completo` varchar(100) NOT NULL,
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

INSERT INTO `usuarios` (`id`, `nombre_completo`, `email`, `destino`, `servicio`, `grado_militar`, `rol_en_servicio`, `telefono`, `numero_interno`, `password`, `firma_digital`, `activo`, `validado_por_admin`, `created_at`) VALUES
(1, 'Super Admin', 'admin@actis.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$hE1T2etv4shephi4qceDHe6nL97Sv0PjkyVl3nBF8hchjc7waakf2', 'uploads/firmas/firma_1_1764967110.png', 1, 1, '2025-12-05 12:01:01'),
(2, 'ENCARGADO LOGISTICA', 'logistica@actis.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$pXLirllX5p2Yl6Yt0Af2b.lACBuHNHzDo5jwUmAWtZWR.pwednDOK', NULL, 1, 1, '2025-12-05 14:11:36'),
(3, 'COMPRAS', 'compras@actis.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$dwL2x.fsz0PpxQ9gC5T9iOmLsEfjRBtCNoLo771d0IqD4x3aH.eDG', 'uploads/firmas/firma_user_3_1764946491.png', 1, 1, '2025-12-05 14:12:43'),
(4, 'SUMINISTROS', 'suministros@actis.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$V96q4zZoyDehKNuZdZ7QuuJRtKEvy.cWZe/Waa7Zj8f4y8TLzEYOK', NULL, 1, 1, '2025-12-05 15:26:00'),
(6, 'DIRECTOR MEDICO', 'dirmedico@actis.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$Ovphj6ZEmYVl06X8oxz8SujjrskHK22hlRBO.RWVt3O6ax2ZvPYie', 'uploads/firmas/firma_6_1764956086.png', 1, 1, '2025-12-05 16:35:22'),
(7, 'INSUMOS MEDICOS', 'insumos@actis.com', NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$zMueH2Ele6SSnakVo3mEa..Q0hL1XvR4qsqMgwBMANty89He5EElK', NULL, 1, 1, '2025-12-05 16:36:41'),
(8, 'FEDERICO GONZALEZ', 'gonzalezfedericomarcelo@gmail.com', 'ACTIS', 'Laboratorio', 'SG', 'Responsable', '1166116861', '', '$2y$10$Magq/sqMtpAjXUWkHDN9yuHaCukP5kxmT9ounkMBCedywkMelSDqO', 'uploads/firmas/firma_8_1765468129.png', 1, 1, '2025-12-10 14:55:57'),
(9, 'DIRECTOR OPERATIVO', 'dirop@actis.com', NULL, '', NULL, 'Responsable', NULL, NULL, '$2y$10$jLWzb.N1NV7OEcomXnWp2uDghJyd5VQaW/ldS2fJOKmkajnPpBKwO', NULL, 1, 1, '2025-12-11 14:10:20'),
(10, 'Marcelo Cañete', 'odonto@actis.com', NULL, 'Odontologia', NULL, 'Responsable', NULL, NULL, '$2y$10$HFSh3qK6Q7BJwQauLa0HxuOyLtQN3AFSG/3ELg4nLYxWNDMVI9EH2', NULL, 1, 1, '2025-12-11 15:54:18');

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
  ADD KEY `id_usuario_solicitante` (`id_usuario_solicitante`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `areas_servicios`
--
ALTER TABLE `areas_servicios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `config_flujos`
--
ALTER TABLE `config_flujos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT de la tabla `entregas`
--
ALTER TABLE `entregas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `entregas_items`
--
ALTER TABLE `entregas_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial_ajustes`
--
ALTER TABLE `historial_ajustes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `insumos_medicos`
--
ALTER TABLE `insumos_medicos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT de la tabla `ordenes_compra`
--
ALTER TABLE `ordenes_compra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `ordenes_compra_items`
--
ALTER TABLE `ordenes_compra_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `pedidos_items`
--
ALTER TABLE `pedidos_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `pedidos_servicio`
--
ALTER TABLE `pedidos_servicio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

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
