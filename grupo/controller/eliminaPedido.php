<?php
session_start();

if (!isset($_SESSION['username'])) {
	header("Location:../login.php");
	exit;
}

if ($_SESSION['nuevoPedido'] == 1 && $_SESSION['cargaPedido'] == 1) {
	// Conexión usando la clase Conexion (misma forma que el resto de Córdoba)
	require_once __DIR__ . '/../../class/conexion.php';
	$conexion = new Conexion();
	$cid = $conexion->conectar('central');

	if ($cid === false) {
		// Error de conexión
		die("No se pudo conectar a la base de datos central.");
	}

	echo '<h3 align="center">Aguarde un momento por favor</h3>';

	$sql = "TRUNCATE TABLE SOF_PEDIDOS_CARGA_LOPEZ";

	$stmt = sqlsrv_query($cid, $sql);
	if ($stmt === false) {
		die("Error al ejecutar TRUNCATE: " . print_r(sqlsrv_errors(), true));
	}
} else {
	header("Location:../login.php");
	exit;
}
?>

<script>
	setTimeout(function () {
		window.location.href = '../eligeSucCordoba.php';
	}, 1000);
</script>
