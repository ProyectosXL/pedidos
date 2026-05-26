<?php
/**
 * Función para obtener pedidos de Córdoba usando ODBC
 * Separa la lógica de conexión de la presentación
 */
function getPedidosCordoba() {
    $dsn = "1 - CENTRAL";
    $user = "sa";
    $pass = "Axoft1988";
    
    $cid = odbc_connect($dsn, $user, $pass);
    
    if (!$cid) {
        return [];
    }
    
    $sql = "
    SET DATEFORMAT YMD
    
    EXEC SJ_TIPO_PEDIDO_CORDOBA_1bis
    
    ";
    
    $result = odbc_exec($cid, $sql);
    
    if (!$result) {
        odbc_close($cid);
        return [];
    }
    
    $pedidos = [];
    while ($v = odbc_fetch_array($result)) {
        $pedidos[] = $v;
    }
    
    odbc_close($cid);
    
    return $pedidos;
}

