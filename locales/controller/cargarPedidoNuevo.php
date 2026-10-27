<?php

// Suprimir warnings y notices para evitar HTML en la respuesta JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Iniciar buffer de salida para poder limpiar cualquier output no deseado
ob_start();

// Sesión necesaria para obtener el nombre del local
session_start();

require_once $_SERVER['DOCUMENT_ROOT'].'/sistemas/class/conexion.php';
require_once __DIR__ . '/../class/pedido.php';
require_once __DIR__ . '/../class/emailPedido.php';
$cid = new Conexion();
$cid_central = $cid->conectar('central');

// Configurar archivo de log
$logFile = $_SERVER['DOCUMENT_ROOT'].'/LOGSFU/LOG.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    @mkdir($logDir, 0777, true);
}

$isDryRun = !empty($_POST['dry_run']);
$tsInicio = microtime(true);
$timing   = [];

// Validar que sea una petición POST con datos
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['matriz'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Petición inválida',
        'mensaje' => 'No se recibieron datos del pedido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar y obtener variables POST
$suc = isset($_POST['numsuc']) ? $_POST['numsuc'] : '';
$codClient = isset($_POST['codClient']) ? $_POST['codClient'] : '';
$t_ped = isset($_POST['tipo_pedido']) ? $_POST['tipo_pedido'] : '';
$depo = isset($_POST['depo']) ? $_POST['depo'] : '';
$talon_ped = 97;
set_time_limit(180); // cubre el loop de stock (N round-trips) + FU_PEDIDOS

// Validar que las variables requeridas no estén vacías
if (empty($suc) || empty($codClient)) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Datos incompletos',
        'mensaje' => 'Faltan datos necesarios para procesar el pedido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================================
// ANTI-DUPLICADO (backend): lock + idempotencia corta por suc/cliente/tipo/payload
// ============================================================================
$lockFp = null;
$lockKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $suc . '_' . $codClient . '_' . $t_ped);
$lockDir = $_SERVER['DOCUMENT_ROOT'] . '/LOGSFU/locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0777, true);
}
$lockFile = $lockDir . '/pedido_' . $lockKey . '.lock';
$idemFile = $lockDir . '/pedido_' . $lockKey . '.last.json';

$liberarLockPedido = function () use (&$lockFp) {
    if (is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        $lockFp = null;
    }
};
register_shutdown_function($liberarLockPedido);

if (!$isDryRun) {
    $lockFp = @fopen($lockFile, 'c+');
    if ($lockFp === false || !flock($lockFp, LOCK_EX | LOCK_NB)) {
        if (is_resource($lockFp)) {
            fclose($lockFp);
            $lockFp = null;
        }
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Pedido en proceso',
            'mensaje' => 'Ya hay un envío de pedido en curso para este cliente. Esperá unos segundos y revisá el Historial antes de reintentar.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Marca de lock en curso
    ftruncate($lockFp, 0);
    rewind($lockFp);
    fwrite($lockFp, json_encode([
        'pid' => getmypid(),
        'started_at' => date('c'),
        'suc' => $suc,
        'codClient' => $codClient,
        'tipo' => $t_ped,
    ]));
    fflush($lockFp);
}

$stringParaSql = '(';

// Decodificar matriz JSON
$matriz = json_decode($_POST['matriz'], true);

if (!is_array($matriz) || empty($matriz)) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Datos inválidos',
        'mensaje' => 'No se pudo procesar la matriz de artículos'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$cantidadPedida = 8; // Índice de cantidad en el array

// ============================================================================
// VALIDACIONES DE CRÉDITO Y STOCK
// ============================================================================

// Determinar si es cliente externo (franquicias, mayoristas, exterior — suc > 100)
$prefijoCodClient = substr($codClient, 0, 2);
$esFranquicia = ($prefijoCodClient === 'FR');
$esClienteExterno = (intval($suc) > 100);

$tsCredito = microtime(true);

// VALIDACIÓN 1: CRÉDITO (para todos los clientes externos con suc > 100)
if ($esClienteExterno) {
    try {
        // Paso 1: obtener GRUPO_EMPR y NRO_LISTA del cliente
        $stmtGrupo = sqlsrv_query($cid_central,
            "SELECT GRUPO_EMPR, NRO_LISTA FROM GVA14 WHERE COD_CLIENT = ?",
            [$codClient]);

        if ($stmtGrupo === false) {
            throw new Exception('Error al consultar datos del cliente');
        }

        $datosGrupo = sqlsrv_fetch_array($stmtGrupo, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtGrupo);

        if (!$datosGrupo) {
            throw new Exception('No se encontraron datos para el cliente');
        }

        $grupoEmpr   = trim($datosGrupo['GRUPO_EMPR'] ?? '');
        $numeroLista = $datosGrupo['NRO_LISTA'] ?? 1;

        // Si tiene grupo empresario se usa ese código, si no el propio COD_CLIENT
        $claveCredito = ($grupoEmpr !== '') ? $grupoEmpr : $codClient;

        // Paso 2: obtener TOTAL_DISPONIBLE del SP pasando la clave
        $stmtSP = sqlsrv_query($cid_central,
            "EXEC RO_PPP_FRANQ_PEDIDOS_GREAT @COD_CLIENTE = ?",
            [$claveCredito]);

        if ($stmtSP === false) {
            throw new Exception('Error al ejecutar SP de crédito');
        }

        $rowSP = sqlsrv_fetch_array($stmtSP, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtSP);

        if (!$rowSP) {
            // Cliente/grupo no encontrado en el SP — omitir restricción de crédito
            error_log("Crédito: $claveCredito no encontrado en SP, validación omitida\n", 3, $logFile);
            $totalDisponible = PHP_INT_MAX;
        } else {
            $totalDisponible = floatval($rowSP['TOTAL_DISPONIBLE']);
        }

        // Paso 3: monto del pedido — precios en lote (evita N queries a GVA17)
        $cantidadesPorArt = [];
        foreach ($matriz as $value) {
            $codigoArticulo = trim((string) ($value[1] ?? ''));
            $cantidadSolicitada = floatval($value[$cantidadPedida] ?? 0);
            if ($codigoArticulo === '' || $cantidadSolicitada <= 0) {
                continue;
            }
            $cantidadesPorArt[$codigoArticulo] = ($cantidadesPorArt[$codigoArticulo] ?? 0) + $cantidadSolicitada;
        }

        $preciosPorArt = [];
        $codigosPrecio = array_keys($cantidadesPorArt);
        $chunkSize = 400; // margen bajo el límite de 2100 params de SQL Server
        foreach (array_chunk($codigosPrecio, $chunkSize) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $paramsPrecio = array_merge([(int) $numeroLista], $chunk);
            $stmtPrecio = sqlsrv_query(
                $cid_central,
                "SELECT RTRIM(COD_ARTICU) AS COD_ARTICU, PRECIO
                 FROM GVA17
                 WHERE NRO_DE_LIS = ? AND COD_ARTICU IN ($placeholders)",
                $paramsPrecio
            );
            if ($stmtPrecio === false) {
                throw new Exception('Error al consultar precios del pedido');
            }
            while ($datosPrecio = sqlsrv_fetch_array($stmtPrecio, SQLSRV_FETCH_ASSOC)) {
                $preciosPorArt[trim((string) $datosPrecio['COD_ARTICU'])] = floatval($datosPrecio['PRECIO'] ?? 0);
            }
            sqlsrv_free_stmt($stmtPrecio);
        }

        $montoPedidoActual = 0;
        foreach ($cantidadesPorArt as $codigoArticulo => $cantidadSolicitada) {
            $montoPedidoActual += $cantidadSolicitada * ($preciosPorArt[$codigoArticulo] ?? 0);
        }

        // Paso 4: TOTAL_DISPONIBLE ya incluye cupo - deuda - pedidos pendientes del SP
        $creditoDisponible = $totalDisponible - $montoPedidoActual;

        // Log
        $logMsg = "Validación Crédito - Cliente:$codClient ClaveCredito:$claveCredito TotalDisponible:$totalDisponible PedidoActual:$montoPedidoActual CreditoFinal:$creditoDisponible\n";
        error_log($logMsg, 3, $logFile);

        if ($creditoDisponible < 0) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);

            $totalDisponibleFormateado   = '$' . number_format($totalDisponible, 2, ',', '.');
            $montoPedidoActualFormateado = '$' . number_format($montoPedidoActual, 2, ',', '.');

            echo json_encode([
                'success' => false,
                'error'   => 'Crédito insuficiente',
                'mensaje' => sprintf(
                    'Crédito insuficiente. Disponible: %s. El pedido requiere: %s',
                    $totalDisponibleFormateado,
                    $montoPedidoActualFormateado
                ),
                'detalles' => [
                    'credito_disponible' => round($totalDisponible, 2),
                    'pedido_monto'       => round($montoPedidoActual, 2)
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

    } catch (Exception $e) {
        error_log("Error validación crédito - Cliente:$codClient - " . $e->getMessage() . "\n", 3, $logFile);
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'Error en validación de crédito',
            'mensaje' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$timing['credito_ms'] = round((microtime(true) - $tsCredito) * 1000);

// VALIDACIÓN 2: STOCK DISPONIBLE (solo clientes externos — suc > 100)
// En lote (no N× EXEC SJ_STOCK_DISPONIBLE_VALIDACION): el SP unitario pegaba
// linked server ~3s/artículo y nginx cortaba a 60s (504). Misma base que carrito.
if ($esClienteExterno) {

$tsStock = microtime(true);

try {
    $solicitudes = [];
    foreach ($matriz as $value) {
        $cod = trim((string) ($value[1] ?? ''));
        $cant = floatval($value[$cantidadPedida] ?? 0);
        if ($cod === '' || $cant <= 0) {
            continue;
        }
        $solicitudes[$cod] = ($solicitudes[$cod] ?? 0) + $cant;
    }

    $tipoSpMap = ['GENERAL' => 1, 'ACCESORIOS' => 2, 'OUTLET' => 3];
    $tipoSp = $tipoSpMap[$t_ped] ?? 1;

    $pedidoHelper = new Pedido();
    $stockPorArt = $pedidoHelper->obtenerStockDisponibleValidacionLote(
        array_keys($solicitudes),
        intval($suc),
        $codClient,
        $tipoSp
    );

    $articulosSinStock = [];
    foreach ($solicitudes as $codigoArticulo => $cantidadSolicitada) {
        $stockDisponible = floatval($stockPorArt[$codigoArticulo] ?? 0);
        if ($cantidadSolicitada > $stockDisponible) {
            $articulosSinStock[] = [
                'articulo'            => $codigoArticulo,
                'stock_disponible'    => intval($stockDisponible),
                'cantidad_solicitada' => intval($cantidadSolicitada)
            ];
        }
    }

    $timing['stock_ms'] = round((microtime(true) - $tsStock) * 1000);
    $timing['stock_arts'] = count($solicitudes);
    $timing['stock_fuente'] = $stockPorArt['_fuente'] ?? 'batch_local';
    unset($stockPorArt['_fuente']);

    error_log(
        "Validación Stock LOTE - Cliente:$codClient Arts:" . count($solicitudes)
        . " ms:{$timing['stock_ms']} fuente:{$timing['stock_fuente']}\n",
        3,
        $logFile
    );

    if (!empty($articulosSinStock)) {
        $n = count($articulosSinStock);

        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);

        $respuesta = [
            'success' => false,
            'error'   => 'Stock insuficiente',
            'mensaje' => sprintf(
                '%d artículo%s sin stock suficiente. Modificá las cantidades.',
                $n,
                $n === 1 ? '' : 's'
            ),
            'articulos_sin_stock' => $articulosSinStock
        ];

        if ($n === 1) {
            $respuesta['detalles'] = $articulosSinStock[0];
        }

        error_log("Stock insuficiente - Cliente:$codClient Articulos:" . json_encode($articulosSinStock) . "\n", 3, $logFile);

        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
        exit;
    }

    error_log("Validaciones OK - Cliente:$codClient TotalArticulos:" . count($matriz) . " stock_ms:{$timing['stock_ms']}\n", 3, $logFile);

} catch (Exception $e) {
    error_log("Error validación stock - Cliente:$codClient - " . $e->getMessage() . "\n", 3, $logFile);

    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Error en validación de stock',
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

} else {
    // Local propio: saltar validación de stock para evitar timeout con pedidos grandes
    $timing['stock_ms'] = 0;
    error_log("Validación stock saltada - suc:$suc local propio, articulos:" . count($matriz) . "\n", 3, $logFile);
}

// ============================================================================
// FIN VALIDACIONES - Continuar con la construcción del pedido
// ============================================================================
$tsBuild = microtime(true);

try {
    $items = [];
    foreach ($matriz as $key => $value) {
        // Validar que existan los índices necesarios
        if (!isset($value[1]) || !isset($value[$cantidadPedida]) || !isset($value[9]) || !isset($value[10])) {
            throw new Exception('Datos de artículo incompletos en la posición ' . $key);
        }
        // Formato: "codigo","cantidad","rubro","stock"
        $items[] = '"'.$value[1].'","'.$value[$cantidadPedida].'","'.$value[9].'","'.$value[10].'"';
    }
    $stringParaSql .= implode(';', $items);
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'mensaje' => 'Error al procesar los artículos del pedido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stringParaSql .= ')';
$timing['build_ms'] = round((microtime(true) - $tsBuild) * 1000);

// Firma del payload para idempotencia (mismo carrito reenviado en ventana corta)
$payloadNorm = [];
foreach ($matriz as $value) {
    if (!isset($value[1], $value[$cantidadPedida])) {
        continue;
    }
    $cant = floatval($value[$cantidadPedida]);
    if ($cant <= 0) {
        continue;
    }
    $cod = (string) $value[1];
    $payloadNorm[$cod] = ($payloadNorm[$cod] ?? 0) + $cant;
}
ksort($payloadNorm);
$payloadHash = hash('sha256', $suc . '|' . $codClient . '|' . $t_ped . '|' . json_encode($payloadNorm));

// Si el mismo payload ya se grabó hace < 90s, devolver ese pedido (no re-ejecutar SP)
if (!$isDryRun && is_file($idemFile)) {
    $idemRaw = @file_get_contents($idemFile);
    $idemData = $idemRaw ? json_decode($idemRaw, true) : null;
    if (
        is_array($idemData)
        && !empty($idemData['payload_hash'])
        && !empty($idemData['nroPedido'])
        && !empty($idemData['ts'])
        && $idemData['payload_hash'] === $payloadHash
        && (time() - intval($idemData['ts'])) <= 90
    ) {
        error_log("Pedido duplicado evitado (idempotencia) - suc:$suc codClient:$codClient nro:" . $idemData['nroPedido'] . "\n", 3, $logFile);
        $liberarLockPedido();
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'nroPedido' => $idemData['nroPedido'],
            'mensaje' => 'Pedido ya registrado (reintento ignorado)',
            'duplicado_evitado' => true
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Pedido idéntico al último del mismo tipo → pedir confirmación (salvo forzar_duplicado)
$forzarDuplicado = !empty($_POST['forzar_duplicado']);
if (!$isDryRun && !$forzarDuplicado && !empty($payloadNorm)) {
    $leyendaPedido = 'PEDIDO ' . $t_ped;
    $sqlUltimo = "
        SELECT TOP 1 RTRIM(NRO_PEDIDO) AS NRO_PEDIDO, ID_GVA21, RTRIM(LEYENDA_1) AS LEYENDA_1
        FROM GVA21
        WHERE COD_CLIENT = ?
          AND TALON_PED IN (96, 97)
          AND (RTRIM(LEYENDA_1) = ? OR RTRIM(LEYENDA_1) = ?)
        ORDER BY ID_GVA21 DESC
    ";
    $stmtUltimo = sqlsrv_query($cid_central, $sqlUltimo, [$codClient, $leyendaPedido, $t_ped]);
    $ultimoPed = $stmtUltimo ? sqlsrv_fetch_array($stmtUltimo, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtUltimo) {
        sqlsrv_free_stmt($stmtUltimo);
    }

    if ($ultimoPed && !empty($ultimoPed['NRO_PEDIDO'])) {
        $nroUltimoRaw = $ultimoPed['NRO_PEDIDO'];
        $nroUltimo = trim((string) $nroUltimoRaw);
        $sqlDet = "
            SELECT RTRIM(COD_ARTICU) AS COD_ARTICU, CAST(SUM(CANT_PEDID) AS FLOAT) AS CANT
            FROM GVA03
            WHERE NRO_PEDIDO = ? AND TALON_PED IN (96, 97)
            GROUP BY RTRIM(COD_ARTICU)
        ";
        $stmtDet = sqlsrv_query($cid_central, $sqlDet, [$nroUltimoRaw]);
        $ultimoNorm = [];
        if ($stmtDet) {
            while ($det = sqlsrv_fetch_array($stmtDet, SQLSRV_FETCH_ASSOC)) {
                $cod = trim((string) $det['COD_ARTICU']);
                $cant = round(floatval($det['CANT']), 3);
                if ($cod !== '' && $cant > 0) {
                    $ultimoNorm[$cod] = ($ultimoNorm[$cod] ?? 0) + $cant;
                }
            }
            sqlsrv_free_stmt($stmtDet);
            ksort($ultimoNorm);
        }

        $payloadCmp = [];
        foreach ($payloadNorm as $cod => $cant) {
            $payloadCmp[trim((string) $cod)] = round(floatval($cant), 3);
        }
        ksort($payloadCmp);

        if ($ultimoNorm !== [] && $ultimoNorm === $payloadCmp) {
            error_log(
                "Pedido identico al ultimo - requiere confirmacion - suc:$suc codClient:$codClient nro:$nroUltimo tipo:$t_ped\n",
                3,
                $logFile
            );
            $liberarLockPedido();
            if (ob_get_length()) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo json_encode([
                'success' => false,
                'requiere_confirmacion_duplicado' => true,
                'nroPedidoUltimo' => $nroUltimo,
                'error' => 'Pedido similar al último',
                'mensaje' => "El pedido es idéntico al último cargado (Nro $nroUltimo). Revisá el Historial o confirmá si querés enviarlo de nuevo."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// Capturar ID máximo actual para detectar si el SP inserta un nuevo registro
$maxIdAntes = 0;
$stmtMaxId = sqlsrv_query($cid_central,
    "SELECT ISNULL(MAX(ID_GVA21), 0) AS MAX_ID FROM GVA21 WHERE COD_CLIENT = ?",
    [$codClient]);
if ($stmtMaxId) {
    $rowMaxId = sqlsrv_fetch_array($stmtMaxId, SQLSRV_FETCH_ASSOC);
    $maxIdAntes = intval($rowMaxId['MAX_ID'] ?? 0);
    sqlsrv_free_stmt($stmtMaxId);
}

// Preparar la consulta del stored procedure
$query = "EXEC FU_PEDIDOS $suc, '$codClient', '$t_ped', '$depo', $talon_ped, '$stringParaSql'";

// Dry run: salir aquí sin tocar FU_PEDIDOS, el carrito ni el email
if ($isDryRun) {
    $timing['total_ms'] = round((microtime(true) - $tsInicio) * 1000);
    error_log("DryRun - suc:$suc codClient:$codClient articulos:" . count($items) . " timing:" . json_encode($timing) . "\n", 3, $logFile);
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'dry_run'      => true,
        'articulos'    => count($items),
        'timing'       => $timing,
        'query_preview'=> substr($query, 0, 400),
        'validaciones' => 'OK',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Log básico del pedido
$message = "Pedido - suc:$suc codClient:$codClient fecha:" . date('Y-m-d H:i:s') . " query:" . substr($query, 0, 200) . PHP_EOL;
error_log($message, 3, $logFile);

try {
    set_time_limit(120);
    // Ejecutar el stored procedure
    $stmt = sqlsrv_query($cid_central, $query);

    // Verificar si hubo un error en la ejecución
    if ($stmt === false) {
        $sqlErrors = sqlsrv_errors();
        $errorMsg = '';
        
        if ($sqlErrors) {
            foreach ($sqlErrors as $error) {
                $errorMsg .= "SQLSTATE: " . $error['SQLSTATE'] . " - Code: " . $error['code'] . " - Message: " . $error['message'] . "\n";
            }
        } else {
            $errorMsg = "Error desconocido al ejecutar el stored procedure";
        }
        
        error_log("Error SP - suc:$suc codClient:$codClient - $errorMsg\n", 3, $logFile);
        throw new Exception("Error al registrar el pedido: " . substr($errorMsg, 0, 200));
    }

    // Drenar todos los result sets del SP (PRINT, SELECTs internos, etc.)
    // Sin esto, la conexión queda en estado "pendiente" y queries posteriores en $cid_central fallan
    do {
        $spErrs = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        if ($spErrs) {
            $spErrStr = '';
            foreach ($spErrs as $e) {
                $spErrStr .= "SQLSTATE:{$e['SQLSTATE']} Code:{$e['code']} Msg:{$e['message']} ";
            }
            error_log("SP result set error - suc:$suc codClient:$codClient - $spErrStr\n", 3, $logFile);
        }
    } while (sqlsrv_next_result($stmt) !== false);
    sqlsrv_free_stmt($stmt);

    // Verificar que el SP insertó un registro nuevo (ID mayor al capturado antes de la llamada)
    $sql = "SELECT TOP 1 NRO_PEDIDO, ID_GVA21
            FROM GVA21
            WHERE COD_CLIENT = ? AND ID_GVA21 > ?
            ORDER BY ID_GVA21 DESC";

    $row = null;
    for ($intento = 1; $intento <= 3; $intento++) {
        if ($intento > 1) {
            usleep(400000); // 400ms entre reintentos
        }
        $result = sqlsrv_query($cid_central, $sql, [$codClient, $maxIdAntes]);
        if ($result === false) {
            $sqlErrors = print_r(sqlsrv_errors(), true);
            error_log("Error consulta pedido (intento $intento) - $sqlErrors\n", 3, $logFile);
            continue;
        }
        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($result);
        if ($row && !empty($row['NRO_PEDIDO'])) {
            break;
        }
        error_log("Verificacion pedido sin resultado (intento $intento) - suc:$suc codClient:$codClient maxIdAntes:$maxIdAntes\n", 3, $logFile);
    }

    if (!$row || empty($row['NRO_PEDIDO'])) {
        error_log("SP fallo silencioso - suc:$suc codClient:$codClient t_ped:$t_ped maxIdAntes:$maxIdAntes\n", 3, $logFile);
        throw new Exception("El pedido no se registró en la base de datos. Por favor reintente o contacte a sistemas.");
    }

    // Limpiar carrito solo después de confirmar que el pedido quedó registrado.
    // Si algo falla antes de llegar aquí, el catch devuelve error y el carrito se preserva.
    $sqlLimpiar = "DELETE FROM SOF_CARRITO_PEDIDO WHERE NRO_SUC = ? AND COD_CLIENT = ? AND TIPO_PEDIDO = ?";
    $paramsLimpiar = [strval($suc), strval($codClient), strval($t_ped)];
    $stmtLimpiar = sqlsrv_query($cid_central, $sqlLimpiar, $paramsLimpiar);
    if ($stmtLimpiar) {
        sqlsrv_free_stmt($stmtLimpiar);
    }

    $nroPedido = trim((string) $row['NRO_PEDIDO']);
    
    if (is_object($row['NRO_PEDIDO'])) {
        if ($row['NRO_PEDIDO'] instanceof DateTime) {
            $nroPedido = $row['NRO_PEDIDO']->format('Y-m-d H:i:s');
        } else {
            $nroPedido = (string) $row['NRO_PEDIDO'];
        }
    }
    
    error_log("Pedido exitoso - Nro: $nroPedido\n", 3, $logFile);

    // Guardar resultado corto para reintentos idénticos (timeout / doble click)
    @file_put_contents($idemFile, json_encode([
        'payload_hash' => $payloadHash,
        'nroPedido' => $nroPedido,
        'ts' => time(),
        'suc' => $suc,
        'codClient' => $codClient,
        'tipo' => $t_ped,
    ]));

    // Enviar notificación por email (no bloqueante: el error no afecta la respuesta)
    try {
        $descLocal = isset($_SESSION['descLocal']) ? $_SESSION['descLocal'] : "Suc. {$suc}";
        $emailPedido = new EmailPedido();
        $emailPedido->notificarNuevoPedido($nroPedido, $descLocal, $codClient, $t_ped);
    } catch (\Throwable $emailErr) {
        error_log("EmailPedido: excepción no controlada — " . $emailErr->getMessage());
    }

    // Limpiar buffer y enviar respuesta exitosa
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'nroPedido' => $nroPedido,
        'mensaje' => 'Pedido registrado exitosamente'
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\Throwable $th) {
    $logFile = $_SERVER['DOCUMENT_ROOT'].'/LOGSFU/LOG.log';
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    
    $errorDetails = $th->getMessage() . " | File: " . $th->getFile() . " | Line: " . $th->getLine();
    error_log("Error pedido - suc:$suc codClient:$codClient - $errorDetails\n", 3, $logFile);
    
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $th->getMessage(),
        'mensaje' => 'Error al registrar el pedido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

?>
