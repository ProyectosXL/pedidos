
<?php
session_start();

if(!isset($_SESSION['username'])){
    header("Location:../../../sistemas/login.php");
    exit;
}

// Verificar que sea un POST
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location:../index.php");
    exit;
}

require_once __DIR__ . '/../class/PedidoManager.php';

try {
    // Validar datos requeridos
    if(!isset($_POST['cantPed']) || !isset($_POST['codArt'])){
        throw new Exception("Datos de pedido incompletos");
    }
    
    $pedidoManager = new PedidoManager();
    
    // Obtener datos del formulario
    $cantidades = $_POST['cantPed'];
    $codigos = $_POST['codArt'];
    $rubros = $_POST['rubro'] ?? array();
    $stocks = $_POST['stock'] ?? array();
    
    // Determinar tipo de pedido (talon)
    $talon = $_POST['talon'] ?? '1'; // 1=General, 2=Accesorios
    $tipoTexto = ($talon == '1') ? 'PEDIDO GENERAL' : 'ACCESORIOS';
    
    // Preparar artículos (filtrar solo los que tienen cantidad > 0)
    $articulos = array();
    foreach($cantidades as $index => $cantidad) {
        $cant = intval($cantidad);
        if($cant > 0 && isset($codigos[$index])) {
            $articulos[] = array(
                'codArticulo' => trim($codigos[$index]),
                'cantidad' => $cant,
                'rubro' => $rubros[$index] ?? '',
                'stock' => $stocks[$index] ?? 0,
                'precio' => 0 // Los precios se calculan luego si es necesario
            );
        }
    }
    
    // Validar que haya al menos un artículo
    if(count($articulos) === 0){
        throw new Exception("Debe seleccionar al menos un artículo con cantidad");
    }
    
    // Preparar datos del encabezado
    $datosEncabezado = array(
        'talon' => $talon,
        'codCliente' => $_SESSION['codClient'],
        'codVendedor' => $_SESSION['codVendedor'] ?? '0',
        'codSucursal' => '01', // Depósito central
        'estado' => '2', // Pendiente
        'importe' => 0,
        'observaciones' => $tipoTexto,
        'tipoPedido' => ($talon == '1') ? 'GENERAL' : 'ACCESORIOS'
    );
    
    // Crear el pedido completo
    $nroPedido = $pedidoManager->crearPedidoCompleto($datosEncabezado, $articulos);
    
    // Calcular totales para mostrar
    $totalArticulos = count($articulos);
    $totalCantidad = array_sum(array_column($articulos, 'cantidad'));
    
    // Mensaje de éxito
    $mensaje = "Pedido creado exitosamente";
    $success = true;
    
} catch(Exception $e) {
    $mensaje = "Error al crear el pedido: " . $e->getMessage();
    $success = false;
    $nroPedido = null;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado del Pedido - XL Extra Large</title>
    <link rel="shortcut icon" href="../images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mayoristas.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .result-container {
            max-width: 650px;
            width: 100%;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .result-card {
            background: white;
            border-radius: 20px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .result-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            animation: bounce 1s;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px);
            }
            60% {
                transform: translateY(-10px);
            }
        }
        
        .result-icon.success {
            color: #28a745;
        }
        
        .result-icon.error {
            color: #dc3545;
        }
        
        .result-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .result-message {
            font-size: 1.15rem;
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        
        .result-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            text-align: left;
        }
        
        .result-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .result-detail-row:last-child {
            border-bottom: none;
        }
        
        .result-detail-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.95rem;
        }
        
        .result-detail-value {
            color: #212529;
            font-size: 1rem;
        }
        
        .result-detail-value.highlight {
            color: #28a745;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .result-actions {
            display: flex;
            gap: 15px;
            justify-center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn-result {
            padding: 14px 32px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-result.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-result.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-result.secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-result.secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .auto-redirect {
            margin-top: 25px;
            padding: 12px 20px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 8px;
            color: #856404;
            font-size: 0.9rem;
        }
        
        .auto-redirect i {
            margin-right: 8px;
        }
    </style>
</head>
            border-bottom: none;
        }
        
        .result-detail-label {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .result-detail-value {
            color: var(--text-dark);
        }
        
        .result-detail-value.highlight {
            color: var(--success-color);
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .result-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-result {
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-result.primary {
            background: var(--secondary-color);
            color: var(--white);
        }
        
        .btn-result.primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-result.secondary {
            background: var(--white);
            color: var(--text-dark);
            border: 2px solid var(--border-color);
        }
        
        .btn-result.secondary:hover {
            border-color: var(--secondary-color);
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="result-container">
        <div class="result-card">
            <?php if($success): ?>
                <!-- Éxito -->
                <div class="result-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="result-title">¡Pedido Creado Exitosamente!</h1>
                <p class="result-message">Su pedido ha sido procesado y registrado correctamente en el sistema</p>
                
                <div class="result-details">
                    <div class="result-detail-row">
                        <span class="result-detail-label"><i class="fas fa-hashtag"></i> Número de Pedido:</span>
                        <span class="result-detail-value highlight"><?php echo $nroPedido; ?></span>
                    </div>
                    <div class="result-detail-row">
                        <span class="result-detail-label"><i class="fas fa-user"></i> Cliente:</span>
                        <span class="result-detail-value"><?php echo $_SESSION['codClient']; ?></span>
                    </div>
                    <div class="result-detail-row">
                        <span class="result-detail-label"><i class="fas fa-tag"></i> Tipo de Pedido:</span>
                        <span class="result-detail-value"><?php echo $tipoTexto; ?></span>
                    </div>
                    <div class="result-detail-row">
                        <span class="result-detail-label"><i class="fas fa-box"></i> Total Artículos:</span>
                        <span class="result-detail-value"><strong><?php echo $totalArticulos; ?></strong></span>
                    </div>
                    <div class="result-detail-row">
                        <span class="result-detail-label"><i class="fas fa-cubes"></i> Cantidad Total:</span>
                        <span class="result-detail-value"><strong><?php echo $totalCantidad; ?></strong> unidades</span>
                    </div>
                </div>
                
                <div class="result-actions">
                    <a href="../pedidos/historial.php" class="btn-result secondary">
                        <i class="fas fa-history"></i> Ver Historial
                    </a>
                    <a href="../pedidos.php" class="btn-result primary">
                        <i class="fas fa-plus-circle"></i> Nuevo Pedido
                    </a>
                </div>
                
                <div class="auto-redirect">
                    <i class="fas fa-info-circle"></i>
                    Será redirigido automáticamente en 8 segundos...
                </div>
                
            <?php else: ?>
                <!-- Error -->
                <div class="result-icon error">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h1 class="result-title">Error al Crear Pedido</h1>
                <p class="result-message"><?php echo $mensaje; ?></p>
                
                <div class="result-details">
                    <div style="padding: 20px; text-align: center;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #dc3545; margin-bottom: 15px;"></i>
                        <p style="color: #495057;">
                            Por favor, verifique los datos ingresados e intente nuevamente. 
                            Si el problema persiste, contacte al administrador del sistema.
                        </p>
                    </div>
                </div>
                
                <div class="result-actions">
                    <a href="javascript:history.back()" class="btn-result secondary">
                        <i class="fas fa-arrow-left"></i> Volver e Intentar Nuevamente
                    </a>
                    <a href="../pedidos.php" class="btn-result primary">
                        <i class="fas fa-home"></i> Ir al Inicio
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if($success): ?>
    <script>
        // Auto-redirigir después de 8 segundos
        setTimeout(function() {
            window.location.href = '../pedidos.php';
        }, 8000);
    </script>
    <?php endif; ?>
</body>
</html>
