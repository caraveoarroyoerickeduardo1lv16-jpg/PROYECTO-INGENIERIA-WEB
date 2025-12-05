<?php
// logout.php – Cierra la sesión y regresa al inicio

session_start();
session_unset();
session_destroy();

// Opcional: borrar la cookie de sesión del navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirigir al home
header("Location: ../PHP/index.php");
exit;


