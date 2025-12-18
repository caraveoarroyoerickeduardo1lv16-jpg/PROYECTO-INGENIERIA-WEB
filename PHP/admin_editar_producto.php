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

function esUrlImagen($url) {
    $url = trim((string)$url);
    if ($url === '') return true; // vacío permitido

    // Permitir data URL de imagen (opcional)
    if (preg_match('~^data:image/(png|jpe?g|gif|webp|bmp|svg\+xml);base64,~i', $url)) {
        return true;
    }

    // Validar por extensión en la ruta (ignorar querystring)
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    return (bool)preg_match('~\.(png|jpe?g|gif|webp|bmp|svg)$~i', $path);
}

$flashImgError = $_SESSION['flash_error_img'] ?? '';
unset($_SESSION['flash_error_img']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---- INVALIDAR PRODUCTO (ANTES BORRABA) ---- */
    if (isset($_POST['eliminar_producto'])) {
        $idEliminar = (int)$_POST['eliminar_producto'];

        if ($idEliminar > 0) {
            $stmt = $conn->prepare("UPDATE producto SET estatus = 0 WHERE id = ?");
            $stmt->bind_param("i", $idEliminar);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: admin_inventario.php?invalido=1");
        exit;
    }

    /* ---- ACTUALIZAR PRODUCTO ---- */
    $nombre     = $_POST['nombre']     ?? '';
    $precio     = (float)($_POST['precio'] ?? 0);
    $stock      = (int)($_POST['stock'] ?? 0);
    $imagen_url = $_POST['imagen_url'] ?? '';
    $marca      = $_POST['marca']      ?? '';
    $categoria  = $_POST['categoria']  ?? '';

    // Validar imagen principal
    if (!esUrlImagen($imagen_url)) {
        $_SESSION['flash_error_img'] = "Esta imagen no es compatible. Solo se permiten: png, jpg, jpeg, gif, webp, bmp, svg.";
        header("Location: admin_editar_producto.php?id=" . (int)$id);
        exit;
    }

    // Validar imágenes extra
    $imagenesExtra = $_POST['imagenes_extra'] ?? [];
    $urlsLimpias = [];

    foreach ($imagenesExtra as $url) {
        $u = trim((string)$url);
        if ($u === '') continue;

        if (!esUrlImagen($u)) {
            $_SESSION['flash_error_img'] = "Una o más URLs no son imágenes compatibles. Solo: png, jpg, jpeg, gif, webp, bmp, svg.";
            header("Location: admin_editar_producto.php?id=" . (int)$id);
            exit;
        }

        $urlsLimpias[] = $u;
    }

    // Guardar producto (OJO: no tocamos estatus aquí, solo datos)
    $stmt = $conn->prepare("
        UPDATE producto
        SET nombre = ?, precio = ?, stock = ?, imagen_url = ?, marca = ?, categoria = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sdisssi", $nombre, $precio, $stock, $imagen_url, $marca, $categoria, $id);
    $stmt->execute();
    $stmt->close();

    /* ---- IMÁGENES EXTRA ---- */
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

    header("Location: admin_inventario.php?editado=1");
    exit;
}

// Cargar producto (incluye estatus)
$stmt = $conn->prepare("
    SELECT id, nombre, precio, stock, imagen_url, marca, categoria, estatus
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

<?php if ($flashImgError !== ''): ?>
  <div id="toast" class="toast toast-error">
    <?= htmlspecialchars($flashImgError) ?>
  </div>
<?php endif; ?>

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

        <?php if ((int)$producto['estatus'] === 0): ?>
            <div class="alert-error" style="margin-bottom: 12px;">
                Este producto está <strong>INVALIDADO</strong> (estatus = 0). No debería mostrarse en la tienda.
            </div>
        <?php endif; ?>

        <section class="edit-card">
            <form method="post" class="edit-form" id="editForm">

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
                        <input type="text" name="imagen_url" id="imgPrincipal"
                               value="<?php echo htmlspecialchars($producto['imagen_url']); ?>">
                        <?php if ($producto['imagen_url']): ?>
                            <div class="edit-preview">
                                <img
                                  src="<?php echo htmlspecialchars($producto['imagen_url']); ?>"
                                  onerror="this.style.display='none'"
                                >
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
                                    <input type="text" name="imagenes_extra[]" class="imgExtra"
                                           value="<?php echo htmlspecialchars($img['url']); ?>">
                                    <button type="button" class="btn-eliminar-imagen">Eliminar</button>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($imagenesExtra) === 0): ?>
                                <div class="extra-image-row">
                                    <input type="text" name="imagenes_extra[]" class="imgExtra" placeholder="URL imagen extra">
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

                    <!-- Este botón YA NO BORRA: invalida (estatus = 0) -->
                    <button type="button" class="btn-eliminar"
                            onclick="confirmarEliminar(<?php echo (int)$producto['id']; ?>)">
                        Invalidar producto
                    </button>
                </div>

            </form>
        </section>

    </main>
</div>

<script>
function confirmarEliminar(id) {
    if (confirm("¿Seguro que quieres INVALIDAR este producto?\nYa no se mostrará en la tienda, pero se conserva el historial de ventas.")) {
        const f = document.createElement("form");
        f.method = "POST";
        const i = document.createElement("input");
        i.type = "hidden";
        i.name = "eliminar_producto"; // se queda igual para no cambiar tu backend
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
        <input type="text" name="imagenes_extra[]" class="imgExtra" placeholder="URL imagen extra">
        <button type="button" class="btn-eliminar-imagen">Eliminar</button>
    `;
    document.getElementById("extraImagesContainer").appendChild(div);
});

/* ========= TOAST AUTO-OCULTAR ========= */
const toast = document.getElementById("toast");
if (toast) setTimeout(() => toast.remove(), 3500);

/* ========= VALIDACIÓN FRONT: solo imágenes ========= */
function esUrlImagen(url){
  url = (url || "").trim();
  if (url === "") return true;

  if (/^data:image\/(png|jpe?g|gif|webp|bmp|svg\+xml);base64,/i.test(url)) return true;

  try{
    const u = new URL(url, window.location.href);
    const p = (u.pathname || "").toLowerCase();
    return /\.(png|jpe?g|gif|webp|bmp|svg)$/.test(p);
  }catch(e){
    return false;
  }
}

function showToast(msg){
  let t = document.getElementById("toastLocal");
  if (!t){
    t = document.createElement("div");
    t.id = "toastLocal";
    t.className = "toast toast-error";
    document.body.appendChild(t);
  }
  t.textContent = msg;
  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(() => t.remove(), 3500);
}

function marcarInvalido(input, invalido){
  if (!input) return;
  input.classList.toggle("input-invalid", !!invalido);
}

document.getElementById("editForm")?.addEventListener("submit", (e) => {
  const principal = document.getElementById("imgPrincipal");
  const valP = principal ? principal.value : "";
  const okP = esUrlImagen(valP);
  marcarInvalido(principal, !okP);

  if (!okP){
    e.preventDefault();
    showToast("Esta imagen no es compatible. Usa: png, jpg, jpeg, gif, webp, bmp, svg.");
    return;
  }

  const extras = document.querySelectorAll('input[name="imagenes_extra[]"]');
  for (const inp of extras){
    const ok = esUrlImagen(inp.value);
    marcarInvalido(inp, !ok);
    if (!ok){
      e.preventDefault();
      showToast("Una URL no es imagen compatible. Corrígela antes de guardar.");
      return;
    }
  }
});

// Validar al salir del input (blur)
document.addEventListener("blur", (e) => {
  if (e.target && (e.target.id === "imgPrincipal" || e.target.classList.contains("imgExtra"))) {
    marcarInvalido(e.target, !esUrlImagen(e.target.value));
  }
}, true);
</script>

</body>
</html>


