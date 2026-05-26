<?php

class Estadistica
{
    private $cid;
    public $cid_locales;

    function __construct()
    {
        require_once __DIR__ . '/conexion.php';
        $this->cid = new Conexion();
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->cid_locales = $this->cid->conectar('locales');
    }

    /**
     * Helper para obtener lista de números de sucursal dinámicamente
     * @param int|null $idFranquicia ID de franquicia (usa sesión si no se proporciona)
     * @return string String con números separados por comas para usar en SQL IN
     */
    private function obtenerListaNumerosSucursalSQL($idFranquicia = null) {
        require_once __DIR__ . '/sucursal.php';
        $sucursalObj = new Sucursal();
        return $sucursalObj->obtenerListaNumerosSucursal($idFranquicia, 'central');
    }

    /**
     * Helper para construir CASE WHEN dinámico para nombres de sucursales
     * @param int|null $idFranquicia ID de franquicia (usa sesión si no se proporciona)
     * @return string SQL CASE WHEN para mapear números a nombres
     */
    private function construirCaseWhenSucursales($idFranquicia = null) {
        require_once __DIR__ . '/sucursal.php';
        $sucursalObj = new Sucursal();
        
        if ($idFranquicia === null && isset($_SESSION['ID_FRANQUICIA'])) {
            $idFranquicia = $_SESSION['ID_FRANQUICIA'];
        }

        if ($idFranquicia === null) {
            return "
                CASE NRO_SUCURS
                    WHEN 813 THEN 'VELEZ'
                    WHEN 814 THEN 'DINO'
                    WHEN 815 THEN 'NUEVO CENTRO'
                    WHEN 816 THEN 'SAN JUAN'
                    WHEN 876 THEN 'PASEO DEL JOCKEY'
                    WHEN 940 THEN 'RIVERA'
                    ELSE CAST(NRO_SUCURS AS VARCHAR)
                END";
        }

        $sucursales = $sucursalObj->listarSucursalesPorFranquicia($idFranquicia, 'central');
        
        if (empty($sucursales)) {
            return "
                CASE NRO_SUCURS
                    WHEN 813 THEN 'VELEZ'
                    WHEN 814 THEN 'DINO'
                    WHEN 815 THEN 'NUEVO CENTRO'
                    WHEN 816 THEN 'SAN JUAN'
                    WHEN 876 THEN 'PASEO DEL JOCKEY'
                    WHEN 940 THEN 'RIVERA'
                    ELSE CAST(NRO_SUCURS AS VARCHAR)
                END";
        }

        $caseWhen = "CASE NRO_SUCURS\n";
        foreach ($sucursales as $suc) {
            $nro = (int)$suc['N_IMPUESTO'];
            $nombre = str_replace("'", "''", $suc['NOM_COM']);
            $caseWhen .= "                    WHEN $nro THEN '$nombre'\n";
        }
        $caseWhen .= "                    ELSE CAST(NRO_SUCURS AS VARCHAR)\n                END";
        
        return $caseWhen;
    }

    /**
     * Trae la lista de sucursales disponibles
     * FASE 2: Usa configuración dinámica de franquicia si está disponible
     * @param int|null $idFranquicia ID de franquicia (opcional, usa sesión si no se proporciona)
     * @return array Array con las sucursales (NRO_SUCURSAL, DESC_SUCURSAL)
     */
    public function traerSucursales($idFranquicia = null)
    {
        if (!$this->cid_locales) {
            return [];
        }

        if ($idFranquicia === null && isset($_SESSION['ID_FRANQUICIA'])) {
            $idFranquicia = $_SESSION['ID_FRANQUICIA'];
        }

        if ($idFranquicia !== null) {
            try {
                require_once __DIR__ . '/sucursal.php';
                $sucursalObj = new Sucursal();
                $sucursalesLista = $sucursalObj->listarSucursalesPorFranquicia($idFranquicia, 'central');
                
                if (!empty($sucursalesLista)) {
                    $sucursales = [];
                    foreach ($sucursalesLista as $suc) {
                        $sucursales[] = [
                            'NRO_SUCURSAL' => (int)$suc['N_IMPUESTO'],
                            'DESC_SUCURSAL' => $suc['NOM_COM']
                        ];
                    }
                    return $sucursales;
                }
            } catch (\Throwable $th) {
                error_log("Error al obtener sucursales desde franquicia, usando fallback: " . $th->getMessage());
            }
        }

        $sucursales = [
            ['NRO_SUCURSAL' => 813, 'DESC_SUCURSAL' => 'VELEZ'],
            ['NRO_SUCURSAL' => 814, 'DESC_SUCURSAL' => 'DINO'],
            ['NRO_SUCURSAL' => 815, 'DESC_SUCURSAL' => 'NUEVO CENTRO'],
            ['NRO_SUCURSAL' => 816, 'DESC_SUCURSAL' => 'SAN JUAN'],
            ['NRO_SUCURSAL' => 876, 'DESC_SUCURSAL' => 'PASEO DEL JOCKEY'],
            ['NRO_SUCURSAL' => 940, 'DESC_SUCURSAL' => 'RIVERA']
        ];

        try {
            $sql = "
                SELECT CAST(NRO_SUCURSAL AS INT) AS NRO_SUCURSAL, CAST(DESC_SUCURSAL AS VARCHAR(255)) AS DESC_SUCURSAL
                FROM SUCURSAL
                WHERE NRO_SUCURSAL IN (813, 814, 815, 816, 876, 940) 
                ORDER BY 1
            ";

            ini_set('max_execution_time', 300);
            $stmt = sqlsrv_query($this->cid_locales, $sql);

            if ($stmt !== false) {
                $rows = array();
                while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $rows[] = $v;
                }
                if (!empty($rows)) {
                    return $rows;
                }
            }
        } catch (\Throwable $th) {
            error_log("Error al obtener sucursales desde BD, usando valores hardcodeados: " . $th->getMessage());
        }

        return $sucursales;

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_locales, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerSucursales: " . print_r($errors, true));
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $v;
            }

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerSucursales: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae las ventas para una sucursal específica
     * @param string $sucursal Número de sucursal (ej: 813, 814, etc.) o 'TODOS'
     * @param string $desde Fecha desde (formato Y-m-d)
     * @param string $hasta Fecha hasta (formato Y-m-d)
     * @return array Array con los resultados (NUM, SUCURSAL, IMPORTE)
     */
    public function traerVentasPorSucursal($sucursal, $desde, $hasta)
    {

        if (!$this->cid_locales) {
            return [];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            return [];
        }

        $desde = str_replace("'", "''", $desde);
        $hasta = str_replace("'", "''", $hasta);
        $sucursal = str_replace("'", "''", $sucursal);

        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $listaSucursales = $this->obtenerListaNumerosSucursalSQL($idFranquicia);
        $caseWhenSucursales = $this->construirCaseWhenSucursales($idFranquicia);

        $sql = "
            SET DATEFORMAT YMD
            SELECT NRO_SUCURS NUM, 
                   $caseWhenSucursales AS SUCURSAL,
                   CAST(SUM(CASE A.T_COMP WHEN 'NCR' THEN (A.IMPORTE*-1) ELSE A.IMPORTE END) AS DECIMAL(10,2)) IMPORTE  
            FROM franquicias_lakers.dbo.CTA02 A
            WHERE (A.FECHA_EMIS BETWEEN '$desde' AND '$hasta')
            AND NRO_SUCURS LIKE '$sucursal'
            AND NRO_SUCURS IN ($listaSucursales)
            GROUP BY NRO_SUCURS
            ORDER BY 1
        ";


        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_locales, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerVentasPorSucursal: " . print_r($errors, true));
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (isset($v['IMPORTE'])) {
                    $v['IMPORTE'] = (float)$v['IMPORTE'];
                }
                $rows[] = $v;
            }

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerVentasPorSucursal: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae las ventas para todas las sucursales
     * @param string $desde Fecha desde (formato Y-m-d)
     * @param string $hasta Fecha hasta (formato Y-m-d)
     * @return array Array con los resultados (NUM, SUCURSAL, IMPORTE)
     */
    public function traerVentasTodasSucursales($desde, $hasta)
    {
        if (!$this->cid_locales) {
            return [];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            return [];
        }

        $desde = str_replace("'", "''", $desde);
        $hasta = str_replace("'", "''", $hasta);

        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $listaSucursales = $this->obtenerListaNumerosSucursalSQL($idFranquicia);
        $caseWhenSucursales = $this->construirCaseWhenSucursales($idFranquicia);

        $sql = "
            SET DATEFORMAT YMD
            SELECT NRO_SUCURS NUM, 
                   $caseWhenSucursales AS SUCURSAL,
                   CAST(SUM(CASE A.T_COMP WHEN 'NCR' THEN (A.IMPORTE*-1) ELSE A.IMPORTE END) AS DECIMAL(10,2)) IMPORTE  
            FROM franquicias_lakers.dbo.CTA02 A
            WHERE (A.FECHA_EMIS BETWEEN '$desde' AND '$hasta')
            AND NRO_SUCURS LIKE '%'
            AND NRO_SUCURS IN ($listaSucursales)
            GROUP BY NRO_SUCURS
            ORDER BY 1
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_locales, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerVentasTodasSucursales: " . print_r($errors, true));
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (isset($v['IMPORTE'])) {
                    $v['IMPORTE'] = (float)$v['IMPORTE'];
                }
                $rows[] = $v;
            }

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerVentasTodasSucursales: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae las ventas agrupadas por fecha
     * @param string $sucursal Número de sucursal (ej: 813, 814, etc.) o 'TODOS'
     * @param string $desde Fecha desde (formato Y-m-d)
     * @param string $hasta Fecha hasta (formato Y-m-d)
     * @return array Array con los resultados (FECHA, IMPORTE)
     */
    public function traerVentasPorFecha($sucursal, $desde, $hasta)
    {
        if (!$this->cid_locales) {
            return [];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            return [];
        }

        $desde = str_replace("'", "''", $desde);
        $hasta = str_replace("'", "''", $hasta);
        $sucursal = str_replace("'", "''", $sucursal);

        $whereSucursal = "";
        if ($sucursal != 'TODOS' && $sucursal != '%') {
            $whereSucursal = "AND NRO_SUCURS LIKE '$sucursal'";
        }

        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $listaSucursales = $this->obtenerListaNumerosSucursalSQL($idFranquicia);

        $sql = "
            SET DATEFORMAT YMD
            SELECT CAST(FECHA_EMIS AS DATE) AS FECHA,
                   CAST(SUM(CASE A.T_COMP WHEN 'NCR' THEN (A.IMPORTE*-1) ELSE A.IMPORTE END) AS DECIMAL(10,2)) IMPORTE  
            FROM franquicias_lakers.dbo.CTA02 A
            WHERE (A.FECHA_EMIS BETWEEN '$desde' AND '$hasta')
            $whereSucursal
            AND NRO_SUCURS IN ($listaSucursales)
            GROUP BY CAST(FECHA_EMIS AS DATE)
            ORDER BY CAST(FECHA_EMIS AS DATE)
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_locales, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerVentasPorFecha: " . print_r($errors, true));
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (isset($v['FECHA'])) {
                    if ($v['FECHA'] instanceof DateTime) {
                        $v['FECHA'] = $v['FECHA']->format('Y-m-d');
                    } elseif (is_object($v['FECHA']) && method_exists($v['FECHA'], 'format')) {
                        $v['FECHA'] = $v['FECHA']->format('Y-m-d');
                    } else {
                        $v['FECHA'] = (string)$v['FECHA'];
                    }
                }
                if (isset($v['IMPORTE'])) {
                    $v['IMPORTE'] = (float)$v['IMPORTE'];
                }
                $rows[] = $v;
            }

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerVentasPorFecha: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae las ventas agrupadas por fecha y sucursal
     * @param string $sucursal Número de sucursal (ej: 813, 814, etc.) o 'TODOS'
     * @param string $desde Fecha desde (formato Y-m-d)
     * @param string $hasta Fecha hasta (formato Y-m-d)
     * @return array Array con los resultados (FECHA, SUCURSAL, IMPORTE)
     */
    public function traerVentasPorFechaYSucursal($sucursal, $desde, $hasta)
    {
        if (!$this->cid_locales) {
            return [];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            return [];
        }

        $desde = str_replace("'", "''", $desde);
        $hasta = str_replace("'", "''", $hasta);
        $sucursal = str_replace("'", "''", $sucursal);

        $whereSucursal = "";
        if ($sucursal != 'TODOS' && $sucursal != '%') {
            $whereSucursal = "AND NRO_SUCURS LIKE '$sucursal'";
        }

        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $listaSucursales = $this->obtenerListaNumerosSucursalSQL($idFranquicia);
        $caseWhenSucursales = $this->construirCaseWhenSucursales($idFranquicia);

        $sql = "
            SET DATEFORMAT YMD
            SELECT CAST(FECHA_EMIS AS DATE) AS FECHA,
                   $caseWhenSucursales AS SUCURSAL,
                   CAST(SUM(CASE A.T_COMP WHEN 'NCR' THEN (A.IMPORTE*-1) ELSE A.IMPORTE END) AS DECIMAL(10,2)) IMPORTE  
            FROM franquicias_lakers.dbo.CTA02 A
            WHERE (A.FECHA_EMIS BETWEEN '$desde' AND '$hasta')
            $whereSucursal
            AND NRO_SUCURS IN ($listaSucursales)
            GROUP BY CAST(FECHA_EMIS AS DATE), NRO_SUCURS
            ORDER BY CAST(FECHA_EMIS AS DATE), NRO_SUCURS
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_locales, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerVentasPorFechaYSucursal: " . print_r($errors, true));
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (isset($v['FECHA'])) {
                    if ($v['FECHA'] instanceof DateTime) {
                        $v['FECHA'] = $v['FECHA']->format('Y-m-d');
                    } elseif (is_object($v['FECHA']) && method_exists($v['FECHA'], 'format')) {
                        $v['FECHA'] = $v['FECHA']->format('Y-m-d');
                    } else {
                        $v['FECHA'] = (string)$v['FECHA'];
                    }
                }
                if (isset($v['IMPORTE'])) {
                    $v['IMPORTE'] = (float)$v['IMPORTE'];
                }
                $rows[] = $v;
            }

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerVentasPorFechaYSucursal: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae las estadísticas mensuales agrupadas por mes
     * @param int $anio Año para filtrar (ej: 2024)
     * @return array Array con los resultados mensuales
     */
    public function traerEstadisticasMensuales($anio)
    {
        if (!$this->cid_locales) {
            return [];
        }

        $anio = intval($anio);
        if ($anio < 2020 || $anio > date('Y')) {
            $anio = date('Y');
        }

        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $sucursales = $this->obtenerListaNumerosSucursalSQL($idFranquicia);
        
        $sql = "
            SET DATEFORMAT YMD
            
            SELECT MES, 
                CAST(SUM(IMPORTE) AS INT) IMPORTE, 
                SUM(ARTICULOS) ARTICULOS, 
                SUM(COMP) COMP,
                CAST(SUM(IMPORTE)/SUM(COMP) AS DECIMAL(10,2)) PROM_TICKET, 
                SUM(CANT_TICKET_2DO) CANT_TICKET_2DO, 
                CAST(CAST(SUM(CANT_TICKET_2DO) AS FLOAT)/CAST(SUM(COMP) AS FLOAT)*100 AS DECIMAL(10,2)) PROM_2DO,
                SUM(CANT_TICKET_3ER) CANT_TICKET_3ER, 
                CAST(CAST(SUM(CANT_TICKET_3ER) AS FLOAT)/CAST(SUM(COMP) AS FLOAT)*100 AS DECIMAL(10,2)) PROM_3ER,
                SUM(CANT_CAMBIOS) CANT_CAMBIOS, 
                CAST(CAST(SUM(CANT_CAMBIOS) AS FLOAT)/CAST(SUM(COMP) AS FLOAT)*100 AS DECIMAL(10,2)) PORC_CAMBIOS,
                CAST(AVG(PORC_INCREM) AS DECIMAL(10,2)) PORC_INCREM
            FROM franquicias_lakers.dbo.fn_fu_BI_MES($anio, '$sucursales') A
            GROUP BY MES
            ORDER BY MES DESC
        ";


        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_locales, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerEstadisticasMensuales: " . print_r($errors, true));
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (isset($v['MES'])) {
                    if ($v['MES'] instanceof DateTime) {
                        $v['MES'] = $v['MES']->format('Y-m-d');
                    } elseif (is_object($v['MES']) && method_exists($v['MES'], 'format')) {
                        $v['MES'] = $v['MES']->format('Y-m-d');
                    } else {
                        $v['MES'] = (string)$v['MES'];
                    }
                }
                if (isset($v['IMPORTE'])) {
                    $v['IMPORTE'] = (int)$v['IMPORTE'];
                }
                if (isset($v['ARTICULOS'])) {
                    $v['ARTICULOS'] = (float)$v['ARTICULOS'];
                }
                if (isset($v['COMP'])) {
                    $v['COMP'] = (float)$v['COMP'];
                }
                $rows[] = $v;
            }

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerEstadisticasMensuales: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae los totales de estadísticas para un año específico
     * @param int $anio Año para filtrar (ej: 2024)
     * @return array Array con los totales
     */
    public function traerTotalesEstadisticas($anio)
    {
        if (!$this->cid_locales) {
            return null;
        }

        $anio = intval($anio);
        if ($anio < 2020 || $anio > date('Y')) {
            $anio = date('Y');
        }

        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $sucursales = $this->obtenerListaNumerosSucursalSQL($idFranquicia);
        
        $sql = "
            SET DATEFORMAT YMD
            
            SELECT 
                CAST(SUM(IMPORTE) AS DECIMAL(18,2)) AS TOTAL_IMPORTE,
                SUM(COMP) TOTAL_COMP,
                SUM(ARTICULOS) TOTAL_ARTICULOS,
                SUM(CANT_TICKET_2DO) TOTAL_2DO,
                SUM(CANT_TICKET_3ER) TOTAL_3ER,
                SUM(CANT_CAMBIOS) TOTAL_CAMBIOS,
                CAST(AVG(PORC_INCREM) AS DECIMAL(10,2)) AVG_INCREM
            FROM franquicias_lakers.dbo.fn_fu_BI_MES($anio, '$sucursales')
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_locales, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerTotalesEstadisticas: " . print_r($errors, true));
                return null;
            }

            $totales = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            if ($totales) {
                if (isset($totales['TOTAL_IMPORTE'])) {
                    $totales['TOTAL_IMPORTE'] = (int)$totales['TOTAL_IMPORTE'];
                }
                if (isset($totales['TOTAL_COMP'])) {
                    $totales['TOTAL_COMP'] = (float)$totales['TOTAL_COMP'];
                }
                if (isset($totales['TOTAL_ARTICULOS'])) {
                    $totales['TOTAL_ARTICULOS'] = (float)$totales['TOTAL_ARTICULOS'];
                }
            }

            return $totales;
        } catch (\Throwable $th) {
            error_log("Error en traerTotalesEstadisticas: " . $th->getMessage());
            return null;
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }
}

