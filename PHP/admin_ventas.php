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

// ===============================
// 1) PRODUCTO MÁS VENDIDO DEL MES
// ===============================

// Inicio de mes actual y primer día del siguiente mes
$inicioMes    = date('Y-m-01');
$inicioMesSig = date('Y-m-01', strtotime('+1 month', strtotime($inicioMes)));

/*
   IMPORTANTE:
   - Tabla de pedidos:      pedidos
   - Columna de fecha:      creada_en
   - Tabla de detalle:      pedido_detalle  (si se llama distinto, solo cambia este nombre)
   - Columnas detalle:      pedido_id, producto_id, cantidad, precio_unit
   - Tabla producto:        producto
*/

$sqlMasVendido = "
    SELECT p.id,
           p.nombre,
           SUM(d.cantidad)                         AS total_vendida,
           SUM(d.cantidad * d.precio_unit)         AS total_importe
    FROM pedido_detalle d
    INNER JOIN pedidos   pe ON d.pedido_id   = pe.id
    INNER JOIN producto  p  ON d.producto_id = p.id
    WHERE pe.creada_en >= ? AND pe.creada_en < ?
    GROUP BY p.id, p.nombre
    ORDER BY total_vendida DESC
    LIMIT 1
";

$stmt = $conn->prepare($sqlMasVendido);
$stmt->bind_param("ss", $inicioMes, $inicioMesSig);
$stmt->execute();
$resMasVendido = $stmt->get_result();
$productoMes   = $resMasVendido->fetch_assoc();
$stmt->close();

// ===============================
// 2) REPORTE DE VENTAS DIARIO
// ===============================

$sqlVentasDiarias = "
    SELECT DATE(creada_en) AS dia,
           COUNT(*)        AS num_pedidos,
           SUM(total)      AS total_ventas
    FROM pedidos
    GROUP BY DATE(creada_en)
    ORDER BY dia DESC
    LIMIT 30
";
$resDiario     = $conn->query($sqlVentasDiarias);
$ventasDiarias = $resDiario->fetch_all(MYSQLI_ASSOC);

// ===============================
// 3) REPORTE DE VENTAS MENSUAL
// ===============================

$sqlVentasMensuales = "
    SELECT DATE_FORMAT(creada_en, '%Y-%m') AS mes,
           COUNT(*)                        AS num_pedidos,
           SUM(total)                      AS total_ventas
    FROM pedidos
    GROUP BY DATE_FORMAT(creada_en, '%Y-%m')
    ORDER BY mes DESC
    LIMIT 12
";
$resMensual      = $conn->query($sqlVentasMensuales);
$ventasMensuales = $resMensual->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de ventas - Mi tiendita</title>
    <link rel="stylesheet" href="../CSS/admin_reportes.css">
    <link rel="stylesheet" href="../CSS/admin_ventas.css">
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

    <a href="admin_reportes.php" class="back-link">← Volver a reportes</a>

    <h1 class="reports-title">Reporte de ventas</h1>

    <!-- 1) Producto más vendido del mes -->
    <section>
        <h2 class="section-title">Producto más vendido del mes</h2>

        <?php if ($productoMes): ?>
            <div class="highlight-card">
                <h2><?= htmlspecialchars($productoMes['nombre']) ?></h2>
                <div class="highlight-row">
                    Cantidad vendida:
                    <strong><?= (int)$productoMes['total_vendida'] ?></strong>
                </div>
                <div class="highlight-row">
                    Importe total:
                    <strong>$<?= number_format($productoMes['total_importe'], 2) ?></strong>
                </div>
                <div class="highlight-row">
                    Mes:
                    <strong><?= date('m/Y', strtotime($inicioMes)) ?></strong>
                </div>
            </div>
        <?php else: ?>
            <p>No hay ventas registradas en el mes actual.</p>
        <?php endif; ?>
    </section>

    <!-- 2) Reporte de ventas diario -->
    <section>
        <h2 class="section-title">Reporte de ventas diario (últimos 30 días)</h2>

        <?php if (!empty($ventasDiarias)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Número de pedidos</th>
                        <th>Total vendido</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventasDiarias as $fila): ?>
                        <tr>
                            <td><?= htmlspecialchars($fila['dia']) ?></td>
                            <td><?= (int)$fila['num_pedidos'] ?></td>
                            <td>$<?= number_format($fila['total_ventas'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay ventas registradas.</p>
        <?php endif; ?>
    </section>

    <!-- 3) Reporte de ventas mensual -->
    <section>
        <h2 class="section-title">Reporte de ventas mensual (últimos 12 meses)</h2>

        <?php if (!empty($ventasMensuales)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Número de pedidos</th>
                        <th>Total vendido</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventasMensuales as $fila): ?>
                        <tr>
                            <td><?= htmlspecialchars($fila['mes']) ?></td>
                            <td><?= (int)$fila['num_pedidos'] ?></td>
                            <td>$<?= number_format($fila['total_ventas'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay ventas registradas.</p>
        <?php endif; ?>
    </section>

</main>

</body>
</html>

