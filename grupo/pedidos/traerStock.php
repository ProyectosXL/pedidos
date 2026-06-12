<?php
require_once __DIR__ . '/../../class/GrupoSesion.php';
require_once __DIR__ . '/../../class/conexion.php';

GrupoSesion::requiereLogin('../../login.php');
GrupoSesion::requiereCargaGrupoCompletada('../controller/cargaPedido.php');

header('Content-Type: application/json; charset=UTF-8');

$title = $_GET['title'] ?? '';
$esAccesorios = stripos($title, 'Accesorios') !== false;
$query = $esAccesorios
    ? 'EXEC SJ_TIPO_PEDIDO_CORDOBA_2_bis'
    : 'EXEC SJ_TIPO_PEDIDO_CORDOBA_1bis';

$conexion = new Conexion();
$cid = $conexion->conectar(GrupoSesion::obtenerBaseDatos());

if ($cid === false) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar a la base central.']);
    exit;
}

$stmt = sqlsrv_query($cid, $query);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar stock.']);
    sqlsrv_close($cid);
    exit;
}

$result = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $result[] = $row;
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($cid);

echo json_encode($result);
