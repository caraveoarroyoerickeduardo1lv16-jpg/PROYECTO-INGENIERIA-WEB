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

// Traer pedidos (NO usamos "fecha" para evitar tu error)
$res = $conn->query("
    SELECT *
    FROM pedidos
    ORDER BY id DESC
");
$pedidos = $res->fetch_all(MYSQLI_ASSOC);

function pick($row, $keys, $default = '') {
    foreach ($keys as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') return $row[$k];
    }
    return $default;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos - Admin</title>
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
            <h1>Pedidos</h1>
            <a class="btn-volver" href="admin.php">← Volver al panel</a>
        </section>

        <section class="orders-card">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th># Pedido</th>
                        <th>Cliente</th>
                        <th>Estatus</th>
                        <th>Total</th>
                        <th>Fecha</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($pedidos)): ?>
                    <tr>
                        <td colspan="6" class="orders-empty">No hay pedidos.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pedidos as $p): ?>
                        <?php
                            $idPedido   = (int)pick($p, ['id']);
                            $cliente    = pick($p, ['usuario_id','cliente_id','id_usuario'], '—');
                            $estatus    = pick($p, ['estatus','estado','status'], '—');
                            $total      = (float)pick($p, ['total'], 0);
                            $fecha      = pick($p, ['creado_en','fecha_creacion','created_at','fecha_pedido'], '—');
                        ?>
                        <tr>
                            <td>#<?= $idPedido ?></td>
                            <td><?= htmlspecialchars((string)$cliente) ?></td>
                            <td><?= htmlspecialchars((string)$estatus) ?></td>
                            <td>$<?= number_format($total, 2) ?></td>
                            <td><?= htmlspecialchars((string)$fecha) ?></td>
                            <td>
                                <a class="btn-ver-detalles"
                                   href="admin_pedido_detalle.php?id=<?= $idPedido ?>">
                                   Ver detalles
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
