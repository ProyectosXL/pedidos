
<?php

class EcommerceController {

    private $remito;

    function __construct() {
        require_once __DIR__.'/remito.php';
        $this->remito = new Remito();
    }

    /**
     * Obtiene las guías de ecommerce para un cliente
     * @param string $codClient Código del cliente
     * @return array Listado de guías
     */
    public function obtenerGuiasEcommerce($codClient) {
        $cid = $this->remito->conn->conectar('central');
        $resultado = [];
        
        if(!$cid) return $resultado;
        
        $sql = "SELECT FECHA, NUM_GUIA FROM RO_VIEW_RELACION_COMP_ECOMMERCE_GUIAS 
                WHERE CLIENTE = '$codClient' AND FECHA >= GETDATE()-14
                GROUP BY FECHA, NUM_GUIA";
                
        try {
            $stmt = sqlsrv_query($cid, $sql);
            
            if ($stmt === false) {
                return $resultado;
            }
            
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $resultado[] = $row;
            }
            
            return $resultado;
            
        } catch (\Throwable $th) {
            return $resultado;
        }
    }
    
    /**
     * Obtiene las facturas asociadas a una guía
     * @param string $numGuia Número de guía
     * @return array Listado de facturas
     */
    public function obtenerFacturasPorGuia($numGuia) {
        $cid = $this->remito->conn->conectar('central');
        $resultado = [];
        
        if(!$cid) return $resultado;
        
        $sql = "SELECT N_COMP, FECHA, TOTAL, CLIENTE 
                FROM RO_VIEW_RELACION_COMP_ECOMMERCE_GUIAS 
                WHERE NUM_GUIA = '$numGuia'";
                
        try {
            $stmt = sqlsrv_query($cid, $sql);
            
            if ($stmt === false) {
                return $resultado;
            }
            
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $resultado[] = $row;
            }
            
            return $resultado;
            
        } catch (\Throwable $th) {
            return $resultado;
        }
    }
    
    /**
     * Registra una factura como verificada
     * @param string $numComp Número de comprobante
     * @param string $usuario Usuario que verifica
     * @return bool Resultado de la operación
     */
    public function registrarFacturaVerificada($numComp, $usuario) {
        $cid = $this->remito->conn->conectar('central');
        
        if(!$cid) return false;
        
        $sql = "INSERT INTO SJ_ECOMMERCE_VERIFICACION 
                (FECHA_VERIFICACION, N_COMP, USUARIO_VERIFICA) 
                VALUES (GETDATE(), '$numComp', '$usuario')";
                
        try {
            $stmt = sqlsrv_prepare($cid, $sql);
            $result = sqlsrv_execute($stmt);
            
            return $result !== false;
            
        } catch (\Throwable $th) {
            return false;
        }
    }
    
    /**
     * Verifica si una factura ya fue verificada
     * @param string $numComp Número de comprobante
     * @return bool Si fue verificada o no
     */
    public function facturaYaVerificada($numComp) {
        $cid = $this->remito->conn->conectar('central');
        
        if(!$cid) return false;
        
        $sql = "SELECT RECIBIDO FROM SJ_LOCAL_ENTREGA_TABLE 
                WHERE N_COMP = '$numComp'";
                
        try {
            $stmt = sqlsrv_query($cid, $sql);
            
            if ($stmt === false) {
                return false;
            }
            
            if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                return $row['RECIBIDO'] == 1;
            }
            
            return false;
            
        } catch (\Throwable $th) {
            return false;
        }
    }
}