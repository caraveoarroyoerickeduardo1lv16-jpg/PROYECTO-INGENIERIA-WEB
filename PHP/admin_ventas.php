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


$mes = trim($_GET['mes'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}
$inicioMes    = $mes . '-01';
$inicioMesSig = date('Y-m-01', strtotime('+1 month', strtotime($inicioMes)));

/* 
 EXPORT EXCEL (CSV) POR PEDIDO + ARTÍCULOS
    */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_csv'])) {

    // 1) Traer cada línea de pedido (pedido + artículo)
    $stmt = $conn->prepare("
        SELECT
            pe.id                      AS pedido_id,
            pe.creada_en               AS fecha,
            pe.total                   AS total_pedido,
            pe.estado                  AS estado_pago,
            pe.estatus                 AS estatus_envio,

            p.nombre                   AS producto,
            d.cantidad                 AS cantidad,
            d.precio_unit              AS precio_unit,
            (d.cantidad * d.precio_unit) AS subtotal_linea
        FROM pedidos pe
        INNER JOIN pedido_detalle d ON d.pedido_id = pe.id
        INNER JOIN producto p       ON p.id = d.producto_id
        WHERE pe.creada_en >= ? AND pe.creada_en < ?
        ORDER BY pe.creada_en ASC, pe.id ASC, p.nombre ASC
    ");
    $stmt->bind_param("ss", $inicioMes, $inicioMesSig);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 2) Descargar como CSV (Excel lo abre perfecto)
    $filename = "reporte_ventas_por_pedido_" . $mes . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

   
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    
    fputcsv($out, [
        'Fecha',
        'Pedido #',
        'Producto',
        'Cantidad',
        'Precio unitario',
        'Subtotal (artículo)',
        'Total del pedido',
        'Estado pago',
        'Estatus envío'
    ]);

  
    $pedidoAnterior = null;

    foreach ($rows as $r) {

        $pedidoActual = (int)$r['pedido_id'];

     
        if ($pedidoAnterior !== null && $pedidoActual !== $pedidoAnterior) {
            fputcsv($out, ['','','','','','','','','']);
        }
        $pedidoAnterior = $pedidoActual;

        fputcsv($out, [
            $r['fecha'], 
            $pedidoActual,
            $r['producto'],
            (int)$r['cantidad'],
            number_format((float)$r['precio_unit'], 2, '.', ''),
            number_format((float)$r['subtotal_linea'], 2, '.', ''),
            number_format((float)$r['total_pedido'], 2, '.', ''),
            $r['estado_pago'],
            $r['estatus_envio']
        ]);
    }

    fclose($out);
    exit;
}


$sqlMasVendido = "
    SELECT 
        p.id,
        p.nombre,
        SUM(d.cantidad)                 AS total_vendida,
        SUM(d.cantidad * d.precio_unit) AS total_importe
    FROM pedidos pe
    INNER JOIN pedido_detalle d ON d.pedido_id = pe.id
    INNER JOIN producto p       ON p.id        = d.producto_id
    WHERE pe.creada_en >= ? AND pe.creada_en < ?
    GROUP BY p.id, p.nombre
    ORDER BY total_vendida DESC
    LIMIT 1
";
$stmt = $conn->prepare($sqlMasVendido);
$stmt->bind_param("ss", $inicioMes, $inicioMesSig);
$stmt->execute();
$productoMes = $stmt->get_result()->fetch_assoc();
$stmt->close();


$stmt = $conn->prepare("
    SELECT
        DATE(pe.creada_en) AS dia,
        COUNT(DISTINCT pe.id) AS num_pedidos,
        SUM(pe.total) AS total_ventas
    FROM pedidos pe
    WHERE pe.creada_en >= ? AND pe.creada_en < ?
    GROUP BY DATE(pe.creada_en)
    ORDER BY dia DESC
");
$stmt->bind_param("ss", $inicioMes, $inicioMesSig);
$stmt->execute();
$ventasDiarias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


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
            <div class="logo-icon"><span class="logo-star">*</span></div>
            <span class="logo-text">Mi tiendita</span>
        </a>
    </div>
</header>

<main class="reports-main">

    <a href="admin_reportes.php" class="back-link">← Volver a reportes</a>

    <div class="ventas-top">
        <h1 class="reports-title">Reporte de ventas</h1>

       
        <form method="post" class="export-form">
            <input type="hidden" name="export_csv" value="1">
            <button type="submit" class="btn-export">
                Descargar Excel
            </button>
        </form>
    </div>

    <div class="mes-chip">
        Mes del reporte: <strong><?= htmlspecialchars($mes) ?></strong>
    </div>

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
                    <strong>$<?= number_format((float)$productoMes['total_importe'], 2) ?></strong>
                </div>
                <div class="highlight-row">
                    Mes:
                    <strong><?= date('m/Y', strtotime($inicioMes)) ?></strong>
                </div>
            </div>
        <?php else: ?>
            <p>No hay ventas registradas en el mes seleccionado.</p>
        <?php endif; ?>
    </section>

    <!-- 2) Reporte diario (resumen del mes) -->
    <section>
        <h2 class="section-title">Resumen diario del mes</h2>

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
                            <td>$<?= number_format((float)$fila['total_ventas'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay ventas registradas.</p>
        <?php endif; ?>
    </section>

    <!-- 3) Reporte mensual -->
    <section>
        <h2 class="section-title">Reporte mensual (últimos 12 meses)</h2>

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
                            <td>$<?= number_format((float)$fila['total_ventas'], 2) ?></td>
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



