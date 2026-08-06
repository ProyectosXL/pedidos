<?php
require_once __DIR__ . '/../../class/GrupoSesion.php';

GrupoSesion::requiereLogin('../login.php');

if (empty($_SESSION['sucursalesGrupo']) || !is_array($_SESSION['sucursalesGrupo'])) {
	$_SESSION['carga_pedido_error'] = 'No hay sucursales configuradas para el grupo empresario.';
	$_SESSION['bloquear_reintento_carga_automatico'] = true;
	header('Location: ../index.php');
	exit;
}

set_time_limit(300);
ini_set('max_execution_time', '300');

/**
 * Registra fallo de carga de sucursal en log de archivo + error_log de PHP.
 */
function logFalloCargaGrupo(array $context) {
	$dir = __DIR__ . '/../../logs';
	if (!is_dir($dir)) {
		@mkdir($dir, 0755, true);
	}
	$context['fecha'] = date('c');
	$context['usuario'] = $_SESSION['username'] ?? null;
	$context['codClient'] = $_SESSION['codClient'] ?? null;
	$line = json_encode($context, JSON_UNESCAPED_UNICODE) . PHP_EOL;
	@file_put_contents($dir . '/carga-pedido-grupo.log', $line, FILE_APPEND | LOCK_EX);
	error_log('[cargaPedido grupo] ' . trim($line));
}

/**
 * @return array{numero: int, nombre: string, motivo: string, detalle?: string, tipo: string, conexion?: array}
 */
function registrarFalloSucursalCarga(int $suc, string $nombre, string $tipo, string $motivo, array $extra = []) {
	$entry = array_merge([
		'numero' => $suc,
		'nombre' => $nombre,
		'tipo'   => $tipo,
		'motivo' => $motivo,
	], $extra);
	logFalloCargaGrupo($entry);
	return $entry;
}

/**
 * Detalle de conexión usado + valores crudos en Lakers.
 */
function armarDetalleConexionSucursal(array $config, Conexion $conexion): array {
	$intento = $conexion->obtenerUltimoIntentoConexion() ?? [];
	return array_merge($intento, [
		'lakers' => [
			'CONEXION_DNS' => $config['CONEXION_DNS'] ?? null,
			'BASE_NOMBRE'  => $config['BASE_NOMBRE'] ?? null,
			'USUARIO_DNS'  => $config['USUARIO_DNS'] ?? null,
			'CLAVE_DNS'    => $config['CLAVE_DNS'] ?? null,
		],
	]);
}

function registrarIntentoConexionSesion(int $suc, string $nombre, array $conexion, bool $ok, array $extra = []) {
	if (!isset($_SESSION['carga_pedido_intentos']) || !is_array($_SESSION['carga_pedido_intentos'])) {
		$_SESSION['carga_pedido_intentos'] = [];
	}
	$_SESSION['carga_pedido_intentos'][] = array_merge([
		'numero'   => $suc,
		'nombre'   => $nombre,
		'ok'       => $ok,
		'conexion' => $conexion,
	], $extra);
}

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
	unset($_SESSION['sucursales_activas'], $_SESSION['sucursales_info'], $_SESSION['carga_pedido_intentos']);
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
	$errorSql = $conexionCentral->formatearUltimoErrorSqlsrv();
	$_SESSION['carga_pedido_error'] = GrupoSesion::mensajeConexionAmigable('conexion_central', $errorSql);
	logFalloCargaGrupo([
		'tipo'     => 'conexion_central',
		'motivo'   => $_SESSION['carga_pedido_error'],
		'detalle'  => $errorSql,
		'conexion' => $conexionCentral->obtenerUltimoIntentoConexion(),
	]);
	$_SESSION['bloquear_reintento_carga_automatico'] = true;
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
		$fallidas[] = registrarFalloSucursalCarga(
			$suc,
			$nombre,
			'sin_config',
			GrupoSesion::mensajeConexionAmigable('sin_config'),
			['detalle' => 'Sin fila en SUCURSALES_LAKERS para NRO_SUCURSAL ' . $suc]
		);
		continue;
	}

	$config = $configSucursales[$suc];
	$conexionDns = trim((string) ($config['CONEXION_DNS'] ?? ''));
	$baseNombre = trim((string) ($config['BASE_NOMBRE'] ?? ''));

	// Sucursales sin DNS o base en SUCURSALES_LAKERS (ej. outlets 912/933):
	// se omiten silenciosamente, no se intenta conectar ni se marcan como fallo.
	if ($conexionDns === '' || $baseNombre === '') {
		logFalloCargaGrupo([
			'tipo'    => 'omitida_sin_dns',
			'numero'  => $suc,
			'nombre'  => $nombre,
			'motivo'  => 'Sucursal omitida: sin CONEXION_DNS o BASE_NOMBRE en SUCURSALES_LAKERS.',
			'lakers'  => [
				'CONEXION_DNS' => $config['CONEXION_DNS'] ?? null,
				'BASE_NOMBRE'  => $config['BASE_NOMBRE'] ?? null,
			],
		]);
		continue;
	}

	$conexionSucursal = new Conexion();
	$conexionSucursal->aplicarConfiguracionSucursal($config);
	$cid = $conexionSucursal->conectar();
	$detalleConexion = armarDetalleConexionSucursal($config, $conexionSucursal);

	if ($cid === false) {
		$errorSql = $conexionSucursal->formatearUltimoErrorSqlsrv();
		$motivoAmigable = GrupoSesion::mensajeConexionAmigable('conexion', $errorSql);
		registrarIntentoConexionSesion($suc, $nombre, $detalleConexion, false, [
			'tipo'    => 'conexion',
			'motivo'  => $motivoAmigable,
			'detalle' => $errorSql,
		]);
		$fallidas[] = registrarFalloSucursalCarga(
			$suc,
			$nombre,
			'conexion',
			$motivoAmigable,
			[
				'detalle'  => $errorSql !== '' ? $errorSql : 'sqlsrv_connect devolvió false sin mensaje',
				'conexion' => $detalleConexion,
			]
		);
		continue;
	}

	registrarIntentoConexionSesion($suc, $nombre, $detalleConexion, true, ['tipo' => 'conexion']);
	logFalloCargaGrupo([
		'tipo'     => 'conexion_ok',
		'numero'   => $suc,
		'nombre'   => $nombre,
		'conexion' => $detalleConexion,
	]);

	$result1 = @sqlsrv_query($cid, $sqlStock);

	if ($result1 === false) {
		$errorSql = Conexion::formatearErroresSqlsrv(sqlsrv_errors(SQLSRV_ERR_ALL));
		$motivoAmigable = GrupoSesion::mensajeConexionAmigable('consulta_stock', $errorSql);
		$fallidas[] = registrarFalloSucursalCarga(
			$suc,
			$nombre,
			'consulta_stock',
			$motivoAmigable,
			[
				'detalle'  => $errorSql,
				'conexion' => $detalleConexion,
			]
		);
		registrarIntentoConexionSesion($suc, $nombre, $detalleConexion, false, [
			'tipo'    => 'consulta_stock',
			'motivo'  => $motivoAmigable,
			'detalle' => $errorSql,
		]);
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
			$errorSql = Conexion::formatearErroresSqlsrv(sqlsrv_errors(SQLSRV_ERR_ALL));
			$errorFatal = GrupoSesion::mensajeConexionAmigable('insert_central', $errorSql);
			logFalloCargaGrupo([
				'tipo'    => 'insert_central',
				'numero'  => $suc,
				'nombre'  => $nombre,
				'motivo'  => $errorFatal,
				'detalle' => $errorSql,
				'filas'   => count($filasSucursal),
			]);
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
$_SESSION['omitir_rechequeo_index'] = true;
unset($_SESSION['bloquear_reintento_carga_automatico']);
$_SESSION['nuevoPedido'] = 0;

echo '<script>window.location.href = "../index.php";</script>';
exit;
