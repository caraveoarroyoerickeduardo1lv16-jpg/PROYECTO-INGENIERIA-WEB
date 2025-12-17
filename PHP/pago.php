<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

date_default_timezone_set('America/Mexico_City');

$usuario_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
if (!$usuario_id) {
    header("Location: login.php");
    exit;
}

function parseStartHour($label) {
    $label = trim((string)$label);
    if (!preg_match('/^(\d{1,2})(am|pm)\s*-\s*(\d{1,2})(am|pm)$/i', $label, $m)) return null;
    $h = (int)$m[1];
    $ap = strtolower($m[2]);
    if ($h === 12) $h = 0;
    if ($ap === 'pm') $h += 12;
    return $h; // 0-23
}

$confirmado = isset($_GET['confirmado']) && $_GET['confirmado'] == '1';
$pedido_id_confirm = $confirmado ? (int)($_GET['pedido_id'] ?? 0) : 0;

$errores = [];
$mensaje_exito = "";
$faltantes = [];

/* Guardar horario y día enviado desde checkout */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['horario'])) {
        $_SESSION['horario_envio'] = $_POST['horario'];
    }
    if (isset($_POST['dia_envio'])) {
        $_SESSION['dia_envio'] = (int)$_POST['dia_envio']; // 0,1,2
    }
}

if (!$confirmado) {
    if (empty($_SESSION['direccion_id']) || empty($_SESSION['horario_envio'])) {
        header("Location: checkout.php");
        exit;
    }
}

if ($confirmado) {
    $mensaje_exito = ($pedido_id_confirm > 0)
        ? "Tu pago se realizó correctamente. Pedido #{$pedido_id_confirm}"
        : "Tu pago se realizó correctamente.";
}

if (!$confirmado) {

    $direccion_id  = (int)$_SESSION['direccion_id'];
    $horario_envio = $_SESSION['horario_envio'];
    $dia_envio     = isset($_SESSION['dia_envio']) ? (int)$_SESSION['dia_envio'] : 0;

    /* ✅ VALIDACIÓN SERVIDOR HORARIO (HOY: cierre 9pm + 2h antes) */
    $startHour   = parseStartHour($horario_envio);
    $now         = new DateTime('now');
    $currentHour = (int)$now->format('G'); // 0-23
    $limitHour   = $currentHour + 2;

    if ($dia_envio === 0) { // HOY
        if ($currentHour >= 21) {
            $errores[] = "Hoy ya cerramos (9pm). Selecciona mañana o pasado mañana.";
        } elseif ($startHour === null) {
            $errores[] = "Horario inválido.";
        } elseif ($startHour < $limitHour || $startHour >= 21) {
            $errores[] = "Ese horario ya no está disponible para hoy. Selecciona otro horario o elige mañana.";
        }
    }

    // Carrito
    $stmt = $conn->prepare("
        SELECT id, total
        FROM carrito
        WHERE usuario_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $carrito = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$carrito) {
        header("Location: carrito.php");
        exit;
    }

    $carrito_id  = (int)$carrito['id'];
    $subtotal    = (float)$carrito['total'];
    $costo_envio = 49.00;
    $total_pagar = $subtotal + $costo_envio;

    // Dirección
    $stmt = $conn->prepare("
        SELECT etiqueta, calle, colonia, ciudad, estado, cp
        FROM direcciones
        WHERE id = ? AND usuario_id = ?
    ");
    $stmt->bind_param("ii", $direccion_id, $usuario_id);
    $stmt->execute();
    $direccion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$direccion) {
        header("Location: checkout.php");
        exit;
    }

    /* ✅ ELIMINAR TARJETA (NUEVO) — oculta (usuario_id=0) */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_metodo_pago'])) {
        $id_mp = (int)$_POST['eliminar_metodo_pago'];

        if ($id_mp > 0) {
            $stmt = $conn->prepare("UPDATE metodos_pago SET usuario_id = 0 WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("ii", $id_mp, $usuario_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: pago.php");
        exit;
    }

    // Métodos de pago guardados (solo del usuario)
    $stmt = $conn->prepare("
        SELECT id, alias, marca, ultimos4, mes_exp, anio_exp
        FROM metodos_pago
        WHERE usuario_id = ?
        ORDER BY creada_en DESC
    ");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $resMP = $stmt->get_result();
    $metodos_guardados = [];
    while ($row = $resMP->fetch_assoc()) $metodos_guardados[] = $row;
    $stmt->close();

    // Procesar pago
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pagar'])) {

        if (empty($errores)) {

            $metodo_pago_id = $_POST['metodo_pago_id'] ?? null;
            if (!$metodo_pago_id) $errores[] = "Selecciona o registra un método de pago.";

            $metodo_id_real = null;

            if ($metodo_pago_id === 'nuevo') {
                $alias     = trim($_POST['alias'] ?? '');
                $titular   = trim($_POST['titular'] ?? '');
                $numeroRaw = $_POST['numero'] ?? '';
                $numero    = preg_replace('/\D/', '', $numeroRaw);
                $mes_exp   = (int)($_POST['mes_exp'] ?? 0);
                $anio_exp  = (int)($_POST['anio_exp'] ?? 0);

                if ($alias === '' || $titular === '' || $numero === '' || !$mes_exp || !$anio_exp) {
                    $errores[] = "Todos los campos de la nueva tarjeta son obligatorios.";
                }

                if (!preg_match('/^\d{16}$/', $numero)) {
                    $errores[] = "El número de tarjeta debe tener exactamente 16 dígitos numéricos.";
                }

                if ($mes_exp < 1 || $mes_exp > 12) $errores[] = "El mes de expiración no es válido.";

                $yearNow = (int)date('Y');
                if ($anio_exp < $yearNow || $anio_exp > $yearNow + 15) $errores[] = "El año de expiración no es válido.";

                if (empty($errores)) {
                    $monthNow = (int)date('n');
                    if ($anio_exp == $yearNow && $mes_exp < $monthNow) $errores[] = "La tarjeta está vencida.";
                }

                if (empty($errores)) {
                    $marca = 'Tarjeta';
                    if (preg_match('/^4/', $numero))           $marca = 'Visa';
                    elseif (preg_match('/^5[1-5]/', $numero)) $marca = 'MasterCard';
                    elseif (preg_match('/^3[47]/', $numero))  $marca = 'American Express';

                    $ultimos4 = substr($numero, -4);

                    $stmt = $conn->prepare("
                        INSERT INTO metodos_pago (usuario_id, alias, titular, marca, ultimos4, mes_exp, anio_exp, creada_en)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("issssii", $usuario_id, $alias, $titular, $marca, $ultimos4, $mes_exp, $anio_exp);
                    $stmt->execute();
                    $metodo_id_real = $conn->insert_id;
                    $stmt->close();
                }

            } else {
                $id_mp = (int)$metodo_pago_id;
                $stmt = $conn->prepare("SELECT id FROM metodos_pago WHERE id = ? AND usuario_id = ?");
                $stmt->bind_param("ii", $id_mp, $usuario_id);
                $stmt->execute();
                $ok = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($ok) $metodo_id_real = $id_mp;
                else $errores[] = "El método de pago seleccionado no es válido.";
            }

            // Stock
            if (empty($errores) && $metodo_id_real) {
                $stmt = $conn->prepare("
                    SELECT cd.producto_id, cd.cantidad, p.stock, p.nombre
                    FROM carrito_detalle cd
                    JOIN producto p ON p.id = cd.producto_id
                    WHERE cd.carrito_id = ?
                ");
                $stmt->bind_param("i", $carrito_id);
                $stmt->execute();
                $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                if (empty($items)) {
                    $errores[] = "Tu carrito está vacío.";
                } else {
                    foreach ($items as $it) {
                        if ((int)$it['cantidad'] > (int)$it['stock']) $faltantes[] = $it;
                    }
                    if (!empty($faltantes)) $errores[] = "No hay stock suficiente para algunos productos. Ajusta las cantidades en tu carrito.";
                }
            }

            if (empty($errores) && $metodo_id_real) {
                try {
                    $conn->begin_transaction();

                    // Descontar stock
                    $stmtUpd = $conn->prepare("UPDATE producto SET stock = stock - ? WHERE id = ?");
                    foreach ($items as $it) {
                        $cant = (int)$it['cantidad'];
                        $pid  = (int)$it['producto_id'];
                        $stmtUpd->bind_param("ii", $cant, $pid);
                        $stmtUpd->execute();
                    }
                    $stmtUpd->close();

                    // Insertar pedido (TU TABLA TRAE estatus, lo ponemos por default)
                    $estado  = 'pagado';
                    $estatus = 'en preparación';

                    $stmt = $conn->prepare("
                        INSERT INTO pedidos (usuario_id, carrito_id, direccion_id, total, estado, metodo_pago_id, horario_envio, creada_en, estatus)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ");
                    $stmt->bind_param("iiidsiss", $usuario_id, $carrito_id, $direccion_id, $total_pagar, $estado, $metodo_id_real, $horario_envio, $estatus);
                    $stmt->execute();
                    $pedido_id = $conn->insert_id;
                    $stmt->close();

                    // Copiar detalle
                    $stmt = $conn->prepare("
                        INSERT INTO pedido_detalle (pedido_id, producto_id, cantidad, precio_unit)
                        SELECT ?, cd.producto_id, cd.cantidad,
                               CASE WHEN cd.cantidad > 0 THEN cd.subtotal / cd.cantidad ELSE 0 END
                        FROM carrito_detalle cd
                        WHERE cd.carrito_id = ?
                    ");
                    $stmt->bind_param("ii", $pedido_id, $carrito_id);
                    $stmt->execute();
                    $stmt->close();

                    // Vaciar carrito
                    $stmt = $conn->prepare("DELETE FROM carrito_detalle WHERE carrito_id = ?");
                    $stmt->bind_param("i", $carrito_id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("DELETE FROM carrito WHERE id = ?");
                    $stmt->bind_param("i", $carrito_id);
                    $stmt->execute();
                    $stmt->close();

                    unset($_SESSION['horario_envio'], $_SESSION['direccion_id'], $_SESSION['dia_envio']);

                    $conn->commit();

                    header("Location: pago.php?confirmado=1&pedido_id=" . $pedido_id);
                    exit;

                } catch (Exception $e) {
                    $conn->rollback();
                    $errores[] = "Ocurrió un error al procesar el pago. Inténtalo de nuevo.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago - Mi Tiendita</title>
    <link rel="stylesheet" href="../CSS/pago.css">
</head>
<body>

<div class="page">
    <header class="header">
        <h1>Mi Tiendita</h1>
        <span class="header-sub">Pago</span>
    </header>

    <?php if ($confirmado): ?>

        <div class="card">
            <h2>Pago exitoso</h2>
            <p><?= htmlspecialchars($mensaje_exito) ?></p>
            <a href="index.php" class="btn-primary btn-full">Seguir comprando</a>
        </div>

    <?php else: ?>

        <?php if (!empty($errores)): ?>
            <div class="alert-error">
                <ul>
                    <?php foreach ($errores as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>

                <?php if (!empty($faltantes)): ?>
                    <ul>
                        <?php foreach ($faltantes as $f): ?>
                            <li><?= htmlspecialchars($f['nombre']) ?> — Pediste: <?= (int)$f['cantidad'] ?>, disponibles: <?= (int)$f['stock'] ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Resumen de tu pedido</h2>
            <div class="row"><span>Dirección</span><span><?= htmlspecialchars($direccion['etiqueta']) ?>: <?= htmlspecialchars($direccion['calle']) ?>, <?= htmlspecialchars($direccion['colonia']) ?>, <?= htmlspecialchars($direccion['ciudad']) ?>, <?= htmlspecialchars($direccion['estado']) ?>, CP <?= htmlspecialchars($direccion['cp']) ?></span></div>
            <div class="row"><span>Horario</span><span><?= htmlspecialchars($horario_envio) ?></span></div>
            <div class="row"><span>Subtotal</span><span>$<?= number_format($subtotal, 2) ?></span></div>
            <div class="row"><span>Envío</span><span>$<?= number_format($costo_envio, 2) ?></span></div>
            <div class="row total"><span>Total</span><span>$<?= number_format($total_pagar, 2) ?></span></div>
        </div>

        <form method="post" class="card" id="formPago">
            <h2>Métodos de pago</h2>

            <?php if (!empty($metodos_guardados)): ?>
                <div class="mp-list">
                    <?php foreach ($metodos_guardados as $mp): ?>
                        <div class="mp-row">
                            <label class="mp-card">
                                <input type="radio" name="metodo_pago_id" value="<?= (int)$mp['id'] ?>">
                                <div class="mp-info">
                                    <div class="mp-alias"><?= htmlspecialchars($mp['alias']) ?></div>
                                    <div class="mp-detalle"><?= htmlspecialchars($mp['marca']) ?> •••• <?= htmlspecialchars($mp['ultimos4']) ?> — Vence <?= sprintf('%02d/%d', $mp['mes_exp'], $mp['anio_exp']) ?></div>
                                </div>
                            </label>

                            <button type="submit"
                                    name="eliminar_metodo_pago"
                                    value="<?= (int)$mp['id'] ?>"
                                    class="btn-danger"
                                    formnovalidate
                                    onclick="return confirm('¿Seguro que quieres eliminar esta tarjeta?');">
                                Eliminar
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">Aún no tienes métodos de pago guardados.</p>
            <?php endif; ?>

            <h3>Nueva tarjeta</h3>
            <label class="mp-card nuevo">
                <input type="radio" name="metodo_pago_id" value="nuevo" checked>
                <div class="mp-info">
                    <div class="mp-alias">Pagar con nueva tarjeta</div>
                </div>
            </label>

            <div class="grid">
                <div class="field">
                    <label>Alias*</label>
                    <input type="text" name="alias" value="<?= htmlspecialchars($_POST['alias'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Titular*</label>
                    <input type="text" name="titular" value="<?= htmlspecialchars($_POST['titular'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Número (16 dígitos)*</label>
                    <input type="text" id="numeroTarjeta" name="numero" maxlength="16" inputmode="numeric"
                           pattern="\d{16}" title="16 dígitos numéricos"
                           value="<?= htmlspecialchars($_POST['numero'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Mes (MM)*</label>
                    <input type="number" name="mes_exp" min="1" max="12" value="<?= htmlspecialchars($_POST['mes_exp'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Año (AAAA)*</label>
                    <input type="number" name="anio_exp" value="<?= htmlspecialchars($_POST['anio_exp'] ?? '') ?>">
                </div>
            </div>

            <div class="actions">
                <a href="checkout.php?paso=3" class="btn-secondary">← Volver</a>
                <button type="submit" name="pagar" class="btn-primary">Pagar ahora</button>
            </div>
        </form>

    <?php endif; ?>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("numeroTarjeta");
    if (!input) return;

    const normalizar = () => input.value = input.value.replace(/\D/g, "").slice(0, 16);
    input.addEventListener("input", normalizar);
    input.addEventListener("paste", (e) => {
        e.preventDefault();
        const txt = (e.clipboardData || window.clipboardData).getData("text");
        input.value = (txt || "").replace(/\D/g, "").slice(0, 16);
    });
});
</script>

</body>
</html>




