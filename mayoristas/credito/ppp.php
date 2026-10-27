<?php 
session_start(); 

if(!isset($_SESSION['username'])){
    header("Location:../../../sistemas/login.php");
    exit;
}

// Verificar si viene POST con selección de vendedor
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'listar_clientes'){
    $_SESSION['vendedorPPP'] = $_POST['cod_vendedor'];
    // Redirect para evitar reenvío de formulario
    header("Location: ppp.php");
    exit;
}

$codVendedor = $_SESSION['vendedorPPP'] ?? $_SESSION['vendedor'] ?? '0';
$vendedorNombre = $_SESSION['descLocal'] ?? '';

// Obtener datos usando la clase
require_once __DIR__ . '/../class/Credito.php';

try {
    $credito = new Credito();
    $clientes = $credito->obtenerCreditoClientes($codVendedor);
    
    // Calcular totales
    $totalSaldoCC = 0;
    $totalVencidas = 0;
    $totalAVencer = 0;
    $totalCheques = 0;
    $totalCheques10 = 0;
    $totalDeuda = 0;
    $totalDisponible = 0;
    
    foreach($clientes as $cliente) {
        $totalSaldoCC += floatval($cliente['SALDO_CC']);
        $totalVencidas += floatval($cliente['VENCIDAS']);
        $totalAVencer += floatval($cliente['A_VENCER']);
        $totalCheques += floatval($cliente['CHEQUE']);
        $totalCheques10 += floatval($cliente['CHEQUES_10_DIAS']);
        $totalDeuda += floatval($cliente['TOTAL_DEUDA']);
        $totalDisponible += floatval($cliente['TOTAL_DISPONIBLE']);
    }
    
} catch(Exception $e) {
    $error = $e->getMessage();
    $clientes = array();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Cuentas - Vendedor: <?php echo $vendedorNombre; ?> - XL Extra Large</title>
    <link rel="shortcut icon" href="../../../sistemas/images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mayoristas.css">
    <link rel="stylesheet" href="css/credito.css">
    <script src="../js/navigation.js"></script>
</head>
<body>
    <!-- Header -->
    <div class="header-container">
        <div class="header-top">
            <div class="logo-section">
                <img src="../images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1>Estado de Cuentas - Vendedor: <?php echo $vendedorNombre; ?></h1>
                    <p>Gestión de crédito y cobranzas de clientes mayoristas</p>
                </div>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <a href="../index.php" class="btn btn-primary">
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
                <i class="fas fa-users"></i>
                <div>
                    <span class="label">Total Clientes</span>
                    <span class="value"><?php echo count($clientes); ?></span>
                </div>
            </div>
            <div class="summary-card danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <span class="label">Deuda Total</span>
                    <span class="value">$<?php echo number_format($totalDeuda, 0, '', '.'); ?></span>
                </div>
            </div>
            <div class="summary-card warning">
                <i class="fas fa-clock"></i>
                <div>
                    <span class="label">Vencidas</span>
                    <span class="value">$<?php echo number_format($totalVencidas, 0, '', '.'); ?></span>
                </div>
            </div>
            <div class="summary-card success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <span class="label">Disponible</span>
                    <span class="value">$<?php echo number_format($totalDisponible, 0, '', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- Search Filter -->
        <div class="filters-bar">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input 
                    type="text" 
                    id="searchClient" 
                    placeholder="Buscar por cliente o razón social..."
                    autocomplete="off"
                >
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <table class="credito-table">
                <thead>
                    <tr>
                        <th class="col-cliente sortable" data-sort="cliente">
                            <i class="fas fa-user"></i> Cliente <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="col-razon sortable" data-sort="razon">
                            <i class="fas fa-building"></i> Razón Social <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="text-center col-ppp sortable" data-sort="ppp">
                            PPP<br>12 meses <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="text-right col-cupo sortable" data-sort="cupo">
                            Cupo<br>Crédito <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="text-right col-saldo sortable" data-sort="saldo">
                            Saldo<br>CC <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="text-right col-vencidas sortable" data-sort="vencidas">
                            Vencidas <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="text-center col-pedidos sortable" data-sort="pedidos">
                            Pedidos<br>Abiertos <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="text-right col-cheques sortable" data-sort="cheques">
                            Total<br>Cheques <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="text-right col-cheques10 sortable" data-sort="cheques10">
                            Cheques<br>10 Días <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="text-right col-deuda sortable" data-sort="deuda">
                            Total<br>Deuda <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th class="text-right col-disponible sortable" data-sort="disponible">
                            Disponible <i class="fas fa-sort sort-icon"></i>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($clientes) > 0): ?>
                        <?php foreach($clientes as $cliente): 
                            $codCliente = trim($cliente['COD_CLIENTE']);
                            $razonSocial = trim($cliente['RAZON_SOCIAL']);
                            $plazo = trim($cliente['PLAZO']);
                            $conMora = ($plazo == 'NO');
                        ?>
                            <tr data-client="<?php echo strtolower($codCliente . ' ' . $razonSocial); ?>"
                                data-cliente="<?php echo strtolower($codCliente); ?>"
                                data-razon="<?php echo strtolower($razonSocial); ?>"
                                data-ppp="<?php echo intval($cliente['PPP']); ?>"
                                data-cupo="<?php echo floatval($cliente['CUPO_CRED']); ?>"
                                data-saldo="<?php echo floatval($cliente['SALDO_CC']); ?>"
                                data-vencidas="<?php echo floatval($cliente['VENCIDAS']); ?>"
                                data-pedidos="<?php echo intval($cliente['PEDIDOS_ABIERTOS']); ?>"
                                data-cheques="<?php echo floatval($cliente['CHEQUE']); ?>"
                                data-cheques10="<?php echo floatval($cliente['CHEQUES_10_DIAS']); ?>"
                                data-deuda="<?php echo floatval($cliente['TOTAL_DEUDA']); ?>"
                                data-disponible="<?php echo floatval($cliente['TOTAL_DISPONIBLE']); ?>"
                                class="<?php echo $conMora ? 'row-danger' : ''; ?>">
                                <td class="col-cliente">
                                    <span class="client-code <?php echo $conMora ? 'danger' : ''; ?>">
                                        <?php echo htmlspecialchars($codCliente); ?>
                                    </span>
                                </td>
                                <td class="col-razon">
                                    <a href="#" onclick="verDetalleCredito('<?php echo $codCliente; ?>', '<?php echo addslashes($razonSocial); ?>'); return false;" class="client-link">
                                        <?php echo htmlspecialchars($razonSocial); ?>
                                    </a>
                                </td>
                                <td class="text-center col-ppp"><?php echo intval($cliente['PPP']); ?></td>
                                <td class="text-right col-cupo"><?php echo number_format($cliente['CUPO_CRED'], 0, '', '.'); ?></td>
                                <td class="text-right col-saldo <?php echo $cliente['SALDO_CC'] < 0 ? 'text-danger' : ''; ?>">
                                    <strong><?php echo number_format($cliente['SALDO_CC'], 0, '', '.'); ?></strong>
                                </td>
                                <td class="text-right col-vencidas <?php echo $conMora ? 'text-danger' : ''; ?>">
                                    <strong><?php echo number_format($cliente['VENCIDAS'], 0, '', '.'); ?></strong>
                                </td>
                                <td class="text-center col-pedidos">
                                    <?php if(intval($cliente['PEDIDOS_ABIERTOS']) > 0): ?>
                                        <span class="badge badge-info"><?php echo intval($cliente['PEDIDOS_ABIERTOS']); ?></span>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td class="text-right col-cheques">
                                    <?php if(intval($cliente['CHEQUE']) > 0): ?>
                                        <a href="#" onclick="verCheques('<?php echo $codCliente; ?>', '<?php echo addslashes($razonSocial); ?>'); return false;" class="cheque-link">
                                            <?php echo number_format($cliente['CHEQUE'], 0, '', '.'); ?>
                                        </a>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td class="text-right col-cheques10">
                                    <?php if(intval($cliente['CHEQUES_10_DIAS']) > 0): ?>
                                        <span class="text-success">
                                            <strong><?php echo number_format($cliente['CHEQUES_10_DIAS'], 0, '', '.'); ?></strong>
                                        </span>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td class="text-right col-deuda"><strong><?php echo number_format($cliente['TOTAL_DEUDA'], 0, '', '.'); ?></strong></td>
                                <td class="text-right col-disponible <?php echo $cliente['TOTAL_DISPONIBLE'] < 0 ? 'text-danger' : ''; ?>">
                                    <?php echo number_format($cliente['TOTAL_DISPONIBLE'], 0, '', '.'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Row -->
                        <tr class="totals-row">
                            <td colspan="4" class="text-right"><strong>TOTAL</strong></td>
                            <td class="text-right col-saldo"><strong><?php echo number_format($totalSaldoCC, 0, '', '.'); ?></strong></td>
                            <td class="text-right col-vencidas"><strong><?php echo number_format($totalVencidas, 0, '', '.'); ?></strong></td>
                            <td class="col-pedidos"></td>
                            <td class="text-right col-cheques"><strong><?php echo number_format($totalCheques, 0, '', '.'); ?></strong></td>
                            <td class="text-right col-cheques10"><strong><?php echo number_format($totalCheques10, 0, '', '.'); ?></strong></td>
                            <td class="text-right col-deuda"><strong><?php echo number_format($totalDeuda, 0, '', '.'); ?></strong></td>
                            <td class="text-right col-disponible"><strong><?php echo number_format($totalDisponible, 0, '', '.'); ?></strong></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="table-empty">
                                <i class="fas fa-inbox"></i>
                                <h3>No hay clientes</h3>
                                <p>No se encontraron clientes para este vendedor</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="js/credito.js"></script>
</body>
</html>
