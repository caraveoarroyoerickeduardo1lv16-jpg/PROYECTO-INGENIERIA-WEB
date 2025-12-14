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

function pick($row, $keys, $default = null) {
    foreach ($keys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') return $row[$k];
    }
    return $default;
}

// 1) Leer pedido
$stmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $idPedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    echo "Pedido no encontrado.";
    exit;
}

$carrito_id = (int)pick($pedido, ['carrito_id'], 0);
$totalPedido = (float)pick($pedido, ['total'], 0);
$estatus = (string)pick($pedido, ['estatus','estado','status'], '—');
$fecha = (string)pick($pedido, ['creado_en','fecha_creacion','created_at','fecha_pedido'], '—');
$cliente = (string)pick($pedido, ['usuario_id','cliente_id','id_usuario'], '—');

// 2) Leer items
$items = [];
$subtotal = 0.0;

if ($carrito_id > 0) {
    $stmt = $conn->prepare("
        SELECT
            cd.producto_id,
            cd.cantidad,
            cd.subtotal,
            p.nombre,
            p.precio,
            p.imagen_url,
            p.marca
        FROM carrito_detalle cd
        INNER JOIN producto p ON p.id = cd.producto_id
        WHERE cd.carrito_id = ?
        ORDER BY cd.id ASC
    ");
    $stmt->bind_param("i", $carrito_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($items as $it) {
        $subtotal += (float)$it['subtotal'];
    }
}

// 3) Envío estimado (si NO lo guardas como columna)
$envio = $totalPedido - $subtotal;
if ($envio < 0) $envio = 0.0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle pedido #<?= $idPedido ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../CSS/admin.css">
    <link rel="stylesheet" href="../CSS/admin_pedidos.css">
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
        <section class="orders-header">
            <h1>Detalle del pedido #<?= $idPedido ?></h1>
            <a class="btn-volver" href="admin_pedidos.php">← Volver a pedidos</a>
        </section>

        <section class="detail-grid">
            <div class="detail-card">
                <h3>Resumen</h3>
                <div class="kv"><span>Cliente:</span><strong><?= htmlspecialchars($cliente) ?></strong></div>
                <div class="kv"><span>Estatus:</span><strong><?= htmlspecialchars($estatus) ?></strong></div>
                <div class="kv"><span>Fecha:</span><strong><?= htmlspecialchars($fecha) ?></strong></div>
                <div class="kv"><span>Carrito ID:</span><strong><?= (int)$carrito_id ?></strong></div>
            </div>

            <div class="detail-card">
                <h3>Totales</h3>
                <div class="kv"><span>Subtotal:</span><strong>$<?= number_format($subtotal, 2) ?></strong></div>
                <div class="kv"><span>Envío:</span><strong>$<?= number_format($envio, 2) ?></strong></div>
                <div class="kv total"><span>Total:</span><strong>$<?= number_format($totalPedido, 2) ?></strong></div>
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
                    <tr>
                        <td colspan="4" class="orders-empty">No se encontraron productos para este pedido.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <div class="prod-info">
                                    <?php if (!empty($it['imagen_url'])): ?>
                                        <img class="prod-img" src="<?= htmlspecialchars($it['imagen_url']) ?>" alt="">
                                    <?php endif; ?>
                                    <div>
                                        <div class="prod-name"><?= htmlspecialchars($it['nombre']) ?></div>
                                        <div class="prod-brand"><?= htmlspecialchars($it['marca'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>$<?= number_format((float)$it['precio'], 2) ?></td>
                            <td><?= (int)$it['cantidad'] ?></td>
                            <td>$<?= number_format((float)$it['subtotal'], 2) ?></td>
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
