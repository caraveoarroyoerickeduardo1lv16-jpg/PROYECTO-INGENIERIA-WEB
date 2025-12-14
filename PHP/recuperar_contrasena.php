<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

$errores = [];
$exito = false;
$password = null;

$correo = $cp = $ultimos4 = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo   = trim($_POST["correo"] ?? "");
    $cp       = trim($_POST["cp"] ?? "");
    $ultimos4 = trim($_POST["ultimos4"] ?? "");

    if ($correo === "") {
        $errores[] = "El correo es obligatorio.";
    }

    if ($cp === "" && $ultimos4 === "") {
        $errores[] = "Debes validar con Código Postal o con tarjeta.";
    }

    if (empty($errores)) {

        $stmt = $conn->prepare("SELECT id, contrasena FROM usuarios WHERE correo = ? LIMIT 1");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $errores[] = "Correo no encontrado.";
        } else {

            $uid = (int)$user["id"];
            $valido = false;

            if ($cp !== "") {
                $stmt = $conn->prepare("
                    SELECT 1
                    FROM direcciones
                    WHERE usuario_id = ? AND cp = ?
                    LIMIT 1
                ");
                $stmt->bind_param("is", $uid, $cp);
                $stmt->execute();
                if ($stmt->get_result()->fetch_assoc()) $valido = true;
                $stmt->close();
            }

            if (!$valido && $ultimos4 !== "") {
                $stmt = $conn->prepare("
                    SELECT 1
                    FROM metodos_pago
                    WHERE usuario_id = ? AND ultimos4 = ?
                    LIMIT 1
                ");
                $stmt->bind_param("is", $uid, $ultimos4);
                $stmt->execute();
                if ($stmt->get_result()->fetch_assoc()) $valido = true;
                $stmt->close();
            }

            if ($valido) {
                $exito = true;
                $password = $user["contrasena"];
            } else {
                $errores[] = "Los datos no coinciden.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/recuperar.css">
</head>
<body>

<div class="card">
    <h2>Recuperar contraseña</h2>

    <?php if (!empty($errores)): ?>
        <div class="alert-error">
            <?php foreach ($errores as $e): ?>
                <p><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input
            type="email"
            name="correo"
            placeholder="Correo"
            required
            value="<?= htmlspecialchars($correo) ?>"
        >

        <input
            type="text"
            name="cp"
            placeholder="Código Postal (opcional)"
            value="<?= htmlspecialchars($cp) ?>"
        >

        <input
            type="text"
            name="ultimos4"
            placeholder="Últimos 4 de tarjeta (opcional)"
            value="<?= htmlspecialchars($ultimos4) ?>"
        >

        <button type="submit">Enviar</button>
    </form>

    <?php if ($exito): ?>
        <p>Esta es tu contraseña:</p>
        <p class="pass-blue"><?= htmlspecialchars($password) ?></p>
        <a href="login.php">Volver a iniciar sesión</a>
    <?php endif; ?>
</div>

</body>
</html>
