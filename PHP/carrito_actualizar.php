<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

$estaLogueado = !empty($_SESSION['user_id']);
$usuario_id   = $estaLogueado ? (int)$_SESSION['user_id'] : null;
$sessionId    = session_id();

$producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
$accion      = $_POST['accion'] ?? '';

if ($producto_id <= 0 || !in_array($accion, ['add','remove','delete'], true)) {
  echo json_encode(['ok' => false, 'msg' => 'Parámetros inválidos']);
  exit;
}

/* 1) Obtener carrito actual (o crearlo si no existe) */
if ($estaLogueado) {
  $stmt = $conn->prepare("SELECT id FROM carrito WHERE usuario_id = ? LIMIT 1");
  $stmt->bind_param("i", $usuario_id);
} else {
  $stmt = $conn->prepare("SELECT id FROM carrito WHERE session_id = ? AND usuario_id IS NULL LIMIT 1");
  $stmt->bind_param("s", $sessionId);
}
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

$carrito_id = $row['id'] ?? null;

if (!$carrito_id) {
  // crear carrito vacío
  if ($estaLogueado) {
    $stmt = $conn->prepare("INSERT INTO carrito (usuario_id, session_id, total) VALUES (?, NULL, 0)");
    $stmt->bind_param("i", $usuario_id);
  } else {
    $stmt = $conn->prepare("INSERT INTO carrito (usuario_id, session_id, total) VALUES (NULL, ?, 0)");
    $stmt->bind_param("s", $sessionId);
  }
  $stmt->execute();
  $carrito_id = $stmt->insert_id;
  $stmt->close();
}

/* 2) Datos del producto (precio) */
$stmt = $conn->prepare("SELECT precio FROM producto WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$resP = $stmt->get_result();
$prod = $resP->fetch_assoc();
$stmt->close();

if (!$prod) {
  echo json_encode(['ok' => false, 'msg' => 'Producto no existe']);
  exit;
}

$precio = (float)$prod['precio'];

/* 3) Obtener cantidad actual en carrito_detalle */
$stmt = $conn->prepare("
  SELECT cantidad
  FROM carrito_detalle
  WHERE carrito_id = ? AND producto_id = ?
  LIMIT 1
");
$stmt->bind_param("ii", $carrito_id, $producto_id);
$stmt->execute();
$resD = $stmt->get_result();
$det  = $resD->fetch_assoc();
$stmt->close();

$cantidad_actual = (int)($det['cantidad'] ?? 0);

if ($accion === 'add') {
  $nueva = $cantidad_actual + 1;
} elseif ($accion === 'remove') {
  $nueva = $cantidad_actual - 1;
} else { // delete
  $nueva = 0;
}

/* 4) Aplicar cambios en carrito_detalle */
$conn->begin_transaction();

try {
  if ($nueva <= 0) {
    // borrar fila
    $stmt = $conn->prepare("DELETE FROM carrito_detalle WHERE carrito_id = ? AND producto_id = ?");
    $stmt->bind_param("ii", $carrito_id, $producto_id);
    $stmt->execute();
    $stmt->close();
    $item_subtotal = 0;
    $item_qty = 0;
  } else {
    $item_subtotal = $nueva * $precio;

    if ($cantidad_actual <= 0) {
      // insertar
      $stmt = $conn->prepare("
        INSERT INTO carrito_detalle (carrito_id, producto_id, cantidad, subtotal)
        VALUES (?, ?, ?, ?)
      ");
      $stmt->bind_param("iiid", $carrito_id, $producto_id, $nueva, $item_subtotal);
      $stmt->execute();
      $stmt->close();
    } else {
      // actualizar
      $stmt = $conn->prepare("
        UPDATE carrito_detalle
        SET cantidad = ?, subtotal = ?
        WHERE carrito_id = ? AND producto_id = ?
      ");
      $stmt->bind_param("idii", $nueva, $item_subtotal, $carrito_id, $producto_id);
      $stmt->execute();
      $stmt->close();
    }

    $item_qty = $nueva;
  }

  /* 5) Recalcular total del carrito y total_items */
  $stmt = $conn->prepare("
    SELECT COALESCE(SUM(subtotal),0) AS total,
           COALESCE(SUM(cantidad),0) AS total_items
    FROM carrito_detalle
    WHERE carrito_id = ?
  ");
  $stmt->bind_param("i", $carrito_id);
  $stmt->execute();
  $resT = $stmt->get_result();
  $tot  = $resT->fetch_assoc();
  $stmt->close();

  $total_carrito = (float)$tot['total'];
  $total_items   = (int)$tot['total_items'];

  $stmt = $conn->prepare("UPDATE carrito SET total = ? WHERE id = ?");
  $stmt->bind_param("di", $total_carrito, $carrito_id);
  $stmt->execute();
  $stmt->close();

  $conn->commit();

  echo json_encode([
    'ok' => true,
    'total_carrito' => $total_carrito,
    'total_items' => $total_items,
    'item_qty' => $item_qty,
    'item_subtotal' => $item_subtotal
  ]);
} catch (Throwable $e) {
  $conn->rollback();
  echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}

