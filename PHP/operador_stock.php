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


if (isset($_GET['ajax']) && $_GET['ajax'] === 'suggest') {
    header('Content-Type: application/json; charset=utf-8');

    $q = trim($_GET['q'] ?? '');
    $categoria = $_GET['categoria'] ?? 'todas';

    if ($q === '' || mb_strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    $like = "%{$q}%";

    if ($categoria === 'todas' || $categoria === '') {
        $stmt = $conn->prepare("
            SELECT id, nombre
            FROM producto
            WHERE nombre LIKE ?
            ORDER BY nombre
            LIMIT 8
        ");
        $stmt->bind_param("s", $like);
    } else {
        $stmt = $conn->prepare("
            SELECT id, nombre
            FROM producto
            WHERE categoria = ?
              AND nombre LIKE ?
            ORDER BY nombre
            LIMIT 8
        ");
        $stmt->bind_param("ss", $categoria, $like);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre']
        ];
    }
    $stmt->close();

    echo json_encode($out);
    exit;
}

/*  MENSAJE DE ERROR DE STOCK  */
$stockError = $_SESSION['stock_error'] ?? '';
unset($_SESSION['stock_error']);


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['producto_id'], $_POST['accion'], $_POST['cantidad'])
) {
    $producto_id   = (int)$_POST['producto_id'];
    $accion        = $_POST['accion'];

    // mantener filtros
    $categoriaPost = $_POST['categoria'] ?? 'todas';
    $qPost         = trim($_POST['q'] ?? '');
    $productoIdPost = isset($_POST['producto_id_filtro']) ? (int)$_POST['producto_id_filtro'] : 0;

    $cantidad = (int)$_POST['cantidad'];
    if ($cantidad < 1) $cantidad = 0;

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

            $stmt = $conn->prepare("SELECT stock FROM producto WHERE id = ?");
            $stmt->bind_param("i", $producto_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stockActual = $res ? (int)$res['stock'] : 0;

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

   
    $params = ['categoria' => $categoriaPost];

    if ($productoIdPost > 0) {
        $params['producto_id'] = $productoIdPost;
    } elseif ($qPost !== '') {
        $params['q'] = $qPost;
    }

    header("Location: operador_stock.php?" . http_build_query($params));
    exit;
}


$categorias = [];
$resCat = $conn->query("SELECT DISTINCT categoria FROM producto WHERE TRIM(categoria) <> '' ORDER BY categoria");
while ($rowCat = $resCat->fetch_assoc()) {
    $categorias[] = $rowCat['categoria'];
}


$categoriaFiltro = $_GET['categoria'] ?? 'todas';
$q = trim($_GET['q'] ?? '');
$productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;


if ($productoId > 0) {

    $stmt = $conn->prepare("
        SELECT id, nombre, stock, imagen_url, categoria
        FROM producto
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $productoId);

} else {

    if (($categoriaFiltro === 'todas' || $categoriaFiltro === '') && $q === '') {

        $stmt = $conn->prepare("
            SELECT id, nombre, stock, imagen_url, categoria
            FROM producto
            ORDER BY categoria, nombre
        ");

    } elseif (($categoriaFiltro === 'todas' || $categoriaFiltro === '') && $q !== '') {

        $like = "%{$q}%";
        $stmt = $conn->prepare("
            SELECT id, nombre, stock, imagen_url, categoria
            FROM producto
            WHERE nombre LIKE ?
            ORDER BY categoria, nombre
        ");
        $stmt->bind_param("s", $like);

    } elseif ($categoriaFiltro !== 'todas' && $categoriaFiltro !== '' && $q === '') {

        $stmt = $conn->prepare("
            SELECT id, nombre, stock, imagen_url, categoria
            FROM producto
            WHERE categoria = ?
            ORDER BY nombre
        ");
        $stmt->bind_param("s", $categoriaFiltro);

    } else {

        $like = "%{$q}%";
        $stmt = $conn->prepare("
            SELECT id, nombre, stock, imagen_url, categoria
            FROM producto
            WHERE categoria = ?
              AND nombre LIKE ?
            ORDER BY nombre
        ");
        $stmt->bind_param("ss", $categoriaFiltro, $like);
    }
}

$stmt->execute();
$res = $stmt->get_result();
$productos = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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

        <div class="stock-top">

            <!-- FILTRO CATEGORÍA -->
            <form method="get" class="category-filter" id="formCategoria">
                <label for="categoria">Categoría:</label>
                <select name="categoria" id="categoria" onchange="this.form.submit()">
                    <option value="todas" <?= ($categoriaFiltro === 'todas' ? 'selected' : '') ?>>Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($categoriaFiltro === $cat ? 'selected' : '') ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($productoId > 0): ?>
                    <input type="hidden" name="producto_id" value="<?= (int)$productoId ?>">
                <?php elseif ($q !== ''): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                <?php endif; ?>
            </form>

            <!-- BUSCADOR CON SUGERENCIAS -->
            <div class="op-search">
                <div class="op-search-input">
                    <span class="op-search-icon">🔎</span>
                    <input
                        type="text"
                        id="opSearchInput"
                        placeholder="Buscar producto por nombre…"
                        autocomplete="off"
                        value="<?= htmlspecialchars($q) ?>"
                    >
                    <button type="button" id="opClearBtn" class="op-clear" aria-label="Limpiar">✕</button>
                </div>

                <div id="opSearchSuggestions" class="op-suggestions"></div>

                <div id="opNotFound" class="op-notfound">
                    Producto no encontrado
                </div>
            </div>

        </div>

        <?php if ($productoId > 0 || $q !== ''): ?>
            <div class="filter-chip">
                <?php if ($productoId > 0): ?>
                    Mostrando un producto seleccionado.
                <?php else: ?>
                    Mostrando resultados para: <strong><?= htmlspecialchars($q) ?></strong>
                <?php endif; ?>

                <a class="chip-link"
                   href="operador_stock.php<?= ($categoriaFiltro !== 'todas' ? '?categoria=' . urlencode($categoriaFiltro) : '') ?>">
                    Quitar filtro
                </a>
            </div>
        <?php endif; ?>

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
                        <input type="hidden" name="categoria" value="<?= htmlspecialchars($categoriaFiltro) ?>">

                        <!-- mantener filtro al volver -->
                        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                        <input type="hidden" name="producto_id_filtro" value="<?= (int)$productoId ?>">

                        <input type="number" name="cantidad" class="qty-input" min="1" value="1">

                        <button type="submit" name="accion" value="quitar" class="btn-remove">Quitar</button>
                        <button type="submit" name="accion" value="agregar" class="btn-add">Agregar</button>
                    </form>
                </div>
            <?php endforeach; ?>

            <?php if (empty($productos)): ?>
                <p class="no-products">No hay productos con ese filtro.</p>
            <?php endif; ?>
        </section>
    </main>

</div>

<script>
(() => {
    const input = document.getElementById("opSearchInput");
    const clearBtn = document.getElementById("opClearBtn");
    const sugBox = document.getElementById("opSearchSuggestions");
    const notFound = document.getElementById("opNotFound");

    const categoria = () => document.getElementById("categoria").value;

    let timer = null;
    let lastQ = "";

    function esc(str){
        return (str ?? "").replace(/[&<>"']/g, m => ({
            "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
        }[m]));
    }

    function hideSuggestions(){
        sugBox.style.display = "none";
        sugBox.innerHTML = "";
        notFound.style.display = "none";
    }

    function toggleClear(){
        clearBtn.style.display = input.value.trim() !== "" ? "inline-flex" : "none";
    }

    async function fetchSuggestions(q){
        const url = `operador_stock.php?ajax=suggest&q=${encodeURIComponent(q)}&categoria=${encodeURIComponent(categoria())}`;
        const res = await fetch(url);
        return await res.json();
    }

    function renderSuggestions(items, q){
        sugBox.innerHTML = "";
        notFound.style.display = "none";

        if (!items || items.length === 0) {
            sugBox.style.display = "none";
            notFound.style.display = "block";
            return;
        }

        items.forEach(it => {
            const div = document.createElement("div");
            div.className = "op-sug-item";
            div.innerHTML = `<span class="op-sug-name">${esc(it.nombre)}</span>`;
            div.addEventListener("click", () => {
               
                const url = `operador_stock.php?categoria=${encodeURIComponent(categoria())}&producto_id=${encodeURIComponent(it.id)}`;
                window.location.href = url;
            });
            sugBox.appendChild(div);
        });

        sugBox.style.display = "block";
    }

    toggleClear();
    hideSuggestions();

    clearBtn.addEventListener("click", () => {
        // limpiar y volver al listado conserva categoria
        window.location.href = `operador_stock.php?categoria=${encodeURIComponent(categoria())}`;
    });

    input.addEventListener("input", () => {
        toggleClear();
        const q = input.value.trim();

        hideSuggestions();

        if (q.length < 2) return;
        if (q === lastQ) return;

        lastQ = q;
        clearTimeout(timer);

        timer = setTimeout(async () => {
            try {
                const items = await fetchSuggestions(q);
                renderSuggestions(items, q);
            } catch (e) {
                
                hideSuggestions();
            }
        }, 180);
    });

    // cerrar sugerencias si clic fuera
    document.addEventListener("click", (e) => {
        const wrap = document.querySelector(".op-search");
        if (!wrap.contains(e.target)) hideSuggestions();
    });

    
    input.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            const q = input.value.trim();
            if (q === "") {
                window.location.href = `operador_stock.php?categoria=${encodeURIComponent(categoria())}`;
            } else {
                window.location.href = `operador_stock.php?categoria=${encodeURIComponent(categoria())}&q=${encodeURIComponent(q)}`;
            }
        }
    });
})();
</script>

</body>
</html>




