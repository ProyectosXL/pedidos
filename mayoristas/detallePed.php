<?php 
session_start(); 

if(!isset($_SESSION['username'])){
    header("Location:login.php");
    exit;
}

$permiso = $_SESSION['permisos'];
$suc = $_GET['suc'];
$pedido = $_GET['pedido'];
$tipo = $_GET['tipo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Pedido <?php echo $pedido; ?> - XL Extra Large</title>
    <link rel="shortcut icon" href="images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/mayoristas.css">
    <link rel="stylesheet" href="css/detallePed.css">
</head>
<body>
    <!-- Header -->
    <div class="header-container">
        <div class="header-top">
            <div class="logo-section">
                <img src="images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1>Detalle del Pedido</h1>
                    <p>Información completa del pedido</p>
                </div>
            </div>
            <div class="header-actions">
                <button onclick="printOrder()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <a href="/sistemas/login.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <?php
        try {
            // CORRECTO ✅
			require_once __DIR__.'/class/pedido.php';
            
            $pedidoObj = new Pedido();
            
            // Verificar si el pedido existe
            if(!$pedidoObj->existePedido($pedido)){
                throw new Exception("El pedido no existe");
            }
            
            // Obtener detalle del pedido
            $detalle = $pedidoObj->obtenerDetallePedido($pedido);
            
            $fecha = $detalle['fecha'];
            $items = $detalle['items'];
            $totalItems = $detalle['totalItems'];
            $totalCantidad = $detalle['totalCantidad'];
            
        } catch(Exception $e) {
            echo '<div class="empty-state">';
            echo '<i class="fas fa-exclamation-triangle"></i>';
            echo '<h3>Error al cargar el pedido</h3>';
            echo '<p>'.$e->getMessage().'</p>';
            echo '</div>';
            echo '</div></body></html>';
            exit;
        }
        ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="historial_mayoristas.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Volver al Historial
            </a>
            <button onclick="printOrder()" class="btn-print">
                <i class="fas fa-print"></i> Imprimir Pedido
            </button>
        </div>

        <!-- Order Info Card -->
        <div class="order-info-card">
            <div class="order-info-grid">
                <div class="info-item">
                    <span class="info-label">Número de Pedido</span>
                    <span class="info-value highlight"><?php echo $pedido; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Cliente</span>
                    <span class="info-value"><?php echo $suc; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tipo de Pedido</span>
                    <span class="info-value"><?php echo $tipo; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Fecha</span>
                    <span class="info-value">
                        <?php echo $fecha ? $fecha->format('d/m/Y') : 'N/A'; ?>
                    </span>
                </div>
            </div>
            
            <!-- Totales -->
            <div class="totals-row">
                <div class="total-item">
                    <span class="total-label">
                        <i class="fas fa-boxes"></i> Total de Artículos
                    </span>
                    <span class="total-value" id="totalItems"><?php echo $totalItems; ?></span>
                </div>
                <div class="total-item">
                    <span class="total-label">
                        <i class="fas fa-calculator"></i> Cantidad Total
                    </span>
                    <span class="total-value highlight" id="totalQuantity"><?php echo $totalCantidad; ?></span>
                </div>
            </div>
        </div>

        <?php if($totalItems > 0): ?>
            <!-- Table Container -->
            <div class="detail-table-container">
                <div class="detail-table-header">
                    <div class="detail-table-title">
                        <i class="fas fa-list"></i>
                        Artículos del Pedido
                        <span class="item-count" id="itemCount"><?php echo $totalItems; ?></span>
                    </div>
                    <?php if($totalItems > 5): ?>
                    <div class="search-wrapper" style="max-width: 300px;">
                        <i class="fas fa-search"></i>
                        <input 
                            type="text" 
                            id="searchItems" 
                            placeholder="Buscar artículo..."
                            autocomplete="off"
                        >
                    </div>
                    <?php endif; ?>
                </div>
                
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="text-center">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rowNumber = 1;
                        foreach($items as $item): 
                        ?>
                            <tr 
                                data-code="<?php echo strtolower($item['COD_ARTICU']); ?>"
                                data-description="<?php echo strtolower($item['DESCRIPCIO']); ?>"
                                data-quantity="<?php echo (int)$item['CANT']; ?>"
                            >
                                <td data-label="#">
                                    <span class="row-number"><?php echo $rowNumber; ?></span>
                                </td>
                                <td data-label="Código">
                                    <span class="article-code"><?php echo $item['COD_ARTICU']; ?></span>
                                </td>
                                <td data-label="Descripción">
                                    <span class="article-description"><?php echo $item['DESCRIPCIO']; ?></span>
                                </td>
                                <td data-label="Cantidad" class="text-center">
                                    <span class="qty-value"><?php echo (int)$item['CANT']; ?></span>
                                </td>
                            </tr>
                        <?php 
                        $rowNumber++;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="detail-table-container">
                <div class="detail-empty">
                    <i class="fas fa-inbox"></i>
                    <h3>No hay artículos en este pedido</h3>
                    <p>El pedido no contiene ningún artículo</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="js/detallePed.js"></script>
    <script>
        // Print function
        function printOrder() {
            window.print();
        }
    </script>
</body>
</html>
