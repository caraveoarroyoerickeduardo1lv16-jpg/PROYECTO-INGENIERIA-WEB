<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

// Solo admins
if (!isset($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'administrador') {
    header("Location: login.php");
    exit;
}

// Filtro por rol
$filtro = $_GET['filtro'] ?? 'todos';

if ($filtro === 'todos') {
    // ✅ Solo activos
    $stmt = $conn->prepare("
        SELECT id, usuario, correo, nombre, tipo, creado_en
        FROM usuarios
        WHERE estatus = 1
        ORDER BY id DESC
    ");
} else {
    // ✅ Solo activos + filtro por rol
    $stmt = $conn->prepare("
        SELECT id, usuario, correo, nombre, tipo, creado_en
        FROM usuarios
        WHERE estatus = 1
          AND tipo = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param("s", $filtro);
}

$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar usuarios - Mi Tiendita</title>
    <link rel="stylesheet" href="../CSS/admin_usuarios.css">
</head>
<body>

<div class="page">

    <header class="topbar">
        <div class="topbar-inner">
            <a href="admin.php" class="logo-link">
                <div class="logo-icon"><span class="logo-star">*</span></div>
                <span class="logo-text">Mi tiendita</span>
            </a>

            <div class="logout-container">
                <a href="logout.php" class="logout-button">Cerrar sesión</a>
            </div>
        </div>
    </header>

    <main class="main">
        <h1>Usuarios</h1>

        <div class="tabs">
            <a href="?filtro=todos" class="tab <?= $filtro==='todos'?'active':'' ?>">Todos</a>
            <a href="?filtro=administrador" class="tab <?= $filtro==='administrador'?'active':'' ?>">Administradores</a>
            <a href="?filtro=operador" class="tab <?= $filtro==='operador'?'active':'' ?>">Operadores</a>
            <a href="?filtro=cliente" class="tab <?= $filtro==='cliente'?'active':'' ?>">Clientes</a>

            <a href="admin_agregar_usuario.php" class="btn-add-user">
                Agregar usuario
            </a>
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Creado en</th>
                    <th>Editar</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['usuario']) ?></td>
                        <td><?= htmlspecialchars($u['nombre']) ?></td>
                        <td><?= htmlspecialchars($u['correo']) ?></td>
                        <td>
                            <span class="rol <?= htmlspecialchars($u['tipo']) ?>">
                                <?= htmlspecialchars(ucfirst($u['tipo'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($u['creado_en']) ?></td>
                        <td>
                            <a href="admin_editar_usuario.php?id=<?= (int)$u['id'] ?>" class="btn-editar">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:14px;">
                            No hay usuarios activos para mostrar.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

</div>

</body>
</html>
