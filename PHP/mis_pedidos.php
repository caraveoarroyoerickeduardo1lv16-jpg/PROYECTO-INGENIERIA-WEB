<?php
// PHP/mis_pedidos.php – Historial de pedidos del usuario

session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = (int)$_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "walmart");
$conn->set_charset("utf8mb4");

// Leer pedidos del usuario
$stmt = $conn->prepare("
    SELECT p.id,
           p.total,
           p.estado,
           p.estatus,
           p.horario_envio,
           p.creada_en,
           d.etiqueta,
           d.ciudad,
           d.estado AS estado_dir
    FROM pedidos p
    LEFT JOIN direcciones d ON d.id = p.direccion_id
    WHERE p.usuario_id = ?
    ORDER BY p.creada_en DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis pedidos - Mi Tiendita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/mis_pedidos.css">
</head>
<body>

<header class="header">
    <div class="header-left">
        <div class="logo">
            <a href="index.php" class="logo-link">
                <div class="logo-icon">*</div>
            </a>
            <h1>Mi Tiendita</h1>
        </div>
    </div>

    <div class="header-right">
        <span class="header-user">
            <?= htmlspecialchars($_SESSION['usuario']); ?>
        </span>
        <a href="logout.php" class="header-link">Cerrar sesión</a>
    </div>
</header>

<main class="main">
    <h2>Mis pedidos</h2>

    <?php if (empty($pedidos)): ?>
        <p>No tienes pedidos registrados.</p>
    <?php else: ?>
        <section class="pedidos-card">
            <table class="pedidos-table">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Pago</th>
                        <th>Estatus envío</th>
                        <th>Horario</th>
                        <th>Dirección</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $p): ?>
                        <tr>
                            <td>#<?= (int)$p['id']; ?></td>
                            <td><?= htmlspecialchars($p['creada_en']); ?></td>
                            <td>$<?= number_format($p['total'], 2); ?></td>
                            <td><?= htmlspecialchars($p['estado']); ?></td>
                            <td>
                                <?php if (!empty($p['estatus'])): ?>
                                    <span class="badge <?= str_replace(' ', '-', $p['estatus']); ?>">
                                        <?= htmlspecialchars(ucfirst($p['estatus'])); ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['horario_envio']); ?></td>
                            <td>
                                <?php if (!empty($p['etiqueta'])): ?>
                                    <?= htmlspecialchars($p['etiqueta']); ?>,
                                    <?= htmlspecialchars($p['ciudad']); ?>,
                                    <?= htmlspecialchars($p['estado_dir']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</main>

</body>
</html>
