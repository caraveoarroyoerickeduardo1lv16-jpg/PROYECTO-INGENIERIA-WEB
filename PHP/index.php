<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

$sessionId    = session_id();
$estaLogueado = !empty($_SESSION['user_id']);
$usuario_id   = $estaLogueado ? (int)$_SESSION['user_id'] : null;

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

$referrer = $_SERVER['HTTP_REFERER'] ?? '';

// ==========================================================
// 0) SI ES INVITADO: BORRAR CARRITO SOLO EN ENTRADAS "NUEVAS"
// ==========================================================
if (!$estaLogueado) {
    if ($referrer === '' || strpos($referrer, 'carrito.php') === false) {
        $stmt = $conn->prepare("
            SELECT id
            FROM carrito
            WHERE session_id = ?
              AND (usuario_id IS NULL OR usuario_id = 0)
            LIMIT 1
        ");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $resCarTmp = $stmt->get_result();
        $carTmp = $resCarTmp->fetch_assoc();
        $stmt->close();

        if ($carTmp) {
            $cid = (int)$carTmp['id'];

            $stmt = $conn->prepare("DELETE FROM carrito_detalle WHERE carrito_id = ?");
            $stmt->bind_param("i", $cid);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM carrito WHERE id = ?");
            $stmt->bind_param("i", $cid);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/* 1) CATEGORÍAS */
$categorias = [];
$resCat = $conn->query("SELECT DISTINCT categoria FROM producto ORDER BY categoria");
while ($row = $resCat->fetch_assoc()) {
    if (trim($row['categoria']) !== '') {
        $categorias[] = $row['categoria'];
    }
}

/* 2) PRODUCTOS */
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

    // ✅ Si te pasan un producto_id inexistente, regresamos a inicio normal
    if (count($productos) === 0) {
        header("Location: index.php");
        exit;
    }

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

/* 3) CARRITO */
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

/* 4) CANTIDADES POR PRODUCTO */
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
<body data-logged="<?php echo $estaLogueado ? '1' : '0'; ?>">

<header class="header">
    <div class="header-left">
        <div class="logo">
            <a href="index.php" class="logo-link">
                <div class="logo-icon">*</div>
            </a>
            <h1>Mi Tiendita</h1>
        </div>

        <div class="search-bar">
            <input
                type="text"
                id="searchInput"
                placeholder="¿Cómo quieres tus artículos?"
                autocomplete="off"
            >
            <div id="searchSuggestions" class="search-suggestions"></div>
            <div id="searchNotFound" class="search-notfound">Producto no encontrado</div>
        </div>
    </div>

    <div class="header-right">
        <?php if ($estaLogueado): ?>
            <span class="header-user"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
            <a href="../PHP/logout.php" class="header-link">Cerrar sesión</a>
        <?php else: ?>
            <a href="../PHP/login.php" class="header-link">Iniciar sesión</a>
        <?php endif; ?>

        <span id="cartTotalItems" class="header-items" data-items="<?php echo $total_items; ?>">
            <?php echo $total_items; ?> artículo<?php echo $total_items === 1 ? '' : 's'; ?>
        </span>

        <span id="cartTotalPrice" class="header-price" data-total="<?php echo $total_carrito; ?>">
            $<?php echo number_format($total_carrito, 2); ?>
        </span>

        <a href="../PHP/carrito.php" class="header-cart-link">
            <span class="header-cart">🛒</span>
        </a>
    </div>
</header>

<nav class="nav-categorias">
    <a href="index.php" class="nav-item <?php echo ($categoriaActual === '' && $productoId === 0) ? 'activo' : ''; ?>">
        Inicio
    </a>

    <?php foreach ($categorias as $cat): ?>
        <a
            href="index.php?categoria=<?php echo urlencode($cat); ?>"
            class="nav-item <?php echo ($categoriaActual === $cat) ? 'activo' : ''; ?>"
        >
            <?php echo htmlspecialchars($cat); ?>
        </a>
    <?php endforeach; ?>

    <?php if ($estaLogueado): ?>
        <a href="mis_pedidos.php" class="nav-item">Mis pedidos📝</a>
    <?php endif; ?>
</nav>

<main class="main-container">
    <h2 class="titulo-seccion"><?php echo htmlspecialchars($tituloSeccion); ?></h2>

    <div class="carrusel-wrapper">
        <button class="btn-carrusel btn-carrusel-izq">&#10094;</button>

        <div class="carrusel-viewport">
            <section class="grid-productos carrusel-pista">
                <?php foreach ($productos as $p): ?>
                    <?php
                        $pid            = (int)$p['id'];
                        $stock          = (int)$p['stock'];
                        $cantEnCarrito  = $cantidadesPorProducto[$pid] ?? 0;
                        $estaEnCarrito  = $cantEnCarrito > 0;

                        $sinStock = ($stock <= 0);
                        $disabledAgregar = (!$estaEnCarrito && $sinStock);
                    ?>
                    <article
                        class="producto-card"
                        data-id="<?php echo $pid; ?>"
                        data-precio="<?php echo (float)$p['precio']; ?>"
                        data-stock="<?php echo $stock; ?>"
                    >
                        <a href="producto_detalle.php?id=<?php echo $pid; ?>" class="producto-link">
                            <div class="producto-img-wrapper">
                                <img
                                    src="<?php echo htmlspecialchars($p['imagen_url']); ?>"
                                    alt="<?php echo htmlspecialchars($p['nombre']); ?>"
                                >
                            </div>

                            <div class="producto-info">
                                <div class="precio-actual">$<?php echo number_format((float)$p['precio'], 2); ?></div>
                                <div class="marca"><?php echo htmlspecialchars($p['marca']); ?></div>
                                <div class="titulo"><?php echo htmlspecialchars($p['nombre']); ?></div>

                                <?php if ($sinStock): ?>
                                    <div class="stock-label">Sin stock</div>
                                <?php endif; ?>
                            </div>
                        </a>

                        <div class="producto-actions">
                            <button
                                class="btn-agregar"
                                <?php echo $disabledAgregar ? 'disabled' : ''; ?>
                                style="<?php echo $estaEnCarrito ? 'display:none;' : ''; ?>"
                            >
                                <?php echo $disabledAgregar ? 'Sin stock' : '+ Agregar'; ?>
                            </button>

                            <div class="cantidad-control <?php echo $estaEnCarrito ? '' : 'oculto'; ?>">
                                <button class="btn-menos">−</button>
                                <span class="cantidad"><?php echo $estaEnCarrito ? (int)$cantEnCarrito : 0; ?></span>
                                <button class="btn-mas" <?php echo ($sinStock ? 'disabled' : ''); ?>>+</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>

        <button class="btn-carrusel btn-carrusel-der">&#10095;</button>
    </div>
</main>

<footer class="footer">
    <div class="footer-content">
        <h3>Mi Tiendita</h3>
        <p>Teléfono de contacto: <strong>55-8923-4417</strong></p>
        <p>Correo de contacto: <strong>soporte@mitiendita.com</strong></p>
    </div>
</footer>

<!-- MODAL LOGIN -->
<div id="loginModal" class="modal-overlay" aria-hidden="true">
    <div class="modal">
        <h3>Iniciar sesión</h3>
        <p>Para agregar o modificar productos en el carrito necesitas iniciar sesión.</p>
        <div class="modal-actions">
            <button id="modalGoLogin" class="modal-btn modal-btn-primary">Iniciar sesión</button>
            <button id="modalClose" class="modal-btn">Cancelar</button>
        </div>
    </div>
</div>

<?php if (isset($_GET['nf']) && $_GET['nf'] === '1'): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const msg = document.getElementById("searchNotFound");
    if (!msg) return;

    msg.classList.add("show");
    setTimeout(() => msg.classList.remove("show"), 2500);

    const u = new URL(window.location.href);
    u.searchParams.delete("nf");
    window.history.replaceState({}, "", u.toString());
});
</script>
<?php endif; ?>

<script src="../JAVASCRIPT/index.js"></script>
</body>
</html>



