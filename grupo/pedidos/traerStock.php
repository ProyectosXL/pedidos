<?php
require_once __DIR__ . '/../../class/GrupoSesion.php';
require_once __DIR__ . '/../../class/conexion.php';
require_once __DIR__ . '/../../class/pedido.php';

GrupoSesion::requiereLogin('../../login.php');
GrupoSesion::requiereCargaGrupoCompletada('../controller/cargaPedido.php');

header('Content-Type: application/json; charset=UTF-8');

$title = $_GET['title'] ?? '';
$esAccesorios = stripos($title, 'Accesorios') !== false;

$db = GrupoSesion::obtenerBaseDatos();
$pedido = new Pedido();

// Misma fuente que general.php / accesorios.php (SP + next_result + enriquecimimage.pngiento)
$result = $esAccesorios
    ? $pedido->listarPedidoCordobaAccesorios($db)
    : $pedido->listarPedidoCordoba($db);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
