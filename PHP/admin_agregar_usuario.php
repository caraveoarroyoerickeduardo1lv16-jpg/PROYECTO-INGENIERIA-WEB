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

$errores = [];
$exito   = "";

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario    = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $nombre     = trim($_POST['nombre'] ?? '');
    $correo     = trim($_POST['correo'] ?? '');
    $tipo       = trim($_POST['tipo'] ?? '');

    $tiposValidos = ['administrador', 'operador', 'cliente'];

    if ($usuario === '' || $contrasena === '' || $nombre === '' || $correo === '' || $tipo === '') {
        $errores[] = "Todos los campos son obligatorios.";
    }

    if (!in_array($tipo, $tiposValidos, true)) {
        $errores[] = "El rol seleccionado no es válido.";
    }

    // checar si elUsuario ya existe
    if (empty($errores)) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ? OR correo = ? LIMIT 1");
        $stmt->bind_param("ss", $usuario, $correo);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existe) {
            $errores[] = "Ya existe un usuario con ese nombre de usuario o correo.";
        }
    }

    // Insertar si todo está bien
    if (empty($errores)) {
        $stmt = $conn->prepare("
            INSERT INTO usuarios (usuario, contrasena, nombre, correo, tipo, creado_en)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("sssss", $usuario, $contrasena, $nombre, $correo, $tipo);
        $stmt->execute();
        $stmt->close();

        
        header("Location: admin_usuarios.php?filtro=todos");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar usuario - Mi Tiendita</title>
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
        <h1>Agregar usuario</h1>

        <?php if (!empty($errores)): ?>
            <div class="alert-error">
                <?php foreach ($errores as $e): ?>
                    <p><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-usuario">
            <div class="form-row">
                <label>Usuario</label>
                <input type="text" name="usuario" required>
            </div>

            <div class="form-row">
                <label>Contraseña</label>
                <input type="text" name="contrasena" required>
            </div>

            <div class="form-row">
                <label>Nombre</label>
                <input type="text" name="nombre" required>
            </div>

            <div class="form-row">
                <label>Correo</label>
                <input type="email" name="correo" required>
            </div>

            <div class="form-row">
                <label>Rol</label>
                <select name="tipo" required>
                    <option value="">Selecciona un rol</option>
                    <option value="administrador">Administrador</option>
                    <option value="operador">Operador</option>
                    <option value="cliente">Cliente</option>
                </select>
            </div>

            <div class="form-actions">
                <a href="admin_usuarios.php?filtro=todos" class="btn-cancelar">Cancelar</a>
                <button type="submit" class="btn-guardar">Guardar</button>
            </div>
        </form>
    </main>

</div>

</body>
</html>
