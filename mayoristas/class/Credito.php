<?php

class Credito {
    private $conn;
    private $cid;
    
    public function __construct() {
        require_once __DIR__ . '/../../../sistemas/class/conexion.php';
        $this->conn = new Conexion();
        $this->cid = $this->conn->conectar('central');
    }
    
    /**
     * Obtiene el listado de clientes con su información de crédito PPP
     * @param string $codVendedor Código del vendedor
     * @return array Array con los datos de crédito de los clientes
     */
    public function obtenerCreditoClientes($codVendedor) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $sql = "
            SET DATEFORMAT YMD

            SELECT COD_CLIENTE, GRUPO_EMPR, PLAZO, RAZON_SOCIAL, PPP, CUPO_CRED, SALDO_CC, CHEQUE, CHEQUES_10_DIAS, TOTAL_DEUDA, TOTAL_DISPONIBLE, 
            CASE WHEN VENCIDAS < 0 THEN A_VENCER + VENCIDAS ELSE (ISNULL(A_VENCER, 0)) END A_VENCER,
            ISNULL((CASE WHEN (ISNULL(VENCIDAS, 0)) < 0 THEN 0 ELSE VENCIDAS END), 0) VENCIDAS,
            PEDIDOS_ABIERTOS
            FROM
            (
                SELECT COD_CLIENTE, GRUPO_EMPR, CASE WHEN FECHA IS NULL THEN 'OK' ELSE 'NO' END PLAZO, RAZON_SOCIAL, PPP, CUPO_CRED, SALDO_CC, CHEQUE, CHEQUES_10_DIAS, TOTAL_DEUDA, 
                (CUPO_CRED - TOTAL_DEUDA) TOTAL_DISPONIBLE, A_VENCER, VENCIDAS, PEDIDOS_ABIERTOS
                FROM
                (
                    SELECT COD_CLIENTE, D.FECHA, RAZON_SOCIAL, GRUPO_EMPR, CAST(PPP AS INT)PPP, CAST(CUPO_CREDI AS int) CUPO_CRED, 
                    CAST(SALDO_CC AS INT)SALDO_CC, 
                    CAST(CASE WHEN B.CHEQUES IS NULL THEN 0 ELSE B.CHEQUES END AS INT)CHEQUE, 
                    CAST(CASE WHEN C.CHEQUES_PRONTO IS NULL THEN 0 ELSE C.CHEQUES_PRONTO END AS INT)CHEQUES_10_DIAS, 
                    CAST((SALDO_CC+(CASE WHEN B.CHEQUES IS NULL THEN 0 ELSE B.CHEQUES END)) AS INT) TOTAL_DEUDA, 
                    SUM(A_VENCER)A_VENCER, SUM(A.VENCIDAS)VENCIDAS,
                    ISNULL(E.PEDIDOS_ABIERTOS, 0) PEDIDOS_ABIERTOS
                    FROM
                    (
                        SELECT COD_CLIENTE, RAZON_SOCIAL, B.GRUPO_EMPR, C.NOMBRE_GRU , 
                        CAST(AVG(PPP) AS decimal(10,2)) PPP, CAST(AVG(DIAS) AS INT) DIAS, B.CUPO_CREDI, B.SALDO_CC, D.A_VENCER, D.VENCIDAS
                        FROM GC_VIEW_PPP A
                        INNER JOIN GVA14 B ON A.COD_CLIENTE = B.COD_CLIENT
                        LEFT JOIN GVA62 C ON B.GRUPO_EMPR = C.GRUPO_EMPR
                        LEFT JOIN SJ_SALDOS_CC D ON A.COD_CLIENTE = D.COD_CLIENT
                        WHERE B.COD_CLIENT LIKE 'MA%'
                        AND FECHA_RECIBO >= GETDATE()-365
                        AND B.COD_VENDED = ?
                        AND B.COD_CLIENT NOT IN (SELECT COD_CLIENT FROM SJ_PPP_EXCLUYE_CLIENTE WHERE SELEC = 1)
                        GROUP BY COD_CLIENTE, RAZON_SOCIAL, B.GRUPO_EMPR, C.NOMBRE_GRU, B.CUPO_CREDI, B.SALDO_CC, D.A_VENCER, D.VENCIDAS
                    )A
                    LEFT JOIN (SELECT CLIENTE, SUM(IMPORTE_CH)CHEQUES FROM SBA14 WHERE FECHA_CHEQ >= GETDATE() AND ESTADO NOT IN ('X', 'R') GROUP BY CLIENTE) B ON A.COD_CLIENTE = B.CLIENTE
                    LEFT JOIN (SELECT CLIENTE, SUM(IMPORTE_CH)CHEQUES_PRONTO FROM SBA14 WHERE (FECHA_CHEQ >= GETDATE() AND FECHA_CHEQ <= GETDATE()+10) AND ESTADO NOT IN ('X', 'R') GROUP BY CLIENTE) C ON A.COD_CLIENTE = C.CLIENTE
                    LEFT JOIN (SELECT FECHA, COD_CLIENT FROM (SELECT MIN(FECHA_EMIS)FECHA, COD_CLIENT FROM GVA12 A WHERE FECHA_EMIS >= GETDATE()-45 AND COD_CLIENT LIKE 'MA%' AND A.T_COMP = 'FAC' AND ESTADO = 'PEN' GROUP BY COD_CLIENT )A WHERE FECHA < GETDATE()-30)D ON A.COD_CLIENTE = D.COD_CLIENT
                    LEFT JOIN (SELECT COD_CLIENT, COUNT(*) PEDIDOS_ABIERTOS FROM GVA21 WHERE ESTADO IN ('2','3') AND COD_CLIENT LIKE 'MA%' GROUP BY COD_CLIENT) E ON A.COD_CLIENTE = E.COD_CLIENT
                    GROUP BY COD_CLIENTE, D.FECHA, RAZON_SOCIAL, GRUPO_EMPR, PPP, CUPO_CREDI, SALDO_CC, 
                    (CASE WHEN B.CHEQUES IS NULL THEN 0 ELSE B.CHEQUES END), 
                    (CASE WHEN C.CHEQUES_PRONTO IS NULL THEN 0 ELSE C.CHEQUES_PRONTO END), 
                    ((SALDO_CC+(CASE WHEN B.CHEQUES IS NULL THEN 0 ELSE B.CHEQUES END))),
                    E.PEDIDOS_ABIERTOS
                )A
            )A
            ORDER BY 1
        ";
        
        ini_set('max_execution_time', 300);
        $stmt = sqlsrv_query($this->cid, $sql, array($codVendedor));
        
        if($stmt === false){
            throw new Exception("Error al consultar crédito de clientes: " . print_r(sqlsrv_errors(), true));
        }
        
        $clientes = array();
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $clientes[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);
        
        return $clientes;
    }
    
    /**
     * Obtiene el detalle de pagos PPP de un cliente
     * @param string $codCliente Código del cliente
     * @return array Array con el historial de recibos del cliente
     */
    public function obtenerDetallePPPCliente($codCliente) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $sql = "
            SET DATEFORMAT YMD

            SELECT CAST(FECHA_RECIBO AS date)FECHA, T_COMP, N_COMP, 
            CAST(CAST(IMPORTE_RECIBO AS float) AS INT) IMPORTE_RECIBO, 
            CAST(CAST(IMPORTE_IMPUTADO AS FLOAT) AS INT) IMPORTE_IMPUTADO, 
            CAST(PPP AS INT) PPP, CAST(DIAS AS INT) DIAS 
            FROM GC_VIEW_PPP
            WHERE COD_CLIENTE = ?
            AND FECHA_RECIBO >= GETDATE()-365
            ORDER BY 1 DESC, 3
        ";
        
        $stmt = sqlsrv_query($this->cid, $sql, array($codCliente));
        
        if($stmt === false){
            throw new Exception("Error al consultar detalle PPP: " . print_r(sqlsrv_errors(), true));
        }
        
        $recibos = array();
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $recibos[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);
        
        return $recibos;
    }
    
    /**
     * Obtiene el detalle de imputaciones de un recibo
     * @param string $numeroRecibo Número de recibo
     * @return array Array con las imputaciones del recibo
     */
    public function obtenerDetalleRecibo($numeroRecibo) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        // Agregar espacio al inicio si no lo tiene (formato de TANGO)
        $numeroReciboFormateado = (strpos($numeroRecibo, ' ') === 0) ? $numeroRecibo : ' ' . $numeroRecibo;
        
        $sql = "
            SET DATEFORMAT YMD;

            SELECT  
                CAST(FECHA_VTO AS date)      AS FECHA_VTO, 
                CAST(F_COMP_CAN AS date)     AS F_COMP_CAN, 
                CAST(IMPORTE_VT AS DECIMAL(18,2))  AS IMP_VTO, 
                CAST(IMPORT_CAN AS DECIMAL(18,2))  AS IMP_CAN, 
                T_COMP, 
                N_COMP, 
                T_COMP_CAN, 
                N_COMP_CAN
            FROM GVA07  
            WHERE N_COMP_CAN = ?
            ORDER BY FECHA_VTO, F_COMP_CAN;
        ";
        
        $stmt = sqlsrv_query($this->cid, $sql, array($numeroReciboFormateado));
        
        if($stmt === false){
            throw new Exception("Error al consultar detalle de recibo: " . print_r(sqlsrv_errors(), true));
        }
        
        $imputaciones = array();
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $imputaciones[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);
        
        return $imputaciones;
    }
    
    /**
     * Obtiene los cheques de un cliente
     * @param string $codCliente Código del cliente
     * @return array Array con los cheques del cliente
     */
    public function obtenerChequesCliente($codCliente) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $sql = "
            SELECT CAST (FECHA_CHEQ AS DATE)FECHA_CHEQ, N_COMP_REC, CAST(IMPORTE_CH AS INT)IMPORTE_CH 
            FROM SBA14 
            WHERE FECHA_CHEQ >= GETDATE() AND ESTADO NOT IN ('X', 'R') 
            AND CLIENTE = ?
            ORDER BY 1
        ";
        
        $stmt = sqlsrv_query($this->cid, $sql, array($codCliente));
        
        if($stmt === false){
            throw new Exception("Error al consultar cheques: " . print_r(sqlsrv_errors(), true));
        }
        
        $cheques = array();
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $cheques[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);
        
        return $cheques;
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
