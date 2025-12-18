<?php
session_start();

// Solo admins
if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'administrador') {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

$idPedido = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idPedido <= 0) {
    header("Location: admin_pedidos.php");
    exit;
}

// Pedido
$stmt = $conn->prepare("
    SELECT *
    FROM pedidos
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $idPedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    echo "Pedido no encontrado.";
    exit;
}

// Productos del pedido
$stmt = $conn->prepare("
    SELECT
        pd.cantidad,
        pd.precio_unit,
        p.nombre,
        p.imagen_url,
        p.marca
    FROM pedido_detalle pd
    INNER JOIN producto p ON p.id = pd.producto_id
    WHERE pd.pedido_id = ?
");
$stmt->bind_param("i", $idPedido);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular subtotal
$subtotal = 0;
foreach ($items as $it) {
    $subtotal += (int)$it['cantidad'] * (float)$it['precio_unit'];
}

$total = (float)$pedido['total'];
$envio = max(0, $total - $subtotal);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Detalle pedido #<?= (int)$idPedido ?></title>


<link rel="stylesheet" href="../CSS/admin_pedido_detalle.css">
</head>
<body>

<div class="page">

<header class="topbar">
    <div class="topbar-inner">
        <a href="admin.php" class="logo-link">
            <div class="logo-icon"><span class="logo-star">*</span></div>
            <span class="logo-text">Mi tiendita</span>
        </a>

        <div class="topbar-actions">
            <a href="logout.php" class="logout-button">Cerrar sesión</a>
        </div>
    </div>
</header>

<main class="admin-main">

    <section class="orders-header">
        <div>
            <h1>Pedido #<?= (int)$idPedido ?></h1>
            <p class="muted">Detalle del pedido</p>
        </div>
        <a href="admin_pedidos.php" class="btn-volver">← Volver</a>
    </section>

    <section class="detail-grid">

        <div class="detail-card">
            <h3>Datos del pedido</h3>
            <div class="kv"><span>Cliente:</span><strong><?= htmlspecialchars((string)$pedido['usuario_id']) ?></strong></div>
            <div class="kv"><span>Pago:</span><strong><?= htmlspecialchars((string)$pedido['estado']) ?></strong></div>
            <div class="kv"><span>Estatus:</span><strong><?= htmlspecialchars((string)$pedido['estatus']) ?></strong></div>
            <div class="kv"><span>Horario:</span><strong><?= htmlspecialchars((string)$pedido['horario_envio']) ?></strong></div>
            <div class="kv"><span>Fecha:</span><strong><?= htmlspecialchars((string)$pedido['creada_en']) ?></strong></div>
        </div>

        <div class="detail-card">
            <h3>Totales</h3>
            <div class="kv"><span>Subtotal:</span><strong>$<?= number_format($subtotal, 2) ?></strong></div>
            <div class="kv"><span>Envío:</span><strong>$<?= number_format($envio, 2) ?></strong></div>
            <div class="kv total"><span>Total:</span><strong>$<?= number_format($total, 2) ?></strong></div>
        </div>

    </section>

    <section class="orders-card">
        <h2 class="mini-title">Productos comprados</h2>

        <table class="orders-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Cantidad</th>
                    <th>Importe</th>
                </tr>
            </thead>
            <tbody>

            <?php if (empty($items)): ?>
                <tr><td colspan="4" class="orders-empty">Sin productos</td></tr>
            <?php else: ?>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <div class="prod-info">
                                <?php if (!empty($it['imagen_url'])): ?>
                                    <img src="<?= htmlspecialchars($it['imagen_url']) ?>" class="prod-img" alt="Producto">
                                <?php else: ?>
                                    <div class="prod-img placeholder" aria-hidden="true"></div>
                                <?php endif; ?>

                                <div class="prod-text">
                                    <div class="prod-name"><?= htmlspecialchars($it['nombre']) ?></div>
                                    <div class="prod-brand"><?= htmlspecialchars($it['marca']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>$<?= number_format((float)$it['precio_unit'], 2) ?></td>
                        <td><?= (int)$it['cantidad'] ?></td>
                        <td>$<?= number_format((int)$it['cantidad'] * (float)$it['precio_unit'], 2) ?></td>
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

