<?php
require_once __DIR__ . '/../../sistemas/xlgestion/bootstrap.php';

// El tipo de pedido se resuelve primero: de él dependen la guarda de permisos
// y la clave NRO_SUC+COD_CLIENT+TIPO_PEDIDO del carrito.
$_tipoNum = intval($_GET['tipo'] ?? 1);

$mapaGuardaTipo = [
    1 => 'pedidos.generales',
    2 => 'pedidos.accesorios',
    3 => 'pedidos.outlet',
    4 => 'pedidos.packaging',
];
Permisos::requiere($mapaGuardaTipo[$_tipoNum] ?? 'pedidos.generales');

include_once __DIR__.'/class/pedido.php';

// Configuración de variables de sesión
$_SESSION['depo'] = '01';
$suc = $_SESSION['numsuc'];
$codClient = $_SESSION['codClient'];
$local = $_SESSION['descLocal'] ?? '';
$tipo_cli = $_SESSION['tipo'];
$esOutlet = $_SESSION['esOutlet'];
$esUsuarioUy = isset($_SESSION['usuarioUy']) ? $_SESSION['usuarioUy'] : null;
$db = ($esUsuarioUy == 1) ? 'uy' : 'central';

// Determinar tipo de cliente para subtítulo dinámico
$prefijoCodClient = substr($codClient, 0, 2);
$esLocalPropio = ($prefijoCodClient === 'GT');
$esFranquicia = ($prefijoCodClient === 'FR');
$esExterior = ($prefijoCodClient === 'UR' || $prefijoCodClient === 'EX');
// Locales propios no ven precios
$mostrarPrecio = ($suc > 100) && !$esLocalPropio;

// $_tipoNum ya se resolvió arriba, junto con la guarda de permisos.
$_tipoMap = [1 => 'GENERAL', 2 => 'ACCESORIOS', 3 => 'OUTLET', 4 => 'PACKAGING'];
$_SESSION['tipo_pedido'] = $_tipoMap[$_tipoNum] ?? 'GENERAL';

// Aumentar límite de tiempo para páginas con consultas lentas a servidores externos
set_time_limit(60);

// Obtener lista de productos
$pedido = new Pedido();
$productos = $pedido->listarPedido($_tipoNum, $tipo_cli, $suc, $codClient, $esOutlet, $db);

// Carrito: restaurar cantidades guardadas en DB (persistencia entre sesiones)
$itemsCarrito = 0;
$carritoActual = []; // [COD_ARTICU => CANTIDAD]
try {
    $itemsCarrito = $pedido->contarItemsCarrito($suc, $codClient, $_SESSION['tipo_pedido']);
    $carritoItems = $pedido->obtenerCarrito($suc, $codClient, $_SESSION['tipo_pedido']);
    foreach ($carritoItems as $ci) {
        $carritoActual[$ci['COD_ARTICU']] = intval($ci['CANTIDAD']);
    }
} catch (Exception $e) {
    error_log('Error al leer carrito: ' . $e->getMessage());
}

// Obtener stock local y ventas para TODOS los locales con base propia
$stockYVentas = null;
$errorConexion = null;
$fuenteDatos = null;
// Mostrar columnas para locales propios, franquicias y exterior (todos con base en SUCURSALES_LAKERS)
$mostrarStockLocal = ($esLocalPropio || $esFranquicia || $esExterior);
$mostrarVentas30d = ($esLocalPropio || $esFranquicia || $esExterior);

if (($mostrarStockLocal || $mostrarVentas30d) && !empty($codClient)) {
    $articulos = array_map(function($p) { return trim($p['COD_ARTICU']); }, $productos);
    $tipoCliente = $esFranquicia ? 'franquicia' : 'local'; // GT*, UR*, EX* → 'local' | FR* → 'franquicia'
    $stockYVentas = $pedido->obtenerStockYVentasLocal($codClient, $articulos, $tipoCliente);
    $errorConexion = $stockYVentas['error'];
    $fuenteDatos = $stockYVentas['fuente'] ?? null;
}

// Calcular si se debe mostrar la sugerencia (solo después de obtener los datos)
$mostrarSugerencia = ($mostrarStockLocal || $mostrarVentas30d) && !$errorConexion;

// Obtener rubros únicos para el filtro
$rubros = array();
foreach($productos as $producto) {
    $rubro = trim($producto['RUBRO']);
    if($rubro && !in_array($rubro, $rubros)) {
        $rubros[] = $rubro;
    }
}
sort($rubros);

// Filtrar productos TOP (ES_TOP = 1) y ordenar por ventas
$topProducts = array_filter($productos, function($p) {
    return (isset($p['ES_TOP']) && $p['ES_TOP'] == 1) || 
           (isset($p['CATEGORIA_VENTA']) && $p['CATEGORIA_VENTA'] == 'TOP');
});
usort($topProducts, function($a, $b) {
    $ventasA = isset($a['UNIDADES_30D']) ? intval($a['UNIDADES_30D']) : 0;
    $ventasB = isset($b['UNIDADES_30D']) ? intval($b['UNIDADES_30D']) : 0;
    return $ventasB - $ventasA;
});

// Filtrar productos NUEVO (ES_NUEVO = 1) y ordenar por ventas
$nuevosProducts = array_filter($productos, function($p) {
    return (isset($p['ES_NUEVO']) && $p['ES_NUEVO'] == 1) || 
           (isset($p['CATEGORIA_VENTA']) && $p['CATEGORIA_VENTA'] == 'NUEVO');
});
usort($nuevosProducts, function($a, $b) {
    $ventasA = isset($a['UNIDADES_30D']) ? intval($a['UNIDADES_30D']) : 0;
    $ventasB = isset($b['UNIDADES_30D']) ? intval($b['UNIDADES_30D']) : 0;
    return $ventasB - $ventasA;
});

// Vista celular: cuántos casilleros de datos lleva la card (stock central siempre,
// stock local y ventas según perfil). La clase en la tabla ajusta la grilla sin :has().
$mCasilleros = 1 + ($mostrarStockLocal ? 1 : 0) + ($mostrarVentas30d ? 1 : 0);

// Vista celular: el estado de conexión hoy es un tooltip con hover, que en pantallas
// táctiles no se ve. Se arma el mismo mensaje como texto para el menú ⋮.
$mEstadoConexion = null;
if ($mostrarStockLocal || $mostrarVentas30d) {
    if (!$errorConexion && isset($fuenteDatos)) {
        if ($fuenteDatos === 'directa') {
            $mEstadoConexion = ['clase' => 'ok', 'icono' => 'fa-info-circle', 'texto' => 'Conexión exitosa a la base del local', 'detalle' => ''];
        } elseif ($fuenteDatos === 'centralizada') {
            $mEstadoConexion = ['clase' => 'warn', 'icono' => 'fa-exclamation-triangle', 'texto' => 'Usando datos de servidor central. No se pudo conectar directamente a la base del local', 'detalle' => ''];
        }
    } elseif ($errorConexion) {
        $mEstadoConexion = [
            'clase'   => 'error',
            'icono'   => 'fa-times-circle',
            'texto'   => 'Error de conexión con la base del local',
            'detalle' => $errorConexion . (!empty($stockYVentas['fuente']) ? ' | Fuente: ' . $stockYVentas['fuente'] : ''),
        ];
    }
}

// Calcular totales iniciales desde el carrito guardado (para renderizar sin esperar JS)
$initArticulos = count($carritoActual);
$initUnidades  = array_sum($carritoActual);
// Precio inicial lo calcula JS (necesita data-precio de cada fila)

// Convertir a JSON para JavaScript
$topProductsJSON    = json_encode(array_values($topProducts));
$nuevosProductsJSON = json_encode(array_values($nuevosProducts));

// Debug
error_log("DEBUG - Total productos: " . count($productos));
error_log("DEBUG - TOP productos: " . count($topProducts));
error_log("DEBUG - NUEVO productos: " . count($nuevosProducts));

// Título según tipo de pedido (tipo_pedido ya fue seteado en $_SESSION arriba)
$tituloPedido = 'Pedido General';
switch($_tipoNum) {
    case 1: $tituloPedido = 'Pedido General';    break;
    case 2: $tituloPedido = 'Pedido Accesorios'; break;
    case 3: $tituloPedido = 'Pedido Outlet';     break;
    case 4: $tituloPedido = 'Pedido Packaging';  break;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <!-- viewport-fit=cover: habilita env(safe-area-inset-*) para la barra fija en iPhone -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>XL Extra Large - <?php echo $tituloPedido; ?></title>
    <link rel="icon" type="image/jpeg" href="../../sistemas/assets/css/icono.jpg?v=1" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/pedidos.css?v=<?php echo filemtime(__DIR__.'/style/pedidos.css'); ?>">
    <link rel="stylesheet" href="style/carrusel.css?v=<?php echo filemtime(__DIR__.'/style/carrusel.css'); ?>">
    <link rel="stylesheet" href="style/tabla.css?v=<?php echo filemtime(__DIR__.'/style/tabla.css'); ?>">
    <link rel="stylesheet" href="style/carrito.css?v=<?php echo filemtime(__DIR__.'/style/carrito.css'); ?>">
    <!-- Vista celular: va última para pisar estilos solo dentro de su media query -->
    <link rel="stylesheet" href="style/pedidos-mobile.css?v=<?php echo filemtime(__DIR__.'/style/pedidos-mobile.css'); ?>">
    <!-- Prefetch carrito para que la segunda visita sea instantánea -->
    <link rel="prefetch" href="carrito.php">
</head>
<body>
    <!-- Header -->
    <div class="header-container">
        <div class="header-top">
            <div class="logo-section">
                <!-- Solo celular: reemplaza al botón "Inicio" con texto -->
                <a href="../../sistemas/index.php" class="m-only m-icon-btn m-home" aria-label="Ir al inicio">
                    <i class="fas fa-home" aria-hidden="true"></i>
                </a>
                <img src="images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1><?php echo $tituloPedido; ?></h1>
                    <p class="info-text">Sucursal: <?php echo htmlspecialchars($suc); ?> | Cliente: <?php echo htmlspecialchars($local ? $local . ' (' . $codClient . ')' : $codClient); ?></p>
                    <p class="m-only m-subtitle">Suc. <?php echo htmlspecialchars($suc); ?> &middot; <?php echo htmlspecialchars($local ?: $codClient); ?></p>
                </div>
            </div>
            
            <?php if($esLocalPropio || $esFranquicia): ?>
            <div class="header-center">
                <?php if($esLocalPropio): ?>
                    <div class="subtitle-main">
                        <i class="fas fa-store"></i>
                        Una nueva forma de abastecer tu local
                    </div>
                    <div class="subtitle-secondary">
                        Abastecé mejor • Más rápido • Más claro
                    </div>
                <?php elseif($esFranquicia): ?>
                    <div class="subtitle-main">
                        <i class="fas fa-shopping-bag"></i>
                        Una nueva forma de armar tu pedido
                    </div>
                    <div class="subtitle-secondary">
                        Comprá mejor • Más rápido • Más claro
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="header-actions">
                <?php if($mostrarStockLocal || $mostrarVentas30d): ?>
                    <?php if(!$errorConexion && isset($fuenteDatos)): ?>
                        <?php if($fuenteDatos === 'directa'): ?>
                            <!-- Conexión directa exitosa -->
                            <button type="button" class="btn btn-info-icon tooltip-trigger" data-tooltip="Conexión exitosa a la base del local">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        <?php elseif($fuenteDatos === 'centralizada'): ?>
                            <!-- Fallback a base centralizada -->
                            <button type="button" class="btn btn-warning-icon tooltip-trigger" data-tooltip="Usando datos de servidor central. No se pudo conectar directamente a la base del local">
                                <i class="fas fa-exclamation-triangle"></i>
                            </button>
                        <?php endif; ?>
                    <?php elseif($errorConexion): ?>
                        <!-- Error de conexión completo -->
                        <button type="button" class="btn btn-error-icon tooltip-trigger" data-tooltip="<?php echo htmlspecialchars($errorConexion . (!empty($stockYVentas['fuente']) ? ' | Fuente: ' . $stockYVentas['fuente'] : '')); ?>">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="../../sistemas/index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Inicio
                </a>
                <button type="button" class="btn btn-secondary" onclick="exportarExcel()">
                    <i class="fas fa-file-excel"></i> Exportar tabla
                </button>
                <!-- Solo celular: menú ⋮ con las acciones que en escritorio son botones con texto -->
                <div class="m-only m-menu-wrap">
                    <button type="button" class="m-icon-btn" id="mMenuBtn" aria-label="Más opciones"
                            aria-haspopup="true" aria-expanded="false" aria-controls="mMenu">
                        <i class="fas fa-ellipsis-vertical" aria-hidden="true"></i>
                    </button>
                    <div class="m-menu" id="mMenu" hidden>
                        <button type="button" class="m-menu-item" data-m-accion="exportar">
                            <i class="fas fa-file-excel" aria-hidden="true"></i> Exportar tabla
                        </button>
                        <button type="button" class="m-menu-item" data-m-accion="limpiar-filtros">
                            <i class="fas fa-eraser" aria-hidden="true"></i> Limpiar filtros
                        </button>
                        <?php if ($mEstadoConexion): ?>
                        <div class="m-menu-status m-status-<?php echo $mEstadoConexion['clase']; ?>">
                            <i class="fas <?php echo $mEstadoConexion['icono']; ?>" aria-hidden="true"></i>
                            <div>
                                <div><?php echo htmlspecialchars($mEstadoConexion['texto']); ?></div>
                                <?php if ($mEstadoConexion['detalle'] !== ''): ?>
                                <div class="m-menu-status-detalle"><?php echo htmlspecialchars($mEstadoConexion['detalle']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="btn-cart" onclick="guardarYVerCarrito()" title="Ver Carrito" aria-label="Ver carrito">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($itemsCarrito > 0): ?>
                    <span class="cart-badge" id="cartBadge"><?php echo $itemsCarrito; ?></span>
                    <?php else: ?>
                    <span class="cart-badge cart-badge-empty" id="cartBadge">0</span>
                    <?php endif; ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        
        <!-- Filtro de Rubros -->
        <div class="rubro-filter-section">
            <div class="rubro-filter-content">
                <label for="filterRubro" class="rubro-label">
                    <i class="fas fa-tag"></i> Filtrar por Rubro:
                </label>
                <!-- aria-label: en el celular el select se mueve fuera de su <label> -->
                <select id="filterRubro" class="filter-input-rubro" aria-label="Filtrar por rubro">
                    <option value="">Todos los rubros</option>
                    <?php foreach($rubros as $rubro): ?>
                        <option value="<?php echo htmlspecialchars($rubro); ?>">
                            <?php echo htmlspecialchars($rubro); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="product-count">
                    <i class="fas fa-box"></i> <span id="productCount"><?php echo count($productos); ?></span> productos disponibles
                </span>
            </div>
        </div>
        
        <!-- CARRUSEL DE PRODUCTOS DESTACADOS -->
        <?php if(count($topProducts) > 0 || count($nuevosProducts) > 0): ?>
        <div class="suggestions-section">
            <div class="suggestions-header">
                <h3><i class="fas fa-lightbulb"></i> Productos Destacados</h3>
                <div class="suggestion-tabs">
                    <?php if(count($topProducts) > 0): ?>
                    <button class="tab-btn active" data-tab="top">
                        <i class="fas fa-fire"></i> Más Vendidos
                    </button>
                    <?php endif; ?>
                    <?php if(count($nuevosProducts) > 0): ?>
                    <button class="tab-btn <?php echo count($topProducts) == 0 ? 'active' : ''; ?>" data-tab="nuevo">
                        <i class="fas fa-star"></i> Novedades
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Carrusel de productos TOP -->
            <?php if(count($topProducts) > 0): ?>
            <div class="suggestions-carousel-wrapper active" data-content="top">
                <button class="carousel-btn prev">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="suggestions-carousel" id="carousel-top">
                    <!-- Se llenará dinámicamente con JavaScript -->
                </div>
                <button class="carousel-btn next">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="carousel-indicators" id="indicators-top"></div>
            </div>
            <?php endif; ?>
            
            <!-- Carrusel de productos NUEVO -->
            <?php if(count($nuevosProducts) > 0): ?>
            <div class="suggestions-carousel-wrapper <?php echo count($topProducts) == 0 ? 'active' : ''; ?>" data-content="nuevo">
                <button class="carousel-btn prev">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="suggestions-carousel" id="carousel-nuevo">
                    <!-- Se llenará dinámicamente con JavaScript -->
                </div>
                <button class="carousel-btn next">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="carousel-indicators" id="indicators-nuevo"></div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Script con datos de productos -->
        <script>
            const allTopProducts = <?php echo $topProductsJSON; ?>;
            const allNuevosProducts = <?php echo $nuevosProductsJSON; ?>;
        </script>
        <?php endif; ?>
        
        <!-- Barra de Búsqueda y Ordenamiento -->
        <div class="filters-bar">
            <div class="filters-grid">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input 
                        type="text" 
                        id="searchProduct" 
                        class="filter-input"
                        placeholder="Buscar por código (ej: XV5SST06ZI05841) o descripción..."
                        autocomplete="off"
                        aria-label="Buscar producto"
                    >
                </div>

                <select id="sortProducts" class="filter-input" aria-label="Ordenar productos">
                    <option value="default">🔄 Orden por defecto</option>
                    <option value="ventas-desc">⭐ Más vendidos</option>
                    <option value="nuevo">✨ Novedades</option>
                    <option value="precio-asc">💰 Menor precio</option>
                    <option value="precio-desc">💎 Mayor precio</option>
                    <option value="stock-desc">📦 Mayor stock</option>
                    <option value="stock-asc">⚠️ Menor stock</option>
                    <option value="nombre-asc">🔤 A-Z</option>
                    <option value="nombre-desc">🔤 Z-A</option>
                </select>
                
                <div class="totals-display">
                    <span class="total-inline">ARTÍCULOS: <span id="totalArticulos">0</span></span>
                    <span class="total-inline">Unidades: <span id="totalCantidad">0</span></span>
                </div>
                
                <button type="button" id="btnClear" class="btn btn-secondary">
                    <i class="fas fa-eraser"></i> Limpiar filtros
                </button>
            </div>
        </div>
        
        <!-- Filtros Activos -->
        <div id="activeFilters" class="active-filters" style="display: none;">
            <div class="filters-label">
                <i class="fas fa-filter"></i> FILTROS ACTIVOS:
            </div>
            <div id="filterChips" class="filter-chips"></div>
        </div>

        <!-- Banner de stock insuficiente (oculto por defecto, lo muestra el JS al detectar marcas) -->
        <div id="stockWarningBanner" class="stock-warning-banner hidden">
            <i class="fas fa-exclamation-triangle"></i>
            <span>
                Hay <strong id="stockWarningCount">0</strong>
                artículo(s) sin stock suficiente. Modificá las cantidades resaltadas para poder enviar el pedido.
            </span>
        </div>

        <!-- Formulario de Pedido -->
        <form id="formPedido" method="POST" onsubmit="return false;">
            <input type="hidden" name="suc" value="<?php echo htmlspecialchars($suc); ?>">
            <input type="hidden" name="codClient" value="<?php echo htmlspecialchars($codClient); ?>">
            <input type="hidden" name="tipo_pedido" value="<?php echo htmlspecialchars($_SESSION['tipo_pedido']); ?>">
            <input type="hidden" name="depo" value="<?php echo htmlspecialchars($_SESSION['depo']); ?>">
            
            <!-- Product Table -->
            <div class="product-table-container">
                <div class="product-table-header">
                    <div class="product-table-title">
                        <i class="fas fa-box-open"></i>
                        Productos Disponibles
                    </div>
                    <span class="item-count"><?php echo count($productos); ?> productos</span>
                    <!-- Solo celular: contador que sigue a los filtros y "Limpiar" que sale de la barra de abajo -->
                    <span class="m-only m-list-count"><span class="js-product-count"><?php echo count($productos); ?></span> productos</span>
                    <button type="button" class="m-only m-link-btn" onclick="limpiarCantidades()">Limpiar cantidades</button>
                </div>

                <table class="product-table m-casilleros-<?php echo $mCasilleros; ?><?php echo $mostrarPrecio ? '' : ' m-sin-precio'; ?>">
                    <thead>
                        <tr>
                            <th class="col-foto">Foto</th>
                            <th class="col-codigo">Código</th>
                            <th>Descripción</th>
                            <th class="col-rubro">Rubro</th>
                            <th class="text-center col-stock">Stock Central</th>
                            <?php if($mostrarStockLocal): ?>
                            <!-- Stock Local (solo locales propios) -->
                            <th class="text-center col-stock">
                                Stock Local
                                <?php if(isset($fuenteDatos) && $fuenteDatos): ?>
                                <span title="Fuente: <?php echo htmlspecialchars($fuenteDatos); ?>"
                                      style="cursor:help; font-size:0.8em; opacity:0.7;">
                                    <?php echo ($fuenteDatos === 'directa') ? '&#x1F7E2;' : '&#x1F7E1;'; ?>
                                </span>
                                <?php endif; ?>
                            </th>
                            <?php endif; ?>
                            <?php if($mostrarVentas30d): ?>
                            <!-- Ventas 30d (locales propios y franquicias) -->
                            <th class="text-center col-stock">Ventas 30d</th>
                            <?php endif; ?>
                            <?php if($mostrarSugerencia): ?>
                            <th class="text-center col-stock">Sugerencia</th>
                            <?php endif; ?>
                            <th class="text-center col-distri">Distribución</th>
                            <?php if($mostrarPrecio): ?>
                            <th class="text-right col-precio">Precio</th>
                            <?php endif; ?>
                            <th class="text-center col-cantidad">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($productos) > 0): ?>
                            <?php foreach($productos as $prod): 
                                $codigo = trim($prod['COD_ARTICU']);
                                $descripcion = trim($prod['DESCRIPCIO']);
                                $rubro = trim($prod['RUBRO']);
                                $stock = intval($prod['CANT_STOCK']);
                                $distri = intval($prod['DISTRI']);
                                $imageName = substr($codigo, 0, 13);
                                
                                // Detectar SALE
                                $enLiquidacion = (strpos($descripcion, '-- SALE! --') !== false);
                                if($enLiquidacion) {
                                    $descripcion = str_replace('-- SALE! --', '', $descripcion);
                                    $descripcion = trim($descripcion);
                                }
                                
                                // Precio según tipo
                                $precio = 0;
                                if($suc > 100 && $suc != 201 && $suc != 202 && !$esLocalPropio) {
                                    if($_tipoNum == 3) {
                                        $precio = intval($prod['PRECIO_MAYO']);
                                    } else {
                                        if(substr($tipo_cli, 0, 1) == 'F' && $_tipoNum != 3) {
                                            $precio = intval($prod['PRECIO_FRANQ']);
                                        } else {
                                            $precio = intval($prod['PRECIO_MAYO']);
                                        }
                                    }
                                }
                                
                                // Determinar clase de stock
                                $stockClass = 'high';
                                if($stock < 10) $stockClass = 'low';
                                elseif($stock < 50) $stockClass = 'medium';
                                
                                // Verificar si debe estar disabled
                                $disabled = ($distri > 0 && $_SESSION['tipo'] != 'FRANQUICIA' && $rubro != 'PACKAGING' && $rubro != 'HOME');
                            ?>
                                <tr 
                                    data-code="<?php echo htmlspecialchars(strtolower($codigo)); ?>"
                                    data-description="<?php echo htmlspecialchars(strtolower($descripcion)); ?>"
                                    data-rubro="<?php echo htmlspecialchars($rubro); ?>"
                                    data-distri="<?php echo $distri; ?>"
                                    data-precio="<?php echo $precio; ?>"
                                    data-ventas="<?php echo intval($prod['UNIDADES_30D'] ?? 0); ?>"
                                    data-nuevo="<?php echo (isset($prod['ES_NUEVO']) && $prod['ES_NUEVO'] == 1) ? '1' : '0'; ?>"
                                >
                                    <!-- Foto -->
                                    <td class="product-image-cell">
                                        <img 
                                            src="https://app.xl.com.ar/imagenes/<?php echo $imageName; ?>.jpg" 
                                            alt="<?php echo htmlspecialchars($descripcion); ?>"
                                            class="product-image"
                                            loading="lazy"
                                            onerror="this.onerror=null; this.src='https://app.xl.com.ar/imagenes/default.jpg'"
                                            onclick="mostrarImagen('https://app.xl.com.ar/imagenes/<?php echo $imageName; ?>.jpg')"
                                        >
                                    </td>
                                    <!-- Código -->
                                    <td class="td-codigo">
                                        <span class="product-code"><?php echo htmlspecialchars($codigo); ?></span>
                                        <input type="hidden" name="codArt[]" value="<?php echo htmlspecialchars($codigo); ?>">
                                    </td>

                                    <!-- Descripción -->
                                    <td class="td-desc">
                                        <?php echo htmlspecialchars($descripcion); ?>
                                        <?php if($enLiquidacion): ?>
                                            <span class="sale-badge">SALE</span>
                                        <?php endif; ?>
                                        <?php // Solo celular: etiquetas en texto en lugar de los íconos 🔥/✨ de la columna de stock ?>
                                        <?php if(isset($prod['ES_TOP']) && $prod['ES_TOP'] == 1): ?>
                                            <span class="m-only m-tag m-tag-top">Más vendido</span>
                                        <?php elseif(isset($prod['ES_NUEVO']) && $prod['ES_NUEVO'] == 1): ?>
                                            <span class="m-only m-tag m-tag-new">Novedad</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Rubro -->
                                    <td class="td-rubro" data-label="Rubro">
                                        <span class="rubro-badge"><?php echo htmlspecialchars($rubro); ?></span>
                                        <input type="hidden" name="rubro[]" value="<?php echo htmlspecialchars($rubro); ?>">
                                    </td>

                                    <!-- Stock Central -->
                                    <td class="text-center td-stock-central" data-label="Stock central">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                            <span class="stock-badge <?php echo $stockClass; ?>">
                                                <?php echo $stock; ?>
                                            </span>
                                            <?php if(isset($prod['ES_TOP']) && $prod['ES_TOP'] == 1): ?>
                                                <span class="sales-badge hot">
                                                    🔥
                                                </span>
                                            <?php elseif(isset($prod['ES_NUEVO']) && $prod['ES_NUEVO'] == 1): ?>
                                                <span class="sales-badge new">
                                                    ✨
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="stock[]" value="<?php echo $stock; ?>">
                                    </td>
                                    
                                    <?php
                                    // Pre-calcular variables de stock local, ventas y sugerencia (código completo, sin truncar)
                                    $codigoNormalizado = trim($codigo);
                                    $stockLocalRaw = (!$errorConexion && $stockYVentas) ? (array_key_exists($codigoNormalizado, $stockYVentas['stock']) ? intval($stockYVentas['stock'][$codigoNormalizado]) : null) : null;
                                    $ventas30Raw   = (!$errorConexion && $stockYVentas) ? (array_key_exists($codigoNormalizado, $stockYVentas['ventas']) ? intval($stockYVentas['ventas'][$codigoNormalizado]) : null) : null;
                                    $stockLocal = $stockLocalRaw ?? 0;
                                    $ventas30   = $ventas30Raw ?? 0;
                                    $stockLocalDesconocido = $stockLocalRaw === null;
                                    $ventas30Desconocido = $ventas30Raw === null;
                                    // Paso 1: MOS_TARGET base según rubro
                                    $rubrosTarget = [
                                        'CARTERAS DE VINILICO'   => 2.1,
                                        'ACCESORIOS DE VINILICO' => 2.1,
                                        'BIJOU'                  => 2.1,
                                        'LLAVEROS'               => 2.1,
                                        'BILLETERAS DE VINILICO' => 2.0,
                                        'BILLETERAS DE CUERO'    => 2.0,
                                        'ACCESORIOS DE CUERO'    => 2.0,
                                        'CARTERAS DE CUERO'      => 1.75,
                                        'COSMETICA'              => 2.0,
                                        'HOME'                   => 2.0,
                                        'CINTOS DE CUERO'        => 2.0,
                                        'CHALINAS'               => 1.75,
                                        'PARAGUAS'               => 1.75,
                                        'CALZADOS'               => 1.5,
                                        'CAMPERAS'               => 1.5,
                                        'INDUMENTARIA'           => 1.5,
                                        'TOALLAS'                => 1.5,
                                        'EQUIPAJES'              => 1.25,
                                    ];
                                    $MOS_TARGET = isset($rubrosTarget[$rubro]) ? $rubrosTarget[$rubro] : 2.0;
                                    // Ajuste por comportamiento del artículo (relativo al target de rubro)
                                    if (intval($prod['ES_TOP'] ?? 0) === 1 || intval($prod['ES_TENDENCIA'] ?? 0) === 1) {
                                        $MOS_TARGET += 0.25;
                                    } elseif (intval($prod['ES_NUEVO'] ?? 0) === 1) {
                                        $MOS_TARGET -= 0.25;
                                    }
                                    // Paso 2: suavizado con tres casos ordenados por prioridad
                                    if ($ventas30 == 0 && $stockLocal > 0) {
                                        // Tiene stock pero no vende: no rotar, no sugerir
                                        $ventasEfectivas = 0;
                                    } elseif ($ventas30 == 0) {
                                        // Sin venta local y sin stock: usar piso de red como estimación
                                        $pctRed = $esFranquicia ? 0.02 : 0.05;
                                        $ventasRed = intval($prod['UNIDADES_30D'] ?? 0);
                                        $ventasEfectivas = (int) round($ventasRed * $pctRed);
                                    } else {
                                        // Hay venta local: confiar en ella directamente
                                        $ventasEfectivas = $ventas30;
                                    }
                                    // Paso 2b: caso especial - stock local muy bajo con amplia disponibilidad central
                                    if ($stockLocal <= 2 && $stock > 100 && $ventasEfectivas == 0) {
                                        $sugerencia = 1;
                                    } else {
                                        $sugerencia = null; // se calculará en el paso siguiente
                                    }
                                    // Paso 3: calcular sugerencia (solo si no fue asignada en el paso anterior)
                                    if ($sugerencia === null) {
                                    if ($errorConexion) {
                                        $sugerencia = null;
                                    } elseif ($ventasEfectivas == 0 && $stockLocal == 0 && intval($prod['ES_NUEVO'] ?? 0) === 1) {
                                        // Artículo nuevo sin historial local: usar ventas centrales como proxy
                                        $ventasProxy = intval($prod['UNIDADES_30D'] ?? 0);
                                        if ($ventasProxy > 0) {
                                            $sugerencia = max(0, (int) round($ventasProxy * 0.1));
                                        } else {
                                            $sugerencia = null;
                                        }
                                    } elseif ($ventasEfectivas == 0 && $stockLocal == 0) {
                                        $sugerencia = null;
                                    } elseif ($ventasEfectivas == 0 && $stockLocal > 0) {
                                        $sugerencia = 0;
                                    } else {
                                        $sugerencia = max(0, (int) round($ventasEfectivas * $MOS_TARGET - $stockLocal));
                                    }
                                    } // fin if ($sugerencia === null)

                                    // Paso 3.5: Penalizacion sublineal general (independiente del stock)
                                    // Aplica a cualquier sugerencia > 0. Ej: 5->4, 8->5, 10->6, 15->9, 20->11
                                    if ($sugerencia !== null && $sugerencia > 0) {
                                        $sugerencia = (int)round(pow($sugerencia, 0.8));
                                    }

                                    // Paso 3.6: Decaimiento por disponibilidad en stock central
                                    // Solo aplica cuando el stock no alcanza para cubrir la sugerencia.
                                    // Exponente 1.5: a 50% cobertura reduce ~65%, a 25% reduce ~87%.
                                    // Subir el exponente (ej: 2.0) hace la caida mas brusca; bajarlo (ej: 1.0) la hace lineal.
                                    if ($sugerencia !== null && $sugerencia > 0 && $stock > 0 && $stock < $sugerencia) {
                                        $cobertura = $stock / $sugerencia; // ratio 0..1
                                        $sugerencia = (int)round($sugerencia * pow($cobertura, 1.5));
                                    }

                                    // Paso 4: Capping por stock central y detectar si se cappea
                                    $sugerenciaSinCap = $sugerencia;
                                    $sugerenciaCappada = false;
                                    if ($sugerencia !== null && $stock > 0) {
                                        $sugerenciaCappada = ($sugerencia > $stock);
                                        if ($sugerenciaCappada) {
                                            $sugerencia = $stock;
                                        }
                                    }
                                    // Pre-calcular titulo para evitar referencia a $sugerenciaSinCap cuando es null
                                    $titleSugerencia = $sugerenciaCappada
                                        ? 'Stock central insuficiente. Sugerencia real: ' . $sugerenciaSinCap . ' - Disponible central: ' . $stock
                                        : 'Click para cargar sugerencia';
                                    ?>

                                    <?php if($mostrarStockLocal): ?>
                                    <!-- Stock Local (solo locales propios) -->
                                    <td class="text-center td-stock-local" data-label="Stock local">
                                        <?php
                                        if ($errorConexion) {
                                            echo '<span class="text-muted" style="font-size: 0.9em;" title="' . htmlspecialchars($errorConexion) . '">-</span>';
                                        } elseif ($stockLocalDesconocido) {
                                            echo '<span class="text-muted" style="font-size: 0.9em;">-</span>';
                                        } else {
                                            $stockClass = 'high';
                                            if ($stockLocal < 10) $stockClass = 'low';
                                            elseif ($stockLocal < 50) $stockClass = 'medium';
                                            echo '<span class="stock-badge ' . $stockClass . '" id="stockLocal_' . htmlspecialchars($codigo) . '">' . $stockLocal . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <?php endif; ?>
                                    
                                    <?php if($mostrarVentas30d): ?>
                                    <!-- Ventas 30 días (locales propios y franquicias) -->
                                    <td class="text-center td-ventas" data-label="Ventas 30d">
                                        <?php 
                                        if ($errorConexion) {
                                            echo '<span class="text-muted" style="font-size: 0.9em;" title="' . htmlspecialchars($errorConexion) . '">-</span>';
                                        } elseif ($ventas30Desconocido) {
                                            echo '<span class="text-muted" style="font-size: 0.9em;">-</span>';
                                        } else {
                                            echo '<span id="ventas30_' . htmlspecialchars($codigo) . '">' . $ventas30 . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <?php endif; ?>

                                    <?php if($mostrarSugerencia): ?>
                                    <!-- Sugerencia de compra -->
                                    <td class="text-center td-sugerencia" data-label="Sugerencia">
                                        <?php if ($sugerencia === null): ?>
                                            <span class="text-muted sug-vacia" style="font-size: 0.9em;">-</span>
                                            <span class="m-only m-inline">&mdash;</span>
                                        <?php elseif ($sugerencia === 0): ?>
                                            <span class="sug-vacia" style="color: #999; font-size: 0.9em;">0</span>
                                            <span class="m-only m-inline">&mdash;</span>
                                        <?php else: ?>
                                            <?php // <button> real para teclado y lectores; el reset de pedidos-mobile.css lo deja igual al <span> anterior ?>
                                            <button
                                                type="button"
                                                class="stock-badge sugerencia-badge"
                                                title="<?php echo htmlspecialchars($titleSugerencia); ?>"
                                                data-sugerencia="<?php echo $sugerencia; ?>"
                                                data-codigo="<?php echo htmlspecialchars($codigo); ?>"
                                            >
                                                <span class="m-only m-inline">Cargar </span><?php echo $sugerencia; ?><span class="m-only m-inline"> u.</span>
                                                <?php if ($sugerenciaCappada): ?>
                                                    <span style="font-size: 0.75em; opacity: 0.75;">(<?php echo $sugerencia == 1 ? 'último' : 'últimos'; ?>)</span>
                                                <?php endif; ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    
                                    <!-- Distribución -->
                                    <td class="text-center td-distri" data-label="Distribución">
                                        <?php echo $distri; ?>
                                    </td>

                                    <?php if($mostrarPrecio): ?>
                                    <!-- Precio -->
                                    <td class="text-right td-precio" data-label="Precio">
                                        <span class="price-display">
                                            $<?php echo number_format($precio, 0, ',', '.'); ?>
                                        </span>
                                    </td>
                                    <?php endif; ?>
                                    
                                    <!-- Cantidad (Input) -->
                                    <td class="text-center td-cantidad">
                                        <div class="qty-actions">
                                            <button type="button" class="qty-btn btn-minus" aria-label="Restar uno" <?php echo $disabled ? 'disabled' : ''; ?>>
                                                <i class="fas fa-minus" aria-hidden="true"></i>
                                            </button>
                                            <input
                                                type="number"
                                                inputmode="numeric"
                                                aria-label="Cantidad"
                                                name="cantPed[]"
                                                class="qty-input<?php echo isset($carritoActual[$codigo]) && $carritoActual[$codigo] > 0 ? ' has-value' : ''; ?>"
                                                value="<?php echo $carritoActual[$codigo] ?? 0; ?>"
                                                min="0"
                                                max="<?php echo $stock; ?>"
                                                data-stock="<?php echo $stock; ?>"
                                                data-codigo="<?php echo htmlspecialchars($codigo); ?>"
                                                data-rubro="<?php echo htmlspecialchars($rubro); ?>"
                                                data-precio="<?php echo $precio; ?>"
                                                autocomplete="off"
                                                <?php echo $disabled ? 'disabled' : ''; ?>
                                            >
                                            <button type="button" class="qty-btn btn-plus" aria-label="Sumar uno" <?php echo $disabled ? 'disabled' : ''; ?>>
                                                <i class="fas fa-plus" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Solo celular: despliega rubro, distribución y sugerencia (oculto en escritorio) -->
                                    <td class="m-only td-ver-mas">
                                        <button type="button" class="m-ver-mas" aria-expanded="false">
                                            <span class="m-ver-mas-texto">Ver más</span>
                                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center" style="padding: 40px;">
                                    <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                    <h3>No hay productos disponibles</h3>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Fixed Footer con Totales y Botones -->
            <div class="fixed-footer">
                <div class="footer-content">
                    <div class="footer-totals">
                        <?php // Etiquetas cortas solo en el celular para que todo entre en una línea ?>
                        <div class="total-item">
                            <span class="total-label"><span class="m-hide">ARTÍCULOS</span><span class="m-only m-inline">ARTÍC.</span></span>
                            <span class="total-value" id="totalArticulos"><?php echo $initArticulos; ?></span>
                        </div>
                        <div class="total-item">
                            <span class="total-label"><span class="m-hide">Unidades</span><span class="m-only m-inline">UNID.</span></span>
                            <span class="total-value highlight" id="totalCantidad"><?php echo $initUnidades; ?></span>
                        </div>
                        <?php if($mostrarPrecio): ?>
                        <div class="total-item">
                            <span class="total-label"><span class="m-hide">Total en $</span><span class="m-only m-inline">TOTAL</span></span>
                            <span class="total-value highlight" id="totalPrecio">$0</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="footer-actions">
                        <button type="button" class="btn-clear" onclick="limpiarCantidades()">
                            <i class="fas fa-eraser"></i> Limpiar
                        </button>
                        <button type="button" id="submitBtn" class="btn-submit" onclick="guardarYVerCarrito()" <?php echo $initArticulos === 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-shopping-cart"></i> Ver Carrito
                        </button>
                    </div>
                </div>
            </div>
            
        </form>
    </div>

    <?php include __DIR__ . '/modals/modals.php'; ?>

    <!-- Panel deslizable del carrito (se genera desde el DOM, sin navegacion) -->
    <div class="cart-panel-overlay" id="cartPanelOverlay" onclick="cerrarCarritoPanel()"></div>
    <div class="cart-panel" id="cartPanel">
        <div class="cart-panel-header">
            <h2><i class="fas fa-shopping-cart"></i> Carrito &mdash; <?php echo $tituloPedido; ?></h2>
            <?php // Solo celular: título con el tipo abajo (aparte, para no alterar el h2 de escritorio) ?>
            <div class="m-only cp-titulo-m">
                <span class="cp-titulo-m-principal">Carrito</span>
                <span class="cp-titulo-m-tipo"><?php echo $tituloPedido; ?></span>
            </div>
            <button class="cart-panel-close" onclick="cerrarCarritoPanel()" title="Cerrar" aria-label="Cerrar carrito">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="cart-panel-body" id="cartPanelBody"></div>
        <div class="cart-panel-footer" id="cartPanelFooter" style="display:none">
            <?php // Cada total va en un span con display: contents en escritorio (la fila se ve igual que antes)
                  // y como columna etiqueta/valor en el celular ?>
            <div class="cart-panel-totals">
                <span class="cp-total"><span class="cp-total-lbl">Art&iacute;culos:</span> <strong id="cpTotalArticulos">0</strong></span>
                <span class="cp-total"><span class="cp-total-lbl"><span class="m-hide">&nbsp;&nbsp;</span>Unidades:</span> <strong id="cpTotalUnidades">0</strong></span>
                <span id="cpTotalPrecioWrap" class="cp-total-precio" style="display:none"><span class="cp-total-lbl"><span class="m-hide">&nbsp;&nbsp;</span>Total:</span> <strong id="cpTotalPrecio">$0</strong></span>
            </div>
            <div class="cart-panel-actions">
                <button type="button" class="btn btn-secondary" onclick="exportarCarrito()" title="Exportar carrito a CSV">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
                <button type="button" class="btn btn-danger-outline" onclick="vaciarCarritoPanel()">
                    <i class="fas fa-trash"></i> Vaciar
                </button>
<button type="button" class="btn btn-primary" onclick="cerrarCarritoPanel(); enviarPedido();" id="cartPanelSendBtn">
                    <i class="fas fa-paper-plane"></i> Enviar Pedido
                </button>
            </div>
        </div>
    </div>

    <!-- Variables PHP para JavaScript -->
    <script>
        const APP = {
            suc:              '<?php echo $suc; ?>',
            codClient:        '<?php echo $codClient; ?>',
            tipoPedido:       '<?php echo $_SESSION['tipo_pedido']; ?>',
            depo:             '<?php echo $_SESSION['depo']; ?>',
            cupoCredi:        <?php echo floatval($_SESSION['cupoCredi'] ?? 0); ?>,
            carritoActual:    <?php echo json_encode($carritoActual); ?>,
            mostrarStockLocal: <?php echo $mostrarStockLocal ? 'true' : 'false'; ?>,
            mostrarVentas30d:  <?php echo $mostrarVentas30d  ? 'true' : 'false'; ?>,
            mostrarSugerencia: <?php echo $mostrarSugerencia ? 'true' : 'false'; ?>,
            mostrarPrecio:     <?php echo $mostrarPrecio ? 'true' : 'false'; ?>
        };
    </script>
    <script src="js/pedidos.js?v=<?php echo filemtime(__DIR__.'/js/pedidos.js'); ?>"></script>
    <script src="js/envio.js?v=<?php echo filemtime(__DIR__.'/js/envio.js'); ?>"></script>
    <script src="js/pedidos-mobile.js?v=<?php echo filemtime(__DIR__.'/js/pedidos-mobile.js'); ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</body>
</html>
