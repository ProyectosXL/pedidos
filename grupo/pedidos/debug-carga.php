<?php
/**
 * Diagnóstico: stock/vendido por sucursal en general.php
 * Uso: /pedidos/grupo/pedidos/debug-carga.php
 *      /pedidos/grupo/pedidos/debug-carga.php?tipo=accesorios
 */
require_once __DIR__ . '/../../class/GrupoSesion.php';
require_once __DIR__ . '/../../class/conexion.php';
require_once __DIR__ . '/../../class/sucursal.php';
require_once __DIR__ . '/../../class/pedido.php';

GrupoSesion::requiereLogin('../../login.php');

header('Content-Type: text/html; charset=UTF-8');

$db = GrupoSesion::obtenerBaseDatos();
$esAccesorios = isset($_GET['tipo']) && $_GET['tipo'] === 'accesorios';
$sp = $esAccesorios ? 'SJ_TIPO_PEDIDO_CORDOBA_2_bis' : 'SJ_TIPO_PEDIDO_CORDOBA_1bis';

$sucursalObj = new Sucursal();
$resolucion = $sucursalObj->construirSucursalesActivasParaPedido($db);
$sucursalesInfo = $resolucion['info'];
$numerosSucursal = array_map('intval', array_keys($sucursalesInfo));

$conexion = new Conexion();
$cid = $conexion->conectar($db);

$diagnostico = [
    'db'              => $db,
    'stored_procedure'=> $sp,
    'sesion'          => [
        'username'              => $_SESSION['username'] ?? null,
        'codClient'             => $_SESSION['codClient'] ?? null,
        'tipo'                  => $_SESSION['tipo'] ?? null,
        'cargaPedido'           => $_SESSION['cargaPedido'] ?? null,
        'sucursalesGrupo'       => $_SESSION['sucursalesGrupo'] ?? null,
        'pedido_grupo_cargado'  => $_SESSION['pedido_grupo_cargado'] ?? null,
        'sucursales_activas'    => $_SESSION['sucursales_activas'] ?? null,
        'sucursales_conexion_fallidas' => $_SESSION['sucursales_conexion_fallidas'] ?? null,
        'carga_pedido_error'    => $_SESSION['carga_pedido_error'] ?? null,
    ],
    'sucursales_pantalla' => $sucursalesInfo,
    'conexion_central'    => $cid !== false,
    'carga_lopez'         => null,
    'sp_primera_fila'     => null,
    'columnas_sp'         => [],
    'columnas_esperadas'  => [],
    'columnas_faltantes'  => [],
    'enriquecimiento_php' => null,
    'veredictos'          => [],
];

foreach ($numerosSucursal as $nro) {
    $diagnostico['columnas_esperadas'][] = $nro . '_STOCK';
    $diagnostico['columnas_esperadas'][] = $nro . '_VENDIDO';
}

if ($cid === false) {
    $diagnostico['veredictos'][] = 'CRÍTICO: No hay conexión a la base central.';
} else {
    // --- Tabla intermedia (lo que carga cargaPedido.php desde cada local) ---
    $sqlCarga = "
        SELECT NUM_SUC, COUNT(*) AS FILAS,
               SUM(CASE WHEN CANT_STOCK > 0 THEN 1 ELSE 0 END) AS CON_STOCK,
               SUM(CASE WHEN VENDIDO > 0 THEN 1 ELSE 0 END) AS CON_VENDIDO
        FROM SOF_PEDIDOS_CARGA_LOPEZ
        GROUP BY NUM_SUC
        ORDER BY NUM_SUC
    ";
    $stmtCarga = sqlsrv_query($cid, $sqlCarga);
    $cargaPorSuc = [];
    $totalFilasCarga = 0;
    if ($stmtCarga !== false) {
        while ($row = sqlsrv_fetch_array($stmtCarga, SQLSRV_FETCH_ASSOC)) {
            $nro = (int) $row['NUM_SUC'];
            $cargaPorSuc[$nro] = $row;
            $totalFilasCarga += (int) $row['FILAS'];
        }
        sqlsrv_free_stmt($stmtCarga);
    }
    $diagnostico['carga_lopez'] = [
        'total_filas' => $totalFilasCarga,
        'por_sucursal'=> $cargaPorSuc,
    ];

    if ($totalFilasCarga === 0) {
        $diagnostico['veredictos'][] = 'CRÍTICO: SOF_PEDIDOS_CARGA_LOPEZ está vacía → cargaPedido.php no corrió o no insertó nada.';
    } else {
        foreach ($numerosSucursal as $nro) {
            if (!isset($cargaPorSuc[$nro])) {
                $diagnostico['veredictos'][] = "ADVERTENCIA: No hay filas en SOF_PEDIDOS_CARGA_LOPEZ para sucursal $nro.";
            } elseif ((int) $cargaPorSuc[$nro]['CON_STOCK'] === 0 && (int) $cargaPorSuc[$nro]['CON_VENDIDO'] === 0) {
                $diagnostico['veredictos'][] = "ADVERTENCIA: Sucursal $nro tiene filas en carga pero stock y vendido son 0 en todos los artículos.";
            }
        }
    }

    // --- Stored procedure (lo que pinta general.php) ---
    $sqlSp = "SET DATEFORMAT YMD; EXEC $sp";
    $stmtSp = sqlsrv_query($cid, $sqlSp);
    if ($stmtSp === false) {
        $diagnostico['veredictos'][] = 'CRÍTICO: Falló EXEC ' . $sp . ' — ' . json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE);
    } else {
        if ($db !== 'uy') {
            sqlsrv_next_result($stmtSp);
        }
        $primeraFila = sqlsrv_fetch_array($stmtSp, SQLSRV_FETCH_ASSOC);
        if ($primeraFila === false) {
            $diagnostico['veredictos'][] = 'ADVERTENCIA: El SP no devolvió filas de artículos.';
        } else {
            $diagnostico['sp_primera_fila'] = $primeraFila;
            $diagnostico['columnas_sp'] = array_keys($primeraFila);

            foreach ($numerosSucursal as $nro) {
                $colStock = $nro . '_STOCK';
                $colVend  = $nro . '_VENDIDO';
                if (!array_key_exists($colStock, $primeraFila)) {
                    $diagnostico['columnas_faltantes'][] = $colStock;
                }
                if (!array_key_exists($colVend, $primeraFila)) {
                    $diagnostico['columnas_faltantes'][] = $colVend;
                }
            }

            if (!empty($diagnostico['columnas_faltantes'])) {
                $diagnostico['veredictos'][] = 'SP sin columnas ' . implode(', ', $diagnostico['columnas_faltantes'])
                    . ' (pivote hardcodeado Córdoba). Pedido.php las completa desde SOF_PEDIDOS_CARGA_LOPEZ al listar.';
            }
        }
        sqlsrv_free_stmt($stmtSp);
    }

    sqlsrv_close($cid);
}

$pedidoObj = new Pedido();
$pedidosEnriquecidos = $esAccesorios
    ? $pedidoObj->listarPedidoCordobaAccesorios($db)
    : $pedidoObj->listarPedidoCordoba($db);
if (!empty($pedidosEnriquecidos)) {
    $filaEnr = $pedidosEnriquecidos[0];
    $muestraEnr = ['COD_ARTICU' => $filaEnr['COD_ARTICU'] ?? ''];
    $okEnr = true;
    foreach ($numerosSucursal as $nro) {
        $muestraEnr[$nro . '_STOCK']   = $filaEnr[$nro . '_STOCK'] ?? null;
        $muestraEnr[$nro . '_VENDIDO'] = $filaEnr[$nro . '_VENDIDO'] ?? null;
        if (!array_key_exists($nro . '_STOCK', $filaEnr)) {
            $okEnr = false;
        }
    }
    $diagnostico['enriquecimiento_php'] = [
        'ok'              => $okEnr,
        'primer_articulo' => $muestraEnr,
    ];
    if ($okEnr && !empty($diagnostico['columnas_faltantes'])) {
        $diagnostico['veredictos'][] = 'OK: Enriquecimiento PHP activo — general.php debería mostrar stock/vendido tras recargar.';
    }
}

// --- Sesión: ¿se dispararía cargaPedido? ---
if (empty($_SESSION['sucursalesGrupo'])) {
    $diagnostico['veredictos'][] = 'CRÍTICO: $_SESSION[sucursalesGrupo] vacío → index no redirige a cargaPedido.php.';
}
if (empty($_SESSION['pedido_grupo_cargado']) && empty($_SESSION['sucursales_activas'])) {
    $diagnostico['veredictos'][] = 'INFO: La carga inicial del grupo no completó en esta sesión.';
}

if (empty($diagnostico['veredictos'])) {
    $diagnostico['veredictos'][] = 'OK: Datos presentes en carga y columnas del SP coinciden. Si aún ves 0, revisá artículo por artículo (puede ser stock real 0).';
}

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Debug carga grupo — stock/vendido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 1.5rem; background: #f5f7fa; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 6px; font-size: 12px; max-height: 400px; overflow: auto; }
        .veredicto-critico { border-left: 4px solid #dc3545; }
        .veredicto-warn { border-left: 4px solid #ffc107; }
        .veredicto-ok { border-left: 4px solid #198754; }
    </style>
</head>
<body>
<div class="container-fluid" style="max-width: 1100px;">
    <h1 class="h4 mb-3">Debug stock / vendido por sucursal</h1>
    <p class="text-muted">
        Cadena: <code>cargaPedido.php</code> → <code>SOF_PEDIDOS_CARGA_LOPEZ</code> → <code><?= h($sp) ?></code> → <code>general.php</code> (<code><?= h('844_STOCK') ?></code>, etc.)
    </p>
    <p>
        <a href="debug-carga.php" class="btn btn-sm btn-outline-primary">General</a>
        <a href="debug-carga.php?tipo=accesorios" class="btn btn-sm btn-outline-secondary">Accesorios</a>
        <a href="general.php" class="btn btn-sm btn-outline-dark">Volver a general.php</a>
        <a href="../controller/cargaPedido.php" class="btn btn-sm btn-warning">Forzar cargaPedido.php</a>
    </p>

    <div class="card mb-3">
        <div class="card-header fw-bold">Veredictos</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($diagnostico['veredictos'] as $v): ?>
                <?php
                $cls = 'veredicto-ok';
                if (stripos($v, 'CRÍTICO') !== false) $cls = 'veredicto-critico';
                elseif (stripos($v, 'ADVERTENCIA') !== false) $cls = 'veredicto-warn';
                ?>
                <li class="list-group-item <?= $cls ?>"><?= h($v) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Sesión</div>
                <div class="card-body"><pre><?= h(json_encode($diagnostico['sesion'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Sucursales en pantalla (nombres de columnas)</div>
                <div class="card-body"><pre><?= h(json_encode($diagnostico['sucursales_pantalla'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header">SOF_PEDIDOS_CARGA_LOPEZ (datos traídos de cada local)</div>
                <div class="card-body">
                    <?php if ($diagnostico['carga_lopez']): ?>
                        <p>Total filas: <strong><?= (int) $diagnostico['carga_lopez']['total_filas'] ?></strong></p>
                        <pre><?= h(json_encode($diagnostico['carga_lopez']['por_sucursal'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        <p class="small text-muted mb-0">SQL manual: <code>SELECT TOP 20 * FROM SOF_PEDIDOS_CARGA_LOPEZ WHERE NUM_SUC IN (<?= h(implode(',', $numerosSucursal)) ?>) ORDER BY NUM_SUC, COD_ARTICU</code></p>
                    <?php else: ?>
                        <p class="text-danger">Sin conexión central.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header">Columnas del SP vs columnas que usa general.php</div>
                <div class="card-body">
                    <p><strong>Esperadas:</strong> <?= h(implode(', ', $diagnostico['columnas_esperadas'])) ?></p>
                    <p><strong>Faltantes en SP:</strong>
                        <?= empty($diagnostico['columnas_faltantes'])
                            ? '<span class="text-success">ninguna</span>'
                            : '<span class="text-danger">' . h(implode(', ', $diagnostico['columnas_faltantes'])) . '</span>' ?>
                    </p>
                    <details>
                        <summary>Todas las columnas del SP (1.er artículo)</summary>
                        <pre><?= h(json_encode($diagnostico['columnas_sp'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    </details>
                    <?php if ($diagnostico['sp_primera_fila']): ?>
                        <details class="mt-2">
                            <summary>Valores 1.er artículo para tus sucursales</summary>
                            <pre><?php
                                $muestra = ['COD_ARTICU' => $diagnostico['sp_primera_fila']['COD_ARTICU'] ?? ''];
                                foreach ($numerosSucursal as $nro) {
                                    $muestra[$nro . '_STOCK']   = $diagnostico['sp_primera_fila'][$nro . '_STOCK'] ?? '(columna no existe)';
                                    $muestra[$nro . '_VENDIDO'] = $diagnostico['sp_primera_fila'][$nro . '_VENDIDO'] ?? '(columna no existe)';
                                }
                                echo h(json_encode($muestra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($diagnostico['enriquecimiento_php']): ?>
        <div class="col-12">
            <div class="card border-success">
                <div class="card-header">Enriquecimiento PHP (listarPedidoCordoba)</div>
                <div class="card-body">
                    <p>Estado:
                        <?= !empty($diagnostico['enriquecimiento_php']['ok'])
                            ? '<span class="text-success fw-bold">OK — columnas completadas desde SOF_PEDIDOS_CARGA_LOPEZ</span>'
                            : '<span class="text-danger fw-bold">FALLÓ</span>' ?>
                    </p>
                    <pre><?= h(json_encode($diagnostico['enriquecimiento_php']['primer_articulo'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
                