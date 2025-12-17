<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$accion = $data['accion'] ?? '';
$producto_id = isset($data['producto_id']) ? (int)$data['producto_id'] : 0;

if (!in_array($accion, ['sumar','restar','eliminar'], true) || $producto_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Solicitud inválida']);
    exit;
}

$estaLogueado = !empty($_SESSION['user_id']);
$usuario_id   = $estaLogueado ? (int)$_SESSION['user_id'] : null;
$sessionId    = session_id();

$conn->begin_transaction();

try {
    if ($estaLogueado) {
        $stmt = $conn->prepare("SELECT id FROM carrito WHERE usuario_id = ? LIMIT 1");
        $stmt->bind_param("i", $usuario_id);
    } else {
        $stmt = $conn->prepare("SELECT id FROM carrito WHERE session_id = ? AND usuario_id IS NULL LIMIT 1");
        $stmt->bind_param("s", $sessionId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $car = $res->fetch_assoc();
    $stmt->close();

    $carrito_id = $car['id'] ?? null;
    if (!$carrito_id) {
        $conn->commit();
        echo json_encode(['ok' => true, 'deleted' => true, 'cantidad' => 0, 'subtotal' => 0, 'total_items' => 0, 'total_carrito' => 0]);
        exit;
    }

    $stmt = $conn->prepare("SELECT cantidad FROM carrito_detalle WHERE carrito_id = ? AND producto_id = ? LIMIT 1");
    $stmt->bind_param("ii", $carrito_id, $producto_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    $cantidadActual = $row ? (int)$row['cantidad'] : 0;
    if ($cantidadActual <= 0) {
        $stmt = $conn->prepare("SELECT SUM(cantidad) total_items, COALESCE(SUM(subtotal),0) total_carrito FROM carrito_detalle WHERE carrito_id = ?");
        $stmt->bind_param("i", $carrito_id);
        $stmt->execute();
        $tot = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total_items = (int)($tot['total_items'] ?? 0);
        $total_carrito = (float)($tot['total_carrito'] ?? 0);

        $stmt = $conn->prepare("UPDATE carrito SET total = ? WHERE id = ?");
        $stmt->bind_param("di", $total_carrito, $carrito_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['ok' => true, 'deleted' => true, 'cantidad' => 0, 'subtotal' => 0, 'total_items' => $total_items, 'total_carrito' => $total_carrito]);
        exit;
    }

    $stmt = $conn->prepare("SELECT precio FROM producto WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $prod = $res->fetch_assoc();
    $stmt->close();

    $precio = (float)($prod['precio'] ?? 0);

    $deleted = false;
    $nuevaCantidad = $cantidadActual;

    if ($accion === 'sumar') {
        $nuevaCantidad = $cantidadActual + 1;
        $nuevoSubtotal = $precio * $nuevaCantidad;

        $stmt = $conn->prepare("UPDATE carrito_detalle SET cantidad = ?, subtotal = ? WHERE carrito_id = ? AND producto_id = ?");
        $stmt->bind_param("idii", $nuevaCantidad, $nuevoSubtotal, $carrito_id, $producto_id);
        $stmt->execute();
        $stmt->close();
    }

    if ($accion === 'restar') {
        $nuevaCantidad = $cantidadActual - 1;

        if ($nuevaCantidad <= 0) {
            $stmt = $conn->prepare("DELETE FROM carrito_detalle WHERE carrito_id = ? AND producto_id = ?");
            $stmt->bind_param("ii", $carrito_id, $producto_id);
            $stmt->execute();
            $stmt->close();

            $deleted = true;
            $nuevaCantidad = 0;
            $nuevoSubtotal = 0;
        } else {
            $nuevoSubtotal = $precio * $nuevaCantidad;

            $stmt = $conn->prepare("UPDATE carrito_detalle SET cantidad = ?, subtotal = ? WHERE carrito_id = ? AND producto_id = ?");
            $stmt->bind_param("idii", $nuevaCantidad, $nuevoSubtotal, $carrito_id, $producto_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($accion === 'eliminar') {
        $stmt = $conn->prepare("DELETE FROM carrito_detalle WHERE carrito_id = ? AND producto_id = ?");
        $stmt->bind_param("ii", $carrito_id, $producto_id);
        $stmt->execute();
        $stmt->close();

        $deleted = true;
        $nuevaCantidad = 0;
        $nuevoSubtotal = 0;
    }

    $stmt = $conn->prepare("SELECT SUM(cantidad) total_items, COALESCE(SUM(subtotal),0) total_carrito FROM carrito_detalle WHERE carrito_id = ?");
    $stmt->bind_param("i", $carrito_id);
    $stmt->execute();
    $tot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total_items = (int)($tot['total_items'] ?? 0);
    $total_carrito = (float)($tot['total_carrito'] ?? 0);

    $stmt = $conn->prepare("UPDATE carrito SET total = ? WHERE id = ?");
    $stmt->bind_param("di", $total_carrito, $carrito_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'deleted' => $deleted,
        'cantidad' => $nuevaCantidad,
        'subtotal' => $nuevoSubtotal,
        'total_items' => $total_items,
        'total_carrito' => $total_carrito
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}



