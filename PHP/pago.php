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

/* =========================
   FUNCIONES
========================= */
function parseStartHour($label) {
    $label = trim((string)$label);
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

function soloNombre($txt) {
    return (bool)preg_match('/^[\p{L} ]{2,}$/u', trim((string)$txt));
}

/* =========================
   GUARDAR HORARIO/DIA EN SESIÓN
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['horario'])) {
        $_SESSION['horario_envio'] = $_POST['horario'];
    }
    if (isset($_POST['dia_envio'])) {
        $_SESSION['dia_envio'] = (int)$_POST['dia_envio'];
    }
}

/* =========================
   SI NO CONFIRMADO, REQUIERE CHECKOUT
========================= */
if (!$confirmado) {
    if (empty($_SESSION['direccion_id']) || empty($_SESSION['horario_envio'])) {
        header("Location: checkout.php");
        exit;
    }
    if (!isset($_SESSION['dia_envio'])) {
        $_SESSION['dia_envio'] = 0;
    }
}

/* =========================
   MENSAJE DE ÉXITO
========================= */
if ($confirmado) {
    $mensaje_exito = $pedido_id_confirm > 0
        ? "Tu pago se realizó correctamente. Pedido #{$pedido_id_confirm}"
        : "Tu pago se realizó correctamente.";
}

/* =========================
   VARIABLES
========================= */
$direccion = null;
$metodos_guardados = [];
$subtotal = 0;
$costo_envio = 49.00;
$total_pagar = 0;
$horario_envio = '';
$dia_envio = 0;

if (!$confirmado) {

    $direccion_id  = (int)$_SESSION['direccion_id'];
    $horario_envio = (string)$_SESSION['horario_envio'];
    $dia_envio     = (int)($_SESSION['dia_envio'] ?? 0);

    if (!horarioEsValido($dia_envio, $horario_envio)) {
        $_SESSION['checkout_error'] = "El horario seleccionado ya no está disponible (hoy cerramos a las 9pm o falta anticipación). Elige otro horario.";
        header("Location: checkout.php?paso=3");
        exit;
    }

    /* =========================
       ELIMINAR MÉTODO (SOFT DELETE: estatus=0)
    ========================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_metodo_pago'])) {
        $mpId = (int)$_POST['eliminar_metodo_pago'];

        if ($mpId > 0) {
            $stmt = $conn->prepare("
                UPDATE metodos_pago
                SET estatus = 0
                WHERE id = ? AND usuario_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $mpId, $usuario_id);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: pago.php");
        exit;
    }

    /* =========================
       CARRITO ACTUAL
    ========================= */
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
    $total_pagar = $subtotal + $costo_envio;

    /* =========================
       DIRECCIÓN SELECCIONADA (solo estatus=1)
    ========================= */
    $stmt = $conn->prepare("
        SELECT etiqueta, calle, colonia, ciudad, estado, cp
        FROM direcciones
        WHERE id = ? AND usuario_id = ? AND estatus = 1
        LIMIT 1
    ");
    $stmt->bind_param("ii", $direccion_id, $usuario_id);
    $stmt->execute();
    $direccion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$direccion) {
        $_SESSION['checkout_error'] = "La dirección seleccionada ya no existe o fue eliminada. Agrega/selecciona otra.";
        header("Location: checkout.php?paso=2");
        exit;
    }

    /* =========================
       MÉTODOS GUARDADOS (solo estatus=1)
    ========================= */
    $stmt = $conn->prepare("
        SELECT id, alias, marca, ultimos4, mes_exp, anio_exp
        FROM metodos_pago
        WHERE usuario_id = ?
          AND estatus = 1
        ORDER BY creada_en DESC
    ");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $resMP = $stmt->get_result();
    while ($row = $resMP->fetch_assoc()) $metodos_guardados[] = $row;
    $stmt->close();

    /* =========================
       PAGAR
    ========================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pagar'])) {

        if (!horarioEsValido($dia_envio, $horario_envio)) {
            $errores[] = "El horario seleccionado ya no está disponible. Regresa y elige otro.";
        }

        $metodo_pago_id = $_POST['metodo_pago_id'] ?? null;
        if (!$metodo_pago_id) {
            $errores[] = "Selecciona o registra un método de pago.";
        }

        $metodo_id_real = null;

        /* ===== NUEVA TARJETA ===== */
        if (empty($errores) && $metodo_pago_id === 'nuevo') {

            $alias    = trim($_POST['alias'] ?? '');
            $titular  = trim($_POST['titular'] ?? '');
            $numero   = preg_replace('/\D/', '', $_POST['numero'] ?? '');
            $mes_exp  = trim($_POST['mes_exp'] ?? '');
            $anio_exp = trim($_POST['anio_exp'] ?? '');

            if ($alias === '' || $titular === '' || $numero === '' || $mes_exp === '' || $anio_exp === '') {
                $errores[] = "Todos los campos de la nueva tarjeta son obligatorios.";
            }

            if ($titular !== '' && !soloNombre($titular)) {
                $errores[] = "El nombre del titular solo debe contener letras y espacios (sin números ni caracteres especiales).";
            }

            if (!preg_match('/^\d{16}$/', $numero)) {
                $errores[] = "El número de tarjeta debe tener exactamente 16 dígitos numéricos.";
            }

            // MES: exactamente 2 dígitos 01-12
            if (!preg_match('/^\d{2}$/', $mes_exp) || (int)$mes_exp < 1 || (int)$mes_exp > 12) {
                $errores[] = "El mes de expiración no es válido (usa 01 a 12).";
            }

            // AÑO: exactamente 4 dígitos y rango
            $yearNow = (int)date('Y');
            $yearMax = $yearNow + 15;

            if (!preg_match('/^\d{4}$/', $anio_exp)) {
                $errores[] = "El año de expiración debe tener 4 dígitos (AAAA).";
            } else {
                $anioInt = (int)$anio_exp;
                if ($anioInt < $yearNow || $anioInt > $yearMax) {
                    $errores[] = "El año de expiración no es válido (debe estar entre {$yearNow} y {$yearMax}).";
                }
            }

            // vencida
            if (empty($errores)) {
                $monthNow = (int)date('n');
                $anioInt  = (int)$anio_exp;
                $mesInt   = (int)$mes_exp;

                if ($anioInt == $yearNow && $mesInt < $monthNow) {
                    $errores[] = "La tarjeta está vencida.";
                }
            }

            if (empty($errores)) {
                $marca = 'Tarjeta';
                if (preg_match('/^4/', $numero))           $marca = 'Visa';
                elseif (preg_match('/^5[1-5]/', $numero)) $marca = 'MasterCard';
                elseif (preg_match('/^3[47]/', $numero))  $marca = 'American Express';

                $ultimos4 = substr($numero, -4);
                $mesInt   = (int)$mes_exp;
                $anioInt  = (int)$anio_exp;

                $stmt = $conn->prepare("
                    INSERT INTO metodos_pago (usuario_id, alias, titular, marca, ultimos4, mes_exp, anio_exp, estatus, creada_en)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->bind_param("issssii", $usuario_id, $alias, $titular, $marca, $ultimos4, $mesInt, $anioInt);
                $stmt->execute();
                $metodo_id_real = $conn->insert_id;
                $stmt->close();
            }

        } else if (empty($errores)) {
            /* ===== MÉTODO GUARDADO (solo estatus=1) ===== */
            $id_mp = (int)$metodo_pago_id;

            $stmt = $conn->prepare("SELECT id FROM metodos_pago WHERE id = ? AND usuario_id = ? AND estatus = 1 LIMIT 1");
            $stmt->bind_param("ii", $id_mp, $usuario_id);
            $stmt->execute();
            $resChk = $stmt->get_result();
            $stmt->close();

            if ($resChk->fetch_assoc()) {
                $metodo_id_real = $id_mp;
            } else {
                $errores[] = "El método de pago seleccionado no es válido o fue eliminado.";
            }
        }

        /* ===== VERIFICAR STOCK ===== */
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

        /* ===== CREAR PEDIDO ===== */
        if (empty($errores) && $metodo_id_real) {
            $estado = 'pagado';

            try {
                $conn->begin_transaction();

                // descontar stock
                $stmtUpd = $conn->prepare("UPDATE producto SET stock = stock - ? WHERE id = ?");
                foreach ($items as $it) {
                    $cant = (int)$it['cantidad'];
                    $pid  = (int)$it['producto_id'];
                    $stmtUpd->bind_param("ii", $cant, $pid);
                    $stmtUpd->execute();
                }
                $stmtUpd->close();

                // insertar pedido
                $stmt = $conn->prepare("
                    INSERT INTO pedidos (usuario_id, carrito_id, direccion_id, metodo_pago_id, horario_envio, total, estado, creada_en)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("iiiisds", $usuario_id, $carrito_id, $direccion_id, $metodo_id_real, $horario_envio, $total_pagar, $estado);
                $stmt->execute();
                $pedido_id = $conn->insert_id;
                $stmt->close();

                // copiar detalle
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

                // vaciar carrito
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
        /* ===== CSS PROPIO SOLO PARA PAGO ===== */
        .pay-wrap{ display:flex; flex-direction:column; gap:12px; }
        .pay-card{
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:14px;
            padding:14px;
            box-shadow:0 2px 10px rgba(0,0,0,0.06);
        }
        .pay-title{ font-size:16px; font-weight:800; color:#111827; margin-bottom:10px; }
        .pay-row{
            display:flex; justify-content:space-between; gap:12px;
            font-size:13px; padding:6px 0; border-bottom:1px dashed #eee;
        }
        .pay-row:last-child{ border-bottom:none; }
        .pay-row strong{ font-weight:800; }
        .pay-total{ font-weight:900; font-size:14px; }

        .mp-list{ display:flex; flex-direction:column; gap:10px; margin-top:6px; }
        .mp-item{
            display:flex; gap:10px; align-items:stretch;
            border:1px solid #e5e7eb;
            border-radius:14px;
            padding:10px;
            background:#fafafa;
            transition:.15s;
        }
        .mp-item:hover{ border-color:#0071e3; background:#f8fbff; }
        .mp-radio{ display:flex; align-items:center; padding-left:4px; }
        .mp-body{ flex:1; display:flex; flex-direction:column; justify-content:center; gap:3px; }
        .mp-alias{ font-weight:900; font-size:14px; color:#111827; }
        .mp-det{ font-size:12px; color:#6b7280; }

        .mp-actions{ display:flex; align-items:center; }
        .btn-danger{
            background:#dc2626; color:#fff;
            border:none; border-radius:999px;
            padding:9px 14px; font-size:13px; font-weight:800;
            cursor:pointer; transition:.15s;
        }
        .btn-danger:hover{ background:#b91c1c; }

        .new-card{
            margin-top:10px;
            border-top:1px solid #eee;
            padding-top:12px;
        }
        .hint{ font-size:12px; color:#6b7280; margin-top:6px; }

        .pay-actions{ display:flex; justify-content:space-between; gap:10px; margin-top:14px; }
        .btn-link{ text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }

        /* inputs mejor */
        .pay-card .form-group input{
            border-radius:10px;
            border:1px solid #d1d5db;
            padding:9px 10px;
            font-size:13px;
            outline:none;
        }
        .pay-card .form-group input:focus{
            border-color:#0071e3;
            box-shadow:0 0 0 3px rgba(0,113,227,0.12);
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

        <div class="pay-card">
            <div class="pay-title">Pago exitoso</div>
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
                            <li><?= htmlspecialchars($f['nombre']) ?> — Pediste: <?= (int)$f['cantidad'] ?>, disponibles: <?= (int)$f['stock'] ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="pay-wrap">

            <div class="pay-card">
                <div class="pay-title">Resumen de tu pedido</div>

                <div class="pay-row">
                    <span>Dirección</span>
                    <span>
                        <?= htmlspecialchars($direccion['etiqueta']) ?>:
                        <?= htmlspecialchars($direccion['calle']) ?>,
                        <?= htmlspecialchars($direccion['colonia']) ?>,
                        <?= htmlspecialchars($direccion['ciudad']) ?>,
                        <?= htmlspecialchars($direccion['estado']) ?>,
                        CP <?= htmlspecialchars($direccion['cp']) ?>
                    </span>
                </div>

                <div class="pay-row">
                    <span>Horario</span>
                    <span><?= htmlspecialchars($horario_envio) ?></span>
                </div>

                <div class="pay-row"><span>Subtotal</span><strong>$<?= number_format($subtotal, 2) ?></strong></div>
                <div class="pay-row"><span>Envío</span><strong>$<?= number_format($costo_envio, 2) ?></strong></div>
                <div class="pay-row pay-total"><span>Total</span><strong>$<?= number_format($total_pagar, 2) ?></strong></div>
            </div>

            <form method="post" class="pay-card" id="formPago">
                <div class="pay-title">Método de pago</div>

                <?php if (!empty($metodos_guardados)): ?>
                    <div class="mp-list">
                        <?php foreach ($metodos_guardados as $mp): ?>
                            <div class="mp-item">
                                <div class="mp-radio">
                                    <input type="radio" name="metodo_pago_id" value="<?= (int)$mp['id'] ?>">
                                </div>

                                <div class="mp-body">
                                    <div class="mp-alias"><?= htmlspecialchars($mp['alias']) ?></div>
                                    <div class="mp-det">
                                        <?= htmlspecialchars($mp['marca']) ?> •••• <?= htmlspecialchars($mp['ultimos4']) ?>
                                        &nbsp; Vence <?= sprintf('%02d/%d', (int)$mp['mes_exp'], (int)$mp['anio_exp']) ?>
                                    </div>
                                </div>

                                <div class="mp-actions">
                                    <button
                                        type="submit"
                                        name="eliminar_metodo_pago"
                                        value="<?= (int)$mp['id'] ?>"
                                        class="btn-danger"
                                        formnovalidate
                                        onclick="return confirm('¿Seguro que quieres eliminar este método de pago?');"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="hint">Aún no tienes métodos de pago guardados.</p>
                <?php endif; ?>

                <div class="new-card">
                    <label class="mp-item" style="background:#fff;">
                        <div class="mp-radio">
                            <input type="radio" name="metodo_pago_id" value="nuevo" checked>
                        </div>
                        <div class="mp-body">
                            <div class="mp-alias">Pagar con nueva tarjeta</div>
                            <div class="mp-det">Ingresa los datos y se guardará</div>
                        </div>
                    </label>

                    <div class="form-grid" style="margin-top:12px;">
                        <div class="form-group">
                            <label>Alias (ej. Mi VISA)*</label>
                            <input id="aliasInput" type="text" name="alias" value="<?= htmlspecialchars($_POST['alias'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Nombre del titular*</label>
                            <input
                                type="text"
                                id="titularInput"
                                name="titular"
                                value="<?= htmlspecialchars($_POST['titular'] ?? '') ?>"
                                required
                                autocomplete="cc-name"
                                pattern="[\p{L} ]{2,}"
                                title="Solo letras y espacios (sin números ni caracteres especiales)."
                            >
                        </div>

                        <div class="form-group">
                            <label>Número de tarjeta* (16 dígitos)</label>
                            <input
                                type="text"
                                id="numeroTarjeta"
                                name="numero"
                                maxlength="16"
                                inputmode="numeric"
                                autocomplete="cc-number"
                                pattern="\d{16}"
                                title="Debe tener exactamente 16 dígitos"
                                value="<?= htmlspecialchars($_POST['numero'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Mes de expiración (MM)*</label>
                            <input
                                id="mesExpInput"
                                type="text"
                                name="mes_exp"
                                inputmode="numeric"
                                maxlength="2"
                                pattern="^\d{2}$"
                                placeholder="MM"
                                title="Ingresa 2 dígitos (01 a 12)"
                                value="<?= htmlspecialchars($_POST['mes_exp'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>Año de expiración (AAAA)*</label>
                            <input
                                id="anioExpInput"
                                type="text"
                                name="anio_exp"
                                inputmode="numeric"
                                maxlength="4"
                                pattern="^\d{4}$"
                                placeholder="AAAA"
                                title="Ingresa un año válido (ej. 2026)"
                                value="<?= htmlspecialchars($_POST['anio_exp'] ?? '') ?>"
                                required
                            >
                        </div>
                    </div>

                    <p class="hint">Guardamos alias, marca y últimos 4 dígitos. No guardamos el número completo.</p>
                </div>

                <div class="pay-actions">
                    <a href="checkout.php?paso=3" class="btn-secondary btn-link">← Volver</a>
                    <button type="submit" name="pagar" class="btn-primary">Pagar ahora</button>
                </div>
            </form>

        </div>

    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {

    // ===== TARJETA: solo dígitos y max 16 =====
    const num = document.getElementById("numeroTarjeta");
    if (num) {
        const normalizar = () => { num.value = (num.value || "").replace(/\D/g, "").slice(0, 16); };
        num.addEventListener("input", normalizar);
        num.addEventListener("paste", (e) => {
            e.preventDefault();
            const txt = (e.clipboardData || window.clipboardData).getData("text");
            num.value = (txt || "").replace(/\D/g, "").slice(0, 16);
        });
    }

    // ===== TITULAR: solo letras y espacios =====
    const titular = document.getElementById("titularInput");
    if (titular) {
        const limpiar = () => { titular.value = (titular.value || "").replace(/[^\p{L} ]/gu, ""); };
        titular.addEventListener("input", limpiar);
        titular.addEventListener("paste", (e) => {
            e.preventDefault();
            const txt = (e.clipboardData || window.clipboardData).getData("text");
            titular.value = (txt || "").replace(/[^\p{L} ]/gu, "");
        });
    }

    // ===== MES EXP: solo números, 2 dígitos, y 1->01 =====
    const mes = document.getElementById("mesExpInput");
    if (mes) {
        mes.addEventListener("input", () => {
            mes.value = (mes.value || "").replace(/\D/g, "").slice(0, 2);
        });

        mes.addEventListener("blur", () => {
            let v = (mes.value || "").trim();
            if (v === "") return;

            v = v.replace(/\D/g, "").slice(0, 2);
            if (v.length === 1) v = v.padStart(2, "0");

            const n = parseInt(v, 10);
            if (isNaN(n) || n < 1 || n > 12) {
                mes.value = "";
                mes.setCustomValidity("Mes inválido. Usa 01 a 12.");
            } else {
                mes.value = v;
                mes.setCustomValidity("");
            }
        });

        mes.addEventListener("focus", () => mes.setCustomValidity(""));
    }

    // ===== AÑO EXP: solo números, 4 dígitos y rango =====
    const anio = document.getElementById("anioExpInput");
    if (anio) {

        anio.addEventListener("input", () => {
            anio.value = (anio.value || "").replace(/\D/g, "").slice(0, 4);
        });

        anio.addEventListener("blur", () => {
            const v = (anio.value || "").trim();
            if (v === "") return;

            if (!/^\d{4}$/.test(v)) {
                anio.value = "";
                anio.setCustomValidity("El año debe tener 4 dígitos (AAAA).");
                return;
            }

            const year = parseInt(v, 10);
            const yearNow = new Date().getFullYear();
            const yearMax = yearNow + 15;

            if (year < yearNow || year > yearMax) {
                anio.value = "";
                anio.setCustomValidity(`Año inválido. Debe estar entre ${yearNow} y ${yearMax}.`);
            } else {
                anio.setCustomValidity("");
            }
        });

        anio.addEventListener("focus", () => anio.setCustomValidity(""));
    }

    // ===== si eliges tarjeta guardada, NO pedir campos nueva tarjeta =====
    const radios = document.querySelectorAll('input[name="metodo_pago_id"]');

    const aliasInput   = document.getElementById("aliasInput");
    const titularInput = document.getElementById("titularInput");
    const numeroInput  = document.getElementById("numeroTarjeta");
    const mesInput     = document.getElementById("mesExpInput");
    const anioInput    = document.getElementById("anioExpInput");

    const campos = [aliasInput, titularInput, numeroInput, mesInput, anioInput].filter(Boolean);

    function setCamposNuevaTarjetaActivos(activo) {
        campos.forEach(el => {
            el.disabled = !activo;
            if (activo) el.setAttribute("required", "required");
            else el.removeAttribute("required");
        });
    }

    function revisarSeleccion() {
        const seleccionado = document.querySelector('input[name="metodo_pago_id"]:checked');
        if (!seleccionado) return;

        if (seleccionado.value === "nuevo") setCamposNuevaTarjetaActivos(true);
        else setCamposNuevaTarjetaActivos(false);
    }

    radios.forEach(r => r.addEventListener("change", revisarSeleccion));
    revisarSeleccion();
});
</script>

</body>
</html>









