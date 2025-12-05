<?php
// carrito_actualizar.php – Manejo del carrito (invitado + logueado)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
header('Content-Type: application/json; charset=utf-8');
session_start();

try {
    $sessionId  = session_id();
    $usuario_id = $_SESSION['user_id'] ?? null;

    $producto_id = (int)($_POST['producto_id'] ?? 0);
    $accion      = $_POST['accion'] ?? ''; // add | remove | delete

    if ($producto_id <= 0 || !in_array($accion, ['add', 'remove', 'delete'], true)) {
        echo json_encode(["success" => false, "message" => "Datos inválidos."]);
        exit;
    }

    $conn = new mysqli("localhost", "root", "", "walmart");
    $conn->set_charset("utf8mb4");

    /* 1) Buscar carrito (por usuario si está logueado, o por session_id si es invitado) */
    if ($usuario_id) {
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
    $res = $stmt->get_result();
    $producto = $res->fetch_assoc();
    $stmt->close();

    if (!$producto) {
        echo json_encode(["success" => false, "message" => "Producto no encontrado."]);
        exit;
    }

    $precio = (float)$producto['precio'];
    $stock  = (int)$producto['stock'];

    /* 3) Leer detalle actual del carrito */
    $stmt = $conn->prepare("SELECT cantidad FROM carrito_detalle WHERE carrito_id = ? AND producto_id = ? LIMIT 1");
    $stmt->bind_param("ii", $carrito_id, $producto_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $detalle = $res->fetch_assoc();
    $stmt->close();

    $cantidad = $detalle ? (int)$detalle['cantidad'] : 0;

    /* 4) Aplicar acción */
    if ($accion === 'add') {
        if ($cantidad < $stock) {
            $cantidad++;
        } else {
            echo json_encode([
                "success" => false,
                "message" => "No hay más stock disponible."
            ]);
            exit;
        }
    } elseif ($accion === 'remove') {
        $cantidad--;
    } elseif ($accion === 'delete') {
        $cantidad = 0;
    }

    /* 5) Actualizar / borrar detalle */
    if ($cantidad <= 0) {
        $stmt = $conn->prepare("DELETE FROM carrito_detalle WHERE carrito_id = ? AND producto_id = ?");
        $stmt->bind_param("ii", $carrito_id, $producto_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $subtotal = $cantidad * $precio;

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
    $stmt = $conn->prepare("SELECT SUM(subtotal) AS total, SUM(cantidad) AS items FROM carrito_detalle WHERE carrito_id = ?");
    $stmt->bind_param("i", $carrito_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $totales = $res->fetch_assoc();
    $stmt->close();

    $total_carrito = (float)($totales['total'] ?? 0);
    $total_items   = (int)($totales['items'] ?? 0);

    // Guardar el total en la tabla carrito
    $stmt = $conn->prepare("UPDATE carrito SET total = ? WHERE id = ?");
    $stmt->bind_param("di", $total_carrito, $carrito_id);
    $stmt->execute();
    $stmt->close();

    /* 7) Respuesta final */
    echo json_encode([
        "success"       => true,
        "cantidad"      => $cantidad,
        "total_items"   => $total_items,
        "total_carrito" => $total_carrito
    ]);
    exit;

} catch (Throwable $e) {
    // Si algo truena, devolvemos error en JSON (para que no rompa response.json())
    echo json_encode([
        "success" => false,
        "message" => "Error en el servidor: " . $e->getMessage()
    ]);
    exit;
}
