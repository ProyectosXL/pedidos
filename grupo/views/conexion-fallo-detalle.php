<?php
/** @var array<string, mixed> $conexion */
/** @var string $detalleTecnico */
if (empty($conexion) || !is_array($conexion)) {
    $conexion = [];
}
$detalleTecnico = $detalleTecnico ?? '';
$lakers = $conexion['lakers'] ?? [];
$errorTecnico = trim((string) ($conexion['error_sql'] ?? ''));
if ($errorTecnico === '' && $detalleTecnico !== '') {
    $errorTecnico = $detalleTecnico;
}
if (empty($conexion) && $errorTecnico === '') {
    return;
}
?>
<details class="mt-2">
    <summary class="small text-primary" style="cursor:pointer;">Ver detalle técnico (soporte)</summary>
    <div class="small bg-light border rounded p-2 mt-1" style="font-family: ui-monospace, monospace; font-size: 11px; line-height: 1.5;">
        <?php if (!empty($conexion)): ?>
        <div><strong>Resultado:</strong> <?= htmlspecialchars((string) ($conexion['resultado'] ?? '—')) ?></div>
        <div><strong>Servidor (sqlsrv):</strong> <?= htmlspecialchars((string) ($conexion['servidor'] ?? '')) ?></div>
        <div><strong>Base de datos:</strong> <?= htmlspecialchars((string) ($conexion['base_datos'] ?? '')) ?></div>
        <div><strong>Usuario usado:</strong> <?= htmlspecialchars((string) ($conexion['usuario'] ?? '')) ?>
            <span class="text-muted">(origen: <?= htmlspecialchars((string) ($conexion['origen_usuario'] ?? '')) ?>)</span></div>
        <div><strong>Clave usada:</strong> <?= htmlspecialchars((string) ($conexion['clave'] ?? '')) ?>
            <span class="text-muted">(origen: <?= htmlspecialchars((string) ($conexion['origen_clave'] ?? '')) ?>)</span></div>
        <div><strong>LoginTimeout:</strong> <?= htmlspecialchars((string) ($conexion['login_timeout'] ?? '10')) ?>s</div>
        <?php endif; ?>
        <?php if ($errorTecnico !== ''): ?>
            <div class="text-danger"><strong>Error técnico:</strong> <?= htmlspecialchars($errorTecnico) ?></div>
        <?php endif; ?>
        <?php if (!empty($lakers)): ?>
        <hr class="my-1">
        <div class="text-muted mb-1"><strong>Valores en SUCURSALES_LAKERS:</strong></div>
        <div><strong>CONEXION_DNS:</strong> <?= htmlspecialchars((string) ($lakers['CONEXION_DNS'] ?? 'NULL')) ?></div>
        <div><strong>BASE_NOMBRE:</strong> <?= htmlspecialchars((string) ($lakers['BASE_NOMBRE'] ?? 'NULL')) ?></div>
        <div><strong>USUARIO_DNS:</strong> <?= htmlspecialchars((string) ($lakers['USUARIO_DNS'] ?? 'NULL')) ?></div>
        <div><strong>CLAVE_DNS:</strong> <?= htmlspecialchars((string) ($lakers['CLAVE_DNS'] ?? 'NULL')) ?></div>
        <?php endif; ?>
    </div>
</details>
