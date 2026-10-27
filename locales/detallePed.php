<?php
// Sesión y datos antes de cualquier salida: si no, header() no puede redirigir al login
session_start();
if(!isset($_SESSION['username'])) {
    header("Location:../../sistemas/login.php");
    exit();
}

require_once __DIR__.'/class/pedido.php';
$suc = $_GET['suc'];
$nroPedido = $_GET['pedido'];
$tipo = $_GET['tipo'];
$usuarioUy = $_SESSION['usuarioUy'];
$pedido = new Pedido();
$pedidos = $pedido->traerDetallePedido($nroPedido, $suc, $usuarioUy);

$totalItems = 0;
$fechasPedido = [];
foreach ($pedidos as $v) {
    $totalItems += (int)$v['CANT'];
    $fechasPedido[$v['FECHA']->format('d/m/Y')] = true;
}
// Vista celular: si todas las líneas tienen la misma fecha, se muestra una sola vez arriba
$fechaUnica = count($fechasPedido) === 1 ? array_key_first($fechasPedido) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- viewport-fit=cover: habilita env(safe-area-inset-*) para la barra de total fija -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Detalle de Pedido</title>
    <link rel="shortcut icon" href="images/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style/detalle.css?v=<?= filemtime(__DIR__.'/style/detalle.css') ?>" rel="stylesheet">
    <style>
        @media print {
            body { 
                background: white;
                padding: 0;
                margin: 0;
            }
            nav, .shadow { 
                box-shadow: none !important;
            }
            nav button, nav a, nav i { 
                display: none !important;
            }
            nav h1 {
                font-size: 18px !important;
                margin: 10px 0 !important;
            }
            main {
                padding: 0 !important;
            }
            .bg-white {
                background: white !important;
                box-shadow: none !important;
                border: none !important;
            }
            table {
                width: 100% !important;
            }
            @page { 
                margin: 1cm;
                size: portrait;
            }
            .no-print {
                display: none !important;
            }
        }
        
        .table-container {
            max-height: calc(100vh - 300px);
            overflow-y: auto;
        }
        
        .table-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <nav class="bg-white shadow-sm no-print det-nav">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 det-nav-fila">
                    <div class="flex items-center det-nav-izq">
                        <a href="historial.php" class="text-gray-500 hover:text-gray-700 transition-colors det-icon-btn" aria-label="Volver al historial">
                            <i class="fas fa-arrow-left text-xl" aria-hidden="true"></i>
                        </a>
                        <h1 class="ml-4 text-xl font-semibold text-gray-900 det-titulo-escritorio">
                            Pedido: <?= htmlspecialchars($nroPedido) ?> - <?= htmlspecialchars($tipo) ?>
                        </h1>
                        <?php // Solo celular: "Pedido N" con el tipo abajo (título aparte para no alterar el de escritorio) ?>
                        <h1 class="m-only det-titulo">
                            <span class="det-nro">Pedido <?= htmlspecialchars($nroPedido) ?></span>
                            <span class="det-tipo"><?= htmlspecialchars($tipo) ?></span>
                        </h1>
                    </div>
                    <div class="flex items-center space-x-4 det-nav-acciones">
                        <button onclick="exportToExcel()" aria-label="Exportar a Excel" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all det-icon-btn">
                            <i class="fas fa-file-excel mr-2" aria-hidden="true"></i>
                            <span class="det-btn-texto">Excel</span>
                        </button>
                        <button onclick="window.print()" aria-label="Imprimir" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all det-icon-btn">
                            <i class="fas fa-print mr-2" aria-hidden="true"></i>
                            <span class="det-btn-texto">Imprimir</span>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 det-main">
            <!-- Tarjeta de resumen -->
            <div class="bg-white rounded-lg shadow mb-6 p-6 det-resumen">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 det-resumen-grid">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                            <i class="fas fa-box text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm text-gray-500">Total de Artículos</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?= $totalItems ?></div>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                            <i class="fas fa-file-alt text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm text-gray-500">Tipo de Pedido</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900"><?= htmlspecialchars($tipo) ?></div>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                            <i class="fas fa-hashtag text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm text-gray-500">Nro. Pedido</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900"><?= htmlspecialchars($nroPedido) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($fechaUnica): ?>
            <!-- Solo celular: la fecha es la misma en todo el pedido, se muestra una vez -->
            <p class="m-only det-fecha-unica">Fecha: <?= htmlspecialchars($fechaUnica) ?></p>
            <?php endif; ?>

            <!-- Tabla de detalle (no agregar nodos dentro de #detalleTable: exportToExcel la lee entera) -->
            <div class="bg-white rounded-lg shadow overflow-hidden det-tabla<?= $fechaUnica ? ' det-con-fecha-unica' : '' ?>">
                <div class="table-container">
                    <table id="detalleTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if(empty($pedidos)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-2 text-gray-300"></i>
                                        <p>No se encontraron artículos en este pedido</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pedidos as $v):
                                    $fecha = $v['FECHA']->format('d/m/Y'); ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($fecha) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($v['COD_ARTICU']) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($v['DESCRIPCIO']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center font-semibold"><?= (int)$v['CANT'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold">
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-right text-sm text-gray-900">Total:</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center"><?= $totalItems ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

    <script>
    function exportToExcel() {
        const table = document.getElementById('detalleTable');
        const ws = XLSX.utils.table_to_sheet(table);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Detalle Pedido');
        
        // Agregar información del pedido
        const fileName = `Pedido_${<?= json_encode($nroPedido) ?>}_${<?= json_encode(str_replace(' ', '_', $tipo)) ?>}.xlsx`;
        XLSX.writeFile(wb, fileName);
    }
    </script>
</body>
</html>