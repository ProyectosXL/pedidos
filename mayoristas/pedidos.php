<?php 
session_start(); 
if(!isset($_SESSION['username'])){
    header("Location:login.php");
    exit;
}

// Verificar si viene POST con selección de cliente
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'seleccionar_cliente'){
    $_SESSION['codClient'] = $_POST['cod_cliente'];
    $_SESSION['razonSocial'] = $_POST['razon_social'];
    // Redirect para evitar reenvío de formulario
    header("Location: pedidos.php");
    exit;
}

// Si no hay cliente en sesión, redirigir a index
if(!isset($_SESSION['codClient'])){
    header("Location:index.php");
    exit;
}

$permiso = $_SESSION['permisos'];
$local = $_SESSION['descLocal'];
$codClient = $_SESSION['codClient'];
$nombreCliente = $_SESSION['razonSocial'] ?? '';

// Si no tenemos el nombre, buscarlo
if(empty($nombreCliente)){
    require_once $_SERVER['DOCUMENT_ROOT'].'/sistemas/class/conexion.php';
    $conn = new Conexion();
    $cid = $conn->conectar('central');
    
    if($cid !== false){
        $sqlCliente = "SELECT RAZON_SOCI FROM GVA14 WHERE COD_CLIENT = ?";
        $stmt = sqlsrv_query($cid, $sqlCliente, array($codClient));
        if($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $nombreCliente = $row['RAZON_SOCI'];
            $_SESSION['razonSocial'] = $nombreCliente;
        }
        sqlsrv_close($cid);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - <?php echo $codClient; ?></title>
    <link rel="shortcut icon" href="images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/mayoristas.css">
    <link rel="stylesheet" href="css/pedidos.css">
</head>
<body>
    <div class="pedidos-container">
        <!-- Header -->
        <div class="header-container">
            <div class="header-top">
                <div class="logo-section">
                    <img src="images/icono.jpg" alt="XL Extra Large">
                    <div class="welcome-text">
                        <h1>Pedidos Mayoristas</h1>
                        <p>Selecciona el tipo de pedido</p>
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
            
            <!-- Client Info Banner -->
            <div class="client-info-banner">
                <h2><i class="fas fa-store"></i> <?php echo $codClient; ?></h2>
                <p><?php echo $nombreCliente ? $nombreCliente : 'Cliente Mayorista'; ?></p>
            </div>

            <!-- Pedidos Menu -->
            <h3 class="menu-title">
                <i class="fas fa-clipboard-list"></i>
                Tipos de Pedidos
            </h3>
            <div class="menu-grid">
                <a href="pedidos/general.php" class="menu-card">
                    <i class="fas fa-boxes"></i>
                    <h3>Pedido General</h3>
                    <p>Productos de línea general</p>
                </a>
                
                <a href="pedidos/accesorios.php" class="menu-card">
                    <i class="fas fa-tags"></i>
                    <h3>Accesorios</h3>
                    <p>Complementos y accesorios</p>
                </a>
            </div>
        </div>
    </div>

    <script src="js/pedidos.js"></script>
</body>
</html>