<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

// Si ya está logueado, redirigir según rol
if (!empty($_SESSION['user_id'])) {
    if ($_SESSION["user_tipo"] === "administrador") {
        header("Location: admin.php");
        exit;
    } elseif ($_SESSION["user_tipo"] === "operador") {
        header("Location: operador.php");
        exit;
    } else {
        header("Location: index.php");
        exit;
    }
}

$errores = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usuarioInput  = trim($_POST["usuario"] ?? "");
    $password      = trim($_POST["password"] ?? "");

    /* =========================
       1) VALIDACIONES DE FORMATO
       (sin consultar BD)
    ========================= */

    if ($usuarioInput === "") {
        $errores[] = "Debes ingresar tu usuario o correo.";
    } else {
        // Heurística: si contiene '.' o '@' asumimos que está intentando usar CORREO
        $pareceCorreo = (strpos($usuarioInput, '@') !== false) || (strpos($usuarioInput, '.') !== false);

        if ($pareceCorreo) {
            if (strpos($usuarioInput, '@') === false) {
                $errores[] = "Te falta un arroba (@) en el correo.";
            } else if (!filter_var($usuarioInput, FILTER_VALIDATE_EMAIL)) {
                $errores[] = "El correo no tiene un formato válido.";
            }
        }
        // Si no parece correo, lo tratamos como usuario (sin reglas extra)
    }

    if ($password === "") {
        $errores[] = "Debes ingresar tu contraseña.";
    } else {
        // Mensajes por partes (qué falta)
        if (strlen($password) < 8) {
            $errores[] = "La contraseña debe tener mínimo 8 caracteres.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errores[] = "A la contraseña le falta al menos 1 letra mayúscula.";
        }
        if (!preg_match('/\d/', $password)) {
            $errores[] = "A la contraseña le falta al menos 1 número.";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errores[] = "A la contraseña le falta al menos 1 carácter especial.";
        }
    }

    /* =========================
       2) SOLO SI FORMATO OK -> BD
    ========================= */
    if (empty($errores)) {

        $stmt = $conn->prepare("
            SELECT id, usuario, correo, contrasena, tipo, estatus
            FROM usuarios
            WHERE usuario = ? OR correo = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $usuarioInput, $usuarioInput);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        // Si no existe, mensaje genérico (porque ya pasó formato)
        if (!$row) {
            $errores[] = "Usuario o contraseña incorrectos.";
        }
        // Si existe pero está invalidado (solo aparece si formato OK)
        elseif ((int)$row['estatus'] === 0) {
            $errores[] = "Este usuario ha sido invalidado. Contacta al administrador.";
        }
        // Existe pero contraseña no coincide
        elseif ($password !== $row["contrasena"]) {
            $errores[] = "Usuario o contraseña incorrectos.";
        }
        // Login correcto
        else {
            $_SESSION["user_id"]   = $row["id"];
            $_SESSION["user_tipo"] = $row["tipo"];
            $_SESSION["usuario"]   = $row["usuario"];

            if ($row["tipo"] === "administrador") {
                header("Location: admin.php");
                exit;
            } elseif ($row["tipo"] === "operador") {
                header("Location: operador.php");
                exit;
            } else {
                header("Location: index.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión - Mi tiendita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/login.css">
</head>
<body>

<div class="page">

<header class="header">
    <div class="header-inner">
        <div class="logo">
            <a href="index.php" class="logo-circle-link">
                <div class="logo-circle"><span class="logo-star">*</span></div>
            </a>
            <span class="logo-text">Mi tiendita</span>
        </div>
    </div>
</header>

<main class="main">
    <div class="login-card">

        <h1 class="login-title">Iniciar sesión</h1>
        <p class="login-subtitle">Ingresa tu usuario o correo y contraseña</p>

        <?php if (!empty($errores)): ?>
            <div class="alert-error">
                <?php foreach ($errores as $e): ?>
                    <p><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="login-form">

            <div class="form-group">
                <label>Usuario o correo</label>
                <input type="text" name="usuario" required value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="btn-primary">Ingresar</button>
        </form>

        <a class="forgot-link" href="recuperar_contrasena.php">
            ¿Olvidaste tu contraseña?
        </a>

        <p class="register-text">
            ¿No tienes cuenta?
            <a href="register.php">Regístrate</a>
        </p>

    </div>
</main>

</div>
</body>
</html>















