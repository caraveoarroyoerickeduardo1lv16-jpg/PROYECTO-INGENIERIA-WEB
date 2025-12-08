<?php


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();


if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; 
}

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

// Leer todos los productos
$res = $conn->query("SELECT id, nombre, precio, stock, imagen_url FROM producto");
$productos = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi tiendita - Tienda</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/tienda.css">
</head>
<body>

<header class="header">
    <div class="header-left">
        <div class="logo">
            <span class="logo-icon">*</span>
            <span class="logo-text">Mi tiendita</span>
        </div>
        <div class="search-bar">
            <input type="text" placeholder="¿Cómo quieres tus artículos?">
        </div>
    </div>
    <div class="header-right">
        <span id="cartTotalItems" class="header-items">0 artículos</span>
        <span id="cartTotalPrice" class="header-price">$0.00</span>
        <span class="header-cart">🛒</span>
    </div>
</header>

<nav class="nav-categorias">
    <a href="#" class="nav-item activo">Categorías</a>
    <a href="#" class="nav-item">Comida</a>
    <a href="#" class="nav-item">Electrónicos</a>
    <a href="#" class="nav-item">Ropa</a>
    <a href="#" class="nav-item">Hogar</a>
</nav>

<main class="main-container">
    <h2 class="titulo-seccion">Lo más comprado</h2>

    <section class="grid-productos">
        <?php foreach ($productos as $p): ?>
            <article class="producto-card"
                     data-id="<?php echo $p['id']; ?>"
                     data-precio="<?php echo $p['precio']; ?>">
                <div class="badge-rebaja">Rebaja</div>
                <button class="btn-favorito">♡</button>

                <div class="producto-img-wrapper">
                    <img src="<?php echo htmlspecialchars($p['imagen_url']); ?>"
                         alt="<?php echo htmlspecialchars($p['nombre']); ?>">
                </div>

                <div class="producto-actions">
                    <button class="btn-agregar">+ Agregar</button>
                    <div class="cantidad-control oculto">
                        <button class="btn-menos">−</button>
                        <span class="cantidad">1</span>
                        <button class="btn-mas">+</button>
                    </div>
                </div>

                <div class="producto-info">
                    <div class="precio-actual">
                        $<?php echo number_format($p['precio'], 2); ?>
                    </div>
                    <div class="marca">
                       
                        Samsung
                    </div>
                    <div class="titulo">
                        <?php echo htmlspecialchars($p['nombre']); ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</main>

<script src="../JAVASCRIPT/tienda.js"></script>
</body>
</html>
