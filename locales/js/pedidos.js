// Vista celular: misma condición que la media query de pedidos-mobile.css.
// El tramo horizontal exige pointer: coarse para que una ventana baja de escritorio no la active.
const MQ_CELULAR = '(max-width: 767.98px), (orientation: landscape) and (max-height: 500px) and (pointer: coarse)';
function esVistaCelular() {
    return window.matchMedia(MQ_CELULAR).matches;
}

// Totales y contadores
let totalArticulos = 0;
let totalCantidad = 0;
let totalPrecio = 0;

// Modal de imagen
function mostrarImagen(src) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    if(modal && modalImg) {
        modalImg.src = src;
        modal.offsetHeight;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function cerrarModal(event) {
    if(event) {
        event.stopPropagation();
    }
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    
    if(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => {
            if(modalImg && !modal.classList.contains('active')) {
                modalImg.src = '';
            }
        }, 300);
    }
}

// Función para limpiar cantidades
function limpiarCantidades() {
    if(totalArticulos === 0) {
        return;
    }
    
    if(confirm('¿Está seguro que desea limpiar todos los valores?')) {
        document.querySelectorAll('.qty-input').forEach(input => {
            if(!input.disabled) {
                input.value = 0;
                input.dispatchEvent(new Event('input'));
            }
        });
        calcularTotales();
    }
}

// Calcular totales
function calcularTotales() {
    totalArticulos = 0;
    totalCantidad = 0;
    totalPrecio = 0;
    
    const inputs = document.querySelectorAll('.qty-input');
    inputs.forEach(input => {
        const cant = parseInt(input.value) || 0;
        const row = input.closest('tr');
        // Clases solo visuales para la card del celular (contorno azul / rojo); sin reglas en escritorio
        if(row) {
            row.classList.toggle('has-qty', cant > 0);
            row.classList.toggle('qty-error', input.classList.contains('error'));
        }
        if(cant > 0) {
            totalArticulos++;
            totalCantidad += cant;

            // Obtener precio del producto desde data-attribute
            const precioText = row.querySelector('.price-display')?.textContent || '0';
            const precio = parseFloat(precioText.replace(/[$.]/g, '').replace(/,/g, '')) || 0;
            totalPrecio += (precio * cant);
            
            input.classList.add('has-value');
        } else {
            input.classList.remove('has-value');
        }
    });
    
    // Actualizar los totales en la barra de filtros (arriba)
    const totalArticulosElements = document.querySelectorAll('#totalArticulos');
    totalArticulosElements.forEach(el => el.textContent = totalArticulos);
    
    const totalCantidadElements = document.querySelectorAll('#totalCantidad');
    totalCantidadElements.forEach(el => el.textContent = totalCantidad);
    
    // Actualizar total en precio formateado
    const totalPrecioElement = document.getElementById('totalPrecio');
    if(totalPrecioElement) {
        totalPrecioElement.textContent = '$' + parseInt(totalPrecio).toLocaleString('es-AR');
    }
    
    // Reevaluar marcas de stock insuficiente (limpia las filas que ya cumplen)
    const sinStockMap = recalcularStockWarnings();

    // Habilitar/deshabilitar botón submit
    const submitBtn = document.getElementById('submitBtn');
    if(submitBtn) {
        submitBtn.disabled = (totalArticulos === 0) || (Object.keys(sinStockMap).length > 0);
    }

    // Actualizar badge del carrito en el header
    actualizarBadgeCarrito(totalArticulos);
}

// Recorre filas .row-sin-stock; si la cantidad ya cumple con el disponible,
// limpia la marca. Sincroniza banner y sessionStorage. Devuelve el mapa actual.
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
    const banner  = document.getElementById('stockWarningBanner');
    const counter = document.getElementById('stockWarningCount');
    const n = Object.keys(map).length;
    if (banner)  banner.classList.toggle('hidden', n === 0);
    if (counter) counter.textContent = n;
    if (n === 0) sessionStorage.removeItem('articulosSinStock');
    else sessionStorage.setItem('articulosSinStock', JSON.stringify(map));
    return map;
}

// Aplica marcas de stock insuficiente desde sessionStorage (sembrado por carrito.php
// o por envio.js tras un envío fallido). Llamado en DOMContentLoaded y en bfcache.
function aplicarMarcasStockDesdeStorage() {
    const raw = sessionStorage.getItem('articulosSinStock');
    if (!raw) return;
    let map = null;
    try { map = JSON.parse(raw); } catch (e) { sessionStorage.removeItem('articulosSinStock'); return; }
    if (!map || typeof map !== 'object' || Object.keys(map).length === 0) return;
    if (typeof marcarArticulosSinStock === 'function') {
        marcarArticulosSinStock(map);
    }
}

// Quick actions - botones +/-
function setupQuickActions() {
    const rows = document.querySelectorAll('.product-table tbody tr');
    
    rows.forEach(row => {
        const input = row.querySelector('.qty-input');
        const btnMinus = row.querySelector('.btn-minus');
        const btnPlus = row.querySelector('.btn-plus');
        
        if(btnMinus && input) {
            btnMinus.addEventListener('click', () => {
                const currentValue = parseInt(input.value) || 0;
                if(currentValue > 0) {
                    input.value = currentValue - 1;
                    input.dispatchEvent(new Event('input'));
                }
            });
        }
        
        if(btnPlus && input) {
            btnPlus.addEventListener('click', () => {
                const currentValue = parseInt(input.value) || 0;
                const stock = parseInt(input.getAttribute('data-stock')) || 999;
                if(currentValue < stock) {
                    input.value = currentValue + 1;
                    input.dispatchEvent(new Event('input'));
                }
            });
        }

        const btnSugerencia = row.querySelector('.sugerencia-badge');
        if(btnSugerencia && input) {
            btnSugerencia.addEventListener('click', () => {
                const sugerencia = parseInt(btnSugerencia.getAttribute('data-sugerencia')) || 0;
                const stock = parseInt(input.getAttribute('data-stock')) || 0;
                input.value = Math.min(sugerencia, stock);
                input.dispatchEvent(new Event('input'));
            });
        }
    });
}

// Toast de stock insuficiente
function mostrarToastStock(input, stock) {
    // Evitar duplicados
    const existente = document.getElementById('toast-stock');
    if (existente) existente.remove();

    const row = input.closest('tr');
    const codigo = row ? (row.querySelector('input[name="codArt[]"]')?.value || '') : '';

    const toast = document.createElement('div');
    toast.id = 'toast-stock';
    toast.style.cssText = [
        'position:fixed', 'bottom:90px', 'right:24px', 'z-index:9999',
        'background:#fff', 'border-radius:12px', 'box-shadow:0 8px 32px rgba(0,0,0,0.18)',
        'border-left:5px solid #f59e0b', 'padding:16px 20px',
        'display:flex', 'align-items:flex-start', 'gap:14px',
        'min-width:280px', 'max-width:360px',
        'animation:slideInToast .25s ease'
    ].join(';');

    toast.innerHTML = `
        <div style="font-size:1.6rem; color:#f59e0b; margin-top:2px;"><i class="fas fa-exclamation-triangle"></i></div>
        <div style="flex:1;">
            <div style="font-weight:700; color:#92400e; margin-bottom:4px;">Stock insuficiente</div>
            ${codigo ? `<div style="font-size:.78rem; color:#6b7280; margin-bottom:6px; font-family:monospace;">${codigo}</div>` : ''}
            <div style="font-size:.88rem; color:#374151;">
                Stock disponible: <strong style="color:#10b981;">${stock} u.</strong><br>
                Se ajustó la cantidad automáticamente.
            </div>
        </div>
        <button onclick="this.parentElement.remove()" aria-label="Cerrar aviso" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:1rem;padding:0;"><i class="fas fa-times"></i></button>
    `;

    // Agregar keyframe si no existe
    if (!document.getElementById('toast-keyframes')) {
        const style = document.createElement('style');
        style.id = 'toast-keyframes';
        style.textContent = '@keyframes slideInToast{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}';
        document.head.appendChild(style);
    }

    document.body.appendChild(toast);
    setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
}

// Verificar stock
function verificarStock(input) {
    const cantidad = parseInt(input.value) || 0;
    const stock = parseInt(input.getAttribute('data-stock')) || 0;

    if(cantidad > stock) {
        input.classList.add('error');
        input.value = stock;
        mostrarToastStock(input, stock);
    } else {
        input.classList.remove('error');
    }
}

// Filtrar productos
function filtrarProductos() {
    const searchTerm = document.getElementById('searchProduct')?.value.toLowerCase() || '';
    const rubroFilter = document.getElementById('filterRubro')?.value || '';
    
    const rows = document.querySelectorAll('.product-table tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const code = row.getAttribute('data-code')?.toLowerCase() || '';
        const description = row.getAttribute('data-description')?.toLowerCase() || '';
        const rubro = row.getAttribute('data-rubro') || '';
        
        const matchSearch = code.includes(searchTerm) || description.includes(searchTerm);
        const matchRubro = !rubroFilter || rubro === rubroFilter;
        
        if(matchSearch && matchRubro) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Actualizar contador de productos
    updateProductCount();
}

// Limpiar cantidades
function limpiarCantidades() {
    if(confirm('¿Desea limpiar todas las cantidades ingresadas?')) {
        const inputs = document.querySelectorAll('.qty-input');
        inputs.forEach(input => {
            input.value = 0;
            input.classList.remove('has-value');
        });
        calcularTotales();
    }
}

// Modal de imagen
function mostrarImagen(src) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    if(modal && modalImg) {
        modalImg.src = src;
        modal.offsetHeight;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function cerrarModal(e) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    
    if(e && e.target === modalImg) {
        return;
    }
    
    if(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => {
            if(modalImg && !modal.classList.contains('active')) {
                modalImg.src = '';
            }
        }, 400);
    }
}

// Inicialización
// ========= CARRITO =========

let cartSaveTimers = {};

function guardarItemEnCarrito(input) {
    const codArt  = input.getAttribute('data-codigo') || '';
    const rubro   = input.getAttribute('data-rubro')  || '';
    const precio  = parseFloat(input.getAttribute('data-precio')) || 0;
    const cantidad = parseInt(input.value) || 0;

    if (!codArt) return;

    if (cartSaveTimers[codArt]) clearTimeout(cartSaveTimers[codArt]);
    cartSaveTimers[codArt] = setTimeout(() => {
        fetch('controller/carritoAjax.php?action=guardar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ codArt, cantidad, rubro, precio })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) actualizarBadgeCarrito(data.count);
        })
        .catch(() => {});
    }, 700);
}

function actualizarBadgeCarrito(count) {
    const badge = document.getElementById('cartBadge');
    if (!badge) return;
    badge.textContent = count;
    if (count > 0) {
        badge.classList.remove('cart-badge-empty');
    } else {
        badge.classList.add('cart-badge-empty');
    }
}

function guardarYVerCarrito() {
    // Flushar timers de debounce pendientes antes de abrir el panel
    const saves = [];
    Object.keys(cartSaveTimers).forEach(codArt => {
        clearTimeout(cartSaveTimers[codArt]);
        delete cartSaveTimers[codArt];
        const input = document.querySelector(`.qty-input[data-codigo="${CSS.escape(codArt)}"]`);
        if (input) {
            const cantidad = parseInt(input.value) || 0;
            const rubro    = input.getAttribute('data-rubro')  || '';
            const precio   = parseFloat(input.getAttribute('data-precio')) || 0;
            saves.push(
                fetch('controller/carritoAjax.php?action=guardar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ codArt, cantidad, rubro, precio })
                }).catch(() => {})
            );
        }
    });

    // Abrir panel inmediatamente (sin esperar los saves — el panel lee el DOM)
    abrirCarritoPanel();

    // Los saves corren en background
    if (saves.length > 0) Promise.all(saves).catch(() => {});
}

// ========= PANEL CARRITO =========
function abrirCarritoPanel() {
    renderCarritoPanel();
    const overlay = document.getElementById('cartPanelOverlay');
    const panel   = document.getElementById('cartPanel');
    if (!overlay || !panel) return;
    overlay.style.display = 'block';
    requestAnimationFrame(() => {
        overlay.classList.add('open');
        panel.classList.add('open');
    });
    document.body.style.overflow = 'hidden';
}

function cerrarCarritoPanel() {
    const overlay = document.getElementById('cartPanelOverlay');
    const panel   = document.getElementById('cartPanel');
    if (!overlay || !panel) return;
    overlay.classList.remove('open');
    panel.classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(() => { overlay.style.display = 'none'; }, 280);
}

function renderCarritoPanel() {
    const body   = document.getElementById('cartPanelBody');
    const footer = document.getElementById('cartPanelFooter');
    if (!body) return;

    // Recopilar ítems con cantidad > 0 desde el DOM
    const items = [];
    document.querySelectorAll('.qty-input').forEach(input => {
        const cant = parseInt(input.value) || 0;
        if (cant <= 0) return;
        const row    = input.closest('tr');
        const cod    = input.getAttribute('data-codigo') || '';
        const rubro  = input.getAttribute('data-rubro')  || '';
        const stock  = parseInt(input.getAttribute('data-stock')) || 999;
        const precio = parseFloat(input.getAttribute('data-precio') || 0);
        const rawDesc = row ? (row.getAttribute('data-description') || cod) : cod;
        const desc   = rawDesc.replace(/\b\w/g, c => c.toUpperCase());
        const imgSrc = row ? (row.querySelector('.product-image')?.src || '') : '';
        items.push({ cod, cant, rubro, stock, precio, desc, imgSrc });
    });

    if (items.length === 0) {
        body.innerHTML = `<div class="cart-panel-empty">
            <i class="fas fa-shopping-cart"></i>
            <p>El carrito est&aacute; vac&iacute;o.<br>Ingres&aacute; cantidades en la tabla.</p>
        </div>`;
        if (footer) footer.style.display = 'none';
        return;
    }

    let totalArt = 0, totalUnd = 0, totalPrecioPanel = 0;
    const rows = items.map(it => {
        totalArt++;
        totalUnd += it.cant;
        const importe = it.precio * it.cant;
        totalPrecioPanel += importe;
        const imgTag = it.imgSrc
            ? `<img src="${it.imgSrc}" class="cart-panel-img" alt="" onerror="this.onerror=null;this.src='https://app.xl.com.ar/imagenes/default.jpg'">`
            : `<div style="width:46px;height:46px;background:#f3f4f6;border-radius:6px;"></div>`;
        // Las clases cp-td-* solo las usa la card del celular (pedidos-mobile.css)
        const importeCell = APP.mostrarPrecio
            ? `<td class="text-right cp-td-importe" data-label="Importe" style="white-space:nowrap">$${parseInt(importe).toLocaleString('es-AR')}</td>`
            : '';
        return `<tr data-panel-cod="${it.cod}">
            <td class="cp-td-img">${imgTag}</td>
            <td class="cp-td-prod">
                <div class="cart-panel-code">${it.cod}</div>
                <div class="cart-panel-desc" title="${it.desc}">${it.desc}</div>
            </td>
            <td class="cp-td-rubro"><span class="cart-panel-rubro">${it.rubro}</span></td>
            <td class="cp-td-qty">
                <div class="cart-panel-qty">
                    <button type="button" class="cart-panel-qty-btn" aria-label="Restar uno" onclick="panelCambiarCant('${it.cod}',-1)"><i class="fas fa-minus" aria-hidden="true"></i></button>
                    <input type="number" inputmode="numeric" aria-label="Cantidad" class="cart-panel-qty-input" value="${it.cant}" min="0" max="${it.stock}"
                           data-panel-cod="${it.cod}"
                           onchange="panelSetCant('${it.cod}', parseInt(this.value)||0)"
                           onkeypress="if(!/[0-9]/.test(event.key)) event.preventDefault()">
                    <button type="button" class="cart-panel-qty-btn" aria-label="Sumar uno" onclick="panelCambiarCant('${it.cod}',1)"><i class="fas fa-plus" aria-hidden="true"></i></button>
                </div>
            </td>
            ${importeCell}
            <td class="cp-td-quitar">
                <button type="button" class="btn-remove-item" onclick="panelEliminar('${it.cod}')" title="Eliminar" aria-label="Quitar artículo">
                    <i class="fas fa-times m-hide" aria-hidden="true"></i><i class="fas fa-trash m-only" aria-hidden="true"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    const thImporte = APP.mostrarPrecio ? '<th class="text-right">Importe</th>' : '';
    body.innerHTML = `<table class="cart-panel-table">
        <thead><tr>
            <th>Foto</th><th>Producto</th><th>Rubro</th><th>Cant.</th>${thImporte}<th></th>
        </tr></thead>
        <tbody>${rows}</tbody>
    </table>`;

    if (footer) {
        footer.style.display = '';
        document.getElementById('cpTotalArticulos').textContent = totalArt;
        document.getElementById('cpTotalUnidades').textContent  = totalUnd;
        const wrapPrecio = document.getElementById('cpTotalPrecioWrap');
        if (wrapPrecio) {
            wrapPrecio.style.display = APP.mostrarPrecio ? '' : 'none';
            if (APP.mostrarPrecio) {
                document.getElementById('cpTotalPrecio').textContent = '$' + parseInt(totalPrecioPanel).toLocaleString('es-AR');
            }
        }
    }
}

function _panelSyncMain(codArt, nuevaCant) {
    // Actualiza el input de la tabla principal y dispara autosave + totales
    const mainInput = document.querySelector(`.qty-input[data-codigo="${CSS.escape(codArt)}"]`);
    if (!mainInput) return;
    mainInput.value = Math.max(0, Math.min(nuevaCant, parseInt(mainInput.getAttribute('data-stock')) || 999));
    mainInput.dispatchEvent(new Event('input')); // autosave debounce + calcularTotales
}

function _panelGetMaxStock(codArt, panelInput) {
    const stockDesdePanel = parseInt(panelInput?.getAttribute('max'));
    if (!Number.isNaN(stockDesdePanel) && stockDesdePanel >= 0) return stockDesdePanel;

    const mainInput = document.querySelector(`.qty-input[data-codigo="${CSS.escape(codArt)}"]`);
    if (!mainInput) return 0;
    return parseInt(mainInput.getAttribute('data-stock')) || 0;
}

function panelCambiarCant(codArt, delta) {
    const panelInput = document.querySelector(`.cart-panel-qty-input[data-panel-cod="${CSS.escape(codArt)}"]`);
    if (!panelInput) return;
    const actual = parseInt(panelInput.value) || 0;
    const stockMax = _panelGetMaxStock(codArt, panelInput);
    const nuevo  = Math.max(0, Math.min(actual + delta, stockMax));
    panelInput.value = nuevo;
    _panelSyncMain(codArt, nuevo);
    if (nuevo === 0) {
        // Eliminar la fila del panel
        const row = panelInput.closest('tr');
        if (row) row.remove();
        _actualizarPanelTotales();
        const tbody = document.querySelector('#cartPanelBody table tbody');
        if (tbody && tbody.children.length === 0) renderCarritoPanel();
    } else {
        _actualizarPanelTotales();
    }
}

function panelSetCant(codArt, nuevaCant) {
    const panelInput = document.querySelector(`.cart-panel-qty-input[data-panel-cod="${CSS.escape(codArt)}"]`);
    const stockMax = _panelGetMaxStock(codArt, panelInput);
    const cantNormalizada = Math.max(0, Math.min(parseInt(nuevaCant) || 0, stockMax));

    if (panelInput) panelInput.value = cantNormalizada;
    _panelSyncMain(codArt, cantNormalizada);

    if (cantNormalizada === 0) {
        const row = document.querySelector(`tr[data-panel-cod="${CSS.escape(codArt)}"]`);
        if (row) row.remove();
        const tbody = document.querySelector('#cartPanelBody table tbody');
        if (tbody && tbody.children.length === 0) renderCarritoPanel();
    }
    _actualizarPanelTotales();
}

function panelEliminar(codArt) {
    _panelSyncMain(codArt, 0);
    const row = document.querySelector(`tr[data-panel-cod="${CSS.escape(codArt)}"]`);
    if (row) {
        row.style.transition = 'opacity .2s';
        row.style.opacity = '0';
        setTimeout(() => {
            row.remove();
            const tbody = document.querySelector('#cartPanelBody table tbody');
            if (!tbody || tbody.children.length === 0) renderCarritoPanel();
            else _actualizarPanelTotales();
        }, 200);
    }
}

function vaciarCarritoPanel() {
    abrirVaciarCarritoModal(() => {
        document.querySelectorAll('.qty-input').forEach(input => {
            if ((parseInt(input.value) || 0) > 0) {
                input.value = 0;
                input.dispatchEvent(new Event('input'));
            }
        });
        renderCarritoPanel();
    });
}

function _actualizarPanelTotales() {
    let art = 0, und = 0, importeTotal = 0;
    document.querySelectorAll('.cart-panel-qty-input').forEach(inp => {
        const c = parseInt(inp.value) || 0;
        if (c > 0) {
            art++; und += c;
            // El precio vive en el input del listado (data-precio), igual que en renderCarritoPanel
            const mainInput = document.querySelector(`.qty-input[data-codigo="${CSS.escape(inp.getAttribute('data-panel-cod'))}"]`);
            const importe = (parseFloat(mainInput?.getAttribute('data-precio')) || 0) * c;
            importeTotal += importe;
            const celdaImporte = inp.closest('tr')?.querySelector('.cp-td-importe');
            if (celdaImporte) celdaImporte.textContent = '$' + parseInt(importe).toLocaleString('es-AR');
        }
    });
    const elA = document.getElementById('cpTotalArticulos');
    const elU = document.getElementById('cpTotalUnidades');
    const elP = document.getElementById('cpTotalPrecio');
    const footer = document.getElementById('cartPanelFooter');
    if (elA) elA.textContent = art;
    if (elU) elU.textContent = und;
    if (elP && APP.mostrarPrecio) elP.textContent = '$' + parseInt(importeTotal).toLocaleString('es-AR');
    if (footer) footer.style.display = art > 0 ? '' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    // Ajustar top del thead sticky al alto del header fijo
    function updateStickyHeaderTop() {
        const header = document.querySelector('.header-container');
        if (header) {
            document.documentElement.style.setProperty('--sticky-header-top', header.offsetHeight + 'px');
        }
    }
    updateStickyHeaderTop();
    window.addEventListener('resize', updateStickyHeaderTop);

    // Setup quick actions
    setupQuickActions();

    // Aplicar marcas de stock insuficiente sembradas en sessionStorage por
    // carrito.php (al cargar) o por envio.js (tras envío fallido).
    aplicarMarcasStockDesdeStorage();

    // Event listeners para inputs de cantidad
    const inputs = document.querySelectorAll('.qty-input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            calcularTotales();
            guardarItemEnCarrito(this);
        });
        
        input.addEventListener('blur', function() {
            verificarStock(this);
            calcularTotales();
        });
        
        input.addEventListener('keypress', function(e) {
            if(!/[0-9]/.test(e.key) && e.key !== 'Enter') {
                e.preventDefault();
            }
        });
    });
    
    // Event listeners para filtros
    const searchProduct = document.getElementById('searchProduct');
    if(searchProduct) {
        searchProduct.addEventListener('input', () => {
            filtrarProductos();
            updateActiveFilters();
        });
    }
    
    const filterRubro = document.getElementById('filterRubro');
    if(filterRubro) {
        filterRubro.addEventListener('change', () => {
            filterCarousel();
            filtrarProductos();
        });
    }
    
    const sortProducts = document.getElementById('sortProducts');
    if(sortProducts) {
        sortProducts.addEventListener('change', () => {
            sortProductTable();
            filtrarProductos();
        });
    }
    
    // Botón limpiar
    const btnClear = document.getElementById('btnClear');
    if(btnClear) {
        btnClear.addEventListener('click', () => {
            document.getElementById('searchProduct').value = '';
            document.getElementById('filterRubro').value = '';
            document.getElementById('sortProducts').value = 'default';
            filterCarousel();
            // Solo filtros: las cantidades se limpian con su propio botón.
            // Hay que volver a filtrar para mostrar las filas que ocultaba la búsqueda.
            filtrarProductos();
            updateActiveFilters();
        });
    }
    
    // Modal de imagen - configurar evento de cierre
    const modal = document.getElementById('imageModal');
    if(modal) {
        modal.addEventListener('click', cerrarModal);
    }
    
    // Calcular totales iniciales
    calcularTotales();
    
    // Inicializar carrusel si existen productos destacados
    if(typeof allTopProducts !== 'undefined' || typeof allNuevosProducts !== 'undefined') {
        initCarousel();
    }
    
    // Animación de filas
    const rows = document.querySelectorAll('.product-table tbody tr');
    rows.forEach((row, index) => {
        row.style.animationDelay = `${index * 0.02}s`;
    });
});

// ========== CARRUSEL DE PRODUCTOS DESTACADOS ==========
let currentPage = { top: 0, nuevo: 0 };
let autoplayInterval = null;
const ITEMS_PER_PAGE = 7;
const AUTOPLAY_INTERVAL = 6000; // 6 segundos
let filteredTopProducts = [];
let filteredNuevosProducts = [];

function initCarousel() {
    // Inicializar productos filtrados con todos los productos
    filteredTopProducts = typeof allTopProducts !== 'undefined' ? [...allTopProducts] : [];
    filteredNuevosProducts = typeof allNuevosProducts !== 'undefined' ? [...allNuevosProducts] : [];
    
    // Renderizar carruseles
    if(filteredTopProducts.length > 0) {
        renderCarousel('top', filteredTopProducts);
        setupCarouselControls('top', filteredTopProducts);
    }
    
    if(filteredNuevosProducts.length > 0) {
        renderCarousel('nuevo', filteredNuevosProducts);
        setupCarouselControls('nuevo', filteredNuevosProducts);
    }
    
    // Tabs
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            
            // Actualizar tabs activos
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Mostrar contenido correspondiente
            document.querySelectorAll('.suggestions-carousel-wrapper').forEach(wrapper => {
                wrapper.classList.remove('active');
            });
            document.querySelector(`[data-content="${tab}"]`).classList.add('active');
            
            // Reiniciar autoplay
            startAutoplay(tab);
        });
    });
    
    // Iniciar autoplay para el tab activo
    const activeTab = document.querySelector('.tab-btn.active');
    if(activeTab) {
        startAutoplay(activeTab.dataset.tab);
    }
}

function renderCarousel(type, products) {
    const carousel = document.getElementById(`carousel-${type}`);
    const indicators = document.getElementById(`indicators-${type}`);
    
    if(!carousel || !indicators) return;
    
    // Limitar a 21 productos (3 páginas de 7)
    const limitedProducts = products.slice(0, 21);
    const totalPages = Math.ceil(limitedProducts.length / ITEMS_PER_PAGE);
    const currentPageNum = currentPage[type];
    
    // Ajustar página si está fuera de rango
    if(currentPageNum >= totalPages) {
        currentPage[type] = 0;
    }
    
    const start = currentPage[type] * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;
    // En el celular el carrusel es una fila deslizable: van todos, sin paginar
    const pageProducts = esVistaCelular() ? limitedProducts : limitedProducts.slice(start, end);
    
    // Fade out
    carousel.classList.remove('fade-in');
    carousel.classList.add('fade-out');
    
    setTimeout(() => {
    
    // Renderizar productos
    carousel.innerHTML = pageProducts.map(prod => {
        const codigo = prod.COD_ARTICU.trim();
        const descripcion = prod.DESCRIPCIO.trim().replace('-- SALE! --', '').trim();
        const stock = parseInt(prod.CANT_STOCK) || 0;
        const imageName = codigo.substring(0, 13);
        const isSale = prod.DESCRIPCIO.includes('-- SALE! --');
        
        // Determinar icono según el tipo de carrusel
        let iconoStock = '';
        if(type === 'top') {
            iconoStock = '<i class="fas fa-fire" style="color: #e74c3c; margin-left: 4px;" title="Más vendido"></i>';
        } else if(type === 'nuevo') {
            iconoStock = '<i class="fas fa-star" style="color: #f39c12; margin-left: 4px;" title="Novedad"></i>';
        }
        
        // Obtener precio si está disponible
        let precioHTML = '';
        if(APP.mostrarPrecio && (prod.PRECIO_MAYO || prod.PRECIO_FRANQ)) {
            const precio = parseInt(prod.PRECIO_MAYO || prod.PRECIO_FRANQ || 0);
            if(precio > 0) {
                precioHTML = `<div class="product-price">$${precio.toLocaleString('es-AR')}</div>`;
            }
        }
        
        // Badge según tipo (dentro de la imagen)
        let badgeInImage = '';
        if(type === 'top') {
            badgeInImage = '<span class="carousel-badge top">🔥 Más Vendidos</span>';
        } else if(type === 'nuevo') {
            badgeInImage = '<span class="carousel-badge new">✨ Novedades</span>';
        }
        
        const saleBadge = isSale ? '<span class="carousel-badge sale">SALE</span>' : '';
        
        return `
            <div class="carousel-item">
                <div class="image-container">
                    <img src="https://app.xl.com.ar/imagenes/${imageName}.jpg" 
                         alt="${descripcion}"
                         onerror="this.src='https://app.xl.com.ar/imagenes/default.jpg'"
                         onclick="scrollToProduct('${codigo}')">
                    ${badgeInImage}
                    ${saleBadge}
                </div>
                <div class="content-wrapper">
                    <span class="product-code">${codigo}</span>
                    <div class="product-name" onclick="scrollToProduct('${codigo}')" style="cursor: pointer;">${descripcion}</div>
                    <div class="product-stock">Stock: ${stock} ${iconoStock}</div>
                    <div class="bottom-row${precioHTML ? '' : ' sin-precio'}">
                        ${precioHTML}
                        <button class="add-btn" onclick="scrollToProduct('${codigo}', event)">
                            <i class="fas fa-arrow-right"></i> Ver producto
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
        // Renderizar indicadores
        indicators.innerHTML = Array.from({length: totalPages}, (_, i) => 
            `<span class="indicator-dot ${i === currentPage[type] ? 'active' : ''}" 
                   onclick="goToPage('${type}', ${i})"></span>`
        ).join('');
        
        // Fade in
        setTimeout(() => {
            carousel.classList.remove('fade-out');
            carousel.classList.add('fade-in');
        }, 50);
    }, 600); // Esperar 600ms para el fade out
}

function setupCarouselControls(type, products) {
    const wrapper = document.querySelector(`[data-content="${type}"]`);
    if(!wrapper) return;
    
    const prevBtn = wrapper.querySelector('.carousel-btn.prev');
    const nextBtn = wrapper.querySelector('.carousel-btn.next');
    
    if(prevBtn) {
        prevBtn.addEventListener('click', () => navigateCarousel(type, products, -1));
    }
    
    if(nextBtn) {
        nextBtn.addEventListener('click', () => navigateCarousel(type, products, 1));
    }
    
    // Pausar autoplay al hover
    wrapper.addEventListener('mouseenter', stopAutoplay);
    wrapper.addEventListener('mouseleave', () => startAutoplay(type));
}

function navigateCarousel(type, products, direction) {
    const limitedProducts = products.slice(0, 21);
    const totalPages = Math.ceil(limitedProducts.length / ITEMS_PER_PAGE);
    currentPage[type] = (currentPage[type] + direction + totalPages) % totalPages;
    renderCarousel(type, products);
}

function goToPage(type, pageNum) {
    currentPage[type] = pageNum;
    const products = type === 'top' ? filteredTopProducts : filteredNuevosProducts;
    renderCarousel(type, products);
}

function filterCarousel() {
    const rubroFilter = document.getElementById('filterRubro').value.toLowerCase();
    
    // Filtrar productos TOP
    if(typeof allTopProducts !== 'undefined') {
        if(rubroFilter) {
            filteredTopProducts = allTopProducts.filter(p => 
                p.RUBRO && p.RUBRO.toLowerCase().includes(rubroFilter)
            );
        } else {
            filteredTopProducts = [...allTopProducts];
        }
        // Limitar a 21
        filteredTopProducts = filteredTopProducts.slice(0, 21);
    }
    
    // Filtrar productos NUEVO
    if(typeof allNuevosProducts !== 'undefined') {
        if(rubroFilter) {
            filteredNuevosProducts = allNuevosProducts.filter(p => 
                p.RUBRO && p.RUBRO.toLowerCase().includes(rubroFilter)
            );
        } else {
            filteredNuevosProducts = [...allNuevosProducts];
        }
        // Limitar a 21
        filteredNuevosProducts = filteredNuevosProducts.slice(0, 21);
    }
    
    // Reset páginas y renderizar
    currentPage.top = 0;
    currentPage.nuevo = 0;
    
    if(filteredTopProducts.length > 0) {
        renderCarousel('top', filteredTopProducts);
    }
    if(filteredNuevosProducts.length > 0) {
        renderCarousel('nuevo', filteredNuevosProducts);
    }
    
    // Actualizar contador de productos
    updateProductCount();
    
    // Actualizar filtros activos
    updateActiveFilters();
}

function updateProductCount() {
    const rows = document.querySelectorAll('.product-table tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        if(row.style.display !== 'none') {
            visibleCount++;
        }
    });
    
    const productCountEl = document.getElementById('productCount');
    if(productCountEl) {
        productCountEl.textContent = visibleCount;
    }
    // Contador del encabezado del listado en el celular
    document.querySelectorAll('.js-product-count').forEach(el => el.textContent = visibleCount);
}

// Al cruzar el corte celular/escritorio: redibuja (con o sin páginas) y retoma o frena el autoplay
function refrescarCarruselPorVista() {
    if(filteredTopProducts.length > 0) renderCarousel('top', filteredTopProducts);
    if(filteredNuevosProducts.length > 0) renderCarousel('nuevo', filteredNuevosProducts);
    const activeTab = document.querySelector('.suggestion-tabs .tab-btn.active');
    if(activeTab) startAutoplay(activeTab.dataset.tab);
}

function startAutoplay(type) {
    stopAutoplay();
    // En el celular no hay autoplay: redibujar cada 6 s mueve la fila mientras se desliza
    if(esVistaCelular()) return;
    autoplayInterval = setInterval(() => {
        const products = type === 'top' ? filteredTopProducts : filteredNuevosProducts;
        if(products && products.length > ITEMS_PER_PAGE) {
            navigateCarousel(type, products, 1);
        }
    }, AUTOPLAY_INTERVAL);
}

function stopAutoplay() {
    if(autoplayInterval) {
        clearInterval(autoplayInterval);
        autoplayInterval = null;
    }
}

// ========== FILTROS ACTIVOS ==========
function updateActiveFilters() {
    const filterChipsContainer = document.getElementById('filterChips');
    const activeFiltersDiv = document.getElementById('activeFilters');
    const rubroSelect = document.getElementById('filterRubro');
    const sortSelect = document.getElementById('sortProducts');
    const searchInput = document.getElementById('searchProduct');
    
    let chips = [];
    
    // Chip de búsqueda
    if(searchInput && searchInput.value.trim()) {
        chips.push({
            type: 'search',
            label: `Búsqueda: ${searchInput.value}`,
            icon: 'fa-search'
        });
    }
    
    // Chip de rubro
    if(rubroSelect && rubroSelect.value) {
        chips.push({
            type: 'rubro',
            label: `Rubro: ${rubroSelect.options[rubroSelect.selectedIndex].text}`,
            icon: 'fa-tag'
        });
    }
    
    // Chip de ordenamiento
    if(sortSelect && sortSelect.value !== 'default') {
        chips.push({
            type: 'sort',
            label: `Orden: ${sortSelect.options[sortSelect.selectedIndex].text.replace(/[🔄⭐✨💰💎📦⚠️🔤]/g, '').trim()}`,
            icon: 'fa-sort'
        });
    }
    
    // Renderizar chips
    if(chips.length > 0) {
        filterChipsContainer.innerHTML = chips.map(chip => `
            <div class="filter-chip">
                <i class="fas ${chip.icon}"></i>
                ${chip.label}
                <i class="fas fa-times" onclick="removeFilter('${chip.type}')"></i>
            </div>
        `).join('');
        activeFiltersDiv.style.display = 'flex';
    } else {
        activeFiltersDiv.style.display = 'none';
    }
}

function removeFilter(type) {
    switch(type) {
        case 'search':
            document.getElementById('searchProduct').value = '';
            break;
        case 'rubro':
            document.getElementById('filterRubro').value = '';
            filterCarousel();
            break;
        case 'sort':
            document.getElementById('sortProducts').value = 'default';
            break;
    }
    filtrarProductos();
    updateActiveFilters();
}

// ========== ORDENAMIENTO DE PRODUCTOS ==========
function sortProductTable() {
    const sortValue = document.getElementById('sortProducts').value;
    const tbody = document.querySelector('.product-table tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        switch(sortValue) {
            case 'ventas-desc':
                const ventasA = parseInt(a.getAttribute('data-ventas') || 0);
                const ventasB = parseInt(b.getAttribute('data-ventas') || 0);
                return ventasB - ventasA;
                
            case 'nuevo':
                const nuevoA = parseInt(a.getAttribute('data-nuevo') || 0);
                const nuevoB = parseInt(b.getAttribute('data-nuevo') || 0);
                if (nuevoB !== nuevoA) return nuevoB - nuevoA;
                return parseInt(b.getAttribute('data-ventas') || 0) - parseInt(a.getAttribute('data-ventas') || 0);
                
            case 'precio-asc':
                const precioA = parseFloat(a.querySelector('.price-display')?.textContent.replace(/[$.]/g, '') || 0);
                const precioB = parseFloat(b.querySelector('.price-display')?.textContent.replace(/[$.]/g, '') || 0);
                return precioA - precioB;
                
            case 'precio-desc':
                const precioDescA = parseFloat(a.querySelector('.price-display')?.textContent.replace(/[$.]/g, '') || 0);
                const precioDescB = parseFloat(b.querySelector('.price-display')?.textContent.replace(/[$.]/g, '') || 0);
                return precioDescB - precioDescA;
                
            case 'stock-desc':
                const stockA = parseInt(a.querySelector('.stock-badge')?.textContent || 0);
                const stockB = parseInt(b.querySelector('.stock-badge')?.textContent || 0);
                return stockB - stockA;
                
            case 'stock-asc':
                const stockAscA = parseInt(a.querySelector('.stock-badge')?.textContent || 0);
                const stockAscB = parseInt(b.querySelector('.stock-badge')?.textContent || 0);
                return stockAscA - stockAscB;
                
            case 'nombre-asc':
                const nombreA = a.getAttribute('data-description') || '';
                const nombreB = b.getAttribute('data-description') || '';
                return nombreA.localeCompare(nombreB);
                
            case 'nombre-desc':
                const nombreDescA = a.getAttribute('data-description') || '';
                const nombreDescB = b.getAttribute('data-description') || '';
                return nombreDescB.localeCompare(nombreDescA);
                
            default:
                return 0;
        }
    });
    
    // Reordenar el DOM
    rows.forEach(row => tbody.appendChild(row));
    
    updateActiveFilters();
}

function scrollToProduct(codigo, event) {
    if(event) event.stopPropagation();
    const rows = document.querySelectorAll('.product-table tbody tr');
    let targetRow = null;
    for(let row of rows) {
        const rowCodigo = row.getAttribute('data-code');
        if(rowCodigo && rowCodigo.toLowerCase() === codigo.toLowerCase()) {
            targetRow = row;
            break;
        }
    }
    if(!targetRow) return;

    // Si el filtro de búsqueda/rubro está ocultando la fila, limpiarlo para poder llegar al producto
    if(targetRow.style.display === 'none') {
        const searchInput = document.getElementById('searchProduct');
        const rubroSelect = document.getElementById('filterRubro');
        if(searchInput) searchInput.value = '';
        if(rubroSelect) rubroSelect.value = '';
        filtrarProductos();
        updateActiveFilters();
    }

    targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    targetRow.classList.remove('row-highlight');
    // Forzar reflow para poder reiniciar la animación si ya se había resaltado antes
    void targetRow.offsetWidth;
    targetRow.classList.add('row-highlight');
    setTimeout(() => {
        targetRow.classList.remove('row-highlight');
    }, 2000);

    // Focus en el input de cantidad (preventScroll para no pisar el scroll suave ya realizado)
    const input = targetRow.querySelector('.qty-input');
    if(input && !input.disabled) {
        setTimeout(() => input.focus({ preventScroll: true }), 500);
    }
}

// Atajos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + F para buscar
    if((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('searchProduct');
        if(searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    // Escape para limpiar búsqueda
    if(e.key === 'Escape') {
        const searchInput = document.getElementById('searchProduct');
        if(searchInput && searchInput.value) {
            searchInput.value = '';
            filtrarProductos();
        }
    }
});

// ========== EXPORTACIÓN (SheetJS XLSX) ==========

// Exportar todos los productos VISIBLES de la tabla principal como .xlsx
function exportarExcel() {
    if (typeof XLSX === 'undefined') {
        alert('La librería de exportación no está cargada. Recargá la página e intentá de nuevo.');
        return;
    }

    const headers = ['Código', 'Descripción', 'Rubro', 'Stock Central'];
    if (APP.mostrarStockLocal) headers.push('Stock Local');
    if (APP.mostrarVentas30d)  headers.push('Ventas 30d');
    if (APP.mostrarSugerencia) headers.push('Sugerencia');
    headers.push('Distribución');
    if (APP.mostrarPrecio)     headers.push('Precio');
    headers.push('Cantidad pedida');

    const data = [headers];

    document.querySelectorAll('.product-table tbody tr').forEach(tr => {
        if (tr.style.display === 'none') return; // respetar filtros activos

        const cod    = tr.querySelector('input[name="codArt[]"]')?.value || tr.dataset.code || '';
        const desc   = tr.dataset.description
            ? tr.dataset.description.replace(/\b\w/g, c => c.toUpperCase())
            : '';
        const rubro  = tr.dataset.rubro || '';
        const stockC = parseInt(tr.querySelector('input[name="stock[]"]')?.value) || 0;
        const distri = parseInt(tr.dataset.distri) || 0;
        const precio = parseInt(tr.dataset.precio) || 0;
        const cant   = parseInt(tr.querySelector('.qty-input')?.value) || 0;

        const sugerEl = tr.querySelector('[data-sugerencia]');
        const suger   = sugerEl ? (parseInt(sugerEl.dataset.sugerencia) || 0) : '';

        const stockLEl = document.getElementById('stockLocal_' + cod);
        const stockL   = stockLEl ? (parseInt(stockLEl.textContent.trim()) || 0) : '';

        const ventas30El = document.getElementById('ventas30_' + cod);
        const ventas30   = ventas30El ? (parseInt(ventas30El.textContent.trim()) || 0) : '';

        const row = [cod, desc, rubro, stockC];
        if (APP.mostrarStockLocal) row.push(stockL);
        if (APP.mostrarVentas30d)  row.push(ventas30);
        if (APP.mostrarSugerencia) row.push(suger);
        row.push(distri);
        if (APP.mostrarPrecio)     row.push(precio);
        row.push(cant);

        data.push(row);
    });

    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = [
        {wch: 16}, {wch: 40}, {wch: 22}, {wch: 14},
        ...(APP.mostrarStockLocal ? [{wch: 12}] : []),
        ...(APP.mostrarVentas30d  ? [{wch: 12}] : []),
        ...(APP.mostrarSugerencia ? [{wch: 12}] : []),
        {wch: 14},
        ...(APP.mostrarPrecio     ? [{wch: 14}] : []),
        {wch: 16}
    ];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Pedido');
    const fecha = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `pedido_${APP.tipoPedido}_${fecha}.xlsx`);
}

// Exportar solo los ítems que están en el carrito (cantidad > 0) como .xlsx
function exportarCarrito() {
    if (typeof XLSX === 'undefined') {
        alert('La librería de exportación no está cargada. Recargá la página e intentá de nuevo.');
        return;
    }

    const headers = ['Código', 'Descripción', 'Rubro', 'Cantidad pedida'];
    if (APP.mostrarPrecio) headers.push('Precio unitario');

    const data = [headers];

    document.querySelectorAll('.qty-input').forEach(input => {
        const cant = parseInt(input.value) || 0;
        if (cant <= 0) return;

        const tr    = input.closest('tr');
        const cod   = input.getAttribute('data-codigo') || '';
        const rubro = input.getAttribute('data-rubro')  || '';
        const precio = parseInt(input.getAttribute('data-precio')) || 0;
        const desc  = tr
            ? (tr.dataset.description || '').replace(/\b\w/g, c => c.toUpperCase())
            : '';

        const row = [cod, desc, rubro, cant];
        if (APP.mostrarPrecio) row.push(precio);
        data.push(row);
    });

    if (data.length === 1) {
        alert('El carrito está vacío — no hay nada que exportar.');
        return;
    }

    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = [
        {wch: 16}, {wch: 40}, {wch: 22}, {wch: 16},
        ...(APP.mostrarPrecio ? [{wch: 16}] : [])
    ];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Carrito');
    const fecha = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `carrito_${APP.tipoPedido}_${fecha}.xlsx`);
}

// ========== BFCACHE: RE-SINCRONIZAR CARRITO AL VOLVER ==========
// Cuando el navegador restaura la página desde bfcache (vuelta instantánea
// desde carrito.php), los qty-inputs pueden estar desactualizados si el
// usuario modificó ítems en el carrito.
// carrito.php escribe el estado actualizado en sessionStorage['carritoSnapshot']
// al modificar ítems, evitando cualquier fetch (y por ende ningún lock de sesión).
window.addEventListener('pageshow', function(e) {
    if (!e.persisted) return; // No es restauración desde bfcache

    // Re-inyectar el prefetch de carrito.php: el bfcache no re-ejecuta
    // los <link rel="prefetch"> del HTML original, así que lo hacemos via JS.
    const pf = document.createElement('link');
    pf.rel  = 'prefetch';
    pf.href = 'carrito.php';
    document.head.appendChild(pf);

    const raw = sessionStorage.getItem('carritoSnapshot');
    if (raw) {
        try {
            const snap = JSON.parse(raw);
            sessionStorage.removeItem('carritoSnapshot'); // limpiar tras aplicar

            document.querySelectorAll('.qty-input').forEach(input => {
                const cod    = input.getAttribute('data-codigo');
                const cantDB  = snap[cod] !== undefined ? parseInt(snap[cod]) || 0 : 0;
                const cantDOM = parseInt(input.value) || 0;
                if (cantDB !== cantDOM) input.value = cantDB;
            });

            calcularTotales();
        } catch(err) {
            sessionStorage.removeItem('carritoSnapshot');
        }
    }

    // Re-aplicar marcas de stock insuficiente (carrito.php pudo haberlas sembrado).
    // Se hace después del snapshot porque calcularTotales puede limpiar marcas si
    // las cantidades ya cumplen.
    aplicarMarcasStockDesdeStorage();
});
