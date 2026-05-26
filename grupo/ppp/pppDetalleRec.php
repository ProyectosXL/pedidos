<?php 
session_start(); 

if(!isset($_SESSION['username'])){

	header("Location:login.php");

}else{
	
		?>
		<!doctype html>
		<html>
		<head>
		<meta charset="utf-8">
		<title>Detalle Recibos</title>
		<link rel="shortcut icon" href="../icono.jpg" />
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>
		
		
		
		
		
		</head>
		<body>

		
		<div >
		<?php

		$dsn = "1 - CENTRAL";
		$user = "sa";
		$pass = "Axoft1988";
		$suc = $_SESSION['numsuc'];
		
		$_SESSION['tipo_pedido'] = 'GENERAL';
		$_SESSION['depo'] = '01';
		
		$codClient = $_SESSION['username'];
		
		$rec = $_GET['recibo']	;
		
		$cid = odbc_connect($dsn, $user, $pass);


		$sql="
		SET DATEFORMAT YMD

		SELECT CAST(FECHA_VTO AS date) FECHA_VTO, CAST(F_COMP_CAN AS date) F_COMP_CAN, 
		CAST(CAST(IMPORTE_VT AS float) AS DECIMAL(10,2))IMP_VTO, CAST(CAST(IMPORT_CAN AS float) AS DECIMAL(10,2)) IMP_CAN, 
		T_COMP, N_COMP, T_COMP_CAN, N_COMP_CAN
		--, CAST(IMP_VT_UNI AS float) IMP_VT_UNI, CAST(IMP_CAN_UN AS float) IMP_CAN_UN
		FROM GVA07  
		WHERE N_COMP_CAN = '$rec'
		
		
		
		ORDER BY 1, 2
		
		";

		$result=odbc_exec($cid,$sql)or die(exit("Error en odbc_exec"));

		?>


		
		<div class="container">
		<table class="table table-striped" >
		
		<tr> 

			<td > <strong> FECHA VTO</strong> </td>
		
			<td > <strong> IMP A VENCER </strong> </td>
			
			<td > <strong> T COMP A VENCER </strong> </td>
			
			<td > <strong> N COMP A VENCER</strong> </td>

		
		</tr>
		
		<?php

		while($v=odbc_fetch_array($result)){

			?>

			<div >
			
				
				<td align="left"> <?php echo $v['FECHA_VTO'] ;?> </td>
				
				<td align="left"> <?php echo $v['IMP_VTO'] ;?> </td>

				
				<td align="left"> <?php echo $v['T_COMP'] ;?> </td>
				
				<td align="left"> <?php echo $v['N_COMP'] ;?> </td>

				
				
				</tr>
				
			</div>

			<?php

		}

		?>



		</table>
		
		</div>

		</div>
		
		
				
		</body>
		</html>

		<?php

	
}
?>

