<?php


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

$estaLogueado = !empty($_SESSION['user_id']);
$usuario_id   = $estaLogueado ? (int)$_SESSION['user_id'] : null;
$sessionId    = session_id();

/* 1) Obtener carrito actual */
if ($estaLogueado) {
    // carrito del usuario
    $stmt = $conn->prepare("
        SELECT id, total
        FROM carrito
        WHERE usuario_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $usuario_id);
} else {
    // carrito temporal por sesión
    $stmt = $conn->prepare("
        SELECT id, total
        FROM carrito
        WHERE session_id = ?
          AND usuario_id IS NULL
        LIMIT 1
    ");
    $stmt->bind_param("s", $sessionId);
}
$stmt->execute();
$resCar   = $stmt->get_result();
$carrito  = $resCar->fetch_assoc();
$stmt->close();

$carrito_id    = $carrito['id'] ?? null;
$total_carrito = (float)($carrito['total'] ?? 0);

/* 2) Obtener detalle de productos */
$productos   = [];
$total_items = 0;

if ($carrito_id) {
    $stmt = $conn->prepare("
        SELECT cd.producto_id,
               cd.cantidad,
               cd.subtotal,
               p.nombre,
               p.marca,
               p.precio,
               p.imagen_url
        FROM carrito_detalle cd
        INNER JOIN producto p ON p.id = cd.producto_id
        WHERE cd.carrito_id = ?
    ");
    $stmt->bind_param("i", $carrito_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $productos = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($productos as $p) {
        $total_items += (int)$p['cantidad'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito - Mi tiendita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/carrito.css">
</head>
<body>

<header class="header">
    <div class="header-left">
       <div class="logo">
        <a href="index.php" class="logo-link">
            <div class="logo-icon">*</div>
        </a>
        <h1>Mi Tiendita</h1>
    </div>
        <div class="search-bar">
            <input type="text" placeholder="¿Cómo quieres tus artículos?">
        </div>
    </div>

    <div class="header-right">
        <?php if ($estaLogueado): ?>
            <span class="header-user">
                <?php echo htmlspecialchars($_SESSION['usuario']); ?>
            </span>
            <a href="../PHP/logout.php" class="header-link">Cerrar sesión</a>
        <?php else: ?>
            <a href="login.php" class="header-link">Iniciar sesión</a>
        <?php endif; ?>

        <span class="header-items">
            <?php echo $total_items; ?> artículo<?php echo $total_items === 1 ? '' : 's'; ?>
        </span>
        <span class="header-price">
            $<?php echo number_format($total_carrito, 2); ?>
        </span>

        <a href="carrito.php" class="header-cart-link">
            <span class="header-cart">🛒</span>
        </a>
    </div>
</header>

<main class="contenedor">

    <h1>Carrito</h1>
    <p><?php echo $total_items; ?> artículos</p>

    <div class="layout">

        <!-- LISTA DE PRODUCTOS -->
        <section class="productos">
            <?php if (empty($productos)): ?>
                <p>No tienes productos en el carrito.</p>
            <?php else: ?>
                <?php foreach ($productos as $p): ?>
                    <article class="item">
                        <div class="item-img">
                            <img src="<?php echo htmlspecialchars($p['imagen_url']); ?>"
                                 alt="<?php echo htmlspecialchars($p['nombre']); ?>">
                        </div>

                        <div class="item-info">
                            <h3><?php echo htmlspecialchars($p['nombre']); ?></h3>
                            <p class="marca">
                                <?php echo htmlspecialchars($p['marca']); ?>
                            </p>

                            <div class="acciones">
                                <button class="btn-menos"
                                        data-id="<?php echo (int)$p['producto_id']; ?>">−</button>

                                <span class="cantidad">
                                    <?php echo (int)$p['cantidad']; ?>
                                </span>

                                <button class="btn-mas"
                                        data-id="<?php echo (int)$p['producto_id']; ?>">+</button>

                                <button class="btn-eliminar"
                                        data-id="<?php echo (int)$p['producto_id']; ?>">Eliminar</button>
                            </div>
                        </div>

                        <div class="precio">
                            $<?php echo number_format($p['subtotal'], 2); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- RESUMEN DERECHA -->
        <aside class="resumen">
            <div class="card-resumen">

                <?php if (!$estaLogueado): ?>
                    <!-- Invitado: botón que lleva a login.php -->
                    <button class="btn-comprar"
                            type="button"
                            onclick="window.location.href='login.php';">
                        Continuar
                    </button>
                <?php else: ?>
                    <!-- Usuario logueado: ir a página Direcciones y método de pago -->
                    <button class="btn-comprar"
                            type="button"
                            onclick="window.location.href='../PHP/checkout.php';">
                        Comprar todos los artículos
                    </button>
                <?php endif; ?>

                <p class="subtotal">
                    Subtotal (<?php echo $total_items; ?> artículos)
                    <span>$<?php echo number_format($total_carrito, 2); ?></span>
                </p>

                <p class="ahorro">
                    Ahorros <span>-$0.00</span>
                </p>

                <hr>

                <p class="total">
                    <strong>Total estimado</strong>
                    <strong>$<?php echo number_format($total_carrito, 2); ?></strong>
                </p>
            </div>
        </aside>

    </div>

</main>

<script src="../JAVASCRIPT/carrito.js"></script>
</body>
</html>




