<?php 
session_start(); 

if(!isset($_SESSION['username'])){
	header("Location:../../login.php");
}else{
	

	require_once __DIR__.'/class/PPP.php';
	$ppp = new PPP();
	

	$resultados = $ppp->traerListaPPP();
	

	$filtroPPP = isset($_GET['filtro_ppp']) ? $_GET['filtro_ppp'] : 'todos';
	$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
	
	// Aplicar filtros
	if (!empty($busqueda)) {
		$resultados = array_filter($resultados, function($v) use ($busqueda) {
			return stripos($v['COD_CLIENTE'], $busqueda) !== false || 
				   stripos($v['RAZON_SOCIAL'], $busqueda) !== false;
		});
	}
	
	if ($filtroPPP !== 'todos') {
		$resultados = array_filter($resultados, function($v) use ($filtroPPP) {
			switch($filtroPPP) {
				case 'bajo':
					return $v['PPP'] <= 30;
				case 'medio':
					return $v['PPP'] > 30 && $v['PPP'] <= 60;
				case 'alto':
					return $v['PPP'] > 60;
				default:
					return true;
			}
		});
	}
	
	// Reindexar array después de filtrar
	$resultados = array_values($resultados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPP - Plan de Pago Programado</title>
    <link rel="shortcut icon" href="../../css/icono.jpg" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 0;
            overflow-x: hidden;
            overflow-y: auto;
            background-color: #f8f9fa;
        }
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background-color: #fff;
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
            background-color: #fff;
        }
        #pppTable {
            font-size: 0.9rem;
            width: 100%;
            margin-bottom: 0;
        }
        #pppTable th, #pppTable td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        #pppTable thead {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: #343a40;
            color: white;
        }
        #pppTable thead th {
            background-color: #343a40;
            color: white;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            text-align: center;
        }
        #pppTable tbody td {
            text-align: center;
        }
        #pppTable tbody td:first-child,
        #pppTable tbody td:nth-child(2) {
            text-align: left;
        }
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
        }
        .page-title {
            font-size: 1.5rem;
            margin-bottom: 0;
            font-weight: 600;
            color: #343a40;
        }
        .badge-danger {
            background-color: #dc3545;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        .badge-success {
            background-color: #28a745;
        }
        .text-danger {
            color: #dc3545 !important;
            font-weight: 600;
        }
        .text-success {
            color: #28a745 !important;
            font-weight: 600;
        }
        .link-cliente {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        .link-cliente:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .tooltip-icon {
            cursor: help;
            margin-left: 5px;
            opacity: 0.7;
        }
        .tooltip-icon:hover {
            opacity: 1;
        }
        .filter-section {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .info-alert {
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .container-fluid.table-wrapper {
                margin-top: 250px;
            }
            #pppTable {
                font-size: 0.8rem;
            }
            #pppTable th, #pppTable td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>	

    <div class="fixed-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-file-invoice-dollar"></i> PPP - Plan de Pago Programado
                    </h1>
                    <p class="text-muted mb-0 mt-2">
                        <small>Promedio de Plazo de Pago por Cliente</small>
                    </p>
                </div>
                <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAyuda">
                    <i class="fas fa-question-circle"></i> ¿Cómo leer este reporte?
                </button>
            </div>
            
            <div class="filter-section">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Buscar Cliente:</label>
                        <input type="text" name="busqueda" class="form-control form-control-sm" 
                               placeholder="Código o Razón Social..." value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Filtro por PPP:</label>
                        <select name="filtro_ppp" class="form-select form-select-sm">
                            <option value="todos" <?= $filtroPPP == 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="bajo" <?= $filtroPPP == 'bajo' ? 'selected' : '' ?>>Bajo (≤ 30 días)</option>
                            <option value="medio" <?= $filtroPPP == 'medio' ? 'selected' : '' ?>>Medio (31-60 días)</option>
                            <option value="alto" <?= $filtroPPP == 'alto' ? 'selected' : '' ?>>Alto (> 60 días)</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm me-2">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="ppp.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-redo"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container-fluid table-wrapper">

<?php
	// Información contextual sobre el reporte
	?>
	<div class="alert alert-info info-alert" role="alert">
		<h5><i class="fas fa-info-circle"></i> Acerca de este reporte</h5>
		<div class="row">
			<div class="col-md-6">
				<p class="mb-1"><strong>PPP (Promedio de Pago):</strong> Promedio de días que tarda el cliente en pagar sus facturas (últimos 365 días).</p>
				<p class="mb-1"><strong>Cupo de Crédito:</strong> Límite máximo de crédito otorgado al cliente.</p>
			</div>
			<div class="col-md-6">
				<p class="mb-1"><strong>Saldo CC:</strong> Saldo actual en cuenta corriente (deuda pendiente).</p>
				<p class="mb-1"><strong>Cheque:</strong> Importe total de cheques diferidos pendientes de cobro.</p>
				<p class="mb-0"><strong>Total Deuda:</strong> Suma de Saldo CC + Cheques pendientes.</p>
			</div>
		</div>
	</div>
	<?php

	if (empty($resultados)) {
		echo '<div class="alert alert-warning">
			<i class="fas fa-exclamation-triangle"></i> No se encontraron registros de PPP para los filtros seleccionados.
		</div>';
	} else {
		?>
		<div class="table-container">
			<table id="pppTable" class="table table-striped table-hover">
				<thead>
					<tr>
						<th>CÓDIGO CLIENTE</th>
						<th>RAZÓN SOCIAL</th>
						<th>
							PPP
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Promedio de días de pago - Últimos 365 días"></i>
						</th>
						<th>
							CUPO CRÉDITO
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Límite máximo de crédito otorgado al cliente"></i>
						</th>
						<th>
							SALDO CC
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Saldo actual en cuenta corriente (deuda pendiente)"></i>
						</th>
						<th>
							CHEQUES
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Importe total de cheques diferidos pendientes de cobro"></i>
						</th>
						<th>
							TOTAL DEUDA
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Suma de Saldo CC + Cheques pendientes"></i>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$totalRegistros = 0;
					$totalDeuda = 0;
					foreach($resultados as $v){
						$totalRegistros++;
						$totalDeuda += $v['TOTAL_DEUDA'];
						
						// Determinar clase de color según nivel de deuda
						$claseDeuda = '';
						if ($v['TOTAL_DEUDA'] > $v['CUPO_CRED']) {
							$claseDeuda = 'text-danger';
						} elseif ($v['TOTAL_DEUDA'] > ($v['CUPO_CRED'] * 0.8)) {
							$claseDeuda = 'text-warning';
						}
						
						// Indicador de riesgo crediticio según PPP
						$riesgoPPP = '';
						$riesgoTexto = '';
						if ($v['PPP'] <= 30) {
							$riesgoPPP = 'badge-success';
							$riesgoTexto = 'Bajo';
						} elseif ($v['PPP'] <= 60) {
							$riesgoPPP = 'badge-warning';
							$riesgoTexto = 'Medio';
						} else {
							$riesgoPPP = 'badge-danger';
							$riesgoTexto = 'Alto';
						}
					?>
						<tr>
							<td><strong><?= htmlspecialchars($v['COD_CLIENTE']) ?></strong></td>
							<td>
								<a href="pppDetalle.php?cliente=<?= urlencode($v['COD_CLIENTE']) ?>" 
								   class="link-cliente">
									<?= htmlspecialchars($v['RAZON_SOCIAL']) ?>
								</a>
							</td>
							<td>
								<span class="badge <?= $riesgoPPP ?>">
									<?= number_format($v['PPP'], 0) ?> días
								</span>
								<br>
								<small class="text-muted">Riesgo: <?= $riesgoTexto ?></small>
							</td>
							<td><?= number_format($v['CUPO_CRED'], 0, ',', '.') ?></td>
							<td><?= number_format($v['SALDO_CC'], 0, ',', '.') ?></td>
							<td><?= number_format($v['CHEQUE'], 0, ',', '.') ?></td>
							<td class="<?= $claseDeuda ?>">
								<strong><?= number_format($v['TOTAL_DEUDA'], 0, ',', '.') ?></strong>
								<?php if ($v['TOTAL_DEUDA'] > $v['CUPO_CRED']): ?>
									<br><small class="badge badge-danger">Excede cupo</small>
								<?php elseif ($v['TOTAL_DEUDA'] > ($v['CUPO_CRED'] * 0.8)): ?>
									<br><small class="badge badge-warning">Próximo al límite</small>
								<?php endif; ?>
							</td>
						</tr>
					<?php
					}
					?>
				</tbody>
				<?php if($totalRegistros > 0) { ?>
				<tfoot>
					<tr class="table-info">
						<td colspan="6" class="text-end fw-bold">Total de registros:</td>
						<td class="text-center fw-bold"><?= $totalRegistros ?></td>
					</tr>
					<tr class="table-secondary">
						<td colspan="6" class="text-end fw-bold">Total Deuda General:</td>
						<td class="text-center fw-bold text-danger"><?= number_format($totalDeuda, 0, ',', '.') ?></td>
					</tr>
				</tfoot>
				<?php } ?>
			</table>
		</div>
		<?php
	}
?>

    </div>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="modalAyuda" tabindex="-1" aria-labelledby="modalAyudaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAyudaLabel">
                        <i class="fas fa-question-circle"></i> ¿Cómo leer este reporte?
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6><strong>PPP (Promedio de Pago Programado)</strong></h6>
                    <p>Indica el promedio de días que tarda el cliente en pagar sus facturas, calculado sobre los últimos 365 días.</p>
                    <ul>
                        <li><span class="badge badge-success">Verde (≤ 30 días):</span> Cliente puntual, bajo riesgo crediticio</li>
                        <li><span class="badge badge-warning">Amarillo (31-60 días):</span> Cliente con retrasos moderados, riesgo medio</li>
                        <li><span class="badge badge-danger">Rojo (> 60 días):</span> Cliente con retrasos significativos, alto riesgo</li>
                    </ul>
                    
                    <h6 class="mt-4"><strong>Cupo de Crédito</strong></h6>
                    <p>Límite máximo de crédito otorgado al cliente. Es importante comparar este valor con el "Total Deuda" para evaluar el riesgo.</p>
                    
                    <h6 class="mt-4"><strong>Saldo CC (Cuenta Corriente)</strong></h6>
                    <p>Deuda actual pendiente del cliente en cuenta corriente. Incluye facturas vencidas y por vencer.</p>
                    
                    <h6 class="mt-4"><strong>Cheques</strong></h6>
                    <p>Importe total de cheques diferidos que aún no han sido cobrados. Estos cheques están pendientes y pueden afectar el flujo de caja.</p>
                    
                    <h6 class="mt-4"><strong>Total Deuda</strong></h6>
                    <p>Suma del Saldo CC + Cheques pendientes. Este valor debe compararse con el Cupo de Crédito:</p>
                    <ul>
                        <li><strong class="text-danger">Rojo:</strong> La deuda excede el cupo de crédito - Requiere atención inmediata</li>
                        <li><strong class="text-warning">Amarillo:</strong> La deuda está próxima al límite (80% o más) - Monitorear</li>
                        <li><strong class="text-success">Verde:</strong> La deuda está dentro de límites seguros</li>
                    </ul>
                    
                    <h6 class="mt-4"><strong>Recomendaciones</strong></h6>
                    <ul>
                        <li>Revisar periódicamente clientes con PPP > 60 días</li>
                        <li>Monitorear clientes que exceden su cupo de crédito</li>
                        <li>Considerar restringir crédito a clientes con riesgo alto persistente</li>
                        <li>Usar los filtros para identificar rápidamente situaciones de riesgo</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>
<?php } ?>
