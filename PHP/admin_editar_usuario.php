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
   INVALIDAR USUARIO (ANTES BORRABA)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_usuario'])) {

    $uid = (int)$_POST['eliminar_usuario'];

    if ($uid > 0) {

        // Evitar que el admin se invalide a sí mismo
        if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $uid) {
            $errores[] = "No puedes invalidar tu propio usuario.";
        } else {

            // Solo invalidar: NO borrar nada relacionado (pedidos/ventas se conservan)
            $stmt = $conn->prepare("UPDATE usuarios SET estatus = 0 WHERE id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $stmt->close();

            header("Location: admin_usuarios.php?invalido=1");
            exit;
        }
    } else {
        $errores[] = "ID de usuario inválido.";
    }
}

/* =========================
   GUARDAR CAMBIOS (EDITAR)
========================= */
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
        $errores[] = "El rol seleccionado no es válido.";
    }

    // VALIDAR CORREO
    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo no tiene un formato válido.";
    }

    // VALIDAR NOMBRE: SOLO LETRAS Y ESPACIOS
    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/', $nombre)) {
        $errores[] = "El nombre solo puede contener letras y espacios.";
    }

    // VALIDAR CONTRASEÑA
    $regexPass = '/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';
    if (!preg_match($regexPass, $contrasena)) {
        $errores[] = "La contraseña debe tener mínimo 8 caracteres, 1 mayúscula, 1 número y 1 carácter especial.";
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

        header("Location: admin_usuarios.php?editado=1");
        exit;
    }
}

/* =========================
   CARGAR USUARIO
========================= */
$stmt = $conn->prepare("
    SELECT id, usuario, contrasena, correo, nombre, tipo, estatus, creado_en
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

<?php if ((int)$usuarioData['estatus'] === 0): ?>
    <div class="alert-error" style="margin-bottom: 12px;">
        Este usuario está <strong>INVALIDADO</strong> (estatus = 0). No debería poder iniciar sesión.
    </div>
<?php endif; ?>

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
            <input type="text" name="contrasena"
                   value="<?= htmlspecialchars($usuarioData['contrasena']) ?>"
                   required
                   pattern="(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                   title="Mínimo 8 caracteres, 1 mayúscula, 1 número y 1 carácter especial">
        </div>

        <div class="form-group">
            <label>Correo</label>
            <input type="email" name="correo" value="<?= htmlspecialchars($usuarioData['correo']) ?>" required>
        </div>

        <div class="form-group">
            <label>Nombre</label>
            <input type="text" name="nombre"
                   value="<?= htmlspecialchars($usuarioData['nombre']) ?>"
                   required
                   pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ ]+"
                   title="Solo letras y espacios">
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

        <!-- Este botón YA NO BORRA: invalida (estatus = 0) -->
        <button type="button" class="btn-eliminar" onclick="confirmarEliminar(<?= (int)$usuarioData['id'] ?>)">
            Invalidar usuario
        </button>
    </div>
</form>

</main>
</div>

<script>
function confirmarEliminar(id) {
    if (confirm("¿Seguro que quieres INVALIDAR este usuario?\nNo se borrarán pedidos/ventas, pero ya no podrá iniciar sesión.")) {
        const f = document.createElement("form");
        f.method = "POST";

        const i = document.createElement("input");
        i.type = "hidden";
        i.name = "eliminar_usuario"; // se queda igual para reutilizar tu lógica
        i.value = id;

        f.appendChild(i);
        document.body.appendChild(f);
        f.submit();
    }
}
</script>

</body>
</html>



