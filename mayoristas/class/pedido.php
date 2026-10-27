<?php

class Pedido {
    private $conn;
    private $cid;
    
    public function __construct() {
    require_once __DIR__ . '/../../../sistemas/class/conexion.php';

// Explicación de la ruta:
// Desde: mayoristas/class/pedido.php
// __DIR__ = mayoristas/class/
// ../     = mayoristas/
// ../../  = pedidos/
// ../../../sistemas/class/conexion.php = sistemas/class/conexion.php
        $this->conn = new Conexion();
        $this->cid = $this->conn->conectar('central');
    }
    
    /**
     * Obtiene el detalle completo de un pedido
     * @param string $numeroPedido
     * @return array Array con fecha, items y totales
     */
    public function obtenerDetallePedido($numeroPedido) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $sql = "
            SET DATEFORMAT YMD
            
            SELECT 
                CAST(A.FECHA_PEDI AS DATE) FECHA, 
                B.COD_ARTICU, 
                C.DESCRIPCIO, 
                CAST(B.CANT_PEDID AS FLOAT) CANT 
            FROM GVA03 B
            INNER JOIN GVA21 A ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
            INNER JOIN STA11 C ON B.COD_ARTICU = C.COD_ARTICU
            WHERE A.NRO_PEDIDO = ?
            ORDER BY B.COD_ARTICU
        ";
        
        ini_set('max_execution_time', 300);
        $stmt = sqlsrv_query($this->cid, $sql, array($numeroPedido));
        
        if($stmt === false){
            throw new Exception("Error al consultar el pedido: " . print_r(sqlsrv_errors(), true));
        }
        
        $items = array();
        $fecha = null;
        $totalCantidad = 0;
        
        while($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $items[] = $v;
            if($fecha === null){
                $fecha = $v['FECHA'];
            }
            $totalCantidad += (int)$v['CANT'];
        }
        
        sqlsrv_free_stmt($stmt);
        
        return array(
            'fecha' => $fecha,
            'items' => $items,
            'totalItems' => count($items),
            'totalCantidad' => $totalCantidad
        );
    }
    
    /**
     * Obtiene el detalle completo de un pedido para un cliente específico
     * @param string $numeroPedido
     * @param string $codigoCliente
     * @return array Array con fecha, items y totales
     */
    public function obtenerDetalleCompletoPedido($numeroPedido, $codigoCliente) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $sql = "
            SET DATEFORMAT YMD

            SELECT 
                CAST(A.FECHA_PEDI AS DATE) AS FECHA, 
                B.COD_ARTICU, 
                C.DESCRIPCIO, 
                CAST(B.CANT_PEDID AS FLOAT) AS CANT 
            FROM GVA03 B
            INNER JOIN GVA21 A ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
            INNER JOIN STA11 C ON B.COD_ARTICU = C.COD_ARTICU
            WHERE A.NRO_PEDIDO = ?
                AND A.COD_CLIENT = ?
                AND B.RENGL_PADR = 0
            ORDER BY B.COD_ARTICU
        ";
        
        ini_set('max_execution_time', 300);
        $stmt = sqlsrv_query($this->cid, $sql, array($numeroPedido, $codigoCliente));
        
        if($stmt === false){
            throw new Exception("Error al consultar pedido: " . print_r(sqlsrv_errors(), true));
        }
        
        $articulos = array();
        $fechaPedido = null;
        $totalCantidad = 0;
        
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $articulos[] = $row;
            if(!$fechaPedido){
                $fechaPedido = $row['FECHA'];
            }
            $totalCantidad += floatval($row['CANT']);
        }
        
        sqlsrv_free_stmt($stmt);
        
        return array(
            'articulos' => $articulos,
            'fechaPedido' => $fechaPedido,
            'totalCantidad' => $totalCantidad
        );
    }
    
    /**
     * Obtiene información básica del cliente
     * @param string $codigoCliente
     * @return array Array con información del cliente
     */
    public function obtenerInfoCliente($codigoCliente) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $sql = "SELECT RAZON_SOCI, COD_CLIENT FROM GVA14 WHERE COD_CLIENT = ?";
        $stmt = sqlsrv_query($this->cid, $sql, array($codigoCliente));
        
        if($stmt === false){
            throw new Exception("Error al consultar cliente: " . print_r(sqlsrv_errors(), true));
        }
        
        $cliente = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        
        return $cliente;
    }
    
    /**
     * Verifica si un pedido existe
     * @param string $numeroPedido
     * @return bool
     */
    public function existePedido($numeroPedido) {
        if($this->cid === false){
            return false;
        }
        
        $sql = "SELECT COUNT(*) as total FROM GVA21 WHERE NRO_PEDIDO = ?";
        $stmt = sqlsrv_query($this->cid, $sql, array($numeroPedido));
        
        if($stmt === false){
            return false;
        }
        
        $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        
        return $result['total'] > 0;
    }
    
    /**
     * Obtiene el historial de pedidos de un cliente
     * @param string $codigoCliente
     * @param int $dias Número de días hacia atrás (default: 90)
     * @return array Array con pedidos, totalPedidos y totalArticulos
     */
    public function obtenerHistorialPedidosCliente($codigoCliente, $dias = 90) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $sql = "
            SET DATEFORMAT YMD
            
            SELECT 
                A.NRO_PEDIDO,
                CAST(A.FECHA_PEDI AS DATE) AS FECHA,
                A.LEYENDA_1 AS OBSERVACIO,
                A.ESTADO,
                COUNT(B.COD_ARTICU) AS TOTAL_ARTICULOS,
                SUM(CAST(B.CANT_PEDID AS FLOAT)) AS TOTAL_CANTIDAD
            FROM GVA21 A
            LEFT JOIN GVA03 B ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
            WHERE A.COD_CLIENT = ?
                AND A.FECHA_PEDI >= DATEADD(DAY, ?, GETDATE())
                AND A.ESTADO IN ('2', '3')
            GROUP BY A.NRO_PEDIDO, A.FECHA_PEDI, A.LEYENDA_1, A.ESTADO
            ORDER BY A.FECHA_PEDI DESC, A.NRO_PEDIDO DESC
        ";
        
        ini_set('max_execution_time', 300);
        $stmt = sqlsrv_query($this->cid, $sql, array($codigoCliente, -$dias));
        
        if($stmt === false){
            throw new Exception("Error al consultar pedidos: " . print_r(sqlsrv_errors(), true));
        }
        
        $pedidos = array();
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $pedidos[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);
        
        // Calcular estadísticas totales
        $totalPedidos = count($pedidos);
        $totalArticulos = array_sum(array_column($pedidos, 'TOTAL_CANTIDAD'));
        
        return array(
            'pedidos' => $pedidos,
            'totalPedidos' => $totalPedidos,
            'totalArticulos' => $totalArticulos
        );
    }
    
    /**
     * Cierra la conexión
     */
    public function cerrarConexion() {
        if($this->cid !== false){
            sqlsrv_close($this->cid);
        }
    }
    
    public function __destruct() {
        $this->cerrarConexion();
    }
}

?>
