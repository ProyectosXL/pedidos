<?php

class PPP
{
    private $cid;
    public $cid_central;

    function __construct()
    {
        require_once __DIR__.'/../../../class/conexion.php';
        $this->cid = new Conexion();
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $db = 'central';
        if(isset($_SESSION['usuarioUy']) && $_SESSION['usuarioUy'] == 1){
            $db = 'uy';
        }
        $this->cid_central = $this->cid->conectar($db);
    }

    /**
     * Trae la lista de clientes con información de PPP
     * Filtra por sucursales de la franquicia actual si está configurada
     * @return array Array con los resultados
     */
    public function traerListaPPP()
    {
        if (!$this->cid_central) {
            return [];
        }

        // FASE 2: Filtrar por sucursales de la franquicia actual
        // Para mantener compatibilidad con versión anterior, usar COD_VENDED = 'Z4' 
        // que trae todos los clientes (sucursales y mayoristas) asociados a Córdoba
        // Esto asegura que se muestren los mismos clientes que en la versión vieja
        $filtroSucursales = "AND B.COD_VENDED = 'Z4'";

        $sql = "
        SET DATEFORMAT YMD

        SELECT COD_CLIENTE, RAZON_SOCIAL, CAST(PPP AS INT)PPP, CAST(CUPO_CREDI AS int) CUPO_CRED, 
        CAST(SALDO_CC AS INT)SALDO_CC, CAST(CASE WHEN B.CHEQUES IS NULL THEN 0 ELSE B.CHEQUES END AS INT)CHEQUE, 
        CAST((SALDO_CC+(CASE WHEN B.CHEQUES IS NULL THEN 0 ELSE B.CHEQUES END)) AS INT) TOTAL_DEUDA
        FROM
        (
        SELECT COD_CLIENTE, RAZON_SOCIAL, 
        CAST(AVG(PPP) AS decimal(10,2)) PPP, CAST(AVG(DIAS) AS INT) DIAS, B.CUPO_CREDI, B.SALDO_CC
        FROM GC_VIEW_PPP A
        INNER JOIN GVA14 B
        ON A.COD_CLIENTE = B.COD_CLIENT
        WHERE B.FECHA_INHA = '1800-01-01'
        " . $filtroSucursales . "
        AND FECHA_RECIBO >= GETDATE()-365
        GROUP BY COD_CLIENTE, RAZON_SOCIAL, B.CUPO_CREDI, B.SALDO_CC
        )A
        LEFT JOIN
        (SELECT CLIENTE, SUM(IMPORTE_CH)CHEQUES FROM SBA14 WHERE FECHA_CHEQ >= GETDATE() AND ESTADO NOT IN ('X', 'R') GROUP BY CLIENTE) B
        ON A.COD_CLIENTE = B.CLIENTE
        ORDER BY 1
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerListaPPP: " . print_r($errors, true));
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $v;
            }

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerListaPPP: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae el detalle de PPP para un cliente específico
     * @param string $cliente Código del cliente
     * @return array Array con los resultados del detalle
     */
    public function traerDetallePPP($cliente)
    {
        if (!$this->cid_central) {
            return [];
        }

        if (empty($cliente)) {
            return [];
        }


        $cliente = str_replace("'", "''", $cliente);

        $sql = "
        SET DATEFORMAT YMD

        SELECT CAST(FECHA_RECIBO AS date)FECHA, T_COMP, N_COMP, CAST(CAST(IMPORTE_RECIBO AS float) AS INT) IMPORTE_RECIBO, 
        CAST(CAST(IMPORTE_IMPUTADO AS FLOAT) AS INT) IMPORTE_IMPUTADO, 
        CAST(PPP AS INT) PPP, CAST(DIAS AS INT) DIAS 
        FROM GC_VIEW_PPP
        WHERE COD_CLIENTE = '$cliente'
        AND FECHA_RECIBO >= GETDATE()-365
        ORDER BY 1 desc, 3
        ";

        ini_set('max_execution_time', 300);
        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerDetallePPP: " . print_r($errors, true));
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Convertir fecha de DateTime a string si es necesario
                if (isset($v['FECHA']) && $v['FECHA'] instanceof DateTime) {
                    $v['FECHA'] = $v['FECHA']->format('Y-m-d');
                }
                $rows[] = $v;
            }

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerDetallePPP: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }
}
?>
