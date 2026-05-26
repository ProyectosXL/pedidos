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
?>
<div align="center">	
	Desde
	<input type="date" name="desde" value="<?php if(!isset($_GET['desde'])){ echo $ayer; } else { echo $desde ; }?>"></input>
	
	Hasta
	<input type="date" name="hasta" value="<?php if(!isset($_GET['hasta'])){ echo $hoy; } else { echo $hasta ; }?>"></input>
	
		<input type="submit" value="Consultar" class="btn btn-primary btn-sm" style="margin-bottom:4px">
	</form>
</div>	

<?php
if(isset($_GET['desde'])){

		$desde = $_GET['desde'];
		$hasta = $_GET['hasta'];

		$dsn = "1 - CENTRAL";
		$user = "sa";
		$pass = "Axoft1988";
		$suc = $_SESSION['numsuc'];
		
		$_SESSION['tipo_pedido'] = 'GENERAL';
		$_SESSION['depo'] = '01';
		
		$codClient = $_SESSION['username'];
	
		$cid = odbc_connect($dsn, $user, $pass);


		$sql="
		SET DATEFORMAT YMD
		SELECT COD_CLIENTE, RAZON_SOCI, SUM(IMPORTE_RECIBO)IMPORTE_RECIBO
		FROM
		(
			SELECT CAST(FECHA_RECIBO AS date)FECHA, COD_CLIENTE, B.RAZON_SOCI, T_COMP, N_COMP, CAST(CAST(IMPORTE_RECIBO AS float) AS INT) IMPORTE_RECIBO , 
			CAST(PPP AS INT) PPP, CAST(DIAS AS INT) DIAS 
			FROM GC_VIEW_PPP A
			INNER JOIN
			(SELECT COD_CLIENT, RAZON_SOCI FROM GVA14 WHERE COD_VENDED = 'Z4')B
			ON A.COD_CLIENTE = B.COD_CLIENT

			WHERE (FECHA_RECIBO BETWEEN '$desde' AND '$hasta')
		)A	
		GROUP BY COD_CLIENTE, RAZON_SOCI
		ORDER BY 1 desc, 3

		";

		$result=odbc_exec($cid,$sql)or die(exit("Error en odbc_exec"));

		?>


		
		
		<table class="table table-striped" >
		
		<tr> 
			
			<td > <strong> CODIGO </strong> </td>
			<td > <strong> NOMBRE </strong> </td>
			
			<td > <strong> IMPORTE RECIBO </strong> </td>

		</tr>
		
		<?php
		$total = 0;
		while($v=odbc_fetch_array($result)){

		?>

						
		<tr>
			
			<td align="left"> <?php echo $v['COD_CLIENTE'] ;?> </td>
			<td align="left"> <a href="recibos_cliente.php?cliente=<?php echo $v['COD_CLIENTE'];?>&desde=<?php echo $desde ?>&hasta=<?php echo $hasta ?>"> 
			<?php echo $v['RAZON_SOCI'] ;?> </a> </td>
			
			<td align="left"> <?php echo number_format($v['IMPORTE_RECIBO'] , 0, '', '.') ;?> </td>
			
		</tr>
			

		<?php
		$total += $v['IMPORTE_RECIBO'];
		}

		?>
		
		<tr>
			<td align="left"> </td>
			<td align="left"> <h5> TOTAL </h5> </td>
			<td align="left"> <h5> <?php echo number_format($total , 0, '', '.'); ?> </h5> </td>
			
		</tr>
		
		
		</table>

		</body>
		</html>

		<?php

	
}
}
?>

