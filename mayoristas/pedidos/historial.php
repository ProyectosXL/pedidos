<?php 
session_start(); 

if(!isset($_SESSION['username'])){
    header("Location:../../../sistemas/login.php");
    exit;
}

$codClient = $_SESSION['codClient'];

// Obtener información del cliente
require_once $_SERVER['DOCUMENT_ROOT'].'/sistemas/class/conexion.php';
$conn = new Conexion();
$cid = $conn->conectar('central');

$nombreCliente = '';
if($cid !== false){
    $sqlCliente = "SELECT RAZON_SOCI FROM GVA14 WHERE COD_CLIENT = ?";
    $stmt = sqlsrv_query($cid, $sqlCliente, array($codClient));
    if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
        $nombreCliente = $row['RAZON_SOCI'];
    }
    sqlsrv_close($cid);
}

// Obtener historial de pedidos
require_once __DIR__ . '/../class/pedido.php';

try {
    $pedidoObj = new Pedido();
    $resultado = $pedidoObj->obtenerHistorialPedidosCliente($codClient, 90);
    
    $pedidos = $resultado['pedidos'];
    $totalPedidos = $resultado['totalPedidos'];
    $totalArticulos = $resultado['totalArticulos'];
    
} catch(Exception $e) {
    $error = $e->getMessage();
    $pedidos = array();
    $totalPedidos = 0;
    $totalArticulos = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Historial de Pedidos - <?php echo $codClient; ?> - XL Extra Large</title>
    <link rel="shortcut icon" href="../images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mayoristas.css">
    <link rel="stylesheet" href="css/historialCliente.css">
    <script src="../js/navigation.js"></script>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    <!-- Header -->
    <div class="header-container">
        <div class="header-top">
            <div class="logo-section">
                <img src="../images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1>Mi Historial de Pedidos</h1>
                    <p>Consulta tus pedidos realizados</p>
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
    <div class="main-container">
        
        <?php if(isset($error)): ?>
            <!-- Error Message -->
            <div class="alert-message warning">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Client Info Banner -->
        <div class="client-info-banner">
            <h2>
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($codClient); ?>
            </h2>
            <p><?php echo htmlspecialchars($nombreCliente); ?></p>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total de Pedidos (90 días)</div>
                <div class="stat-value" id="totalPedidos"><?php echo $totalPedidos; ?></div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-label">Total de Artículos</div>
                <div class="stat-value" id="totalArticulos"><?php echo $totalArticulos; ?></div>
            </div>
        </div>

        <?php if($totalPedidos > 0): ?>
            
            <!-- Period Selector -->
            <div class="period-selector">
                <button class="period-btn" onclick="cambiarPeriodo('hoy')">
                    <i class="fas fa-calendar-day"></i> Hoy
                </button>
                <button class="period-btn" onclick="cambiarPeriodo('semana')">
                    <i class="fas fa-calendar-week"></i> Última Semana
                </button>
                <button class="period-btn active" onclick="cambiarPeriodo('mes')">
                    <i class="fas fa-calendar-alt"></i> Último Mes
                </button>
                <button class="period-btn" onclick="cambiarPeriodo('trimestre')">
                    <i class="fas fa-calendar"></i> Último Trimestre
                </button>
                <button class="period-btn" onclick="cambiarPeriodo('todos')">
                    <i class="fas fa-infinity"></i> Todos
                </button>
            </div>

            <!-- Search Bar -->
            <div class="filters-container">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input 
                        type="text" 
                        id="searchHistory" 
                        placeholder="Buscar por número de pedido u observaciones..."
                        autocomplete="off"
                    >
                </div>
                
                <button onclick="exportarExcel()" class="btn btn-primary" style="margin-left: 10px;">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
            </div>

            <!-- History Table -->
            <div class="table-container">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Fecha</th>
                            <th style="width: 180px;">Número de Pedido</th>
                            <th>Observaciones</th>
                            <th class="text-center" style="width: 100px;">Cantidad</th>
                            <th class="text-center" style="width: 120px;">Estado</th>
                            <th class="text-center" style="width: 140px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pedidos as $pedido): 
                            $nroPedido = $pedido['NRO_PEDIDO']; // No usar trim() - el espacio es parte del número
                            $fecha = $pedido['FECHA'];
                            $observaciones = trim($pedido['OBSERVACIO']);
                            $totalCantidad = intval($pedido['TOTAL_CANTIDAD']);
                            $estado = trim($pedido['ESTADO']);
                            
                            // Determinar badge de estado
                            $estadoBadge = '';
                            $estadoTexto = '';
                            switch($estado) {
                                case '2':
                                    $estadoBadge = 'warning';
                                    $estadoTexto = 'Pendiente';
                                    break;
                                case '3':
                                    $estadoBadge = 'success';
                                    $estadoTexto = 'Procesado';
                                    break;
                                default:
                                    $estadoBadge = 'secondary';
                                    $estadoTexto = 'Estado ' . $estado;
                            }
                            
                            // Formatear fecha para atributo data
                            $fechaData = $fecha ? $fecha->format('Y-m-d') : '';
                        ?>
                            <tr 
                                data-fecha="<?php echo $fechaData; ?>"
                                data-pedido="<?php echo strtolower($nroPedido); ?>"
                                data-observaciones="<?php echo strtolower($observaciones); ?>"
                                data-cantidad="<?php echo $totalCantidad; ?>"
                            >
                                <td data-label="Fecha">
                                    <span class="date-cell">
                                        <?php echo $fecha ? $fecha->format('d/m/Y') : 'N/A'; ?>
                                    </span>
                                </td>
                                
                                <td data-label="Nº Pedido">
                                    <a href="#" 
                                       onclick="verDetallePedido('<?php echo $nroPedido; ?>', '<?php echo $codClient; ?>', '<?php echo addslashes($observaciones); ?>'); return false;"
                                       class="order-number">
                                        <?php echo htmlspecialchars($nroPedido); ?>
                                    </a>
                                </td>
                                
                                <td data-label="Observaciones">
                                    <span class="badge badge-primary">
                                        <?php echo htmlspecialchars($observaciones); ?>
                                    </span>
                                </td>
                                
                                <td data-label="Cantidad">
                                    <span class="qty-badge">
                                        <?php echo $totalCantidad; ?>
                                    </span>
                                </td>
                                
                                <td data-label="Estado">
                                    <span class="badge badge-<?php echo $estadoBadge; ?>">
                                        <?php echo $estadoTexto; ?>
                                    </span>
                                </td>
                                
                                <td data-label="Acción">
                                    <a href="#"
                                        onclick="verDetallePedido('<?php echo $nroPedido; ?>', '<?php echo $codClient; ?>', '<?php echo addslashes($observaciones); ?>'); return false;"
                                        class="btn-view">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Empty State Row (hidden by default) -->
                        <tr id="emptyState" style="display: none;">
                            <td colspan="6">
                                <div class="table-empty">
                                    <i class="fas fa-search"></i>
                                    <h3>No se encontraron pedidos</h3>
                                    <p>No hay pedidos que coincidan con los filtros seleccionados</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            
            <!-- Empty State -->
            <div class="table-empty" style="padding: 60px 20px; text-align: center;">
                <i class="fas fa-inbox" style="font-size: 4rem; color: #ccc; margin-bottom: 20px;"></i>
                <h3>No tienes pedidos registrados</h3>
                <p>Aún no has realizado ningún pedido en los últimos 90 días</p>
                <a href="../pedidos.php?directo=1" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Realizar mi Primer Pedido
                </a>
            </div>

        <?php endif; ?>
    </div>

    <script src="js/historial_cliente.js"></script>
</body>
</html>