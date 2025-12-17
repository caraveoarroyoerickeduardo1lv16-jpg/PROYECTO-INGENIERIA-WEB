<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost", "walmartuser", "1234", "walmart");
$conn->set_charset("utf8mb4");

$usuario_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

date_default_timezone_set('America/Mexico_City');

function formatHourLabel($h) {
    $ampm = $h >= 12 ? 'pm' : 'am';
    $h12  = $h % 12;
    if ($h12 == 0) $h12 = 12;
    return $h12 . $ampm;
}

$paso   = isset($_GET['paso']) ? (int)$_GET['paso'] : 1;
$error  = '';
$direccionesUsuario = [];

// ✅ validación servidor: solo letras (incluye acentos), espacios, punto y guion
function soloLetras($txt) {
    return (bool)preg_match('/^[\p{L}\s\.\-]{2,}$/u', $txt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ✅ ELIMINAR DIRECCIÓN (NUEVO) — no la borra, solo la oculta */
    if (isset($_POST['eliminar_direccion'])) {
        $dirId = (int)$_POST['eliminar_direccion'];

        if ($usuario_id && $dirId > 0) {
            $stmt = $conn->prepare("UPDATE direcciones SET usuario_id = 0 WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("ii", $dirId, $usuario_id);
            $stmt->execute();
            $stmt->close();

            if (!empty($_SESSION['direccion_id']) && (int)$_SESSION['direccion_id'] === $dirId) {
                unset($_SESSION['direccion_id']);
            }

            header("Location: checkout.php?paso=2");
            exit;
        } else {
            $error = "Dirección inválida.";
            $paso = 2;
        }
    }

    /* usar dirección existente  */
    if (isset($_POST['usar_direccion'])) {
        $_SESSION['direccion_id'] = (int)$_POST['usar_direccion'];
        header("Location: checkout.php?paso=3");
        exit;
    }

    /* guardar nueva dirección */
    if (isset($_POST['guardar_direccion'])) {
        $etiqueta = $_POST['etiqueta'] ?? 'Casa';
        $calle    = trim($_POST['calle'] ?? '');
        $colonia  = trim($_POST['colonia'] ?? '');
        $ciudad   = trim($_POST['ciudad'] ?? '');
        $estado   = trim($_POST['estado'] ?? '');
        $cp       = trim($_POST['cp'] ?? '');

        if ($usuario_id && $calle !== '' && $colonia !== '' && $ciudad !== '' && $estado !== '' && $cp !== '') {

            if (!preg_match('/^\d{5}$/', $cp)) {
                $error = "El código postal debe tener exactamente 5 dígitos numéricos.";
                $paso  = 2;

            } elseif (!soloLetras($colonia)) {
                $error = "La colonia no debe contener números (solo letras y espacios).";
                $paso  = 2;
            } elseif (!soloLetras($ciudad)) {
                $error = "La ciudad no debe contener números (solo letras y espacios).";
                $paso  = 2;
            } elseif (!soloLetras($estado)) {
                $error = "El estado no debe contener números (solo letras y espacios).";
                $paso  = 2;

            } else {
                $stmt = $conn->prepare("
                    INSERT INTO direcciones (usuario_id, etiqueta, calle, colonia, ciudad, estado, cp, creada_en)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("issssss", $usuario_id, $etiqueta, $calle, $colonia, $ciudad, $estado, $cp);
                $stmt->execute();

                $_SESSION['direccion_id'] = $conn->insert_id;
                header("Location: checkout.php?paso=3");
                exit;
            }
        } else {
            $error = "Faltan datos obligatorios de la dirección.";
            $paso  = 2;
        }
    }
}

/* DIRECCIONES GUARDADAS (solo las del usuario actual) */
if ($usuario_id) {
    $stmt = $conn->prepare("
        SELECT id, etiqueta, calle, colonia, ciudad, estado, cp
        FROM direcciones
        WHERE usuario_id = ?
        ORDER BY creada_en DESC
    ");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $resDir = $stmt->get_result();
    while ($row = $resDir->fetch_assoc()) $direccionesUsuario[] = $row;
    $stmt->close();
}

$datos = [
    'etiqueta' => '',
    'calle'    => '',
    'colonia'  => '',
    'ciudad'   => '',
    'estado'   => '',
    'cp'       => ''
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Mi Tiendita</title>
    <link rel="stylesheet" href="../CSS/checkout.css">
    <style> ::placeholder { color:#999; opacity:1; } </style>
</head>
<body>

<div class="page">
    <header class="header header-brand">
        <div class="brand-row">
            <div class="brand-left">
                <div class="brand-badge">*</div>
                <div>
                    <h1>Mi Tiendita</h1>
                    <span class="header-sub">Checkout - Envío</span>
                </div>
            </div>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" id="formCheckout">
        <!-- ✅ IMPORTANTE: esto viaja a pago.php -->
        <input type="hidden" name="dia_envio" id="diaEnvio" value="0">

        <!-- PASO 1 -->
        <section id="paso1" class="checkout-container <?= $paso === 1 ? '' : 'hidden' ?>">
            <div class="days-tabs">
                <button type="button" class="day-button active" data-dia="0" data-step="1">
                    <span>Hoy</span><br><span class="date"><?= date('j/n') ?></span>
                </button>
                <button type="button" class="day-button" data-dia="1" data-step="1">
                    <span>Mañana</span><br><span class="date"><?= date('j/n', strtotime('+1 day')) ?></span>
                </button>
                <button type="button" class="day-button" data-dia="2" data-step="1">
                    <span>Pasado</span><br><span class="date"><?= date('j/n', strtotime('+2 day')) ?></span>
                </button>
            </div>

            <p class="status-hoy" style="display:none;"></p>

            <div class="alert">
                <strong>⚠</strong>
                Para ver y reservar los horarios de envío más precisos,
                <a href="#" id="btnAgregarDireccion">agrega una dirección</a>.
            </div>

            <div class="slots-group">
                <?php for ($h = 9; $h < 21; $h++):
                    $label = formatHourLabel($h) . '-' . formatHourLabel($h + 1);
                ?>
                    <label class="slot slot-disabled" data-hora="<?= $h ?>">
                        <input type="radio" disabled>
                        <div class="slot-info">
                            <div class="slot-title"><?= $label ?></div>
                            <div class="slot-sub">Horario disponible pagando en línea</div>
                        </div>
                        <div class="slot-price">$49.00</div>
                    </label>
                <?php endfor; ?>
            </div>
        </section>

        <!-- PASO 2 -->
        <section id="paso2" class="checkout-container <?= $paso === 2 ? '' : 'hidden' ?>">
            <h2 class="section-title">Agregar dirección</h2>

            <?php if (!empty($direccionesUsuario)): ?>
                <div class="saved-addresses">
                    <h3>Mis direcciones guardadas</h3>

                    <?php foreach ($direccionesUsuario as $dir): ?>
                        <div class="saved-address-card">
                            <div class="saved-label"><?= htmlspecialchars($dir['etiqueta']) ?></div>
                            <div class="saved-text">
                                <?= htmlspecialchars($dir['calle']) ?>,
                                <?= htmlspecialchars($dir['colonia']) ?>,
                                <?= htmlspecialchars($dir['ciudad']) ?>,
                                <?= htmlspecialchars($dir['estado']) ?>,
                                CP <?= htmlspecialchars($dir['cp']) ?>
                            </div>

                            <div class="saved-actions">
                                <button type="submit"
                                        name="usar_direccion"
                                        value="<?= (int)$dir['id'] ?>"
                                        class="btn-primary btn-sm"
                                        formnovalidate>
                                    Usar esta dirección
                                </button>

                                <button type="submit"
                                        name="eliminar_direccion"
                                        value="<?= (int)$dir['id'] ?>"
                                        class="btn-danger"
                                        formnovalidate
                                        onclick="return confirm('¿Seguro que quieres eliminar esta dirección?');">
                                    Eliminar dirección
                                </button>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Etiqueta*</label>
                    <input type="text" name="etiqueta"
                           value="<?= htmlspecialchars($datos['etiqueta']) ?>"
                           required placeholder="Ej: Casa, Trabajo">
                </div>

                <div class="form-group">
                    <label>Calle*</label>
                    <input type="text" name="calle"
                           value="<?= htmlspecialchars($datos['calle']) ?>"
                           required placeholder="Ej: Av. Reforma 123">
                </div>

                <div class="form-group">
                    <label>Colonia*</label>
                    <input type="text" name="colonia" id="coloniaInput"
                           value="<?= htmlspecialchars($datos['colonia']) ?>"
                           required
                           placeholder="Ej: Centro"
                           pattern="[\p{L}\s\.\-]{2,}"
                           title="Solo letras y espacios (sin números).">
                </div>

                <div class="form-group">
                    <label>Ciudad*</label>
                    <input type="text" name="ciudad" id="ciudadInput"
                           value="<?= htmlspecialchars($datos['ciudad']) ?>"
                           required
                           placeholder="Ej: Ciudad de México"
                           pattern="[\p{L}\s\.\-]{2,}"
                           title="Solo letras y espacios (sin números).">
                </div>

                <div class="form-group">
                    <label>Estado*</label>
                    <input type="text" name="estado" id="estadoInput"
                           value="<?= htmlspecialchars($datos['estado']) ?>"
                           required
                           placeholder="Ej: CDMX"
                           pattern="[\p{L}\s\.\-]{2,}"
                           title="Solo letras y espacios (sin números).">
                </div>

                <div class="form-group">
                    <label>Código postal*</label>
                    <input type="text" name="cp"
                           value="<?= htmlspecialchars($datos['cp']) ?>"
                           required
                           placeholder="Ej: 01234"
                           maxlength="5"
                           pattern="\d{5}"
                           inputmode="numeric"
                           title="Ingresa 5 dígitos numéricos">
                </div>
            </div>

            <div class="step-buttons">
                <button type="button" class="btn-secondary" id="btnVolverPaso1">← Volver</button>
                <button type="submit" name="guardar_direccion" class="btn-primary" id="btnGuardarDireccion">
                    Guardar dirección
                </button>
            </div>
        </section>

        <!-- PASO 3 -->
        <section id="paso3" class="checkout-container <?= $paso === 3 ? '' : 'hidden' ?>">
            <header class="step-header">
                <button type="button" class="back-icon" id="btnVolverPaso2">←</button>
                <div>
                    <div class="step-title">Reservar un horario</div>
                    <div class="step-sub">Selecciona la hora de entrega</div>
                </div>
            </header>

            <div class="days-tabs">
                <button type="button" class="day-button active" data-dia="0" data-step="3">
                    <span>Hoy</span><br><span class="date"><?= date('j/n') ?></span>
                </button>
                <button type="button" class="day-button" data-dia="1" data-step="3">
                    <span>Mañana</span><br><span class="date"><?= date('j/n', strtotime('+1 day')) ?></span>
                </button>
                <button type="button" class="day-button" data-dia="2" data-step="3">
                    <span>Pasado mañana</span><br><span class="date"><?= date('j/n', strtotime('+2 day')) ?></span>
                </button>
            </div>

            <p class="status-hoy" style="display:none;"></p>

            <div class="slots-group">
                <?php for ($h = 9; $h < 21; $h++):
                    $label = formatHourLabel($h) . '-' . formatHourLabel($h + 1);
                    $isDefault = ($label === '1pm-2pm');
                ?>
                    <label class="slot <?= $isDefault ? 'selected' : '' ?>" data-hora="<?= $h ?>">
                        <input type="radio" name="horario"
                               value="<?= $label ?>"
                               <?= $isDefault ? 'checked' : '' ?>
                               required>
                        <div class="slot-info">
                            <div class="slot-title"><?= $label ?></div>
                        </div>
                        <div class="slot-price">$49.00</div>
                    </label>
                <?php endfor; ?>
            </div>

            <div class="summary">
                <div>Envío estándar</div>
                <div id="resumenHorario">1pm-2pm · $49.00</div>
            </div>

            <div class="step-buttons">
                <button type="submit"
                        id="btnContinuarPago"
                        class="btn-primary btn-full"
                        formaction="pago.php"
                        formnovalidate>
                    Continuar al pago
                </button>
            </div>
        </section>

    </form>
</div>

<script src="../JAVASCRIPT/checkout.js"></script>
</body>
</html>









