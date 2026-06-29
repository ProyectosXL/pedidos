<?php
require_once __DIR__ . '/../../class/GrupoSesion.php';

GrupoSesion::requiereLogin('../login.php');

if (empty($_SESSION['sucursalesGrupo']) || !is_array($_SESSION['sucursalesGrupo'])) {
	$_SESSION['carga_pedido_error'] = 'No hay sucursales configuradas para el grupo empresario.';
	header('Location: ../index.php');
	exit;
}

set_time_limit(300);
ini_set('max_execution_time', '300');

/**
 * Inserta filas en SOF_PEDIDOS_CARGA_LOPEZ en lotes de 200 (límite SQL Server ~2100 params).
 * @param resource $cidCentral
 * @param array<int, array{num_suc: int, cod_articu: string, cant_stock: float, cant_vend: float}> $filas
 */
function insertarLoteCargaLopez($cidCentral, array $filas) {
	if (empty($filas)) {
		return true;
	}

	$loteSize = 200;
	foreach (array_chunk($filas, $loteSize) as $lote) {
		$placeholders = [];
		$params = [];
		foreach ($lote as $fila) {
			$placeholders[] = '(?, ?, ?, ?)';
			$params[] = $fila['num_suc'];
			$params[] = $fila['cod_articu'];
			$params[] = $fila['cant_stock'];
			$params[] = $fila['cant_vend'];
		}

		$sql = 'INSERT INTO SOF_PEDIDOS_CARGA_LOPEZ (NUM_SUC, COD_ARTICU, CANT_STOCK, VENDIDO) VALUES '
			. implode(', ', $placeholders);

		if (sqlsrv_query($cidCentral, $sql, $params) === false) {
			return false;
		}
	}

	return true;
}

require_once __DIR__ . '/../../class/sucursal.php';
require_once __DIR__ . '/../../class/conexion.php';

$sucursalesGrupo = $_SESSION['sucursalesGrupo'];
$totalSucursales = count($sucursalesGrupo);

if (empty($_SESSION['pedido_grupo_cargado'])) {
	unset($_SESSION['sucursales_activas'], $_SESSION['sucursales_info']);
}

$sucursalObj = new Sucursal();
$infoSucursales = $sucursalObj->listarSucursalesPorNumeros($sucursalesGrupo, 'central');
$infoPorNumero = [];
foreach ($infoSucursales as $row) {
	$infoPorNumero[(int) $row['N_IMPUESTO']] = $row;
}

$conexionCentral = new Conexion();
$cidCentral = $conexionCentral->conectar('central');

if ($cidCentral === false) {
	$_SESSION['carga_pedido_error'] = 'No se pudo conectar con la base central.';
	header('Location: ../index.php');
	exit;
}

sqlsrv_configure('QueryTimeout', 90);
$configSucursales = $conexionCentral->obtenerConfiguracionesSucursales($sucursalesGrupo);

header('Content-Type: text/html; charset=UTF-8');
while (ob_get_level() > 0) {
	ob_end_flush();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Cargando sucursales...</title>
	<style>
		body { font-family: system-ui, sans-serif; background: #f5f7fa; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
		.box { background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08); max-width: 420px; width: 90%; text-align: center; }
		.spinner { width: 40px; height: 40px; border: 4px solid #e9ecef; border-top-color: #667eea; border-radius: 50%; animation: spin .8s linear infinite; margin: 0 auto 1rem; }
		@keyframes spin { to { transform: rotate(360deg); } }
		#status { color: #495057; font-size: .95rem; margin: 0; }
	</style>
</head>
<body>
	<div class="box">
		<div class="spinner"></div>
		<h2 style="margin:0 0 .5rem;font-size:1.15rem;">Conectando sucursales</h2>
		<p id="status">Preparando carga (0 / <?= (int) $totalSucursales ?>)</p>
	</div>
</body>
</html>
<?php
flush();

@sqlsrv_query($cidCentral, 'TRUNCATE TABLE SOF_PEDIDOS_CARGA_LOPEZ');

$sucursalesActivas = [];
$sucursalesInfo = [];
$fallidas = [];
$errorFatal = null;

$sqlStock = "
	SET DATEFORMAT YMD
	SELECT COD_ARTICU, CANT_STOCK, CASE WHEN VENDIDO IS NULL THEN 0 ELSE VENDIDO END VENDIDO  FROM
	(
	SELECT A.COD_ARTICU, A.CANT_STOCK, B.VENDIDO FROM STA19 A
	LEFT JOIN
	(
	SELECT COD_ARTICU, SUM(CASE T_COMP WHEN 'NCR' THEN CANTIDAD*-1 ELSE CANTIDAD END)VENDIDO FROM GVA53 WHERE FECHA_MOV > (GETDATE()-30) GROUP BY COD_ARTICU
	)B
	ON A.COD_ARTICU = B.COD_ARTICU
	WHERE A.COD_ARTICU IN
	(SELECT DISTINCT(COD_ARTICU) FROM GVA53 WHERE FECHA_MOV > (GETDATE()-180))
	OR A.COD_ARTICU IN
	(
	SELECT COD_ARTICU FROM ( SELECT A.COD_ARTICU, A.CANT_STOCK, B.VENDIDO FROM STA19 A
	LEFT JOIN (SELECT COD_ARTICU, SUM(CASE T_COMP WHEN 'NCR' THEN CANTIDAD*-1 ELSE CANTIDAD END)VENDIDO FROM GVA53 WHERE FECHA_MOV > (GETDATE()-30) GROUP BY COD_ARTICU)B
	ON A.COD_ARTICU = B.COD_ARTICU )A WHERE CANT_STOCK > 0 AND VENDIDO IS NULL
	)
	GROUP BY A.COD_ARTICU, A.CANT_STOCK, B.VENDIDO
	)A
";

$procesadas = 0;
foreach ($sucursalesGrupo as $sucRaw) {
	$procesadas++;
	$suc = (int) $sucRaw;
	$nombre = isset($infoPorNumero[$suc]['NOM_COM']) && trim((string) $infoPorNumero[$suc]['NOM_COM']) !== ''
		? trim((string) $infoPorNumero[$suc]['NOM_COM'])
		: ('Sucursal ' . $suc);
	$dsn = isset($infoPorNumero[$suc]['DSN']) ? $infoPorNumero[$suc]['DSN'] : '';

	echo '<script>document.getElementById("status").textContent = ' . json_encode(
		'Conectando ' . $nombre . ' (' . $procesadas . ' / ' . $totalSucursales . ')'
	) . ';</script>' . "\n";
	flush();

	if (!isset($configSucursales[$suc])) {
		$fallidas[] = [
			'numero' => $suc,
			'nombre' => $nombre,
			'motivo' => 'No se encontró configuración de conexión para la sucursal.',
		];
		continue;
	}

	$conexionSucursal = new Conexion();
	$conexionSucursal->aplicarConfiguracionSucursal($configSucursales[$suc]);
	$cid = $conexionSucursal->conectar();

	if ($cid === false) {
		$fallidas[] = [
			'numero' => $suc,
			'nombre' => $nombre,
			'motivo' => 'No se pudo conectar' . ($dsn !== '' ? " ($dsn)" : '') . ' (timeout 10s).',
		];
		continue;
	}

	$result1 = @sqlsrv_query($cid, $sqlStock);

	if ($result1 === false) {
		$fallidas[] = [
			'numero' => $suc,
			'nombre' => $nombre,
			'motivo' => 'Error al ejecutar la consulta de stock y ventas.',
		];
		sqlsrv_close($cid);
		continue;
	}

	$sucStr = (string) $suc;
	if (!in_array($sucStr, $sucursalesActivas, true)) {
		$sucursalesActivas[] = $sucStr;
		$sucursalesInfo[$sucStr] = [
			'dsn' => $dsn,
			'nombre' => $nombre,
			'codClient' => isset($infoPorNumero[$suc]['COD_CLIENT']) ? $infoPorNumero[$suc]['COD_CLIENT'] : '',
		];
	}

	$filasSucursal = [];
	while ($v = sqlsrv_fetch_array($result1, SQLSRV_FETCH_ASSOC)) {
		$filasSucursal[] = [
			'num_suc'    => $suc,
			'cod_articu' => trim((string) $v['COD_ARTICU']),
			'cant_stock' => (float) str_replace(',', '.', $v['CANT_STOCK']),
			'cant_vend'  => (float) str_replace(',', '.', $v['VENDIDO'] ?? 0),
		];
	}

	if ($errorFatal === null && !empty($filasSucursal)) {
		if (!insertarLoteCargaLopez($cidCentral, $filasSucursal)) {
			$errorFatal = 'Error al guardar datos en la base central.';
			break;
		}
	}

	sqlsrv_free_stmt($result1);
	sqlsrv_close($cid);
}

if ($errorFatal !== null) {
	$_SESSION['carga_pedido_error'] = $errorFatal;
} elseif (empty($sucursalesActivas)) {
	$_SESSION['carga_pedido_error'] = 'No se pudo conectar con ninguna sucursal del grupo empresario.';
}

$_SESSION['sucursales_activas'] = $sucursalesActivas;
$_SESSION['sucursales_info'] = $sucursalesInfo;
$_SESSION['sucursales_conexion_fallidas'] = $fallidas;
$_SESSION['pedido_grupo_cargado'] = true;
$_SESSION['nuevoPedido'] = 0;

echo '<script>window.location.href = "../index.php";</script>';
exit;
