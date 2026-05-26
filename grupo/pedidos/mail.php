<?php
session_start(); 
if(!isset($_SESSION['username'])){
	header("Location:login.php");
}else{
$mails = array
(
//'expedicion@lakerscorp.com.ar', 'admlogistica@lakerscorp.com.ar', 'logistica@lakerscorp.com.ar', 'controldelogistica@lakerscorp.com.ar', 
'sistemas@xl.com.ar'
);

for($f=0; $f < count($mails); $f++){
	
	$email = 'sistemas@xl.com.ar';

	$header = 'From: ' . $email . " \r\n";
	$header .= "X-Mailer: PHP/" . phpversion() . " \r\n";
	$header .= "Mime-Version: 1.0 \r\n";
	$header .= "Content-Type: text/html ; charset=utf-8";

	$mensaje1 = "
	Nuevo pedido cargado.
	</br></br>
	Se ha cargado el pedido $t_ped Nro: $numPed del cliente $codClient
	</br></br></br></br>
	-- Sistemas XL --
	"
	;


	$para = $mails[$f];
	$asunto = 'NUEVO PEDIDO DE LOCALES - '.$codClient.' - '.$numPed;

	mail($para, $asunto, utf8_decode($mensaje1), $header);
	
	
	echo 'Mensaje enviado a '.$mails[$f].'</br>'	;
}

}


?>