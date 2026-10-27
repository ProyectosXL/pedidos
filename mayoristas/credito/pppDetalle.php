<?php 
session_start(); 

if(!isset($_SESSION['username'])){
    header("Location:../../../sistemas/login.php");
    exit;
}

// Verificar si viene POST con selección de cliente
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'ver_detalle'){
    $_SESSION['clientePPP'] = $_POST['cod_cliente'];
    $_SESSION['clientePPPNombre'] = $_POST['razon_social'];
    // Redirect para evitar reenvío de formulario
    header("Location: pppDetalle.php");
    exit;
}

// Si no hay cliente en sesión, redirigir
if(!isset($_SESSION['clientePPP'])){
    header("Location:ppp.php");
    exit;
}

$codCliente = $_SESSION['clientePPP'];
$nombreCliente = $_SESSION['clientePPPNombre'] ?? '';

// Obtener datos usando la clase
require_once __DIR__ . '/../class/Credito.php';

try {
    $credito = new Credito();
    $recibos = $credito->obtenerDetallePPPCliente($codCliente);
    
    // Si no tenemos el nombre, buscarlo
    if(empty($nombreCliente)){
        require_once __DIR__ . '/../../../sistemas/class/conexion.php';
        $conn = new Conexion();
        $cid = $conn->conectar('central');
        $sqlCliente = "SELECT RAZON_SOCI FROM GVA14 WHERE COD_CLIENT = ?";
        $params = array($codCliente);
        $resultCliente = sqlsrv_query($cid, $sqlCliente, $params);
        
        if($resultCliente && $row = sqlsrv_fetch_array($resultCliente, SQLSRV_FETCH_ASSOC)) {
            $nombreCliente = trim($row['RAZON_SOCI']);
            $_SESSION['clientePPPNombre'] = $nombreCliente;
        }
        sqlsrv_close($cid);
    }
    
    // Calcular totales
    $totalImporteRecibo = 0;
    $totalImporteImputado = 0;
    $sumPPP = 0;
    $countPPP = 0;
    
    foreach($recibos as $recibo) {
        $totalImporteRecibo += floatval($recibo['IMPORTE_RECIBO']);
        $totalImporteImputado += floatval($recibo['IMPORTE_IMPUTADO']);
        $sumPPP += floatval($recibo['PPP']);
        $countPPP++;
    }
    
    $pppPromedio = $countPPP > 0 ? $sumPPP / $countPPP : 0;
    
} catch(Exception $e) {
    $error = $e->getMessage();
    $recibos = array();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Pagos - <?php echo htmlspecialchars($nombreCliente); ?> - XL Extra Large</title>
    <link rel="shortcut icon" href="../../../sistemas/images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mayoristas.css">
    <link rel="stylesheet" href="css/pppDetalle.css">
    <script src="../js/navigation.js"></script>
</head>
<body>
    <!-- Header -->
    <div class="header-container">
        <div class="header-top">
            <div class="logo-section">
                <img src="../images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1>Detalle de Pagos - <?php echo htmlspecialchars($nombreCliente); ?></h1>
                    <p>Cliente: <?php echo htmlspecialchars($codCliente); ?></p>
                </div>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <a href="ppp.php?cod=<?php echo $_SESSION['vendedor'] ?? ''; ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        
        <?php if(isset($error)): ?>
            <div class="alert-message warning">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <i class="fas fa-file-invoice-dollar"></i>
                <div>
                    <span class="label">Total Recibos</span>
                    <span class="value"><?php echo count($recibos); ?></span>
                </div>
            </div>
            <div class="summary-card success">
                <i class="fas fa-money-bill-wave"></i>
                <div>
                    <span class="label">Total Imputado</span>
                    <span class="value">$<?php echo number_format($totalImporteImputado, 0, '', '.'); ?></span>
                </div>
            </div>
            <div class="summary-card info">
                <i class="fas fa-calendar-alt"></i>
                <div>
                    <span class="label">PPP Promedio</span>
                    <span class="value"><?php echo number_format($pppPromedio, 0); ?> días</span>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <table class="detalle-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo Comp.</th>
                        <th>Comprobante</th>
                        <th class="text-right">Importe Recibo</th>
                        <th class="text-right">Importe Imputado</th>
                        <th class="text-center">PPP (días)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($recibos) > 0): ?>
                        <?php foreach($recibos as $recibo): 
                            $fecha = $recibo['FECHA'] instanceof DateTime ? $recibo['FECHA']->format('d/m/Y') : $recibo['FECHA'];
                            $tipoComp = trim($recibo['T_COMP']);
                            $nroComp = $recibo['N_COMP']; // NO hacer trim - el espacio es parte del número
                            $importeRecibo = floatval($recibo['IMPORTE_RECIBO']);
                            $importeImputado = floatval($recibo['IMPORTE_IMPUTADO']);
                            $ppp = intval($recibo['PPP']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fecha); ?></td>
                                <td>
                                    <span class="tipo-comp"><?php echo htmlspecialchars($tipoComp); ?></span>
                                </td>
                                <td>
                                    <a href="#" onclick="verDetalleRecibo('<?php echo addslashes($nroComp); ?>', '<?php echo $codCliente; ?>'); return false;" class="recibo-link">
                                        <?php echo htmlspecialchars($nroComp); ?>
                                    </a>
                                </td>
                                <td class="text-right"><?php echo number_format($importeRecibo, 0, '', '.'); ?></td>
                                <td class="text-right"><strong><?php echo number_format($importeImputado, 0, '', '.'); ?></strong></td>
                                <td class="text-center">
                                    <span class="ppp-badge <?php echo $ppp <= 30 ? 'success' : ($ppp <= 60 ? 'warning' : 'danger'); ?>">
                                        <?php echo $ppp; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr class="totals-row">
                            <td colspan="3" class="text-right"><strong>TOTAL</strong></td>
                            <td class="text-right"><strong><?php echo number_format($totalImporteRecibo, 0, '', '.'); ?></strong></td>
                            <td class="text-right"><strong><?php echo number_format($totalImporteImputado, 0, '', '.'); ?></strong></td>
                            <td class="text-center"><strong><?php echo number_format($pppPromedio, 0); ?></strong></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="table-empty">
                                <i class="fas fa-inbox"></i>
                                <h3>No hay recibos</h3>
                                <p>No se encontraron pagos en los últimos 365 días</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="js/pppDetalle.js"></script>
</body>
</html>
