<?php
require_once __DIR__ . '/../../class/GrupoSesion.php';
require_once __DIR__ . '/../../class/CrearPedido.php';
require_once __DIR__ . '/../../class/sucursal.php';

GrupoSesion::requiereLogin('../../login.php');
GrupoSesion::requiereCargaGrupoCompletada('../controller/cargaPedido.php');

// Log de depuración: si este archivo se ejecutó, el POST llegó.
(function () {
    $dir = __DIR__ . '/../../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $keysCant = [];
    foreach ($_POST as $k => $v) {
        if (strpos((string) $k, 'cantPed_') === 0) {
            $keysCant[] = $k . '(' . (is_array($v) ? count($v) : 1) . ')';
        }
    }
    @file_put_contents(
        $dir . '/envio-pedido.log',
        json_encode([
            'fecha' => date('c'),
            'usuario' => $_SESSION['username'] ?? null,
            'tipo_pedido' => $_SESSION['tipo_pedido'] ?? null,
            'post_keys' => count($_POST),
            'codArt' => isset($_POST['codArt']) && is_array($_POST['codArt']) ? count($_POST['codArt']) : 0,
            'cantPed' => $keysCant,
            'max_input_vars' => (int) ini_get('max_input_vars'),
            'warning_max_input' => !empty($_SERVER['PHP_SELF']) && count($_POST) >= (int) ini_get('max_input_vars'),
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
})();

/**
 * Muestra aviso visible y luego redirige.
 */
function mostrarAvisoPedidoYRedirigir(string $tipo, string $titulo, string $texto, string $destino = '../index.php'): void
{
    $icon = $tipo === 'success' ? 'success' : 'error';
    $tituloJs = json_encode($titulo, JSON_UNESCAPED_UNICODE);
    $textoJs = json_encode($texto, JSON_UNESCAPED_UNICODE);
    $destinoJs = json_encode($destino, JSON_UNESCAPED_UNICODE);
    $iconJs = json_encode($icon, JSON_UNESCAPED_UNICODE);

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>'
        . htmlspecialchars($titulo)
        . '</title>'
        . '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
        . '</head><body style="background:#f5f7fa;">'
        . '<script>Swal.fire({icon:' . $iconJs . ',title:' . $tituloJs . ',html:' . $textoJs
        . ',confirmButtonText:"Aceptar",allowOutsideClick:false}).then(function(){window.location.href='
        . $destinoJs . ';});</script></body></html>';
    exit;
}

$comprometerStockPath = $_SERVER['DOCUMENT_ROOT'] . '/Controlador/comprometer_stock.php';
if (is_file($comprometerStockPath)) {
    require_once $comprometerStockPath;
}

$sucursalObj = new Sucursal();
$db = GrupoSesion::obtenerBaseDatos();
$resolucion = $sucursalObj->construirSucursalesActivasParaPedido($db);
$sucursalesPedido = $resolucion['info'];

$_SESSION['omitir_rechequeo_index'] = true;

if (empty($sucursalesPedido)) {
    mostrarAvisoPedidoYRedirigir(
        'error',
        'No se pudo enviar',
        'No hay sucursales activas para procesar el pedido.'
    );
}

foreach ($sucursalesPedido as $numSuc => $info) {
    $campo = 'cantPed_' . $numSuc;
    if (!CrearPedido::validarCantidadesEnteras($_POST[$campo] ?? [])) {
        mostrarAvisoPedidoYRedirigir(
            'error',
            'Cantidades inválidas',
            'Hay cantidades inválidas en la sucursal ' . htmlspecialchars((string) $numSuc) . '. Use solo enteros mayores o iguales a cero.',
            'general.php'
        );
    }
}

$tipo = $_SESSION['tipo_pedido'] ?? CrearPedido::TIPO_GENERAL;
$depo = $_SESSION['depo'] ?? '01';

$pedidos = CrearPedido::normalizarDesdePostGrupo($_POST, $sucursalesPedido);

if (empty($pedidos)) {
    mostrarAvisoPedidoYRedirigir(
        'error',
        'Pedido vacío',
        'No ingresó cantidades mayores a cero en ninguna sucursal.',
        'general.php'
    );
}

$crearPedido = new CrearPedido($db);
$resultado = $crearPedido->crearMultiplesGrupo($pedidos, [
    'tipo'                => $tipo,
    'depo'                => $depo,
    'comprometer_stock'   => function_exists('comp_stock'),
]);

if (!$resultado['success']) {
    $msg = $resultado['mensaje'] ?? $resultado['error'] ?? 'No se pudo crear el pedido.';
    $_SESSION['pedido_grupo_mensaje'] = ['tipo' => 'error', 'texto' => $msg];
    mostrarAvisoPedidoYRedirigir('error', 'Error al enviar el pedido', htmlspecialchars((string) $msg));
}

$detalleHtml = '<p><strong>Pedido enviado correctamente</strong></p>';
$detalleHtml .= '<p>Se generaron pedidos en <strong>' . (int) ($resultado['procesados'] ?? 0) . '</strong> sucursal/es.</p>';

$items = [];
foreach (($resultado['resultados'] ?? []) as $r) {
    if (!empty($r['success']) && !empty($r['nro_pedido'])) {
        $items[] = '<li>' . htmlspecialchars((string) ($r['cod_client'] ?? 'Sucursal'))
            . ': N° <strong>' . htmlspecialchars((string) $r['nro_pedido']) . '</strong></li>';
    }
}
if (!empty($items)) {
    $detalleHtml .= '<ul style="text-align:left;max-height:220px;overflow:auto;">' . implode('', $items) . '</ul>';
}

$_SESSION['pedido_grupo_mensaje'] = [
    'tipo' => 'success',
    'texto' => 'Pedido enviado correctamente (' . (int) ($resultado['procesados'] ?? 0) . ' sucursal/es).',
];

mostrarAvisoPedidoYRedirigir('success', 'Pedido enviado', $detalleHtml);
