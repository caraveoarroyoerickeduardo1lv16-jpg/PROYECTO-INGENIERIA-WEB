<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Solo operadores
if (!isset($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'operador') {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del operador - Mi Tiendita</title>
    <link rel="stylesheet" href="../CSS/operador.css">
</head>
<body>

<div class="page">

    
    <header class="topbar">
        <div class="topbar-inner">
            <a href="operador.php" class="logo-link">
                <div class="logo-icon">
                    <span class="logo-star">*</span>
                </div>
                <span class="logo-text">Mi tiendita</span>
            </a>

            <div class="logout-container">
                <a href="logout.php" class="logout-button">Cerrar sesión</a>
            </div>
        </div>
    </header>

   
    <main class="op-main">
        <h1>Panel del operador</h1>

        <
       
        <!-- TARJETAS -->
        <section class="op-cards">

            <!-- CARD PEDIDOS -->
            <article class="op-card">
                <div class="card-icon">
                    📋
                </div>
                <h2>Gestionar pedidos</h2>
                <p>Revisa y actualiza el estado de los pedidos.</p>

                <a href="operador_pedidos.php" class="btn-primary">
                    Ir a pedidos
                </a>
            </article>

            <!-- CARD STOCK -->
            <article class="op-card">
                <div class="card-icon">
                    🏠
                </div>
                <h2>Gestionar stock</h2>
                <p>Controla existencias y movimientos de almacén.</p>

                <a href="operador_stock.php" class="btn-primary btn-secondary">
                    Ir a stock
                </a>
            </article>

        </section>
    </main>

</div>

</body>
</html>
