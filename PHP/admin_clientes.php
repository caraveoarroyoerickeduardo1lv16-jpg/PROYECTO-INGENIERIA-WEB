<?php
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'administrador') {
    header("Location: login.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

$sql = "SELECT id, correo, nombre FROM usuarios WHERE tipo = 'cliente' ORDER BY id ASC";
$res = $conn->query($sql);
$clientes = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes - Mi tiendita</title>
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

    <!-- ✅ BOTÓN AZUL VOLVER A REPORTES -->
    <a href="admin_reportes.php" class="btn-volver-reportes">
        ← Volver a reportes
    </a>

    <h1 class="reports-title">Clientes</h1>

    <div class="table-wrapper">
        <div class="table-title">Listado de clientes</div>

        <?php if (empty($clientes)): ?>
            <p>No hay clientes registrados.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Número de cliente</th>
                        <th>Correo</th>
                        <th>Nombre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                        <tr>
                            <td><?= (int)$c['id'] ?></td>
                            <td><?= htmlspecialchars($c['correo']) ?></td>
                            <td><?= htmlspecialchars($c['nombre']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</main>

</body>
</html>
