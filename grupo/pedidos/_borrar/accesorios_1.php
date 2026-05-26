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

			SELECT A.COD_ARTICU, DESCRIPCIO, B.RUBRO, CANT_STOCK, STOCK_LOCAL, VENDIDO_LOCAL, DIST FROM
			(
				SELECT COD_ARTICU, 
				CASE WHEN A.COD_ARTICU IN (SELECT COD_ARTICU FROM MAESTRO_DESTINOS WHERE LIQUIDACION = 'SI') THEN DESCRIPCIO+' -- SALE! --' ELSE DESCRIPCIO END DESCRIPCIO, 
				CASE WHEN CANT_STOCK < 0 THEN 0 ELSE CANT_STOCK END CANT_STOCK, STOCK_LOCAL, VENDIDO_LOCAL, CASE WHEN DIST IS NULL THEN 0 ELSE DIST END DIST FROM
				(
					SELECT A.COD_ARTICU, DESCRIPCIO, CASE WHEN B.CANT_PEND IS NULL THEN A.CANT_STOCK ELSE CANT_STOCK-B.CANT_PEND END CANT_STOCK, 
					STOCK_LOCAL, VENDIDO_LOCAL, CAST(C.CANT_PEDID AS INT) DIST 
					FROM
						(SELECT A.COD_ARTICU, A.DESCRIPCIO, A.CANT_STOCK, CASE WHEN B.CANT_STOCK IS NULL THEN 0 ELSE B.CANT_STOCK END STOCK_LOCAL, 
						CASE WHEN B.VENDIDO IS NULL THEN 0 ELSE B.VENDIDO END VENDIDO_LOCAL FROM
						(SELECT A.COD_ARTICU, B.DESCRIPCIO, A.CANT_STOCK FROM
							( SELECT A.COD_ARTICU, A.CANT_STOCK FROM STA19 A WHERE A.COD_DEPOSI = '01' AND (A.COD_ARTICU LIKE 'X%' OR A.COD_ARTICU = 'OHGIFT') AND A.CANT_STOCK > 0 
							UNION ALL SELECT COD_ARTICU, MIN (STOCK) STOCK FROM ( SELECT A.COD_ARTICU, A.COD_INSUMO, CAST(B.CANT_STOCK AS INT)STOCK FROM STA03 A
							INNER JOIN STA19 B ON A.COD_INSUMO = B.COD_ARTICU WHERE B.COD_DEPOSI = '01'
							AND A.COD_ARTICU IN ('HC INV 17-01', 'HC INV 17-02','HC VER 17-01-A', 'HC VER 17-01-B', 'HC VER 17-01-C', 'HC VER 17-01-D')
							)A GROUP BY COD_ARTICU )A INNER JOIN STA11 B ON A.COD_ARTICU = B.COD_ARTICU  )A LEFT JOIN (SELECT * FROM SOF_PEDIDOS_CARGA WHERE NUM_SUC = $suc)B 
					ON A.COD_ARTICU = B.COD_ARTICU COLLATE Modern_Spanish_CI_AI )A
					LEFT JOIN 
						(SELECT COD_ARTICU, SUM(CANT_A_DES)CANT_PEND FROM GVA03 A INNER JOIN GVA21 B ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
						WHERE B.ESTADO = 2 AND B.FECHA_PEDI > (GETDATE()-15) AND B.COD_SUCURS = '01' GROUP BY COD_ARTICU HAVING SUM(CANT_A_DES) > 0)B
				ON A.COD_ARTICU = B.COD_ARTICU
				LEFT JOIN
				(SELECT B.COD_ARTICU, SUM(B.CANT_PEDID) CANT_PEDID FROM GVA21 A INNER JOIN GVA03 B ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
				WHERE A.ESTADO IN (2, 3, 4) AND A.FECHA_PEDI >= GETDATE()-15 AND A.COD_CLIENT = '$codClient' AND COD_SUCURS = '03' GROUP BY B.COD_ARTICU)C
				ON A.COD_ARTICU = C.COD_ARTICU
				)A
			)A
			INNER JOIN 
				(SELECT A.CODE, B.DESCRIP RUBRO FROM STA11ITC A INNER JOIN STA11FLD B ON A.IDFOLDER = B.IDFOLDER WHERE B.DESCRIP NOT LIKE '[_]D%'
				AND B.IDFOLDER IN (2, 4, 5, 7, 10, 14, 16, 18, 19, 21, 31)
				)B
			ON A.COD_ARTICU = B.CODE
			
			WHERE CANT_STOCK > 0
			AND A.COD_ARTICU NOT IN (SELECT COD_ARTICU FROM MAESTRO_DESTINOS WHERE DESTINO LIKE '%DISCONTINUO%' OR DESTINO LIKE '%GUARDAR%')
			AND A.COD_ARTICU NOT IN (SELECT COD_INSUMO FROM STA03 WHERE COD_ARTICU IN ('HC INV 17-01', 'HC INV 17-02','HC VER 17-01-A', 'HC VER 17-01-B', 'HC VER 17-01-C', 'HC VER 17-01-D'))
			ORDER BY 3, 1
		
		";

		$result=odbc_exec($cid,$sql)or die(exit("Error en odbc_exec"));

		?>


		<form method="POST" action="cargarPedidoNuevo.php" onkeypress = "return pulsar(event)">

		
		<table class="table table-striped" id="id_tabla">

		
		
		<tr class="row" id="encabezado">
		
		<td class="col-md" >CODIGO</td>

			<td class="col-md" style="margin-left:10%">DESCRIPCION</td>
			
			<td class="col-md" style="margin-left:20%">RUBRO</td>

			<td class="col-md" style="margin-left:16%;font-size:smaller" align="center">STOCK CENTRAL</td>
			
			<?php if($_SESSION['dsn']!='SIN'){echo "<td class='col-md' align='center' style='font-size:smaller'>STOCK LOCAL</td>"; }?>
			
			<?php if($_SESSION['dsn']!='SIN'){echo "<td class='col-md' align='center' style='font-size:smaller'>VENTAS 30 DIAS</td>"; }?>
			
			<td class="col-md" style="font-size:smaller">DIST</td></div>
			
			<td class="col-md" style="font-size:smaller">PEDIDO</td></div>

			<td class="col-md" ><input type="submit" value="Enviar" class="btn btn-primary btn-sm"></td>
		
		</tr>


		<?php


		while($v=odbc_fetch_array($result)){

			?>

			<div style="margin-top:20px">
			
			<?php 
			if(substr($v['DESCRIPCIO'], -11)=='-- SALE! --'){
				?>
				<tr style="font-size:smaller;font-weight:bold;color:#FE2E2E" >
				<?php
			}else{
				?>
				<tr style="font-size:smaller" >
				<?php
			}
			?>

			<td> <?php echo $v['COD_ARTICU'] ;?></td>
			
			<td><input name="codArt[]" value="<?php echo $v['COD_ARTICU'] ;?>"  hidden></td>

			<td align="left"><?php echo $v['DESCRIPCIO'] ;?></td>
			
			<td align="left"><?php echo $v['RUBRO'] ;?></td>
			
			<td><input name="rubro[]" value="<?php echo $v['RUBRO'] ;?>"  hidden></td>
			
			<td><input name="stock[]" value="<?php echo $v['CANT_STOCK'] ;?>"  hidden></td>

			<td align="left"><?php echo (int)($v['CANT_STOCK']) ;?></td>

			<?php 
			if($_SESSION['dsn']!='SIN'){?>
			<td align="left"><?php echo (int)($v['STOCK_LOCAL']) ;?></td>
			<?php ;}?>
			
			<?php 
			if($_SESSION['dsn']!='SIN'){?>
			<td align="left" style="margin-right:60px"><?php echo (int)($v['VENDIDO_LOCAL']) ;?></td>
			<?php ;}?>
			
			<td align="left"><?php echo (int)($v['DIST']) ;?></td>

			<td ><input type="text" name="cantPed[]" value="0" size="4" tabindex="1"  id="articulo" onChange="total()"></td>

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
