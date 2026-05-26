<?php 
session_start(); 

if(!isset($_SESSION['username'])){

	header("Location:../../../sistemas/login.php");

}else{
?>
	
<!doctype html>
<html>
<head>
<?php include '../../../css/header.php'; ?>
<title>Estadisticas - Semana</title>
</head>
<body>


<div >
<?php

$dsn = "FRANQUICIAS";
$user = "sa";
$pass = "Axoft";

//$sem = $_GET['sem'];

$cid = odbc_connect($dsn, $user, $pass);


$sql="
SET DATEFORMAT YMD

SELECT * FROM
(
SELECT 
B.SEMANA, B.PRIMER, B.ULTIMO, CAST(SUM(IMPORTE) AS INT) IMPORTE, SUM(ARTICULOS)ARTICULOS, SUM(COMP)COMP,
SUM(IMPORTE)/SUM(COMP) PROM_TICKET, 
SUM(CANT_TICKET_2DO)CANT_TICKET_2DO, CAST(CAST(SUM(CANT_TICKET_2DO) AS FLOAT)/CAST(SUM(COMP) AS FLOAT)*100AS DECIMAL(10,2)) PROM_2DO,
SUM(CANT_TICKET_3ER)CANT_TICKET_3ER, CAST(CAST(SUM(CANT_TICKET_3ER) AS FLOAT)/CAST(SUM(COMP) AS FLOAT)*100AS DECIMAL(10,2)) PROM_3ER,
SUM(CANT_CAMBIOS)CANT_CAMBIOS, CAST(CAST(SUM(CANT_CAMBIOS) AS FLOAT)/CAST(SUM(COMP) AS FLOAT)*100AS DECIMAL(10,2)) PORC_CAMBIOS,
CAST(AVG(PORC_INCREM) AS DECIMAL(10,2))PORC_INCREM

FROM SJ_BI_DIA A

INNER JOIN SJ_SEMANA B
ON DATEPART(WEEK, A.FECHA) = B.SEMANA AND ANIO = 2018

WHERE A.NRO_SUCURS IN (813, 814, 815, 816, 876)

GROUP BY B.SEMANA, B.PRIMER, B.ULTIMO
)A


ORDER BY 1

";


ini_set('max_execution_time', 300);
$result=odbc_exec($cid,$sql)or die(exit("Error en odbc_exec"));

?>

<div style="width:100%">
  
  <table class="table table-striped table-fh table-9c">
	
	<thead>
		<tr >
			<th style="width: 5%"><a href="index.php">MES</a> /<br>SEM</th>
			<th style="width: 8%">PRIMER</th>
			<th style="width: 8%">ULTIMO</th>
			<th style="width: 10%" align="center">VENTAS<br>CON IVA</th>
			<th style="width: 8%" align="center">CANT<br>COMP</th>
			<th style="width: 8%">ARTICULOS</th>
			<th style="width: 8%" align="center">PROM<br>TICKET</th>
			<th style="width: 6%" align="center">2DO<br>PROD</th>
			<th style="width: 6%" align="center">PROM<br>2DO</th>
			<th style="width: 6%" align="center">3ER<br>PROD</th>
			<th style="width: 6%" align="center">PROM<br>3ER</th>
			<th style="width: 7%">CAMBIOS</th>
			<th style="width: 7%" align="center">%<br>CAMBIOS</th>
			<th style="width: 7%" align="center">%<br>INCREM</th>
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

		<td style="width: 5%"> 
			<a href="detalle_sem_local.php?sem=<?php echo $v['SEMANA'];?>&desde=<?php echo $v['PRIMER'];?>&hasta=<?php echo $v['ULTIMO'];?>" >
			<?php echo $v['SEMANA'];?> 
			</a>
		</td>
		<td style="width: 8%"> <?php echo $v['PRIMER'];?> </td>
		<td style="width: 8%"> <?php echo $v['ULTIMO'];?> </td>
		<td style="width: 10%"> <?php echo number_format($v['IMPORTE'], 0, '', '.'); ?> </td>
		<td style="width: 8%"> <?php echo number_format($v['COMP'], 0, '', '.') ;?> </td>
		<td style="width: 8%"> <?php echo number_format($v['ARTICULOS'], 0, '', '.') ;?> </td>
		<td style="width: 8%"> <?php echo (int)($v['PROM_TICKET']) ;?> </td>
		<td style="width: 6%"> <?php echo number_format($v['CANT_TICKET_2DO'], 0, '', '.') ; ?> </td>
		<td style="width: 6%"> <?php echo $v['PROM_2DO'] ;?> </td>
		<td style="width: 6%"> <?php echo number_format($v['CANT_TICKET_3ER'], 0, '', '.') ;?> </td>
		<td style="width: 6%"> <?php echo $v['PROM_3ER'] ;?> </td>
		<td style="width: 7%"> <?php echo number_format($v['CANT_CAMBIOS'], 0, '', '.') ;?> </td>
		<td style="width: 7%"> <?php echo $v['PORC_CAMBIOS'] ;?> </td>
		<td style="width: 7%"> <?php echo $v['PORC_INCREM'] ;?> </td>
			
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
		<td style="width: 8%"><h6></h6></td>
		<td style="width: 8%"><h6>TOTAL</h6></td>
		<td style="width: 10%"><h6><?php echo number_format($importe, 0, '', '.') ;?></h6></td>
		<td style="width: 8%"><h6><?php echo number_format($comp, 0, '', '.') ;?></h6></td>
		<td style="width: 8%"><h6><?php echo number_format($articulos, 0, '', '.') ;?></h6></td>
		<td style="width: 8%"><h6><?php echo (int)($importe/$comp) ;?></h6></td>
		<td style="width: 6%"><h6><?php echo number_format($cant_2do, 0, '', '.') ;?></h6></td>
		<td style="width: 6%"><h6><?php echo number_format((($cant_2do/$comp)*100), 1, ',', '.') ;?></h6></td>
		<td style="width: 6%"><h6><?php echo number_format($cant_3er, 0, '', '.') ;?></h6></td>
		<td style="width: 6%"><h6><?php echo number_format((($cant_3er/$comp)*100), 1, ',', '.') ;?></h6></td>
		<td style="width: 7%"><h6><?php echo number_format($cant_cambios, 0, '', '.') ;?></h6></td>
		<td style="width: 7%"><h6><?php echo number_format((($cant_cambios/$comp)*100), 1, ',', '.') ;?></h6></td>
		<td style="width: 7%"><h6><?php echo number_format(($increm/$cont), 1, ',', '.') ;?></h6></td>
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

