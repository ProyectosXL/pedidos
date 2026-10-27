<?php 
session_start(); 

if(!isset($_SESSION['username'])){
    header("Location:../../sistemas/login.php");
    exit;
}

$permiso = $_SESSION['permisos'];
$suc = $_SESSION['numsuc'];
$codClient = $_SESSION['codClient'];
$cod_vended = $_SESSION['vendedor'];
$local = $_SESSION['descLocal'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Pedidos - XL Extra Large</title>
    <link rel="shortcut icon" href="images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/mayoristas.css">
    <link rel="stylesheet" href="css/historial.css">
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
                    <h1>Historial de Pedidos</h1>
                    <p>Consulta y gestiona los pedidos de tus clientes</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <a href="/sistemas/login.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <?php
        require_once $_SERVER['DOCUMENT_ROOT'].'/sistemas/class/conexion.php';
        
        $conn = new Conexion();
        $cid = $conn->conectar('central');
        
        if($cid === false){
            echo '<div class="empty-state">';
            echo '<i class="fas fa-exclamation-triangle"></i>';
            echo '<h3>Error de conexión</h3>';
            echo '<p>No se pudo conectar con la base de datos</p>';
            echo '</div>';
            exit;
        }
        
        $sql = "
            SET DATEFORMAT YMD
            
            SELECT 
                C.RAZON_SOCI, 
                C.COD_CLIENT,
                CAST(A.FECHA_PEDI AS DATE) FECHA, 
                A.NRO_PEDIDO, 
                A.LEYENDA_1, 
                B.CANT 
            FROM GVA21 A
            INNER JOIN (
                SELECT NRO_PEDIDO, CAST(SUM(CANT_PEDID) AS FLOAT) CANT 
                FROM GVA03 
                GROUP BY NRO_PEDIDO
            ) B ON A.NRO_PEDIDO = B.NRO_PEDIDO
            INNER JOIN GVA14 C ON A.COD_CLIENT = C.COD_CLIENT 
            WHERE C.COD_VENDED = ? 
            AND A.FECHA_PEDI > (GETDATE()-60)
            AND A.ESTADO IN (2, 3)
            ORDER BY A.FECHA_PEDI DESC, A.NRO_PEDIDO DESC
        ";
        
        ini_set('max_execution_time', 300);
        $stmt = sqlsrv_query($cid, $sql, array($cod_vended));
        
        if($stmt === false){
            echo '<div class="empty-state">';
            echo '<i class="fas fa-exclamation-triangle"></i>';
            echo '<h3>Error al cargar datos</h3>';
            echo '<p>'.print_r(sqlsrv_errors(), true).'</p>';
            echo '</div>';
            exit;
        }
        
        // Collect data for stats and filters
        $pedidos = array();
        $totalArticulos = 0;
        $clientes = array();
        
        while($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $pedidos[] = $v;
            $totalArticulos += (int)$v['CANT'];
            if(!isset($clientes[$v['COD_CLIENT']])){
                $clientes[$v['COD_CLIENT']] = $v['RAZON_SOCI'];
            }
        }
        
        $totalPedidos = count($pedidos);
        $totalClientes = count($clientes);
        ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Pedidos</div>
                <div class="stat-value" id="totalPedidos"><?php echo $totalPedidos; ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Total Artículos</div>
                <div class="stat-value" id="totalArticulos"><?php echo $totalArticulos; ?></div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">Clientes Activos</div>
                <div class="stat-value" id="totalClientes"><?php echo $totalClientes; ?></div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput">
                        <i class="fas fa-search"></i> Buscar
                    </label>
                    <input 
                        type="text" 
                        id="searchInput" 
                        placeholder="Buscar por cliente, pedido u observaciones..."
                        autocomplete="off"
                    >
                </div>
                <div class="filter-group">
                    <label for="clientFilter">
                        <i class="fas fa-user"></i> Cliente
                    </label>
                    <select id="clientFilter">
                        <option value="">Todos los clientes</option>
                        <?php foreach($clientes as $code => $name): ?>
                            <option value="<?php echo $code; ?>"><?php echo $code; ?> - <?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="dateFilter">
                        <i class="fas fa-calendar"></i> Período
                    </label>
                    <select id="dateFilter">
                        <option value="all">Todos</option>
                        <option value="today">Hoy</option>
                        <option value="week">Última semana</option>
                        <option value="month" selected>Último mes</option>
                    </select>
                </div>
                <button class="btn-filter" id="clearFilters">
                    <i class="fas fa-times"></i> Limpiar
                </button>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list"></i>
                    Lista de Pedidos
                </div>
                <div class="table-actions">
                    <span class="stats-badge">
                        <i class="fas fa-clock"></i>
                        Últimos 60 días
                    </span>
                </div>
            </div>
            
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Nº Pedido</th>
                        <th>Observaciones</th>
                        <th>Cantidad</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($pedidos) > 0): ?>
                        <?php foreach($pedidos as $pedido): ?>
                            <tr 
                                data-client-name="<?php echo strtolower($pedido['RAZON_SOCI']); ?>"
                                data-client-code="<?php echo strtolower($pedido['COD_CLIENT']); ?>"
                                data-order-number="<?php echo strtolower($pedido['NRO_PEDIDO']); ?>"
                                data-observations="<?php echo strtolower($pedido['LEYENDA_1']); ?>"
                                data-date="<?php echo $pedido['FECHA']->format('Y-m-d'); ?>"
                                data-cantidad="<?php echo (int)$pedido['CANT']; ?>"
                            >
                                <td data-label="Cliente">
                                    <div class="client-cell">
                                        <span class="client-name"><?php echo $pedido['RAZON_SOCI']; ?></span>
                                        <span class="client-code"><?php echo $pedido['COD_CLIENT']; ?></span>
                                    </div>
                                </td>
                                <td data-label="Fecha">
                                    <span class="date-cell">
                                        <?php echo $pedido['FECHA']->format('d/m/Y'); ?>
                                    </span>
                                </td>
                                <td data-label="Nº Pedido">
                                    <a href="#" 
                                       onclick="navigateTo('pedidos/detallePed.php', {accion: 'ver_detalle', numero_pedido: '<?php echo $pedido['NRO_PEDIDO']; ?>', sucursal: '<?php echo $pedido['COD_CLIENT']; ?>', tipo: '<?php echo addslashes($pedido['LEYENDA_1']); ?>', origen: 'vendedor'}); return false;"
                                       class="order-number">
                                        <?php echo $pedido['NRO_PEDIDO']; ?>
                                    </a>
                                </td>
                                <td data-label="Observaciones">
                                    <span class="badge badge-primary">
                                        <?php echo $pedido['LEYENDA_1']; ?>
                                    </span>
                                </td>
                                <td data-label="Cantidad">
                                    <span class="qty-badge">
                                        <?php echo (int)$pedido['CANT']; ?>
                                    </span>
                                </td>
                                <td data-label="Acción">
                                    <a href="#" 
                                       onclick="navigateTo('pedidos/detallePed.php', {accion: 'ver_detalle', numero_pedido: '<?php echo $pedido['NRO_PEDIDO']; ?>', sucursal: '<?php echo $pedido['COD_CLIENT']; ?>', tipo: '<?php echo addslashes($pedido['LEYENDA_1']); ?>', origen: 'vendedor'}); return false;"
                                       class="btn-view">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Empty state row (hidden by default) -->
                        <tr id="emptyState" style="display: none;">
                            <td colspan="6">
                                <div class="table-empty">
                                    <i class="fas fa-search"></i>
                                    <h3>No se encontraron resultados</h3>
                                    <p>Intenta ajustar los filtros de búsqueda</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="table-empty">
                                    <i class="fas fa-inbox"></i>
                                    <h3>No hay pedidos registrados</h3>
                                    <p>No se encontraron pedidos en los últimos 60 días</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="js/historial.js"></script>
    <script>
        // Show loading function
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }
    </script>
</body>
</html>
<?php
sqlsrv_free_stmt($stmt);
sqlsrv_close($cid);
?>