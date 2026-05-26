<?php
session_start();
if (!isset($_SESSION['username'])) {
	header("Location:login.php");
} else {
	
	$vendedor = $_SESSION['vendedor'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Selección de Sucursales - Córdoba</title>
	<link rel="shortcut icon" href="../css/icono.jpg" />
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
	<link rel="stylesheet" href="pedidos/css/preloader.css">
	<?php include '../assets/css/header.php'; ?>
	<style>
		/* Sobrescribir estilos del preloader y header.php que bloquean el scroll */
		/* IMPORTANTE: Estos estilos deben estar después del preloader.css y header.php */
		* {
			box-sizing: border-box;
		}
		body {
			background-color: #f5f7fa !important;
			padding-top: 0 !important;
			overflow-y: auto !important;
			overflow-x: hidden !important;
			display: block !important;
			min-height: 100vh !important;
			margin: 0 !important;
			justify-content: normal !important;
			align-items: normal !important;
		}
		html {
			overflow-y: auto !important;
			overflow-x: hidden !important;
			height: auto !important;
			margin: 0 !important;
			padding: 0 !important;
		}
		/* Eliminar espacios en blanco innecesarios */
		body {
			margin: 0 !important;
			padding: 0 !important;
		}
		body > *:first-child {
			margin-top: 0 !important;
			padding-top: 0 !important;
		}
		/* Asegurar que no haya espacios antes del primer elemento */
		body::before {
			content: none !important;
			display: none !important;
		}
		/* Corregir el preloader para que no bloquee el scroll */
		.container.preloader-container {
			display: none !important;
			position: fixed !important;
			top: 0 !important;
			left: 0 !important;
			right: 0 !important;
			bottom: 0 !important;
			width: 100% !important;
			height: 100vh !important;
			background-color: rgba(255, 255, 255, 0.9) !important;
			z-index: 9999 !important;
		}
		.container.preloader-container.show {
			display: flex !important;
		}
		/* Asegurar que el contenido principal sea scrolleable */
		.container:not(.preloader-container) {
			position: relative !important;
			display: block !important;
		}
		.page-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 30px 0;
			margin: 0 0 30px 0 !important;
			box-shadow: 0 4px 6px rgba(0,0,0,.1);
			width: 100%;
			position: relative;
		}
		/* Eliminar cualquier espacio antes del header */
		body > .page-header:first-child {
			margin-top: 0 !important;
		}
		.page-header h1 {
			margin: 0;
			font-size: 2rem;
			font-weight: 600;
		}
		.page-header p {
			margin: 10px 0 0 0;
			opacity: 0.9;
			font-size: 1rem;
		}
		.card {
			border: none;
			border-radius: 10px;
			box-shadow: 0 2px 10px rgba(0,0,0,.08);
			margin-bottom: 30px;
		}
		.card-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			border-radius: 10px 10px 0 0 !important;
			padding: 15px 20px;
			font-weight: 600;
		}
		.table-container {
			overflow-x: auto;
			border-radius: 8px;
		}
		.table {
			margin-bottom: 0;
		}
		.table thead th {
			background-color: #f8f9fa;
			border-bottom: 2px solid #dee2e6;
			font-weight: 600;
			color: #495057;
			text-transform: uppercase;
			font-size: 0.85rem;
			padding: 15px;
		}
		.table tbody td {
			padding: 15px;
			vertical-align: middle;
		}
		.table tbody tr {
			transition: all 0.2s ease;
		}
		.table tbody tr:hover {
			background-color: #f8f9fa;
			transform: scale(1.01);
			box-shadow: 0 2px 4px rgba(0,0,0,.05);
		}
		.badge-sucursal {
			display: inline-block;
			padding: 8px 15px;
			border-radius: 20px;
			font-weight: 600;
			font-size: 0.9rem;
			background-color: #e9ecef;
			color: #495057;
		}
		.form-select {
			border-radius: 6px;
			border: 2px solid #dee2e6;
			transition: all 0.3s ease;
			font-weight: 500;
		}
		.form-select:focus {
			border-color: #667eea;
			box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
		}
		.form-select option[value="si"] {
			background-color: #d4edda;
			color: #155724;
		}
		.form-select option[value="no"] {
			background-color: #f8d7da;
			color: #721c24;
		}
		.btn-submit {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			border: none;
			padding: 12px 40px;
			font-size: 1.1rem;
			font-weight: 600;
			border-radius: 25px;
			box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
			transition: all 0.3s ease;
		}
		.btn-submit:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
		}
		.btn-submit:active {
			transform: translateY(0);
		}
		.info-box {
			background-color: #e7f3ff;
			border-left: 4px solid #2196F3;
			padding: 15px 20px;
			border-radius: 6px;
			margin-bottom: 20px;
		}
		.info-box i {
			color: #2196F3;
			margin-right: 10px;
		}
		.stats-card {
			text-align: center;
			padding: 20px;
			background: white;
			border-radius: 8px;
			box-shadow: 0 2px 8px rgba(0,0,0,.08);
		}
		.stats-card .number {
			font-size: 2rem;
			font-weight: 700;
			color: #667eea;
		}
		.stats-card .label {
			color: #6c757d;
			font-size: 0.9rem;
			margin-top: 5px;
		}
	</style>
</head>

<body>
	<div class="page-header">
		<div class="container" style="color: black;">
			<h1><i class="fas fa-store"></i> Selección de Sucursales</h1>
			<p>Seleccione las sucursales a las que desea conectarse para cargar pedidos</p>
		</div>
	</div>

	<div class="container">

		<?php
		// Usar la clase Sucursal para obtener las sucursales
		require_once __DIR__ . '/../class/sucursal.php';
		
		$sucursal = new Sucursal();
		

		if (isset($_SESSION['ID_FRANQUICIA'])) {
			$sucursales = $sucursal->listarSucursalesPorFranquicia($_SESSION['ID_FRANQUICIA'], 'central');
		} else {
			$sucursales = $sucursal->listarSucursalesCordoba('central');
		}
		
		$total = count($sucursales);
		
		if ($total === 0) {
			echo '<div class="alert alert-warning" role="alert">
				<i class="fas fa-exclamation-triangle"></i> 
				<strong>Advertencia:</strong> No se pudieron cargar las sucursales. Por favor, intente nuevamente.
			</div>';
		}
		?>

		<!-- Estadísticas -->
		<div class="row mb-4">
			<div class="col-md-4">
				<div class="stats-card">
					<div class="number"><?= $total ?></div>
					<div class="label"><i class="fas fa-store"></i> Sucursales Disponibles</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="stats-card">
					<div class="number" id="selectedCount">0</div>
					<div class="label"><i class="fas fa-check-circle"></i> Seleccionadas</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="stats-card">
					<div class="number" id="notSelectedCount"><?= $total ?></div>
					<div class="label"><i class="fas fa-times-circle"></i> No Seleccionadas</div>
				</div>
			</div>
		</div>

		<!-- Información -->
		<div class="info-box">
			<i class="fas fa-info-circle"></i>
			<strong>Instrucciones:</strong> Seleccione "SI" para las sucursales a las que desea conectarse y obtener información de stock y ventas. 
			Las sucursales marcadas como "NO" no se incluirán en la carga de pedidos.
		</div>

		<!-- Formulario -->
		<div class="card">
			<div class="card-header">
				<i class="fas fa-list"></i> Lista de Sucursales
			</div>
			<div class="card-body p-0">
				<form id="formulario" action="cargaPedidoCordoba.php" method="post">
					<div class="table-container">
						<table class="table table-hover mb-0" id="id_tabla">
							<thead>
								<tr>
									<th style="width: 10%"><i class="fas fa-hashtag"></i> Número</th>
									<th style="width: 15%"><i class="fas fa-code"></i> Código</th>
									<th style="width: 35%"><i class="fas fa-building"></i> Nombre</th>
									<th style="width: 20%"><i class="fas fa-server"></i> DSN</th>
									<th style="width: 20%"><i class="fas fa-toggle-on"></i> Selección</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($sucursales as $v): ?>
									<tr>
										<td>
											<span class="badge-sucursal">
												<i class="fas fa-store"></i> <?= htmlspecialchars($v['N_IMPUESTO']) ?>
											</span>
										</td>
										<td>
											<strong><?= htmlspecialchars($v['COD_CLIENT']) ?></strong>
										</td>
										<td>
											<?= htmlspecialchars($v['NOM_COM']) ?>
										</td>
										<td>
											<small class="text-muted"><?= htmlspecialchars($v['DSN']) ?></small>
										</td>
										<td>
											<input type="hidden" name="suc[]" value="<?= htmlspecialchars($v['N_IMPUESTO']) ?>">
											<input type="hidden" name="dsn[]" value="<?= htmlspecialchars($v['DSN']) ?>">
											<select name="selec[]" class="form-select form-select-sm select-sucursal" data-sucursal="<?= htmlspecialchars($v['NOM_COM']) ?>">
												<option value="si" selected>✓ SI</option>
												<option value="no">✗ NO</option>
											</select>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<div class="card-footer bg-white text-end">
						<a href="index.php" class="btn btn-secondary me-2">
							<i class="fas fa-arrow-left"></i> Volver
						</a>
						<button type="submit" class="btn btn-submit text-white">
							<i class="fas fa-arrow-right"></i> Continuar
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- Preloader (oculto por defecto) -->
	<div class="container preloader-container" id="preloaderContainer" style="display: none !important;">
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

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		// Esperar a que el DOM esté completamente cargado
		document.addEventListener('DOMContentLoaded', function() {
			// Actualizar contadores cuando cambia la selección
			function updateCounters() {
				const selects = document.querySelectorAll('.select-sucursal');
				let selected = 0;
				let notSelected = 0;
				
				selects.forEach(select => {
					if (select.value === 'si') {
						selected++;
					} else {
						notSelected++;
					}
				});
				
				const selectedCountEl = document.getElementById('selectedCount');
				const notSelectedCountEl = document.getElementById('notSelectedCount');
				
				if (selectedCountEl) {
					selectedCountEl.textContent = selected;
				}
				if (notSelectedCountEl) {
					notSelectedCountEl.textContent = notSelected;
				}
			}

			// Agregar event listeners a los selects
			document.querySelectorAll('.select-sucursal').forEach(select => {
				select.addEventListener('change', function() {
					updateCounters();
					
					// Cambiar estilo visual según selección
					const row = this.closest('tr');
					if (this.value === 'si') {
						row.style.backgroundColor = '';
						this.style.backgroundColor = '#d4edda';
						this.style.color = '#155724';
					} else {
						row.style.backgroundColor = '#fff3cd';
						this.style.backgroundColor = '#f8d7da';
						this.style.color = '#721c24';
					}
				});
			});

			// Inicializar contadores al cargar la página
			updateCounters();

			// Manejar envío del formulario
			const formulario = document.getElementById('formulario');
			if (formulario) {
				formulario.addEventListener("submit", function (e) {
					// Contar selects con valor 'si' correctamente
					const selects = document.querySelectorAll('.select-sucursal');
					let selectedCount = 0;
					
					selects.forEach(select => {
						if (select.value === 'si') {
							selectedCount++;
						}
					});
					
					if (selectedCount === 0) {
						e.preventDefault();
						alert('Debe seleccionar al menos una sucursal para continuar.');
						return false;
					}
					
					// Mostrar preloader
					const preloaderContainer = document.getElementById('preloaderContainer');
					if (preloaderContainer) {
						preloaderContainer.style.display = 'flex';
						preloaderContainer.classList.add('show');
					}
				});
			}
		});

		function traerCantidadPedidos() {
			conexion1 = new XMLHttpRequest();
			conexion1.onreadystatechange = () => {
				if (conexion1.readyState == 4 && conexion1.status == 200) {
					estado = JSON.parse(conexion1.responseText);
					console.log(estado);
					localStorage.setItem("infoCordoba", JSON.stringify(estado));
				}
			};
			conexion1.open("GET", "pedidos/limitePedidosCordoba.php", true);
			conexion1.send();
		}
	</script>
</body>
</html>

<?php
}
?>
