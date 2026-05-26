<?php
// Obtener sucursales activas de la sesión (si existen)
$sucursalesActivas = isset($_SESSION['sucursales_activas']) ? $_SESSION['sucursales_activas'] : ['812', '813', '814', '815', '816', '876', '940'];

// Mapeo de número de sucursal a información
$sucursalesInfo = [
	'812' => ['nombre' => 'BAULERA', 'codClient' => 'FRBAUD', 'nombreCompleto' => 'BAULERA'],
	'813' => ['nombre' => 'VELEZ', 'codClient' => 'FRORCE', 'nombreCompleto' => 'VELEZ'],
	'814' => ['nombre' => 'DINO', 'codClient' => 'FRORIG', 'nombreCompleto' => 'DINO'],
	'815' => ['nombre' => 'NVO CENTRO', 'codClient' => 'FRORNC', 'nombreCompleto' => 'NVO CENTRO'],
	'816' => ['nombre' => 'SAN JUAN', 'codClient' => 'FRORSJ', 'nombreCompleto' => 'SAN JUAN'],
	'876' => ['nombre' => 'JOCKEY', 'codClient' => 'FRPASJ', 'nombreCompleto' => 'JOCKEY'],
	'940' => ['nombre' => 'RIVERA', 'codClient' => 'FRPRIN', 'nombreCompleto' => 'RIVERA']
];

// Filtrar solo las sucursales activas
$sucursalesActivasInfo = [];
foreach ($sucursalesActivas as $suc) {
	if (isset($sucursalesInfo[$suc])) {
		$sucursalesActivasInfo[$suc] = $sucursalesInfo[$suc];
	}
}

// Calcular rango de fechas para el período de análisis de ventas (últimos 30 días)
$fechaHasta = date('d/m/Y');
$fechaDesde = date('d/m/Y', strtotime('-30 days'));
?>
<tr style="font-size:smaller">
	<th style="width: 4%">FOTO</th>
	<th style="width: 8%">CODIGO</th>
	<th style="width: 1%"></th>	
	<th style="width: 12%">DESCRIPCION</th>
	<th style="width: 12%">RUBRO</th>				
	<th style="width: 1%"></th>	
	<th style="width: 8%" title="Stock disponible en Casa Central">STOCK<br>CASA CENTRAL</th>
	<?php foreach ($sucursalesActivasInfo as $suc => $info): ?>
		<th colspan="3" style="width: 8%"><?= str_replace(' ', '<br>', $info['nombre']) ?></th>
	<?php endforeach; ?>
	<th style="width: 4%"><input type="submit" id='btnEnviar' value="Enviar" class="btn btn-primary btn-sm"></th>
</tr>
<tr style="font-size:smaller; background-color: #e9ecef;">
	<th></th>
	<th></th>
	<th></th>
	<th></th>
	<th></th>
	<th></th>
	<th></th>
	<?php foreach ($sucursalesActivasInfo as $suc => $info): ?>
		<th style="width: 2%" title="Stock disponible en la sucursal <?= $info['nombreCompleto'] ?>">
			<i class="fas fa-boxes" style="font-size: 0.8rem;"></i><br>Stock
		</th>
		<th style="width: 2%" title="Unidades vendidas en <?= $info['nombreCompleto'] ?> del <?= $fechaDesde ?> al <?= $fechaHasta ?> (últimos 30 días)">
			<i class="fas fa-chart-line" style="font-size: 0.8rem;"></i><br>Vendido
		</th>
		<th style="width: 4%" title="Cantidad a pedir para <?= $info['nombreCompleto'] ?>">
			<i class="fas fa-shopping-cart" style="font-size: 0.8rem;"></i><br>Pedido
		</th>
	<?php endforeach; ?>
	<th></th>
</tr>