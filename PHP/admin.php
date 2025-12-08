<?php
session_start();

// Solo admins 
if (empty($_SESSION['user_id']) || ($_SESSION['user_tipo'] ?? '') !== 'administrador') {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de administrador - Mi tiendita</title>
    <link rel="stylesheet" href="../CSS/admin.css">
</head>
<body>

<div class="page">

    
    <header class="topbar">
        <div class="topbar-inner">
            <a href="admin.php" class="logo-link">
                <div class="logo-icon">
                    <span class="logo-star">*</span>
                </div>
                <span class="logo-text">Mi tiendita</span>
            </a>
        </div>
    </header>




   
    <main class="admin-main">
        <section class="admin-header">
            <h1>Panel de administrador</h1>
    <!-- BOTÓN DE CERRAR SESIÓN -->
<div class="logout-container">
    <a href="logout.php" class="logout-button">Cerrar sesión</a>
</div>
    
        </section>

        <section class="admin-cards">
            <!-- CARD REPORTES -->
            <article class="admin-card">
                <div class="card-icon bars-icon">📊</div>
                <h2>Ver reportes</h2>
                <p>Consulta ventas, pedidos y estadísticas.</p>
                <a href="admin_reportes.php" class="btn-primary">Ver reportes</a>
                   
            </article>

           
          <!-- CARD INVENTARIO -->
    <article class="admin-card">
        <div class="card-icon box-icon">📦</div>
        <h2>Gestionar inventario</h2>
        <p>Actualiza existencias y productos.</p>
        <a href="admin_inventario.php" class="btn-secondary">Gestionar inventario</a>
</article>


            <!-- CARD USUARIOS -->
            <article class="admin-card">
                <div class="card-icon user-icon">👤</div>
                <h2>Gestionar usuarios</h2>
                <p>Administra cuentas y permisos.</p>
                <a href="admin_usuarios.php" class="btn-secondary">Gestionar usuarios</a>
            </article>
        </section>
    </main>

</div>

</body>
</html>
