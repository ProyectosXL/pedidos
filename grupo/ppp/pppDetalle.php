<?php 
session_start(); 

if(!isset($_SESSION['username'])){
	header("Location:../../login.php");
}else{
	
	// Incluir la clase PPP
	require_once __DIR__.'/class/PPP.php';
	$ppp = new PPP();
	
	$cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
	
	if (empty($cliente)) {
		die("Error: No se especificó un cliente");
	}
	
	// Obtener datos usando la clase
	$resultados = $ppp->traerDetallePPP($cliente);
	
	// Obtener información del cliente para el título
	require_once __DIR__.'/../../class/conexion.php';
	$conn = new Conexion();
	$db = isset($_SESSION['usuarioUy']) && $_SESSION['usuarioUy'] == 1 ? 'uy' : 'central';
	$cid = $conn->conectar($db);
	
	$nombreCliente = $cliente;
	if ($cid !== false) {
		$clienteEscapado = str_replace("'", "''", $cliente);
		$sqlCliente = "SELECT RAZON_SOCI FROM GVA14 WHERE COD_CLIENT = '$clienteEscapado'";
		$stmtCliente = sqlsrv_query($cid, $sqlCliente);
		if ($stmtCliente !== false && $rowCliente = sqlsrv_fetch_array($stmtCliente, SQLSRV_FETCH_ASSOC)) {
			$nombreCliente = $rowCliente['RAZON_SOCI'];
		}
		if (isset($stmtCliente) && $stmtCliente !== false) {
			sqlsrv_free_stmt($stmtCliente);
		}
	}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle PPP - <?= htmlspecialchars($cliente) ?></title>
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
            margin-top: 150px;
            padding: 0 15px;
        }
        .table-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: calc(100vh - 170px);
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: #fff;
        }
        #pppDetalleTable {
            font-size: 0.9rem;
            width: 100%;
            margin-bottom: 0;
        }
        #pppDetalleTable th, #pppDetalleTable td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        #pppDetalleTable thead {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: #343a40;
            color: white;
        }
        #pppDetalleTable thead th {
            background-color: #343a40;
            color: white;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            text-align: center;
        }
        #pppDetalleTable tbody td {
            text-align: center;
        }
        #pppDetalleTable tbody td:first-child,
        #pppDetalleTable tbody td:nth-child(2),
        #pppDetalleTable tbody td:nth-child(3) {
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
        .link-comprobante {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        .link-comprobante:hover {
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
        @media (max-width: 768px) {
            .container-fluid.table-wrapper {
                margin-top: 180px;
            }
            #pppDetalleTable {
                font-size: 0.8rem;
            }
            #pppDetalleTable th, #pppDetalleTable td {
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
                        <i class="fas fa-file-invoice-dollar"></i> Detalle PPP - <?= htmlspecialchars($cliente) ?>
                    </h1>
                    <p class="text-muted mb-0 mt-2">
                        <small><?= htmlspecialchars($nombreCliente) ?></small>
                    </p>
                </div>
                <a href="ppp.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Volver al listado
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid table-wrapper">

<?php
	if (empty($resultados)) {
		echo '<div class="alert alert-info">
			<i class="fas fa-info-circle"></i> No se encontraron registros de detalle PPP para el cliente <strong>' . htmlspecialchars($cliente) . '</strong> en los últimos 365 días.
		</div>';
	} else {
		$totalRecibos = 0;
		$totalImputado = 0;
		$promedioPPP = 0;
		$sumaPPP = 0;
		$contadorPPP = 0;
		
		foreach($resultados as $v) {
			$totalRecibos += $v['IMPORTE_RECIBO'];
			$totalImputado += $v['IMPORTE_IMPUTADO'];
			if ($v['PPP'] > 0) {
				$sumaPPP += $v['PPP'];
				$contadorPPP++;
			}
		}
		
		if ($contadorPPP > 0) {
			$promedioPPP = round($sumaPPP / $contadorPPP, 0);
		}
		?>
		<div class="alert alert-info mb-3" role="alert">
			<div class="row">
				<div class="col-md-3">
					<strong>Total Recibos:</strong> <?= number_format($totalRecibos, 0, ',', '.') ?>
				</div>
				<div class="col-md-3">
					<strong>Total Imputado:</strong> <?= number_format($totalImputado, 0, ',', '.') ?>
				</div>
				<div class="col-md-3">
					<strong>Promedio PPP:</strong> 
					<span class="badge <?= $promedioPPP > 30 ? 'badge-danger' : ($promedioPPP > 15 ? 'badge-warning' : 'badge-success') ?>">
						<?= $promedioPPP ?> días
					</span>
				</div>
				<div class="col-md-3">
					<strong>Total Registros:</strong> <?= count($resultados) ?>
				</div>
			</div>
		</div>
		
		<div class="table-container">
			<table id="pppDetalleTable" class="table table-striped table-hover">
				<thead>
					<tr>
						<th>
							FECHA
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Fecha del recibo"></i>
						</th>
						<th>
							TIPO COMP
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Tipo de comprobante"></i>
						</th>
						<th>
							COMPROBANTE
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Número de comprobante (clic para ver detalle)"></i>
						</th>
						<th>
							IMPORTE RECIBO
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Importe total del recibo"></i>
						</th>
						<th>
							IMPORTE IMPUTADO
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Importe que fue aplicado/imputado"></i>
						</th>
						<th>
							PPP
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Promedio de días de pago para este recibo"></i>
						</th>
						<th>
							DÍAS
							<i class="fas fa-question-circle tooltip-icon" 
							   data-bs-toggle="tooltip" 
							   data-bs-placement="top"
							   title="Días transcurridos hasta el pago"></i>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach($resultados as $v){
						// Determinar badge según PPP
						$badgePPP = '';
						if ($v['PPP'] <= 30) {
							$badgePPP = 'badge-success';
						} elseif ($v['PPP'] <= 60) {
							$badgePPP = 'badge-warning';
						} else {
							$badgePPP = 'badge-danger';
						}
					?>
						<tr>
							<td><?= htmlspecialchars($v['FECHA']) ?></td>
							<td><?= htmlspecialchars($v['T_COMP']) ?></td>
							<td>
								<a href="pppDetalleRec.php?recibo=<?= urlencode($v['N_COMP']) ?>" 
								   class="link-comprobante">
									<?= htmlspecialchars($v['N_COMP']) ?>
								</a>
							</td>
							<td><?= number_format($v['IMPORTE_RECIBO'], 0, ',', '.') ?></td>
							<td><?= number_format($v['IMPORTE_IMPUTADO'], 0, ',', '.') ?></td>
							<td>
								<span class="badge <?= $badgePPP ?>">
									<?= number_format($v['PPP'], 0) ?> días
								</span>
							</td>
							<td><?= number_format($v['DIAS'], 0) ?></td>
						</tr>
					<?php
					}
					?>
				</tbody>
				<?php if(count($resultados) > 0) { ?>
				<tfoot>
					<tr class="table-info">
						<td colspan="3" class="text-end fw-bold">Totales:</td>
						<td class="text-center fw-bold"><?= number_format($totalRecibos, 0, ',', '.') ?></td>
						<td class="text-center fw-bold"><?= number_format($totalImputado, 0, ',', '.') ?></td>
						<td colspan="2"></td>
					</tr>
				</tfoot>
				<?php } ?>
			</table>
		</div>
		<?php
	}
?>

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
