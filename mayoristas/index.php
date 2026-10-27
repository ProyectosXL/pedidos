<?php 
session_start(); 
if(!isset($_SESSION['username'])){
    header("Location:../../sistemas/login.php");
    exit;
}

$permiso = $_SESSION['permisos'];
$local = $_SESSION['descLocal'];
$vendedor = $_SESSION['vendedor'];
$esVendedor = ($vendedor != '0' && $_SESSION['tipo'] != 'MAYORISTA');
$esMayorista = ($_SESSION['tipo'] == 'MAYORISTA');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $esVendedor ? 'Seleccionar Cliente' : 'Pedidos Mayoristas'; ?> - XL Extra Large</title>
    <link rel="shortcut icon" href="images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/mayoristas.css">
    <link rel="stylesheet" href="css/index.css">
    <script src="js/navigation.js"></script>
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
                <img src="images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1>Bienvenido, <?php echo $local; ?></h1>
                    <p><?php echo $esVendedor ? 'Gestión de Clientes Mayoristas' : 'Portal de Pedidos'; ?></p>
                </div>
            </div>
            <div class="header-actions">
                <a href="/sistemas/login.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </div>
        <?php if($esVendedor): ?>
            <?php
            require_once $_SERVER['DOCUMENT_ROOT'].'/sistemas/class/conexion.php';
            $conn = new Conexion();
            $cid = $conn->conectar('central');
            
            if($cid === false){
                die("<div class='alert alert-danger'>Error al conectar con la base de datos</div>");
            }
            
            $sqlCount = "
                SELECT COUNT(*) as total 
                FROM GVA14 
                WHERE COD_VENDED = ? 
                AND FECHA_INHA = '1800-01-01' 
                AND COD_CLIENT LIKE 'MA%'
            ";
            
            $stmtCount = sqlsrv_query($cid, $sqlCount, array($vendedor));
            $totalClientes = 0;
            if($stmtCount && $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)){
                $totalClientes = $row['total'];
            }
            ?>
            <div class="stats-badge">
                <i class="fas fa-users"></i>
                <span><?php echo $totalClientes; ?> clientes activos</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <?php if($esMayorista): ?>
            <!-- Cliente Mayorista - Acceso Directo -->
            <h2 class="section-title">
                <i class="fas fa-shopping-cart"></i>
                ¿Qué deseas hacer hoy?
            </h2>
            <div class="quick-actions">
                <a href="pedidos.php" class="action-card">
                    <i class="fas fa-boxes"></i>
                    <h3>Realizar Pedido</h3>
                    <p>Genera nuevos pedidos de productos</p>
                </a>
                <a href="pedidos/historial.php" class="action-card secondary">
                    <i class="fas fa-history"></i>
                    <h3>Ver Historial</h3>
                    <p>Consulta tus pedidos anteriores</p>
                </a>
            </div>
        <?php else: ?>
            <!-- Vendedor Mayorista - Layout de 2 Columnas -->
            <div class="two-column-layout">
                <!-- Columna Izquierda: Búsqueda + Clientes -->
                <div class="left-column">
                    <div class="search-section">
                        <h2 class="section-title">
                            <i class="fas fa-search"></i>
                            Buscar Cliente
                        </h2>
                        <div class="search-wrapper">
                            <i class="fas fa-search"></i>
                            <input 
                                type="text" 
                                id="searchInput" 
                                placeholder="Buscar por código o nombre del cliente..."
                                autocomplete="off"
                            >
                            <button class="clear-search" id="clearSearch">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <h2 class="section-title">
                        <i class="fas fa-users"></i>
                        Clientes Disponibles
                    </h2>

                    <div class="clients-grid" id="clientsGrid">
                <?php
                $sql = "
                    SELECT COD_CLIENT, RAZON_SOCI 
                    FROM GVA14
                    WHERE COD_VENDED = ?
                    AND COD_CLIENT LIKE 'MA%'
                    AND FECHA_INHA = '1800-01-01'
                    ORDER BY RAZON_SOCI
                ";
                
                $stmt = sqlsrv_query($cid, $sql, array($vendedor));
                
                if($stmt === false){
                    echo '<div class="empty-state">';
                    echo '<i class="fas fa-exclamation-triangle"></i>';
                    echo '<h3>Error al cargar clientes</h3>';
                    echo '<p>'.print_r(sqlsrv_errors(), true).'</p>';
                    echo '</div>';
                } else {
                    $hayClientes = false;
                    while($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
                        $hayClientes = true;
                ?>
                        <a href="#" 
                           onclick="seleccionarCliente('<?php echo $v['COD_CLIENT']; ?>', '<?php echo addslashes($v['RAZON_SOCI']); ?>'); return false;"
                           class="client-card" 
                           data-code="<?php echo strtolower($v['COD_CLIENT']); ?>"
                           data-name="<?php echo strtolower($v['RAZON_SOCI']); ?>">
                            <div class="client-header">
                                <span class="client-code"><?php echo $v['COD_CLIENT']; ?></span>
                                <i class="fas fa-store client-icon"></i>
                            </div>
                            <h3 class="client-name"><?php echo $v['RAZON_SOCI']; ?></h3>
                            <div class="client-footer">
                                <span class="client-type">
                                    <i class="fas fa-tag"></i>
                                    Mayorista
                                </span>
                                <i class="fas fa-arrow-right client-arrow"></i>
                            </div>
                        </a>
                <?php
                    }
                    
                    if(!$hayClientes){
                        echo '<div class="empty-state">';
                        echo '<i class="fas fa-inbox"></i>';
                        echo '<h3>No hay clientes disponibles</h3>';
                        echo '<p>No se encontraron clientes asignados a este vendedor.</p>';
                        echo '</div>';
                    }
                    
                    sqlsrv_free_stmt($stmt);
                }
                
                sqlsrv_close($cid);
                ?>
                    </div>

                    <!-- Estado vacío para búsqueda sin resultados -->
                    <div class="empty-state" id="emptySearch" style="display: none;">
                        <i class="fas fa-search-minus"></i>
                        <h3>No se encontraron resultados</h3>
                        <p>Intenta con otro término de búsqueda</p>
                    </div>
                </div>

                <!-- Columna Derecha: Cards de Acceso Rápido -->
                <div class="right-column">
                    <div class="quick-access-cards">
                        <!-- Card Estado de Cuenta -->
                        <a href="#" onclick="verEstadoCuenta('<?php echo $vendedor; ?>'); return false;" class="quick-card quick-card-green">
                            <div class="card-icon-wrapper">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <h3 class="card-title">Estado de Cuenta</h3>
                            <p class="card-description">Consulta el crédito disponible, deudas totales, saldos vencidos y límites de crédito de todos tus clientes en tiempo real</p>
                            <div class="card-stats">
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $totalClientes; ?> Clientes</span>
                                </div>
                            </div>
                            <div class="card-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>

                        <!-- Card Historial de Pedidos -->
                        <a href="historial_mayoristas.php" class="quick-card quick-card-blue">
                            <div class="card-icon-wrapper">
                                <i class="fas fa-history"></i>
                            </div>
                            <h3 class="card-title">Historial de Pedidos</h3>
                            <p class="card-description">Accede al historial completo de pedidos realizados por tus clientes, con filtros por período, búsqueda avanzada y estadísticas detalladas</p>
                            <div class="card-stats">
                                <div class="stat-item">
                                    <i class="fas fa-box"></i>
                                    <span>Todos los pedidos</span>
                                </div>
                            </div>
                            <div class="card-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="js/index.js"></script>
</body>
</html>