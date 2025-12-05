<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

$conn = new mysqli("localhost", "root", "", "walmart");
$conn->set_charset("utf8mb4");

$estaLogueado = !empty($_SESSION['user_id']);
$usuario_id   = $estaLogueado ? (int)$_SESSION['user_id'] : null;
$sessionId    = session_id();

/* 1) OBTENER CARRITO ACTUAL (Misma lógica que en index.php) */
if ($estaLogueado) {
    $stmt = $conn->prepare("SELECT id FROM carrito WHERE usuario_id = ? LIMIT 1");
    $stmt->bind_param("i", $usuario_id);
} else {
    $stmt = $conn->prepare("
        SELECT id
        FROM carrito
        WHERE session_id = ?
          AND (usuario_id IS NULL OR usuario_id = 0)
        LIMIT 1
    ");
    $stmt->bind_param("s", $sessionId);
}
$stmt->execute();
$resCar = $stmt->get_result();
$carrito = $resCar->fetch_assoc();
$stmt->close();

if (!$carrito) {
    echo "No tienes ningún carrito activo.";
    exit;
}

$carrito_id = (int)$carrito['id'];

/* 2) LEER DETALLES DEL CARRITO + STOCK ACTUAL DEL PRODUCTO */
$stmt = $conn->prepare("
    SELECT cd.producto_id,
           cd.cantidad,
           p.stock,
           p.nombre
    FROM carrito_detalle cd
    JOIN producto p ON p.id = cd.producto_id
    WHERE cd.carrito_id = ?
");
$stmt->bind_param("i", $carrito_id);
$stmt->execute();
$resDet = $stmt->get_result();
$items = $resDet->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($items)) {
    echo "Tu carrito está vacío.";
    exit;
}

/* 3) VERIFICAR SI HAY STOCK SUFICIENTE */
$faltantes = [];

foreach ($items as $it) {
    if ($it['cantidad'] > $it['stock']) {
        $faltantes[] = [
            'nombre'    => $it['nombre'],
            'pedido'    => (int)$it['cantidad'],
            'disponible'=> (int)$it['stock']
        ];
    }
}

if (!empty($faltantes)) {
    // NO HAY STOCK SUFICIENTE, NO SE HACE LA COMPRA
    echo "<h2>No hay stock suficiente de los siguientes productos:</h2>";
    echo "<ul>";
    foreach ($faltantes as $f) {
        echo "<li>"
           . htmlspecialchars($f['nombre'])
           . " — Pediste: {$f['pedido']}, disponibles: {$f['disponible']}"
           . "</li>";
    }
    echo "</ul>";
    echo '<p><a href="carrito.php">Volver al carrito</a></p>';
    exit;
}

/* 4) HAY STOCK → DESCONTARLO Y (OPCIONAL) CREAR PEDIDO */
$conn->begin_transaction();

try {
    // Descontar stock producto por producto
    $stmtUpd = $conn->prepare("
        UPDATE producto
        SET stock = stock - ?
        WHERE id = ?
    ");

    foreach ($items as $it) {
        $cantidad   = (int)$it['cantidad'];
        $productoId = (int)$it['producto_id'];
        $stmtUpd->bind_param("ii", $cantidad, $productoId);
        $stmtUpd->execute();
    }
    $stmtUpd->close();

    // Aquí podrías crear el pedido y pedido_detalle si ya tienes esas tablas

    // Vaciar carrito
    $stmt = $conn->prepare("DELETE FROM carrito_detalle WHERE carrito_id = ?");
    $stmt->bind_param("i", $carrito_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM carrito WHERE id = ?");
    $stmt->bind_param("i", $carrito_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo "<h2>Compra realizada con éxito ✅</h2>";
    echo '<p><a href="index.php">Volver a la tienda</a></p>';

} catch (Exception $e) {
    $conn->rollback();
    echo "Ocurrió un error al procesar la compra.";
}
