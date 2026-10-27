<?php 
session_start(); 

if(!isset($_SESSION['username'])){
    header("Location:../../../sistemas/login.php");
    exit;
}

// Verificar si viene POST con selección de cliente
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'ver_cheques'){
    $_SESSION['clienteCheques'] = $_POST['cod_cliente'];
    $_SESSION['clienteChequesNombre'] = $_POST['razon_social'];
    // Redirect para evitar reenvío de formulario
    header("Location: pppCheque.php");
    exit;
}

// Si no hay cliente en sesión, redirigir
if(!isset($_SESSION['clienteCheques'])){
    header("Location:ppp.php");
    exit;
}

$codCliente = $_SESSION['clienteCheques'];
$nombreCliente = $_SESSION['clienteChequesNombre'] ?? '';

// Obtener datos usando la clase
require_once __DIR__ . '/../class/Credito.php';

try {
    $credito = new Credito();
    $cheques = $credito->obtenerChequesCliente($codCliente);
    
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
            $_SESSION['clienteChequesNombre'] = $nombreCliente;
        }
        sqlsrv_close($cid);
    }
    
    // Calcular total
    $totalCheques = 0;
    foreach($cheques as $cheque) {
        $totalCheques += floatval($cheque['IMPORTE_CH']);
    }
    
} catch(Exception $e) {
    $error = $e->getMessage();
    $cheques = array();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Cheques - <?php echo htmlspecialchars($nombreCliente); ?> - XL Extra Large</title>
    <link rel="shortcut icon" href="../../../sistemas/images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mayoristas.css">
    <link rel="stylesheet" href="css/pppCheque.css">
    <script src="../js/navigation.js"></script>
</head>
<body>
    <!-- Header -->
    <div class="header-container">
        <div class="header-top">
            <div class="logo-section">
                <img src="../images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1>Detalle de Cheques - <?php echo htmlspecialchars($nombreCliente); ?></h1>
                    <p>Cliente: <?php echo htmlspecialchars($codCliente); ?></p>
                </div>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button onclick="window.history.back()" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
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
                <i class="fas fa-money-check"></i>
                <div>
                    <span class="label">Total Cheques</span>
                    <span class="value"><?php echo count($cheques); ?></span>
                </div>
            </div>
            <div class="summary-card success">
                <i class="fas fa-dollar-sign"></i>
                <div>
                    <span class="label">Importe Total</span>
                    <span class="value">$<?php echo number_format($totalCheques, 0, '', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <table class="cheques-table">
                <thead>
                    <tr>
                        <th>Fecha Cheque</th>
                        <th>Nº Recibo</th>
                        <th class="text-right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($cheques) > 0): ?>
                        <?php foreach($cheques as $cheque): 
                            $fechaCheque = $cheque['FECHA_CHEQ'] instanceof DateTime ? $cheque['FECHA_CHEQ']->format('d/m/Y') : $cheque['FECHA_CHEQ'];
                            $nroRecibo = trim($cheque['N_COMP_REC']);
                            $importe = floatval($cheque['IMPORTE_CH']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fechaCheque); ?></td>
                                <td>
                                    <span class="recibo-badge"><?php echo htmlspecialchars($nroRecibo); ?></span>
                                </td>
                                <td class="text-right">
                                    <strong><?php echo number_format($importe, 0, '', '.'); ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr class="totals-row">
                            <td colspan="2" class="text-right"><strong>TOTAL CHEQUES</strong></td>
                            <td class="text-right"><strong><?php echo number_format($totalCheques, 0, '', '.'); ?></strong></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="table-empty">
                                <i class="fas fa-inbox"></i>
                                <h3>No hay cheques</h3>
                                <p>No se encontraron cheques activos para este cliente</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="js/pppCheque.js"></script>
</body>
</html>