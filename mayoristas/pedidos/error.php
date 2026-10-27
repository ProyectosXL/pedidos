<?php
session_start(); 

if(!isset($_SESSION['username'])){

	header("Location:login.php");

}else{

?>

	<h1>No ha ingresado ningun articulo</h1> 

	<script>setTimeout(function () {window.location.href= '../pedidos.php?cliente=<?php echo $_SESSION['codClient']; ?>';},1000);</script>

<?php 
}
?>