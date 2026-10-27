/**
 * Sistema de navegación segura sin exposición de datos en URL
 * Usa formularios POST y sesiones
 */

// Navegar a una página con datos POST
function navigateTo(url, data = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    
    for (const [key, value] of Object.entries(data)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}

// Seleccionar cliente (desde index.php a pedidos.php)
function seleccionarCliente(codCliente, razonSocial) {
    navigateTo('pedidos.php', {
        'accion': 'seleccionar_cliente',
        'cod_cliente': codCliente,
        'razon_social': razonSocial
    });
}

// Ver estado de cuenta (desde index.php a ppp.php)
function verEstadoCuenta(codVendedor) {
    navigateTo('credito/ppp.php', {
        'accion': 'listar_clientes',
        'cod_vendedor': codVendedor
    });
}

// Ver detalle de crédito (desde ppp.php a pppDetalle.php)
function verDetalleCredito(codCliente, razonSocial) {
    navigateTo('pppDetalle.php', {
        'accion': 'ver_detalle',
        'cod_cliente': codCliente,
        'razon_social': razonSocial
    });
}

// Ver detalle de recibo (desde pppDetalle.php a pppDetalleRec.php)
function verDetalleRecibo(numeroRecibo, codCliente) {
    navigateTo('pppDetalleRec.php', {
        'accion': 'ver_recibo',
        'numero_recibo': numeroRecibo,
        'cod_cliente': codCliente
    });
}

// Ver cheques (desde ppp.php a pppCheque.php)
function verCheques(codCliente, razonSocial) {
    navigateTo('pppCheque.php', {
        'accion': 'ver_cheques',
        'cod_cliente': codCliente,
        'razon_social': razonSocial
    });
}

// Ver detalle de pedido (desde historial.php a detallePed.php)
function verDetallePedido(numeroPedido, sucursal, tipo, origen = 'cliente') {
    navigateTo('detallePed.php', {
        'accion': 'ver_detalle',
        'numero_pedido': numeroPedido,
        'sucursal': sucursal,
        'tipo': tipo,
        'origen': origen
    });
}

// Volver a página anterior usando POST
function volverSeguro(url, data = {}) {
    navigateTo(url, data);
}

// Mostrar indicador de carga
function showLoading() {
    if (!document.getElementById('loadingOverlay')) {
        const overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;
        overlay.innerHTML = '<div style="background: white; padding: 30px; border-radius: 10px; text-align: center;"><i class="fas fa-spinner fa-spin fa-3x" style="color: #3c4043;"></i><p style="margin-top: 15px; font-size: 16px; color: #3c4043;">Cargando...</p></div>';
        document.body.appendChild(overlay);
    }
}
