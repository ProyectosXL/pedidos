<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location:../../sistemas/login.php");
    exit;
}

include_once __DIR__.'/class/pedido.php';

$suc        = $_SESSION['numsuc'];
$codClient  = $_SESSION['codClient'];
$local      = $_SESSION['descLocal'] ?? '';
$tipoPedido = $_SESSION['tipo_pedido'] ?? 'GENERAL';
$esOutlet   = $_SESSION['esOutlet'] ?? 0;

// Locales propios no ven precios
$esLocalPropio = (substr($codClient, 0, 2) === 'GT');
$mostrarPrecio = ($suc > 100) && !$esLocalPropio;

// Determinar tipo numérico para el vínculo "Seguir armando"
$tipoMap = ['GENERAL' => 1, 'ACCESORIOS' => 2, 'OUTLET' => 3];
$tipoNum = $tipoMap[$tipoPedido] ?? 1;
$urlPedidos = "pedidos.php?tipo=$tipoNum";

$pedido = new Pedido();
$items  = $pedido->obtenerCarrito($suc, $codClient, $tipoPedido);

// Validar stock disponible actual (físico − reservado en pedidos pendientes)
// para detectar artículos consumidos por otras sucursales mientras el carrito esperaba.
$codigosCarrito = array_map(fn($i) => trim($i['COD_ARTICU']), $items);
$stockDisponible = !empty($codigosCarrito)
    ? $pedido->obtenerStockDisponible($codigosCarrito)
    : [];

// Mapa [cod => stock_disponible] solo de los que tienen problema
$itemsConProblema = [];
foreach ($items as $it) {
    $cod  = trim($it['COD_ARTICU']);
    $cant = intval($it['CANTIDAD']);
    $disp = intval($stockDisponible[$cod] ?? 0);
    if ($cant > $disp) {
        $itemsConProblema[$cod] = $disp;
    }
}

// Totales
$totalArticulos = count($items);
$totalCantidad  = array_reduce($items, fn($c, $i) => $c + intval($i['CANTIDAD']), 0);
$totalPrecio    = $mostrarPrecio ? array_reduce($items, fn($s, $i) => $s + (intval($i['PRECIO'] ?? 0) * intval($i['CANTIDAD'])), 0) : 0;

$tituloPedido = match($tipoPedido) {
    'ACCESORIOS' => 'Pedido Accesorios',
    'OUTLET'     => 'Pedido Outlet',
    default      => 'Pedido General',
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito — <?php echo $tituloPedido; ?></title>
    <link rel="icon" type="image/jpeg" href="../../sistemas/assets/css/icono.jpg?v=1" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/pedidos.css?v=<?php echo filemtime(__DIR__.'/style/pedidos.css'); ?>">
    <link rel="stylesheet" href="style/tabla.css">
    <link rel="stylesheet" href="style/carrito.css?v=<?php echo filemtime(__DIR__.'/style/carrito.css'); ?>">
    <!-- Prefetch para que pedidos.php esté lista si bfcache no aplica -->
    <link rel="prefetch" href="<?php echo $urlPedidos; ?>">
</head>
<body>

    <!-- Header -->
    <div class="header-container">
        <div class="header-top">
            <div class="logo-section">
                <img src="images/icono.jpg" alt="XL Extra Large">
                <div class="welcome-text">
                    <h1><i class="fas fa-shopping-cart"></i> Carrito — <?php echo $tituloPedido; ?></h1>
                    <p class="info-text">Sucursal: <?php echo $suc; ?> | Cliente: <?php echo $local ? $local . ' (' . $codClient . ')' : $codClient; ?></p>
                </div>
            </div>

            <div class="header-actions">
                <button type="button" class="btn btn-secondary" onclick="volverAPedidos()">
                    <i class="fas fa-arrow-left"></i> Seguir armando
                </button>
                <?php if ($totalArticulos > 0): ?>
                <button type="button" class="btn btn-secondary" onclick="exportarCarritoCSV()">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
                <button type="button" class="btn btn-danger-outline" onclick="vaciarCarrito()">
                    <i class="fas fa-trash"></i> Vaciar carrito
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main -->
    <div class="main-container">

        <?php if (empty($items)): ?>
        <div class="carrito-empty">
            <i class="fas fa-shopping-cart"></i>
            <h2>El carrito está vacío</h2>
            <p>No hay artículos guardados para este pedido.</p>
            <a href="<?php echo $urlPedidos; ?>" class="btn btn-primary" style="margin-top:16px;">
                <i class="fas fa-arrow-left"></i> Volver a armar el pedido
            </a>
        </div>
        <?php else: ?>

        <div id="stockWarningBanner" class="stock-warning-banner<?php echo empty($itemsConProblema) ? ' hidden' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i>
            <span>
                Hay <strong id="stockWarningCount"><?php echo count($itemsConProblema); ?></strong>
                artículo(s) sin stock suficiente. Modificá las cantidades resaltadas para poder enviar el pedido.
            </span>
        </div>

        <div class="product-table-container">
            <table class="product-table">
                <thead>
                    <tr>
                        <th style="width:80px;">Foto</th>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th>Rubro</th>
                        <th class="text-center">Cantidad</th>
                        <?php if($mostrarPrecio): ?>
                        <th class="text-right">Importe</th>
                        <?php endif; ?>
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody id="carritoBody">
                    <?php foreach ($items as $item):
                        $codRaw = trim($item['COD_ARTICU']);
                        $cod    = htmlspecialchars($codRaw);
                        $desc   = htmlspecialchars($item['DESCRIPCION'] ?? $item['COD_ARTICU']);
                        $rubro  = htmlspecialchars($item['RUBRO'] ?? '');
                        $cant   = intval($item['CANTIDAD']);
                        $stock  = intval($item['STOCK']);
                        $disp   = intval($stockDisponible[$codRaw] ?? 0);
                        $sinStock = isset($itemsConProblema[$codRaw]);
                        $img    = substr($codRaw, 0, 13);
                    ?>
                    <tr data-rubro="<?php echo $rubro; ?>" data-art="<?php echo $cod; ?>" data-precio="<?php echo intval($item['PRECIO'] ?? 0); ?>" data-stock-disp="<?php echo $disp; ?>"<?php echo $sinStock ? ' class="row-sin-stock"' : ''; ?>>
                        <td class="product-image-cell">
                            <img src="https://app.xl.com.ar/imagenes/<?php echo $img; ?>.jpg"
                                 alt="<?php echo $desc; ?>"
                                 class="product-image"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='https://app.xl.com.ar/imagenes/default.jpg'">
                        </td>
                        <td>
                            <span class="product-code"><?php echo $cod; ?></span>
                            <input type="hidden" name="codArt[]" value="<?php echo $cod; ?>">
                        </td>
                        <td><?php echo $desc; ?></td>
                        <td><span class="rubro-badge"><?php echo $rubro; ?></span></td>
                        <td class="text-center">
                            <div class="qty-actions">
                                <?php $tope = min($stock, $disp); ?>
                                <button type="button" class="qty-btn btn-minus" onclick="qtyStep(this, -1, <?php echo $tope; ?>)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number"
                                       name="cantPed[]"
                                       class="qty-input has-value"
                                       value="<?php echo $cant; ?>"
                                       min="0"
                                       max="<?php echo $tope; ?>"
                                       data-stock="<?php echo $stock; ?>"
                                       data-stock-disp="<?php echo $disp; ?>"
                                       data-tope="<?php echo $tope; ?>"
                                       data-codigo="<?php echo $cod; ?>"
                                       data-rubro="<?php echo $rubro; ?>"
                                       autocomplete="off"
                                       oninput="onQtyEdit(this)"
                                       onchange="onQtyEdit(this)"
                                       onblur="onQtyEdit(this)">
                                <button type="button" class="qty-btn btn-plus" onclick="qtyStep(this, 1, <?php echo $tope; ?>)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </td>
                        <?php if($mostrarPrecio): ?>
                        <td class="text-right importe-cell">
                            $<?php echo number_format(intval($item['PRECIO'] ?? 0) * $cant, 0, ',', '.'); ?>
                        </td>
                        <?php endif; ?>
                        <td class="text-center">
                            <button type="button" class="btn-remove-item"
                                    onclick="eliminarItem(this, '<?php echo $cod; ?>')"
                                    title="Quitar del carrito">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer con totales y botón enviar -->
        <div class="fixed-footer">
            <div class="footer-content">
                <div class="footer-totals">
                    <div class="total-item">
                        <span class="total-label">ARTÍCULOS</span>
                        <span class="total-value" id="totalArticulosEl"><?php echo $totalArticulos; ?></span>
                    </div>
                    <div class="total-item">
                        <span class="total-label">Unidades</span>
                        <span class="total-value highlight" id="totalCantidadEl"><?php echo $totalCantidad; ?></span>
                    </div>
                    <?php if($mostrarPrecio): ?>
                    <div class="total-item">
                        <span class="total-label">Total en $</span>
                        <span class="total-value highlight" id="totalPrecioEl">$<?php echo number_format($totalPrecio, 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="footer-actions">
                    <a href="<?php echo $urlPedidos; ?>" class="btn-clear">
                        <i class="fas fa-arrow-left"></i> Seguir armando
                    </a>
<button type="button" id="submitBtn" class="btn-submit"
                            onclick="enviarPedido()"
                            <?php echo ($totalArticulos === 0 || !empty($itemsConProblema)) ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane"></i> Enviar Pedido
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /main-container -->

    <?php include __DIR__ . '/modals/modals.php'; ?>

    <!-- Variables PHP para JavaScript -->
    <script>
        const APP = {
            suc:           '<?php echo $suc; ?>',
            codClient:     '<?php echo $codClient; ?>',
            tipoPedido:    '<?php echo $tipoPedido; ?>',
            depo:          '<?php echo $_SESSION['depo'] ?? '01'; ?>',
            cupoCredi:     <?php echo floatval($_SESSION['cupoCredi'] ?? 0); ?>,
            mostrarPrecio: <?php echo $mostrarPrecio ? 'true' : 'false'; ?>
        };

        // Totales globales requeridos por envio.js
        let totalArticulos = <?php echo $totalArticulos; ?>;
        let totalCantidad  = <?php echo $totalCantidad; ?>;
        let totalPrecio    = <?php echo $totalPrecio; ?>;

        // Mapa de artículos sin stock {cod: stock_disponible}, sembrado desde el render
        // y propagado a sessionStorage para que pedidos.php lo lea al volver.
        const ITEMS_SIN_STOCK = <?php echo json_encode((object) $itemsConProblema, JSON_UNESCAPED_UNICODE); ?>;
        if (Object.keys(ITEMS_SIN_STOCK).length > 0) {
            sessionStorage.setItem('articulosSinStock', JSON.stringify(ITEMS_SIN_STOCK));
        } else {
            sessionStorage.removeItem('articulosSinStock');
        }

        function calcularTotalesCarrito() {
            totalArticulos = 0;
            totalCantidad  = 0;
            totalPrecio    = 0;

            const snap = {};
            document.querySelectorAll('.qty-input').forEach(input => {
                const cant   = parseInt(input.value) || 0;
                const cod    = input.getAttribute('data-codigo');
                const precio = parseFloat(input.closest('tr')?.getAttribute('data-precio') || 0);
                if (cod) snap[cod] = cant;
                if (cant > 0) {
                    totalArticulos++;
                    totalCantidad += cant;
                    totalPrecio   += precio * cant;
                }
                // Actualizar celda de importe por fila
                const importeCell = input.closest('tr')?.querySelector('.importe-cell');
                if (importeCell) {
                    importeCell.textContent = '$' + parseInt(precio * cant).toLocaleString('es-AR');
                }
            });

            // Guardar estado en sessionStorage para que pedidos.php lo lea
            // al volver via bfcache (sin ningún fetch, sin lock de sesión)
            sessionStorage.setItem('carritoSnapshot', JSON.stringify(snap));

            const elArt    = document.getElementById('totalArticulosEl');
            const elCant   = document.getElementById('totalCantidadEl');
            const elPrecio = document.getElementById('totalPrecioEl');
            if (elArt)    elArt.textContent    = totalArticulos;
            if (elCant)   elCant.textContent   = totalCantidad;
            if (elPrecio) elPrecio.textContent = '$' + parseInt(totalPrecio).toLocaleString('es-AR');

            // Reevaluar marcas de stock insuficiente
            const sinStockMap = recalcularStockWarnings();

            const btn = document.getElementById('submitBtn');
            if (btn) btn.disabled = (totalArticulos === 0 || Object.keys(sinStockMap).length > 0);
        }

        // Recorre filas .row-sin-stock; si la cantidad ya cumple con el disponible,
        // limpia la marca. Devuelve el mapa actualizado (también sincroniza sessionStorage y banner).
        function recalcularStockWarnings() {
            const map = {};
            document.querySelectorAll('tr.row-sin-stock').forEach(row => {
                const input = row.querySelector('.qty-input');
                if (!input) return;
                const cod  = input.getAttribute('data-codigo');
                const disp = parseInt(input.getAttribute('data-stock-disp')) || 0;
                const val  = parseInt(input.value) || 0;
                if (val > disp) {
                    map[cod] = disp;
                } else {
                    row.classList.remove('row-sin-stock');
                }
            });
            const banner = document.getElementById('stockWarningBanner');
            const counter = document.getElementById('stockWarningCount');
            const n = Object.keys(map).length;
            if (banner) banner.classList.toggle('hidden', n === 0);
            if (counter) counter.textContent = n;
            if (n === 0) sessionStorage.removeItem('articulosSinStock');
            else sessionStorage.setItem('articulosSinStock', JSON.stringify(map));
            return map;
        }

        let cartSaveTimers = {};
        function guardarItemEnCarrito(input) {
            const codArt   = input.getAttribute('data-codigo') || '';
            const rubro    = input.getAttribute('data-rubro')  || '';
            const cantidad = parseInt(input.value) || 0;
            if (!codArt) return;

            if (cartSaveTimers[codArt]) clearTimeout(cartSaveTimers[codArt]);
            cartSaveTimers[codArt] = setTimeout(() => {
                fetch('controller/carritoAjax.php?action=guardar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ codArt, cantidad, rubro, precio: 0 })
                }).catch(() => {});
            }, 600);
        }

        function eliminarItem(btn, codArt) {
            const row = btn.closest('tr');
            if (!row) return;

            // Poner cantidad en 0 y guardar
            const input = row.querySelector('.qty-input');
            if (input) input.value = 0;

            fetch('controller/carritoAjax.php?action=guardar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ codArt, cantidad: 0, rubro: '', precio: 0 })
            }).catch(() => {});

            row.style.transition = 'opacity .25s, transform .25s';
            row.style.opacity = '0';
            row.style.transform = 'translateX(20px)';
            setTimeout(() => {
                row.remove();
                calcularTotalesCarrito();
                if (document.querySelectorAll('.product-table tbody tr').length === 0) {
                    location.reload();
                }
            }, 280);
        }

        function vaciarCarrito() {
            abrirVaciarCarritoModal(() => {
                sessionStorage.setItem('carritoSnapshot', JSON.stringify({}));
                fetch('controller/carritoAjax.php?action=limpiar')
                    .then(() => location.reload())
                    .catch(() => location.reload());
            });
        }

        // calcularTotales es llamado por envio.js — redirige a la versión local
        function calcularTotales() { calcularTotalesCarrito(); }

        function exportarCarritoCSV() {
            if (typeof XLSX === 'undefined') {
                alert('La librería de exportación no está cargada. Recargá la página e intentá de nuevo.');
                return;
            }

            const headers = ['Código', 'Descripción', 'Rubro', 'Cantidad'];
            if (APP.mostrarPrecio) headers.push('Importe');
            const data = [headers];

            document.querySelectorAll('.product-table tbody tr').forEach(tr => {
                const cod    = tr.getAttribute('data-art') || '';
                const rubro  = tr.getAttribute('data-rubro') || '';
                const precio = parseFloat(tr.getAttribute('data-precio') || 0);
                const input  = tr.querySelector('.qty-input');
                const cant   = input ? (parseInt(input.value) || 0) : 0;
                if (cant <= 0) return;
                const descEl = tr.querySelectorAll('td')[2]; // 3ª td = descripción
                const desc   = descEl ? descEl.textContent.trim() : '';
                const row = [cod, desc, rubro, cant];
                if (APP.mostrarPrecio) row.push(precio * cant);
                data.push(row);
            });

            if (data.length === 1) { alert('No hay artículos con cantidad.'); return; }

            const ws = XLSX.utils.aoa_to_sheet(data);
            ws['!cols'] = APP.mostrarPrecio
                ? [{wch: 16}, {wch: 40}, {wch: 22}, {wch: 16}, {wch: 16}]
                : [{wch: 16}, {wch: 40}, {wch: 22}, {wch: 16}];
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Carrito');
            const fecha = new Date().toISOString().slice(0, 10);
            XLSX.writeFile(wb, `carrito_<?php echo $tipoPedido; ?>_${fecha}.xlsx`);
        }

        // Tope autorizado: el menor entre data-stock (central), data-stock-disp
        // (disponible = central − reservado) y el atributo HTML max. Si nada está
        // informado, devuelve 0 (bloqueo total) en vez de null para evitar fugas.
        function _topeQty(input) {
            const fuentes = [
                input.getAttribute('data-stock'),
                input.getAttribute('data-stock-disp'),
                input.getAttribute('max')
            ].map(v => parseInt(v)).filter(n => Number.isFinite(n) && n >= 0);
            return fuentes.length ? Math.min(...fuentes) : 0;
        }

        // Recorta el valor del input al rango [0, tope]. Devuelve true si modificó.
        function _clampQty(input) {
            const tope = _topeQty(input);
            let v = parseInt(input.value);
            if (!Number.isFinite(v) || v < 0) v = 0;
            if (v > tope) v = tope;
            if (String(v) !== input.value) {
                input.value = v;
                return true;
            }
            return false;
        }

        // Handlers globales invocados vía onclick / oninput inline en el markup.
        // Vivir en globals (en vez de addEventListener en DOMContentLoaded) garantiza
        // que la versión recién renderizada sea la que se ejecuta, sin listeners
        // residuales por bfcache o múltiples bindings.
        function onQtyEdit(input) {
            _clampQty(input);
            calcularTotalesCarrito();
            guardarItemEnCarrito(input);
        }

        function qtyStep(btn, delta) {
            const input = btn.parentElement.querySelector('.qty-input');
            if (!input) return;
            const v    = parseInt(input.value) || 0;
            const tope = _topeQty(input);
            const next = v + delta;
            if (next < 0) return;
            if (next > tope) {
                input.classList.add('error');
                setTimeout(() => input.classList.remove('error'), 600);
                return;
            }
            input.value = next;
            _clampQty(input);
            calcularTotalesCarrito();
            guardarItemEnCarrito(input);
        }
    </script>
    <script>
        // Volver a pedidos.php: usa history.back() para aprovechar el bfcache
        // (retorno instantáneo). Si no hay historial, navega directo.
        function volverAPedidos() {
            if (window.history.length > 1) {
                history.back();
            } else {
                window.location.href = '<?php echo $urlPedidos; ?>';
            }
        }
    </script>
    <script src="js/envio.js?v=<?php echo filemtime(__DIR__.'/js/envio.js'); ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</body>
</html>
