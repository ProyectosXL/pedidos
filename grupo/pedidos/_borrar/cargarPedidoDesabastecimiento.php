<?php
session_start(); 

if(!isset($_SESSION['username'])){

	header("Location:login.php");

}else{
	
	if($_SESSION['nuevoPedido']==0 && $_SESSION['cargaPedido']==1){

//var_dump($_POST);


$todos = $_POST['cantPed'];

$x = 0;

for($i=0;$i<count($_POST['cantPed']);$i++){
//	echo (int)($todos[$i]).'</br>';
	$x = $x + (int)($todos[$i]);
}



if($x!=0){

$dsn = "1 - CENTRAL";
$user = "Axoft";
$pass = "Axoft";
$suc = 1;
$codClient = 'GTCENT';

$t_ped = $_SESSION['tipo_pedido'];

$fecha = date('Y-m-d');
$fechaCompleta = $fecha.' 00:00:00.000';

$depo = '09';

$cid = odbc_connect($dsn, $user, $pass);

//////////// TOMAR EL PROXIMO NUMERO Y EL PUNTO DE VENTA DEL TALONARIO 97 

$sqlProx = "
	SET DATEFORMAT YMD
	SELECT PROXIMO, SUCURSAL FROM GVA43 WHERE TALONARIO = '97' 
	";

$resultProx=odbc_exec($cid,$sqlProx)or die(exit("Error en odbc_exec"));

while($v=odbc_fetch_array($resultProx)){
	$prox = $v['PROXIMO'];
	$ptoVta = $v['SUCURSAL'];
	
}
	

////////// DESCIFRAR PROXIMO NUMERO, CONVERTIRLO EN VARIABLE	
	
$sqlProxDes = "
	SET DATEFORMAT YMD
	SELECT DBO.Fn_obtenerproximonumero('$prox')proxDes 
	";

$resultProxDes=odbc_exec($cid,$sqlProxDes)or die(exit("Error en odbc_exec"));

while($v=odbc_fetch_array($resultProxDes)){
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

$resultProx=odbc_exec($cid,$sqlActuaProx)or die(exit("Error en odbc_exec"));

while($v=odbc_fetch_array($resultProx)){
	$proxPedDes = $v['proxEnc'];
}

/////////// ACTUALIZAR TABLA CON EL PROXIMO NUMERO

$sqlActuaProxDes = "
	SET DATEFORMAT YMD
	UPDATE GVA43 SET PROXIMO = '$proxPedDes' WHERE TALONARIO = '97'
	";

odbc_exec($cid,$sqlActuaProxDes)or die(exit("Error en odbc_exec"));


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
GC_ECOMMERCE_CERRADO, TOTAL_DESC_TIENDA, PORCEN_DESC_TIENDA, 
HORA_INGRESO
)
VALUES
(
1, '$codClient', '$depo', 
(SELECT COD_TRANSP FROM GVA14 WHERE COD_CLIENT = '$codClient'), 'ZZ', 0, 
(SELECT COND_VTA FROM GVA14 WHERE COD_CLIENT = '$codClient'), 1, 2, 
0, '1800-01-01', '1800-01-01', 
'$fecha', 'PEDIDO DE DESABASTECIMIENTO ECOMMERCE', 1, 
(SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = '$codClient'), ' 000000000000', ' '+'$numPed', 
0, 'E', (SELECT PORC_DESC FROM GVA14 WHERE COD_CLIENT = '$codClient'), 
'A', 'A', 'A', 
0, 97, 0, 
'', 3, 0,
'1800-01-01', '1800-01-01', (SELECT ID_DIRECCION_ENTREGA FROM DIRECCION_ENTREGA WHERE COD_CLIENTE = '$codClient'), 
0, '1800-01-01', 0, 
0, 0, 0, 
(SELECT LEFT((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 2)+SUBSTRING((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 4, 2)+RIGHT((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 2))
)	
";

odbc_exec($cid,$sqlCargaPedidoEncabezado)or die(exit("Error en odbc_exec"));

/////////////// DETALLE

$nroRenglon = 1;

//for($i = 0; $i < count($_POST['codArt']); $i++){
foreach ($_POST['codArt'] as $clave => $valor) {
	$cantArt = $_POST['cantPed'][$clave];
	$rubro = $_POST['rubro'][$clave];
	$stock = $_POST['stock'][$clave];
	//echo $clave.' '.$valor.' '.$cantArt.'</br>';
		
	$sqlVerificaArt = "SELECT * FROM STA03 WHERE COD_ARTICU = '$valor'";
	
	$resultVerifica = odbc_exec($cid, $sqlVerificaArt)or die(exit("Error en odbc_exec"));
		
	if(odbc_num_rows($resultVerifica)==0){
	
		if($cantArt>0){
			//echo $codArt.' '.$cantArt.'</br>';
			
			if($rubro != 'PACKAGING' && $cantArt > 15 ){
			
				$cantArt = 15;
			
			}elseif($rubro == 'PACKAGING' && $cantArt > 100 ){
				
				$cantArt = 100;
				
			}
			
			if($cantArt > $stock){
				
				$cantArt = $stock;
				
			}
				
			$sqlCargaPedidoDetalle = "
			INSERT INTO GVA03
			(
			CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE, 
			PRECIO, TALON_PED, 
			CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
			PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM
			)
			VALUES
			(
			1, $cantArt, $cantArt, $cantArt, $cantArt, $cantArt, '$valor', 0, $nroRenglon, ' '+'$numPed', 0, 0, 
			(SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = '$valor' AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = '$codClient') ), 97,
			0, 0, 0, 0, 0, 0, 7, 7, 'V', 0,
			0, 0, 0, 0, 0, 0, 0
			)
			";

			odbc_exec($cid, $sqlCargaPedidoDetalle)or die(exit("Error en odbc_exec"));
		
			$nroRenglon++;
		
		}

	}else{
		
		if($cantArt>0){
			
			if($rubro != 'PACKAGING' && $cantArt > 15 ){
			
				$cantArt = 15;
			
			}
			
			
			$sqlCargaPedidoDetalle = "
			INSERT INTO GVA03
			(
			CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE, 
			PRECIO, TALON_PED, 
			CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
			PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM, COD_ARTICU_KIT
			)
			VALUES
			(
			1, $cantArt, $cantArt, $cantArt, $cantArt, $cantArt, '$valor', 0, $nroRenglon, ' '+'$numPed', 0, 0, 
			(SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = '$valor' AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = '$codClient') ), 97,
			0, 0, 0, 0, 0, 0, 7, 7, 'V', 0,
			1, 0, 1, 0, 0, 0, 0, '$valor'
			)
			";

			odbc_exec($cid, $sqlCargaPedidoDetalle)or die(exit("Error en odbc_exec"));
		
			$ultRenglon = $nroRenglon;
			
			$nroRenglon++;
			
			
			
			$sqlKit = "SELECT COD_INSUMO, CANTIDAD FROM STA03 WHERE COD_ARTICU = '$valor'";
			
			$resultExplota = odbc_exec($cid, $sqlKit)or die(exit("Error en odbc_exec"));
			
			while($v=odbc_fetch_array($resultExplota)){
				
				$codArt = $v['COD_INSUMO'];
				$cantInsumo = $v['CANTIDAD'];
				
				$cantArt2 = $cantInsumo * $cantArt;
				
				$sqlCargaPedidoDetalle = "
				INSERT INTO GVA03
				(
				CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE, 
				PRECIO, TALON_PED, 
				CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
				PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM, COD_ARTICU_KIT
				)
				VALUES
				(
				1, $cantArt2, $cantArt2, $cantArt2, $cantArt2, $cantArt2, '$codArt', 0, $nroRenglon, ' '+'$numPed', 0, 0, 
				(SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = '$codArt' AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = '$codClient') ), 97,
				0, 0, 0, 0, 0, 0, 7, 7, 'P', $ultRenglon,
				0, 0, 1, 0, 0, 0, 0, '$valor'
				)
				";

				odbc_exec($cid, $sqlCargaPedidoDetalle)or die(exit("Error en odbc_exec"));
			
				$nroRenglon++;
				
				
			}	
		
		}
		
		
		
	}

		
}

$_SESSION['cargaPedido']=0;

//include('mail.php');

echo "<script>setTimeout(function () {window.location.href= '../index.php';},1000);</script>";

}


else{
		//echo $_SESSION['nuevoPedido'].' '.$_SESSION['cargaPedido'];
		header("Location:error.php");
	}

}else{
		//echo $_SESSION['nuevoPedido'].' '.$_SESSION['cargaPedido'];
		header("Location:../index.php");
	}
}	
?>