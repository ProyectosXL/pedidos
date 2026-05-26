<?php 
session_start(); 

if(!isset($_SESSION['username'])){

	header("Location:login.php");

}else{

echo '<h3 align="center">Aguarde un momento por favor</h3>';

// Guardar sucursales activas en sesión para filtrar columnas después
// Solo se agregarán las que se conecten exitosamente
$sucursalesActivas = [];
$sucursalesInfo = []; // Mapeo de número de sucursal a información

require_once __DIR__ . '/../class/sucursal.php';
require_once __DIR__ . '/../class/conexion.php';

$sucursalObj = new Sucursal();
$idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
$sucursalToCodClient = $sucursalObj->obtenerMapeoSucursalCodCliente($idFranquicia, 'central');
$_SESSION['sucursal_to_codclient'] = $sucursalToCodClient;

// Conexión central usando clase Conexion (sin ODBC)
$conexionCentral = new Conexion();
$cidCentral = $conexionCentral->conectar('central');

if ($cidCentral === false) {
	echo "</br></br><H3 ALIGN='CENTER' style='color:red;'>ERROR: No se pudo conectar con la base central.</H3></br>";
} else {
	// Limpiar tabla antes de cargar nuevos datos
	$sqlTruncate = "TRUNCATE TABLE SOF_PEDIDOS_CARGA_LOPEZ";
	@sqlsrv_query($cidCentral, $sqlTruncate);
}

// Procesar solo las sucursales activas (si la base central está disponible)
if ($cidCentral !== false) {
	for($i=0;$i<count($_POST['suc']);$i++){
		$suc = $_POST['suc'][$i];
		$dsn = $_POST['dsn'][$i]; // mantenemos por compatibilidad en mensajes
	
		$selec = $_POST['selec'][$i];
		
		if($selec == 'si'){

		// Obtener conexión a la sucursal usando la clase Conexion
		$conexionSucursal = new Conexion();

		// Configurar DNS y base para la sucursal seleccionada
		if (!$conexionSucursal->setearDnsBaseName($suc)) {
			echo "</br></br><H3 ALIGN='CENTER' style='color:orange;'>ADVERTENCIA: No se encontró configuración para sucursal $suc</H3></br>";
			continue;
		}

		$cid = $conexionSucursal->conectar(); 
		
		$sql1 = "
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
		
		ini_set('max_execution_time', 300);
		
		// Verificar si la conexión fue exitosa
		if ($cid === false) {
			// Error de conexión - no agregar esta sucursal a activas
			echo "</br></br><H3 ALIGN='CENTER' style='color:orange;'>ADVERTENCIA: No se pudo conectar con sucursal $suc ($dsn)</H3></br>";
			continue; // Continuar con la siguiente sucursal
		}
		
		$result1 = @sqlsrv_query($cid, $sql1);
		
		// Verificar si la ejecución de la consulta fue exitosa
		if ($result1 === false) {
			// Error al ejecutar consulta - no agregar esta sucursal a activas
			echo "</br></br><H3 ALIGN='CENTER' style='color:orange;'>ADVERTENCIA: Error al ejecutar consulta para sucursal $suc</H3></br>";
			continue; // Continuar con la siguiente sucursal
		}
		
		// Si llegamos aquí, la conexión fue exitosa - agregar a sucursales activas
		if (!in_array($suc, $sucursalesActivas)) {
			$sucursalesActivas[] = $suc;
			$sucursalesInfo[$suc] = [
				'dsn' => $dsn,
				'selec' => $selec
			];
		}

		while($v = sqlsrv_fetch_array($result1, SQLSRV_FETCH_ASSOC)){
			$codArticu = trim((string) $v['COD_ARTICU']);
			
			$cantStock = (float) str_replace(',', '.', $v['CANT_STOCK']);
			$cantVend  = (float) str_replace(',', '.', $v['VENDIDO'] ?? 0);

			$sql2 = "
			INSERT INTO SOF_PEDIDOS_CARGA_LOPEZ (NUM_SUC, COD_ARTICU, CANT_STOCK, VENDIDO) 
			VALUES (?, ?, ?, ?);
			";
			
			$params2 = array((int) $suc, $codArticu, $cantStock, $cantVend);
			
			ini_set('max_execution_time', 300);
			$resultInsert = sqlsrv_query($cidCentral, $sql2, $params2);

			if ($resultInsert === false) {
				die("</br></br>IMPOSIBLE CONECTARSE CON BASE CENTRAL PARA INSERTAR DATOS");
			}
		}
		
		}

	}
}

// Guardar en sesión solo las sucursales que se conectaron exitosamente
$_SESSION['sucursales_activas'] = $sucursalesActivas;
$_SESSION['sucursales_info'] = $sucursalesInfo;

}
?>
<script>setTimeout(function () {window.location.href= 'index.php';},1000);</script>