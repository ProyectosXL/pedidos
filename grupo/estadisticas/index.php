<?php
session_start();

if(!isset($_SESSION['username'])){
	header("Location:../../../sistemas/login.php");
} else {
	
	// Incluir la clase de estadísticas
	require_once __DIR__.'/../../class/estadistica.php';
	$estadistica = new Estadistica();
	
	// Configuración de caché
	$cacheDir = __DIR__ . "/cache";
	if (!is_dir($cacheDir)) {
		mkdir($cacheDir, 0755, true);
	}
	
	// Obtener año del filtro (por defecto año actual)
	$anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
	$anio = ($anio >= 2020 && $anio <= date('Y')) ? $anio : date('Y');
	
	// Clave de caché basada en año y usuario
	$cacheKey = "estadisticas_" . ($_SESSION['username'] ?? 'guest') . "_" . $anio;
	$cacheFile = $cacheDir . "/" . $cacheKey . ".json";
	$cacheTime = 3600; // 1 hora
	
	$data = null;
	$fromCache = false;
	
	// Intentar cargar desde caché
	if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
		$data = json_decode(file_get_contents($cacheFile), true);
		if ($data !== null) {
			$fromCache = true;
		}
	}
	
	// Si no hay caché válido, ejecutar queries usando la clase

	// Obtener estadísticas mensuales
	$rows = $estadistica->traerEstadisticasMensuales($anio);
	// Obtener totales
	$totales = $estadistica->traerTotalesEstadisticas($anio);
	
	// Preparar datos para guardar en caché
	$data = [
		'rows' => $rows,
		'totales' => $totales,
		'anio' => $anio,
		'timestamp' => time()
	];
	
	// Guardar en caché
	file_put_contents($cacheFile, json_encode($data));

	
	$rows = $data['rows'];
	$totales = $data['totales'];
?>
	
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Estadísticas - Córdoba</title>
	<?php include '../../assets/css/header.php'; ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
	<style type="text/css">
		body {
			padding-top: 0;
			overflow-x: hidden;
			overflow-y: auto;
		}
		
		/* Estilos responsive */
		.table-responsive {
			overflow-x: auto;
			-webkit-overflow-scrolling: touch;
			margin-bottom: 20px;
		}
		
		@media (max-width: 768px) {
			table th, table td {
				font-size: 0.8rem;
				padding: 0.5rem;
			}
			.table-responsive {
				font-size: 0.85rem;
			}
		}
		
		/* Estilos para tooltips */
		.tooltip-icon {
			cursor: help;
			color: #6c757d;
			margin-left: 5px;
			font-size: 0.9em;
		}
		
		.tooltip-icon:hover {
			color: #007bff;
		}
		
		/* Estilos para el filtro */
		.filter-container {
			background-color: #f8f9fa;
			padding: 15px;
			border-radius: 5px;
			margin-bottom: 20px;
		}
		
		/* Estilos para el indicador de caché */
		.cache-indicator {
			font-size: 0.85rem;
			color: #28a745;
			margin-left: 10px;
		}
		
		/* Estilos para el preloader */
		#loading-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.7);
			z-index: 9999;
			display: flex;
			justify-content: center;
			align-items: center;
			color: white;
		}
		
		#loading-overlay .spinner-border {
			width: 3rem;
			height: 3rem;
		}
		
		/* Mejoras visuales para la tabla */
		.table thead th {
			background-color: #e9ecef;
			border-bottom: 2px solid #dee2e6;
			position: sticky;
			top: 0;
			z-index: 10;
		}
		
		.table tbody tr:hover {
			background-color: #f5f5f5;
		}
		
		.table tbody tr:last-child {
			font-weight: bold;
			background-color: #e9ecef;
		}
		
		.page-title {
			font-size: 1.3rem;
			margin-bottom: 1rem;
			font-weight: 600;
		}
	</style>
</head>
<body>

<!-- Preloader -->
<div id="loading-overlay" style="display:none;">
	<div style="text-align:center;">
		<div class="spinner-border" role="status">
			<span class="sr-only">Cargando...</span>
		</div>
		<p class="mt-3">Cargando estadísticas...</p>
	</div>
</div>

<div class="container-fluid mt-3">
	
	<!-- Filtro de período -->
	<div class="filter-container">
		<h1 class="page-title">
			<i class="fas fa-chart-line"></i> Estadísticas <?= $anio ?>
		</h1>
		<form method="GET" class="mb-0" id="filterForm">
			<div class="row align-items-end">
				<div class="col-md-3">
					<label for="anio" class="form-label"><strong>Año:</strong></label>
					<select name="anio" id="anio" class="form-control">
						<?php 
						$anioActual = date('Y');
						for ($y = $anioActual; $y >= 2020; $y--): 
						?>
							<option value="<?= $y ?>" <?= ($anio == $y) ? 'selected' : '' ?>>
								<?= $y ?>
							</option>
						<?php endfor; ?>
					</select>
				</div>
				<div class="col-md-3">
					<button type="submit" class="btn btn-primary">
						<i class="fas fa-filter"></i> Filtrar
					</button>
					<?php if ($fromCache): ?>
						<span class="cache-indicator" title="Datos cargados desde caché">
							<i class="fas fa-check-circle"></i> Caché
						</span>
					<?php endif; ?>
				</div>
			</div>
		</form>
	</div>

	<!-- Tabla responsive -->
	<div class="table-responsive">
		<table class="table table-striped table-sm table-hover">
			<thead>
				<tr>
					<th style="width: 10%">
						MES / <a href="detalle_sem.php">SEM</a>
					</th>
					<th style="width: 10%">
						VENTAS<br>CON IVA
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Total de ventas con IVA incluido"></i>
					</th>
					<th style="width: 8%">
						CANT<br>COMP
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Cantidad total de comprobantes emitidos"></i>
					</th>
					<th style="width: 8%">
						ARTICULOS
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Cantidad total de artículos vendidos"></i>
					</th>
					<th style="width: 8%">
						PROM<br>TICKET
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Promedio de importe por comprobante"></i>
					</th>
					<th style="width: 8%">
						2DO PROD
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Cantidad de tickets con 2 o más productos"></i>
					</th>
					<th style="width: 8%">
						PROM 2DO
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Porcentaje promedio de tickets con 2do producto"></i>
					</th>
					<th style="width: 8%">
						3ER PROD
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Cantidad de tickets con 3 o más productos"></i>
					</th>
					<th style="width: 8%">
						PROM 3ER
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Porcentaje promedio de tickets con 3er producto"></i>
					</th>
					<th style="width: 8%">
						CAMBIOS
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Cantidad total de cambios realizados"></i>
					</th>
					<th style="width: 8%">
						% CAMBIOS
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Porcentaje de comprobantes con cambios"></i>
					</th>
					<th style="width: 8%">
						% INCREM
						<i class="fas fa-question-circle tooltip-icon" 
						   data-toggle="tooltip" 
						   title="Porcentaje de incremento promedio"></i>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php
				if (empty($rows)) {
					?>
					<tr>
						<td colspan="12" class="text-center">
							<p class="mt-3">No hay datos disponibles para el año <?= $anio ?></p>
						</td>
					</tr>
					<?php
				} else {
					foreach($rows as $v) {
						// Formatear MES si es necesario
						$mesDisplay = $v['MES'];
						if ($mesDisplay instanceof DateTime) {
							$mesDisplay = $mesDisplay->format('Y-m-d');
						}
						?>
						<tr>
							<td style="width: 10%">
								<a href="detalle_mes.php?mes=<?= htmlspecialchars($mesDisplay) ?>">
									<?= htmlspecialchars($mesDisplay) ?>
								</a>
							</td>
							<td style="width: 10%">
								<?= number_format($v['IMPORTE'], 0, '', '.') ?>
							</td>
							<td style="width: 8%">
								<?= number_format($v['COMP'], 0, '', '.') ?>
							</td>
							<td style="width: 8%">
								<?= number_format($v['ARTICULOS'], 0, '', '.') ?>
							</td>
							<td style="width: 8%">
								<?= (int)($v['PROM_TICKET']) ?>
							</td>
							<td style="width: 8%">
								<?= number_format($v['CANT_TICKET_2DO'], 0, '', '.') ?>
							</td>
							<td style="width: 8%">
								<?= $v['PROM_2DO'] ?>
							</td>
							<td style="width: 8%">
								<?= number_format($v['CANT_TICKET_3ER'], 0, '', '.') ?>
							</td>
							<td style="width: 8%">
								<?= $v['PROM_3ER'] ?>
							</td>
							<td style="width: 8%">
								<?= number_format($v['CANT_CAMBIOS'], 0, '', '.') ?>
							</td>
							<td style="width: 8%">
								<?= $v['PORC_CAMBIOS'] ?>
							</td>
							<td style="width: 8%">
								<?= $v['PORC_INCREM'] ?>
							</td>
						</tr>
						<?php
					}
					
					// Fila de totales (calculados en SQL)
					if ($totales && isset($totales['TOTAL_COMP']) && $totales['TOTAL_COMP'] > 0) {
						$promTicketTotal = (int)($totales['TOTAL_IMPORTE'] / $totales['TOTAL_COMP']);
						$prom2doTotal = number_format((($totales['TOTAL_2DO'] / $totales['TOTAL_COMP']) * 100), 1, ',', '.');
						$prom3erTotal = number_format((($totales['TOTAL_3ER'] / $totales['TOTAL_COMP']) * 100), 1, ',', '.');
						$porcCambiosTotal = number_format((($totales['TOTAL_CAMBIOS'] / $totales['TOTAL_COMP']) * 100), 1, ',', '.');
						$avgIncremTotal = number_format($totales['AVG_INCREM'], 1, ',', '.');
						?>
						<tr>
							<td style="width: 10%"><h6>TOTAL</h6></td>
							<td style="width: 10%"><h6><?= number_format($totales['TOTAL_IMPORTE'], 0, '', '.') ?></h6></td>
							<td style="width: 8%"><h6><?= number_format($totales['TOTAL_COMP'], 0, '', '.') ?></h6></td>
							<td style="width: 8%"><h6><?= number_format($totales['TOTAL_ARTICULOS'], 0, '', '.') ?></h6></td>
							<td style="width: 8%"><h6><?= $promTicketTotal ?></h6></td>
							<td style="width: 8%"><h6><?= number_format($totales['TOTAL_2DO'], 0, '', '.') ?></h6></td>
							<td style="width: 8%"><h6><?= $prom2doTotal ?></h6></td>
							<td style="width: 8%"><h6><?= number_format($totales['TOTAL_3ER'], 0, '', '.') ?></h6></td>
							<td style="width: 8%"><h6><?= $prom3erTotal ?></h6></td>
							<td style="width: 8%"><h6><?= number_format($totales['TOTAL_CAMBIOS'], 0, '', '.') ?></h6></td>
							<td style="width: 8%"><h6><?= $porcCambiosTotal ?></h6></td>
							<td style="width: 8%"><h6><?= $avgIncremTotal ?></h6></td>
						</tr>
						<?php
					}
				}
				?>
			</tbody>
		</table>
	</div>
</div>

<script>
	// Mostrar preloader al enviar formulario
	document.getElementById('filterForm')?.addEventListener('submit', function() {
		document.getElementById('loading-overlay').style.display = 'flex';
	});
	
	// Ocultar preloader cuando la página carga completamente
	window.addEventListener('load', function() {
		setTimeout(function() {
			document.getElementById('loading-overlay').style.display = 'none';
		}, 500);
	});
	
	// Inicializar tooltips de Bootstrap
	$(document).ready(function() {
		$('[data-toggle="tooltip"]').tooltip();
	});
</script>

</body>
</html>

<?php
}
?>
