<?php 
session_start(); 

if(!isset($_SESSION['username'])){

	header("Location:../../../sistemas/login.php");

}else{
?>
	
<!doctype html>
<html>
<head>
<?php 
include '../../../css/header.php'; 
$mes = $_GET['mes'];
?>
<title>Estadisticas Mes - <?php echo $mes ; ?></title>
</head>
<body>


<div >
<?php

$dsn = "FRANQUICIAS";
$user = "sa";
$pass = "Axoft";



$cid = odbc_connect($dsn, $user, $pass);


$sql="
SELECT A.NRO_SUCURS, B.DESC_SUCURSAL, IMPORTE, ARTICULOS, COMP, PROM_TICKET, CANT_TICKET_2DO, PROM_2DO, 
CANT_TICKET_3ER, PROM_3ER, CANT_CAMBIOS, PORC_CAMBIOS, PORC_INCREM 
FROM SJ_BI_MES A 
INNER JOIN SUCURSAL B
ON A.NRO_SUCURS = B.NRO_SUCURSAL
WHERE CAST(MES AS VARCHAR) = '$mes'
AND A.NRO_SUCURS IN (813, 814, 815, 816, 876)
ORDER BY 1

";


ini_set('max_execution_time', 300);
$result=odbc_exec($cid,$sql)or die(exit("Error en odbc_exec"));

?>

<div style="width:100%">
  
  <table class="table table-striped table-fh table-9c">
	
	<thead>
		<tr >
			<th style="width: 5%">NRO</th>
			<th style="width: 18%">SUCURSAL</th>
			<th style="width: 12%">VENTAS<br>CON IVA</th>
			<th style="width: 5%">CANT<br>COMP</th>
			<th style="width: 8%">ARTICULOS</th>
			<th style="width: 8%">PROM<br>TICKET</th>
			<th style="width: 5%">2DO<br>PROD</th>
			<th style="width: 5%">PROM<br>2DO</th>
			<th style="width: 5%">3ER<br>PROD</th>
			<th style="width: 5%">PROM<br>3ER</th>
			<th style="width: 8%">CAMBIOS</th>
			<th style="width: 8%">% CAMBIOS</th>
			<th style="width: 8%">% INCREM</th>
		</tr>
	</thead>

	<div >
	<tbody >
<?php

	$importe = 0;
	$articulos = 0;
	$comp = 0;
	$cant_2do = 0;
	$cant_3er = 0;
	$cant_cambios = 0;
	$increm = 0;
	$cont= 0;

 while($v=odbc_fetch_array($result)){

	?>
	<tr>

		<td style="width: 5%"> <?php echo $v['NRO_SUCURS'];?> </td>
		<td style="width: 18%"> <?php echo $v['DESC_SUCURSAL'];?> </td>
		<td style="width: 12%"> <?php echo number_format($v['IMPORTE'], 0, '', '.'); ?> </td>
		<td style="width: 5%"> <?php echo number_format($v['COMP'], 0, '', '.') ;?> </td>
		<td style="width: 8%"> <?php echo number_format($v['ARTICULOS'], 0, '', '.') ;?> </td>
		<td style="width: 8%"> <?php echo (int)($v['PROM_TICKET']) ;?> </td>
		<td style="width: 5%"> <?php echo number_format($v['CANT_TICKET_2DO'], 0, '', '.') ; ?> </td>
		<td style="width: 5%"> <?php echo $v['PROM_2DO'] ;?> </td>
		<td style="width: 5%"> <?php echo number_format($v['CANT_TICKET_3ER'], 0, '', '.') ;?> </td>
		<td style="width: 5%"> <?php echo $v['PROM_3ER'] ;?> </td>
		<td style="width: 8%"> <?php echo number_format($v['CANT_CAMBIOS'], 0, '', '.') ;?> </td>
		<td style="width: 8%"> <?php echo $v['PORC_CAMBIOS'] ;?> </td>
		<td style="width: 8%"> <?php echo $v['PORC_INCREM'] ;?> </td>
			
	</tr>
		
		
	<?php
	
	$importe	 += $v['IMPORTE'];
	$articulos	 += $v['ARTICULOS'];
	$comp		 += $v['COMP'];
	$cant_2do	 += $v['CANT_TICKET_2DO'];
	$cant_3er	 += $v['CANT_TICKET_3ER'];
	$cant_cambios+= $v['CANT_CAMBIOS'];
	$increm		 += $v['PORC_INCREM'];
	$cont++;
 }

?>
	<tr>
		<td style="width: 5%"><h6></h6></td>
		<td style="width: 18%"><h6>TOTAL</h6></td>
		<td style="width: 12%"><h6><?php echo number_format($importe, 0, '', '.') ;?></h6></td>
		<td style="width: 5%"><h6><?php echo number_format($comp, 0, '', '.') ;?></h6></td>
		<td style="width: 8%"><h6><?php echo number_format($articulos, 0, '', '.') ;?></h6></td>
		<td style="width: 8%"><h6><?php echo (int)($importe/$comp) ;?></h6></td>
		<td style="width: 5%"><h6><?php echo number_format($cant_2do, 0, '', '.') ;?></h6></td>
		<td style="width: 5%"><h6><?php echo number_format((($cant_2do/$comp)*100), 1, ',', '.') ;?></h6></td>
		<td style="width: 5%"><h6><?php echo number_format($cant_3er, 0, '', '.') ;?></h6></td>
		<td style="width: 5%"><h6><?php echo number_format((($cant_3er/$comp)*100), 1, ',', '.') ;?></h6></td>
		<td style="width: 8%"><h6><?php echo number_format($cant_cambios, 0, '', '.') ;?></h6></td>
		<td style="width: 8%"><h6><?php echo number_format((($cant_cambios/$comp)*100), 1, ',', '.') ;?></h6></td>
		<td style="width: 8%"><h6><?php echo number_format(($increm/$cont), 1, ',', '.') ;?></h6></td>
	</tr>
</tbody>
</div>
</table>

</div>

</div>


		
</body>
</html>

		


		
<?php
}
?>