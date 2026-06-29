<?php
require_once __DIR__ . '/../../class/GrupoSesion.php';
GrupoSesion::requiereLogin('../../login.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido vacío</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center p-4">
        <h1 class="h4 mb-3">No ingresó cantidades en ninguna sucursal</h1>
        <p class="text-muted">Será redirigido al inicio en unos segundos.</p>
    </div>
    <script>setTimeout(function () { window.location.href = '../index.php'; }, 2000);</script>
</body>
</html>
