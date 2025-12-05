<?php
// PHP/register.php – Formulario y lógica de registro

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// CONEXIÓN BD walmart
$conn = new mysqli("localhost", "root", "", "walmart");
$conn->set_charset('utf8mb4');

$errores = [];
$exito   = "";

// Valores para repoblar formulario
$nombre  = "";
$correo  = "";

// SI VIENE POST => PROCESA REGISTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre     = trim($_POST['nombre'] ?? '');
    $correo     = trim($_POST['correo'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $confirm    = $_POST['contrasena_confirm'] ?? '';

    // VALIDACIONES
    if (strlen($nombre) < 3) {
        $errores[] = "El nombre es muy corto.";
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Correo no válido.";
    }

    // VALIDACIÓN CONTRASEÑA SEGURA
    if (!preg_match('/[A-Z]/', $contrasena)) {
        $errores[] = "La contraseña debe incluir al menos una letra mayúscula.";
    }

    if (!preg_match('/[0-9]/', $contrasena)) {
        $errores[] = "La contraseña debe incluir al menos un número.";
    }

    if (!preg_match('/[\W_]/', $contrasena)) {
        $errores[] = "La contraseña debe incluir un carácter especial.";
    }

    if (strlen($contrasena) < 8) {
        $errores[] = "La contraseña debe tener al menos 8 caracteres.";
    }

    if ($contrasena !== $confirm) {
        $errores[] = "Las contraseñas no coinciden.";
    }

    if (empty($errores)) {

        try {
            // Verificar si el correo ya existe
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo = ? LIMIT 1");
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->fetch_assoc()) {
                $errores[] = "Ese correo ya está registrado.";
            }
            $stmt->close();

            if (empty($errores)) {

                $usuario = $correo;
                $tipo    = "cliente";

                // GUARDAR SIN HASH (como pediste)
                $stmt = $conn->prepare("
                    INSERT INTO usuarios (usuario, contrasena, correo, nombre, tipo, creado_en)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("sssss", $usuario, $contrasena, $correo, $nombre, $tipo);
                $stmt->execute();
                $stmt->close();

                $exito   = "Cuenta creada correctamente. Ya puedes iniciar sesión.";
                $nombre  = "";
                $correo  = "";
            }

        } catch (Exception $e) {
            $errores[] = "Error en el servidor: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear cuenta - Mi tiendita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="../CSS/registro.css">

    <style>
        ::placeholder {
            color: #999;
            opacity: 1;
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-left">
        <div class="logo">
            <span class="logo-icon">*</span>
            <span class="logo-text">Mi tiendita</span>
        </div>

        <div class="search-bar">
            <input type="text" placeholder="¿Cómo quieres tus artículos?">
        </div>
    </div>
</header>

<main class="main-container">
    <section class="card" style="max-width:420px;">
        <h1 style="text-align:center;margin-bottom:20px;">Crear cuenta</h1>

        <?php if (!empty($errores)): ?>
            <div class="message message-error">
                <?php foreach ($errores as $e): ?>
                    <p><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($exito): ?>
            <div class="message message-success">
                <p><?= htmlspecialchars($exito) ?></p>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">

            <div class="form-group">
                <label>Nombre completo</label>
                <input type="text" name="nombre" required
                       placeholder="Ej: Juan Pérez López"
                       value="<?= htmlspecialchars($nombre) ?>">
            </div>

            <div class="form-group">
                <label>Correo electrónico</label>
                <input type="email" name="correo" required
                       placeholder="correo@ejemplo.com"
                       value="<?= htmlspecialchars($correo) ?>">
            </div>

            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="contrasena" required
                       placeholder="Mínimo 8 caracteres, 1 mayúscula, 1 número y 1 símbolo">
            </div>

            <div class="form-group">
                <label>Confirmar contraseña</label>
                <input type="password" name="contrasena_confirm" required
                       placeholder="Repite tu contraseña">
            </div>

            <button type="submit" class="btn-primary">Crear cuenta</button>
        </form>

        <p style="text-align:center;margin-top:15px;">
            ¿Ya tienes cuenta?
            <a href="login.php">Iniciar sesión</a>
        </p>
    </section>
</main>

</body>
</html>

