<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

// SOLO OPERADORES
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_tipo"] ?? '') !== "operador") {
    header("Location: login.php");
    exit;
}

/* 1) MANEJAR CAMBIO DE ESTATUS  */
$estatusPosibles = ['en preparación', 'en ruta', 'entregado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'], $_POST['nuevo_estatus'])) {
    $pedido_id     = (int)$_POST['pedido_id'];
    $nuevo_estatus = trim($_POST['nuevo_estatus']);
    $filtro_post   = $_POST['filtro'] ?? 'preparacion';

    if ($pedido_id > 0 && in_array($nuevo_estatus, $estatusPosibles, true)) {
        $stmt = $conn->prepare("UPDATE pedidos SET estatus = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estatus, $pedido_id);
        $stmt->execute();
        $stmt->close();
    }

    
    header("Location: operador_pedidos.php?filtro=" . urlencode($filtro_post));
    exit;
}

/* 2) MOSTRAR LISTA SEGÚN FILTRO  */

// FILTRO
$filtro = $_GET["filtro"] ?? "preparacion";

$estatusBD = [
    "preparacion" => "en preparación",
    "ruta"        => "en ruta"
];

$estatusSeleccionado = $estatusBD[$filtro] ?? "en preparación";

// CONSULTA DE PEDIDOS
$stmt = $conn->prepare("
    SELECT p.id, p.estatus, p.horario_envio, p.total, 
           u.nombre AS cliente
    FROM pedidos p
    INNER JOIN usuarios u ON u.id = p.usuario_id
    WHERE p.estatus = ?
    ORDER BY p.id DESC
");
$stmt->bind_param("s", $estatusSeleccionado);
$stmt->execute();
$pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos - Operador</title>
    <link rel="stylesheet" href="../CSS/operador_pedidos.css">
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

        <nav class="tabs">
            <a href="?filtro=preparacion" class="tab <?= $filtro==='preparacion'?'active':'' ?>">En preparación</a>
            <a href="?filtro=ruta" class="tab <?= $filtro==='ruta'?'active':'' ?>">En ruta</a>
        </nav>

        <section class="cards">
            <?php if (empty($pedidos)): ?>
                <p>No hay pedidos en esta categoría.</p>
            <?php else: ?>

                <?php foreach ($pedidos as $p): ?>
                    <article class="card">
                        <h2>Pedido #<?= $p['id'] ?></h2>

                        <span class="badge <?= str_replace(' ', '-', $p['estatus']) ?>">
                            <?= ucfirst($p['estatus']) ?>
                        </span>

                        <p class="cliente"><?= htmlspecialchars($p['cliente']) ?></p>

                        <!-- FORMULARIO PARA CAMBIAR ESTATUS -->
                        <form method="post" class="status-form">
                            <input type="hidden" name="pedido_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro) ?>">

                            <select name="nuevo_estatus" class="status-select">
                                <?php foreach ($estatusPosibles as $op): ?>
                                    <option value="<?= htmlspecialchars($op) ?>"
                                        <?= $op === $p['estatus'] ? 'selected' : '' ?>>
                                        <?= ucfirst($op) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" class="status-submit">
                                Guardar
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>

            <?php endif; ?>
        </section>
    </main>

</div>
</body>
</html>

