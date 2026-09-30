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
                    <p>No se pudo conectar con las siguientes sucursales del grupo. Las marcadas como <span class="badge bg-secondary">SIN CONEXIÓN</span> aparecen igual en la carga de pedido, sin datos de stock ni ventas del local:</p>
                    <ul class="list-group">
                        <?php foreach ($sucursalesConexionFallidas as $fallo): ?>
                            <li class="list-group-item">
                                <strong><?= htmlspecialchars($fallo['nombre'] ?? '') ?></strong>
                                (Nº <?= htmlspecialchars((string) ($fallo['numero'] ?? '')) ?>)
                                <?php if (!empty($fallo['sinConexion'])): ?>
                                    <span class="badge bg-secondary ms-1">SIN CONEXIÓN · se puede pedir</span>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-1">No disponible para pedir</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted"><?php
                                    $tipoFallo = (string) ($fallo['tipo'] ?? '');
                                    $detalleTecnico = (string) ($fallo['detalle'] ?? '');
                                    echo htmlspecialchars(
                                        $tipoFallo !== ''
                                            ? GrupoSesion::mensajeConexionAmigable($tipoFallo, $detalleTecnico)
                                            : ($fallo['motivo'] ?? 'No se pudo conectar con este local.')
                                    );
                                ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="small text-muted mb-0 mt-3">
                        Si el problema persiste, contacte a soporte técnico.
                    </p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>
