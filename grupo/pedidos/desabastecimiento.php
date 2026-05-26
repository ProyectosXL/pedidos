<?php 
session_start(); 

if(!isset($_SESSION['username'])){

	header("Location:../login.php");

}else{
	
	if($_SESSION['nuevoPedido']==0 && $_SESSION['cargaPedido']==1){
		?>
		<!doctype html>
		<html>
		<head>
		<meta charset="utf-8">
		<title>Carga de Pedidos</title>
		<link rel="shortcut icon" href="icono.jpg" />
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>
		
		<script>
			
			function pulsar(e) {
			tecla = (document.all) ? e.keyCode : e.which;
			return (tecla != 13);
			}
			
			function total() {
				var suma = 0;
				var x = document.querySelectorAll("#id_tabla input[name='cantPed[]']"); //tomo todos los input con name='cantProd[]'

				var i;
				for (i = 0; i < x.length; i++) {
					suma += parseInt(0+x[i].value); //acá hago 0+x[i].value para evitar problemas cuando el input está vacío, si no tira NaN
				}

				// ni idea dónde lo vas a mostrar ese dato, yo puse un input, pero puede ser cualquier otro elemento
				document.getElementById('total').value = suma;
			};
			
		</script>
		
		<style type="text/css">
		#encabezado{
			position:fixed;
			top:0;
			width:100%;
			height:60px;
			background-color:#333;
			color:#FFFFFF;
			
		}
		</style>
		
		</head>
		<body>

		</br></br>
		<div class="container-fluid">
		<?php

		$dsn = "1 - CENTRAL";
		$user = "Axoft";
		$pass = "Axoft";
		$suc = $_SESSION['numsuc'];
		
		$_SESSION['tipo_pedido'] = 'ACCESORIOS';
		$_SESSION['depo'] = '01';
		
		$codClient = $_SESSION['username'];
		
		$cid = odbc_connect($dsn, $user, $pass);


		$sql="
			SET DATEFORMAT YMD

			SELECT COD_ARTICU, DESCRIPCIO, RUBRO, CAST(CANT_STOCK - (CASE WHEN CANT_PEDID IS NULL THEN 0 ELSE CANT_PEDID END) AS INT) STOCK_DISP
			FROM
			(
				SELECT A.COD_ARTICU, B.DESCRIPCIO, D.RUBRO, A.CANT_STOCK, C.CANT_PEDID FROM STA19 A
				INNER JOIN STA11 B
				ON A.COD_ARTICU = B.COD_ARTICU
				LEFT JOIN
				(
					SELECT A.COD_ARTICU, SUM(A.CANT_PEDID)CANT_PEDID FROM (
					SELECT B.COD_ARTICU, SUM(B.CANT_PEDID) CANT_PEDID FROM GVA21 A INNER JOIN GVA03 B ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
					WHERE A.TALON_PED = 99 AND ESTADO = 2 AND B.COD_ARTICU NOT LIKE '[*]%' AND A.FECHA_PEDI >= GETDATE()-15 GROUP BY B.COD_ARTICU
					UNION ALL
					SELECT B.COD_ARTICU, SUM(B.CANT_PEDID) CANT_PEDID FROM GVA21 A INNER JOIN GVA03 B ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
					WHERE A.TALON_PED = 97 AND A.COD_SUCURS = '09' AND ESTADO = 2 AND B.COD_ARTICU NOT LIKE '[*]%' AND A.FECHA_PEDI >= GETDATE()-15 GROUP BY B.COD_ARTICU
					)A GROUP BY A.COD_ARTICU
				)C
				ON B.COD_ARTICU = C.COD_ARTICU
				INNER JOIN SOF_RUBROS_TANGO D
				ON A.COD_ARTICU = D.COD_ARTICU
				WHERE A.COD_DEPOSI = '09'
				AND A.CANT_STOCK > 0

			)A
			WHERE (CANT_STOCK - (CASE WHEN CANT_PEDID IS NULL THEN 0 ELSE CANT_PEDID END)) > 0
			ORDER BY 3, 1
		
		";

		$result=odbc_exec($cid,$sql)or die(exit("Error en odbc_exec"));

		?>


		<form method="POST" action="cargarPedidoDesabastecimiento.php" onkeypress = "return pulsar(event)">

		
		<table class="table table-striped" id="id_tabla">

		
		
		<tr class="row" id="encabezado">
		
		<td class="col-md" >CODIGO</td>

			<td class="col-md" style="margin-left:15%">DESCRIPCION</td>
			
			<td class="col-md" style="margin-left:23%">RUBRO</td>

			<td class="col-md" style="margin-left:16%;font-size:smaller" align="center">STOCK CENTRAL</td>
			
			<td class="col-md" style="font-size:smaller">PEDIDO</td></div>

			<td class="col-md" ><input type="submit" value="Enviar" class="btn btn-primary btn-sm"></td>
		
		</tr>


		<?php


		while($v=odbc_fetch_array($result)){

			?>

			<div style="margin-top:20px">
			
			<tr style="font-size:smaller" >

			<td> <?php echo $v['COD_ARTICU'] ;?></td>
			
			<td><input name="codArt[]" value="<?php echo $v['COD_ARTICU'] ;?>"  hidden></td>

			<td align="left"><?php echo $v['DESCRIPCIO'] ;?></td>
			
			<td align="left"><?php echo $v['RUBRO'] ;?></td>
			
			<td><input name="rubro[]" value="<?php echo $v['RUBRO'] ;?>"  hidden></td>
			
			<td><input name="stock[]" value="<?php echo $v['STOCK_DISP'] ;?>"  hidden></td>

			<td align="left"><?php echo (int)($v['STOCK_DISP']) ;?></td>

			<td ><input type="text" name="cantPed[]" value="0" size="2" tabindex="1"  id="articulo" onChange="total()"></td>

			<td></td>
			
			</tr>
			</div>

			<?php
			
		}


		?>



		</table>
		
		</form>

		</div>
		</br></br></br>
		<div align="center"><a>Total de articulos:</a><input name="total_todo" size="4" id="total" value="0" type="text"></div>
		</br></br></br>
		</body>
		</html>

		<?php
	}

	else{
		//echo $_SESSION['nuevoPedido'].' '.$_SESSION['cargaPedido'];
		header("Location:../login.php");
	}
	
}
?>
