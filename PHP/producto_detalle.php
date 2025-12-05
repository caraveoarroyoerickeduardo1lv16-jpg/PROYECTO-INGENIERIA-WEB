<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

$sessionId    = session_id();
$estaLogueado = !empty($_SESSION['user_id']);
$usuario_id   = $estaLogueado ? (int)$_SESSION['user_id'] : null;

$errorResena = "";

/* ==========================================================
   0) MANEJAR ENVÍO DE RESEÑA (POST)
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'nueva_resena') {
    $productoPostId = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    $calif          = isset($_POST['calificacion']) ? (int)$_POST['calificacion'] : 0;
    $comentario     = trim($_POST['comentario'] ?? '');

    if ($productoPostId <= 0) {
        $errorResena = "Producto inválido.";
    } elseif ($calif < 1 || $calif > 5) {
        $errorResena = "Selecciona una calificación de 1 a 5 estrellas.";
    } elseif ($comentario === '') {
        $errorResena = "Escribe un comentario sobre el producto.";
    } else {
        // IMPORTANTE: para permitir reseñas de invitados,
        // en la BD: resena_producto.usuario_id debe permitir NULL.
        if ($estaLogueado) {
            $stmt = $conn->prepare("
                INSERT INTO resena_producto (producto_id, usuario_id, calificacion, comentario)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiis", $productoPostId, $usuario_id, $calif, $comentario);
        } else {
            // invitado (usuario_id = NULL)
            $stmt = $conn->prepare("
                INSERT INTO resena_producto (producto_id, calificacion, comentario)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $productoPostId, $calif, $comentario);
        }
        $stmt->execute();
        $stmt->close();

        // Recalcular promedio y número de reseñas
        $stmt = $conn->prepare("
            SELECT AVG(calificacion) AS prom, COUNT(*) AS n
            FROM resena_producto
            WHERE producto_id = ?
        ");
        $stmt->bind_param("i", $productoPostId);
        $stmt->execute();
        $resStats = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $prom = (float)($resStats['prom'] ?? 0);
        $n    = (int)($resStats['n'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE producto
            SET calificacion = ?, num_resenas = ?
            WHERE id = ?
        ");
        $stmt->bind_param("dii", $prom, $n, $productoPostId);
        $stmt->execute();
        $stmt->close();

        // Redirigir con GET para evitar reenvío del formulario
        header("Location: producto_detalle.php?id=" . $productoPostId . "&resena_ok=1");
        exit;
    }
}

/* ==========================================================
   1) OBTENER ID DE PRODUCTO
   ========================================================== */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

/* ==========================================================
   2) LEER CATEGORÍAS PARA EL MENÚ
   ========================================================== */
$categorias = [];
$resCat = $conn->query("SELECT DISTINCT categoria FROM producto ORDER BY categoria");
while ($row = $resCat->fetch_assoc()) {
    if (trim($row['categoria']) !== '') {
        $categorias[] = $row['categoria'];
    }
}

/* ==========================================================
   3) LEER CARRITO ACTUAL (para header)
   ========================================================== */
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
$carrito = $stmt->get_result()->fetch_assoc();
$stmt->close();

$carrito_id    = $carrito['id']   ?? null;
$total_carrito = (float)($carrito['total'] ?? 0.0);
$total_items   = 0;

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
        $total_items += (int)$row['cantidad'];
    }
    $stmt->close();
}

/* ==========================================================
   4) LEER DATOS DEL PRODUCTO
   ========================================================== */
$stmt = $conn->prepare("
    SELECT id, nombre, precio, stock, imagen_url, marca, categoria,
           calificacion, num_resenas
    FROM producto
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$producto) {
    header("Location: index.php");
    exit;
}

/* Cantidad en carrito de ESTE producto */
$cantidadEnCarrito = 0;
if ($carrito_id) {
    $stmt = $conn->prepare("
        SELECT cantidad
        FROM carrito_detalle
        WHERE carrito_id = ? AND producto_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $carrito_id, $id);
    $stmt->execute();
    $rowCant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($rowCant) {
        $cantidadEnCarrito = (int)$rowCant['cantidad'];
    }
}
$estaEnCarrito = $cantidadEnCarrito > 0;

/* ==========================================================
   5) LEER IMÁGENES DEL PRODUCTO (CARRUSEL)
   ========================================================== */
$stmt = $conn->prepare("
    SELECT url
    FROM producto_imagen
    WHERE producto_id = ?
    ORDER BY orden, id
");
$stmt->bind_param("i", $id);
$stmt->execute();
$resImgs  = $stmt->get_result();
$imagenes = $resImgs->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (count($imagenes) === 0 && !empty($producto['imagen_url'])) {
    $imagenes[] = ['url' => $producto['imagen_url']];
}

/* ==========================================================
   6) LEER RESEÑAS DEL PRODUCTO
   ========================================================== */
$stmt = $conn->prepare("
    SELECT r.calificacion, r.comentario, r.creado_en,
           u.usuario
    FROM resena_producto r
    LEFT JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.producto_id = ?
    ORDER BY r.creado_en DESC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$resResenas = $stmt->get_result();
$resenas = $resResenas->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$resenaOk = isset($_GET['resena_ok']) && $_GET['resena_ok'] == 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($producto['nombre']); ?> - Mi Tiendita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/index.css">
    <link rel="stylesheet" href="../CSS/producto_detalle.css">
</head>
<body>

<!-- ============ HEADER (igual que index) ============ -->
<header class="header">
    <div class="header-left">
        <div class="logo">
            <a href="index.php" class="logo-link">
                <div class="logo-icon">*</div>
            </a>
            <h1>Mi Tiendita</h1>
        </div>

        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="¿Cómo quieres tus artículos?" autocomplete="off">
            <div id="searchSuggestions" class="search-suggestions"></div>
        </div>
    </div>

    <div class="header-right">
        <?php if ($estaLogueado): ?>
            <span class="header-user"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
            <a href="../PHP/logout.php" class="header-link">Cerrar sesión</a>
        <?php else: ?>
            <a href="../PHP/login.php" class="header-link">Iniciar sesión</a>
        <?php endif; ?>

        <span id="cartTotalItems" class="header-items">
            <?php echo $total_items; ?> artículo<?php echo $total_items === 1 ? '' : 's'; ?>
        </span>

        <span id="cartTotalPrice" class="header-price">
            $<?php echo number_format($total_carrito, 2); ?>
        </span>

        <a href="../PHP/carrito.php" class="header-cart-link">
            <span class="header-cart">🛒</span>
        </a>
    </div>
</header>

<!-- ============ NAV CATEGORÍAS ============ -->
<nav class="nav-categorias">
    <a href="index.php" class="nav-item">Inicio</a>
    <?php foreach ($categorias as $cat): ?>
        <a href="index.php?categoria=<?php echo urlencode($cat); ?>" class="nav-item">
            <?php echo htmlspecialchars($cat); ?>
        </a>
    <?php endforeach; ?>
</nav>

<!-- ============ CONTENIDO DETALLE ============ -->
<main class="detalle-container">

    <!-- Columna izquierda: galería -->
    <section class="detalle-galeria">
        <div class="detalle-thumbs">
            <?php foreach ($imagenes as $idx => $img): ?>
                <img
                    class="thumb-img <?php echo $idx === 0 ? 'activa' : ''; ?>"
                    src="<?php echo htmlspecialchars($img['url']); ?>"
                    data-large="<?php echo htmlspecialchars($img['url']); ?>"
                    alt="Miniatura"
                >
            <?php endforeach; ?>
        </div>

        <div class="detalle-imagen-principal">
            <img
                id="mainImage"
                src="<?php echo htmlspecialchars($imagenes[0]['url']); ?>"
                alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
            >
        </div>
    </section>

    <!-- Columna derecha: info, carrito, reseñas -->
    <section class="detalle-info">

        <div class="detalle-marca">
            <?php echo htmlspecialchars($producto['marca']); ?>
        </div>

        <h1 class="detalle-titulo">
            <?php echo htmlspecialchars($producto['nombre']); ?>
        </h1>

        <div class="detalle-rating">
            <span class="rating-num">
                <?php echo number_format($producto['calificacion'], 1); ?>
            </span>
            <span class="rating-stars">
                <?php
                $rating = (float)$producto['calificacion'];
                for ($i = 1; $i <= 5; $i++) {
                    echo ($rating >= $i) ? "★" : "☆";
                }
                ?>
            </span>
            <span class="rating-count">
                (<?php echo (int)$producto['num_resenas']; ?> reseñas)
            </span>
        </div>

        <div class="detalle-precio">
            $<?php echo number_format($producto['precio'], 2); ?>
        </div>

        <div class="detalle-stock">
            Disponible: <?php echo (int)$producto['stock']; ?> pieza(s)
        </div>

        <!-- Botones tipo landing: agregar / + / - -->
        <div
            class="detalle-actions"
            data-id="<?php echo $producto['id']; ?>"
            data-logged="<?php echo $estaLogueado ? '1' : '0'; ?>"
        >
            <?php if (!$estaLogueado): ?>
                <!-- NO logueado: botón que manda a login -->
                <a href="../PHP/login.php" class="detalle-btn-agregar detalle-btn-login">
                    Agregar al carrito
                </a>
            <?php else: ?>
                <!-- Logueado: botones reales de carrito -->
                <button
                    class="detalle-btn-agregar"
                    style="<?php echo $estaEnCarrito ? 'display:none;' : ''; ?>"
                >
                    Agregar al carrito
                </button>

                <div class="detalle-cantidad-control <?php echo $estaEnCarrito ? '' : 'oculto'; ?>">
                    <button class="detalle-btn-menos">−</button>
                    <span class="detalle-cantidad"><?php echo $estaEnCarrito ? $cantidadEnCarrito : 0; ?></span>
                    <button class="detalle-btn-mas">+</button>
                </div>
            <?php endif; ?>
        </div>

        <div class="detalle-extra-info">
            Precio y disponibilidad sujetos a cambios. Imágenes ilustrativas.
        </div>

        <!-- Mensajes de reseña -->
        <?php if ($resenaOk): ?>
            <div class="detalle-mensaje-ok">
                ¡Gracias por tu reseña!
            </div>
        <?php endif; ?>

        <?php if ($errorResena !== ''): ?>
            <div class="detalle-mensaje-error">
                <?php echo htmlspecialchars($errorResena); ?>
            </div>
        <?php endif; ?>

        <!-- ======== SECCIÓN DE RESEÑAS ======== -->
        <section class="detalle-resenas">
            <h2>Opiniones del producto</h2>

            <!-- Formulario para nueva reseña -->
            <form method="post" class="resena-form">
                <input type="hidden" name="accion" value="nueva_resena">
                <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                <input type="hidden" name="calificacion" id="inputCalificacion" value="0">

                <label>Tu calificación:</label>
                <div class="resena-estrellas" id="resenaEstrellas">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="resena-estrella" data-value="<?php echo $i; ?>">★</span>
                    <?php endfor; ?>
                </div>

                <label for="comentario">Tu reseña:</label>
                <textarea
                    name="comentario"
                    id="comentario"
                    placeholder="Cuéntanos qué te pareció el producto..."
                ></textarea>

                <button type="submit">Enviar reseña</button>
            </form>

            <!-- Lista de reseñas existentes -->
            <div class="lista-resenas">
                <?php if (count($resenas) === 0): ?>
                    <p>No hay reseñas todavía. ¡Sé el primero en opinar!</p>
                <?php else: ?>
                    <?php foreach ($resenas as $r): ?>
                        <article class="resena-item">
                            <div class="resena-header">
                                <span class="resena-usuario">
                                    <?php echo htmlspecialchars($r['usuario'] ?? 'Anónimo'); ?>
                                </span>
                                <span class="resena-fecha">
                                    <?php echo htmlspecialchars($r['creado_en']); ?>
                                </span>
                            </div>
                            <div class="resena-rating">
                                <?php
                                $rc = (int)$r['calificacion'];
                                for ($i = 1; $i <= 5; $i++) {
                                    echo ($i <= $rc) ? "★" : "☆";
                                }
                                ?>
                            </div>
                            <div class="resena-comentario">
                                <?php echo nl2br(htmlspecialchars($r['comentario'])); ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </section>

</main>

<!-- ============ SCRIPTS ============ -->
<script>
// ===== GALERÍA DE IMÁGENES =====
const thumbs   = document.querySelectorAll('.thumb-img');
const mainImg  = document.getElementById('mainImage');
let currentIdx = 0;

thumbs.forEach((img, index) => {
    img.addEventListener('click', () => {
        currentIdx = index;
        mainImg.src = img.dataset.large;
        document.querySelectorAll('.thumb-img').forEach(t => t.classList.remove('activa'));
        img.classList.add('activa');
    });
});

// Pase automático cada 4s si hay varias imágenes
if (thumbs.length > 1) {
    setInterval(() => {
        currentIdx = (currentIdx + 1) % thumbs.length;
        const img = thumbs[currentIdx];
        mainImg.src = img.dataset.large;
        document.querySelectorAll('.thumb-img').forEach(t => t.classList.remove('activa'));
        img.classList.add('activa');
    }, 4000);
}

// ===== CARRITO EN DETALLE =====
async function actualizarCarrito(productoId, accion) {
    const formData = new FormData();
    formData.append("producto_id", productoId);
    formData.append("accion", accion);

    const response = await fetch("../PHP/carrito_actualizar.php", {
        method: "POST",
        body: formData
    });
    return await response.json();
}

document.addEventListener("DOMContentLoaded", () => {
    const acciones = document.querySelector(".detalle-actions");
    if (!acciones) return;

    const estaLogueado = acciones.dataset.logged === "1";
    // Si NO está logueado, no montamos lógica de carrito (el botón ya es enlace a login)
    if (!estaLogueado) {
        return;
    }

    const productoId    = parseInt(acciones.dataset.id, 10);
    const btnAgregar    = acciones.querySelector(".detalle-btn-agregar");
    const contCant      = acciones.querySelector(".detalle-cantidad-control");
    const btnMas        = acciones.querySelector(".detalle-btn-mas");
    const btnMenos      = acciones.querySelector(".detalle-btn-menos");
    const spanCantidad  = acciones.querySelector(".detalle-cantidad");

    const cartTotalItems = document.getElementById("cartTotalItems");
    const cartTotalPrice = document.getElementById("cartTotalPrice");

    function actualizarHeader(items, total) {
        if (!cartTotalItems || !cartTotalPrice) return;
        cartTotalItems.textContent =
            items + " artículo" + (items !== 1 ? "s" : "");
        cartTotalPrice.textContent = "$" + total.toFixed(2);
    }

    let cantidadLocal = parseInt(spanCantidad.textContent, 10) || 0;

    if (btnAgregar) {
        btnAgregar.addEventListener("click", async () => {
            const data = await actualizarCarrito(productoId, "add");
            if (!data.success) {
                alert(data.message || "Error al agregar al carrito");
                return;
            }
            cantidadLocal = data.cantidad;
            spanCantidad.textContent = cantidadLocal;
            btnAgregar.style.display = "none";
            contCant.classList.remove("oculto");
            actualizarHeader(data.total_items, data.total_carrito);
        });
    }

    if (btnMas) {
        btnMas.addEventListener("click", async () => {
            const data = await actualizarCarrito(productoId, "add");
            if (!data.success) {
                alert(data.message || "Error al agregar");
                return;
            }
            cantidadLocal = data.cantidad;
            spanCantidad.textContent = cantidadLocal;
            actualizarHeader(data.total_items, data.total_carrito);
        });
    }

    if (btnMenos) {
        btnMenos.addEventListener("click", async () => {
            const data = await actualizarCarrito(productoId, "remove");
            if (!data.success) {
                alert(data.message || "Error al quitar");
                return;
            }
            cantidadLocal = data.cantidad;
            if (cantidadLocal <= 0) {
                contCant.classList.add("oculto");
                btnAgregar.style.display = "inline-block";
                spanCantidad.textContent = "0";
            } else {
                spanCantidad.textContent = cantidadLocal;
            }
            actualizarHeader(data.total_items, data.total_carrito);
        });
    }

    // ===== ESTRELLAS DINÁMICAS PARA RESEÑA (izquierda → derecha) =====
    const estrellasContainer = document.getElementById("resenaEstrellas");
    const inputCalif         = document.getElementById("inputCalificacion");

    if (estrellasContainer && inputCalif) {
        const estrellas = estrellasContainer.querySelectorAll(".resena-estrella");
        let califActual = 0;

        function pintarEstrellas(n) {
            estrellas.forEach(star => {
                const val = parseInt(star.dataset.value, 10);
                if (val <= n) {
                    star.classList.add("activa");
                } else {
                    star.classList.remove("activa");
                }
            });
        }

        estrellas.forEach(star => {
            const val = parseInt(star.dataset.value, 10);

            star.addEventListener("click", () => {
                califActual = val;
                inputCalif.value = val;
                pintarEstrellas(val);
            });

            star.addEventListener("mouseenter", () => {
                pintarEstrellas(val);
            });

            star.addEventListener("mouseleave", () => {
                pintarEstrellas(califActual);
            });
        });
    }
});
</script>

</body>
</html>



