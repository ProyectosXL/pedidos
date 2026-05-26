<?php 
session_start(); 

if(!isset($_SESSION['username'])){

	header("Location:../../login.php");

}else{
	
		?>
		<!doctype html>
		<html>
		<head>
		<title>Detalle PPP</title>
		<?php include '../../../css/header_simple.php' ?>	
		</head>
		<body>
<form action="" id="sucu" style="margin:20px">
	
	
<?php
if(!isset($_GET['desde'])){
	$ayer = date('Y-m').'-'.strright(('0'.((date('d'))-1)),2);
	$hoy = date('Y-m').'-'.strright(('0'.((date('d')))),2);
}else{
	$desde = $_GET['desde'];
	$hasta = $_GET['hasta'];
}

		$dsn = "1 - CENTRAL";
		$user = "sa";
		$pass = "Axoft1988";
		$suc = $_SESSION['numsuc'];
		
		$_SESSION['tipo_pedido'] = 'GENERAL';
		$_SESSION['depo'] = '01';
		
		$codClient = $_SESSION['username'];
		$cliente = $_GET['cliente'];
		$cid = odbc_connect($dsn, $user, $pass);


		$sql="
			SET DATEFORMAT YMD
			SELECT CAST(FECHA_RECIBO AS date)FECHA, COD_CLIENTE, B.RAZON_SOCI, T_COMP, N_COMP, CAST(CAST(IMPORTE_RECIBO AS float) AS INT) IMPORTE_RECIBO , 
			CAST(PPP AS INT) PPP, CAST(DIAS AS INT) DIAS 
			FROM GC_VIEW_PPP A
			INNER JOIN
			(SELECT COD_CLIENT, RAZON_SOCI FROM GVA14 WHERE COD_VENDED = 'Z4')B
			ON A.COD_CLIENTE = B.COD_CLIENT

			WHERE (FECHA_RECIBO BETWEEN '$desde' AND '$hasta')
			AND A.COD_CLIENTE = '$cliente'
		

		";

		$result=odbc_exec($cid,$sql)or die(exit("Error en odbc_exec"));

		?>


		
		
		<table class="table table-striped" >
		
		<tr> 
			<td > <strong> FECHA </strong> </td>
			<td > <strong> CODIGO </strong> </td>
			<td > <strong> NOMBRE </strong> </td>
			<td > <strong> TIPO COMP </strong> </td>
			<td > <strong> COMPROBANTE </strong> </td>
			<td > <strong> IMPORTE RECIBO </strong> </td>
			<td > <strong> PPP </strong> </td>
		</tr>
		
		<?php
		$total = 0;
		while($v=odbc_fetch_array($result)){

		?>

						
		<tr>
			<td align="left"> <?php echo $v['FECHA'] ;?> </td>
			<td align="left"> <?php echo $v['COD_CLIENTE'] ;?> </td>
			<td align="left"> <?php echo $v['RAZON_SOCI'] ;?> </td>
			<td align="left"> <?php echo $v['T_COMP'] ;?> </td>
			<td align="left"><a href="pppDetalleRec.php?recibo=<?php echo $v['N_COMP'] ; ?>"</a> <?php echo $v['N_COMP'] ;?> </td>
			<td align="left"> <?php echo number_format($v['IMPORTE_RECIBO'] , 0, '', '.') ;?> </td>
			<td align="left"> <?php echo $v['PPP'] ;?> </td>
		</tr>
			

		<?php
		$total += $v['IMPORTE_RECIBO'];
		}

		?>
		
		<tr>
			<td align="left"> </td>
			<td align="left"> </td>
			<td align="left"> </td>
			<td align="left"> </td>
			<td align="left"> <h5> TOTAL </h5> </td>
			<td align="left"> <h5> <?php echo number_format($total , 0, '', '.'); ?> </h5> </td>
			<td align="left"> </td>
		</tr>
		
		
		</table>

		</body>
		</html>

		<?php

	

}
?>

