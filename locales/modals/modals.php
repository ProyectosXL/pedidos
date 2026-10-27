<?php
// modals.php — Todos los modales de la página de pedidos.
// Se incluye al final del <body> en pedidos.php
?>

<!-- Modal: Confirmar vaciar carrito -->
<div id="vaciarCarritoModal" class="confirm-modal">
    <div class="confirm-modal-content" style="max-width: 440px;">
        <div class="confirm-modal-icon" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
            <i class="fas fa-trash" style="font-size: 3rem;"></i>
        </div>
        <h2 class="confirm-modal-title" style="color: #ef4444;">¿Vaciar el carrito?</h2>
        <p class="confirm-modal-subtitle">Se eliminarán todos los artículos seleccionados.</p>
        <p class="confirm-modal-note" style="background: #fef2f2; border-left: 4px solid #ef4444; color: #7f1d1d;">
            <i class="fas fa-exclamation-triangle"></i>
            Esta acción no se puede deshacer.
        </p>
        <div class="confirm-modal-actions">
            <button type="button" class="btn-confirm-send" onclick="confirmarVaciarCarrito()"
                    style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
                <i class="fas fa-trash"></i> Sí, vaciar
            </button>
            <button type="button" class="btn-confirm-cancel" onclick="cerrarVaciarCarritoModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>
<script>
let _vaciarCarritoCallback = null;

function abrirVaciarCarritoModal(callback) {
    _vaciarCarritoCallback = callback || null;
    document.getElementById('vaciarCarritoModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function cerrarVaciarCarritoModal() {
    document.getElementById('vaciarCarritoModal').classList.remove('active');
    document.body.style.overflow = '';
    _vaciarCarritoCallback = null;
}

function confirmarVaciarCarrito() {
    const cb = _vaciarCarritoCallback;
    cerrarVaciarCarritoModal();
    if (typeof cb === 'function') cb();
}
</script>

<!-- Modal: Confirmar limpieza de cantidades -->
<div id="limpiarModal" class="confirm-modal">
    <div class="confirm-modal-content" style="max-width: 440px;">
        <div class="confirm-modal-icon" style="background: linear-gradient(135deg, #6b7280 0%, #374151 100%);">
            <i class="fas fa-eraser" style="font-size: 3rem;"></i>
        </div>
        <h2 class="confirm-modal-title" style="color: #374151;">Limpiar cantidades</h2>
        <p class="confirm-modal-subtitle">Se borrarán todas las cantidades ingresadas.</p>
        <p class="confirm-modal-note" style="background: #f9fafb; border-left: 4px solid #6b7280; color: #374151;">
            <i class="fas fa-info-circle"></i>
            Esta acción no se puede deshacer.
        </p>
        <div class="confirm-modal-actions">
            <button type="button" class="btn-confirm-send" onclick="confirmarLimpiar()" style="background: linear-gradient(135deg, #6b7280 0%, #374151 100%);">
                <i class="fas fa-eraser"></i> Sí, limpiar
            </button>
            <button type="button" class="btn-confirm-cancel" onclick="cerrarLimpiarModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Modal de imagen ampliada -->
<div id="imageModal" class="image-modal" onclick="cerrarModal(event)">
    <span class="close-modal" onclick="cerrarModal(event)">&times;</span>
    <img id="modalImage" src="" alt="Producto" onclick="event.stopPropagation()">
</div>

<!-- Modal: Error genérico / stock insuficiente en backend -->
<div id="errorPedidoModal" class="confirm-modal">
    <div class="confirm-modal-content" style="max-width: 480px;">
        <div class="confirm-modal-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <i class="fas fa-exclamation-triangle" style="font-size: 3rem;"></i>
        </div>
        <h2 class="confirm-modal-title" id="errorPedidoTitulo" style="color: #d97706;">Error en el pedido</h2>
        <div id="errorPedidoCuerpo"></div>
        <div class="confirm-modal-actions" id="errorPedidoAcciones">
            <button type="button" class="btn-confirm-cancel" onclick="cerrarErrorModal()" style="width: 100%;">
                <i class="fas fa-arrow-left"></i> Volver al pedido
            </button>
        </div>
    </div>
</div>

<!-- Modal: Crédito insuficiente -->
<div id="creditoModal" class="confirm-modal">
    <div class="confirm-modal-content" style="max-width: 480px;">
        <div class="confirm-modal-icon" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
            <i class="fas fa-ban" style="font-size: 3rem;"></i>
        </div>
        <h2 class="confirm-modal-title" style="color: #ef4444;">Crédito insuficiente</h2>
        <p class="confirm-modal-subtitle">No es posible procesar el pedido.</p>
        <p class="confirm-modal-note" style="background: #fef2f2; border-left: 4px solid #ef4444; color: #7f1d1d;">
            <i class="fas fa-exclamation-triangle"></i>
            El monto total del pedido supera el cupo de crédito disponible.
            Reducí las cantidades o contactá a tu ejecutivo de cuenta.
        </p>
        <div class="confirm-modal-actions">
            <button type="button" class="btn-confirm-cancel" onclick="cerrarCreditoModal()" style="width: 100%;">
                <i class="fas fa-arrow-left"></i> Volver al pedido
            </button>
        </div>
    </div>
</div>

<!-- Modal: Confirmación de envío -->
<div id="confirmOrderModal" class="confirm-modal">
    <div class="confirm-modal-content">
        <div class="confirm-modal-icon">
            <i class="fas fa-question-circle"></i>
        </div>
        <h2 class="confirm-modal-title">¿Confirmar pedido?</h2>
        <p class="confirm-modal-subtitle">Va a realizar un pedido con:</p>

        <div class="confirm-modal-details">
            <div class="confirm-detail-item">
                <i class="fas fa-box"></i>
                <span id="confirmArticulos">0</span> artículos
            </div>
            <div class="confirm-detail-item">
                <i class="fas fa-cubes"></i>
                <span id="confirmUnidades">0</span> unidades totales
            </div>
            <?php if (!empty($mostrarPrecio)): ?>
            <div class="confirm-detail-item">
                <i class="fas fa-money-bill-wave"></i>
                $<span id="confirmTotal">0</span>
            </div>
            <?php endif; ?>
        </div>

        <p class="confirm-modal-note">
            <i class="fas fa-info-circle"></i>
            El pedido será procesado y confirmado por el sistema
        </p>

        <div class="confirm-modal-actions">
            <button type="button" class="btn-confirm-send" id="btnConfirmarEnvioPedido" onclick="confirmarEnvio()">
                <i class="fas fa-paper-plane"></i> Sí, enviar pedido
            </button>
            <button type="button" class="btn-confirm-cancel" onclick="cerrarConfirmModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Modal: Progreso de envío (no closeable) -->
<div id="progressModal" class="progress-modal">
    <div class="progress-modal-content">
        <div class="progress-spinner-wrap">
            <div class="progress-ring"></div>
            <div class="progress-ring-icon">
                <i class="fas fa-paper-plane"></i>
            </div>
        </div>
        <h2 class="progress-modal-title">Procesando pedido</h2>
        <p class="progress-modal-sub">Por favor aguardá un momento</p>
        <div class="progress-steps">
            <span class="progress-step-dot"></span>
            <span class="progress-step-dot"></span>
            <span class="progress-step-dot"></span>
        </div>
    </div>
</div>

<!-- Modal: Pedido registrado con éxito -->
<div id="successModal" class="confirm-modal">
    <div class="confirm-modal-content" style="max-width: 500px;">
        <div class="confirm-modal-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <i class="fas fa-check-circle" style="font-size: 3rem;"></i>
        </div>

        <h2 class="confirm-modal-title" style="color: #10b981;">¡Pedido Registrado!</h2>

        <div class="success-message">
            <div class="success-pedido-info">
                <i class="fas fa-receipt"></i>
                <div>
                    <div class="success-label">Número de pedido</div>
                    <div class="success-number" id="successNroPedido">-</div>
                </div>
            </div>
            <p class="success-text">
                <i class="fas fa-info-circle"></i>
                Tu pedido ha sido procesado exitosamente y será preparado para su envío
            </p>
        </div>

        <div class="confirm-modal-actions">
            <button type="button" class="btn-confirm-send" onclick="irAHistorial()" style="width: 100%;">
                <i class="fas fa-history"></i> Ver Historial de Pedidos
            </button>
        </div>
    </div>
</div>
