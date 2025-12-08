<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

// SOLO OPERADOR
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_tipo"] ?? '') !== "operador") {
    header("Location: login.php");
    exit;
}

/*  MENSAJE DE ERROR DE STOCK  */
$stockError = $_SESSION['stock_error'] ?? '';
unset($_SESSION['stock_error']);

/* 1) ACTUALIZAR STOCK */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['producto_id'], $_POST['accion'], $_POST['cantidad'])
) {
    $producto_id   = (int)$_POST['producto_id'];
    $accion        = $_POST['accion'];
    $categoriaPost = $_POST['categoria'] ?? 'todas';

    $cantidad = (int)$_POST['cantidad'];
    if ($cantidad < 1) {
        $cantidad = 0;
    }

    if ($producto_id > 0 && $cantidad > 0) {

        if ($accion === 'agregar') {
            $delta = $cantidad;

            $stmt = $conn->prepare("
                UPDATE producto
                SET stock = stock + ?
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $delta, $producto_id);
            $stmt->execute();
            $stmt->close();

        } elseif ($accion === 'quitar') {

            // 1) Leer stock actual
            $stmt = $conn->prepare("SELECT stock FROM producto WHERE id = ?");
            $stmt->bind_param("i", $producto_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stockActual = $res ? (int)$res['stock'] : 0;

            // 2) Si intenta quitar más de lo que hay  NO permitir
            if ($cantidad > $stockActual) {
                $_SESSION['stock_error'] = "No puedes quitar más unidades de las que hay en stock (stock actual: {$stockActual}).";

            } else {
                $delta = -$cantidad;

                $stmt = $conn->prepare("
                    UPDATE producto
                    SET stock = stock + ?
                    WHERE id = ?
                ");
                $stmt->bind_param("ii", $delta, $producto_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Redirigir para evitar reenvío y mantener categoría
    header("Location: operador_stock.php?categoria=" . urlencode($categoriaPost));
    exit;
}

/* 2) LEER CATEGORÍAS  */
$categorias = [];
$resCat = $conn->query("SELECT DISTINCT categoria FROM producto WHERE TRIM(categoria) <> '' ORDER BY categoria");
while ($rowCat = $resCat->fetch_assoc()) {
    $categorias[] = $rowCat['categoria'];
}

/*3) FILTRO POR CATEGORÍA  */
$categoriaFiltro = $_GET['categoria'] ?? 'todas';

if ($categoriaFiltro === 'todas' || $categoriaFiltro === '') {
    $res = $conn->query("
        SELECT id, nombre, stock, imagen_url, categoria
        FROM producto
        ORDER BY categoria, nombre
    ");
} else {
    $stmt = $conn->prepare("
        SELECT id, nombre, stock, imagen_url, categoria
        FROM producto
        WHERE categoria = ?
        ORDER BY nombre
    ");
    $stmt->bind_param("s", $categoriaFiltro);
    $stmt->execute();
    $res = $stmt->get_result();
}

$productos = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Stock - Operador</title>
    <link rel="stylesheet" href="../CSS/operador_stock.css">
</head>
<body>

<div class="page">


    <header class="topbar">
        <div class="topbar-inner">
            <a href="operador.php" class="logo-link">
                <div class="logo-icon"><span class="logo-star">*</span></div>
                <span class="logo-text">Mi tiendita</span>
            </a>

            <a href="logout.php" class="logout-button">Cerrar sesión</a>
        </div>
    </header>


    <main class="main">
        <h1>Panel de operador</h1>
        <h2 class="subtitle">Existencias</h2>

        <?php if ($stockError !== ''): ?>
            <div class="stock-error">
                <?= htmlspecialchars($stockError) ?>
            </div>
        <?php endif; ?>

        <!-- FILTRO POR CATEGORÍA -->
        <form method="get" class="category-filter">
            <label for="categoria">Categoría:</label>
            <select name="categoria" id="categoria" onchange="this.form.submit()">
                <option value="todas" <?= ($categoriaFiltro === 'todas' ? 'selected' : '') ?>>
                    Todas
                </option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"
                        <?= ($categoriaFiltro === $cat ? 'selected' : '') ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <section class="stock-card">
            <header class="stock-header">
                <span>Producto</span>
                <span>Stock</span>
                <span>Cambiar existencias</span>
            </header>

            <?php foreach ($productos as $p): ?>
                <div class="stock-row">
                    <div class="stock-producto">
                        <div class="stock-img">
                            <img src="<?= htmlspecialchars($p['imagen_url']) ?>"
                                 alt="<?= htmlspecialchars($p['nombre']) ?>">
                        </div>
                        <div class="stock-nombre">
                            <?= htmlspecialchars($p['nombre']) ?>
                        </div>
                    </div>

                    <div class="stock-valor">
                        <?= (int)$p['stock'] ?>
                    </div>

                    <form method="post" class="stock-controles">
                        <input type="hidden" name="producto_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="categoria"
                               value="<?= htmlspecialchars($categoriaFiltro) ?>">

                        <input type="number" name="cantidad" class="qty-input" min="1" value="1">

                        <button type="submit" name="accion" value="quitar" class="btn-remove">
                            Quitar
                        </button>

                        <button type="submit" name="accion" value="agregar" class="btn-add">
                            Agregar
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>

            <?php if (empty($productos)): ?>
                <p class="no-products">No hay productos en esta categoría.</p>
            <?php endif; ?>
        </section>
    </main>

</div>

</body>
</html>


