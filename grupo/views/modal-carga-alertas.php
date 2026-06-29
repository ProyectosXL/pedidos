<div class="modal fade" id="modalSucursalesFallidas" tabindex="-1" aria-labelledby="modalSucursalesFallidasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modalSucursalesFallidasLabel">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <?= !empty($cargaPedidoError) ? 'Error de carga' : 'Advertencia de conexión' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($cargaPedidoError)): ?>
                    <p class="mb-3"><?= htmlspecialchars($cargaPedidoError) ?></p>
                <?php endif; ?>
                <?php if (!empty($sucursalesConexionFallidas)): ?>
                    <p>No se pudo conectar con las siguientes sucursales del grupo. El pedido continuará solo con las sucursales disponibles:</p>
                    <ul class="list-group">
                        <?php foreach ($sucursalesConexionFallidas as $fallo): ?>
                            <li class="list-group-item">
                                <strong><?= htmlspecialchars($fallo['nombre'] ?? '') ?></strong>
                                (Nº <?= htmlspecialchars((string) ($fallo['numero'] ?? '')) ?>)
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($fallo['motivo'] ?? '') ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>
