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
       1) VALIDACIONES LOCALES
       (antes de consultar BD)
    ========================= */

    if ($usuarioInput === "") {
        $errores[] = "Debes ingresar tu usuario o correo.";
    } else {
        // Si parece correo (contiene @), validamos formato
        if (strpos($usuarioInput, '@') !== false) {
            if (!filter_var($usuarioInput, FILTER_VALIDATE_EMAIL)) {
                $errores[] = "El correo no tiene un formato válido.";
            }
        }
        // Si NO es correo, lo tomamos como usuario y no exigimos formato especial
        // (si quieres, aquí puedes validar mínimo de caracteres del usuario)
    }

    if ($password === "") {
        $errores[] = "Debes ingresar tu contraseña.";
    } else {
        // Contraseña: mínimo 8, 1 mayúscula, 1 número, 1 caracter especial
        $regexPass = '/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';
        if (!preg_match($regexPass, $password)) {
            $errores[] = "La contraseña debe tener mínimo 8 caracteres, 1 mayúscula, 1 número y 1 carácter especial.";
        }
    }

    /* =========================
       2) SI TODO OK -> CONSULTAR BD
    ========================= */
    if (empty($errores)) {

        // Buscar usuario (NO filtramos estatus aquí)
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

        // No existe
        if (!$row) {
            $errores[] = "Usuario o contraseña incorrectos.";
        }
        // Existe pero invalidado
        elseif ((int)$row['estatus'] === 0) {
            $errores[] = "Este usuario ha sido invalidado. Contacta al administrador.";
        }
        // Contraseña incorrecta (ya validamos formato, aquí validamos match)
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














