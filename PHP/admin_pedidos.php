<?php
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'administrador') {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost", "root", "", "walmart");
$conn->set_charset("utf8mb4");

/*
Tabla pedidos:
id | usuario_id | carrito_id | direccion_id | total | estado | metodo_pago_id | horario_envio | creada_en
*/

// Traer todos los pedidos
$sql = "SELECT id, total, estado, creada_en FROM pedidos ORDER BY creada_en DESC";
$res = $conn->query($sql);
$pedidos = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos - Mi tiendita</title>
    <link rel="stylesheet" href="../CSS/admin_reportes.css">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a href="admin_reportes.php" class="logo-link">
            <div class="logo-icon">
                <span class="logo-star">*</span>
            </div>
            <span class="logo-text">Mi tiendita</span>
        </a>
    </div>
</header>

<main class="reports-main">
    <a href="admin_reportes.php" class="btn-back">← Volver a reportes</a>
    <h1 class="reports-title">Pedidos</h1>

    <div class="table-wrapper">
        <div class="table-title">Listado de pedidos</div>

        <?php if (empty($pedidos)): ?>
            <p>No hay pedidos registrados.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Número de pedido</th>
                        <th>Monto</th>
                        <th>Estatus del pedido</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $p): ?>
                        <tr>
                            <td><?= (int)$p['id'] ?></td>
                            <td>$<?= number_format($p['total'], 2) ?></td>
                            <td><?= htmlspecialchars($p['estado']) ?></td>
                            <td><?= htmlspecialchars($p['creada_en']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
