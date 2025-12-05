-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 05-12-2025 a las 20:47:10
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `walmart`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carrito`
--

CREATE TABLE `carrito` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `carrito`
--

INSERT INTO `carrito` (`id`, `usuario_id`, `session_id`, `total`, `creado_en`) VALUES
(64, 13, 'fjfuci4ji6aadacgi7a3ua2mn7', 0.00, '2025-12-03 21:34:43'),
(66, NULL, 'ujhkvqun3fg047sdsu00ttf7h2', 15576.00, '2025-12-04 00:00:21'),
(69, NULL, 'h3babhsppve13v4grmm3q7057i', 1399.00, '2025-12-04 00:07:30'),
(73, 17, '5g33hau0uq46uao4lnudiv18po', 0.00, '2025-12-04 21:02:30'),
(76, NULL, 'oua8p0760g4m40odortodpoiat', 186.00, '2025-12-05 06:46:52'),
(77, 14, 'oua8p0760g4m40odortodpoiat', 14354.00, '2025-12-05 06:47:22'),
(81, NULL, 'lsc3s9vqdg8bcsq8agjdfkgh5d', 0.00, '2025-12-05 07:03:08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carrito_detalle`
--

CREATE TABLE `carrito_detalle` (
  `id` int(11) NOT NULL,
  `carrito_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `carrito_detalle`
--

INSERT INTO `carrito_detalle` (`id`, `carrito_id`, `producto_id`, `cantidad`, `subtotal`) VALUES
(94, 66, 1, 1, 13991.00),
(95, 66, 2, 1, 186.00),
(96, 66, 4, 1, 1399.00),
(102, 69, 4, 1, 1399.00),
(115, 76, 2, 1, 186.00),
(122, 77, 1, 1, 13991.00),
(123, 77, 2, 1, 186.00),
(124, 77, 5, 1, 177.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `direcciones`
--

CREATE TABLE `direcciones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `etiqueta` varchar(50) DEFAULT 'Casa',
  `calle` varchar(200) NOT NULL,
  `colonia` varchar(150) NOT NULL,
  `ciudad` varchar(150) NOT NULL,
  `estado` varchar(150) NOT NULL,
  `cp` varchar(10) NOT NULL,
  `creada_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `direcciones`
--

INSERT INTO `direcciones` (`id`, `usuario_id`, `etiqueta`, `calle`, `colonia`, `ciudad`, `estado`, `cp`, `creada_en`) VALUES
(6, 13, 'Casa', 'av', 'morels', 'mexico', 'mexico', '666887', '2025-12-03 19:35:32'),
(7, 14, 'Casa', 'prato', 'real toscana', 'mexico', 'mexico', '55767', '2025-12-03 22:11:42'),
(8, 15, 'Casa', 'montes', 'ojo de agua', 'mexico', 'mexico', '67656', '2025-12-04 00:03:56'),
(9, 16, 'Ubicación actual', 'Ubicación actual', 'Lat: 19.421594, Lon: -99.159245', 'MORELO', 'mexico', '00000', '2025-12-04 00:09:34'),
(10, 17, 'Casa', 'prato', 'acolam', 'MORELO', 'mexico', '12465', '2025-12-04 18:22:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metodos_pago`
--

CREATE TABLE `metodos_pago` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `alias` varchar(50) NOT NULL,
  `titular` varchar(100) NOT NULL,
  `marca` varchar(20) NOT NULL,
  `ultimos4` varchar(4) NOT NULL,
  `mes_exp` tinyint(4) NOT NULL,
  `anio_exp` smallint(6) NOT NULL,
  `creada_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `metodos_pago`
--

INSERT INTO `metodos_pago` (`id`, `usuario_id`, `alias`, `titular`, `marca`, `ultimos4`, `mes_exp`, `anio_exp`, `creada_en`) VALUES
(3, 13, 'Mi tarjeta', 'eduardo', 'Tarjeta', '3456', 12, 2025, '2025-12-03 13:36:20'),
(4, 14, 'Mi tarjeta', 'dana sanchez', 'Visa', '2176', 9, 2027, '2025-12-03 16:12:22'),
(5, 15, 'Mi tarjeta', 'dana sanchez', 'Visa', '2818', 9, 2026, '2025-12-03 18:05:36'),
(6, 16, 'Mi tarjeta', 'kjsadoijasodjias', 'Tarjeta', '1234', 11, 2026, '2025-12-03 18:11:11'),
(7, 17, 'Mi tarjeta', 'Eduardo', 'Tarjeta', '3456', 12, 2025, '2025-12-04 12:22:46');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `carrito_id` int(11) NOT NULL,
  `direccion_id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `estado` varchar(20) NOT NULL,
  `metodo_pago_id` int(11) NOT NULL,
  `horario_envio` varchar(50) NOT NULL,
  `creada_en` datetime NOT NULL,
  `estatus` varchar(30) NOT NULL DEFAULT 'en preparación'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `usuario_id`, `carrito_id`, `direccion_id`, `total`, `estado`, `metodo_pago_id`, `horario_envio`, `creada_en`, `estatus`) VALUES
(9, 13, 60, 6, 1448.00, 'pagado', 3, '4pm-5pm', '2025-12-03 13:36:20', 'en ruta'),
(10, 13, 61, 6, 235.00, 'pagado', 3, '5pm-6pm', '2025-12-03 15:15:07', 'entregado'),
(11, 13, 63, 6, 83.00, 'pagado', 3, '5pm-6pm', '2025-12-03 15:34:27', 'entregado'),
(12, 14, 65, 7, 28379.00, 'pagado', 4, '6pm-7pm', '2025-12-03 16:12:22', 'entregado'),
(13, 15, 67, 8, 29670.00, 'pagado', 5, '12pm-1pm', '2025-12-03 18:05:36', 'en ruta'),
(14, 16, 70, 9, 235.00, 'pagado', 6, '8pm-9pm', '2025-12-03 18:11:11', 'en preparación'),
(15, 17, 72, 10, 103.00, 'pagado', 7, '2pm-3pm', '2025-12-04 12:22:46', 'en preparación');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_detalle`
--

CREATE TABLE `pedido_detalle` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unit` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

CREATE TABLE `producto` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `imagen_url` varchar(255) DEFAULT NULL,
  `marca` varchar(100) NOT NULL DEFAULT '',
  `categoria` varchar(50) NOT NULL DEFAULT 'General',
  `calificacion` decimal(2,1) NOT NULL DEFAULT 0.0,
  `num_resenas` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto`
--

INSERT INTO `producto` (`id`, `nombre`, `precio`, `stock`, `imagen_url`, `marca`, `categoria`, `calificacion`, `num_resenas`) VALUES
(1, 'Television Samsung 65 Pulgadas 4K QLED QN65QEF1AFXZXX', 13991.00, 18, 'https://i5.walmartimages.com/asr/606236b5-3003-4b68-ae76-dad194bd0da7.955dec1892bbd198ee56500006af9bf8.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'Samsung', 'Electrónicos', 4.0, 2),
(2, 'Arrachera de res Marketside marinada 600 g', 186.00, 36, 'https://i5.walmartimages.com/asr/cede7e74-69f7-4919-9d01-541cb8512a67.ba883d9b6c43a340915b16a784ba19c8.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'Marketside', 'Comida', 3.0, 3),
(3, 'Antitranspirante Rexona Women powder dry 45 g', 54.00, 17, 'https://i5.walmartimages.com.mx/gr/images/product-images/img_large/00000007506292L.jpg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'Rexona', 'Cuidado personal', 0.0, 0),
(4, 'Perrito Camina Conmigo Fisher-Price Mattel Café', 1399.00, 3, 'https://i5.walmartimages.com/asr/e2a95348-9480-47f6-9274-3ba09fbfa181.d2e1de01a88d57293feb9f9223f36234.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'Fisher-Price', 'Juguetes', 0.0, 0),
(5, 'Leche Santa Clara entera caja con 6 pzas de 1 l c/u', 177.00, 32, 'https://i5.walmartimages.com/asr/6716721d-f8ad-4210-ad20-7f7f8947b2dc.94dd16317698c6c8ed5b6da1941fb83f.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'Santa Clara', 'Bebidas', 4.0, 1),
(6, 'Bebida alcohólica preparada New Mix El Jimador Vampiro 473 ml', 34.00, 7, 'https://i5.walmartimages.com/asr/9e2da033-b087-46d2-93a0-1b8745d28367.b693a0b5bddd9d13ac0620f73e98bbbf.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'El Jimador', 'Bebidas', 0.0, 0),
(7, 'Atún Dolores aleta amarilla en agua 140 g', 21.00, 70, 'https://i5.walmartimages.com/asr/9338ef18-9b9d-4d0b-bf09-eb5340a28300.49fba045f9ff726eafd93ac1a4e8001e.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'Dolores', 'General', 0.0, 0),
(8, 'Detergente líquido MAS colores intensos 6.64 l', 235.00, 8, 'https://i5.walmartimages.com.mx/gr/images/product-images/img_large/00750045900029L.jpg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'Mas', 'General', 0.0, 0),
(9, 'Frambuesa 175 gms', 64.00, 3, 'https://i5.walmartimages.com/asr/863d2ff0-ce71-49e8-8956-57f0d9203139.04cae4be91663d15e94b65af7f080738.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'Walmart', 'General', 0.0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_home`
--

CREATE TABLE `productos_home` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `categoria` varchar(100) NOT NULL,
  `descuento` int(11) NOT NULL DEFAULT 0,
  `imagen_url` varchar(255) NOT NULL,
  `fila` enum('superior','inferior') NOT NULL DEFAULT 'superior'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos_home`
--

INSERT INTO `productos_home` (`id`, `nombre`, `categoria`, `descuento`, `imagen_url`, `fila`) VALUES
(1, 'Pantalla', '', 20, 'https://i5.walmartimages.com/asr/606236b5-3003-4b68-ae76-dad194bd0da7.955dec1892bbd198ee56500006af9bf8.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'superior'),
(2, 'Piña', '', 10, 'https://i5.walmartimages.com/asr/554a19ed-da0e-4733-95ab-50718712c825.df6420bd84d8738180fe3ec1dbaa6474.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 'superior');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_imagen`
--

CREATE TABLE `producto_imagen` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `url` varchar(255) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto_imagen`
--

INSERT INTO `producto_imagen` (`id`, `producto_id`, `url`, `orden`) VALUES
(19, 1, 'https://i5.walmartimages.com/asr/62665a0a-f0db-41f3-b3f7-871cbc4dea7f.bea7fc3755f369ac489aac910fed20c0.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 1),
(20, 1, 'https://i5.walmartimages.com/asr/38eaf2b5-8c7d-45b6-aba3-0c66a67d40f0.850e93d04be48bed48425eb39271c5c2.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 2),
(21, 1, 'https://i5.walmartimages.com/asr/9ff44791-0270-42e9-bc10-978d5e1925d9.a07dd5e8859ccd520d71b4425026e041.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF', 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resena_producto`
--

CREATE TABLE `resena_producto` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `calificacion` tinyint(4) NOT NULL,
  `comentario` text NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `resena_producto`
--

INSERT INTO `resena_producto` (`id`, `producto_id`, `usuario_id`, `calificacion`, `comentario`, `creado_en`) VALUES
(1, 2, 17, 4, 'buena', '2025-12-05 00:01:34'),
(2, 2, 17, 4, 'mala', '2025-12-05 00:10:39'),
(3, 2, 17, 1, 'malisisma', '2025-12-05 00:10:57'),
(4, 1, NULL, 4, 'hola', '2025-12-05 00:44:01'),
(5, 1, 14, 4, 'xhjx', '2025-12-05 01:09:22'),
(6, 5, 14, 4, 'hola', '2025-12-05 01:10:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('cliente','operador','administrador') NOT NULL DEFAULT 'cliente',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `contrasena`, `correo`, `nombre`, `tipo`, `creado_en`) VALUES
(11, 'admin', 'admin123', 'admin@mitiendita.com', 'Administrador', 'administrador', '2025-12-02 21:33:35'),
(13, 'meli@gmsa.com', 'melissa', 'meli@gmsa.com', 'MEli', 'operador', '2025-12-02 22:00:33'),
(14, 'caraveo@gmail.com', '123456', 'caraveo@gmail.com', 'eduardo', 'cliente', '2025-12-03 22:08:20'),
(15, 'mel@gmail.com', '123456', 'mel@gmail.com', 'dana sanchez', 'cliente', '2025-12-04 00:01:41'),
(16, 'jfragosorizo@gmail.com', '123456', 'jfragosorizo@gmail.com', 'Jose Manuel', 'cliente', '2025-12-04 00:08:18'),
(17, 'caraveoo@gmail.com', 'Lalocura26$', 'caraveoo@gmail.com', 'Juan Lopez', 'cliente', '2025-12-04 18:14:30');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `carrito`
--
ALTER TABLE `carrito`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_carrito_usuario` (`usuario_id`);

--
-- Indices de la tabla `carrito_detalle`
--
ALTER TABLE `carrito_detalle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_detalle_carrito` (`carrito_id`),
  ADD KEY `fk_detalle_producto` (`producto_id`);

--
-- Indices de la tabla `direcciones`
--
ALTER TABLE `direcciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_direcciones_usuario` (`usuario_id`);

--
-- Indices de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `direccion_id` (`direccion_id`);

--
-- Indices de la tabla `pedido_detalle`
--
ALTER TABLE `pedido_detalle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `producto`
--
ALTER TABLE `producto`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `productos_home`
--
ALTER TABLE `productos_home`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `producto_imagen`
--
ALTER TABLE `producto_imagen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `resena_producto`
--
ALTER TABLE `resena_producto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `carrito`
--
ALTER TABLE `carrito`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT de la tabla `carrito_detalle`
--
ALTER TABLE `carrito_detalle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT de la tabla `direcciones`
--
ALTER TABLE `direcciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `pedido_detalle`
--
ALTER TABLE `pedido_detalle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `producto`
--
ALTER TABLE `producto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `productos_home`
--
ALTER TABLE `productos_home`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `producto_imagen`
--
ALTER TABLE `producto_imagen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `resena_producto`
--
ALTER TABLE `resena_producto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `carrito`
--
ALTER TABLE `carrito`
  ADD CONSTRAINT `fk_carrito_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `carrito_detalle`
--
ALTER TABLE `carrito_detalle`
  ADD CONSTRAINT `fk_detalle_carrito` FOREIGN KEY (`carrito_id`) REFERENCES `carrito` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`producto_id`) REFERENCES `producto` (`id`);

--
-- Filtros para la tabla `direcciones`
--
ALTER TABLE `direcciones`
  ADD CONSTRAINT `fk_direcciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  ADD CONSTRAINT `metodos_pago_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`direccion_id`) REFERENCES `direcciones` (`id`);

--
-- Filtros para la tabla `pedido_detalle`
--
ALTER TABLE `pedido_detalle`
  ADD CONSTRAINT `pedido_detalle_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `pedido_detalle_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `producto` (`id`);

--
-- Filtros para la tabla `producto_imagen`
--
ALTER TABLE `producto_imagen`
  ADD CONSTRAINT `producto_imagen_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `producto` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `resena_producto`
--
ALTER TABLE `resena_producto`
  ADD CONSTRAINT `resena_producto_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `producto` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resena_producto_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
