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

    if ($usuarioInput === "" || $password === "") {
        $errores[] = "Todos los campos son obligatorios.";
    } else {

        // 1️⃣ Buscar usuario (NO filtramos estatus aquí)
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

        // 2️⃣ No existe
        if (!$row) {
            $errores[] = "Usuario o contraseña incorrectos.";
        }
        // 3️⃣ Existe pero está invalidado
        elseif ((int)$row['estatus'] === 0) {
            $errores[] = "Este usuario ha sido invalidado. Contacta al administrador.";
        }
        // 4️⃣ Contraseña incorrecta
        elseif ($password !== $row["contrasena"]) {
            $errores[] = "Usuario o contraseña incorrectos.";
        }
        // 5️⃣ Login correcto
        else {

            $_SESSION["user_id"]   = $row["id"];
            $_SESSION["user_tipo"] = $row["tipo"];
            $_SESSION["usuario"]   = $row["usuario"];

            if ($row["tipo"] === "administrador") {
                header("Location: admin.php");
                exit;
            }

            if ($row["tipo"] === "operador") {
                header("Location: operador.php");
                exit;
            }

            header("Location: index.php");
            exit;
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
                <input type="text" name="usuario" required>
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













