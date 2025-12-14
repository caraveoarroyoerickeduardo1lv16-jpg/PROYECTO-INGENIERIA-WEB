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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: admin_usuarios.php");
    exit;
}

$errores = [];

/* =========================
   ELIMINAR USUARIO
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_usuario'])) {

    $uid = (int)$_POST['eliminar_usuario'];

    if ($uid > 0) {

        // 1) Eliminar detalles de pedidos del usuario
        $stmt = $conn->prepare("
            DELETE pd
            FROM pedido_detalle pd
            INNER JOIN pedidos p ON p.id = pd.pedido_id
            WHERE p.usuario_id = ?
        ");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();

        // 2) Eliminar pedidos
        $stmt = $conn->prepare("DELETE FROM pedidos WHERE usuario_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();

        // 3) Eliminar detalles de carrito
        $stmt = $conn->prepare("
            DELETE cd
            FROM carrito_detalle cd
            INNER JOIN carrito c ON c.id = cd.carrito_id
            WHERE c.usuario_id = ?
        ");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();

        // 4) Eliminar carrito
        $stmt = $conn->prepare("DELETE FROM carrito WHERE usuario_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();

        // 5) Eliminar métodos de pago
        $stmt = $conn->prepare("DELETE FROM metodos_pago WHERE usuario_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();

        // 6) Eliminar direcciones
        $stmt = $conn->prepare("DELETE FROM direcciones WHERE usuario_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();

        // 7) Eliminar usuario
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin_usuarios.php?eliminado=1");
    exit;
}

/* =========================
   GUARDAR CAMBIOS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Si viene eliminar_usuario ya salimos arriba. Aquí solo llega cuando guardas.
    if (isset($_POST['eliminar_usuario'])) {
        // seguridad extra
        header("Location: admin_usuarios.php");
        exit;
    }

    $usuario    = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $correo     = trim($_POST['correo'] ?? '');
    $nombre     = trim($_POST['nombre'] ?? '');
    $tipo       = trim($_POST['tipo'] ?? '');

    if ($usuario === '' || $contrasena === '' || $correo === '' || $nombre === '' || $tipo === '') {
        $errores[] = "Todos los campos son obligatorios.";
    }

    if (!in_array($tipo, ['administrador', 'operador', 'cliente'], true)) {
        $errores[] = "El rol seleccionado no es válido.";
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

/* =========================
   CARGAR USUARIO
========================= */
$stmt = $conn->prepare("
    SELECT id, usuario, contrasena, correo, nombre, tipo, creado_en
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

        <div class="logout-container">
            <a href="logout.php" class="logout-button">Cerrar sesión</a>
        </div>
    </div>
</header>

<main class="main">

<div class="edit-header">
    <h1>Editar usuario</h1>
    <a href="admin_usuarios.php" class="btn-volver">← Volver a usuarios</a>
</div>

<?php if (!empty($errores)): ?>
    <div class="alert-error">
        <ul>
            <?php foreach ($errores as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
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

        <!-- BOTÓN ELIMINAR -->
        <button type="button" class="btn-eliminar" onclick="confirmarEliminar(<?= (int)$usuarioData['id'] ?>)">
            Eliminar usuario
        </button>
    </div>
</form>

</main>
</div>

<script>
function confirmarEliminar(id) {
    if (confirm("¿Seguro que quieres eliminar este usuario?\nSe eliminarán pedidos, carritos, direcciones y métodos de pago.")) {
        const f = document.createElement("form");
        f.method = "POST";

        const i = document.createElement("input");
        i.type = "hidden";
        i.name = "eliminar_usuario";
        i.value = id;

        f.appendChild(i);
        document.body.appendChild(f);
        f.submit();
    }
}
</script>

</body>
</html>
