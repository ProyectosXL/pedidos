<?php 
session_start(); 

if(!isset($_SESSION['username'])){
    header("Location:../../../sistemas/login.php");
    exit;
}

// Verificar si viene POST con selección de pedido
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'ver_detalle'){
    $_SESSION['numeroPedido'] = $_POST['numero_pedido'];
    $_SESSION['sucursalPedido'] = $_POST['sucursal'];
    $_SESSION['tipoPedido'] = $_POST['tipo'];
    $_SESSION['origenDetalle'] = $_POST['origen'] ?? 'cliente'; // 'cliente' o 'vendedor'
    // Redirect para evitar reenvío de formulario
    header("Location: detallePed.php");
    exit;
}

// Si no hay pedido en sesión, redirigir
if(!isset($_SESSION['numeroPedido']) || !isset($_SESSION['sucursalPedido'])){
    header("Location: historial.php");
    exit;
}

$codClient = $_SESSION['codClient'];
$nombreCliente = $_SESSION['razonSocial'] ?? '';

$suc = $_SESSION['sucursalPedido'];
$pedido = $_SESSION['numeroPedido'];
$tipo = $_SESSION['tipoPedido'] ?? '';
$origen = $_SESSION['origenDetalle'] ?? 'cliente';

// Determinar URL de retorno según el origen
$urlVolver = ($origen === 'vendedor') ? '../historial_mayoristas.php' : 'historial.php';

require_once __DIR__ . '/../class/pedido.php';

try {
    $pedidoObj = new Pedido();
    $detalle = $pedidoObj->obtenerDetalleCompletoPedido($pedido, $suc);
    
    $articulos = $detalle['articulos'];
    $fechaPedido = $detalle['fechaPedido'];
    $totalCantidad = $detalle['totalCantidad'];
    
} catch(Exception $e) {
    $error = $e->getMessage();
    $articulos = array();
    $fechaPedido = null;
    $totalCantidad = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Pedido <?php echo htmlspecialchars($pedido); ?> - XL Extra Large</title>
    <link rel="shortcut icon" href="../images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mayoristas.css">
    <link rel="stylesheet" href="css/detallePedido.css">
    <script src="../js/navigation.js"></script>
</head>
<body>
    <!-- Header -->
    <div class="header-container">
        <div class="header-top">
            <div class="logo-section">
                <img src="../images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1>Detalle del Pedido</h1>
                    <p>Información completa de tu pedido</p>
                </div>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <a href="<?php echo $urlVolver; ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        
        <?php if(isset($error)): ?>
            <!-- Error Message -->
            <div class="alert-message warning">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Pedido Info Card -->
        <div class="pedido-info-card">
            <div class="info-row">
                <div class="info-item">
                    <i class="fas fa-hashtag"></i>
                    <div>
                        <span class="info-label">Número de Pedido</span>
                        <span class="info-value"><?php echo htmlspecialchars($pedido); ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-calendar"></i>
                    <div>
                        <span class="info-label">Fecha</span>
                        <span class="info-value"><?php echo $fechaPedido ? $fechaPedido->format('d/m/Y') : 'N/A'; ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <span class="info-label">Cliente</span>
                        <span class="info-value"><?php echo htmlspecialchars($codClient); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="summary-card">
            <h3><i class="fas fa-chart-bar"></i> Resumen del Pedido</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <i class="fas fa-box summary-icon"></i>
                    <div>
                        <span class="summary-label">Total de Artículos</span>
                        <span class="summary-value"><?php echo count($articulos); ?></span>
                    </div>
                </div>
                <div class="summary-item">
                    <i class="fas fa-cubes summary-icon"></i>
                    <div>
                        <span class="summary-label">Cantidad Total</span>
                        <span class="summary-value"><?php echo intval($totalCantidad); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if(count($articulos) > 0): ?>
            
            <!-- Articles Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-box"></i> Artículos del Pedido</h3>
                    <button onclick="exportarPedido()" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Exportar
                    </button>
                </div>
                
                <div class="table-container">
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;" class="text-center">#</th>
                                <th style="width: 150px;">Código</th>
                                <th class="text-center">Descripción</th>
                                <th style="width: 120px;" class="text-center">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $fila = 1;
                            foreach($articulos as $articulo): 
                            ?>
                                <tr>
                                    <td data-label="#" class="text-center">
                                        <span class="numero-fila"><?php echo $fila++; ?></span>
                                    </td>
                                    <td data-label="Código">
                                        <span class="codigo-producto">
                                            <?php echo htmlspecialchars($articulo['COD_ARTICU']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Descripción" class="text-center">
                                        <?php echo htmlspecialchars($articulo['DESCRIPCIO']); ?>
                                    </td>
                                    <td data-label="Cantidad" class="text-center">
                                        <span class="cantidad-badge">
                                            <?php echo intval($articulo['CANT']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            
            <!-- Empty State -->
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No hay artículos en este pedido</h3>
                <p>No se encontraron artículos asociados a este pedido</p>
            </div>
            
        <?php endif; ?>
    </div>

    <script src="js/detallePedido.js"></script>
</body>
</html>