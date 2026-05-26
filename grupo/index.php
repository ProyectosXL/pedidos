<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location:../login.php");
} else {

$local = $_SESSION['descLocal'];

$_SESSION['numsuc']    = isset($_SESSION['numsuc'])    ? $_SESSION['numsuc']    : 100;
$_SESSION['codClient'] = 'Cordoba';
$codClient             = 'Cordoba';
$habPedidos            = '00';
$deposi                = '00';
$dashboard             = isset($_SESSION['dashboard']) ? $_SESSION['dashboard'] : '';

$powerbiUrl = isset($_SESSION['POWERBI_URL']) && !empty($_SESSION['POWERBI_URL'])
    ? $_SESSION['POWERBI_URL']
    : 'https://app.powerbi.com/view?r=eyJrIjoiY2U5MDc4NzEtMTVkYy00YTNmLWJmNjYtMWRiZjBhZTM1OGI3IiwidCI6IjQ0Y2E2MmNkLTY4MjItNDZkNC05NTUxLTEzNDQ5N2ZmM2VjMiIsImMiOjR9';
$permiteMayoristas = isset($_SESSION['PERMITE_MAYORISTAS']) ? $_SESSION['PERMITE_MAYORISTAS'] : true;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>XL Gestión — Pedidos</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/logo.jpg" type="image/jpeg">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

    <!-- ===== HEADER FIJO ===== -->
    <header class="xl-header">
        <div class="xl-header-left">
            <div class="xl-logo-box">XL</div>
            <span class="xl-header-title">Gestión de Pedidos</span>
        </div>
        <div class="xl-header-right">
            <span class="xl-user-name"><?= htmlspecialchars($local) ?></span>
            <a href="/ppp/franquicias/grupo/index.php" class="btn-volver">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
        </div>
    </header>

    <!-- ===== CONTENIDO PRINCIPAL ===== -->
    <main class="xl-main">
        <div class="container" style="max-width: 1080px;">

            <!-- BIENVENIDA -->
            <div class="welcome-card mb-4">
                <img src="../images/logo.jpg" alt="Logo XL">
                <div class="welcome-text">
                    <h2>Bienvenido Original Products 1966 srl</h2>
                    <p><?= htmlspecialchars($local) ?></p>
                </div>
            </div>

            <!-- HERRAMIENTAS -->
            <div class="section-label">Herramientas Disponibles</div>

            <div class="row g-3">

                <!-- Pedido General -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="tool-card">
                        <div class="card-icon-box">
                            <i class="fa-solid fa-box"></i>
                        </div>
                        <div class="card-title">Pedido General</div>
                        <div class="card-desc">
                            Carga de pedidos de productos como carteras, calzados, equipaje y packaging.
                        </div>
                        <hr class="card-divider">
                        <div class="card-tags">
                            <span class="card-tag"><i class="fa-solid fa-warehouse"></i> Stock</span>
                            <span class="card-tag"><i class="fa-solid fa-store"></i> Sucursales</span>
                        </div>
                        <a href="pedidos/general.php" class="btn-action">
                            <i class="fa-solid fa-arrow-right"></i> Cargar Pedido
                        </a>
                    </div>
                </div>

                <!-- Pedido Accesorios -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="tool-card">
                        <div class="card-icon-box">
                            <i class="fa-solid fa-tags"></i>
                        </div>
                        <div class="card-title">Pedido Accesorios</div>
                        <div class="card-desc">
                            Carga de pedidos de accesorios como billeteras, paraguas, lentes, relojes, etc.
                        </div>
                        <hr class="card-divider">
                        <div class="card-tags">
                            <span class="card-tag"><i class="fa-solid fa-tag"></i> Accesorios</span>
                            <span class="card-tag"><i class="fa-solid fa-chart-bar"></i> Ventas</span>
                        </div>
                        <a href="pedidos/accesorios.php" class="btn-action">
                            <i class="fa-solid fa-arrow-right"></i> Cargar Pedido
                        </a>
                    </div>
                </div>

                <!-- Historial de Pedidos -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="tool-card">
                        <div class="card-icon-box">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div class="card-title">Historial de Pedidos</div>
                        <div class="card-desc">
                            Consulta de pedidos realizados, estado y detalle por sucursal.
                        </div>
                        <hr class="card-divider">
                        <div class="card-tags">
                            <span class="card-tag"><i class="fa-solid fa-list"></i> Pedidos</span>
                            <span class="card-tag"><i class="fa-solid fa-circle-check"></i> Estado</span>
                        </div>
                        <a href="pedidos/historial.php" class="btn-action">
                            <i class="fa-solid fa-arrow-right"></i> Ver Historial
                        </a>
                    </div>
                </div>

                <!-- Guías de Transporte -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="tool-card">
                        <div class="card-icon-box">
                            <i class="fa-solid fa-truck"></i>
                        </div>
                        <div class="card-title">Guías de Transporte</div>
                        <div class="card-desc">
                            Seguimiento de guías de despacho con factura y remitos por sucursal.
                        </div>
                        <hr class="card-divider">
                        <div class="card-tags">
                            <span class="card-tag"><i class="fa-solid fa-file-lines"></i> Guías</span>
                            <span class="card-tag"><i class="fa-solid fa-receipt"></i> Remitos</span>
                        </div>
                        <a href="../logistica/guiasDespacho/franquicias.php" class="btn-action">
                            <i class="fa-solid fa-arrow-right"></i> Ver Guías
                        </a>
                    </div>
                </div>

            </div><!-- /row -->
        </div><!-- /container -->
    </main>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
<?php
}
?>
