<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location:../../../sistemas/login.php");
    exit;
}

include_once __DIR__.'/../class/pedido.php';

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location:../pedidos.php?tipo=1");
    exit;
}

// Número de tipo de pedido, usado para volver a la pantalla correcta.
$mapaTipoNum = ['GENERAL' => 1, 'ACCESORIOS' => 2, 'OUTLET' => 3, 'PACKAGING' => 4];
$tipoNum = $mapaTipoNum[$_SESSION['tipo_pedido']] ?? 1;

// Obtener datos del formulario
$suc = $_POST['suc'];
$codClient = $_POST['codClient'];
$tipo_pedido = $_POST['tipo_pedido'];
$depo = $_POST['depo'];
$codArt = $_POST['codArt'];
$cantPed = $_POST['cantPed'];
$rubro = $_POST['rubro'];
$stock = $_POST['stock'];

// Filtrar solo artículos con cantidad > 0
$pedidoFinal = array();
for($i = 0; $i < count($codArt); $i++) {
    if(intval($cantPed[$i]) > 0) {
        $pedidoFinal[] = array(
            'codigo' => $codArt[$i],
            'cantidad' => intval($cantPed[$i]),
            'rubro' => $rubro[$i],
            'stock' => $stock[$i]
        );
    }
}

// Validar que haya al menos un artículo
if(count($pedidoFinal) == 0) {
    $_SESSION['msg_error'] = 'Debe ingresar al menos un artículo con cantidad';
    header("Location:../pedidos.php?tipo=" . $tipoNum);
    exit;
}

// Instanciar clase Pedido
$pedidoObj = new Pedido();

// Verificar límite de crédito para mayoristas
if($_SESSION['numsuc'] > 100 && $_SESSION['numsuc'] != 201 && $_SESSION['numsuc'] != 202) {
    $limiteCredito = $_SESSION['cupoCredi'];
    // Aquí iría la lógica de verificación de límite de crédito
    // Por ahora solo hacemos validación básica
}

// Guardar el pedido
try {
    $resultado = $pedidoObj->grabarPedido(
        $suc,
        $codClient,
        $tipo_pedido,
        $depo,
        $pedidoFinal,
        $_SESSION['username']
    );
    
    if($resultado) {
        $_SESSION['msg_success'] = 'Pedido enviado correctamente. Total: ' . count($pedidoFinal) . ' artículos.';
        header("Location:../historial.php");
    } else {
        $_SESSION['msg_error'] = 'Error al procesar el pedido. Intente nuevamente.';
        header("Location:../pedidos.php?tipo=" . $tipoNum);
    }
} catch (Exception $e) {
    $_SESSION['msg_error'] = 'Error: ' . $e->getMessage();
    header("Location:../pedidos.php?tipo=" . $tipoNum);
}

exit;
?>
