<?php 
session_start(); 

if(!isset($_SESSION['username'])){

	header("Location:../../login.php");

}else{
	
		?>
		<!doctype html>
		<html>
		<head>
		<title>Carga de Pedidos</title>
		<?php include '../../../css/header.php'; ?>
		</head>
		<body>

		<?php

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
		
		SELECT * FROM
		(
		SELECT A.COD_ARTICU, DESCRIPCIO, RUBRO, CANT_STOCK, [812_STOCK], [812_VENDIDO], [813_STOCK], [813_VENDIDO], [814_STOCK], [814_VENDIDO],
		[815_STOCK], [815_VENDIDO], [816_STOCK], [816_VENDIDO], [876_STOCK], [876_VENDIDO], DISTRI, 
		ISNULL(FRBAUD, 0) FRBAUD, 
		ISNULL(FRORCE, 0) FRORCE, 
		ISNULL(FRORIG, 0) FRORIG, 
		ISNULL(FRORNC, 0) FRORNC, 
		ISNULL(FRORSJ, 0) FRORSJ, 
		ISNULL(FRPASJ, 0) FRPASJ
		FROM 
		(
						
		SELECT COD_ARTICU, DESCRIPCIO, RUBRO, CANT_STOCK, 
		CASE WHEN A.[812_STOCK] IS NULL THEN 0 ELSE A.[812_STOCK] END [812_STOCK], 
		CASE WHEN A.[812_VENDIDO] IS NULL THEN 0 ELSE A.[812_VENDIDO] END [812_VENDIDO], 
		CASE WHEN A.[813_STOCK] IS NULL THEN 0 ELSE A.[813_STOCK] END [813_STOCK], 
		CASE WHEN A.[813_VENDIDO] IS NULL THEN 0 ELSE A.[813_VENDIDO] END [813_VENDIDO], 
		CASE WHEN A.[814_STOCK] IS NULL THEN 0 ELSE A.[814_STOCK] END [814_STOCK], 
		CASE WHEN A.[814_VENDIDO] IS NULL THEN 0 ELSE A.[814_VENDIDO] END [814_VENDIDO], 
		CASE WHEN A.[815_STOCK] IS NULL THEN 0 ELSE A.[815_STOCK] END [815_STOCK], 
		CASE WHEN A.[815_VENDIDO] IS NULL THEN 0 ELSE A.[815_VENDIDO] END [815_VENDIDO], 
		CASE WHEN A.[816_STOCK] IS NULL THEN 0 ELSE A.[816_STOCK] END [816_STOCK], 
		CASE WHEN A.[816_VENDIDO] IS NULL THEN 0 ELSE A.[816_VENDIDO] END [816_VENDIDO], 
		CASE WHEN A.[876_STOCK] IS NULL THEN 0 ELSE A.[876_STOCK] END [876_STOCK], 
		CASE WHEN A.[876_VENDIDO] IS NULL THEN 0 ELSE A.[876_VENDIDO] END [876_VENDIDO], 
		CASE WHEN DIST IS NULL THEN 0 ELSE DIST END DISTRI
		FROM
		(	
			SELECT A.COD_ARTICU, 
			CASE WHEN A.COD_ARTICU IN (SELECT COD_ARTICU FROM MAESTRO_DESTINOS WHERE LIQUIDACION = 'SI') THEN DESCRIPCIO+' -- SALE! --' ELSE DESCRIPCIO END DESCRIPCIO, 
			B.RUBRO, CAST(CANT_STOCK AS INT)CANT_STOCK, A.[812_STOCK], A.[812_VENDIDO], A.[813_STOCK], A.[813_VENDIDO], 
			A.[814_STOCK], A.[814_VENDIDO], A.[815_STOCK], A.[815_VENDIDO], A.[816_STOCK], A.[816_VENDIDO], A.[876_STOCK], A.[876_VENDIDO], 		
			CAST(C.CANT_PEDID AS INT) DIST 
			FROM
			(
				SELECT COD_ARTICU, DESCRIPCIO, CASE WHEN CANT_STOCK < 0 THEN 0 ELSE CANT_STOCK END CANT_STOCK, A.[812_STOCK], A.[812_VENDIDO], A.[813_STOCK], A.[813_VENDIDO], 
				A.[814_STOCK], A.[814_VENDIDO], A.[815_STOCK], A.[815_VENDIDO], A.[816_STOCK], A.[816_VENDIDO], A.[876_STOCK], A.[876_VENDIDO]
				FROM
				(
					SELECT A.COD_ARTICU, DESCRIPCIO, CASE WHEN B.CANT_PEND IS NULL THEN A.CANT_STOCK ELSE CANT_STOCK-B.CANT_PEND END CANT_STOCK, 
					A.[812_STOCK], A.[812_VENDIDO], A.[813_STOCK], A.[813_VENDIDO], A.[814_STOCK], A.[814_VENDIDO], A.[815_STOCK], A.[815_VENDIDO],
					A.[816_STOCK], A.[816_VENDIDO], A.[876_STOCK], A.[876_VENDIDO]
					FROM
					(
						SELECT A.COD_ARTICU, A.DESCRIPCIO, CAST(A.CANT_STOCK AS INT) CANT_STOCK, B.[812_STOCK], B.[812_VENDIDO], B.[813_STOCK], B.[813_VENDIDO], B.[814_STOCK], B.[814_VENDIDO],
						B.[815_STOCK], B.[815_VENDIDO], B.[816_STOCK], B.[816_VENDIDO], B.[876_STOCK], B.[876_VENDIDO]
						FROM
						(SELECT A.COD_ARTICU, C.DESCRIPCIO, A.CANT_STOCK FROM STA19 A INNER JOIN STA11 C ON A.COD_ARTICU = C.COD_ARTICU WHERE A.COD_DEPOSI = '01' 
						AND (A.COD_ARTICU LIKE 'X%' OR A.COD_ARTICU = 'OHGIFT') AND A.CANT_STOCK > 0 )A LEFT JOIN SOF_PEDIDOS_CARGADOS_LOPEZ B 
						ON A.COD_ARTICU = B.COD_ARTICU COLLATE Modern_Spanish_CI_AI 
					)A
					LEFT JOIN 
						(SELECT COD_ARTICU, SUM(CANT_A_DES)CANT_PEND FROM GVA03 A INNER JOIN GVA21 B ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
						WHERE B.ESTADO = 2 AND B.FECHA_PEDI > (GETDATE()-15) AND B.COD_SUCURS = '01' GROUP BY COD_ARTICU HAVING SUM(CANT_A_DES) > 0)B
					ON A.COD_ARTICU = B.COD_ARTICU
				)A
			)A
			INNER JOIN 
				(SELECT A.CODE, B.DESCRIP RUBRO FROM STA11ITC A INNER JOIN STA11FLD B ON A.IDFOLDER = B.IDFOLDER WHERE B.DESCRIP NOT LIKE '[_]%'
				AND B.IDFOLDER IN (6, 8, 9, 12, 15))B
			ON A.COD_ARTICU = B.CODE
			LEFT JOIN
				(SELECT B.COD_ARTICU, SUM(B.CANT_PEDID) CANT_PEDID FROM GVA21 A INNER JOIN GVA03 B ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
				WHERE A.ESTADO IN (2, 3, 4) AND A.FECHA_PEDI >= GETDATE()-15 AND A.COD_CLIENT IN ('FRBAUD', 'FRORCE','FRORIG','FRORNC','FRORSJ','FRPASJ') 
				AND COD_SUCURS = '03' GROUP BY B.COD_ARTICU)C
			ON A.COD_ARTICU = C.COD_ARTICU
			WHERE CANT_STOCK > 0
			AND A.COD_ARTICU NOT IN
				(SELECT COD_ARTICU FROM MAESTRO_DESTINOS WHERE DESTINO LIKE '%DISCONTINUO%' OR DESTINO LIKE '%GUARDAR%')
		)A
						
		)A
		LEFT JOIN SJ_CORDOBA_BACKUP B
		ON A.COD_ARTICU = B.COD_ARTICU
		)A
		ORDER BY 3, 1
		
		";

		$result=odbc_exec($cid,$sql)or die(exit("Error en odbc_exec"));

		?>


		<form method="POST" action="cargarPedidoNuevoCordoba.php" onkeypress = "return pulsar(event)">
		<div style="width:100%">
		  
		<table class="table table-striped table-fh table-12c" id="id_tabla">
		
		<thead>
			<tr style="font-size:smaller">
				<th style="width: 12%">CODIGO</th>
				<th style="width: 20%">DESCRIPCION</th>
				<th style="width: 12%">RUBRO</th>				
				<th style="width: 2%"></th>	
				<th style="width: 8%">STOCK</th>				
				<th style="width: 8%">BAULERA</th>				
				<th style="width: 2%"></th>	
				<th style="width: 8%">VELEZ</th>				
				<th style="width: 8%">DINO</th>				
				<th style="width: 8%">NVO<br>CENTRO</th>				
				<th style="width: 8%">SAN<br>JUAN</th>				
				<th style="width: 8%">JOCKEY</th>				
				<th style="width: 4%"><input type="submit" value="Enviar" class="btn btn-primary btn-sm"></th>
			</tr>
		</thead>
		
		<tbody>
		<?php


		while($v=odbc_fetch_array($result)){

			?>

			<div >
			
				
				
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

				<td style="width: 12% ; border-left: 1px solid black"><?php echo $v['COD_ARTICU'] ;?>  </td>	
				<td style="width: 1%"><input name="codArt[]" value="<?php echo $v['COD_ARTICU'] ;?>"  hidden>  </td>					
				<td style="width: 20%"><?php echo $v['DESCRIPCIO'] ;?>  </td>				
				<td style="width: 12%"><?php echo $v['RUBRO'] ;?>  </td>
				<td style="width: 1%"><input name="rubro[]" value="<?php echo $v['RUBRO'] ;?>"  hidden>  </td>	
				<td style="width: 3%"><?php echo (int)($v['CANT_STOCK']) ;?>  </td>
				<td style="width: 1% ; border-left: 1px solid black"><input name="stock[]" value="<?php echo $v['CANT_STOCK'] ;?>"  hidden>  </td>	
				
				<td style="width: 2%"><?php echo (int)($v['812_STOCK']) ;?>  </td>
				<td style="width: 4%"><input type="text" name="cantPed_812[]" value="<?php echo $v['FRBAUD'] ;?>" size="1" tabindex="1" id="articulo" onChange="total();verifica()">  </td>
				
				<td style="width: 2% ; border-left: 1px solid black"><?php echo (int)($v['813_STOCK']) ;?>  </td>				
				<td style="width: 2%"><?php echo (int)($v['813_VENDIDO']) ;?>  </td>
				<td style="width: 4%"><input type="text" name="cantPed_813[]" value="<?php echo $v['FRORCE'] ;?>" size="1" tabindex="1" id="articulo" onChange="total();verifica()">  </td>
				
				<td style="width: 2% ; border-left: 1px solid black"><?php echo (int)($v['814_STOCK']) ;?>  </td>
				<td style="width: 2%"><?php echo (int)($v['814_VENDIDO']) ;?>  </td>
				<td style="width: 4%"><input type="text" name="cantPed_814[]" value="<?php echo $v['FRORIG'] ;?>" size="1" tabindex="1" id="articulo" onChange="total();verifica()">  </td>
				
				<td style="width: 2% ; border-left: 1px solid black"><?php echo (int)($v['815_STOCK']) ;?>  </td>
				<td style="width: 2%"><?php echo (int)($v['815_VENDIDO']) ;?>  </td>
				<td style="width: 4%"><input type="text" name="cantPed_815[]" value="<?php echo $v['FRORNC'] ;?>" size="1" tabindex="1" id="articulo" onChange="total();verifica()">  </td>
				
				<td style="width: 2% ; border-left: 1px solid black"><?php echo (int)($v['816_STOCK']) ;?>  </td>				
				<td style="width: 2%"><?php echo (int)($v['816_VENDIDO']) ;?>  </td>
				<td style="width: 4%"><input type="text" name="cantPed_816[]" value="<?php echo $v['FRORSJ'] ;?>" size="1" tabindex="1" id="articulo" onChange="total();verifica()">  </td>
				
				<td style="width: 2% ; border-left: 1px solid black"><?php echo (int)($v['876_STOCK']) ;?>  </td>				
				<td style="width: 2%"><?php echo (int)($v['876_VENDIDO']) ;?>  </td>
				<td style="width: 4%"><input type="text" name="cantPed_876[]" value="<?php echo $v['FRPASJ'] ;?>" size="1" tabindex="1" id="articulo" onChange="total();verifica()">  </td>
				
				<td style="width: 4%"></td>			
							
				
				
				</tr>

			<?php

		}

		?>



		</table>
		
		</form>

		</div>
		
		
				
		</body>
		</html>

		<?php

	
}
?>

