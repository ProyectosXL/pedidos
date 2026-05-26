<?php 
session_start(); 
if(!isset($_SESSION['username'])){
	header("Location:../../login.php");
}else{
	
$permiso = $_SESSION['permisos'];

// Función auxiliar para obtener el día actual con formato
function strright($rightstring, $length) {
    return(substr($rightstring, -$length));
}

// Establecer fechas por defecto
if(!isset($_GET['desde'])){
	$ayer = date('Y-m').'-'.strright(('0'.((date('d')))),2);
} else {
	$desde = $_GET['desde'];
	$hasta = $_GET['hasta'];
}

$desde = isset($_GET['desde']) ? $_GET['desde'] : $ayer;
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : $ayer;
$remito = isset($_GET['remito']) ? $_GET['remito'] : '';

// Incluir la clase de guías
require_once __DIR__.'/class/Guia.php';
$guia = new Guia();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guías por Local</title>
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
        #guiasTable {
            font-size: 0.9rem;
            width: 100%;
            margin-bottom: 0;
        }
        #guiasTable th, #guiasTable td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        #guiasTable thead {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: #343a40;
            color: white;
        }
        #guiasTable thead th {
            background-color: #343a40;
            color: white;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
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
            #guiasTable {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>	

    <div class="fixed-header">
        <div class="container-fluid">
            <h1 class="page-title">
                <i class="fas fa-truck"></i> Guías por Local
            </h1>
            <div class="filter-section">
                <form class="row g-3" action="" id="sucu" method="GET">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Sucursal:</label>
                        <select class="form-select form-select-sm" name="sucursal" id="sucursal">
                            <option value="TODOS" <?= (!isset($_GET['sucursal']) || $_GET['sucursal'] == 'TODOS') ? 'selected' : '' ?>>Todos</option> 
                            <option value="FRBAUD" <?= (isset($_GET['sucursal']) && $_GET['sucursal'] == 'FRBAUD') ? 'selected' : '' ?>>BAULERA</option> 
                            <option value="FRORCE" <?= (isset($_GET['sucursal']) && $_GET['sucursal'] == 'FRORCE') ? 'selected' : '' ?>>VELEZ</option> 
                            <option value="FRORIG" <?= (isset($_GET['sucursal']) && $_GET['sucursal'] == 'FRORIG') ? 'selected' : '' ?>>DINO</option> 
                            <option value="FRORNC" <?= (isset($_GET['sucursal']) && $_GET['sucursal'] == 'FRORNC') ? 'selected' : '' ?>>NUEVO CENTRO</option> 
                            <option value="FRORSJ" <?= (isset($_GET['sucursal']) && $_GET['sucursal'] == 'FRORSJ') ? 'selected' : '' ?>>SAN JUAN</option> 
                            <option value="FRPASJ" <?= (isset($_GET['sucursal']) && $_GET['sucursal'] == 'FRPASJ') ? 'selected' : '' ?>>PASEO DEL JOCKEY</option> 
                            <option value="FRPRIN" <?= (isset($_GET['sucursal']) && $_GET['sucursal'] == 'FRPRIN') ? 'selected' : '' ?>>RIVERA</option> 
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
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Remito:</label>
                        <input class="form-control form-control-sm" type="text" name="remito" placeholder="Ingrese remito" value="<?= htmlspecialchars($remito) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-search"></i> Consultar
                        </button>
                    </div>
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
		$resultados = $guia->traerGuiasPorSucursal($suc, $desde, $hasta, $remito);

		if (empty($resultados)) {
			echo '<div class="alert alert-info">No se encontraron registros para los filtros seleccionados.</div>';
		} else {
			?>
			<div class="table-container">
				<table id="guiasTable" class="table table-striped table-hover">
					<thead>
						<tr>
							<th>CLIENTE</th>
							<th>FECHA_COMP</th>
							<th>N_COMP</th>
							<th>NRO_GUIA</th>
							<th>FECHA_GUIA</th>
							<th>OBSERVACIONES</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$totalRegistros = 0;
						foreach($resultados as $v){
							$totalRegistros++;
						?>
							<tr>
								<td><?= htmlspecialchars($v['CLIENTE']) ?></td>
								<td><?= htmlspecialchars($v['FECHA_COMP']) ?></td>
								<td>
									<a href="detalleRem.php?remito=<?= $v['N_COMP'] ?>&suc=<?= $v['CLIENTE'] ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>" 
									   class="text-decoration-none fw-bold">
										<?= htmlspecialchars($v['N_COMP']) ?>
									</a>
								</td>
								<td><?= htmlspecialchars($v['NRO_GUIA']) ?></td>
								<td><?= htmlspecialchars($v['FECHA_GUIA']) ?></td>
								<td><?= htmlspecialchars($v['OBSERVACIONES']) ?></td>
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
		$resultados = $guia->traerGuiasTodasSucursales($desde, $hasta, $remito);

		if (empty($resultados)) {
			echo '<div class="alert alert-info">No se encontraron registros para los filtros seleccionados.</div>';
		} else {
			?>
			<div class="table-container">
				<table id="guiasTable" class="table table-striped table-hover">
					<thead>
						<tr>
							<th>CLIENTE</th>
							<th>FECHA_COMP</th>
							<th>N_COMP</th>
							<th>NRO_GUIA</th>
							<th>FECHA_GUIA</th>
							<th>OBSERVACIONES</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$totalRegistros = 0;
						foreach($resultados as $v){
							$totalRegistros++;
						?>
							<tr>
								<td><?= htmlspecialchars($v['CLIENTE']) ?></td>
								<td><?= htmlspecialchars($v['FECHA_COMP']) ?></td>
								<td>
									<a href="detalleRem.php?remito=<?= $v['N_COMP'] ?>&suc=<?= $v['CLIENTE'] ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>" 
									   class="text-decoration-none fw-bold">
										<?= htmlspecialchars($v['N_COMP']) ?>
									</a>
								</td>
								<td><?= htmlspecialchars($v['NRO_GUIA']) ?></td>
								<td><?= htmlspecialchars($v['FECHA_GUIA']) ?></td>
								<td><?= htmlspecialchars($v['OBSERVACIONES']) ?></td>
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

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
</body>
</html>
<?php } ?>
