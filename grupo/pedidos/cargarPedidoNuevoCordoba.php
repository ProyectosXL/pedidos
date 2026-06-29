<?php
require_once __DIR__ . '/../../class/GrupoSesion.php';
require_once __DIR__ . '/../../class/CrearPedido.php';
require_once __DIR__ . '/../../class/sucursal.php';

GrupoSesion::requiereLogin('../../login.php');
GrupoSesion::requiereCargaGrupoCompletada('../controller/cargaPedido.php');

$comprometerStockPath = $_SERVER['DOCUMENT_ROOT'] . '/Controlador/comprometer_stock.php';
if (is_file($comprometerStockPath)) {
    require_once $comprometerStockPath;
}

$sucursalObj = new Sucursal();
$db = GrupoSesion::obtenerBaseDatos();
$resolucion = $sucursalObj->construirSucursalesActivasParaPedido($db);
$sucursalesPedido = $resolucion['info'];

if (empty($sucursalesPedido)) {
    die('No hay sucursales activas para procesar el pedido.');
}

foreach ($sucursalesPedido as $numSuc => $info) {
    $campo = 'cantPed_' . $numSuc;
    if (!CrearPedido::validarCantidadesEnteras($_POST[$campo] ?? [])) {
        die('Error: cantidades inválidas en sucursal ' . htmlspecialchars((string) $numSuc) . '. Use solo enteros >= 0.');
    }
}

$tipo = $_SESSION['tipo_pedido'] ?? CrearPedido::TIPO_GENERAL;
$depo = $_SESSION['depo'] ?? '01';

$pedidos = CrearPedido::normalizarDesdePostGrupo($_POST, $sucursalesPedido);

$crearPedido = new CrearPedido($db);
$resultado = $crearPedido->crearMultiplesGrupo($pedidos, [
    'tipo'                => $tipo,
    'depo'                => $depo,
    'comprometer_stock'   => function_exists('comp_stock'),
]);

if (!$resultado['success']) {
    header('Location: error.php');
    exit;
}

echo '<script>setTimeout(function () { window.location.href = "../index.php"; }, 1000);</script>';
