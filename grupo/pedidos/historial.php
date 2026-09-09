<?php
require_once __DIR__ . '/../../class/GrupoSesion.php';
require_once __DIR__ . '/../../class/sucursal.php';

GrupoSesion::requiereLogin('../../login.php');

// Por defecto: últimos 7 días incluyendo hoy
$hoy = date('Y-m-d');
$hace7Dias = date('Y-m-d', strtotime('-6 days'));
$desde = isset($_GET['desde']) ? $_GET['desde'] : $hace7Dias;
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : $hoy;
$sucursalSeleccionada = $_GET['sucursal'] ?? 'TODOS';

$sucursalObj = new Sucursal();
$opcionesSucursales = $sucursalObj->obtenerSucursalesParaSelector(GrupoSesion::obtenerBaseDatos());

require_once __DIR__ . '/class/HistorialPedido.php';
$historial = new HistorialPedido();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Pedidos</title>
    <link rel="shortcut icon" href="../../css/icono.jpg" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background-color: #f8f9fa;
            padding: 15px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            width: 100%;
        }
        .fixed-header .container-fluid {
            padding: 0 15px;
        }
        /* --header-h la recalcula el JS con la altura real del encabezado fijo */
        :root {
            --header-h: 150px;
        }
        .container-fluid.table-wrapper {
            margin-top: calc(var(--header-h) + 15px);
            padding: 0 15px;
        }
        .table-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: calc(100vh - var(--header-h) - 45px);
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        #historialTable {
            font-size: 0.9rem;
            width: 100%;
            margin-bottom: 0;
        }
        #historialTable th, #historialTable td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        #historialTable thead {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: #343a40;
            color: white;
        }
        #historialTable thead th {
            background-color: #343a40;
            color: white;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
        }
        .badge-estado {
            padding: 0.4em 0.8em;
            font-size: 0.85em;
            font-weight: 600;
        }
        .badge-aprobado {
            background-color: #28a745;
            color: white;
        }
        .badge-anulado {
            background-color: #dc3545;
            color: white;
        }
        .badge-reposicion {
            background-color: #0d6efd;
            color: white;
        }
        .badge-distribucion {
            background-color: #6610f2;
            color: white;
        }
        .badge-rotacion {
            background-color: #fd7e14;
            color: white;
        }
        .badge-otro {
            background-color: #6c757d;
            color: white;
        }
        .search-box {
            position: relative;
        }
        .search-box input {
            padding-right: 1.8rem;
        }
        .clear-search {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        .page-title {
            font-size: 1.3rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        .filter-section {
            background-color: #e9ecef;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 0;
        }
        .filter-section .form-label {
            margin-bottom: 0.2rem;
            font-size: 0.85rem;
        }
        .botones-filtro .btn {
            white-space: nowrap;
        }
        #loadingOverlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background-color: rgba(255, 255, 255, 0.75);
            align-items: center;
            justify-content: center;
        }
        #loadingOverlay.show {
            display: flex;
        }
        #loadingOverlay .loading-box {
            background-color: #fff;
            padding: 1.5rem 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12);
            text-align: center;
        }
        #loadingOverlay .loading-text {
            margin-top: 0.75rem;
            font-size: 0.95rem;
            color: #495057;
        }
        @media (max-width: 768px) {
            #historialTable {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>

    <div id="loadingOverlay">
        <div class="loading-box">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <div class="loading-text">Consultando pedidos, aguarde un momento...</div>
        </div>
    </div>

    <div class="fixed-header">
        <div class="container-fluid">
            <h1 class="page-title">
                <i class="fas fa-history"></i> Historial de Pedidos
            </h1>
            <div class="filter-section">
                <?php $hayFiltrosTabla = isset($_GET['sucursal']); ?>
                <form class="row g-2 align-items-end" action="" id="sucu" method="GET">
                    <div class="<?= $hayFiltrosTabla ? 'col-lg-2 col-md-4 col-sm-6' : 'col-lg-4 col-md-5 col-sm-6' ?>">
                        <label class="form-label fw-bold" for="sucursal">Sucursal:</label>
                        <select class="form-select form-select-sm" name="sucursal" id="sucursal">
                            <option value="TODOS" <?= $sucursalSeleccionada === 'TODOS' ? 'selected' : '' ?>>Todos</option>
                            <?php foreach ($opcionesSucursales as $opcion): ?>
                            <option value="<?= htmlspecialchars($opcion['codigo']) ?>" <?= $sucursalSeleccionada === $opcion['codigo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcion['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-3 col-6">
                        <label class="form-label fw-bold" for="desde">Desde:</label>
                        <input class="form-control form-control-sm" type="date" name="desde" id="desde" value="<?= $desde ?>">
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-3 col-6">
                        <label class="form-label fw-bold" for="hasta">Hasta:</label>
                        <input class="form-control form-control-sm" type="date" name="hasta" id="hasta" value="<?= $hasta ?>">
                    </div>
                    <?php if($hayFiltrosTabla) { ?>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label class="form-label fw-bold" for="tipoFilter">Tipo:</label>
                        <select class="form-select form-select-sm" id="tipoFilter">
                            <option value="">Todos los tipos</option>
                            <option value="Reposición">Reposición</option>
                            <option value="Distribución">Distribución</option>
                            <option value="Rotación">Rotación</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-8 col-sm-6">
                        <label class="form-label fw-bold" for="searchBox">Buscar:</label>
                        <div class="search-box">
                            <input type="text" id="searchBox" class="form-control form-control-sm" placeholder="Cliente, pedido u observaciones...">
                            <i class="fas fa-times clear-search" id="clearSearch"></i>
                        </div>
                    </div>
                    <?php } ?>
                    <div class="col-lg-auto col-12 ms-lg-auto d-flex gap-2 botones-filtro">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-search"></i> Consultar
                        </button>
                        <a href="../index.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container-fluid table-wrapper">

<?php

if(isset($_GET['sucursal'])){

	$suc = $_GET['sucursal'];

	$resultados = ($suc <> 'TODOS')
		? $historial->traerHistorialPorSucursal($suc, $desde, $hasta)
		: $historial->traerHistorialTodasSucursales($desde, $hasta);

	if (empty($resultados)) {
		echo '<div class="alert alert-info">No se encontraron registros para los filtros seleccionados.</div>';
	} else {

?>
			<div class="table-container">
				<table id="historialTable" class="table table-striped table-hover">
					<thead>
						<tr>
							<th>CLIENTE</th>
							<th>FECHA</th>
							<th>PEDIDO</th>
							<th>TIPO</th>
							<th>OBSERVACIONES</th>
							<th>CANTIDAD</th>
							<th>ESTADO</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$totalRegistros = 0;
						foreach($resultados as $v){
							$totalRegistros++;
							$estadoClass = ($v['ESTADO'] == 'ANULADO') ? 'badge-anulado' : 'badge-aprobado';
							$tipo = HistorialPedido::tipoDePedido($v['TALON_PED'] ?? 0);
							$tipoClasses = [
								'Distribución' => 'badge-distribucion',
								'Reposición'   => 'badge-reposicion',
								'Rotación'     => 'badge-rotacion',
							];
							$tipoClass = $tipoClasses[$tipo] ?? 'badge-otro';
						?>
							<tr data-tipo="<?= htmlspecialchars($tipo) ?>">
								<td><?= htmlspecialchars($v['COD_CLIENT']) ?></td>
								<td><?= htmlspecialchars($v['FECHA']) ?></td>
								<td>
									<a href="detallePed.php?pedido=<?= urlencode($v['NRO_PEDIDO']) ?>&suc=<?= urlencode($v['COD_CLIENT']) ?>&talon=<?= urlencode($v['TALON_PED']) ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>"
									   class="text-decoration-none fw-bold">
										<?= htmlspecialchars($v['NRO_PEDIDO']) ?>
									</a>
								</td>
								<td>
									<span class="badge badge-estado <?= $tipoClass ?>" title="Talonario <?= (int)$v['TALON_PED'] ?>">
										<?= htmlspecialchars($tipo) ?>
									</span>
								</td>
								<td><?= htmlspecialchars($v['LEYENDA_1']) ?></td>
								<td class="text-end"><?= number_format($v['CANT'], 0, ',', '.') ?></td>
								<td>
									<span class="badge badge-estado <?= $estadoClass ?>">
										<?= htmlspecialchars($v['ESTADO']) ?>
									</span>
								</td>
							</tr>
						<?php
						}
						?>
					</tbody>
					<?php if($totalRegistros > 0) { ?>
					<tfoot>
						<tr class="table-info">
							<td colspan="5" class="text-end fw-bold">Total de registros:</td>
							<td class="text-end fw-bold"><?= $totalRegistros ?></td>
							<td></td>
						</tr>
					</tfoot>
					<?php } ?>
				</table>
			</div>
			<?php
	}
}
?>

<?php if (!empty($_GET['debug']) && $_GET['debug'] === '1'): ?>
    <div class="alert alert-secondary small mb-3">
        <strong>Debug historial</strong>
        <?php if (isset($resultados)): ?> — <?= count($resultados) ?> fila(s)<?php endif; ?>
        <pre class="mb-0 mt-2" style="max-height: 320px; overflow: auto; font-size: 0.75rem;"><?= htmlspecialchars(implode("\n", HistorialPedido::getDebugTrace())) ?></pre>
    </div>
<?php endif; ?>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Filtra por texto (cliente, pedido, observaciones) y por tipo de pedido
            function aplicarFiltros() {
                var searchTerm = ($('#searchBox').val() || '').toLowerCase();
                var tipoSeleccionado = $('#tipoFilter').val() || '';

                $('#historialTable tbody tr').each(function() {
                    var cliente = $(this).find('td:eq(0)').text().toLowerCase();
                    var pedido = $(this).find('td:eq(2)').text().toLowerCase();
                    var observaciones = $(this).find('td:eq(4)').text().toLowerCase();
                    var tipo = $(this).data('tipo') || '';

                    var coincideTexto = cliente.indexOf(searchTerm) !== -1 ||
                        pedido.indexOf(searchTerm) !== -1 ||
                        observaciones.indexOf(searchTerm) !== -1;
                    var coincideTipo = tipoSeleccionado === '' || tipo === tipoSeleccionado;

                    if (coincideTexto && coincideTipo) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }

            // Evento de búsqueda en el input
            $('#searchBox').on('keyup', aplicarFiltros);

            // Filtro por tipo de pedido
            $('#tipoFilter').on('change', aplicarFiltros);

            // Limpiar búsqueda
            $('#clearSearch').on('click', function() {
                $('#searchBox').val('');
                aplicarFiltros();
            });

            // El encabezado es fijo y cambia de alto según los filtros y el ancho:
            // se mide y se publica como variable CSS para que no tape la tabla.
            function ajustarAltoEncabezado() {
                var alto = $('.fixed-header').outerHeight();
                if (alto) {
                    document.documentElement.style.setProperty('--header-h', alto + 'px');
                }
            }

            ajustarAltoEncabezado();
            $(window).on('resize load', ajustarAltoEncabezado);

            // Spinner: la consulta al servidor puede demorar y la página vieja
            // queda visible mientras tanto.
            var overlay = $('#loadingOverlay');

            function mostrarSpinner() {
                overlay.addClass('show');
            }

            $('#sucu').on('submit', mostrarSpinner);
            $('#historialTable').on('click', 'a', mostrarSpinner);
            $('.botones-filtro a').on('click', mostrarSpinner);

            // Al volver con el botón "atrás" la página puede restaurarse desde caché
            $(window).on('pageshow', function() {
                overlay.removeClass('show');
            });
        });
    </script>
</body>
</html>