<?php 
session_start(); 

if(!isset($_SESSION['username'])){
    header("Location:../../../sistemas/login.php");
    exit;
}

// Verificar si viene POST con selección de recibo
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'ver_recibo'){
    $_SESSION['numeroRecibo'] = $_POST['numero_recibo'];
    $_SESSION['codClienteRecibo'] = $_POST['cod_cliente'];
    // Redirect para evitar reenvío de formulario
    header("Location: pppDetalleRec.php");
    exit;
}

// Si no hay recibo en sesión, redirigir
if(!isset($_SESSION['numeroRecibo'])){
    header("Location:ppp.php");
    exit;
}

$numeroRecibo = $_SESSION['numeroRecibo'];
$codCliente = $_SESSION['codClienteRecibo'] ?? $_SESSION['clientePPP'] ?? '';

// Obtener datos usando la clase
require_once __DIR__ . '/../class/Credito.php';

try {
    $credito = new Credito();
    $imputaciones = $credito->obtenerDetalleRecibo($numeroRecibo);
    
    // Debug
    if(empty($imputaciones)) {
        $error = "No se encontraron imputaciones para el recibo: " . htmlspecialchars($numeroRecibo);
    }
    
    // Calcular totales
    $totalVencimiento = 0;
    $totalCancelado = 0;
    
    foreach($imputaciones as $imp) {
        $totalVencimiento += floatval($imp['IMP_VTO']);
        $totalCancelado += floatval($imp['IMP_CAN']);
    }
    
} catch(Exception $e) {
    $error = $e->getMessage();
    $imputaciones = array();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Recibo - <?php echo htmlspecialchars($numeroRecibo); ?> - XL Extra Large</title>
    <link rel="shortcut icon" href="../../../sistemas/images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mayoristas.css">
    <link rel="stylesheet" href="css/pppDetalleRec.css">
    <script src="../js/navigation.js"></script>
</head>
<body>
    <!-- Header -->
    <div class="header-container">
        <div class="header-top">
            <div class="logo-section">
                <img src="../images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1>Detalle de Recibo - Nº <?php echo htmlspecialchars($numeroRecibo); ?></h1>
                    <p>Imputaciones y aplicaciones del recibo</p>
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
                <i class="fas fa-receipt"></i>
                <div>
                    <span class="label">Total Imputaciones</span>
                    <span class="value"><?php echo count($imputaciones); ?></span>
                </div>
            </div>
            <div class="summary-card info">
                <i class="fas fa-file-invoice"></i>
                <div>
                    <span class="label">Total a Vencer</span>
                    <span class="value">$<?php echo number_format($totalVencimiento, 2, ',', '.'); ?></span>
                </div>
            </div>
            <div class="summary-card success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <span class="label">Total Cancelado</span>
                    <span class="value">$<?php echo number_format($totalCancelado, 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <table class="recibo-table">
                <thead>
                    <tr>
                        <th>Fecha Vencimiento</th>
                        <th>Fecha Cancelación</th>
                        <th>Tipo Comp.</th>
                        <th>Nº Comprobante</th>
                        <th class="text-right">Imp. a Vencer</th>
                        <th class="text-right">Imp. Cancelado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($imputaciones) > 0): ?>
                        <?php foreach($imputaciones as $imp): 
                            $fechaVto = $imp['FECHA_VTO'] instanceof DateTime ? $imp['FECHA_VTO']->format('d/m/Y') : $imp['FECHA_VTO'];
                            $fechaCancel = $imp['F_COMP_CAN'] instanceof DateTime ? $imp['F_COMP_CAN']->format('d/m/Y') : $imp['F_COMP_CAN'];
                            $tipoComp = trim($imp['T_COMP']);
                            $nroComp = trim($imp['N_COMP']);
                            $impVto = floatval($imp['IMP_VTO']);
                            $impCan = floatval($imp['IMP_CAN']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fechaVto); ?></td>
                                <td><?php echo htmlspecialchars($fechaCancel); ?></td>
                                <td>
                                    <span class="tipo-comp"><?php echo htmlspecialchars($tipoComp); ?></span>
                                </td>
                                <td>
                                    <span class="comp-number"><?php echo htmlspecialchars($nroComp); ?></span>
                                </td>
                                <td class="text-right"><?php echo number_format($impVto, 2, ',', '.'); ?></td>
                                <td class="text-right"><strong><?php echo number_format($impCan, 2, ',', '.'); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr class="totals-row">
                            <td colspan="4" class="text-right"><strong>TOTAL</strong></td>
                            <td class="text-right"><strong><?php echo number_format($totalVencimiento, 2, ',', '.'); ?></strong></td>
                            <td class="text-right"><strong><?php echo number_format($totalCancelado, 2, ',', '.'); ?></strong></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="table-empty">
                                <i class="fas fa-inbox"></i>
                                <h3>No hay imputaciones</h3>
                                <p>No se encontraron imputaciones para este recibo</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="js/pppDetalleRec.js"></script>
</body>
</html>
