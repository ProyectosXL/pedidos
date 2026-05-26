<?php
session_start();

// Definir función auxiliar si no existe (normalmente viene de header_simple.php)
if (!function_exists('strright')) {
	function strright($rightstring, $length) {
		return(substr($rightstring, -$length));
	}
}

// Incluir la clase de estadísticas
require_once __DIR__.'/../../class/estadistica.php';
$estadistica = new Estadistica();

// Establecer fechas por defecto
if(!isset($_GET['desde'])){
	$ayer = date('Y-m').'-'.strright(('0'.((date('d'))-1)),2);
} else {
	$desde = $_GET['desde'];
	$hasta = $_GET['hasta'];
}

$desde = isset($_GET['desde']) ? $_GET['desde'] : $ayer;
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : $ayer;
$sucursal = isset($_GET['sucursal']) ? $_GET['sucursal'] : 'TODOS';

// Obtener lista de sucursales
$sucursales = $estadistica->traerSucursales();

// Función para formatear fecha al formato argentino (dd/mm/yyyy)
function formatearFechaArgentina($fecha) {
    if (empty($fecha)) return '';
    $timestamp = strtotime($fecha);
    return date('d/m/Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Ventas - Córdoba</title>
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
        #ventasTable {
            font-size: 0.9rem;
            width: 100%;
            margin-bottom: 0;
        }
        #ventasTable th, #ventasTable td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        #ventasTable thead {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: #343a40;
            color: white;
        }
        #ventasTable thead th {
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
        .total-row {
            background-color: #d1ecf1;
            font-weight: bold;
        }
        .importe-column {
            text-align: right;
        }
        .charts-section {
            margin-bottom: 30px;
        }
        .chart-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #343a40;
        }
        .chart-container {
            position: relative;
            height: 350px;
        }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #3494e6 0%, #2980b9 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .toggle-charts {
            margin-bottom: 15px;
        }
        .date-range-info {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        .date-range-info strong {
            color: #0056b3;
        }
        @media (max-width: 768px) {
            .container-fluid.table-wrapper {
                margin-top: 250px;
            }
            #ventasTable {
                font-size: 0.8rem;
            }
            .filter-section .row > div {
                margin-bottom: 10px;
            }
            .chart-container {
                height: 300px;
            }
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>	

    <div class="fixed-header">
        <div class="container-fluid">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i> Estadísticas de Ventas - Córdoba
            </h1>
            <div class="filter-section">
                <form class="row g-3" action="" id="sucu" method="GET">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Sucursal:</label>
                        <select class="form-select form-select-sm" name="sucursal" id="sucursal">
                            <option value="TODOS" <?= ($sucursal == 'TODOS') ? 'selected' : '' ?>>Todos</option>
                            <?php foreach($sucursales as $suc): ?>
                                <option value="<?= htmlspecialchars($suc['NRO_SUCURSAL']) ?>" <?= ($sucursal == $suc['NRO_SUCURSAL']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($suc['DESC_SUCURSAL']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Desde:</label>
                        <input class="form-control form-control-sm" type="date" name="desde" value="<?= htmlspecialchars($desde) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Hasta:</label>
                        <input class="form-control form-control-sm" type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-search"></i> Consultar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container-fluid table-wrapper">

<?php

// Mostrar rango de fechas con formato argentino
echo '<div class="date-range-info">
	<i class="fas fa-calendar-alt"></i> <strong>Período de análisis:</strong> 
	Desde ' . formatearFechaArgentina($desde) . ' hasta ' . formatearFechaArgentina($hasta) . '
</div>';

if(isset($_GET['sucursal'])){
	
	$suc = $_GET['sucursal'];

	if($suc <> 'TODOS'){
		// Obtener ventas para una sucursal específica
		$resultados = $estadistica->traerVentasPorSucursal($suc, $desde, $hasta);
		// Obtener ventas por fecha para el gráfico temporal
		$ventasPorFecha = $estadistica->traerVentasPorFecha($suc, $desde, $hasta);
		// Obtener ventas por fecha y sucursal para el gráfico comparativo
		$ventasPorFechaYSucursal = $estadistica->traerVentasPorFechaYSucursal('TODOS', $desde, $hasta);

		if (empty($resultados)) {
			echo '<div class="alert alert-info">
				<i class="fas fa-info-circle"></i> No se encontraron registros para los filtros seleccionados.
			</div>';
		} else {
			// Preparar datos para gráficos
			$labels = [];
			$data = [];
			$sum = 0;
			$totalRegistros = 0;
			
			foreach($resultados as $v): 
				$labels[] = $v['SUCURSAL'];
				$data[] = $v['IMPORTE'];
				$sum += $v['IMPORTE'];
				$totalRegistros++;
			endforeach;
			
			// Preparar datos para gráfico temporal
			$fechasLabels = [];
			$fechasData = [];
			foreach($ventasPorFecha as $vf) {
				$fechasLabels[] = formatearFechaArgentina($vf['FECHA']);
				$fechasData[] = $vf['IMPORTE'];
			}
			
			// Preparar datos para gráfico por sucursal y fecha
			$ventasPorSucursalFecha = [];
			$todasLasFechas = [];
			
			// Verificar que hay datos
			if (!empty($ventasPorFechaYSucursal)) {
				foreach($ventasPorFechaYSucursal as $vfs) {
					$fecha = formatearFechaArgentina($vfs['FECHA']);
					$sucursal = $vfs['SUCURSAL'];
					
					if (!in_array($fecha, $todasLasFechas)) {
						$todasLasFechas[] = $fecha;
					}
					
					if (!isset($ventasPorSucursalFecha[$sucursal])) {
						$ventasPorSucursalFecha[$sucursal] = [];
					}
					$ventasPorSucursalFecha[$sucursal][$fecha] = $vfs['IMPORTE'];
				}
				
				// Ordenar fechas
				usort($todasLasFechas, function($a, $b) {
					return strtotime(str_replace('/', '-', $a)) - strtotime(str_replace('/', '-', $b));
				});
				
				// Preparar datasets para el gráfico
				$datasetsPorSucursal = [];
				foreach($ventasPorSucursalFecha as $sucursal => $ventas) {
					$data = [];
					foreach($todasLasFechas as $fecha) {
						$data[] = isset($ventas[$fecha]) ? $ventas[$fecha] : 0;
					}
					$datasetsPorSucursal[] = [
						'label' => $sucursal,
						'data' => $data
					];
				}
			} else {
				// Si no hay datos, crear array vacío
				$datasetsPorSucursal = [];
				$todasLasFechas = [];
			}
			
			$chartData = [
				'labels' => $labels,
				'data' => $data,
				'total' => $sum,
				'registros' => $totalRegistros,
				'fechasLabels' => $fechasLabels,
				'fechasData' => $fechasData,
				'fechasPorSucursal' => [
					'labels' => $todasLasFechas,
					'datasets' => $datasetsPorSucursal
				]
			];
			?>
			
			<!-- Cards de estadísticas -->
			<div class="stats-cards">
				<div class="stat-card success">
					<div class="stat-value">$<?= number_format($sum, 0, '', '.') ?></div>
					<div class="stat-label"><i class="fas fa-dollar-sign"></i> Total Ventas</div>
				</div>
				<div class="stat-card info">
					<div class="stat-value"><?= $totalRegistros ?></div>
					<div class="stat-label"><i class="fas fa-store"></i> Sucursales</div>
				</div>
				<div class="stat-card warning">
					<div class="stat-value">$<?= $totalRegistros > 0 ? number_format($sum / $totalRegistros, 0, '', '.') : '0' ?></div>
					<div class="stat-label"><i class="fas fa-chart-bar"></i> Promedio</div>
				</div>
			</div>

			<!-- Sección de gráficos -->
			<div class="charts-section">
				<div class="toggle-charts">
					<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#chartsCollapse" aria-expanded="false" aria-controls="chartsCollapse">
						<i class="fas fa-chart-line"></i> Mostrar/Ocultar Gráficos
					</button>
				</div>
				<div class="collapse" id="chartsCollapse">
					<div class="row">
						<div class="col-md-12">
							<div class="chart-card">
								<div class="chart-title">
									<i class="fas fa-chart-line"></i> Ventas por Día por Sucursal
								</div>
								<div class="chart-container">
									<canvas id="lineChartSucursales"></canvas>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="chart-card">
								<div class="chart-title">
									<i class="fas fa-chart-bar"></i> Ventas por Sucursal
								</div>
								<div class="chart-container">
									<canvas id="barChart"></canvas>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="chart-card">
								<div class="chart-title">
									<i class="fas fa-chart-pie"></i> Distribución de Ventas
								</div>
								<div class="chart-container">
									<canvas id="pieChart"></canvas>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Tabla de datos -->
			<div class="table-container">
				<table id="ventasTable" class="table table-striped table-hover">
					<thead>
						<tr>
							<th>NUM</th>
							<th>SUCURSAL</th>
							<th class="importe-column">IMPORTE</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach($resultados as $v): ?>
							<tr>
								<td><?= htmlspecialchars($v['NUM']) ?></td>
								<td><?= htmlspecialchars($v['SUCURSAL']) ?></td>
								<td class="importe-column">$<?= number_format($v['IMPORTE'], 0, '', '.') ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<?php if($totalRegistros > 0) { ?>
					<tfoot>
						<tr class="table-info">
							<td colspan="2" class="text-end fw-bold">Total de registros:</td>
							<td class="text-end fw-bold"><?= $totalRegistros ?></td>
						</tr>
					</tfoot>
					<?php } ?>
				</table>
			</div>
			
			<script>
				const chartData = <?= json_encode($chartData) ?>;
			</script>
			<?php
		}
	}
	else{
		// Obtener ventas para todas las sucursales
		$resultados = $estadistica->traerVentasTodasSucursales($desde, $hasta);
		// Obtener ventas por fecha para el gráfico temporal
		$ventasPorFecha = $estadistica->traerVentasPorFecha('TODOS', $desde, $hasta);
		// Obtener ventas por fecha y sucursal para el gráfico comparativo
		$ventasPorFechaYSucursal = $estadistica->traerVentasPorFechaYSucursal('TODOS', $desde, $hasta);

		if (empty($resultados)) {
			echo '<div class="alert alert-info">
				<i class="fas fa-info-circle"></i> No se encontraron registros para los filtros seleccionados.
			</div>';
		} else {
			// Preparar datos para gráficos
			$labels = [];
			$data = [];
			$sum = 0;
			$totalRegistros = 0;
			
			foreach($resultados as $v): 
				$labels[] = $v['SUCURSAL'];
				$data[] = $v['IMPORTE'];
				$sum += $v['IMPORTE'];
				$totalRegistros++;
			endforeach;
			
			// Preparar datos para gráfico temporal
			$fechasLabels = [];
			$fechasData = [];
			foreach($ventasPorFecha as $vf) {
				$fechasLabels[] = formatearFechaArgentina($vf['FECHA']);
				$fechasData[] = $vf['IMPORTE'];
			}
			
			// Preparar datos para gráfico por sucursal y fecha
			$ventasPorSucursalFecha = [];
			$todasLasFechas = [];
			
			// Verificar que hay datos
			if (!empty($ventasPorFechaYSucursal)) {
				foreach($ventasPorFechaYSucursal as $vfs) {
					$fecha = formatearFechaArgentina($vfs['FECHA']);
					$sucursal = $vfs['SUCURSAL'];
					
					if (!in_array($fecha, $todasLasFechas)) {
						$todasLasFechas[] = $fecha;
					}
					
					if (!isset($ventasPorSucursalFecha[$sucursal])) {
						$ventasPorSucursalFecha[$sucursal] = [];
					}
					$ventasPorSucursalFecha[$sucursal][$fecha] = $vfs['IMPORTE'];
				}
				
				// Ordenar fechas
				usort($todasLasFechas, function($a, $b) {
					return strtotime(str_replace('/', '-', $a)) - strtotime(str_replace('/', '-', $b));
				});
				
				// Preparar datasets para el gráfico
				$datasetsPorSucursal = [];
				foreach($ventasPorSucursalFecha as $sucursal => $ventas) {
					$data = [];
					foreach($todasLasFechas as $fecha) {
						$data[] = isset($ventas[$fecha]) ? $ventas[$fecha] : 0;
					}
					$datasetsPorSucursal[] = [
						'label' => $sucursal,
						'data' => $data
					];
				}
			} else {
				// Si no hay datos, crear array vacío
				$datasetsPorSucursal = [];
				$todasLasFechas = [];
			}
			
			$chartData = [
				'labels' => $labels,
				'data' => $data,
				'total' => $sum,
				'registros' => $totalRegistros,
				'fechasLabels' => $fechasLabels,
				'fechasData' => $fechasData,
				'sucursalSeleccionada' => 'Todas las Sucursales',
				'fechasPorSucursal' => [
					'labels' => $todasLasFechas,
					'datasets' => $datasetsPorSucursal
				]
			];
			?>
			
			<!-- Cards de estadísticas -->
			<div class="stats-cards">
				<div class="stat-card success">
					<div class="stat-value">$<?= number_format($sum, 0, '', '.') ?></div>
					<div class="stat-label"><i class="fas fa-dollar-sign"></i> Total Ventas</div>
				</div>
				<div class="stat-card info">
					<div class="stat-value"><?= $totalRegistros ?></div>
					<div class="stat-label"><i class="fas fa-store"></i> Sucursales</div>
				</div>
				<div class="stat-card warning">
					<div class="stat-value">$<?= $totalRegistros > 0 ? number_format($sum / $totalRegistros, 0, '', '.') : '0' ?></div>
					<div class="stat-label"><i class="fas fa-chart-bar"></i> Promedio</div>
				</div>
			</div>

			<!-- Sección de gráficos -->
			<div class="charts-section">
				<div class="toggle-charts">
					<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#chartsCollapse" aria-expanded="false" aria-controls="chartsCollapse">
						<i class="fas fa-chart-line"></i> Mostrar/Ocultar Gráficos
					</button>
				</div>
				<div class="collapse" id="chartsCollapse">
					<div class="row">
						<div class="col-md-12">
							<div class="chart-card">
								<div class="chart-title">
									<i class="fas fa-chart-line"></i> Ventas por Día por Sucursal
								</div>
								<div class="chart-container">
									<canvas id="lineChartSucursales"></canvas>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="chart-card">
								<div class="chart-title">
									<i class="fas fa-chart-bar"></i> Ventas por Sucursal
								</div>
								<div class="chart-container">
									<canvas id="barChart"></canvas>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="chart-card">
								<div class="chart-title">
									<i class="fas fa-chart-pie"></i> Distribución de Ventas
								</div>
								<div class="chart-container">
									<canvas id="pieChart"></canvas>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Tabla de datos -->
			<div class="table-container">
				<table id="ventasTable" class="table table-striped table-hover">
					<thead>
						<tr>
							<th>NUM</th>
							<th>SUCURSAL</th>
							<th class="importe-column">IMPORTE</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach($resultados as $v): ?>
							<tr>
								<td><?= htmlspecialchars($v['NUM']) ?></td>
								<td><?= htmlspecialchars($v['SUCURSAL']) ?></td>
								<td class="importe-column">$<?= number_format($v['IMPORTE'], 0, '', '.') ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<?php if($totalRegistros > 0) { ?>
						<tr class="table-info">
							<td colspan="2" class="text-end fw-bold">Total de registros:</td>
							<td class="text-end fw-bold"><?= $totalRegistros ?></td>
						</tr>
						<?php } ?>
						<tr class="total-row">
							<td colspan="2" class="text-end fw-bold">
								<h5 class="mb-0">TOTAL GENERAL:</h5>
							</td>
							<td class="text-end fw-bold">
								<h5 class="mb-0">$<?= number_format($sum, 0, '', '.') ?></h5>
							</td>
						</tr>
					</tfoot>
				</table>
			</div>
			
			<script>
				const chartData = <?= json_encode($chartData) ?>;
			</script>
			<?php
		}
	}
}

?>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
  
        
        // Inicializar gráficos si hay datos
        if (typeof chartData !== 'undefined' && chartData.labels.length > 0) {
            console.log("aca2");
            // Colores para los gráficos
            const colors = [
                '#3B82F6', '#10B981', '#F59E0B', '#EF4444', 
                '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'
            ];
            
            const backgroundColors = colors.slice(0, chartData.labels.length);
            const borderColors = backgroundColors.map(c => c);

            // Gráfico de Línea Temporal (por fecha)
            const lineCtx = document.getElementById('lineChart');
            if (lineCtx && chartData.fechasLabels && chartData.fechasLabels.length > 0) {
                new Chart(lineCtx, {
                    type: 'line',
                    data: {
                        labels: chartData.fechasLabels,
                        datasets: [{
                            label: chartData.sucursalSeleccionada ? 'Ventas Diarias - ' + chartData.sucursalSeleccionada + ' ($)' : 'Ventas Diarias ($)',
                            data: chartData.fechasData,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#3B82F6',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Ventas: $' + context.parsed.y.toLocaleString('es-AR');
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString('es-AR');
                                    },
                                    maxTicksLimit: 10
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            }
                        }
                    }
                });
            }
            // Gráfico de Líneas Múltiples - Ventas por Día por Sucursal
            const lineSucursalesCtx = document.getElementById('lineChartSucursales');
            console.log("aca55", lineSucursalesCtx);
            if (lineSucursalesCtx) {
                console.log("aca43");
                // Verificar si hay datos
                if (!chartData.fechasPorSucursal || !chartData.fechasPorSucursal.datasets || chartData.fechasPorSucursal.datasets.length === 0) {
                    console.warn('No hay datos de fechasPorSucursal disponibles');
                    // Ocultar el contenedor si no hay datos
                    lineSucursalesCtx.closest('.chart-card').style.display = 'none';
                } else {
                    console.log('Inicializando gráfico de sucursales con', chartData.fechasPorSucursal.datasets.length, 'sucursales');
                    console.log('Labels:', chartData.fechasPorSucursal.labels);
                    console.log('Datasets:', chartData.fechasPorSucursal.datasets);
                    const sucursalColors = [
                        '#3B82F6', '#10B981', '#F59E0B', '#EF4444', 
                        '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'
                    ];
                    
                    const datasets = chartData.fechasPorSucursal.datasets.map((dataset, index) => {
                        console.log(`Dataset ${index}: ${dataset.label} con ${dataset.data.length} puntos`);
                        return {
                            label: dataset.label,
                            data: dataset.data,
                            borderColor: sucursalColors[index % sucursalColors.length],
                            backgroundColor: sucursalColors[index % sucursalColors.length] + '20',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: sucursalColors[index % sucursalColors.length],
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        };
                    });

                new Chart(lineSucursalesCtx, {
                    type: 'line',
                    data: {
                        labels: chartData.fechasPorSucursal.labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': $' + context.parsed.y.toLocaleString('es-AR');
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString('es-AR');
                                    },
                                    maxTicksLimit: 10
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            }
                        }
                    }
                });
                }
            }

            // Gráfico de Barras
            const barCtx = document.getElementById('barChart');
            if (barCtx) {
                new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            label: 'Ventas ($)',
                            data: chartData.data,
                            backgroundColor: backgroundColors,
                            borderColor: borderColors,
                            borderWidth: 2,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Ventas: $' + context.parsed.y.toLocaleString('es-AR');
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString('es-AR');
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // Gráfico de Pastel
            const pieCtx = document.getElementById('pieChart');
            if (pieCtx) {
                new Chart(pieCtx, {
                    type: 'pie',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            data: chartData.data,
                            backgroundColor: backgroundColors,
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = chartData.total;
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return label + ': $' + value.toLocaleString('es-AR') + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    </script>
</body>
</html>
