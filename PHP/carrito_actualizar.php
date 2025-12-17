<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
header('Content-Type: application/json; charset=utf-8');
session_start();

try {
    $sessionId  = session_id();
    $usuario_id = $_SESSION['user_id'] ?? null;

    $producto_id = (int)($_POST['producto_id'] ?? 0);
    $accion      = $_POST['accion'] ?? '';

    if ($producto_id <= 0 || !in_array($accion, ['add', 'remove', 'delete'], true)) {
        echo json_encode([
            "success" => false,
            "ok" => false,
            "message" => "Datos inválidos.",
            "msg" => "Datos inválidos.",
            "session_id" => $sessionId
        ]);
        exit;
    }

    $conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
    $conn->set_charset("utf8mb4");

    /* 1) Buscar carrito por usuario si está logueado, o por session_id si es invitado */
    if ($usuario_id) {
        $stmt = $conn->prepare("SELECT id FROM carrito WHERE usuario_id = ? LIMIT 1");
        $stmt->bind_param("i", $usuario_id);
    } else {
        $stmt = $conn->prepare("SELECT id FROM carrito WHERE session_id = ? AND usuario_id IS NULL LIMIT 1");
        $stmt->bind_param("s", $sessionId);
    }
    $stmt->execute();
    $carrito = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($carrito) {
        $carrito_id = (int)$carrito['id'];
    } else {
        // Crear carrito nuevo
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

    /* 2) Obtener info del producto */
    $stmt = $conn->prepare("SELECT precio, stock FROM producto WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$producto) {
        echo json_encode([
            "success" => false,
            "ok" => false,
            "message" => "Producto no encontrado.",
            "msg" => "Producto no encontrado.",
            "session_id" => $sessionId,
            "carrito_id" => $carrito_id
        ]);
        exit;
    }

    $precio = (float)$producto['precio'];
    $stock  = (int)$producto['stock'];

    /* 3) Leer detalle actual */
    $stmt = $conn->prepare("SELECT cantidad FROM carrito_detalle WHERE carrito_id = ? AND producto_id = ? LIMIT 1");
    $stmt->bind_param("ii", $carrito_id, $producto_id);
    $stmt->execute();
    $detalle = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cantidad = $detalle ? (int)$detalle['cantidad'] : 0;

    /* 4) Aplicar acción */
    if ($accion === 'add') {
        if ($cantidad < $stock) {
            $cantidad++;
        } else {
            echo json_encode([
                "success" => false,
                "ok" => false,
                "message" => "No hay más stock disponible.",
                "msg" => "No hay más stock disponible.",
                "session_id" => $sessionId,
                "carrito_id" => $carrito_id
            ]);
            exit;
        }
    } elseif ($accion === 'remove') {
        $cantidad--;
    } elseif ($accion === 'delete') {
        $cantidad = 0;
    }

    /* 5) Actualizar/borrar detalle */
    $item_subtotal = 0.0;

    if ($cantidad <= 0) {
        $stmt = $conn->prepare("DELETE FROM carrito_detalle WHERE carrito_id = ? AND producto_id = ?");
        $stmt->bind_param("ii", $carrito_id, $producto_id);
        $stmt->execute();
        $stmt->close();
        $cantidad = 0;
        $item_subtotal = 0.0;
    } else {
        $subtotal = $cantidad * $precio;
        $item_subtotal = $subtotal;

        if ($detalle) {
            $stmt = $conn->prepare("UPDATE carrito_detalle SET cantidad = ?, subtotal = ? WHERE carrito_id = ? AND producto_id = ?");
            $stmt->bind_param("idii", $cantidad, $subtotal, $carrito_id, $producto_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO carrito_detalle (carrito_id, producto_id, cantidad, subtotal)
                                    VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $carrito_id, $producto_id, $cantidad, $subtotal);
            $stmt->execute();
            $stmt->close();
        }
    }

    /* 6) Recalcular total del carrito */
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(subtotal),0) AS total,
               COALESCE(SUM(cantidad),0) AS items
        FROM carrito_detalle
        WHERE carrito_id = ?
    ");
    $stmt->bind_param("i", $carrito_id);
    $stmt->execute();
    $totales = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total_carrito = (float)($totales['total'] ?? 0);
    $total_items   = (int)($totales['items'] ?? 0);

    // Guardar total en carrito
    $stmt = $conn->prepare("UPDATE carrito SET total = ? WHERE id = ?");
    $stmt->bind_param("di", $total_carrito, $carrito_id);
    $stmt->execute();
    $stmt->close();

    // Textos listos para header
    $header_items_text = $total_items . " artículo" . ($total_items === 1 ? "" : "s");
    $header_total_text = "$" . number_format($total_carrito, 2, ".", "");

    echo json_encode([
        "success"       => true,
        "ok"            => true,

        "cantidad"      => $cantidad,
        "total_items"   => $total_items,
        "total_carrito" => $total_carrito,

        "item_qty"      => $cantidad,
        "item_subtotal" => $item_subtotal,

        "header_items_text" => $header_items_text,
        "header_total_text" => $header_total_text,

        "session_id" => $sessionId,
        "carrito_id" => $carrito_id,

        "message" => "OK",
        "msg"     => "OK"
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "ok" => false,
        "message" => "Error en el servidor: " . $e->getMessage(),
        "msg" => "Error en el servidor: " . $e->getMessage(),
        "session_id" => session_id()
    ]);
    exit;
}


