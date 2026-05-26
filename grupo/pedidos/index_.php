<?php 
session_start(); 
if(!isset($_SESSION['username'])){
	header("Location:login.php");
}else{
	
$permiso = $_SESSION['permisos'];
$_SESSION['cargaPedido'] = 1;
$local = $_SESSION['descLocal'];
?>
<!DOCTYPE HTML>

<html>
<head>

<meta charset="utf-8">
<title>INICIO</title>	

<link rel="shortcut icon" href="icono.jpg" />
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css" integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>

</head>
<body>	
</br>
<h1 align="center">Bienvenido <?php echo $local; ?></h1>

<div align="center" style="margin-top:50px"> 

<img src="logo.jpg">
</div>
</br>
<ul class="nav justify-content-center">
<div class="btn-group" role="group" aria-label="Basic example">
	<button type="button" class="btn btn-secondary" onclick="location.href='general.php'">Pedidos Generales</button>
	<button type="button" class="btn btn-secondary" onclick="location.href='accesorios.php'">Pedidos de Accesorios</button>
	<?php 
	if($_SESSION['numsuc']== 8 || $_SESSION['numsuc']== 56 || $_SESSION['numsuc']== 72 || $_SESSION['numsuc']== 76 || $_SESSION['numsuc']== 60)
	{
		?>
		<button type="button" class="btn btn-secondary" onclick="location.href='outlet.php'">Pedidos de Outlet</button>
		<?php
	}
	?>
	<button type="button" class="btn btn-secondary" onclick="location.href='historial.php'">Historial de pedidos</button>
</div>
</ul>
</br>

</ul>

</body>
<?php
}
?>

