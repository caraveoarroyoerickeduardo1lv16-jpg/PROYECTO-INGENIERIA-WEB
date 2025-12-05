<?php

$host = "localhost";
$db   = "walmart"; // <-- CAMBIA ESTO AL NOMBRE DE TU BD
$user = "walmartuser";
$pass = "1234"; // si tienes contraseña en MySQL cámbiala aquí

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la conexión a la base de datos"]);
    exit;
}
