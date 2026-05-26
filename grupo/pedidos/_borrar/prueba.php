<?php


function backup(){
	$dsn = "1 - CENTRAL";
	$user = "sa";
	$pass = "Axoft1988";
	$cid = odbc_connect($dsn, $user, $pass);
	
	$sqlLimpia = "SET DATEFORMAT YMD TRUNCATE TABLE SJ_CORDOBA_BACKUP";
	odbc_exec($cid,$sqlLimpia)or die(exit("Error en odbc_exec"));	
	
	for($i=0;$i<count($_POST['codArt']);$i++){
		$codArt = $_POST['codArt'][$i];
		$local_812 = $_POST['cantPed_812'][$i];
		$local_813 = $_POST['cantPed_813'][$i];
		$local_814 = $_POST['cantPed_814'][$i];
		$local_815 = $_POST['cantPed_815'][$i];
		$local_816 = $_POST['cantPed_816'][$i];
		$local_876 = $_POST['cantPed_876'][$i];
		
		//echo $codArt.' - '.$local_812.' - '.$local_813.' - '.$local_814.' - '.$local_815.' - '.$local_816.' - '.$local_876.'<br>';
		$sqlInsert = "INSERT INTO SJ_CORDOBA_BACKUP VALUES ('$codArt', $local_812, $local_813, $local_814, $local_815, $local_816, $local_876)";
		odbc_exec($cid,$sqlInsert)or die(exit("Error en odbc_exec"));	
	}
}

backup();


?>