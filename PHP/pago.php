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

$confirmado = isset($_GET['confirmado']) && $_GET['confirmado'] == '1';
$pedido_id_confirm = $confirmado ? (int)($_GET['pedido_id'] ?? 0) : 0;

$errores = [];
$mensaje_exito = "";
$faltantes = [];

/* ===== helpers horario ===== */
function parseStartHour($label) {
    $label = trim($label);
    if (!preg_match('/^(\d{1,2})(am|pm)\-/i', $label, $m)) return null;
    $h = (int)$m[1];
    $ampm = strtolower($m[2]);
    if ($h < 1 || $h > 12) return null;

    if ($ampm === 'am') return ($h === 12) ? 0 : $h;
    return ($h === 12) ? 12 : ($h + 12);
}

function horarioEsValido($dia_envio, $horario_envio) {
    $dia_envio = (int)$dia_envio;
    $start = parseStartHour($horario_envio);
    if ($start === null) return false;

    if ($start < 9 || $start >= 21) return false;

    if ($dia_envio === 0) {
        $nowHour = (int)date('G');
        if ($nowHour >= 21) return false;
        if ($start < ($nowHour + 2)) return false;
    }
    return true;
}

/* guardar horario + día en sesión (desde checkout) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['horario'])) $_SESSION['horario_envio'] = $_POST['horario'];
    if (isset($_POST['dia_envio'])) $_SESSION['dia_envio'] = (int)$_POST['dia_envio'];
}

if (!$confirmado) {
    if (empty($_SESSION['direccion_id']) || empty($_SESSION['horario_envio'])) {
        header("Location: checkout.php");
        exit;
    }
    if (!isset($_SESSION['dia_envio'])) $_SESSION['dia_envio'] = 0;
}

if ($confirmado) {
    $mensaje_exito = $pedido_id_confirm > 0
        ? "Tu pago se realizó correctamente. Pedido #{$pedido_id_confirm}"
        : "Tu pago se realizó correctamente.";
}

if (!$confirmado) {

    $direccion_id  = (int)$_SESSION['direccion_id'];
    $horario_envio = (string)$_SESSION['horario_envio'];
    $dia_envio     = (int)($_SESSION['dia_envio'] ?? 0);

    /* bloqueo servidor */
    if (!horarioEsValido($dia_envio, $horario_envio)) {
        $_SESSION['checkout_error'] = "El horario seleccionado ya no está disponible (hoy cerramos a las 9pm o falta anticipación). Elige otro horario.";
        header("Location: checkout.php?paso=3");
        exit;
    }

    // carrito
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

    // dirección
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

    // métodos guardados
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

    // procesar pago
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pagar'])) {

        if (!horarioEsValido($dia_envio, $horario_envio)) {
            $errores[] = "El horario seleccionado ya no está disponible. Regresa y elige otro.";
        }

        $metodo_pago_id = $_POST['metodo_pago_id'] ?? null;
        if (!$metodo_pago_id) $errores[] = "Selecciona o registra un método de pago.";

        $metodo_id_real = null;

        // nueva tarjeta
        if (empty($errores) && $metodo_pago_id === 'nuevo') {
            $alias    = trim($_POST['alias'] ?? '');
            $titular  = trim($_POST['titular'] ?? '');
            $numero   = preg_replace('/\D/', '', $_POST['numero'] ?? '');
            $mes_exp  = (int)($_POST['mes_exp'] ?? 0);
            $anio_exp = (int)($_POST['anio_exp'] ?? 0);

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

        } elseif (empty($errores)) {
            // método guardado
            $id_mp = (int)$metodo_pago_id;
            $stmt = $conn->prepare("SELECT id FROM metodos_pago WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("ii", $id_mp, $usuario_id);
            $stmt->execute();
            $resChk = $stmt->get_result();
            $stmt->close();
            if ($resChk->fetch_assoc()) $metodo_id_real = $id_mp;
            else $errores[] = "El método de pago seleccionado no es válido.";
        }

        // stock
        $items = [];
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
                if (!empty($faltantes)) $errores[] = "No hay stock suficiente para algunos productos. Ajusta cantidades.";
            }
        }

        // crear pedido
        if (empty($errores) && $metodo_id_real) {
            $estado = 'pagado';

            try {
                $conn->begin_transaction();

                $stmtUpd = $conn->prepare("UPDATE producto SET stock = stock - ? WHERE id = ?");
                foreach ($items as $it) {
                    $cant = (int)$it['cantidad'];
                    $pid  = (int)$it['producto_id'];
                    $stmtUpd->bind_param("ii", $cant, $pid);
                    $stmtUpd->execute();
                }
                $stmtUpd->close();

                $stmt = $conn->prepare("
                    INSERT INTO pedidos (usuario_id, carrito_id, direccion_id, metodo_pago_id, horario_envio, total, estado, creada_en)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("iiiisds", $usuario_id, $carrito_id, $direccion_id, $metodo_id_real, $horario_envio, $total_pagar, $estado);
                $stmt->execute();
                $pedido_id = $conn->insert_id;
                $stmt->close();

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago - Mi Tiendita</title>
    <link rel="stylesheet" href="../CSS/checkout.css">

    <style>
        /* Solo “mejoras” visuales para pago, sin romper tu checkout.css */
        .pill {
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:6px 12px;
            border-radius:999px;
            background:#eef6ff;
            border:1px solid #dbeafe;
            color:#1e3a8a;
            font-size:12px;
            font-weight:700;
        }
        .muted { color:#6b7280; font-size:12px; }
        .section-subtitle { margin-top:10px; font-size:14px; font-weight:800; color:#111827; }

        /* Cards de métodos más bonitos */
        .mp-card{
            display:flex;
            align-items:center;
            gap:10px;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:10px;
            background:#fff;
            cursor:pointer;
            transition:.15s;
        }
        .mp-card:hover{ border-color:#0071e3; background:#f8fbff; }
        .mp-card input{ margin-right:6px; }
        .mp-info{ display:flex; flex-direction:column; gap:2px; }
        .mp-alias{ font-size:14px; font-weight:800; }
        .mp-detalle{ font-size:12px; color:#555; }

        .btn-link{
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }
    </style>
</head>
<body>

<div class="page">
    <header class="header header-brand">
        <div class="brand-row">
            <div class="brand-left">
                <div class="brand-badge">*</div>
                <div>
                    <h1>Mi Tiendita</h1>
                    <span class="header-sub">Pago</span>
                </div>
            </div>
        </div>
    </header>

    <?php if ($confirmado): ?>

        <div class="checkout-container">
            <h2 class="section-title">Pago exitoso</h2>
            <p><?= htmlspecialchars($mensaje_exito) ?></p>

            <a href="index.php" class="btn-primary btn-full btn-link">Seguir comprando</a>
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
                            <li>
                                <?= htmlspecialchars($f['nombre']) ?> —
                                Pediste: <?= (int)$f['cantidad'] ?>,
                                disponibles: <?= (int)$f['stock'] ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="checkout-container">
            <h2 class="section-title">Resumen de tu pedido</h2>

            <div class="pill">📍 Envío a domicilio</div>
            <div style="height:10px;"></div>

            <div class="resumen-row">
                <span>Dirección</span>
                <span class="muted">
                    <?= htmlspecialchars($direccion['etiqueta']) ?>:
                    <?= htmlspecialchars($direccion['calle']) ?>,
                    <?= htmlspecialchars($direccion['colonia']) ?>,
                    <?= htmlspecialchars($direccion['ciudad']) ?>,
                    <?= htmlspecialchars($direccion['estado']) ?>,
                    CP <?= htmlspecialchars($direccion['cp']) ?>
                </span>
            </div>

            <div class="resumen-row">
                <span>Horario</span>
                <span class="muted"><?= htmlspecialchars($horario_envio) ?></span>
            </div>

            <div class="resumen-row">
                <span>Subtotal</span>
                <span>$<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="resumen-row">
                <span>Envío</span>
                <span>$<?= number_format($costo_envio, 2) ?></span>
            </div>
            <div class="resumen-row resumen-total">
                <span>Total</span>
                <span>$<?= number_format($total_pagar, 2) ?></span>
            </div>
        </div>

        <form method="post" class="checkout-container" id="formPago">
            <h2 class="section-title">Método de pago</h2>

            <?php if (!empty($metodos_guardados)): ?>
                <div class="metodos-guardados" style="display:flex; flex-direction:column; gap:8px; margin-bottom:12px;">
                    <?php foreach ($metodos_guardados as $mp): ?>
                        <label class="mp-card">
                            <input type="radio" name="metodo_pago_id" value="<?= (int)$mp['id'] ?>">
                            <div class="mp-info">
                                <div class="mp-alias"><?= htmlspecialchars($mp['alias']) ?></div>
                                <div class="mp-detalle">
                                    <?= htmlspecialchars($mp['marca']) ?> •••• <?= htmlspecialchars($mp['ultimos4']) ?>
                                    &nbsp; Vence <?= sprintf('%02d/%d', $mp['mes_exp'], $mp['anio_exp']) ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted" style="margin-bottom:10px;">Aún no tienes métodos guardados.</p>
            <?php endif; ?>

            <div class="section-subtitle">Usar nueva tarjeta</div>

            <label class="mp-card" style="margin-top:8px; margin-bottom:10px;">
                <input type="radio" name="metodo_pago_id" value="nuevo" checked>
                <div class="mp-info">
                    <div class="mp-alias">Pagar con nueva tarjeta</div>
                    <div class="mp-detalle">Ingresa los datos y se guardará para la próxima</div>
                </div>
            </label>

            <div class="form-grid">
                <div class="form-group">
                    <label>Alias (ej. Mi VISA)*</label>
                    <input type="text" name="alias" value="<?= htmlspecialchars($_POST['alias'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Nombre del titular*</label>
                    <input type="text" name="titular" value="<?= htmlspecialchars($_POST['titular'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Número de tarjeta* (16 dígitos)</label>
                    <input type="text" id="numeroTarjeta" name="numero"
                           maxlength="16" inputmode="numeric" autocomplete="cc-number"
                           pattern="\d{16}" title="Debe tener exactamente 16 dígitos numéricos"
                           value="<?= htmlspecialchars($_POST['numero'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Mes de expiración (MM)*</label>
                    <input type="number" name="mes_exp" min="1" max="12"
                           value="<?= htmlspecialchars($_POST['mes_exp'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Año de expiración (AAAA)*</label>
                    <input type="number" name="anio_exp"
                           value="<?= htmlspecialchars($_POST['anio_exp'] ?? '') ?>">
                </div>
            </div>

            <div class="step-buttons">
                <a href="checkout.php?paso=3" class="btn-secondary btn-link">← Volver</a>
                <button type="submit" name="pagar" class="btn-primary">Pagar ahora</button>
            </div>
        </form>

    <?php endif; ?>

</div>

<script>
/* tarjeta: solo dígitos y max 16 */
document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("numeroTarjeta");
    if (!input) return;

    const normalizar = () => { input.value = input.value.replace(/\D/g, "").slice(0, 16); };
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






