<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost","walmartuser", "1234","walmart");
$conn->set_charset("utf8mb4");

// SOLO OPERADOR
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_tipo"] ?? '') !== "operador") {
    header("Location: login.php");
    exit;
}

$id = intval($_GET["id"]);

// Obtener pedido
$stmt = $conn->prepare("SELECT estatus FROM pedidos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    die("Pedido no encontrado.");
}

$estatusActual = $res["estatus"];

$nuevoEstatus = $estatusActual === "en preparación"
                ? "en ruta"
                : "entregado";

// Actualizar
$stmt = $conn->prepare("UPDATE pedidos SET estatus = ? WHERE id = ?");
$stmt->bind_param("si", $nuevoEstatus, $id);
$stmt->execute();
$stmt->close();

header("Location: operador_pedidos.php");
exit;
