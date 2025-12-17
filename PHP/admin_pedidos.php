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

// Leer pedidos
$res = $conn->query("
    SELECT 
        id,
        usuario_id,
        total,
        estado,
        estatus,
        creada_en
    FROM pedidos
    ORDER BY id DESC
");
$pedidos = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos - Admin</title>
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

    <!-- ✅ BOTÓN AZUL VOLVER A REPORTES -->
    <a href="admin_reportes.php" class="btn-volver-reportes">
        ← Volver a reportes
    </a>
</section>

<section class="orders-card">
<table class="orders-table">
<thead>
<tr>
    <th># Pedido</th>
    <th>Cliente</th>
    <th>Estado pago</th>
    <th>Estatus envío</th>
    <th>Total</th>
    <th>Fecha</th>
    <th></th>
</tr>
</thead>
<tbody>

<?php if (empty($pedidos)): ?>
<tr>
    <td colspan="7" class="orders-empty">No hay pedidos registrados.</td>
</tr>
<?php else: ?>
<?php foreach ($pedidos as $p): ?>
<tr>
    <td>#<?= (int)$p['id'] ?></td>
    <td><?= (int)$p['usuario_id'] ?></td>
    <td><?= htmlspecialchars($p['estado']) ?></td>
    <td><?= htmlspecialchars($p['estatus']) ?></td>
    <td>$<?= number_format((float)$p['total'], 2) ?></td>
    <td><?= htmlspecialchars($p['creada_en']) ?></td>
    <td>
        <a class="btn-ver-detalles"
           href="admin_pedido_detalle.php?id=<?= (int)$p['id'] ?>">
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
