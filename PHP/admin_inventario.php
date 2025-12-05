<?php
session_start();

// Solo admins pueden entrar aquí
if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'administrador') {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

// Leer categorías para el combo
$categorias = [];
$resCat = $conn->query("SELECT DISTINCT categoria FROM producto ORDER BY categoria");
while ($row = $resCat->fetch_assoc()) {
    if (trim($row['categoria']) !== '') {
        $categorias[] = $row['categoria'];
    }
}

// Filtro por categoría (opcional)
$categoriaActual = $_GET['categoria'] ?? '';

if ($categoriaActual !== '') {
    $stmt = $conn->prepare("
        SELECT id, nombre, precio, stock, imagen_url, marca, categoria
        FROM producto
        WHERE categoria = ?
        ORDER BY id
    ");
    $stmt->bind_param("s", $categoriaActual);
} else {
    $stmt = $conn->prepare("
        SELECT id, nombre, precio, stock, imagen_url, marca, categoria
        FROM producto
        ORDER BY id
    ");
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
    <link rel="stylesheet" href="../CSS/admin.css">
<link rel="stylesheet" href="../CSS/admin_inventario.css">

</head>
<body>

<div class="page">

    <!-- BARRA AZUL SUPERIOR (MISMA QUE admin.php) -->
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

    <!-- BOTÓN CERRAR SESIÓN (EL BLANCO QUE YA TENEMOS) -->
    <div class="logout-container">
        <a href="logout.php" class="logout-button">Cerrar sesión</a>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="admin-main">

        <section class="inventory-header">
            <h1>Panel de administrador</h1>
            <h2>Inventario</h2>

            <div class="inventory-top">
                <!-- Filtro por categoría -->
                <form method="get" class="inventory-filter">
                    <label for="categoria">Categoría:</label>
                    <select
                        id="categoria"
                        name="categoria"
                        onchange="this.form.submit()"
                    >
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option
                                value="<?php echo htmlspecialchars($cat); ?>"
                                <?php echo ($cat === $categoriaActual) ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <!-- Botón añadir producto (por ahora placeholder) -->
                <a href="#" class="btn-add-producto">Añadir producto</a>
            </div>
        </section>

        <!-- TARJETA CON LA TABLA DE INVENTARIO -->
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
                            <td colspan="4" class="inventory-empty">
                                No hay productos en esta categoría.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                            <tr>
                                <td>
                                    <div class="prod-info">
                                        <?php if (!empty($p['imagen_url'])): ?>
                                            <img
                                                src="<?php echo htmlspecialchars($p['imagen_url']); ?>"
                                                alt="<?php echo htmlspecialchars($p['nombre']); ?>"
                                                class="prod-img"
                                            >
                                        <?php endif; ?>
                                        <div class="prod-name">
                                            <?php echo htmlspecialchars($p['nombre']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($p['categoria']); ?></td>
                                <td><?php echo (int)$p['stock']; ?></td>
                                <td>
                                    <a
                                        href="admin_editar_producto.php?id=<?php echo (int)$p['id']; ?>"
                                        class="btn-editar"
                                    >
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

</body>
</html>
