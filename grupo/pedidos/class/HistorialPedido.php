<?php

class HistorialPedido
{
    private $cid;
    public $cid_central;

    function __construct()
    {
        require_once '../../class/conexion.php';
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
     * Trae el historial de pedidos para una sucursal específica
     * @param string $sucursal Código de la sucursal (ej: FRBAUD, FRORCE, etc.)
     * @param string $desde Fecha desde (formato Y-m-d)
     * @param string $hasta Fecha hasta (formato Y-m-d)
     * @return array Array con los resultados
     */
    public function traerHistorialPorSucursal($sucursal, $desde, $hasta)
    {
        if (!$this->cid_central) {
            return [];
        }


        require_once __DIR__.'/../../../class/sucursal.php';
        $sucursalObj = new Sucursal();
        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $sucursalesValidas = $sucursalObj->obtenerListaCodigosCliente($idFranquicia, 'central');
        
        if (!in_array($sucursal, $sucursalesValidas)) {
            return [];
        }

        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            return [];
        }

        // Escapar valores para SQL (aunque ya están validados)
        $sucursal = str_replace("'", "''", $sucursal);
        $desde = str_replace("'", "''", $desde);
        $hasta = str_replace("'", "''", $hasta);

        // Construir lista de códigos para SQL
        $codigosSQL = "'" . implode("', '", array_map(function($cod) { return str_replace("'", "''", $cod); }, $sucursalesValidas)) . "'";

        $sql = "
            SET DATEFORMAT YMD

            SELECT CAST(FECHA_PEDI AS DATE) FECHA, A.COD_CLIENT, A.NRO_PEDIDO, LEYENDA_1, CAST(B.CANT AS INT) CANT,
            CASE WHEN ESTADO = 5 THEN 'ANULADO' ELSE 'APROBADO' END ESTADO 
            FROM GVA21 A
            INNER JOIN
            (
                SELECT NRO_PEDIDO, CAST(SUM(CANT_PEDID) AS FLOAT) CANT 
                FROM GVA03 
                GROUP BY NRO_PEDIDO
            ) B
            ON A.NRO_PEDIDO = B.NRO_PEDIDO
            WHERE COD_CLIENT IN ($codigosSQL)
            AND FECHA_PEDI > (GETDATE()-60) 
            AND (FECHA_PEDI BETWEEN '$desde' AND '$hasta')
            AND A.COD_CLIENT = '$sucursal'
            ORDER BY 1 desc, 2 desc
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerHistorialPorSucursal: " . print_r($errors, true));
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
            error_log("Error en traerHistorialPorSucursal: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae el historial de pedidos para todas las sucursales
     * @param string $desde Fecha desde (formato Y-m-d)
     * @param string $hasta Fecha hasta (formato Y-m-d)
     * @return array Array con los resultados
     */
    public function traerHistorialTodasSucursales($desde, $hasta)
    {
        if (!$this->cid_central) {
            return [];
        }

        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            return [];
        }

        // Escapar valores para SQL (aunque ya están validados)
        $desde = str_replace("'", "''", $desde);
        $hasta = str_replace("'", "''", $hasta);

        require_once __DIR__.'/../../../class/sucursal.php';
        $sucursalObj = new Sucursal();
        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $sucursalesValidas = $sucursalObj->obtenerListaCodigosCliente($idFranquicia, 'central');
        $codigosSQL = "'" . implode("', '", array_map(function($cod) { return str_replace("'", "''", $cod); }, $sucursalesValidas)) . "'";

        $sql = "
            SET DATEFORMAT YMD

            SELECT CAST(FECHA_PEDI AS DATE) FECHA, A.COD_CLIENT, A.NRO_PEDIDO, LEYENDA_1, CAST(B.CANT AS INT) CANT, 
            CASE WHEN ESTADO = 5 THEN 'ANULADO' ELSE 'APROBADO' END ESTADO 
            FROM GVA21 A
            INNER JOIN
            (
                SELECT NRO_PEDIDO, CAST(SUM(CANT_PEDID) AS FLOAT) CANT 
                FROM GVA03 
                GROUP BY NRO_PEDIDO
            ) B
            ON A.NRO_PEDIDO = B.NRO_PEDIDO
            WHERE COD_CLIENT IN ($codigosSQL)
            AND FECHA_PEDI > (GETDATE()-60) 
            AND (FECHA_PEDI BETWEEN '$desde' AND '$hasta')
            ORDER BY 1 desc, 2 desc
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerHistorialTodasSucursales: " . print_r($errors, true));
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
            error_log("Error en traerHistorialTodasSucursales: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae el detalle de un pedido específico
     * @param string $pedido Número de pedido
     * @param string $sucursal Código de la sucursal (ej: FRBAUD, FRORCE, etc.)
     * @return array Array con los resultados del detalle del pedido
     */
    public function traerDetallePedido($pedido, $sucursal)
    {
        if (!$this->cid_central) {
            return [];
        }

        require_once __DIR__.'/../../../class/sucursal.php';
        $sucursalObj = new Sucursal();
        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $sucursalesValidas = $sucursalObj->obtenerListaCodigosCliente($idFranquicia, 'central');
        
        if (!in_array($sucursal, $sucursalesValidas)) {
            return [];
        }

        // Escapar valores para SQL
        $pedido = str_replace("'", "''", $pedido);
        $sucursal = str_replace("'", "''", $sucursal);

        $sql = "
            SET DATEFORMAT YMD

            SELECT CAST(A.FECHA_PEDI AS DATE) FECHA, B.COD_ARTICU, C.DESCRIPCIO, CAST(B.CANT_PEDID AS FLOAT) CANT 
            FROM GVA03 B
            INNER JOIN GVA21 A
            ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
            INNER JOIN STA11 C
            ON B.COD_ARTICU = C.COD_ARTICU
            WHERE A.NRO_PEDIDO = '$pedido'
            AND A.COD_CLIENT = '$sucursal'
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerDetallePedido: " . print_r($errors, true));
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
            error_log("Error en traerDetallePedido: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }
}
?>

