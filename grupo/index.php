<?php 

session_start(); 
if(!isset($_SESSION['username'])){
	header("Location:../login.php");
}else{


$local = $_SESSION['descLocal'];


$_SESSION['numsuc'] = isset($_SESSION['numsuc']) ? $_SESSION['numsuc'] : 100;
$_SESSION['codClient'] = 'Cordoba';
$codClient = 'Cordoba';
$habPedidos =  '00';
$deposi = '00';
$dashboard = isset($_SESSION['dashboard']) ? $_SESSION['dashboard'] : '';


$powerbiUrl = isset($_SESSION['POWERBI_URL']) && !empty($_SESSION['POWERBI_URL']) 
    ? $_SESSION['POWERBI_URL'] 
    : 'https://app.powerbi.com/view?r=eyJrIjoiY2U5MDc4NzEtMTVkYy00YTNmLWJmNjYtMWRiZjBhZTM1OGI3IiwidCI6IjQ0Y2E2MmNkLTY4MjItNDZkNC05NTUxLTEzNDQ5N2ZmM2VjMiIsImMiOjR9'; // Fallback
$permiteMayoristas = isset($_SESSION['PERMITE_MAYORISTAS']) ? $_SESSION['PERMITE_MAYORISTAS'] : true; // Por defecto true para Córdoba



?>
<!DOCTYPE HTML>
<html charset="UTF-8">

<head>
<title>XL Extralarge - Inicio</title>	
<meta charset="UTF-8"></meta>
<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">

<?php include '../assets/css/header_simple.php'; ?>
<?php include_once __DIR__.'/../assets/css/fontawesome/css.php';?>
<link rel="stylesheet" href="../ajustes/css/msj-seincomp.css">
<link rel="stylesheet" href="../css/index.css"> 
<link rel="stylesheet" href="https://unpkg.com/bootstrap-submenu@3.0.1/dist/css/bootstrap-submenu.css">
<style>

	.dropdown-submenu {
	position: relative;
	}

	.dropdown-submenu a::after {
	transform: rotate(-90deg);
	position: absolute;
	right: 6px;
	top: .8em;
	}

	.dropdown-submenu .dropdown-menu {
	top: 0;
	left: 100%;
	margin-left: .1rem;
	margin-right: .1rem;
	}

</style>

<title>INICIO</title>

</head>
<body>	
<div class="container">
	<?php	
	include_once '../Controlador/nav_menu.php';
	?>

	<!-- CARTEL DE BIENVENIDA -->

	<div class="form-group" style="margin-top: 0.5rem;">
			<div class="col-">
				<div class="mb-1">
					<div class="row" style="display: flex; justify-content: center;">
						<div class="col-"><h2>Bienvenido Original Products 1966 srl</h2></div>
					</div>
					<div class="row" style="display: flex; justify-content: center;">
						<div class="col-"><h2 class="text-secondary"><?php echo $local; ?></h2></div>
					</div>
				</div>
				<?php
			
				?>
			</div>

			<div class="col-" style="margin-top: 1rem; display: flex; justify-content: center;"> 
				<img src="../Controlador/logo.jpg" style="height: 150px; width: 200px">
			</div>
		</div>
	</div>

	<?php include_once __DIR__ . '/../assets/css/fontawesome/js.php'; ?>
	
	<!-- Bootstrap JS -->
	<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
	
	<script>
	$(document).ready(function() {
		// Inicializar dropdowns de Bootstrap
		$('.dropdown-toggle').dropdown();
		
		// Script para dropdown-submenu
		$('.dropdown-menu a.dropdown-toggle').on('click', function(e) {
		  if (!$(this).next().hasClass('show')) {
		    $(this).parents('.dropdown-menu').first().find('.show').removeClass("show");
		  }
		  var $subMenu = $(this).next(".dropdown-menu");
		  $subMenu.toggleClass('show');


		  $(this).parents('li.nav-item.dropdown.show').on('hidden.bs.dropdown', function(e) {
		    $('.dropdown-submenu .show').removeClass("show");
		  });


		  return false;
		});
	});
	</script>
</body>

<?php
}
?>

