<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['numsuc']) || !isset($_SESSION['codClient'])) {
    echo json_encode(['success' => false, 'error' => 'Sin sesión']);
    exit;
}

require_once '../class/pedido.php';

$suc        = $_SESSION['numsuc'];
$codClient  = $_SESSION['codClient'];
$tipoPedido = $_SESSION['tipo_pedido'] ?? '';

// Liberar el lock de sesión inmediatamente — ya no necesitamos escribir en $_SESSION.
// Sin esto, cada fetch de auto-guardar bloquea session_start() de la siguiente página.
session_write_close();

$pedido = new Pedido();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'guardar':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $_POST;

        $codArt   = trim($data['codArt']   ?? '');
        $cantidad = intval($data['cantidad'] ?? 0);
        $rubro    = trim($data['rubro']     ?? '');
        $precio   = floatval($data['precio'] ?? 0);

        if (!$codArt) {
            echo json_encode(['success' => false, 'error' => 'Código inválido']);
            exit;
        }

        $ok = $pedido->guardarItemCarrito($suc, $codClient, $tipoPedido, $codArt, $cantidad, $rubro, $precio);
        $count = $pedido->contarItemsCarrito($suc, $codClient, $tipoPedido);
        echo json_encode(['success' => $ok, 'count' => $count]);
        break;

    case 'contar':
        $count = $pedido->contarItemsCarrito($suc, $codClient, $tipoPedido);
        echo json_encode(['success' => true, 'count' => $count]);
        break;

    case 'obtener':
        $items = $pedido->obtenerCarrito($suc, $codClient, $tipoPedido);
        echo json_encode(['success' => true, 'items' => $items]);
        break;

    case 'limpiar':
        $ok = $pedido->limpiarCarrito($suc, $codClient, $tipoPedido);
        echo json_encode(['success' => $ok]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción desconocida']);
}
