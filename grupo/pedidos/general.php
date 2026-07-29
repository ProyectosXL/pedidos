<?php 
require_once __DIR__ . '/../../class/GrupoSesion.php';
GrupoSesion::requiereLogin('../../login.php');
GrupoSesion::redirigirSiCargaPendiente('../controller/cargaPedido.php');

include_once __DIR__.'/../../class/pedido.php';
	require_once $_SERVER['DOCUMENT_ROOT'] . '/sistemas/assets/js/js.php';
	require_once $_SERVER['DOCUMENT_ROOT'] . '/sistemas/Controlador/cargaPedidoNew.php';
	
	$_SESSION['depo'] = '01';
	$_SESSION['tipo_pedido'] = 'GENERAL';
		
	$suc = $_SESSION['numsuc'];
	$codClient = $_SESSION['username'];
	$tipo_cli = $_SESSION['tipo'];
	$esUsuarioUy = isset($_SESSION['usuarioUy']) ? $_SESSION['usuarioUy'] : null;
	$db = 'central';

	if($esUsuarioUy == 1){
		$db = 'uy';
	}

	$pedido = new Pedido();
	$pedidos = $pedido->listarPedidoCordoba($db);
	

	require_once __DIR__.'/../../class/sucursal.php';
	$sucursalObj = new Sucursal();
	$resolucionSucursales = $sucursalObj->construirSucursalesActivasParaPedido($db);
	$sucursalesActivasInfo = $resolucionSucursales['info'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga de Pedidos - General</title>
    <link rel="shortcut icon" href="../../images/logo.jpg" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/preloader.css">
    <link rel="stylesheet" href="css/general.css">
</head>
<body>
    <div id="aguarde" style="display: none;">
        <h1 class="text-center">Aguarde un momento por favor
            <div hidden id="pedidosCount"><?= empty($pedidos) ? '0' : '1' ?></div>
            <div class="spinner-border text-dark" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </h1>
    </div>

    <div class="container" style="display: none;">
        <div class="cubo">
            <span style="display: flex; justify-content: center; align-items: center;">XL</span>
            <span style="display: flex; justify-content: center; align-items: center;">XL</span>
            <span style="display: flex; justify-content: center; align-items: center;">XL</span>
            <span></span>
            <span style="display: flex; justify-content: center; align-items: center;">XL</span>
            <span style="display: flex; justify-content: center; align-items: center;">XL</span>
        </div>
        <div>
            <div class="loading">
                <h1>Aguarde un momento...</h1>
                <p></p>
            </div>
        </div>
    </div>

    <div class="fixed-header">
        <div class="container-fluid">
            <div class="row align-items-center flex-nowrap">
                <div class="col-auto">
                    <h1 class="page-title mb-0" style="font-size: 1rem;">
                        <i class="fas fa-shopping-cart"></i> Carga de Pedido - General
                    </h1>
                </div>
                <div class="col-auto">
                    <a href="../index.php" class="btn btn-sm btn-outline-secondary py-1">
                        <i class="fas fa-home"></i> Inicio
                    </a>
                </div>
                <div class="col">
                    <div class="search-box">
                        <input type="text" id="searchBox" class="form-control form-control-sm py-1" placeholder="Buscar...">
                        <i class="fas fa-times clear-search" id="clearSearch"></i>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="input-group input-group-sm py-0" style="max-width: 90px;">
                        <span class="input-group-text py-1 px-1">SKU</span>
                        <input type="text" id="totalSKU" class="form-control form-control-sm py-1 px-1" value="0" readonly>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="input-group input-group-sm py-0" style="max-width: 100px;">
                        <span class="input-group-text py-1 px-1">Unid.</span>
                        <input type="text" name="total_todo" id="total" class="form-control form-control-sm py-1 px-1" value="0" readonly>
                    </div>
                </div>
                <div class="col-auto importe-summary-expanded">
                    <div class="input-group input-group-sm py-0" style="max-width: 180px;">
                        <span class="input-group-text py-1 px-1">Importe</span>
                        <input type="text" name="total_precio" id="totalPrecio" class="form-control form-control-sm py-1 px-1" value="0" onchange="verificarCredito()" readonly>
                    </div>
                    <div id="creditAlertContainer" style="position:absolute;z-index:1000;width:22%;"></div>
                </div>
                <div class="col-auto">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary py-1 dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <i class="fas fa-thumbtack"></i> Fijar columnas
                        </button>
                        <ul class="dropdown-menu p-2" id="menuColumnasFijas">
                            <li><label class="dropdown-item mb-0"><input type="checkbox" class="chk-col-fija form-check-input me-1" value="foto"> FOTO</label></li>
                            <li><label class="dropdown-item mb-0"><input type="checkbox" class="chk-col-fija form-check-input me-1" value="codigo"> CODIGO</label></li>
                            <li><label class="dropdown-item mb-0"><input type="checkbox" class="chk-col-fija form-check-input me-1" value="descripcion"> DESCRIPCION</label></li>
                            <li><label class="dropdown-item mb-0"><input type="checkbox" class="chk-col-fija form-check-input me-1" value="rubro"> RUBRO</label></li>
                            <li><label class="dropdown-item mb-0"><input type="checkbox" class="chk-col-fija form-check-input me-1" value="stockcc"> STOCK CC</label></li>
                            <li><label class="dropdown-item mb-0"><input type="checkbox" class="chk-col-fija form-check-input me-1" value="precio"> PRECIO</label></li>
                        </ul>
                        <button class="btn btn-success py-1" id="btnExport"><i class="fas fa-file-excel"></i> Exportar</button>
                        <button type="button" id="btnEnviar" class="btn btn-primary btn-sm py-1"><i class="fas fa-paper-plane"></i> Enviar</button>
                        <span id="sinConexion" class="badge bg-danger align-middle ms-1" style="display: none;">SIN CONEXIÓN</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid table-wrapper">
        <?php
        // Calcular rango de fechas para el período de análisis de ventas (últimos 30 días)
        $fechaHasta = date('d/m/Y');
        $fechaDesde = date('d/m/Y', strtotime('-30 days'));
        ?>
        <div class="alert alert-info mb-3" role="alert">
            <i class="fas fa-calendar-alt"></i> 
            <strong>Período de análisis de ventas:</strong> 
            Las unidades vendidas corresponden al período del <strong><?= $fechaDesde ?></strong> al <strong><?= $fechaHasta ?></strong> (últimos 30 días)
        </div>
        <form id='formulario' method="POST" onkeypress="return pulsar(event)" style="position: relative;">
        <div class="table-container">
            <table id="id_tabla" class="table table-striped table-hover">
                <thead class="table-fixed-header">
                    <tr>
                        <th data-col="foto">FOTO</th>
                        <th id="thCodigoSort" data-col="codigo" class="sortable-col">CODIGO <i class="fas fa-sort" id="iconSortCodigo"></i></th>
                        <th></th>
                        <th data-col="descripcion">DESCRIPCION</th>
                        <th data-col="rubro">RUBRO</th>
                        <th></th>
                        <th data-col="stockcc">STOCK CC</th>
                        <th></th>
                        <th data-col="precio">PRECIO</th>
                        <?php foreach ($sucursalesActivasInfo as $suc => $info): ?>
                            <th colspan="3" class="sucursal-header" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($info['nombreCompleto']) ?>"><?= htmlspecialchars($info['codClient']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th data-col="foto"></th>
                        <th data-col="codigo"></th>
                        <th></th>
                        <th data-col="descripcion"></th>
                        <th data-col="rubro"></th>
                        <th></th>
                        <th data-col="stockcc"></th>
                        <th></th>
                        <th data-col="precio"></th>
                        <?php foreach ($sucursalesActivasInfo as $suc => $info): ?>
                            <th>
                                <i class="fas fa-boxes"></i> Stock
                            </th>
                            <th>
                                <i class="fas fa-chart-line"></i> Vendido
                            </th>
                            <th>
                                <i class="fas fa-shopping-cart"></i>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="tabla">
                    <?php foreach ($pedidos as $v) {
                        $imageName = substr($v['COD_ARTICU'], 0, 13);
                        $imageUrl = file_exists("../../../Imagenes/".$imageName.".jpg") ? "../../../Imagenes/".$imageName.".jpg" : "";
                        $isSale = (substr($v['DESCRIPCIO'], -11) == '-- SALE! --');
                        $description = $isSale ? substr($v['DESCRIPCIO'], 0, -11) : $v['DESCRIPCIO'];
                    ?>
                        <tr>
                            <td data-col="foto">
                                <?php if ($imageUrl): ?>
                                    <img src="<?= $imageUrl ?>" alt="Sin imagen" height="40" width="40" class="product-image" data-bs-toggle="modal" data-bs-target="#imageModal<?= $imageName ?>">
                                <?php else: ?>
                                    <span>Sin</span>
                                <?php endif; ?>
                            </td>
                            <td data-col="codigo"><?= $v['COD_ARTICU'] ?></td>
                            <td><input name="codArt[]" value="<?= $v['COD_ARTICU'] ?>" type="hidden"></td>
                            <td data-col="descripcion">
                                <?= $description ?>
                                <?= $isSale ? '<span class="sale-badge">SALE</span>' : '' ?>
                            </td>
                            <td data-col="rubro"><?= $v['RUBRO'] ?></td>
                            <td><input name="rubro[]" value="<?= $v['RUBRO'] ?>" type="hidden"></td>
                            <td id="stock" data-col="stockcc"><?= (int)($v['CANT_STOCK']) ?></td>
                            <td><input name="stock[]" value="<?= $v['CANT_STOCK'] ?>" type="hidden"></td>
                            <td id="precio" data-col="precio" data-precio-raw="<?= (int)($v['PRECIO'] ?? 0) ?>">$ <?= number_format((int)($v['PRECIO'] ?? 0), 0, ',', '.') ?></td>

                            <?php foreach ($sucursalesActivasInfo as $suc => $info): ?>
                                <td class="stock-column"><?= (int)($v[$suc . '_STOCK'] ?? 0) ?></td>
                                <td><?= (int)($v[$suc . '_VENDIDO'] ?? 0) ?></td>
                                <td><input type="text" inputmode="numeric" name="cantPed_<?= $suc ?>[]" value="0" onkeyup="total();precioTotal()" onblur="validarInputCantidad(this)" size="1" tabindex="1" class="form-control form-control-sm pedido-input <?= $info['codClient'] ?>"></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7"><strong>Totales</strong></td>
                        <td></td>
                        <td id="totalPrecioFooter" data-col="precio">0</td>
                        <?php foreach ($sucursalesActivasInfo as $suc => $info): ?>
                            <td></td>
                            <td></td>
                            <td id="totalSuc_<?= $suc ?>" class="total-suc">0</td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        </form>
    </div>

    <?php foreach ($pedidos as $v) {
        $imageName = substr($v['COD_ARTICU'], 0, 13);
        $imageUrl = file_exists("../../../Imagenes/".$imageName.".jpg") ? "../../../Imagenes/".$imageName.".jpg" : "";
    ?>
        <div class="modal fade" id="imageModal<?= $imageName ?>" tabindex="-1" aria-labelledby="imageModalLabel<?= $imageName ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imageModalLabel<?= $imageName ?>"><?= $v['DESCRIPCIO'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="<?= $imageUrl ?>" alt="<?= $imageName ?>.jpg - imagen no encontrada" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php
    $suc = isset($_SESSION['numsuc']) ? $_SESSION['numsuc'] : '';
    $codClient = isset($_SESSION['codClient']) ? $_SESSION['codClient'] : '';
    $t_ped = isset($_SESSION['tipo_pedido']) ? $_SESSION['tipo_pedido'] : '';
    $depo = isset($_SESSION['depo']) ? $_SESSION['depo'] : '';
    $talon_ped = 97;
    ?>

    <script>
        let suc = '<?= $suc; ?>'
        let codClient = '<?= $codClient; ?>'
        let t_ped = '<?= $t_ped; ?>'
        let depo = '<?= $depo; ?>'
        let talon_ped = '<?= $talon_ped; ?>'
        let cupo_credito = '<?= isset($_SESSION['cupoCredi']) ? (int)$_SESSION['cupoCredi'] : 0;  ?>'
        let sucursalesIds = [<?= implode(',', array_map(function($s) { return "'" . $s . "'"; }, array_keys($sucursalesActivasInfo))) ?>];
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
    <script src="js/main.js?v=<?= filemtime(__DIR__ . '/js/main.js') ?>"></script>
    <!-- <script src="../../pedidos/js/envio.js"></script>
    <script src="../../pedidos/js/jquery.table2excel.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Función de búsqueda simple
            function customSearch(value) {
                var searchTerm = value.toLowerCase();
                $('#id_tabla tbody tr').each(function() {
                    var codigo = $(this).find('td:eq(1)').text().toLowerCase();
                    var descripcion = $(this).find('td:eq(3)').text().toLowerCase();
                    var rubro = $(this).find('td:eq(4)').text().toLowerCase();
                    
                    if (codigo.indexOf(searchTerm) !== -1 || 
                        descripcion.indexOf(searchTerm) !== -1 || 
                        rubro.indexOf(searchTerm) !== -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }

            // Evento de búsqueda en el input
            $('#searchBox').on('keyup', function() {
                customSearch(this.value);
            });

            // Limpiar búsqueda
            $('#clearSearch').on('click', function() {
                $('#searchBox').val('');
                customSearch('');
            });

            // Botón exportar
            $('#btnExport').on('click', function() {
                $('#id_tabla').table2excel({
                    exclude: ".noExl",
                    name: "Pedidos_Cordoba",
                    filename: "Pedidos_Cordoba_" + new Date().toISOString().split('T')[0]
                });
            });

            // Botón Enviar del header - dispara submit NATIVO (requestSubmit)
            // Nota: $('#formulario').submit() de jQuery NO ejecuta addEventListener('submit') de main.js
            $('#btnEnviar').on('click', function(e) {
                e.preventDefault();
                var formEl = document.getElementById('formulario');
                if (!formEl) {
                    console.error('No se encontró #formulario');
                    return;
                }
                if (typeof formEl.requestSubmit === 'function') {
                    formEl.requestSubmit();
                } else if (typeof window.enviarPedidoGrupo === 'function') {
                    window.enviarPedidoGrupo();
                } else {
                    formEl.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            });

            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                new bootstrap.Tooltip(el);
            });
            if (typeof total === 'function') total();
            if (typeof precioTotal === 'function') precioTotal();

            if(document.querySelector("#pedidosCount") && document.querySelector("#pedidosCount").textContent == 0){
                Swal.fire({
                    icon: "error",
                    title: "Error de acceso",
                    text: "No se pudo acceder a la plataforma de pedidos. Por favor intente nuevamente.",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Reintentar",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        location.reload();
                    }
                });
            }
        });
    </script>
</body>
</html>
