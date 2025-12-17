<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

$sessionId    = session_id();
$estaLogueado = !empty($_SESSION['user_id']);
$usuario_id   = $estaLogueado ? (int)$_SESSION['user_id'] : null;

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

/* =========================
   CATEGORÍAS
========================= */
$categorias = [];
$resCat = $conn->query("SELECT DISTINCT categoria FROM producto ORDER BY categoria");
while ($row = $resCat->fetch_assoc()) {
    if (trim($row['categoria']) !== '') {
        $categorias[] = $row['categoria'];
    }
}

/* =========================
   PRODUCTOS
========================= */
$categoriaActual = trim($_GET['categoria'] ?? '');
$productoId      = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

if ($productoId > 0) {
    $stmt = $conn->prepare("
        SELECT id, nombre, precio, stock, imagen_url, marca, categoria
        FROM producto
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $productoId);
    $stmt->execute();
    $resProd   = $stmt->get_result();
    $productos = $resProd->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} elseif ($categoriaActual !== '') {
    $stmt = $conn->prepare("
        SELECT id, nombre, precio, stock, imagen_url, marca, categoria
        FROM producto
        WHERE categoria = ?
    ");
    $stmt->bind_param("s", $categoriaActual);
    $stmt->execute();
    $resProd   = $stmt->get_result();
    $productos = $resProd->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $resProd = $conn->query("
        SELECT id, nombre, precio, stock, imagen_url, marca, categoria
        FROM producto
    ");
    $productos = $resProd->fetch_all(MYSQLI_ASSOC);
}

/* =========================
   CARRITO
========================= */
if ($estaLogueado) {
    $stmt = $conn->prepare("SELECT id, total FROM carrito WHERE usuario_id = ? LIMIT 1");
    $stmt->bind_param("i", $usuario_id);
} else {
    $stmt = $conn->prepare("
        SELECT id, total
        FROM carrito
        WHERE session_id = ?
          AND (usuario_id IS NULL OR usuario_id = 0)
        LIMIT 1
    ");
    $stmt->bind_param("s", $sessionId);
}
$stmt->execute();
$resCar  = $stmt->get_result();
$carrito = $resCar->fetch_assoc();
$stmt->close();

$carrito_id    = $carrito['id']   ?? null;
$total_carrito = (float)($carrito['total'] ?? 0.0);
$total_items   = 0;

/* =========================
   CANTIDADES EN CARRITO
========================= */
$cantidadesPorProducto = [];
if ($carrito_id) {
    $stmt = $conn->prepare("
        SELECT producto_id, cantidad
        FROM carrito_detalle
        WHERE carrito_id = ?
    ");
    $stmt->bind_param("i", $carrito_id);
    $stmt->execute();
    $resDet = $stmt->get_result();

    while ($row = $resDet->fetch_assoc()) {
        $pid  = (int)$row['producto_id'];
        $cant = (int)$row['cantidad'];
        $cantidadesPorProducto[$pid] = $cant;
        $total_items += $cant;
    }
    $stmt->close();
}

if ($productoId > 0 && count($productos) === 1) {
    $tituloSeccion = $productos[0]['nombre'];
} elseif ($categoriaActual !== '') {
    $tituloSeccion = $categoriaActual;
} else {
    $tituloSeccion = "Lo más comprado";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi tiendita - Inicio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/index.css">
</head>

<body data-logged="<?= $estaLogueado ? '1' : '0'; ?>">

<header class="header">
    <div class="header-left">
        <div class="logo">
            <a href="index.php" class="logo-link">
                <div class="logo-icon">*</div>
            </a>
            <h1>Mi Tiendita</h1>
        </div>

        <div class="search-bar">
            <input type="text" id="searchInput"
                   placeholder="¿Cómo quieres tus artículos?"
                   autocomplete="off">
            <div id="searchSuggestions" class="search-suggestions"></div>
            <div id="searchNotFound" class="search-notfound">Producto no encontrado</div>
        </div>
    </div>

    <div class="header-right">
        <?php if ($estaLogueado): ?>
            <span class="header-user"><?= htmlspecialchars($_SESSION['usuario']); ?></span>
            <a href="../PHP/logout.php" class="header-link">Cerrar sesión</a>
        <?php else: ?>
            <a href="../PHP/login.php" class="header-link">Iniciar sesión</a>
        <?php endif; ?>

        <span id="cartTotalItems"><?= $total_items; ?> artículos</span>
        <span id="cartTotalPrice">$<?= number_format($total_carrito, 2); ?></span>

        <a href="../PHP/carrito.php" class="header-cart-link">🛒</a>
    </div>
</header>

<nav class="nav-categorias">
    <a href="index.php" class="nav-item <?= ($categoriaActual === '' && $productoId === 0) ? 'activo' : ''; ?>">
        Inicio
    </a>

    <?php foreach ($categorias as $cat): ?>
        <a href="index.php?categoria=<?= urlencode($cat); ?>"
           class="nav-item <?= ($categoriaActual === $cat) ? 'activo' : ''; ?>">
            <?= htmlspecialchars($cat); ?>
        </a>
    <?php endforeach; ?>
</nav>

<main class="main-container">

    <h2 class="titulo-seccion"><?= htmlspecialchars($tituloSeccion); ?></h2>

    <!-- ✅ MENSAJE GRANDE (controlado por JS) -->
    <div id="productsMessage" class="products-message" style="display:none;">
        Producto no existente
    </div>

    <!-- PRODUCTOS -->
    <div class="carrusel-wrapper">
        <div class="carrusel-viewport">
            <section class="grid-productos carrusel-pista">

                <?php foreach ($productos as $p): ?>
                    <?php
                        $pid           = (int)$p['id'];
                        $stock         = (int)$p['stock'];
                        $cantCarrito   = $cantidadesPorProducto[$pid] ?? 0;
                        $estaEnCarrito = $cantCarrito > 0;
                        $sinStock      = ($stock <= 0);
                    ?>
                    <article class="producto-card"
                             data-id="<?= $pid; ?>"
                             data-stock="<?= $stock; ?>"
                             data-precio="<?= (float)$p['precio']; ?>">

                        <a href="producto_detalle.php?id=<?= $pid; ?>" class="producto-link">
                            <img src="<?= htmlspecialchars($p['imagen_url']); ?>"
                                 alt="<?= htmlspecialchars($p['nombre']); ?>">
                            <div class="producto-info">
                                <div class="precio">$<?= number_format($p['precio'], 2); ?></div>
                                <div class="marca"><?= htmlspecialchars($p['marca']); ?></div>
                                <div class="titulo"><?= htmlspecialchars($p['nombre']); ?></div>
                            </div>
                        </a>

                        <div class="producto-actions">
                            <button class="btn-agregar"
                                <?= (!$estaEnCarrito && $sinStock) ? 'disabled' : ''; ?>
                                style="<?= $estaEnCarrito ? 'display:none;' : ''; ?>">
                                <?= $sinStock ? 'Sin stock' : '+ Agregar'; ?>
                            </button>

                            <div class="cantidad-control <?= $estaEnCarrito ? '' : 'oculto'; ?>">
                                <button class="btn-menos">−</button>
                                <span class="cantidad"><?= $cantCarrito; ?></span>
                                <button class="btn-mas" <?= $sinStock ? 'disabled' : ''; ?>>+</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>

            </section>
        </div>
    </div>
</main>

<footer class="footer">
    <p>Mi Tiendita · soporte@mitiendita.com</p>
</footer>

<script src="../JAVASCRIPT/index.js"></script>
</body>
</html>




