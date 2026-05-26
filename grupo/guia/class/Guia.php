<?php

class Guia
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
     * Trae las guías para una sucursal específica
     * @param string $sucursal Código de la sucursal (ej: FRBAUD, FRORCE, etc.)
     * @param string $desde Fecha desde (formato Y-m-d)
     * @param string $hasta Fecha hasta (formato Y-m-d)
     * @param string $remito Número de remito (opcional, puede ser vacío)
     * @return array Array con los resultados
     */
    public function traerGuiasPorSucursal($sucursal, $desde, $hasta, $remito = '')
    {
        if (!$this->cid_central) {
            return [];
        }

        // Validar y sanitizar inputs
        $sucursalesValidas = ['FRBAUD', 'FRORCE', 'FRORIG', 'FRORNC', 'FRORSJ', 'FRPASJ', 'FRPRIN'];
        if (!in_array($sucursal, $sucursalesValidas)) {
            return [];
        }

        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            return [];
        }

        // Escapar valores para SQL
        $sucursal = str_replace("'", "''", $sucursal);
        $desde = str_replace("'", "''", $desde);
        $hasta = str_replace("'", "''", $hasta);
        $remito = str_replace("'", "''", $remito);

        // Construir condición de remito
        $condicionRemito = $remito ? "AND N_COMP LIKE '%$remito'" : "";

        $sql = "
            SET DATEFORMAT YMD
            SELECT COD_CLIENT CLIENTE, CAST(FECHA_COMP AS DATE) FECHA_COMP, N_COMP, NRO_GUIA, CAST(FECHA_GUIA AS DATE) FECHA_GUIA, OBSERVACIONES
            FROM (  
                SELECT  GVA12.COD_CLIENT,  GVA14.RAZON_SOCI,  GVA12.FECHA_EMIS AS FECHA_COMP,  GVA12.T_COMP,  GVA12.N_COMP,  GC_GDT_GUIA_ENCABEZADO.NUM_GUIA AS NRO_GUIA,  
                GC_GDT_GUIA_ENCABEZADO.FECHA AS FECHA_GUIA, GC_GDT_GUIA_ENCABEZADO.OBSERVACIONES  
                FROM GVA12  
                INNER JOIN GVA14 ON GVA12.COD_CLIENT = GVA14.COD_CLIENT  
                INNER JOIN GC_GDT_GUIA_ENCABEZADO ON GVA12.GC_GDT_NUM_GUIA = GC_GDT_GUIA_ENCABEZADO.NUM_GUIA  
                UNION ALL  
                SELECT  STA14.COD_PRO_CL AS COD_CLIENT,  GVA14.RAZON_SOCI,  STA14.FECHA_MOV AS FECHA_COMP,  STA14.T_COMP,  STA14.N_COMP,  
                GC_GDT_GUIA_ENCABEZADO.NUM_GUIA AS NRO_GUIA,  GC_GDT_GUIA_ENCABEZADO.FECHA AS FECHA_GUIA, GC_GDT_GUIA_ENCABEZADO.OBSERVACIONES  
                FROM STA14  
                INNER JOIN GVA14 ON STA14.COD_PRO_CL = GVA14.COD_CLIENT  
                INNER JOIN GC_GDT_STA14 ON STA14.TCOMP_IN_S = GC_GDT_STA14.TCOMP_IN_S AND STA14.NCOMP_IN_S = GC_GDT_STA14.NCOMP_IN_S  
                INNER JOIN GC_GDT_GUIA_ENCABEZADO ON GC_GDT_STA14.GC_GDT_NUM_GUIA = GC_GDT_GUIA_ENCABEZADO.NUM_GUIA  
            ) A 
            WHERE COD_CLIENT LIKE 'FR%' 
            AND FECHA_GUIA >= '2017-08-01' 
            AND (FECHA_COMP BETWEEN '$desde' AND '$hasta')
            AND COD_CLIENT LIKE '%$sucursal%'
            $condicionRemito
            ORDER BY FECHA_GUIA, COD_CLIENT
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerGuiasPorSucursal: " . print_r($errors, true));
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Convertir fechas de DateTime a string si es necesario
                if (isset($v['FECHA_COMP']) && $v['FECHA_COMP'] instanceof DateTime) {
                    $v['FECHA_COMP'] = $v['FECHA_COMP']->format('Y-m-d');
                }
                if (isset($v['FECHA_GUIA']) && $v['FECHA_GUIA'] instanceof DateTime) {
                    $v['FECHA_GUIA'] = $v['FECHA_GUIA']->format('Y-m-d');
                }
                $rows[] = $v;
            }

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerGuiasPorSucursal: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae las guías para todas las sucursales
     * @param string $desde Fecha desde (formato Y-m-d)
     * @param string $hasta Fecha hasta (formato Y-m-d)
     * @param string $remito Número de remito (opcional, puede ser vacío)
     * @return array Array con los resultados
     */
    public function traerGuiasTodasSucursales($desde, $hasta, $remito = '')
    {
        if (!$this->cid_central) {
            return [];
        }

        // Validar formato de fecha
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            return [];
        }

        // Escapar valores para SQL
        $desde = str_replace("'", "''", $desde);
        $hasta = str_replace("'", "''", $hasta);
        $remito = str_replace("'", "''", $remito);

        // Construir condición de remito
        $condicionRemito = $remito ? "AND N_COMP LIKE '%$remito'" : "";

        $sql = "
            SET DATEFORMAT YMD
            SELECT COD_CLIENT CLIENTE, CAST(FECHA_COMP AS DATE) FECHA_COMP, N_COMP, NRO_GUIA, CAST(FECHA_GUIA AS DATE) FECHA_GUIA, OBSERVACIONES
            FROM (  
                SELECT  GVA12.COD_CLIENT,  GVA14.RAZON_SOCI,  GVA12.FECHA_EMIS AS FECHA_COMP,  GVA12.T_COMP,  GVA12.N_COMP,  GC_GDT_GUIA_ENCABEZADO.NUM_GUIA AS NRO_GUIA,  
                GC_GDT_GUIA_ENCABEZADO.FECHA AS FECHA_GUIA, GC_GDT_GUIA_ENCABEZADO.OBSERVACIONES  
                FROM GVA12  
                INNER JOIN GVA14 ON GVA12.COD_CLIENT = GVA14.COD_CLIENT  
                INNER JOIN GC_GDT_GUIA_ENCABEZADO ON GVA12.GC_GDT_NUM_GUIA = GC_GDT_GUIA_ENCABEZADO.NUM_GUIA  
                UNION ALL  
                SELECT  STA14.COD_PRO_CL AS COD_CLIENT,  GVA14.RAZON_SOCI,  STA14.FECHA_MOV AS FECHA_COMP,  STA14.T_COMP,  STA14.N_COMP,  
                GC_GDT_GUIA_ENCABEZADO.NUM_GUIA AS NRO_GUIA,  GC_GDT_GUIA_ENCABEZADO.FECHA AS FECHA_GUIA, GC_GDT_GUIA_ENCABEZADO.OBSERVACIONES  
                FROM STA14  
                INNER JOIN GVA14 ON STA14.COD_PRO_CL = GVA14.COD_CLIENT  
                INNER JOIN GC_GDT_STA14 ON STA14.TCOMP_IN_S = GC_GDT_STA14.TCOMP_IN_S AND STA14.NCOMP_IN_S = GC_GDT_STA14.NCOMP_IN_S  
                INNER JOIN GC_GDT_GUIA_ENCABEZADO ON GC_GDT_STA14.GC_GDT_NUM_GUIA = GC_GDT_GUIA_ENCABEZADO.NUM_GUIA  
            ) A 
            WHERE COD_CLIENT LIKE 'FR%' 
            AND FECHA_GUIA >= '2017-08-01' 
            AND (FECHA_COMP BETWEEN '$desde' AND '$hasta')
            AND COD_CLIENT IN 
            (
                'FRBAUD', 
                'FRORCE',
                'FRORIG',
                'FRORNC',
                'FRORSJ',
                'FRPASJ',
                'FRPRIN'
            )
            $condicionRemito
            ORDER BY FECHA_GUIA, COD_CLIENT
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerGuiasTodasSucursales: " . print_r($errors, true));
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Convertir fechas de DateTime a string si es necesario
                if (isset($v['FECHA_COMP']) && $v['FECHA_COMP'] instanceof DateTime) {
                    $v['FECHA_COMP'] = $v['FECHA_COMP']->format('Y-m-d');
                }
                if (isset($v['FECHA_GUIA']) && $v['FECHA_GUIA'] instanceof DateTime) {
                    $v['FECHA_GUIA'] = $v['FECHA_GUIA']->format('Y-m-d');
                }
                $rows[] = $v;
            }

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerGuiasTodasSucursales: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae el detalle de un remito específico
     * @param string $remito Número de remito
     * @param string $sucursal Código de la sucursal (ej: FRBAUD, FRORCE, etc.)
     * @return array Array con los resultados del detalle del remito
     */
    public function traerDetalleRemito($remito, $sucursal)
    {
        if (!$this->cid_central) {
            return [];
        }

        // Validar y sanitizar inputs
        $sucursalesValidas = ['FRBAUD', 'FRORCE', 'FRORIG', 'FRORNC', 'FRORSJ', 'FRPASJ', 'FRPRIN'];
        if (!in_array($sucursal, $sucursalesValidas)) {
            return [];
        }

        // Escapar valores para SQL
        $remito = str_replace("'", "''", $remito);
        $sucursal = str_replace("'", "''", $sucursal);

        $sql = "
            SET DATEFORMAT YMD
            SELECT CAST(A.FECHA_MOV AS DATE) FECHA, A.N_COMP REMITO, B.COD_ARTICU ARTICULO, C.DESCRIPCIO DESCRIPCION, CAST(B.CANTIDAD AS FLOAT) CANT 
            FROM STA14 A
            INNER JOIN STA20 B
            ON A.NCOMP_IN_S = B.NCOMP_IN_S AND A.TCOMP_IN_S = B.TCOMP_IN_S
            INNER JOIN STA11 C
            ON C.COD_ARTICU = B.COD_ARTICU
            WHERE A.FECHA_MOV >= '2017-08-01'
            AND A.COD_PRO_CL LIKE '$sucursal'
            AND A.N_COMP = '$remito'
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerDetalleRemito: " . print_r($errors, true));
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
            error_log("Error en traerDetalleRemito: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }
}
?>

