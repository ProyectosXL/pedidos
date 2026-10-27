<?php
// Aumentar límite de variables de entrada
@ini_set('max_input_vars', '5000');

session_start(); 

if(!isset($_SESSION['username'])){
	header("Location:login.php");
	exit;
}elseif($_SESSION['nuevoPedido']==0 && $_SESSION['cargaPedido']==1){

// Temporalmente comentado - necesita migración a sqlsrv
// require '../../../Controlador/comprometer_stock.php';

$todos = $_POST['cantPed'];

$x = 0;

for($i=0;$i<count($_POST['cantPed']);$i++){
//	echo (int)($todos[$i]).'</br>';
	$x = $x + (int)($todos[$i]);
}



if($x!=0){
require_once $_SERVER['DOCUMENT_ROOT'].'/sistemas/class/conexion.php';

$suc = $_SESSION['numsuc'];
$codClient = $_SESSION['codClient'];

$t_ped = $_SESSION['tipo_pedido'];

$vendedor = $_SESSION['vendedor'];

$fecha = date('Y-m-d');
$fechaCompleta = $fecha.' 00:00:00.000';

$depo = $_SESSION['depo'];

$conn = new Conexion();
$cid = $conn->conectar('central');

if($cid === false){
	die("Error al conectar con la base de datos");
}

//////////// TOMAR EL PROXIMO NUMERO Y EL PUNTO DE VENTA DEL TALONARIO 94 

$sqlProx = "
	SET DATEFORMAT YMD
	SELECT PROXIMO, SUCURSAL FROM GVA43 WHERE TALONARIO = '94' 
	";

$resultProx=sqlsrv_query($cid,$sqlProx);
if($resultProx === false){
	die("Error en sqlsrv_query: ".print_r(sqlsrv_errors(), true));
}

while($v=sqlsrv_fetch_array($resultProx, SQLSRV_FETCH_ASSOC)){
	$prox = $v['PROXIMO'];
	$ptoVta = $v['SUCURSAL'];
}

////////// DESCIFRAR PROXIMO NUMERO, CONVERTIRLO EN VARIABLE	
	
$sqlProxDes = "
	SET DATEFORMAT YMD
	SELECT DBO.Fn_obtenerproximonumero('$prox')proxDes 
	";

$resultProxDes=sqlsrv_query($cid,$sqlProxDes);
if($resultProxDes === false){
	die("Error en sqlsrv_query: ".print_r(sqlsrv_errors(), true));
}

while($v=sqlsrv_fetch_array($resultProxDes, SQLSRV_FETCH_ASSOC)){
	$proxDes = $v['proxDes'];
}

/////////// NUMERO DE PEDIDO PARA CARGAR EN TANGO

$numPed = (string)($ptoVta).(string)($proxDes);

/////////// NUMERO DE PEDIDO PARA CIFRAR Y ACTUALIZAR TABLA GVA43

$proxPed = substr((string)('0000000').(string)($proxDes+1),-8);


$sqlActuaProx = "
	SET DATEFORMAT YMD
	SELECT DBO.Fn_encryptarproximonumero('$proxPed')proxEnc
	";

$resultProx=sqlsrv_query($cid,$sqlActuaProx);
if($resultProx === false){
	die("Error en sqlsrv_query: ".print_r(sqlsrv_errors(), true));
}

while($v=sqlsrv_fetch_array($resultProx, SQLSRV_FETCH_ASSOC)){
	$proxPedDes = $v['proxEnc'];
}

/////////// ACTUALIZAR TABLA CON EL PROXIMO NUMERO

$sqlActuaProxDes = "
	SET DATEFORMAT YMD
	UPDATE GVA43 SET PROXIMO = '$proxPedDes' WHERE TALONARIO = '94'
	";

$resultUpdate = sqlsrv_query($cid,$sqlActuaProxDes);
if($resultUpdate === false){
	die("Error en sqlsrv_query: ".print_r(sqlsrv_errors(), true));
}


/************** AHORA SE CARGA EL PEDIDO **********/

/////////////// ENCABEZADO

//echo $codClient.' '.$fecha.' '.$numPed.'</br>';

$sqlCargaPedidoEncabezado = "
SET DATEFORMAT YMD
INSERT INTO GVA21
(
CIRCUITO, COD_CLIENT, COD_SUCURS, 
COD_TRANSP, COD_VENDED, COMP_STK, 
COND_VTA, COTIZ, ESTADO, 
EXPORTADO, FECHA_APRU, FECHA_ENTR, 
FECHA_PEDI, LEYENDA_1, MON_CTE, 
N_LISTA, N_REMITO, NRO_PEDIDO, 
NRO_SUCURS, ORIGEN, PORC_DESC, 
REVISO_FAC, REVISO_PRE, REVISO_STK, 
TALONARIO, TALON_PED, TOTAL_PEDI, 
TIPO_ASIEN, ID_ASIENTO_MODELO_GV, TAL_PE_ORI, 
FECHA_INGRESO, FECHA_ULTIMA_MODIFICACION, ID_DIRECCION_ENTREGA, 
ES_PEDIDO_WEB, FECHA_O_COMP, WEB_ORDER_ID, 
TOTAL_DESC_TIENDA, PORCEN_DESC_TIENDA, 
HORA_INGRESO,
APLICA_DESCUENTO_CLIENTE,
 ES_CLOUD,
 IMPORTE_DESCUENTO_CLIENTE,
 IMPORTE_DESCUENTO_GENERAL,
 IMPORTE_DESCUENTO_GENERAL_SIN_IMPUESTOS,
 IMPORTE_FLETE,
 IMPORTE_FLETE_SIN_IMPUESTOS,
 IMPORTE_INTERES,
 IMPORTE_INTERES_SIN_IMPUESTOS,
 IMPORTE_RECARGO_GENERAL,
 IMPORTE_RECARGO_GENERAL_SIN_IMPUESTOS,
 PORCENTAJE_DESCUENTO_CLIENTE,
 PORCENTAJE_FLETE,
 PORCENTAJE_INTERES,
 PORCENTAJE_RECARGO_GENERAL,
 TOTAL_EXENTO,
 ID_MONEDA
)
VALUES
(
1, '$codClient', '$depo', 
(SELECT COD_TRANSP FROM GVA14 WHERE COD_CLIENT = '$codClient'), '$vendedor', 1, 
(SELECT COND_VTA FROM GVA14 WHERE COD_CLIENT = '$codClient'), 1, 2, 
0, '1800-01-01', '1800-01-01', 
'$fecha', 'PEDIDO $t_ped - MAYORISTAS', 1, 
(SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = '$codClient'), ' 000000000000', ' '+'$numPed', 
0, 'E', (SELECT PORC_DESC FROM GVA14 WHERE COD_CLIENT = '$codClient'), 
'A', 'A', 'A', 
0, 94, 0, 
'', 3, 0,
'1800-01-01', '1800-01-01', (SELECT ID_DIRECCION_ENTREGA FROM DIRECCION_ENTREGA WHERE COD_CLIENTE = '$codClient'), 
0, '1800-01-01', 0, 
0, 0, 
(SELECT LEFT((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 2)+SUBSTRING((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 4, 2)+RIGHT((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 2)),
0,
0,
0,
0,
0,
0,
0,
0,
0,
0,
0,
0,
0,
0,
0,
0,
1
)";

$resultEncabezado = sqlsrv_query($cid,$sqlCargaPedidoEncabezado);
if($resultEncabezado === false){
	die("Error en sqlsrv_query al insertar encabezado: ".print_r(sqlsrv_errors(), true));
}

$nroRenglon = 1;

//for($i = 0; $i < count($_POST['codArt']); $i++){
foreach ($_POST['codArt'] as $clave => $valor) {
	// Verificar que existan todas las claves necesarias (por max_input_vars)
	if(!isset($_POST['cantPed'][$clave]) || !isset($_POST['rubro'][$clave]) || !isset($_POST['stock'][$clave])){
		continue; // Saltar este artículo si faltan datos
	}
	
	$cantArt = $_POST['cantPed'][$clave];
	$rubro = $_POST['rubro'][$clave];
	$stock = $_POST['stock'][$clave];
	//echo $clave.' '.$valor.' '.$cantArt.'</br>';
		
	$sqlVerificaArt = "SELECT * FROM STA03 WHERE COD_ARTICU = '$valor'";
	
	$resultVerifica = sqlsrv_query($cid, $sqlVerificaArt);
	if($resultVerifica === false){
		die("Error en sqlsrv_query: ".print_r(sqlsrv_errors(), true));
	}
	
	$numRows = 0;
	while(sqlsrv_fetch($resultVerifica)){
		$numRows++;
	}
		
	if($numRows==0){
	
		if($cantArt>0){
			//echo $codArt.' '.$cantArt.'</br>';
			
			
			
			if($cantArt > $stock){
				
				$cantArt = $stock;
				
			}
			$promocion = 0;
			$kitCompleto = 0;
				
			$sqlCargaPedidoDetalle = "
			INSERT INTO GVA03
			(
			CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE, 
			PRECIO, TALON_PED, 
			CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
			PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM, NRO_ORDEN,
			IMPORTE_CON_IMPUESTOS,	IMPORTE_PROPORCIONADO,	IMPORTE_SIN_IMPUESTOS
			)
			VALUES
			(
			1, $cantArt, $cantArt, $cantArt, $cantArt, $cantArt, '$valor', 0, $nroRenglon, ' '+'$numPed', 0, 0, 
			(SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = '$valor' AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = '$codClient') ), 94,
			0, 0, 0, 0, 0, 0, 7, 7, 'V', 0,
			$promocion, 0, $kitCompleto, 0, 0, 0, 0, 0,
			0,	0,	0
			)
			";

			$resultDetalle = sqlsrv_query($cid, $sqlCargaPedidoDetalle);
			if($resultDetalle === false){
				die("Error en sqlsrv_query al insertar detalle: ".print_r(sqlsrv_errors(), true));
			}
		
			$nroRenglon++;
		
			// Ejecutar stored procedure para comprometer stock
			$sqlCompStock = "EXEC SJ_COMP_STOCK $cantArt, '$valor', '$depo'";
			$resultCompStock = sqlsrv_query($cid, $sqlCompStock);
			if($resultCompStock === false){
				die("Error al comprometer stock: ".print_r(sqlsrv_errors(), true));
			}
		
		}

	}else{
		
		if($cantArt>0){
			$promocion = 1;
			$kitCompleto = 1;
			$sqlCargaPedidoDetalle = "
			INSERT INTO GVA03
			(
			CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE, 
			PRECIO, TALON_PED, 
			CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
			PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM, COD_ARTICU_KIT, NRO_ORDEN,
			IMPORTE_CON_IMPUESTOS,	IMPORTE_PROPORCIONADO,	IMPORTE_SIN_IMPUESTOS
			)
			VALUES
			(
			1, $cantArt, $cantArt, $cantArt, $cantArt, $cantArt, '$valor', 0, $nroRenglon, ' '+'$numPed', 0, 0, 
			(SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = '$valor' AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = '$codClient') ), 94,
			0, 0, 0, 0, 0, 0, 7, 7, 'V', 0,
			$promocion, 0, $kitCompleto, 0, 0, 0, 0, '$valor', 0,
			0,	0,	0
			)
			";

			$resultDetalleKit = sqlsrv_query($cid, $sqlCargaPedidoDetalle);
			if($resultDetalleKit === false){
				die("Error en sqlsrv_query al insertar detalle kit: ".print_r(sqlsrv_errors(), true));
			}
		
			$ultRenglon = $nroRenglon;
			
			$nroRenglon++;

			// Ejecutar stored procedure para comprometer stock
			$sqlCompStock = "EXEC SJ_COMP_STOCK $cantArt, '$valor', '$depo'";
			$resultCompStock = sqlsrv_query($cid, $sqlCompStock);
			if($resultCompStock === false){
				die("Error al comprometer stock: ".print_r(sqlsrv_errors(), true));
			}
			
			
			
			$sqlKit = "SELECT COD_INSUMO, CANTIDAD FROM STA03 WHERE COD_ARTICU = '$valor'";
			
			$resultExplota = sqlsrv_query($cid, $sqlKit);
			if($resultExplota === false){
				die("Error en sqlsrv_query: ".print_r(sqlsrv_errors(), true));
			}
			
			while($v=sqlsrv_fetch_array($resultExplota, SQLSRV_FETCH_ASSOC)){
				
				$codArt = $v['COD_INSUMO'];
				$cantInsumo = (float)$v['CANTIDAD'];
				$cantArt2 = $cantInsumo * (float)$cantArt;
				$promocion = 0;
				$kitCompleto = 1;
				
				$sqlCargaPedidoDetalle = "
				INSERT INTO GVA03
				(
				CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE, 
				PRECIO, TALON_PED, 
				CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
				PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM, COD_ARTICU_KIT, NRO_ORDEN,
				IMPORTE_CON_IMPUESTOS,	IMPORTE_PROPORCIONADO,	IMPORTE_SIN_IMPUESTOS
				)
				VALUES
				(
				1, $cantArt2, $cantArt2, $cantArt2, $cantArt2, $cantArt2, '$codArt', 0, $nroRenglon, ' '+'$numPed', 0, 0, 
				(SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = '$codArt' AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = '$codClient') ), 94,
				0, 0, 0, 0, 0, 0, 7, 7, 'P', $ultRenglon,
				$promocion, 0, $kitCompleto, 0, 0, 0, 0, '$valor', 0,
				0,	0,	0
				)
				";

				$resultInsumoKit = sqlsrv_query($cid, $sqlCargaPedidoDetalle);
				if($resultInsumoKit === false){
					die("Error en sqlsrv_query al insertar insumo kit: ".print_r(sqlsrv_errors(), true));
				}
			
				$nroRenglon++;

				// Comprometer la cantidad real cargada del componente (cantidad por kit * cantidad pedida)
				$sqlCompStock = "EXEC SJ_COMP_STOCK $cantArt2, '$codArt', '$depo'";
				$resultCompStock = sqlsrv_query($cid, $sqlCompStock);
				if($resultCompStock === false){
					die("Error al comprometer stock: ".print_r(sqlsrv_errors(), true));
				}
				
				
			}	
		
		}
		
		
	}

	
}

$_SESSION['cargaPedido']=0;

//include('mail.php');

?>

<script>setTimeout(function () {window.location.href= '../pedidos.php?cliente=<?php echo $_SESSION['codClient']; ?>';},1000);</script>

<?php

}else{
	// Si x = 0, redirigir a error
	header("Location:error.php");
	exit;
}

}else{
	//echo $_SESSION['nuevoPedido'].' '.$_SESSION['cargaPedido'];
	header("Location:../index.php");
	exit;
}

?>