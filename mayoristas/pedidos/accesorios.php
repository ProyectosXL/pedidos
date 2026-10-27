<?php 
session_start(); 

if(!isset($_SESSION['username'])){
    header("Location:../../../sistemas/login.php");
    exit;
}

$codClient = $_SESSION['codClient'];
$codVendedor = $_SESSION['codVendedor'] ?? '0';
$nombreCliente = $_SESSION['razonSocial'] ?? '';

// Obtener productos usando la clase
require_once __DIR__ . '/../class/PedidoManager.php';

try {
    $pedidoManager = new PedidoManager();
    $productos = $pedidoManager->obtenerProductosAccesorios(200, $codClient); // 200 = Tipo pedido accesorios
    
    // Obtener rubros únicos para el filtro
    $rubros = array();
    foreach($productos as $producto) {
        $rubro = trim($producto['RUBRO']);
        if($rubro && !in_array($rubro, $rubros)) {
            $rubros[] = $rubro;
        }
    }
    sort($rubros);
    
    // LÓGICA PHP PARA SUGERENCIAS - Preparar TODOS los productos para filtrado dinámico
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
    
    // Filtrar productos TENDENCIA (ES_TENDENCIA = 1) - nuevos con rotación acelerada
    $tendenciaProducts = array_filter($productos, function($p) {
        return isset($p['ES_TENDENCIA']) && $p['ES_TENDENCIA'] == 1;
    });
    usort($tendenciaProducts, function($a, $b) {
        $ventasA = isset($a['UNIDADES_30D']) ? intval($a['UNIDADES_30D']) : 0;
        $ventasB = isset($b['UNIDADES_30D']) ? intval($b['UNIDADES_30D']) : 0;
        return $ventasB - $ventasA;
    });
    
    // Convertir a JSON para JavaScript
    $topProductsJSON = json_encode(array_values($topProducts));
    $nuevosProductsJSON = json_encode(array_values($nuevosProducts));
    $tendenciaProductsJSON = json_encode(array_values($tendenciaProducts));
    
} catch(Exception $e) {
    $error = "Error al cargar productos: " . $e->getMessage();
    $productos = array();
    $rubros = array();
    $topProducts = array();
    $nuevosProducts = array();
    $tendenciaProducts = array();
    $topProductsJSON = '[]';
    $nuevosProductsJSON = '[]';
    $tendenciaProductsJSON = '[]';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Accesorios - <?php echo $codClient; ?><?php echo $nombreCliente ? ' - ' . $nombreCliente : ''; ?></title>
    <link rel="shortcut icon" href="../images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mayoristas.css">
    <link rel="stylesheet" href="../css/cargaPedido.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../css/sweetalert-custom.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Header -->
    <div class="header-container">
        <div class="header-top">
            <div class="logo-section">
                <img src="../images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1>Pedido Accesorios</h1>
                    <p>Cliente: <?php echo $codClient; ?><?php echo $nombreCliente ? ' - ' . $nombreCliente : ''; ?></p>
                </div>
            </div>
            <div class="header-actions">
                <a href="../pedidos.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <a href="../../../sistemas/login.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container" style="max-width: 100%; padding: 20px;">
        
        <?php if(isset($error)): ?>
            <!-- Error Message -->
            <div class="alert-message warning">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            
            <!-- Info Alert -->
            <div class="alert-message info">
                <i class="fas fa-info-circle"></i>
                Ingrese las cantidades deseadas para cada artículo. Los totales se actualizarán automáticamente.
            </div>

            <!-- Filtro de Rubros -->
            <div class="filters-bar" style="margin-bottom: 20px;">
                <div class="filters-grid" style="grid-template-columns: 1fr; max-width: 400px; margin-left: auto;">
                    <select id="filterRubro" class="filter-input">
                        <option value="">🏷️ Todos los rubros</option>
                        <?php foreach($rubros as $rubro): ?>
                            <option value="<?php echo htmlspecialchars($rubro); ?>">
                                <?php echo htmlspecialchars($rubro); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- SECCIÓN DE SUGERENCIAS DINÁMICA -->
            <?php if(count($topProducts) > 0 || count($nuevosProducts) > 0 || count($tendenciaProducts) > 0): ?>
            <div class="suggestions-section">
                <div class="suggestions-header">
                    <h3><i class="fas fa-lightbulb"></i> Accesorios Destacados</h3>
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
                
                <!-- Carrusel dinámico de productos TOP -->
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
                
                <!-- Carrusel dinámico de productos TENDENCIA -->
                <?php if(count($tendenciaProducts) > 0): ?>
                <div class="suggestions-carousel-wrapper <?php echo count($topProducts) == 0 ? 'active' : ''; ?>" data-content="tendencia">
                    <button class="carousel-btn prev">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="suggestions-carousel" id="carousel-tendencia">
                        <!-- Se llenará dinámicamente con JavaScript -->
                    </div>
                    <button class="carousel-btn next">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <div class="carousel-indicators" id="indicators-tendencia"></div>
                </div>
                <?php endif; ?>
                
                <!-- Carrusel dinámico de productos NUEVO -->
                <?php if(count($nuevosProducts) > 0): ?>
                <div class="suggestions-carousel-wrapper <?php echo count($topProducts) == 0 && count($tendenciaProducts) == 0 ? 'active' : ''; ?>" data-content="nuevo">
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
                const allTendenciaProducts = <?php echo $tendenciaProductsJSON; ?>;
            </script>
            <?php endif; ?>

            <!-- Barra de Búsqueda y Controles Mejorada -->
            <div class="advanced-filters-bar">
                <div class="filters-main-controls">
                    <div class="search-box-advanced">
                        <i class="fas fa-search search-icon"></i>
                        <input 
                            type="text" 
                            id="searchProduct" 
                            class="search-input-advanced"
                            placeholder="Buscar por código (ej: XV5SST06ZI05841) o descripción..."
                            autocomplete="off"
                        >
                        <button type="button" class="clear-search-btn" id="clearSearchBtn" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="controls-group">
                        <select id="sortProducts" class="sort-select-advanced">
                            <option value="default">🔄 Orden por defecto</option>
                            <option value="ventas-desc">⭐ Más vendidos</option>
                            <option value="nuevos">✨ Novedades</option>
                            <option value="precio-asc">💰 Menor precio</option>
                            <option value="precio-desc">💵 Mayor precio</option>
                            <option value="stock-desc">📦 Mayor stock</option>
                            <option value="stock-asc">⚠️ Menor stock</option>
                            <option value="az">🔤 A-Z</option>
                            <option value="za">🔤 Z-A</option>
                        </select>
                        
                        <button type="button" class="btn-clear-filters" id="btnClearAllFilters">
                            <i class="fas fa-eraser"></i> Limpiar filtros
                        </button>
                    </div>
                </div>
                
                <!-- Chips de Filtros Activos -->
                <div class="active-filters-chips" id="activeFiltersChips" style="display: none;">
                    <span class="chips-label">Filtros activos:</span>
                    <div class="chips-container" id="chipsContainer"></div>
                </div>
                
                <!-- Contador de Resultados -->
                <div class="results-counter">
                    <i class="fas fa-box-open"></i>
                    <span id="resultsCounter"><?php echo count($productos); ?> productos disponibles</span>
                </div>
            </div>

            <!-- Formulario de Pedido -->
            <form id="formPedido" method="POST" action="../Controller/ProcesarPedidoController.php" onsubmit="return validarPedido();">
                <input type="hidden" name="talon" value="1">
                
                <!-- Product Table -->
                <div class="product-table-container">
                    <div class="product-table-header">
                        <div class="product-table-title">
                            <i class="fas fa-tags"></i>
                            Accesorios Disponibles
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <span class="item-count"><?php echo count($productos); ?> productos</span>
                            <button type="button" id="btnClear" class="btn btn-primary" style="padding: 8px 16px;">
                                <i class="fas fa-eraser"></i> Limpiar
                            </button>
                        </div>
                    </div>
                    
                    <table class="product-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Foto</th>
                                <th style="width: 120px;" class="sortable" data-sort="codigo">
                                    Código <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" data-sort="descripcion">
                                    Descripción <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th style="width: 150px;" class="sortable" data-sort="rubro">
                                    Rubro <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="text-center sortable" style="width: 100px;" data-sort="stock">
                                    Stock <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="text-right sortable" style="width: 100px;" data-sort="precio">
                                    Precio <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="text-center" style="width: 180px;">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($productos) > 0): ?>
                                <?php foreach($productos as $prod): 
                                    $codigo = trim($prod['COD_ARTICU']);
                                    $descripcion = trim($prod['DESCRIPCIO']);
                                    $rubro = trim($prod['RUBRO']);
                                    $stock = intval($prod['CANT_STOCK']);
                                    $precio = floatval($prod['PRECIO_MAYO']);
                                    $foto = substr($codigo, 0, 13) . '.jpg';
                                    
                                    // Datos de ventas
                                    $esTop = isset($prod['ES_TOP']) ? $prod['ES_TOP'] : 0;
                                    $esNuevo = isset($prod['ES_NUEVO']) ? $prod['ES_NUEVO'] : 0;
                                    $esTendencia = isset($prod['ES_TENDENCIA']) ? $prod['ES_TENDENCIA'] : 0;
                                    $categoriaVenta = isset($prod['CATEGORIA_VENTA']) ? $prod['CATEGORIA_VENTA'] : '';
                                    $unidades30d = isset($prod['UNIDADES_30D']) ? intval($prod['UNIDADES_30D']) : 0;
                                    $clientesCompraron = isset($prod['CLIENTES_COMPRARON']) ? intval($prod['CLIENTES_COMPRARON']) : 0;
                                    
                                    // Detectar si es un producto en liquidación
                                    $enLiquidacion = (strpos($descripcion, '-- SALE! --') !== false);
                                    // Limpiar descripción si tiene SALE
                                    if($enLiquidacion) {
                                        $descripcion = str_replace('-- SALE! --', '', $descripcion);
                                        $descripcion = trim($descripcion);
                                    }
                                    
                                    // Determinar clase de stock
                                    $stockClass = 'high';
                                    if($stock < 10) $stockClass = 'low';
                                    elseif($stock < 50) $stockClass = 'medium';
                                ?>
                                    <tr 
                                        data-code="<?php echo strtolower($codigo); ?>"
                                        data-description="<?php echo strtolower($descripcion); ?>"
                                        data-rubro="<?php echo htmlspecialchars($rubro); ?>"
                                        data-ventas="<?php echo $unidades30d; ?>"
                                        data-categoria="<?php echo htmlspecialchars($categoriaVenta); ?>"
                                        data-stock-num="<?php echo $stock; ?>"
                                        data-precio="<?php echo $precio; ?>"
                                        data-es-top="<?php echo $esTop; ?>"
                                        data-es-nuevo="<?php echo $esNuevo; ?>"
                                        data-es-tendencia="<?php echo $esTendencia; ?>"
                                        class="<?php echo $enLiquidacion ? 'producto-liquidacion' : ''; ?>"
                                    >
                                        <!-- Foto -->
                                        <td>
                                            <img 
                                                src="https://app.xl.com.ar/imagenes/<?php echo $foto; ?>" 
                                                alt="<?php echo htmlspecialchars($descripcion); ?>"
                                                class="product-image"
                                                onclick="mostrarImagen('https://app.xl.com.ar/imagenes/<?php echo $foto; ?>')"
                                            >
                                        </td>
                                        
                                        <!-- Código -->
                                        <td>
                                            <span class="product-code"><?php echo htmlspecialchars($codigo); ?></span>
                                            <!-- Input hidden para enviar el código -->
                                            <input type="hidden" name="codArt[]" value="<?php echo htmlspecialchars($codigo); ?>">
                                        </td>
                                        
                                        <!-- Descripción -->
                                        <td>
                                            <span class="product-description">
                                                <?php echo htmlspecialchars($descripcion); ?>
                                                <?php if($enLiquidacion): ?>
                                                    <span class="badge-sale">SALE</span>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        
                                        <!-- Rubro -->
                                        <td>
                                            <span class="rubro-badge"><?php echo htmlspecialchars($rubro); ?></span>
                                            <!-- Input hidden para enviar el rubro -->
                                            <input type="hidden" name="rubro[]" value="<?php echo htmlspecialchars($rubro); ?>">
                                        </td>
                                        
                                        <!-- Stock con BADGES -->
                                        <td class="text-center">
                                            <div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                                <span class="stock-badge <?php echo $stockClass; ?>">
                                                    <?php echo $stock; ?>
                                                </span>
                                                <?php if($esTop == 1): ?>
                                                    <span class="sales-badge hot" 
                                                          data-vendidos="<?php echo $unidades30d; ?>"
                                                          data-clientes="<?php echo $clientesCompraron; ?>">
                                                        🔥
                                                    </span>
                                                <?php elseif($esNuevo == 1): ?>
                                                    <span class="sales-badge new">
                                                        ✨
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <input type="hidden" name="stock[]" value="<?php echo $stock; ?>">
                                        </td>
                                        
                                        <!-- Precio -->
                                        <td class="text-right">
                                            <span class="price-display">
                                                $<?php echo number_format($precio, 0); ?>
                                            </span>
                                        </td>
                                        
                                        <!-- Cantidad (Input) -->
                                        <td class="text-center">
                                            <div class="qty-actions">
                                                <button type="button" class="qty-btn btn-minus">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input 
                                                    type="number" 
                                                    name="cantPed[]" 
                                                    class="qty-input"
                                                    value="0"
                                                    min="0"
                                                    max="<?php echo $stock; ?>"
                                                    data-stock="<?php echo $stock; ?>"
                                                    autocomplete="off"
                                                >
                                                <button type="button" class="qty-btn btn-plus">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="table-empty">
                                        <i class="fas fa-inbox"></i>
                                        <h3>No hay productos disponibles</h3>
                                        <p>No se encontraron productos de accesorios</p>
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
                            <div class="total-item">
                                <span class="total-label">Artículos</span>
                                <span class="total-value" id="totalArticulos">0</span>
                            </div>
                            <div class="total-item">
                                <span class="total-label">Cantidad Total</span>
                                <span class="total-value highlight" id="totalCantidad">0</span>
                            </div>
                            <div class="total-item">
                                <span class="total-label">Total en $</span>
                                <span class="total-value highlight" id="totalPrecio">$0</span>
                            </div>
                        </div>
                        
                        <div class="footer-actions">
                            <button type="button" class="btn-clear" onclick="limpiarCantidades()">
                                <i class="fas fa-eraser"></i> Limpiar
                            </button>
                            <button type="submit" id="submitBtn" class="btn-submit" disabled>
                                <i class="fas fa-paper-plane"></i> Enviar Pedido
                            </button>
                        </div>
                    </div>
                </div>
                
            </form>

        <?php endif; ?>
    </div>

    <!-- Modal para imagen -->
    <div id="imageModal" class="image-modal" onclick="cerrarModal(event)">
        <span class="close-modal" onclick="cerrarModal(event)">&times;</span>
        <img id="modalImage" src="" alt="Producto" onclick="event.stopPropagation()">
    </div>

    <script src="../js/cargaPedido.js"></script>
</body>
</html>
