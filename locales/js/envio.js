/**
 * envio.js — Lógica de envío y modales de pedidos.
 * Depende de:
 *   - pedidos.js  (para totalArticulos, totalCantidad, totalPrecio, cerrarModal)
 *   - APP         (objeto de config inyectado por PHP en pedidos.php)
 */

// Evita doble envío (doble click / modal aún visible durante la transición)
let pedidoEnCurso = false;

// ── Cierre global con ESC ─────────────────────────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        if (pedidoEnCurso) return; // no cerrar modales mientras se procesa
        cerrarModal();
        cerrarConfirmModal();
        cerrarCreditoModal();
        cerrarErrorModal();
        cerrarLimpiarModal();
        cerrarCarritoPanel();
        if (typeof cerrarVaciarCarritoModal === 'function') cerrarVaciarCarritoModal();
    }
});

// ── Limpiar cantidades ───────────────────────────────────────────────────────
function limpiarCantidades() {
    if (totalArticulos === 0) return;
    document.getElementById('limpiarModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function cerrarLimpiarModal() {
    document.getElementById('limpiarModal').classList.remove('active');
    document.body.style.overflow = '';
}

function confirmarLimpiar() {
    cerrarLimpiarModal();
    document.querySelectorAll('.qty-input').forEach(input => {
        if (!input.disabled) {
            input.value = 0;
            input.classList.remove('has-value', 'error');
        }
    });
    calcularTotales();
}

// ── Helpers de modales ────────────────────────────────────────────────────────
function cerrarConfirmModal() {
    const modal = document.getElementById('confirmOrderModal');
    if (!modal) return;
    modal.classList.remove('active');
    // Evita clicks durante la transición CSS (~300ms) del modal
    modal.style.pointerEvents = 'none';
    document.body.style.overflow = '';
}

function abrirConfirmModal() {
    const modal = document.getElementById('confirmOrderModal');
    if (!modal) return;
    modal.style.pointerEvents = '';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function setBotonesEnvioDisabled(disabled) {
    const confirmBtn = document.getElementById('btnConfirmarEnvioPedido')
        || document.querySelector('#confirmOrderModal .btn-confirm-send');
    if (confirmBtn) {
        confirmBtn.disabled = disabled;
        confirmBtn.style.pointerEvents = disabled ? 'none' : '';
        confirmBtn.style.opacity = disabled ? '0.7' : '';
    }

    const cartSendBtn = document.getElementById('cartPanelSendBtn');
    if (cartSendBtn) cartSendBtn.disabled = disabled;

    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn && submitBtn.textContent && submitBtn.textContent.indexOf('Enviar') !== -1) {
        submitBtn.disabled = disabled;
    }
}

function liberarEnvio() {
    pedidoEnCurso = false;
    setBotonesEnvioDisabled(false);
}

function cerrarCreditoModal() {
    document.getElementById('creditoModal').classList.remove('active');
    document.body.style.overflow = '';
}

function cerrarErrorModal() {
    document.getElementById('errorPedidoModal').classList.remove('active');
    document.body.style.overflow = '';
}

function mostrarProgreso() {
    const m = document.getElementById('progressModal');
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
}

function ocultarProgreso() {
    const m = document.getElementById('progressModal');
    if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
}

function mostrarConfirmacionDuplicado(data) {
    const nro = data.nroPedidoUltimo || '—';
    document.getElementById('errorPedidoTitulo').textContent = 'Pedido igual al último';
    document.getElementById('errorPedidoCuerpo').innerHTML = `
        <p class="confirm-modal-subtitle" style="margin-bottom:12px;">
            Este pedido tiene <strong>los mismos artículos y cantidades</strong>
            que el último cargado (<strong>Nro ${nro}</strong>).
        </p>
        <p class="confirm-modal-note" style="background:#fffbeb; border-left:4px solid #f59e0b; color:#78350f;">
            <i class="fas fa-exclamation-triangle"></i>
            Si ya lo enviaste y falló la conexión, revisá el Historial antes de mandarlo de nuevo.
        </p>`;
    const acciones = document.getElementById('errorPedidoAcciones');
    if (acciones) {
        acciones.innerHTML = `
            <button type="button" class="btn-confirm-send" onclick="confirmarEnvioDuplicado()" style="width:100%;">
                <i class="fas fa-paper-plane"></i> Sí, enviar de nuevo
            </button>
            <button type="button" class="btn-confirm-cancel" onclick="irAHistorialRevisar()" style="width:100%;">
                <i class="fas fa-history"></i> Revisar Historial
            </button>
            <button type="button" class="btn-confirm-cancel" onclick="cerrarErrorModal()" style="width:100%;">
                <i class="fas fa-times"></i> Cancelar
            </button>`;
    }
    document.getElementById('errorPedidoModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function mostrarErrorPedido(data) {
    const esStock = data.error === 'Stock insuficiente';
    const esConexion = data.error === 'Error de conexión' || data.pedirRevisarHistorial === true;
    const lista   = Array.isArray(data.articulos_sin_stock) ? data.articulos_sin_stock : null;
    let cuerpo = '';

    const acciones = document.getElementById('errorPedidoAcciones');

    if (esConexion) {
        document.getElementById('errorPedidoTitulo').textContent = 'No se pudo confirmar el envío';
        cuerpo = `
            <p class="confirm-modal-subtitle" style="margin-bottom:12px;">
                Puede que el pedido <strong>sí se haya cargado</strong> aunque no recibimos respuesta del servidor.
            </p>
            <p class="confirm-modal-note" style="background:#fffbeb; border-left:4px solid #f59e0b; color:#78350f;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Antes de volver a enviar:</strong> revisá el Historial.
                Si el pedido ya figura, <strong>no lo cargues de nuevo</strong>.
                Solo reintentá si no aparece ahí.
            </p>`;
        if (acciones) {
            acciones.innerHTML = `
                <button type="button" class="btn-confirm-send" onclick="irAHistorialRevisar()" style="width:100%;">
                    <i class="fas fa-history"></i> Revisar Historial
                </button>
                <button type="button" class="btn-confirm-cancel" onclick="cerrarErrorModal()" style="width:100%;">
                    <i class="fas fa-arrow-left"></i> Volver al pedido
                </button>`;
        }
    } else if (esStock && lista && lista.length > 0) {
        document.getElementById('errorPedidoTitulo').textContent = 'Stock insuficiente';
        const filas = lista.map(a => `
            <tr>
                <td style="padding:6px 8px; font-family:monospace;">${a.articulo}</td>
                <td style="padding:6px 8px; text-align:center; color:#10b981; font-weight:700;">${a.stock_disponible}</td>
                <td style="padding:6px 8px; text-align:center; color:#ef4444; font-weight:700;">${a.cantidad_solicitada}</td>
            </tr>
        `).join('');
        cuerpo = `
            <p class="confirm-modal-subtitle">${lista.length} artículo${lista.length === 1 ? '' : 's'} sin stock suficiente:</p>
            <div style="max-height:240px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:6px; margin:12px 0;">
                <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                    <thead style="background:#f9fafb; position:sticky; top:0;">
                        <tr>
                            <th style="padding:8px; text-align:left;">Código</th>
                            <th style="padding:8px;">Disp.</th>
                            <th style="padding:8px;">Pedido</th>
                        </tr>
                    </thead>
                    <tbody>${filas}</tbody>
                </table>
            </div>
            <p class="confirm-modal-note" style="background:#fffbeb; border-left:4px solid #f59e0b; color:#78350f;">
                <i class="fas fa-info-circle"></i>
                Ajustá las cantidades y volvé a intentar el envío.
            </p>`;
        if (acciones) {
            acciones.innerHTML = `
                <button type="button" class="btn-confirm-cancel" onclick="cerrarErrorModal()" style="width:100%;">
                    <i class="fas fa-arrow-left"></i> Volver al pedido
                </button>`;
        }
    } else if (esStock && data.detalles) {
        const art        = data.detalles.articulo || '';
        const disponible = data.detalles.stock_disponible ?? 0;
        const solicitado = data.detalles.cantidad_solicitada ?? 0;

        document.getElementById('errorPedidoTitulo').textContent = 'Stock insuficiente';
        cuerpo = `
            <p class="confirm-modal-subtitle">No hay unidades suficientes para el artículo:</p>
            <div class="confirm-modal-details">
                <div class="confirm-detail-item" style="justify-content:center; font-weight:700; font-size:1.05rem; letter-spacing:.5px;">
                    <i class="fas fa-barcode"></i> ${art}
                </div>
                <div class="confirm-detail-item">
                    <i class="fas fa-check-circle" style="color:#10b981"></i>
                    Stock disponible: <strong>${disponible} u.</strong>
                </div>
                <div class="confirm-detail-item">
                    <i class="fas fa-shopping-cart" style="color:#ef4444"></i>
                    Cantidad pedida: <strong>${solicitado} u.</strong>
                </div>
            </div>
            <p class="confirm-modal-note" style="background:#fffbeb; border-left:4px solid #f59e0b; color:#78350f;">
                <i class="fas fa-info-circle"></i>
                Ajustá la cantidad del artículo e intentá enviar el pedido nuevamente.
            </p>`;
        if (acciones) {
            acciones.innerHTML = `
                <button type="button" class="btn-confirm-cancel" onclick="cerrarErrorModal()" style="width:100%;">
                    <i class="fas fa-arrow-left"></i> Volver al pedido
                </button>`;
        }
    } else {
        document.getElementById('errorPedidoTitulo').textContent = data.error || 'Error en el pedido';
        cuerpo = `
            <p class="confirm-modal-note" style="background:#fffbeb; border-left:4px solid #f59e0b; color:#78350f;">
                <i class="fas fa-info-circle"></i>
                ${data.mensaje || 'Ocurrió un error inesperado. Intentá nuevamente.'}
            </p>`;
        if (acciones) {
            acciones.innerHTML = `
                <button type="button" class="btn-confirm-cancel" onclick="cerrarErrorModal()" style="width:100%;">
                    <i class="fas fa-arrow-left"></i> Volver al pedido
                </button>`;
        }
    }

    document.getElementById('errorPedidoCuerpo').innerHTML = cuerpo;
    document.getElementById('errorPedidoModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Aplica la clase .row-sin-stock + data-stock-disp en filas/inputs visibles para
// los códigos del mapa, muestra el banner-leyenda, persiste en sessionStorage y
// deshabilita el botón de envío. Funciona igual en carrito.php y pedidos.php.
function marcarArticulosSinStock(map) {
    if (!map || typeof map !== 'object') map = {};
    const codes = Object.keys(map);

    codes.forEach(cod => {
        const disp = parseInt(map[cod]) || 0;
        document.querySelectorAll(`.qty-input[data-codigo="${CSS.escape(cod)}"]`).forEach(input => {
            input.setAttribute('data-stock-disp', disp);
            input.setAttribute('max', disp);
            const row = input.closest('tr');
            if (row) row.classList.add('row-sin-stock');
        });
    });

    if (codes.length > 0) {
        sessionStorage.setItem('articulosSinStock', JSON.stringify(map));
    } else {
        sessionStorage.removeItem('articulosSinStock');
    }

    const banner  = document.getElementById('stockWarningBanner');
    const counter = document.getElementById('stockWarningCount');
    if (counter) counter.textContent = codes.length;
    if (banner)  banner.classList.toggle('hidden', codes.length === 0);

    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn && codes.length > 0) submitBtn.disabled = true;
}

function mostrarExito(nroPedido) {
    cerrarConfirmModal();
    document.getElementById('successNroPedido').textContent = nroPedido;
    document.getElementById('successModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function irAHistorial() {
    // Limpiar el carrito en DB antes de ir al historial
    fetch('controller/carritoAjax.php?action=limpiar')
        .catch(() => {})
        .finally(() => { window.location.href = 'historial.php'; });
}

// Tras error de conexión: ir a historial SIN vaciar carrito
// (el pedido pudo no haberse creado; el usuario decide según lo que vea)
function irAHistorialRevisar() {
    window.location.href = 'historial.php';
}

// ── Envío de pedido ───────────────────────────────────────────────────────────
function enviarPedido() {
    if (pedidoEnCurso) {
        return false;
    }

    if (totalArticulos === 0) {
        alert('Debe seleccionar al menos un artículo con cantidad');
        return false;
    }

    // Debug de crédito
    console.log('=== VALIDACIÓN DE CRÉDITO ===');
    console.log('suc:', APP.suc, '| es externo (>100):', parseInt(APP.suc) > 100);
    console.log('cupoCredi (sesión):', APP.cupoCredi);
    console.log('totalPrecio (pedido actual):', totalPrecio);
    console.log('totalArticulos:', totalArticulos, '| totalCantidad:', totalCantidad);
    if (parseInt(APP.suc) > 100) {
        const diff = APP.cupoCredi - totalPrecio;
        console.log('Diferencia (cupo - pedido):', diff, '→', diff < 0 ? 'EXCEDIDO' : 'OK');
    }
    console.log('=============================');

    // Validación de cupo (clientes externos, suc > 100)
    if (parseInt(APP.suc) > 100 && totalPrecio > APP.cupoCredi) {
        console.warn('❌ Pedido bloqueado por cupo insuficiente.');
        document.getElementById('creditoModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        return false;
    }

    // Mostrar modal de confirmación
    document.getElementById('confirmArticulos').textContent = totalArticulos;
    document.getElementById('confirmUnidades').textContent  = totalCantidad;
    const confirmTotalEl = document.getElementById('confirmTotal');
    if (confirmTotalEl) confirmTotalEl.textContent = parseInt(totalPrecio).toLocaleString('es-AR');
    abrirConfirmModal();
}

function confirmarEnvioDuplicado() {
    cerrarErrorModal();
    confirmarEnvio(true);
}

function confirmarEnvio(forzarDuplicado) {
    if (pedidoEnCurso) {
        return;
    }
    pedidoEnCurso = true;
    setBotonesEnvioDisabled(true);

    cerrarConfirmModal();
    mostrarProgreso();

    // Recopilar artículos con cantidad > 0
    const matriz = [];
    document.querySelectorAll('.qty-input').forEach((input, index) => {
        const cantidad = parseInt(input.value) || 0;
        if (cantidad > 0) {
            const row    = input.closest('tr');
            const codigo = row.querySelector('input[name="codArt[]"]').value;
            const rubro  = row.getAttribute('data-rubro');
            const stock  = input.getAttribute('data-stock');
            // Formato esperado por cargarPedidoNuevo.php
            matriz.push([index, codigo, '', '', '', '', '', '', cantidad, rubro, stock]);
        }
    });

    console.log('=== ENVÍO PEDIDO ===');
    console.log('tipoPedido:', APP.tipoPedido, '| suc:', APP.suc, '| codClient:', APP.codClient);
    console.log('forzarDuplicado:', !!forzarDuplicado);
    console.log('matriz:', matriz.map(r => ({ cod: r[1], cant: r[8], stock: r[10] })));

    const body = {
        numsuc:      APP.suc,
        codClient:   APP.codClient,
        tipo_pedido: APP.tipoPedido,
        depo:        APP.depo,
        matriz:      JSON.stringify(matriz)
    };
    if (forzarDuplicado) {
        body.forzar_duplicado = '1';
    }

    fetch('controller/cargarPedidoNuevo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(body)
    })
    .then(r => r.json())
    .then(data => {
        console.log('=== RESPUESTA SERVIDOR ===', data);
        ocultarProgreso();
        if (data.success) {
            // Éxito: mantener bloqueo (ir a historial / no reenviar)
            mostrarExito(data.nroPedido);
        } else if (data.requiere_confirmacion_duplicado) {
            liberarEnvio();
            mostrarConfirmacionDuplicado(data);
        } else {
            // Error de negocio (stock, validación): permitir reintento corregido
            if (Array.isArray(data.articulos_sin_stock) && data.articulos_sin_stock.length > 0) {
                const map = {};
                data.articulos_sin_stock.forEach(a => { map[a.articulo] = a.stock_disponible; });
                marcarArticulosSinStock(map);
            }
            mostrarErrorPedido(data);

            const hayStockProblema = Array.isArray(data.articulos_sin_stock) && data.articulos_sin_stock.length > 0;
            if (!hayStockProblema) {
                liberarEnvio();
            } else {
                // Stock: liberar flag pero dejar botones bloqueados por marcarArticulosSinStock
                pedidoEnCurso = false;
                setBotonesEnvioDisabled(true);
            }
        }
    })
    .catch(error => {
        ocultarProgreso();
        console.error('Error fetch:', error);
        // Ambiguo: el SP pudo haber insertado. No rehabilitar envío automático.
        mostrarErrorPedido({
            error:   'Error de conexión',
            pedirRevisarHistorial: true,
            mensaje: 'Puede que el pedido sí se haya cargado. Revisá el Historial antes de reenviar.'
        });
    });
}

// ── Simulación dry-run (sin escribir en BD) ───────────────────────────────────
function simularEnvio() {
    const dryRunBtn = document.getElementById('dryRunBtn');
    if (dryRunBtn) {
        dryRunBtn.disabled  = true;
        dryRunBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Simulando...';
    }

    const matriz = [];
    document.querySelectorAll('.qty-input').forEach((input, index) => {
        const cantidad = parseInt(input.value) || 0;
        if (cantidad > 0) {
            const row    = input.closest('tr');
            const codigo = row.querySelector('input[name="codArt[]"]').value;
            const rubro  = row.getAttribute('data-rubro');
            const stock  = input.getAttribute('data-stock');
            matriz.push([index, codigo, '', '', '', '', '', '', cantidad, rubro, stock]);
        }
    });

    const tsInicio = Date.now();

    fetch('controller/cargarPedidoNuevo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            numsuc:      APP.suc,
            codClient:   APP.codClient,
            tipo_pedido: APP.tipoPedido,
            depo:        APP.depo,
            matriz:      JSON.stringify(matriz),
            dry_run:     '1'
        })
    })
    .then(r => r.json())
    .then(data => {
        const totalFrontend = Date.now() - tsInicio;
        console.log('=== DRY RUN RESULTADO ===', data);

        let titulo, cuerpo;

        if (data.dry_run) {
            titulo = 'Simulacion completada — sin cambios en BD';
            const t = data.timing || {};
            const filas = [
                ['Articulos procesados', `<strong>${data.articulos}</strong>`],
                t.credito_ms !== undefined ? ['Credito validado', `<strong>${t.credito_ms} ms</strong>`] : null,
                t.stock_ms   !== undefined ? ['Stock validado',   `<strong>${t.stock_ms} ms</strong>`]   : null,
                t.build_ms   !== undefined ? ['Build query',      `<strong>${t.build_ms} ms</strong>`]   : null,
                ['Total servidor', `<strong>${t.total_ms ?? '?'} ms</strong>`],
                ['Total frontend', `<strong>${totalFrontend} ms</strong>`],
                ['Validaciones', `<strong style="color:green;">${data.validaciones}</strong>`],
            ].filter(Boolean);

            const tabla = filas.map(([k, v]) =>
                `<tr><td style="padding:3px 10px 3px 0;color:#555;">${k}</td><td>${v}</td></tr>`
            ).join('');

            cuerpo = `<table style="font-family:monospace;font-size:0.85em;border-collapse:collapse;">${tabla}</table>
                      <p style="margin-top:12px;font-size:0.78em;color:#888;word-break:break-all;">${data.query_preview}</p>`;
        } else {
            titulo = 'Error en simulacion: ' + (data.error || 'desconocido');
            cuerpo = data.mensaje || '';
        }

        mostrarErrorPedido({ error: titulo, mensaje: cuerpo });
    })
    .catch(err => {
        const totalFrontend = Date.now() - tsInicio;
        console.error('DryRun fetch error:', err);
        mostrarErrorPedido({
            error:   'Timeout / error de conexion en SIMULACION',
            mensaje: `El servidor no respondio despues de ${totalFrontend} ms. Este puede ser el mismo error que falla en produccion.`
        });
    })
    .finally(() => {
        if (dryRunBtn) {
            dryRunBtn.disabled  = false;
            dryRunBtn.innerHTML = '<i class="fas fa-flask"></i> Simular';
        }
    });
}

// ── Offset dinámico del thead sticky ─────────────────────────────────────────
function ajustarStickyThead() {
    const header = document.querySelector('.header-container');
    const thead  = document.querySelector('.product-table thead');
    if (header && thead) {
        thead.style.top = header.offsetHeight + 'px';
    }
}

document.addEventListener('DOMContentLoaded', ajustarStickyThead);
window.addEventListener('resize', ajustarStickyThead);

// ── Utilidades ────────────────────────────────────────────────────────────────
