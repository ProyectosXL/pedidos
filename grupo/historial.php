<?php 
session_start(); 

if(!isset($_SESSION['username'])){

	header("Location:login.php");

}else{
	
$permiso = $_SESSION['permisos'];
?>
<!DOCTYPE HTML>

<html>
<head>
	<meta charset="utf-8">
	<title>DETALLE PEDIDOS</title>	
	<link rel="shortcut icon" href="XL.png" />
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>
<body>	

<?php

	
$dsn = "1 - CENTRAL";



$dsn = "1 - CENTRAL";
$usuario = "sa";
$clave="Axoft1988";
$suc = $_SESSION['numsuc'];
$codClient = $_SESSION['codClient'];

$cid=odbc_connect($dsn, $usuario, $clave);





$sql=
	"
	SET DATEFORMAT YMD

	SELECT CAST(FECHA_PEDI AS DATE)FECHA, A.NRO_PEDIDO, LEYENDA_1, B.CANT FROM GVA21 A
	INNER JOIN
	(
	SELECT NRO_PEDIDO, CAST(SUM(CANT_PEDID) AS FLOAT) CANT FROM GVA03 WHERE TALON_PED IN (96, 97) GROUP BY NRO_PEDIDO
	)B
	ON A.NRO_PEDIDO = B.NRO_PEDIDO
	WHERE COD_CLIENT IN
	(
	'FRBAUD', 
	'FRORCE',
	'FRORIG',
	'FRORNC',
	'FRORSJ',
	'FRPASJ'
	) 
	AND FECHA_PEDI > (GETDATE()-60) AND A.TALON_PED IN (96, 97)
	ORDER BY 1 desc, 2 desc

	";

ini_set('max_execution_time', 300);
$result=odbc_exec($cid,$sql)or die(exit("Error en odbc_exec"));

?>
<a href="../index.php"><img src="botonAtras.png"></a>

<div class="container">
<table class="table table-striped" style="margin-left:20px; margin-right:50px">

        <tr >

				<td class="col-"><h4>FECHA</h4></td>
		
				<td class="col-"><h4>NRO PEDIDO</h4></td>
		
		        <td class="col-"><h4>OBSERVACIONES</h4></td>
		
                <td class="col-"><h4>CANT ARTICULOS</h4></td>
				
				<!--<td class="col-"><h4>FECHA_GUIA</h4></td>  -->

                
        </tr>

		
        <?php

       
		while($v=odbc_fetch_array($result)){

        ?>

		
        <tr >
		
                <td class="col-"><?php echo $v['FECHA'] ;?></td>
				
				<td class="col-"><a href="detallePed.php?pedido=<?php echo $v['NRO_PEDIDO'] ;?>&suc=<?php echo $codClient ;?>&tipo=<?php echo $v['LEYENDA_1'] ;?>"><?php echo $v['NRO_PEDIDO'] ;?></a></td>
				
				<td class="col-"><?php echo $v['LEYENDA_1'] ;?></td>

				<td class="col-" align="center"><?php echo (int)($v['CANT']) ;?></td>
				
				<!--<td class="col-"><?php //echo $v['FECHA_GUIA'] ;?></td>-->

                

        </tr>

		
        <?php

        }

        ?>

		
        		
</table>
</div>
<?php

}





?>