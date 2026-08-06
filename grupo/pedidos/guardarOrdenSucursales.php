<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../class/GrupoSesion.php';
GrupoSesion::iniciar();

if (empty($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'No hay una sesión activa.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

if (empty($_SESSION['sucursales_activas'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Falta completar la carga inicial de sucursales.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || empty($payload['accion'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Solicitud inválida.']);
    exit;
}

require_once __DIR__ . '/../../class/sucursal.php';
$sucursalObj = new Sucursal();

$accion = (string) $payload['accion'];

if ($accion === 'restaurar') {
    if ($sucursalObj->restaurarPreferenciasPorDefecto()) {
        echo json_encode(['ok' => true, 'mensaje' => 'Se restauraron los valores por defecto.']);
    } else {
        error_log('guardarOrdenSucursales.php: fallo al restaurar preferencias por defecto para usuario ' . $_SESSION['username']);
        echo json_encode(['ok' => false, 'mensaje' => 'No se pudieron restaurar los valores por defecto.']);
    }
    exit;
}

if ($accion !== 'guardar') {
    echo json_encode(['ok' => false, 'mensaje' => 'Acción no reconocida.']);
    exit;
}

$sucursalesRecibidas = isset($payload['sucursales']) && is_array($payload['sucursales']) ? $payload['sucursales'] : null;
if ($sucursalesRecibidas === null || empty($sucursalesRecibidas)) {
    echo json_encode(['ok' => false, 'mensaje' => 'No se recibió un listado de sucursales válido.']);
    exit;
}

$items = [];
$numerosRecibidos = [];
foreach ($sucursalesRecibidas as $item) {
    if (!is_array($item) || !isset($item['nro'])) {
        echo json_encode(['ok' => false, 'mensaje' => 'Solicitud inválida.']);
        exit;
    }
    $nro = (int) $item['nro'];
    $items[] = [
        'nro'   => $nro,
        'alias' => isset($item['alias']) ? (string) $item['alias'] : '',
    ];
    $numerosRecibidos[] = $nro;
}

// Validación anti-manipulación: el conjunto recibido tiene que ser EXACTAMENTE
// el de las sucursales activas del usuario — sin faltantes, sobrantes ni
// duplicados. Nunca confiar en la lista que llega del cliente.
$resolucion = $sucursalObj->construirSucursalesActivasParaPedido(GrupoSesion::obtenerBaseDatos());
$sucursalesActivas = array_map('intval', array_keys($resolucion['info'] ?? []));

$sinDuplicados = count($numerosRecibidos) === count(array_unique($numerosRecibidos));

$numerosOrdenados = $numerosRecibidos;
sort($numerosOrdenados);
$activasOrdenadas = $sucursalesActivas;
sort($activasOrdenadas);

if (!$sinDuplicados || $numerosOrdenados !== $activasOrdenadas) {
    error_log('guardarOrdenSucursales.php: intento de guardar sucursales que no coinciden con las activas del usuario ' . $_SESSION['username']);
    echo json_encode(['ok' => false, 'mensaje' => 'El listado recibido no coincide con las sucursales activas del usuario.']);
    exit;
}

if ($sucursalObj->guardarPreferenciasSucursales($items)) {
    echo json_encode(['ok' => true, 'mensaje' => 'Preferencias de sucursales guardadas.']);
} else {
    error_log('guardarOrdenSucursales.php: fallo al guardar preferencias para usuario ' . $_SESSION['username']);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudieron guardar las preferencias de sucursales.']);
}
