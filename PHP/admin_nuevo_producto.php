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

/* ==========================================================
   1) SI VIENE POR POST, INSERTAR PRODUCTO + IMÁGENES EXTRA
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

    // Insertar producto principal
    $stmt = $conn->prepare("
        INSERT INTO producto (nombre, precio, stock, imagen_url, marca, categoria)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sdisss", $nombre, $precio, $stock, $imagen_url, $marca, $categoria);
    $stmt->execute();
    $nuevoId = $stmt->insert_id;
    $stmt->close();

    // IMÁGENES EXTRA (producto_imagen)
    $imagenesExtra = $_POST['imagenes_extra'] ?? [];

    $urlsLimpias = [];
    foreach ($imagenesExtra as $url) {
        $u = trim($url);
        if ($u !== '') {
            $urlsLimpias[] = $u;
        }
    }

    if (count($urlsLimpias) > 0) {
        $orden = 1;
        $stmt = $conn->prepare("
            INSERT INTO producto_imagen (producto_id, url, orden)
            VALUES (?, ?, ?)
        ");
        foreach ($urlsLimpias as $urlExtra) {
            $stmt->bind_param("isi", $nuevoId, $urlExtra, $orden);
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
   2) SI VIENE POR GET, MOSTRAR FORM VACÍO
   ========================================================== */
$producto = [
    'id'         => '',
    'nombre'     => '',
    'precio'     => '',
    'stock'      => '',
    'imagen_url' => '',
    'marca'      => '',
    'categoria'  => ''
];

$imagenesExtra = []; // vacío por defecto
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo producto - Mi tiendita</title>
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
            <h1>Nuevo producto</h1>
            <a href="admin_inventario.php" class="btn-volver">← Volver al inventario</a>
        </section>

        <section class="edit-card">
            <form method="post" class="edit-form">

                <div class="edit-row">
                    <div class="edit-col">
                        <label>ID</label>
                        <input type="text" value="(se asigna al guardar)" readonly>
                    </div>
                    <div class="edit-col">
                        <label>Nombre</label>
                        <input
                            type="text"
                            name="nombre"
                            required
                            value=""
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
                            value=""
                        >
                    </div>
                    <div class="edit-col">
                        <label>Stock</label>
                        <input
                            type="number"
                            name="stock"
                            min="0"
                            required
                            value=""
                        >
                    </div>
                </div>

                <div class="edit-row">
                    <div class="edit-col">
                        <label>Marca</label>
                        <input
                            type="text"
                            name="marca"
                            value=""
                        >
                    </div>
                    <div class="edit-col">
                        <label>Categoría</label>
                        <input
                            type="text"
                            name="categoria"
                            value=""
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
                            value=""
                        >
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
                            <!-- Un campo vacío por defecto -->
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
                        </div>

                        <button type="button" class="btn-agregar-imagen" id="btnAgregarImagen">
                            + Insertar otra imagen
                        </button>
                    </div>
                </div>

                <div class="edit-actions">
                    <button type="submit" class="btn-guardar">Guardar producto</button>
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
