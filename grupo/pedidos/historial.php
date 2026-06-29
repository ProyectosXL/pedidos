<?php
require_once __DIR__ . '/../../class/GrupoSesion.php';
require_once __DIR__ . '/../../class/sucursal.php';

GrupoSesion::requiereLogin('../../login.php');

$ayer = date('Y-m-d', strtotime('-1 day'));
$desde = isset($_GET['desde']) ? $_GET['desde'] : $ayer;
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : $ayer;
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
        .container-fluid.table-wrapper {
            margin-top: 200px;
            padding: 0 15px;
        }
        .table-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: calc(100vh - 220px);
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
        .search-box {
            position: relative;
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
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .filter-section {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        @media (max-width: 768px) {
            .container-fluid.table-wrapper {
                margin-top: 250px;
            }
            #historialTable {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>	

    <div class="fixed-header">
        <div class="container-fluid">
            <h1 class="page-title">
                <i class="fas fa-history"></i> Historial de Pedidos
            </h1>
            <div class="filter-section">
                <form class="row g-3" action="" id="sucu" method="GET">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Sucursal:</label>
                        <select class="form-select form-select-sm" name="sucursal" id="sucursal">
                            <option value="TODOS" <?= $sucursalSeleccionada === 'TODOS' ? 'selected' : '' ?>>Todos</option>
                            <?php foreach ($opcionesSucursales as $opcion): ?>
                            <option value="<?= htmlspecialchars($opcion['codigo']) ?>" <?= $sucursalSeleccionada === $opcion['codigo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcion['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Desde:</label>
                        <input class="form-control form-control-sm" type="date" name="desde" value="<?= $desde ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Hasta:</label>
                        <input class="form-control form-control-sm" type="date" name="hasta" value="<?= $hasta ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm me-2">
                            <i class="fas fa-search"></i> Consultar
                        </button>
                        <a href="../index.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                    <?php if(isset($_GET['sucursal'])) { ?>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="search-box w-100">
                            <input type="text" id="searchBox" class="form-control form-control-sm" placeholder="Buscar por cliente, pedido u observaciones...">
                            <i class="fas fa-times clear-search" id="clearSearch"></i>
                        </div>
                    </div>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>

    <div class="container-fluid table-wrapper">

<?php

if(isset($_GET['sucursal'])){
	
	$suc = $_GET['sucursal'];


	if($suc <> 'TODOS'){

		// Usar la clase para obtener datos de una sucursal específica
		$resultados = $historial->traerHistorialPorSucursal($suc, $desde, $hasta);

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
						?>
							<tr>
								<td><?= htmlspecialchars($v['COD_CLIENT']) ?></td>
								<td><?= htmlspecialchars($v['FECHA']) ?></td>
								<td>
									<a href="detallePed.php?pedido=<?= $v['NRO_PEDIDO'] ?>&suc=<?= $v['COD_CLIENT'] ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>" 
									   class="text-decoration-none fw-bold">
										<?= htmlspecialchars($v['NRO_PEDIDO']) ?>
									</a>
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
							<td colspan="4" class="text-end fw-bold">Total de registros:</td>
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
	else{
		// Usar la clase para obtener datos de todas las sucursales
		$resultados = $historial->traerHistorialTodasSucursales($desde, $hasta);

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
						?>
							<tr>
								<td><?= htmlspecialchars($v['COD_CLIENT']) ?></td>
								<td><?= htmlspecialchars($v['FECHA']) ?></td>
								<td>
									<a href="detallePed.php?pedido=<?= $v['NRO_PEDIDO'] ?>&suc=<?= $v['COD_CLIENT'] ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>" 
									   class="text-decoration-none fw-bold">
										<?= htmlspecialchars($v['NRO_PEDIDO']) ?>
									</a>
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
							<td colspan="4" class="text-end fw-bold">Total de registros:</td>
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
            // Función de búsqueda
            function customSearch(value) {
                var searchTerm = value.toLowerCase();
                $('#historialTable tbody tr').each(function() {
                    var cliente = $(this).find('td:eq(0)').text().toLowerCase();
                    var pedido = $(this).find('td:eq(2)').text().toLowerCase();
                    var observaciones = $(this).find('td:eq(3)').text().toLowerCase();
                    
                    if (cliente.indexOf(searchTerm) !== -1 || 
                        pedido.indexOf(searchTerm) !== -1 || 
                        observaciones.indexOf(searchTerm) !== -1) {
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
        });
    </script>
</body>
</html>