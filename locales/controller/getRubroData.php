<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__.'/class/pedido.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['username'])) {
        throw new Exception('No autorizado');
    }

    if (!isset($_GET['rubro'])) {
        throw new Exception('Falta el parámetro rubro');
    }

    $rubro = trim($_GET['rubro']);
    if (empty($rubro)) {
        throw new Exception('El rubro no puede estar vacío');
    }

    $pedido     = new Pedido();
    $codClient  = $_SESSION['codClient'];
    $usuarioUy  = $_SESSION['usuarioUy'];
    $suc        = isset($_SESSION['numsuc']) ? $_SESSION['numsuc'] : '53';

    $pedidosRubro = $pedido->traerHistorialRubro($codClient, $usuarioUy);
    $ventasRubro  = $pedido->traerVentasRubro($codClient, $suc, $usuarioUy);

    $rubroData = [];

    foreach ($pedidosRubro as $p) {
        if (!isset($p['FECHA'], $p['RUBRO'], $p['CANT'])) {
            continue;
        }
        if (trim($p['RUBRO']) !== $rubro) {
            continue;
        }

        $fecha = $p['FECHA'];
        if (!($fecha instanceof DateTime)) {
            continue;
        }

        $yearWeek = $fecha->format('Y-W');
        if (!isset($rubroData[$yearWeek])) {
            $rubroData[$yearWeek] = [
                'semana'  => "Sem " . $fecha->format("W") . "/" . $fecha->format("Y"),
                'pedidos' => 0,
                'ventas'  => 0
            ];
        }
        $rubroData[$yearWeek]['pedidos'] += (float)$p['CANT'];
    }

    foreach ($ventasRubro as $v) {
        if (!isset($v['FECHA'], $v['RUBRO'], $v['CANT_VENTAS'])) {
            continue;
        }
        if (trim($v['RUBRO']) !== $rubro) {
            continue;
        }

        $fecha = $v['FECHA'];
        if (!($fecha instanceof DateTime)) {
            $fecha = new DateTime($fecha);
        }

        $yearWeek = $fecha->format('Y-W');
        if (!isset($rubroData[$yearWeek])) {
            $rubroData[$yearWeek] = [
                'semana'  => "Sem " . $fecha->format("W") . "/" . $fecha->format("Y"),
                'pedidos' => 0,
                'ventas'  => 0
            ];
        }
        $rubroData[$yearWeek]['ventas'] += (float)$v['CANT_VENTAS'];
    }

    ksort($rubroData);

    $response = ['labels' => [], 'pedidosData' => [], 'ventasData' => []];
    foreach ($rubroData as $data) {
        $response['labels'][]      = $data['semana'];
        $response['pedidosData'][] = $data['pedidos'];
        $response['ventasData'][]  = $data['ventas'];
    }

    echo json_encode($response);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => $e->getMessage(),
        'details' => 'Error procesando la solicitud'
    ]);
    exit;
}
