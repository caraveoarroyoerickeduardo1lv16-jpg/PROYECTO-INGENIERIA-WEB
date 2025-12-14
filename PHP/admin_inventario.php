<?php
session_start();

// Solo admins pueden entrar
if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'administrador') {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

// Categorías
$categorias = [];
$resCat = $conn->query("SELECT DISTINCT categoria FROM producto ORDER BY categoria");
while ($row = $resCat->fetch_assoc()) {
    if (trim($row['categoria']) !== '') $categorias[] = $row['categoria'];
}

// Filtros
$categoriaActual = trim($_GET['categoria'] ?? '');
$q              = trim($_GET['q'] ?? '');
$productoId      = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

// Productos
if ($productoId > 0) {
    $stmt = $conn->prepare("
        SELECT id, nombre, precio, stock, imagen_url, marca, categoria
        FROM producto
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $productoId);
} else {
    if ($categoriaActual !== '' && $q !== '') {
        $like = "%{$q}%";
        $stmt = $conn->prepare("
            SELECT id, nombre, precio, stock, imagen_url, marca, categoria
            FROM producto
            WHERE categoria = ?
              AND (nombre LIKE ? OR marca LIKE ?)
            ORDER BY id
        ");
        $stmt->bind_param("sss", $categoriaActual, $like, $like);
    } elseif ($categoriaActual !== '') {
        $stmt = $conn->prepare("
            SELECT id, nombre, precio, stock, imagen_url, marca, categoria
            FROM producto
            WHERE categoria = ?
            ORDER BY id
        ");
        $stmt->bind_param("s", $categoriaActual);
    } elseif ($q !== '') {
        $like = "%{$q}%";
        $stmt = $conn->prepare("
            SELECT id, nombre, precio, stock, imagen_url, marca, categoria
            FROM producto
            WHERE (nombre LIKE ? OR marca LIKE ?)
            ORDER BY id
        ");
        $stmt->bind_param("ss", $like, $like);
    } else {
        $stmt = $conn->prepare("
            SELECT id, nombre, precio, stock, imagen_url, marca, categoria
            FROM producto
            ORDER BY id
        ");
    }
}

$stmt->execute();
$resProd = $stmt->get_result();
$productos = $resProd->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario - Mi tiendita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../CSS/admin.css">
    <link rel="stylesheet" href="../CSS/admin_inventario.css">
</head>
<body>

<div class="page">

    <header class="topbar">
        <div class="topbar-inner">
            <a href="admin.php" class="logo-link">
                <div class="logo-icon">
                    <span class="logo-star">*</span>
                </div>
                <span class="logo-text">Mi tiendita</span>
            </a>
        </div>
    </header>

    <div class="logout-container">
        <a href="logout.php" class="logout-button">Cerrar sesión</a>
    </div>

    <main class="admin-main">

        <section class="inventory-header">
            <h1>Panel de administrador</h1>
            <h2>Inventario</h2>

            <div class="inventory-top">

                <!-- filtro categoría -->
                <form method="get" class="inventory-filter" id="formCategoria">
                    <label for="categoria">Categoría:</label>
                    <select id="categoria" name="categoria" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"
                                <?= ($cat === $categoriaActual) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($q !== '' && $productoId === 0): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                    <?php endif; ?>
                </form>

                <!-- ✅ BUSCADOR BONITO (ADMIN) -->
                <div class="inv-search">
                    <div class="inv-search-input">
                        <span class="inv-search-icon">🔎</span>
                        <input
                            type="text"
                            id="invSearchInput"
                            placeholder="Buscar producto por nombre o marca…"
                            autocomplete="off"
                            value="<?= htmlspecialchars($q) ?>"
                        >
                        <button type="button" id="invClearBtn" class="inv-clear" aria-label="Limpiar">✕</button>
                    </div>

                    <div id="invSearchSuggestions" class="inv-suggestions"></div>

                    <div id="invNotFound" class="inv-notfound">
                        Producto no encontrado
                    </div>
                </div>

                <a href="admin_nuevo_producto.php" class="btn-add-producto">Añadir producto</a>
            </div>

            <?php if ($productoId > 0 || $q !== ''): ?>
                <div class="filter-chip">
                    <?php if ($productoId > 0): ?>
                        Mostrando un producto seleccionado.
                    <?php else: ?>
                        Mostrando resultados para: <strong><?= htmlspecialchars($q) ?></strong>
                    <?php endif; ?>

                    <a class="chip-link"
                       href="admin_inventario.php<?= $categoriaActual ? '?categoria=' . urlencode($categoriaActual) : '' ?>">
                        Quitar filtro
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <section class="inventory-card">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Existencias</th>
                        <th>Editar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="4" class="inventory-empty">No hay productos con ese filtro.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                            <tr>
                                <td>
                                    <div class="prod-info">
                                        <?php if (!empty($p['imagen_url'])): ?>
                                            <img
                                                src="<?= htmlspecialchars($p['imagen_url']); ?>"
                                                alt="<?= htmlspecialchars($p['nombre']); ?>"
                                                class="prod-img"
                                            >
                                        <?php endif; ?>
                                        <div class="prod-name">
                                            <?= htmlspecialchars($p['nombre']); ?>
                                            <div class="prod-brand"><?= htmlspecialchars($p['marca']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($p['categoria']); ?></td>
                                <td><?= (int)$p['stock']; ?></td>
                                <td>
                                    <a href="admin_editar_producto.php?id=<?= (int)$p['id']; ?>" class="btn-editar">
                                        Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

    </main>

</div>

<script src="../JAVASCRIPT/admin_inventario.js"></script>
</body>
</html>


