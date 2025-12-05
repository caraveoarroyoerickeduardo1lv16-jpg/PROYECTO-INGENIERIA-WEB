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

// === ESTADÍSTICAS BÁSICAS ===

// Total de ventas (suma de total en pedidos)
$res = $conn->query("SELECT IFNULL(SUM(total),0) AS total_ventas FROM pedidos");
$ventasTotal = $res->fetch_assoc()['total_ventas'] ?? 0;

// Cantidad de pedidos
$res = $conn->query("SELECT COUNT(*) AS num_pedidos FROM pedidos");
$pedidosTotal = $res->fetch_assoc()['num_pedidos'] ?? 0;

// Cantidad de clientes
$res = $conn->query("SELECT COUNT(*) AS num_clientes FROM usuarios WHERE tipo='cliente'");
$clientesTotal = $res->fetch_assoc()['num_clientes'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de administrador - Mi tiendita</title>
    <link rel="stylesheet" href="../CSS/admin_reportes.css">
</head>
<body>

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

<main class="reports-main">

    <h1 class="reports-title">Reportes de administrador</h1>

    <!-- TARJETAS DE RESUMEN -->
    <section class="reports-cards">

        <!-- VENTAS (solo resumen, sin Ver más) -->
        <article class="report-card">
            <div class="report-header">Ventas</div>
            <div class="report-icon">📈</div>
            <div class="report-main">
                $<?= number_format($ventasTotal, 2) ?>
            </div>
        </article>

        <!-- PEDIDOS (con botón Ver más) -->
        <article class="report-card">
            <div class="report-header">Pedidos</div>
            <div class="report-icon">📦</div>
            <div class="report-main">
                <?= (int)$pedidosTotal ?>
            </div>
            <a href="admin_pedidos.php" class="btn-more">Ver más</a>
        </article>

        <!-- CLIENTES (con botón Ver más) -->
        <article class="report-card">
            <div class="report-header">Clientes</div>
            <div class="report-icon">👤</div>
            <div class="report-main">
                <?= (int)$clientesTotal ?>
            </div>
            <a href="admin_clientes.php" class="btn-more">Ver más</a>
        </article>

    </section>

</main>

</body>
</html>


