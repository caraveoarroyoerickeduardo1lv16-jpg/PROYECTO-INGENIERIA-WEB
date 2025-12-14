<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

// Solo admins
if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'administrador') {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: admin_usuarios.php");
    exit;
}

$errores = [];

/* ===========================
   ELIMINAR USUARIO
   =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_usuario'])) {

    // 1) Eliminar carritos
    $stmt = $conn->prepare("DELETE FROM carrito_detalle WHERE carrito_id IN (SELECT id FROM carrito WHERE usuario_id = ?)");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM carrito WHERE usuario_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 2) Eliminar direcciones
    $stmt = $conn->prepare("DELETE FROM direcciones WHERE usuario_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 3) Eliminar métodos de pago
    $stmt = $conn->prepare("DELETE FROM metodos_pago WHERE usuario_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 4) Eliminar pedidos y detalles
    $stmt = $conn->prepare("DELETE FROM pedido_detalle WHERE pedido_id IN (SELECT id FROM pedidos WHERE usuario_id = ?)");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM pedidos WHERE usuario_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // 5) Eliminar usuario
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_usuarios.php?eliminado=1");
    exit;
}

/* ===========================
   GUARDAR CAMBIOS
   =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['eliminar_usuario'])) {

    $usuario    = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $correo     = trim($_POST['correo'] ?? '');
    $nombre     = trim($_POST['nombre'] ?? '');
    $tipo       = trim($_POST['tipo'] ?? '');

    if ($usuario === '' || $contrasena === '' || $correo === '' || $nombre === '' || $tipo === '') {
        $errores[] = "Todos los campos son obligatorios.";
    }

    if (!in_array($tipo, ['administrador', 'operador', 'cliente'], true)) {
        $errores[] = "Rol inválido.";
    }

    if (empty($errores)) {
        $stmt = $conn->prepare("
            UPDATE usuarios
            SET usuario = ?, contrasena = ?, correo = ?, nombre = ?, tipo = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssssi", $usuario, $contrasena, $correo, $nombre, $tipo, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: admin_usuarios.php");
        exit;
    }
}

/* ===========================
   CARGAR USUARIO
   =========================== */
$stmt = $conn->prepare("
    SELECT usuario, contrasena, correo, nombre, tipo
    FROM usuarios
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$usuarioData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuarioData) {
    echo "Usuario no encontrado.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar usuario - Mi Tiendita</title>
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
        <a href="logout.php" class="logout-button">Cerrar sesión</a>
    </div>
</header>

<main class="main">

<div class="edit-header">
    <h1>Editar usuario</h1>
    <a href="admin_usuarios.php" class="btn-volver">← Volver</a>
</div>

<?php if (!empty($errores)): ?>
<div class="alert-error">
    <?php foreach ($errores as $e): ?>
        <p><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="edit-card">

<div class="form-grid">

    <div class="form-group">
        <label>Usuario</label>
        <input type="text" name="usuario" value="<?= htmlspecialchars($usuarioData['usuario']) ?>" required>
    </div>

    <div class="form-group">
        <label>Contraseña</label>
        <input type="text" name="contrasena" value="<?= htmlspecialchars($usuarioData['contrasena']) ?>" required>
    </div>

    <div class="form-group">
        <label>Correo</label>
        <input type="email" name="correo" value="<?= htmlspecialchars($usuarioData['correo']) ?>" required>
    </div>

    <div class="form-group">
        <label>Nombre</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($usuarioData['nombre']) ?>" required>
    </div>

    <div class="form-group">
        <label>Rol</label>
        <select name="tipo" required>
            <option value="administrador" <?= $usuarioData['tipo']==='administrador'?'selected':'' ?>>Administrador</option>
            <option value="operador" <?= $usuarioData['tipo']==='operador'?'selected':'' ?>>Operador</option>
            <option value="cliente" <?= $usuarioData['tipo']==='cliente'?'selected':'' ?>>Cliente</option>
        </select>
    </div>

</div>

<div class="edit-actions">
    <button type="submit" class="btn-guardar">Guardar cambios</button>
</div>

</form>

<form method="post" onsubmit="return confirm('¿Seguro que deseas eliminar este usuario? Esta acción no se puede deshacer.');">
    <input type="hidden" name="eliminar_usuario" value="1">
    <button type="submit" class="btn-eliminar">Eliminar usuario</button>
</form>

</main>

</div>

</body>
</html>


