<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
header('Content-Type: application/json');
session_start();

$sessionId  = session_id(); // identificador del invitado
$usuario_id = $_SESSION['user_id'] ?? null; // si está logueado o no

$producto_id = (int)($_POST['producto_id'] ?? 0);
$accion      = $_POST['accion'] ?? ''; 

if ($producto_id <= 0 || !in_array($accion, ['add','remove','delete'], true)) {
    echo json_encode(["success" => false, "message" => "Datos inválidos."]);
    exit;
}

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

/* 1) Buscar carrito si esté logueado o no */
if ($usuario_id) {
    // Usuario logueado asiganamso el carrito por usuario_id
    $stmt = $conn->prepare("SELECT id FROM carrito WHERE usuario_id = ? LIMIT 1");
    $stmt->bind_param("i", $usuario_id);
} else {
    
    $stmt = $conn->prepare("SELECT id FROM carrito WHERE session_id = ? AND usuario_id IS NULL LIMIT 1");
    $stmt->bind_param("s", $sessionId);
}
$stmt->execute();
$res = $stmt->get_result();
$carrito = $res->fetch_assoc();
$stmt->close();

if ($carrito) {
    $carrito_id = (int)$carrito['id'];
} else {
    // Crear carrito nuevo si hay usuario, se guarda su id; si no, se deja NULL
    if ($usuario_id) {
        $stmt = $conn->prepare("INSERT INTO carrito (usuario_id, session_id, total) VALUES (?, ?, 0)");
        $stmt->bind_param("is", $usuario_id, $sessionId);
    } else {
        $stmt = $conn->prepare("INSERT INTO carrito (usuario_id, session_id, total) VALUES (NULL, ?, 0)");
        $stmt->bind_param("s", $sessionId);
    }
    $stmt->execute();
    $carrito_id = $stmt->insert_id;
    $stmt->close();
}
