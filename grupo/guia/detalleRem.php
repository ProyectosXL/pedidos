<?php 
session_start(); 
if(!isset($_SESSION['username'])){
	header("Location:../../login.php");
}else{
	
$permiso = $_SESSION['permisos'];

// Obtener parámetros
$suc = isset($_GET['suc']) ? $_GET['suc'] : '';
$remito = isset($_GET['remito']) ? $_GET['remito'] : '';
$desde = isset($_GET['desde']) ? $_GET['desde'] : '';
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : '';

// Incluir la clase de guías
require_once __DIR__.'/class/Guia.php';
$guia = new Guia();

// Obtener detalle del remito
$detalles = [];
if ($remito && $suc) {
	$detalles = $guia->traerDetalleRemito($remito, $suc);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Remito</title>
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
        .container-fluid.content-wrapper {
            margin-top: 120px;
            padding: 0 15px;
        }
        .table-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: calc(100vh - 180px);
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        #detalleTable {
            font-size: 0.9rem;
            width: 100%;
            margin-bottom: 0;
        }
        #detalleTable th, #detalleTable td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        #detalleTable thead {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: #343a40;
            color: white;
        }
        #detalleTable thead th {
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
        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        @media (max-width: 768px) {
            .container-fluid.content-wrapper {
                margin-top: 150px;
            }
            #detalleTable {
                font-size: 0.8rem;
            }
        }
        @media print {
            .fixed-header {
                position: relative;
            }
            .container-fluid.content-wrapper {
                margin-top: 0;
            }
            .action-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>	

    <div class="fixed-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="page-title mb-0">
                    <i class="fas fa-file-invoice"></i> Detalle de Remito: <?= htmlspecialchars($remito) ?>
                </h1>
                <div class="action-buttons">
                    <?php 
                    $backUrl = "index.php";
                    $params = [];
                    if ($suc) {
                        $params[] = "sucursal=" . urlencode($suc);
                    }
                    if ($desde) {
                        $params[] = "desde=" . urlencode($desde);
                    }
                    if ($hasta) {
                        $params[] = "hasta=" . urlencode($hasta);
                    }
                    if (!empty($params)) {
                        $backUrl .= "?" . implode("&", $params);
                    }
                    ?>
                    <a href="<?= $backUrl ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                    <button onclick="window.print()" class="btn btn-primary btn-sm">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid content-wrapper">

<?php
if (empty($detalles)) {
	echo '<div class="alert alert-warning">No se encontraron detalles para el remito seleccionado.</div>';
} else {
	$totalCantidad = 0;
	foreach($detalles as $detalle) {
		$totalCantidad += (float)$detalle['CANT'];
	}
	?>
	<div class="table-container">
		<table id="detalleTable" class="table table-striped table-hover">
			<thead>
				<tr>
					<th>FECHA</th>
					<th>REMITO</th>
					<th>ARTÍCULO</th>
					<th>DESCRIPCIÓN</th>
					<th class="text-end">CANTIDAD</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach($detalles as $v){
				?>
					<tr>
						<td><?= htmlspecialchars($v['FECHA']) ?></td>
						<td><?= htmlspecialchars($v['REMITO']) ?></td>
						<td><?= htmlspecialchars($v['ARTICULO']) ?></td>
						<td><?= htmlspecialchars($v['DESCRIPCION']) ?></td>
						<td class="text-end"><?= number_format((float)$v['CANT'], 0, ',', '.') ?></td>
					</tr>
				<?php
				}
				?>
			</tbody>
			<tfoot>
				<tr class="table-info">
					<td colspan="4" class="text-end fw-bold">Total de artículos:</td>
					<td class="text-end fw-bold"><?= count($detalles) ?></td>
				</tr>
				<tr class="table-info">
					<td colspan="4" class="text-end fw-bold">Total cantidad:</td>
					<td class="text-end fw-bold"><?= number_format($totalCantidad, 0, ',', '.') ?></td>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
}
?>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
</body>
</html>
<?php } ?>
