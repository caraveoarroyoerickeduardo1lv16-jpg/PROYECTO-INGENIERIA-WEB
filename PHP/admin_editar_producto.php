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

// ID del producto (NO se muestra, solo se usa internamente)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: admin_inventario.php");
    exit;
}

/* =========================
   PROCESAR POST
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---- ELIMINAR PRODUCTO ---- */
    if (isset($_POST['eliminar_producto'])) {
        $idEliminar = (int)$_POST['eliminar_producto'];

        if ($idEliminar > 0) {
            $stmt = $conn->prepare("DELETE FROM producto_imagen WHERE producto_id = ?");
            $stmt->bind_param("i", $idEliminar);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM resena_producto WHERE producto_id = ?");
            $stmt->bind_param("i", $idEliminar);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM pedido_detalle WHERE producto_id = ?");
            $stmt->bind_param("i", $idEliminar);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM carrito_detalle WHERE producto_id = ?");
            $stmt->bind_param("i", $idEliminar);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM producto WHERE id = ?");
            $stmt->bind_param("i", $idEliminar);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: admin_inventario.php?eliminado=1");
        exit;
    }

    /* ---- ACTUALIZAR PRODUCTO ---- */
    $nombre     = $_POST['nombre']     ?? '';
    $precio     = (float)($_POST['precio'] ?? 0);
    $stock      = (int)($_POST['stock'] ?? 0);
    $imagen_url = $_POST['imagen_url'] ?? '';
    $marca      = $_POST['marca']      ?? '';
    $categoria  = $_POST['categoria']  ?? '';

    $stmt = $conn->prepare("
        UPDATE producto
        SET nombre = ?, precio = ?, stock = ?, imagen_url = ?, marca = ?, categoria = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sdisssi", $nombre, $precio, $stock, $imagen_url, $marca, $categoria, $id);
    $stmt->execute();
    $stmt->close();

    /* ---- IMÁGENES EXTRA ---- */
    $imagenesExtra = $_POST['imagenes_extra'] ?? [];

    $urlsLimpias = [];
    foreach ($imagenesExtra as $url) {
        $u = trim($url);
        if ($u !== '') $urlsLimpias[] = $u;
    }

    $stmt = $conn->prepare("DELETE FROM producto_imagen WHERE producto_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

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

    header("Location: admin_inventario.php");
    exit;
}

/* =========================
   CARGAR PRODUCTO
   ========================= */
$stmt = $conn->prepare("
    SELECT id, nombre, precio, stock, imagen_url, marca, categoria
    FROM producto
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$producto) {
    echo "Producto no encontrado.";
    exit;
}

/* IMÁGENES EXTRA */
$stmt = $conn->prepare("
    SELECT url
    FROM producto_imagen
    WHERE producto_id = ?
    ORDER BY orden, id
");
$stmt->bind_param("i", $id);
$stmt->execute();
$imagenesExtra = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

    <header class="topbar">
        <div class="topbar-inner">
            <a href="admin.php" class="logo-link">
                <div class="logo-icon"><span class="logo-star">*</span></div>
                <span class="logo-text">Mi tiendita</span>
            </a>
        </div>
    </header>

    <div class="logout-container">
        <a href="logout.php" class="logout-button">Cerrar sesión</a>
    </div>

    <main class="admin-main">

        <section class="edit-header">
            <h1>Editar producto</h1>
            <a href="admin_inventario.php" class="btn-volver">← Volver al inventario</a>
        </section>

        <section class="edit-card">
            <form method="post" class="edit-form">

                <div class="edit-row">
                    <div class="edit-col full-width">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required
                               value="<?php echo htmlspecialchars($producto['nombre']); ?>">
                    </div>
                </div>

                <div class="edit-row">
                    <div class="edit-col">
                        <label>Precio</label>
                        <input type="number" name="precio" step="0.01" min="0" required
                               value="<?php echo htmlspecialchars($producto['precio']); ?>">
                    </div>
                    <div class="edit-col">
                        <label>Stock</label>
                        <input type="number" name="stock" min="0" required
                               value="<?php echo htmlspecialchars($producto['stock']); ?>">
                    </div>
                </div>

                <div class="edit-row">
                    <div class="edit-col">
                        <label>Marca</label>
                        <input type="text" name="marca"
                               value="<?php echo htmlspecialchars($producto['marca']); ?>">
                    </div>
                    <div class="edit-col">
                        <label>Categoría</label>
                        <input type="text" name="categoria"
                               value="<?php echo htmlspecialchars($producto['categoria']); ?>">
                    </div>
                </div>

                <div class="edit-row">
                    <div class="edit-col full-width">
                        <label>URL de la imagen principal</label>
                        <input type="text" name="imagen_url"
                               value="<?php echo htmlspecialchars($producto['imagen_url']); ?>">
                        <?php if ($producto['imagen_url']): ?>
                            <div class="edit-preview">
                                <img src="<?php echo htmlspecialchars($producto['imagen_url']); ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="edit-row">
                    <div class="edit-col full-width">
                        <label>Imágenes adicionales</label>

                        <div id="extraImagesContainer">
                            <?php foreach ($imagenesExtra as $img): ?>
                                <div class="extra-image-row">
                                    <input type="text" name="imagenes_extra[]"
                                           value="<?php echo htmlspecialchars($img['url']); ?>">
                                    <button type="button" class="btn-eliminar-imagen">Eliminar</button>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($imagenesExtra) === 0): ?>
                                <div class="extra-image-row">
                                    <input type="text" name="imagenes_extra[]" placeholder="URL imagen extra">
                                    <button type="button" class="btn-eliminar-imagen">Eliminar</button>
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
                    <button type="button" class="btn-eliminar"
                            onclick="confirmarEliminar(<?php echo (int)$producto['id']; ?>)">
                        Eliminar producto
                    </button>
                </div>

            </form>
        </section>

    </main>
</div>

<script>
function confirmarEliminar(id) {
    if (confirm("¿Seguro que quieres eliminar este producto?")) {
        const f = document.createElement("form");
        f.method = "POST";
        const i = document.createElement("input");
        i.type = "hidden";
        i.name = "eliminar_producto";
        i.value = id;
        f.appendChild(i);
        document.body.appendChild(f);
        f.submit();
    }
}

document.addEventListener("click", e => {
    if (e.target.classList.contains("btn-eliminar-imagen")) {
        e.target.closest(".extra-image-row").remove();
    }
});

document.getElementById("btnAgregarImagen")?.addEventListener("click", () => {
    const div = document.createElement("div");
    div.className = "extra-image-row";
    div.innerHTML = `
        <input type="text" name="imagenes_extra[]" placeholder="URL imagen extra">
        <button type="button" class="btn-eliminar-imagen">Eliminar</button>
    `;
    document.getElementById("extraImagesContainer").appendChild(div);
});
</script>

</body>
</html>

