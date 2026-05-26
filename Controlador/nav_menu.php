<?php
include('estado_cuenta.php');
?>

<?php
// Función para detectar si el dispositivo es móvil
function isMobile()
{
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

// Determinar la URL correcta basada en el tipo de dispositivo
$egresosCajaUrl = isMobile() ? 'egresosDeCaja/egresosCajaMobile.php' : 'egresosDeCaja/egresosDeCaja.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <a class="navbar-brand" onClick="window.location='index.php'"><i class="fad fa-home" title="INICIO"></i></a>
    <a class="navbar-brand" onClick="window.location='login.php'"><i class="far fa-times-octagon"
            title="CERRAR SESION"></i></a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav">
            <!-- PEDIDOS -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" data-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-shopping-cart"></i> Pedidos
                </a>
                <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                    <?php if ($habPedidos == 1 || $_SESSION['numsuc'] < 100) { ?>
                        <a class="dropdown-item spinner" href="#" onClick="nuevoPedido('PEDIDO GENERAL', 1)"
                            id="buttonPedidoGeneral"><i class="fas fa-box"></i> Generales</a>
                        <a class="dropdown-item spinner" href="#" onClick="nuevoPedido('PEDIDO ACCESORIOS', 2)"
                            id="buttonPedidoAccesorios"><i class="fas fa-tags"></i> Accesorios</a>
                    <?php } ?>
                    <?php if ($_SESSION['esOutlet'] == 1) { ?>
                        <a class="dropdown-item spinner" href="#" onClick="nuevoPedido('PEDIDO OUTLET', 3)"
                            id="buttonPedidoOutlet"><i class="fas fa-store-alt"></i> Outlet</a>
                    <?php } ?>
                    <?php if ($deposi != '00') { ?>
                        <a class="dropdown-item" href="#" onclick="location.href='pedidos/desabastecimiento.php'"><i
                                class="fas fa-exclamation-triangle"></i> Desabastecimiento</a>
                    <?php } ?>
                    <a class="dropdown-item spinner" href="#" onclick="location.href='pedidosNew/historial.php'"><i class="fas fa-history"></i> Historial</a>
                </div>
            </li>

            <!-- CONSULTAS -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" data-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-search"></i> Consultas
                </a>
                <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                    <?php if ($_SESSION['numsuc'] < 100 || $_SESSION['usuarioUy'] == 1) { ?>
                        <a class="dropdown-item spinner" href="stockYprecios/consultaStockPrecios.php"
                            onclick="location.href='guia_local'"><i class="fas fa-boxes"></i> Stock y Precios</a>
                    <?php } ?>
                    <?php if ($_SESSION['numsuc'] < 100) { ?>
                        <a class="dropdown-item spinner" href="#"
                            onclick="location.href='stockYprecios/stockDepositos.php'"><i class="fas fa-cubes"></i> Stock
                            por Artículo</a>
                    <?php } ?>
                    <a class="dropdown-item spinner" href="#" onclick="location.href='maestroDestinos/indexMob.php'"><i
                            class="fas fa-map-marker-alt"></i> Destinos por Articulo</a>
                </div>
         
            </li>

            <!-- ECOMMERCE -->
            <?php if ($_SESSION['tipo'] != 'MAYORISTA') { ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-globe"></i> Ecommerce
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                            <?php if ($_SESSION['numsuc'] > 100) { ?>
                            <a class="dropdown-item" href="#" onclick="location.href='control/index.php'"><i
                                    class="fas fa-clipboard-check"></i> Registrar Recepción Pedidos</a>
                            <?php } ?>
                        <a class="dropdown-item spinner" href="#" onclick="location.href='guia_local'"><i
                                class="fas fa-cash-register"></i> Registrar Entrega Pedidos</a>
                        <a class="dropdown-item spinner" href="#" onclick="location.href='guia_local/buscar_factura.php'"><i
                                class="fas fa-search-dollar"></i> Buscar Factura</a>
                        <a class="dropdown-item spinner" href="#"
                            onclick="location.href='../logistica/ecommerce/index.php'"><i class="fas fa-tasks"></i> Control
                            pedidos</a>
                    </div>
                </li>
            <?php } ?>

            <?php if ($_SESSION['tipo'] != 'MAYORISTA') { ?>
                <!-- REPORTES -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" data-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-chart-bar"></i> Reportes
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                        <?php if ($_SESSION['usuarioUy'] != 1) { ?>
                            <a class="dropdown-item spinner" href="#"
                                onclick="<?= (substr($codClient, 0, 2) == 'FR') ? "location.href='../logistica/guiasDespacho/franquicias.php'" : "location.href='../logistica/guiasDespacho/locales.php'"; ?>"><i
                                    class="fas fa-truck"></i> Guia de Transporte</a>
                        <?php } ?>
                        <?php if ($_SESSION['tipo'] != 'MAYORISTA') { ?>
                            <a class="dropdown-item spinner" href="#" onclick="location.href='../bi/sucursales/'"><i
                                    class="fas fa-chart-line"></i> Dashboard BI</a>
                        <?php } ?>
                        <a class="dropdown-item spinner" href="#" onclick="location.href='maestroDestinos/index.php'"><i
                                class="fas fa-map-marker-alt"></i> Maestro Destinos</a>
                        <a class="dropdown-item spinner" href="#" onclick="location.href='../ppp/objetivos/portal.php'"><i
                                class="fas fa-bullseye"></i> Cumplimiento Objetivos</a>
                    </div>
                </li>

                <!-- OPERACIONES -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="http://example.com" id="navbarDropdownMenuLink"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-cogs"></i> Operaciones
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                            <?php if ($_SESSION['numsuc'] < 100 || $_SESSION['usuarioUy'] == 1) {
                                $lista = 'facturaManual/listado.php?suc=' . $_SESSION['numsuc'];
                                $carga = 'facturaManual/carga.php?suc=' . $_SESSION['numsuc'];
                                ?>
                            <li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" href="#"><i
                                        class="fas fa-user-cog"></i> Administración</a>
                                <ul class="dropdown-menu">
                                    <a class="dropdown-item spinner" href="#"
                                        onclick="location.href='<?php echo $egresosCajaUrl; ?>'"><i
                                            class="fas fa-money-bill-wave"></i> Egresos de caja</a>
                                    <a class="dropdown-item spinner" href="#" onclick="location.href='<?php echo $lista; ?>'"><i
                                            class="fas fa-file-invoice"></i> Factura manual </a>
                                    <a class="dropdown-item spinner" href="#"
                                        onclick="location.href='../administracion/tesoreria/ListarRetiros.php'"><i
                                            class="fas fa-truck"></i> Guía de traslado</a>

                                </ul>
                            </li>
                        <?php } ?>



                            <?php if ($_SESSION['numsuc'] < 100 || $_SESSION['usuarioUy'] == 1) { ?>
                            <li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" href="#"><i
                                        class="fas fa-check-circle"></i> Calidad</a>
                                <ul class="dropdown-menu">
                                    <a class="dropdown-item spinner" href="#"
                                        onclick="location.href='controlFallas/seleccionDeSolicitudes.php'"><i
                                            class="fas fa-exclamation-circle"></i> Gestion de fallas</a>
                              <?php if ($_SESSION['esOutlet'] == 1) { ?>
                                        <a class="dropdown-item spinner" href="#"
                                            onclick="location.href='controlFallas/seleccionDeSolicitudesDestino.php'"><i
                                                class="fas fa-code-branch"></i> Gestionar recodificaciones</a>
                                            <?php } ?>
                                            <?php if ($_SESSION['usuarioUy'] != 1) { ?>
                                        <a class="dropdown-item spinner" href="#"
                                            onclick="location.href='talonarioFallas/index.php'"><i
                                                class="fas fa-clipboard-list"></i> Talonario de fallas</a>
                                            <?php } ?>
                                        <a class="dropdown-item spinner" href="http://extralarge.dyndns.biz:822/Default.aspx" target="_blank"><i
                                                class="fas fa-tools"></i> Plataforma de arreglos</a>
                                </ul>
                            </li>
                        <?php } ?>


                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">
                                <i class="fas fa-cubes"></i> Stock
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($_SESSION['connection_db'] != false) { ?>
                                    <?php if ($_SESSION['numsuc'] < 100 || $_SESSION['usuarioUy'] == 1) { ?>
                                        <a class="dropdown-item" href="#" onclick="location.href='control/index.php'">
                                            <i class="fas fa-clipboard-check"></i> Control de recepción
                                        </a>
                                    <?php } ?>
                                <?php } ?>

                                <?php if ($_SESSION['numsuc'] < 100 || $_SESSION['usuarioUy'] == 1) { ?>
                                    <a class="dropdown-item" href="#" onclick="location.href='remitosLocal/index.php'">
                                        <i class="fas fa-tags"></i> Rotulo rotaciones
                                    </a>
                                <?php } ?>

                                <a class="dropdown-item" href="#" onclick="location.href='barcode/index.html'">
                                    <i class="fas fa-barcode"></i> Etiq. codigo de barras
                                </a>

                                <a class="dropdown-item" href="#" onclick="location.href='https://app.xl.com.ar/conteoLocal/login.php'">
                                    <i class="fas fa-list-ol"></i> Sistema De Conteo
                                </a>

                                <?php if ($_SESSION['numsuc'] > 100) { ?>
                                    <a class="dropdown-item" href="#"
                                        onclick="location.href='../comercial/franquicias/index.php'">
                                        <i class="fas fa-warehouse"></i> Gestión de Stock
                                    </a>
                                    <a class="dropdown-item" href="https://app.xl.com.ar/LOGIstica/RECLAMOS/login.php"
                                        target="_blank">
                                        <i class="fas fa-dolly"></i> Reclamo Logístico
                                    </a>
                                <?php } ?>
                            </ul>
                        </li>

                        <?php if ($_SESSION['numsuc'] < 100 || $_SESSION['usuarioUy'] == 1) { ?>
                            <?php if (!isMobile()) { ?>
                                <li class="dropdown-submenu">
                                    <a class="dropdown-item dropdown-toggle" href="#"><i class="fas fa-users"></i> RRHH</a>
                                    <ul class="dropdown-menu">
                                        <?php if ($_SESSION['numsuc'] < 100) { ?>
                                            <li>
                                                <a class="dropdown-item spinner"
                                                    href="../recursosHumanos/anticipoSueldos/cargaAnticipoGrupoSuc.php">
                                                    <i class="fas fa-university"></i> Solicitud Anticipo de Sueldos
                                                </a>
                                            </li>
                                        <?php } ?>
                                        <li>
                                            <a class="dropdown-item spinner"
                                                href="https://app.xl.com.ar/comercial/horarios/local.php">
                                                <i class="fas fa-clock"></i> Plataforma Horaria
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            <?php } ?>
                        <?php } ?>

                        <?php if ($_SESSION['usuarioUy'] != 1) { ?>
                            <li class="dropdown-submenu"><a class="dropdown-item dropdown-toggle" href="#"><i
                                        class="fas fa-cash-register"></i> Ventas</a>
                                <ul class="dropdown-menu">
                                    <a class="dropdown-item spinner" href="#"
                                        onclick="location.href='../comercial/sucursales/cartelVidriera.php'"><i
                                            class="fas fa-bookmark"></i> Carteles Precios Vidriera</a>
                                            <?php if ($_SESSION['numsuc'] < 100) { ?>
                                        <a class="dropdown-item spinner" href="#"
                                            onclick="location.href='../comercial/auditoria/ingreso_sucursal.php'"><i
                                                class="fas fa-search-dollar"></i> Auditoría Ajuste Precio</a>
                                            <?php } ?>
                                </ul>
                            </li>
                        <?php } ?>

                    </ul>
                </li>

                <?php if ($_SESSION['numsuc'] < 100 || $codClient == 'GTWEB') { ?>
                    <!-- UTILES -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" data-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-tools"></i> Utiles
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                            <a class="dropdown-item" href="#" onclick="location.href='procedimientos'"><i
                                    class="fas fa-book"></i> Procedimientos</a>
                            <a class="dropdown-item" href="#"
                                onclick="window.open('../comercial/recepcion/index.php', '_blank');"><i
                                    class="fas fa-phone"></i> Internos</a>
                            <?php if ($_SESSION['numsuc'] < 100) { ?>
                                <a class="dropdown-item" href="#"
                                    onclick="location.href='../comercial/recepcion/direccionario/index.php'"><i
                                        class="fas fa-address-book"></i> Direccionario</a>
                            <?php } ?>
                        </div>
                    </li>
                <?php } ?>

                <?php if ($_SESSION['numsuc'] > 100 && $_SESSION['habPedidos'] == 1 && $_SESSION['usuarioUy'] != 1) { ?>
                    <!-- DISTRIBUCION INICIAL -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" data-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-truck-loading"></i> Distribucion Inicial
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                            <a class="dropdown-item" href="#" onclick="location.href='DistriCuero/listOrdenesActivas.php'"><i
                                    class="fas fa-clipboard-list"></i> Nota de pedido</a>
                        </div>
                    </li>
                <?php } ?>

                <?php if ($codClient == 'FRSAL1' || $codClient == 'FRSAL2' || $codClient == 'FRSAL3') { ?>
                    <!-- ESTADISTICAS -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" data-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-chart-line"></i> Estadisticas
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                            <a class="dropdown-item" href="#" onclick="location.href='estadisticas/index.php'"><i
                                    class="fas fa-chart-pie"></i> Indices</a>
                            <a class="dropdown-item" href="#" onclick="location.href='estadisticas/ventas.php'"><i
                                    class="fas fa-chart-bar"></i> Ventas</a>
                        </div>
                    </li>
                <?php } ?>
            <?php } ?>
        </ul>
    </div>
</nav>

<!-- spinner -->
<div id="boxLoading"></div>

<script>
    (function () {
        var codClientNav = <?= json_encode(isset($codClient) ? $codClient : (isset($_SESSION['codClient']) ? $_SESSION['codClient'] : '')) ?>;

        window.nuevoPedido = function (tipo, idTipo) {
            var clientesNuevos = ['GTCABA', 'FRSMI2', 'FRBALL', 'FRSMT', 'FRCASE', 'FRFLO2'];
            var rutaPedidos = clientesNuevos.indexOf(codClientNav) !== -1
                ? 'pedidosNew/pedidos.php?tipo='
                : 'pedidos/pedidos.php?tipo=';
            location.href = rutaPedidos + idTipo;
        };

        var btn = document.querySelectorAll('.spinner');
        btn.forEach(function (el) {
            el.addEventListener('click', function () {
                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery('#boxLoading').addClass('loading');
                }
            });
        });

    document.querySelector("#FichadaPorLegajo").addEventListener("click", () => { $("#boxLoading").removeClass("loading") });

    // Quitar spinner al volver con el botón Atrás (bfcache)
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) { $("#boxLoading").removeClass("loading"); }
    });
</script>