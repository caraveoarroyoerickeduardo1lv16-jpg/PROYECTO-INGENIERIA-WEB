<?php

$host = "localhost";
$db   = "walmart"; 
$user = "walmartuser";
$pass = "1234"; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error en la conexión a la base de datos"]);
    exit;
}
