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

// ID del producto
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: admin_inventario.php");
    exit;
}

/* ==========================================================
   1) SI VIENE POR POST, ACTUALIZAR PRODUCTO + IMÁGENES EXTRA
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre     = $_POST['nombre']     ?? '';
    $precio     = $_POST['precio']     ?? '0';
    $stock      = $_POST['stock']      ?? '0';
    $imagen_url = $_POST['imagen_url'] ?? '';
    $marca      = $_POST['marca']      ?? '';
    $categoria  = $_POST['categoria']  ?? '';

    // Convertir números
    $precio = (float)$precio;
    $stock  = (int)$stock;

    // Actualizar producto principal
    $stmt = $conn->prepare("
        UPDATE producto
        SET nombre = ?, precio = ?, stock = ?, imagen_url = ?, marca = ?, categoria = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sdisssi", $nombre, $precio, $stock, $imagen_url, $marca, $categoria, $id);
    $stmt->execute();
    $stmt->close();

    // IMÁGENES EXTRA (producto_imagen)
    // Recibimos un arreglo de URLs: imagenes_extra[]
    $imagenesExtra = $_POST['imagenes_extra'] ?? [];

    // Limpiar: quitar vacíos y espacios
    $urlsLimpias = [];
    foreach ($imagenesExtra as $url) {
        $u = trim($url);
        if ($u !== '') {
            $urlsLimpias[] = $u;
        }
    }

    // Borramos las imágenes actuales de este producto
    $stmt = $conn->prepare("DELETE FROM producto_imagen WHERE producto_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Insertamos las nuevas (si hay)
    if (count($urlsLimpias) > 0) {
        $orden = 1;
        $stmt = $conn->prepare("
            INSERT INTO producto_imagen (producto_id, url, orden)
            VALUES (?, ?, ?)
        ");
        foreach ($urlsLimpias as $urlExtra) {
            $stmt->bind_param("isi", $id, $urlExtra, $orden);
            $stmt->execute();
            $orden++;
        }
        $stmt->close();
    }

    // Regresar al inventario
    header("Location: admin_inventario.php");
    exit;
}

/* ==========================================================
   2) SI VIENE POR GET, LEER DATOS ACTUALES
   ========================================================== */
$stmt = $conn->prepare("
    SELECT id, nombre, precio, stock, imagen_url, marca, categoria
    FROM producto
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$producto = $res->fetch_assoc();
$stmt->close();

if (!$producto) {
    echo "Producto no encontrado.";
    exit;
}

// Leer imágenes extra de producto_imagen
$stmt = $conn->prepare("
    SELECT url
    FROM producto_imagen
    WHERE producto_id = ?
    ORDER BY orden, id
");
$stmt->bind_param("i", $id);
$stmt->execute();
$resImg = $stmt->get_result();
$imagenesExtra = $resImg->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar producto - Mi tiendita</title>
    <link rel="stylesheet" href="../CSS/admin.css">
    <link rel="stylesheet" href="../CSS/admin_editar_producto.css">
</head>
<body>

<div class="page">

    <!-- BARRA AZUL SUPERIOR -->
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

    <!-- BOTÓN CERRAR SESIÓN -->
    <div class="logout-container">
        <a href="logout.php" class="logout-button">Cerrar sesión</a>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="admin-main">

        <section class="edit-header">
            <h1>Editar producto</h1>
            <a href="admin_inventario.php" class="btn-volver">← Volver al inventario</a>
        </section>

        <section class="edit-card">
            <form method="post" class="edit-form">

                <div class="edit-row">
                    <div class="edit-col">
                        <label>ID</label>
                        <input type="text" value="<?php echo (int)$producto['id']; ?>" readonly>
                    </div>
                    <div class="edit-col">
                        <label>Nombre</label>
                        <input
                            type="text"
                            name="nombre"
                            required
                            value="<?php echo htmlspecialchars($producto['nombre']); ?>"
                        >
                    </div>
                </div>

                <div class="edit-row">
                    <div class="edit-col">
                        <label>Precio</label>
                        <input
                            type="number"
                            name="precio"
                            step="0.01"
                            min="0"
                            required
                            value="<?php echo htmlspecialchars($producto['precio']); ?>"
                        >
                    </div>
                    <div class="edit-col">
                        <label>Stock</label>
                        <input
                            type="number"
                            name="stock"
                            min="0"
                            required
                            value="<?php echo htmlspecialchars($producto['stock']); ?>"
                        >
                    </div>
                </div>

                <div class="edit-row">
                    <div class="edit-col">
                        <label>Marca</label>
                        <input
                            type="text"
                            name="marca"
                            value="<?php echo htmlspecialchars($producto['marca']); ?>"
                        >
                    </div>
                    <div class="edit-col">
                        <label>Categoría</label>
                        <input
                            type="text"
                            name="categoria"
                            value="<?php echo htmlspecialchars($producto['categoria']); ?>"
                        >
                    </div>
                </div>

                <!-- IMAGEN PRINCIPAL -->
                <div class="edit-row">
                    <div class="edit-col full-width">
                        <label>URL de la imagen principal</label>
                        <input
                            type="text"
                            name="imagen_url"
                            value="<?php echo htmlspecialchars($producto['imagen_url']); ?>"
                        >
                        <?php if (!empty($producto['imagen_url'])): ?>
                            <div class="edit-preview">
                                <span>Vista previa:</span>
                                <img
                                    src="<?php echo htmlspecialchars($producto['imagen_url']); ?>"
                                    alt="Vista previa"
                                >
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- IMÁGENES EXTRA (N veces) -->
                <div class="edit-row">
                    <div class="edit-col full-width">
                        <label>Imágenes adicionales del producto</label>
                        <p class="help-text">
                            Estas imágenes se mostrarán como galería / carrusel en la ficha del producto.
                        </p>

                        <div id="extraImagesContainer">
                            <?php if (count($imagenesExtra) > 0): ?>
                                <?php foreach ($imagenesExtra as $img): ?>
                                    <div class="extra-image-row">
                                        <input
                                            type="text"
                                            name="imagenes_extra[]"
                                            placeholder="URL de imagen adicional"
                                            value="<?php echo htmlspecialchars($img['url']); ?>"
                                        >
                                        <button
                                            type="button"
                                            class="btn-eliminar-imagen"
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Al menos un campo vacío por defecto -->
                                <div class="extra-image-row">
                                    <input
                                        type="text"
                                        name="imagenes_extra[]"
                                        placeholder="URL de imagen adicional"
                                        value=""
                                    >
                                    <button
                                        type="button"
                                        class="btn-eliminar-imagen"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="btn-agregar-imagen" id="btnAgregarImagen">
                            + Insertar otra imagen
                        </button>
                    </div>
                </div>

                <div class="edit-actions">
                    <button type="submit" class="btn-guardar">Guardar cambios</button>
                </div>

            </form>
        </section>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const contenedor = document.getElementById('extraImagesContainer');
    const btnAgregar = document.getElementById('btnAgregarImagen');

    // Agregar nueva fila de imagen extra
    if (btnAgregar && contenedor) {
        btnAgregar.addEventListener('click', () => {
            const div = document.createElement('div');
            div.className = 'extra-image-row';

            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'imagenes_extra[]';
            input.placeholder = 'URL de imagen adicional';

            const btnDel = document.createElement('button');
            btnDel.type = 'button';
            btnDel.className = 'btn-eliminar-imagen';
            btnDel.textContent = 'Eliminar';

            div.appendChild(input);
            div.appendChild(btnDel);
            contenedor.appendChild(div);
        });
    }

    // Eliminar fila al hacer clic en "Eliminar"
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-eliminar-imagen')) {
            const row = e.target.closest('.extra-image-row');
            if (row) {
                row.remove();
            }
        }
    });
});
</script>

</body>
</html>


