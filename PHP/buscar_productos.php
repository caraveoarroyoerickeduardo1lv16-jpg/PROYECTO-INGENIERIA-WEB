<?php
// PHP/buscar_productos.php – Devuelve sugerencias de productos en JSON

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "root", "", "walmart");
$conn->set_charset("utf8mb4");

$q = trim($_GET['q'] ?? '');

if ($q === '' || mb_strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

// Solo productos que EMPIEZAN con el texto escrito
$like = $q . '%';

$stmt = $conn->prepare("
    SELECT id, nombre, marca, precio
    FROM producto
    WHERE nombre LIKE ? OR marca LIKE ?
    ORDER BY nombre ASC
    LIMIT 5
");
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$res = $stmt->get_result();

$sugerencias = [];
while ($row = $res->fetch_assoc()) {
    $sugerencias[] = $row;
}
$stmt->close();

echo json_encode($sugerencias);

