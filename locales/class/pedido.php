<?php

class Pedido {

    function __construct(){

        require_once __DIR__ . '/../../../sistemas/class/conexion.php';
        $this->conn = new Conexion;
        
    }

    public function listarPedido($tipoPedido, $tipo_cli, $suc, $codClient, $esOutlet = null, $db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            // Verificar conexión
            if ($cid === false) {
                return [];
            }
    
            switch ($tipoPedido) {
                case 1:
                    $_SESSION['tipo_pedido'] = 'GENERAL';
                    break;
                case 2:
                    $_SESSION['tipo_pedido'] = 'ACCESORIOS';
                    break;
                case 3:
                    $_SESSION['tipo_pedido'] = 'OUTLET';
                    break;
                case 4:
                    $_SESSION['tipo_pedido'] = 'PACKAGING';
                    break;
            }
    
            // Construir la consulta SQL
            if ($esOutlet != null && $tipoPedido == 1) {
                $sql = "EXEC SJ_TIPO_PEDIDO_{$tipoPedido}_OUTLET '{$suc}', '{$codClient}'";
            } else {
                $sql = "EXEC SJ_TIPO_PEDIDO_{$tipoPedido} '{$suc}', '{$codClient}'";
            }
    
            // Para debug: Imprimir la consulta SQL
            error_log("SQL Query: " . $sql);
            
            ini_set('max_execution_time', 300);
            
            // Ejecutar la consulta
            $stmt = sqlsrv_query($cid, $sql);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar la consulta:\n";
                foreach ($errors as $error) {
                    $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
                    $errorMessage .= "Code: " . $error['code'] . "\n";
                    $errorMessage .= "Message: " . $error['message'] . "\n";
                }
                throw new Exception($errorMessage);
            }
    
            $v = [];
            
            // Si no es Uruguay y hay resultados, obtener el siguiente conjunto
            if ($db != 'uy' && $stmt !== false) {
                $next_result = sqlsrv_next_result($stmt);
                if ($next_result === false) {
                    $errors = sqlsrv_errors();
                    error_log("Error en next_result: " . print_r($errors, true));
                }
            }
    
            // Obtener los resultados
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $v[] = $row;
            }
    
            return $v;
        }
        catch (Exception $e) {
            error_log("Error en listarPedido: " . $e->getMessage());
            return [];
        }
        finally {
            // Liberar recursos
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    public function traerHistorial($codClient, $usuarioUy = 0){



        if($usuarioUy == 1){

            $cid = $this->conn->conectar('uy');
            $codClient = '000000';
            $talonarios = '98';

        }else{

            $cid = $this->conn->conectar('central');
            $talonarios = '1, 96, 97, 120';
        }



        $sql=
        "
        SET DATEFORMAT YMD

        SELECT CAST(FECHA_PEDI AS DATE)FECHA, B.TALON_PED, A.NRO_PEDIDO, LEYENDA_1, B.CANT FROM GVA21 A
        INNER JOIN
        (
            SELECT NRO_PEDIDO, TALON_PED, CAST(SUM(CANT_PEDID) AS FLOAT) CANT FROM GVA03 WHERE TALON_PED IN ($talonarios) GROUP BY NRO_PEDIDO, TALON_PED
        )B
        ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
        WHERE COD_CLIENT = '$codClient' AND FECHA_PEDI > (GETDATE()-42) AND A.TALON_PED IN ($talonarios)
        ORDER BY 1 desc, 2 desc

        ";
            
   
        try {
            $stmt = sqlsrv_query($cid, $sql);
            $v = [];
            while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        }
        catch (\Throwable $th) {
            die("Error en sqlsrv_exec");
        };


        
    }

    public function traerDetallePedido($pedido, $suc, $usuarioUy = 0){

        
       

        if($usuarioUy == 1){

            $cid = $this->conn->conectar('uy');
            $suc = '000000';
            $talonarios = '98';

        }else{

            $cid = $this->conn->conectar('central');
            $talonarios = '1, 96, 97, 120';
        }


        $sql=
        "
        SET DATEFORMAT YMD

        SELECT CAST(A.FECHA_PEDI AS DATE)FECHA, B.COD_ARTICU, C.DESCRIPCIO, CAST(B.CANT_PEDID AS FLOAT) CANT FROM GVA03 B
        INNER JOIN GVA21 A
        ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
        INNER JOIN STA11 C
        ON B.COD_ARTICU = C.COD_ARTICU
        WHERE A.TALON_PED IN ($talonarios) AND A.NRO_PEDIDO = '$pedido'
        AND A.COD_CLIENT = '$suc'
        ";


        try{

            $stmt = sqlsrv_query($cid, $sql);
            
            while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;
        } 
        catch(\Throwable $th){
            die("Error en sqlsrv_exec");
        };
    }

    public function traerHistorialRubro($codClient, $usuarioUy = 0){



        if($usuarioUy == 1){

            $cid = $this->conn->conectar('uy');
            $codClient = '000000';
            $talonarios = '98';

        }else{

            $cid = $this->conn->conectar('central');
            $talonarios = '1, 96, 97, 120';
        }

    
        
        $sql=
        "
        SET DATEFORMAT YMD

        SELECT CAST(FECHA_PEDI AS DATE)FECHA, RUBRO, B.CANT FROM GVA21 A
        INNER JOIN
        (
            SELECT NRO_PEDIDO, TALON_PED, CAST(SUM(CANT_PEDID) AS FLOAT) CANT, RUBRO FROM GVA03 A
			LEFT JOIN SOF_RUBROS_TANGO B ON A.COD_ARTICU = B.COD_ARTICU
			WHERE TALON_PED IN ($talonarios) 
			GROUP BY NRO_PEDIDO, TALON_PED, RUBRO
        )B
        ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
        WHERE COD_CLIENT = '$codClient' AND FECHA_PEDI > (GETDATE()-42) AND A.TALON_PED IN ($talonarios)
        ORDER BY 1 desc, 2 desc

        ";
            
   
        try {
            $stmt = sqlsrv_query($cid, $sql);
            $v = [];
            while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        }
        catch (\Throwable $th) {
            die("Error en sqlsrv_exec");
        };


        
    }

    public function traerRubros($codClient, $usuarioUy = 0) {
        if($usuarioUy == 1){
            $cid = $this->conn->conectar('uy');
            $codClient = '000000';
            $talonarios = '98';
        } else {
            $cid = $this->conn->conectar('central');
            $talonarios = '1, 96, 97, 120';
        }

        $sql = "
            SET DATEFORMAT YMD
            SELECT DISTINCT(RUBRO) RUBRO FROM GVA21 A
            INNER JOIN
            (
                SELECT NRO_PEDIDO, TALON_PED, CAST(SUM(CANT_PEDID) AS FLOAT) CANT, RUBRO FROM GVA03 A
                LEFT JOIN SOF_RUBROS_TANGO B ON A.COD_ARTICU = B.COD_ARTICU
                WHERE TALON_PED IN ($talonarios) 
                GROUP BY NRO_PEDIDO, TALON_PED, RUBRO
            )B
            ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
            WHERE COD_CLIENT = '$codClient' AND FECHA_PEDI > (GETDATE()-42) AND A.TALON_PED IN ($talonarios)
            ORDER BY RUBRO
        ";
        
        try {
            $stmt = sqlsrv_query($cid, $sql);
            $rubros = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (!empty($row['RUBRO'])) {
                    $rubros[] = trim($row['RUBRO']);
                }
            }
            return $rubros;
        }
        catch (\Throwable $th) {
            error_log("Error en traerRubros: " . $th->getMessage());
            return [];
        }
    }

    public function traerVentasRubro($codClient, $suc, $usuarioUy = 0) {
        if($usuarioUy == 1){
            $cid = $this->conn->conectar('uy');
        } else {
            $cid = $this->conn->conectar('central');
        }
        
        $sql = "
            SELECT CAST(FECHA_EMIS AS DATE) FECHA, SUM(CANTIDAD) CANT_VENTAS, RUBRO FROM 
            (
                SELECT A.FECHA_EMIS, 
                       CAST(CASE WHEN B.T_COMP LIKE 'NC%' THEN B.CANTIDAD *-1 ELSE B.CANTIDAD END AS FLOAT) CANTIDAD, 
                       C.RUBRO 
                FROM " . $this->conn->prefix . "CTA02 A          
                INNER JOIN " . $this->conn->prefix . "CTA03 B ON A.FECHA_EMIS = B.FECHA_MOV AND A.N_COMP = B.N_COMP AND A.T_COMP = B.T_COMP   
                INNER JOIN SOF_RUBROS_TANGO C ON B.COD_ARTICU = C.COD_ARTICU
                WHERE A.FECHA_EMIS >= GETDATE()-42 AND A.NRO_SUCURS = '$suc'
            ) A
            GROUP BY FECHA_EMIS, RUBRO
            ORDER BY FECHA_EMIS
        ";
        
        try {
            $stmt = sqlsrv_query($cid, $sql);
            $ventas = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $ventas[] = [
                    'FECHA' => $row['FECHA'],
                    'CANT_VENTAS' => (float)$row['CANT_VENTAS'],
                    'RUBRO' => trim($row['RUBRO'])
                ];
            }
            return $ventas;
        }
        catch (\Throwable $th) {
            error_log("Error en traerVentasRubro: " . $th->getMessage());
            return [];
        }
    }

    public function listarDesabastecimiento($depoMarketPlace) {
        try {
            $cid = $this->conn->conectar('central');
            
            // Verificar conexión
            if ($cid === false) {
                return [];
            }

            $sql = "
                SET DATEFORMAT YMD

                SELECT COD_ARTICU, DESCRIPCIO, RUBRO, CAST(CANT_STOCK - (CASE WHEN CANT_PEDID IS NULL THEN 0 ELSE CANT_PEDID END) AS INT) STOCK_DISP
                FROM
                (
                    SELECT A.COD_ARTICU, B.DESCRIPCIO, D.RUBRO, A.CANT_STOCK, C.CANT_PEDID FROM STA19 A
                    INNER JOIN STA11 B
                    ON A.COD_ARTICU = B.COD_ARTICU
                    LEFT JOIN
                    (
                        SELECT A.COD_ARTICU, SUM(A.CANT_PEDID)CANT_PEDID FROM (
                        SELECT B.COD_ARTICU, SUM(B.CANT_PEDID) CANT_PEDID FROM GVA21 A INNER JOIN GVA03 B ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
                        WHERE A.TALON_PED = 99 AND ESTADO = 2 AND B.COD_ARTICU NOT LIKE '[*]%' AND A.FECHA_PEDI >= GETDATE()-15 GROUP BY B.COD_ARTICU
                        UNION ALL
                        SELECT B.COD_ARTICU, SUM(B.CANT_PEDID) CANT_PEDID FROM GVA21 A INNER JOIN GVA03 B ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
                        WHERE A.TALON_PED = 97 AND A.COD_SUCURS = ? AND ESTADO = 2 AND B.COD_ARTICU NOT LIKE '[*]%' AND A.FECHA_PEDI >= GETDATE()-15 GROUP BY B.COD_ARTICU
                        )A GROUP BY A.COD_ARTICU
                    )C
                    ON B.COD_ARTICU = C.COD_ARTICU
                    INNER JOIN SOF_RUBROS_TANGO D
                    ON A.COD_ARTICU = D.COD_ARTICU
                    WHERE A.COD_DEPOSI = ?
                    AND A.CANT_STOCK > 0

                )A
                WHERE (CANT_STOCK - (CASE WHEN CANT_PEDID IS NULL THEN 0 ELSE CANT_PEDID END)) > 0
                ORDER BY 3, 1
            ";

            $params = array($depoMarketPlace, $depoMarketPlace);
            
            // Ejecutar la consulta
            $stmt = sqlsrv_query($cid, $sql, $params);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar la consulta de desabastecimiento:\n";
                foreach ($errors as $error) {
                    $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
                    $errorMessage .= "Code: " . $error['code'] . "\n";
                    $errorMessage .= "Message: " . $error['message'] . "\n";
                }
                throw new Exception($errorMessage);
            }

            $resultados = [];
            
            // Obtener los resultados
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $resultados[] = $row;
            }

            return $resultados;
        }
        catch (Exception $e) {
            error_log("Error en listarDesabastecimiento: " . $e->getMessage());
            return [];
        }
        finally {
            // Liberar recursos
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Log a archivo dedicado para diagnóstico de conexiones
     */
    private function logConexion($mensaje) {
        $logFile = __DIR__ . '/../logs/conexion_stock_ventas.log';
        $logDir = dirname($logFile);
        
        // Crear directorio si no existe
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $linea = "[$timestamp] $mensaje" . PHP_EOL;
        
        // Escribir al archivo y también a error_log
        @file_put_contents($logFile, $linea, FILE_APPEND);
        error_log($mensaje);
    }

    /**
     * Obtener stock y ventas del local conectando directamente al servidor del local.
     * Usa las variables CONEXION_DNS, BASE_NOMBRE, USUARIO_DNS, CLAVE_DNS de SUCURSALES_LAKERS.
     * 
     * @param string $nombreLocal Código del local (ej: "GTABAS", "FRORNC")
     * @param array $articulos Array de códigos de artículos
     * @param string $tipoCliente 'local' para GT, UR, EX (propios+exterior), 'franquicia' para FR
     * @return array ['stock' => array, 'ventas' => array, 'error' => string|null, 'fuente' => string]
     */
    public function obtenerStockYVentasLocal($nombreLocal, $articulos, $tipoCliente = 'local') {
        $esFranquicia = ($tipoCliente === 'franquicia');
        $resultado = [
            'stock' => [],
            'ventas' => [],
            'error' => null,
            'fuente' => null
        ];
        
        $this->logConexion("=========================================================================================");
        $this->logConexion("=== INICIO obtenerStockYVentasLocal (CONEXION DIRECTA) ===");
        $this->logConexion("Local: $nombreLocal | Tipo: $tipoCliente | Total Artículos: " . count($articulos));
        
        if (empty($nombreLocal) || empty($articulos)) {
            $resultado['error'] = "Datos insuficientes para consultar stock y ventas";
            $this->logConexion("ERROR: Datos insuficientes - Local vacío o sin artículos");
            return $resultado;
        }
        
        set_time_limit(55);
        
        // Preparar lista de códigos normalizados y únicos
        $codigosLimpios = array_filter(array_map(function($art) {
            return trim(strval($art));
        }, $articulos), function($art) {
            return $art !== '';
        });
        $codigosLimpios = array_values(array_unique($codigosLimpios));
        $codigosStr = "'" . implode("','", $codigosLimpios) . "'";
        
        // ============================================================================
        // PASO 1: Conectar al servidor CENTRAL para obtener datos de conexión
        // ============================================================================
        $this->logConexion("-----------------------------------------------------------------------------------------");
        $this->logConexion("PASO 1: Conectando a servidor CENTRAL...");
        
        try {
            $connCentral = $this->conn->conectar('central');
            
            if (!$connCentral) {
                $resultado['error'] = "No se pudo conectar al servidor central";
                $this->logConexion("ERROR PASO 1: No se pudo conectar al servidor central");
                return $resultado;
            }
            
            $this->logConexion("PASO 1: ✓ Conexión exitosa a servidor central");
            
        } catch (Exception $e) {
            $this->logConexion("ERROR PASO 1: Exception - " . $e->getMessage());
            $resultado['error'] = "Error al conectar al servidor central";
            return $resultado;
        }
        
        // ============================================================================
        // PASO 2: Obtener datos de conexión desde SUCURSALES_LAKERS
        // ============================================================================
        $this->logConexion("-----------------------------------------------------------------------------------------");
        $this->logConexion("PASO 2: Obteniendo datos de conexión desde SUCURSALES_LAKERS");
        
        $nroSucursal = null;
        $conexionDns = null;
        $baseNombre = null;
        $usuarioDns = null;
        $claveDns = null;
        
        try {
            // SUCURSALES_LAKERS solo existe en LOCALES_LAKERS (contiene todas las conexiones)
            $tablaSucursales = '[XL-LAKERBIS].LOCALES_LAKERS.DBO.SUCURSALES_LAKERS';
            
            $sqlBuscar = "SELECT NRO_SUCURSAL, CONEXION_DNS, BASE_NOMBRE, USUARIO_DNS, CLAVE_DNS 
                          FROM $tablaSucursales WITH (NOLOCK) 
                          WHERE COD_CLIENT = ? AND NRO_SUC_MADRE IS NULL";
            $paramsBuscar = [$nombreLocal];
            $stmtBuscar = @sqlsrv_query($connCentral, $sqlBuscar, $paramsBuscar, ['QueryTimeout' => 5]);
            
            if ($stmtBuscar) {
                $rowBuscar = sqlsrv_fetch_array($stmtBuscar, SQLSRV_FETCH_ASSOC);
                if ($rowBuscar) {
                    $nroSucursal = $rowBuscar['NRO_SUCURSAL'];
                    $conexionDns = trim($rowBuscar['CONEXION_DNS'] ?? '');
                    $baseNombre = trim($rowBuscar['BASE_NOMBRE'] ?? '');
                    $usuarioDns = trim($rowBuscar['USUARIO_DNS'] ?? '');
                    $claveDns = trim($rowBuscar['CLAVE_DNS'] ?? '');
                    
                    $this->logConexion("PASO 2: ✓ Datos de conexión obtenidos");
                    $this->logConexion("  -> NRO_SUCURSAL: $nroSucursal");
                    $this->logConexion("  -> CONEXION_DNS: $conexionDns");
                    $this->logConexion("  -> BASE_NOMBRE: $baseNombre");
                    $this->logConexion("  -> USUARIO_DNS: $usuarioDns");
                    $this->logConexion("  -> CLAVE_DNS: " . (empty($claveDns) ? 'VACIA' : '***'));
                } else {
                    $this->logConexion("PASO 2: ✗ No se encontró registro para COD_CLIENT='$nombreLocal' en $tablaSucursales");
                }
                sqlsrv_free_stmt($stmtBuscar);
            } else {
                $errores = sqlsrv_errors();
                $this->logConexion("PASO 2: ✗ Error en consulta SQL: " . json_encode($errores));
            }
            
            if (!$nroSucursal || empty($conexionDns) || empty($baseNombre)) {
                $resultado['error'] = "Datos de conexión incompletos";
                $this->logConexion("ERROR PASO 2: Datos de conexión incompletos");
                // Ir directamente al fallback
                goto fallback;
            }
            
        } catch (Exception $e) {
            $this->logConexion("ERROR PASO 2: Exception - " . $e->getMessage());
            goto fallback;
        }
        
        // ============================================================================
        // PASO 3: Conectar al servidor del local
        // ============================================================================
        $this->logConexion("-----------------------------------------------------------------------------------------");
        $this->logConexion("PASO 3: Conectando al servidor del local [$conexionDns]");
        
        $connLocal = null;
        try {
            $connectionInfo = array(
                "Database" => $baseNombre,
                "UID" => $usuarioDns,
                "PWD" => $claveDns,
                "CharacterSet" => "UTF-8",
                "LoginTimeout" => 10,
                "ConnectionPooling" => 0
            );
            
            $connLocal = @sqlsrv_connect($conexionDns, $connectionInfo);
            if (!$connLocal && stripos($conexionDns, 'tcp:') !== 0 && preg_match('/^([^,]+),(\d+)$/', $conexionDns, $matches)) {
                $tcpServer = 'tcp:' . $conexionDns;
                $this->logConexion("PASO 3: Intentando conexión alternativa con servidor local [$tcpServer]");
                $connLocal = @sqlsrv_connect($tcpServer, $connectionInfo);
            }
            
            if (!$connLocal) {
                $errores = sqlsrv_errors();
                $this->logConexion("PASO 3: ✗ No se pudo conectar a [$conexionDns]");
                $this->logConexion("  -> Errores: " . json_encode($errores));
                goto fallback;
            }
            
            $this->logConexion("PASO 3: ✓ Conexión exitosa a servidor local [$conexionDns]");
            
        } catch (Exception $e) {
            $this->logConexion("ERROR PASO 3: Exception - " . $e->getMessage());
            goto fallback;
        }
        
        // ============================================================================
        // PASO 4: Consultar STOCK desde STA19 del servidor local
        // ============================================================================
        $this->logConexion("-----------------------------------------------------------------------------------------");
        $this->logConexion("PASO 4: Consultando STOCK desde [$baseNombre].DBO.STA19");
        
        try {
            $sqlStock = "SELECT COD_ARTICU, SUM(CAST(CANT_STOCK AS FLOAT)) AS STOCK_TOTAL
                         FROM [$baseNombre].DBO.STA19 WITH (NOLOCK)
                         WHERE CANT_STOCK > 0
                           AND COD_ARTICU IN ($codigosStr)
                         GROUP BY COD_ARTICU";
            
            $stmtStock = @sqlsrv_query($connLocal, $sqlStock, [], ['QueryTimeout' => 10]);
            if ($stmtStock) {
                while ($row = sqlsrv_fetch_array($stmtStock, SQLSRV_FETCH_ASSOC)) {
                    $resultado['stock'][trim($row['COD_ARTICU'])] = intval($row['STOCK_TOTAL']);
                }
                sqlsrv_free_stmt($stmtStock);
                $this->logConexion("PASO 4: ✓ Stock obtenido - " . count($resultado['stock']) . " artículos con stock");
            } else {
                $errores = sqlsrv_errors();
                $this->logConexion("PASO 4: ✗ Error en consulta STOCK: " . json_encode($errores));
            }
            
        } catch (Exception $e) {
            $this->logConexion("PASO 4: ✗ Exception: " . $e->getMessage());
        }
        
        // ============================================================================
        // PASO 5: Consultar VENTAS desde GVA53 del servidor local (con fallback a CTA02/CTA03)
        // GVA53 existe en Tango pero no siempre está populado en franquicias;
        // CTA03 es la tabla de detalle de comprobantes de ventas, más universal.
        // ============================================================================
        $this->logConexion("-----------------------------------------------------------------------------------------");
        $this->logConexion("PASO 5: Consultando VENTAS desde [$baseNombre].DBO.GVA53");

        try {
            $sqlVentas = "SELECT COD_ARTICU,
                                 SUM(CASE T_COMP WHEN 'NCR' THEN -CANTIDAD ELSE CANTIDAD END) AS VENDIDO_30D
                          FROM [$baseNombre].DBO.GVA53 WITH (NOLOCK)
                          WHERE FECHA_MOV >= DATEADD(DAY, -30, CAST(GETDATE() AS date))
                            AND COD_ARTICU IN ($codigosStr)
                          GROUP BY COD_ARTICU";

            $stmtVentas = @sqlsrv_query($connLocal, $sqlVentas, [], ['QueryTimeout' => 10]);
            if ($stmtVentas) {
                while ($row = sqlsrv_fetch_array($stmtVentas, SQLSRV_FETCH_ASSOC)) {
                    $resultado['ventas'][trim($row['COD_ARTICU'])] = intval($row['VENDIDO_30D']);
                }
                sqlsrv_free_stmt($stmtVentas);
                $this->logConexion("PASO 5: ✓ GVA53 - " . count($resultado['ventas']) . " artículos con ventas");
            } else {
                $errores = sqlsrv_errors();
                $this->logConexion("PASO 5: ✗ GVA53 falló: " . json_encode($errores));
            }
        } catch (Exception $e) {
            $this->logConexion("PASO 5: ✗ GVA53 Exception: " . $e->getMessage());
        }

        // Si GVA53 no devolvió datos, intentar CTA02/CTA03 como alternativa
        if (empty($resultado['ventas'])) {
            $this->logConexion("PASO 5b: GVA53 sin datos — intentando CTA02/CTA03...");
            try {
                $sqlVentasCta = "SELECT B.COD_ARTICU,
                                        SUM(CASE WHEN A.T_COMP LIKE 'NC%' THEN -B.CANTIDAD ELSE B.CANTIDAD END) AS VENDIDO_30D
                                 FROM [$baseNombre].DBO.CTA02 A WITH (NOLOCK)
                                 INNER JOIN [$baseNombre].DBO.CTA03 B WITH (NOLOCK)
                                     ON A.T_COMP = B.T_COMP AND A.N_COMP = B.N_COMP
                                 WHERE A.FECHA_EMIS >= DATEADD(DAY, -30, CAST(GETDATE() AS date))
                                   AND B.COD_ARTICU IN ($codigosStr)
                                 GROUP BY B.COD_ARTICU";

                $stmtVentasCta = @sqlsrv_query($connLocal, $sqlVentasCta, [], ['QueryTimeout' => 10]);
                if ($stmtVentasCta) {
                    while ($row = sqlsrv_fetch_array($stmtVentasCta, SQLSRV_FETCH_ASSOC)) {
                        $resultado['ventas'][trim($row['COD_ARTICU'])] = intval($row['VENDIDO_30D']);
                    }
                    sqlsrv_free_stmt($stmtVentasCta);
                    $this->logConexion("PASO 5b: ✓ CTA03 - " . count($resultado['ventas']) . " artículos con ventas");
                } else {
                    $errores = sqlsrv_errors();
                    $this->logConexion("PASO 5b: ✗ CTA03 falló: " . json_encode($errores));
                }
            } catch (Exception $e) {
                $this->logConexion("PASO 5b: ✗ CTA03 Exception: " . $e->getMessage());
            }
        }
        
        // Cerrar conexión local
        if ($connLocal) {
            sqlsrv_close($connLocal);
            $this->logConexion("Conexión al servidor local cerrada");
        }
        
        // Verificar si obtuvimos datos
        if (!empty($resultado['stock']) || !empty($resultado['ventas'])) {
            $resultado['fuente'] = 'directa';
            $this->logConexion("✓✓✓ DATOS OBTENIDOS EXITOSAMENTE VÍA CONEXIÓN DIRECTA ✓✓✓");
            
            $this->logConexion("-----------------------------------------------------------------------------------------");
            $this->logConexion("=== FIN obtenerStockYVentasLocal ===");
            $this->logConexion("  -> Fuente: " . ($resultado['fuente'] ?? 'NULL'));
            $this->logConexion("  -> Error: " . ($resultado['error'] ?? 'NINGUNO'));
            $this->logConexion("  -> Stock obtenido: " . count($resultado['stock']) . " artículos");
            $this->logConexion("  -> Ventas obtenidas: " . count($resultado['ventas']) . " artículos");
            $this->logConexion("=========================================================================================");
            $this->logConexion("");
            
            return $resultado;
        }
        
        // ============================================================================
        // FALLBACK: Usar tablas BI centralizadas
        // ============================================================================
        fallback:
        $this->logConexion("-----------------------------------------------------------------------------------------");
        $this->logConexion("FALLBACK: Usando tablas BI centralizadas...");
        
        try {
            // Si no tenemos NRO_SUCURSAL, intentar obtenerlo
            if (!$nroSucursal && $connCentral) {
                // SUCURSALES_LAKERS solo existe en LOCALES_LAKERS
                $tablaSucursales = '[XL-LAKERBIS].LOCALES_LAKERS.DBO.SUCURSALES_LAKERS';
                $sqlNro = "SELECT NRO_SUCURSAL FROM $tablaSucursales WITH (NOLOCK) 
                           WHERE COD_CLIENT = ? AND NRO_SUC_MADRE IS NULL";
                $stmtNro = @sqlsrv_query($connCentral, $sqlNro, [$nombreLocal]);
                if ($stmtNro && ($rowNro = sqlsrv_fetch_array($stmtNro, SQLSRV_FETCH_ASSOC))) {
                    $nroSucursal = $rowNro['NRO_SUCURSAL'];
                    sqlsrv_free_stmt($stmtNro);
                } else {
                    // Intentar fallback por nombre de local si COD_CLIENT no funciona
                    $sqlNro2 = "SELECT NRO_SUCURSAL FROM $tablaSucursales WITH (NOLOCK) 
                                WHERE DESC_SUCURSAL = ?";
                    $stmtNro2 = @sqlsrv_query($connCentral, $sqlNro2, [$nombreLocal]);
                    if ($stmtNro2 && ($rowNro2 = sqlsrv_fetch_array($stmtNro2, SQLSRV_FETCH_ASSOC))) {
                        $nroSucursal = $rowNro2['NRO_SUCURSAL'];
                        sqlsrv_free_stmt($stmtNro2);
                    }
                }
            }
            
            if (!$nroSucursal) {
                $resultado['error'] = "No se pudo determinar NRO_SUCURSAL para fallback";
                $this->logConexion("ERROR FALLBACK: No se pudo determinar NRO_SUCURSAL");
            } else {
                // STOCK: Consultar desde tablas BI según tipo
                if ($esFranquicia) {
                    $sqlStock = "SELECT ARTICULO AS COD,
                                        SUM(CAST(CANT_STOCK AS FLOAT)) AS STOCK_TOTAL
                                 FROM FRANQUICIAS_LAKERS.DBO.BI_STOCK_FRANQUICIAS WITH (NOLOCK)
                                 WHERE NRO_SUCURSAL = ?
                                   AND ARTICULO IN ($codigosStr)
                                   AND CANT_STOCK > 0
                                 GROUP BY ARTICULO";
                } else {
                    $sqlStock = "SELECT COD_ARTICULO AS COD,
                                        SUM(CAST(CANT_STOCK AS FLOAT)) AS STOCK_TOTAL
                                 FROM LOCALES_LAKERS.DBO.BI_STOCK_LOCALES WITH (NOLOCK)
                                 WHERE NRO_SUCURSAL = ?
                                   AND COD_ARTICULO IN ($codigosStr)
                                   AND CANT_STOCK > 0
                                 GROUP BY COD_ARTICULO";
                }
                
                $paramsStock = [$nroSucursal];
                $stmtStock = @sqlsrv_query($connCentral, $sqlStock, $paramsStock, ['QueryTimeout' => 5]);
                if ($stmtStock) {
                    while ($row = sqlsrv_fetch_array($stmtStock, SQLSRV_FETCH_ASSOC)) {
                        $resultado['stock'][trim($row['COD'])] = intval($row['STOCK_TOTAL']);
                    }
                    sqlsrv_free_stmt($stmtStock);
                    $this->logConexion("FALLBACK: ✓ Stock obtenido - " . count($resultado['stock']) . " artículos");
                } else {
                    $errores = sqlsrv_errors();
                    $this->logConexion("FALLBACK: ✗ Stock query failed: " . json_encode($errores));
                }

                if (empty($resultado['stock'])) {
                    $this->logConexion("FALLBACK: No se obtuvieron filas de stock, intentando búsqueda alternativa por COD_CLIENT...");
                    if ($esFranquicia) {
                        $sqlStockAlt = "SELECT ARTICULO AS COD,
                                            SUM(CAST(CANT_STOCK AS FLOAT)) AS STOCK_TOTAL
                                     FROM FRANQUICIAS_LAKERS.DBO.BI_STOCK_FRANQUICIAS WITH (NOLOCK)
                                     WHERE COD_CLIENT = ?
                                       AND ARTICULO IN ($codigosStr)
                                       AND CANT_STOCK > 0
                                     GROUP BY ARTICULO";
                    } else {
                        $sqlStockAlt = "SELECT COD_ARTICULO AS COD,
                                            SUM(CAST(CANT_STOCK AS FLOAT)) AS STOCK_TOTAL
                                     FROM LOCALES_LAKERS.DBO.BI_STOCK_LOCALES WITH (NOLOCK)
                                     WHERE COD_CLIENT = ?
                                       AND COD_ARTICULO IN ($codigosStr)
                                       AND CANT_STOCK > 0
                                     GROUP BY COD_ARTICULO";
                    }
                    $stmtStockAlt = @sqlsrv_query($connCentral, $sqlStockAlt, [$nombreLocal], ['QueryTimeout' => 5]);
                    if ($stmtStockAlt) {
                        while ($row = sqlsrv_fetch_array($stmtStockAlt, SQLSRV_FETCH_ASSOC)) {
                            $resultado['stock'][trim($row['COD'])] = intval($row['STOCK_TOTAL']);
                        }
                        sqlsrv_free_stmt($stmtStockAlt);
                        $this->logConexion("FALLBACK: ✓ Stock alternativo obtenido - " . count($resultado['stock']) . " artículos");
                    } else {
                        $errores = sqlsrv_errors();
                        $this->logConexion("FALLBACK: ✗ Stock alternativa por COD_CLIENT falló: " . json_encode($errores));
                    }
                }

                // VENTAS: Desde CTA02/CTA03
                $sqlVentas = "SELECT B.COD_ARTICU AS COD, 
                                     SUM(CAST(CASE WHEN A.T_COMP LIKE 'NC%' 
                                         THEN B.CANTIDAD * -1 
                                         ELSE B.CANTIDAD END AS FLOAT)) AS TOTAL
                              FROM CTA02 A WITH (NOLOCK)
                              INNER JOIN CTA03 B WITH (NOLOCK)
                                  ON A.NRO_SUCURS = B.NRO_SUCURS
                                  AND A.T_COMP = B.T_COMP 
                                  AND A.N_COMP = B.N_COMP
                              WHERE A.FECHA_EMIS >= DATEADD(DAY, -30, GETDATE())
                                AND A.NRO_SUCURS = ?
                                AND B.COD_ARTICU IN ($codigosStr)
                              GROUP BY B.COD_ARTICU";
                
                $paramsVentas = [$nroSucursal];
                $stmtVentas = @sqlsrv_query($connCentral, $sqlVentas, $paramsVentas, ['QueryTimeout' => 30]);
                if ($stmtVentas) {
                    while ($row = sqlsrv_fetch_array($stmtVentas, SQLSRV_FETCH_ASSOC)) {
                        $resultado['ventas'][trim($row['COD'])] = intval($row['TOTAL']);
                    }
                    sqlsrv_free_stmt($stmtVentas);
                    $this->logConexion("FALLBACK: ✓ Ventas obtenidas - " . count($resultado['ventas']) . " artículos");
                }
                
                $resultado['fuente'] = 'centralizada';
            }
            
        } catch (Exception $e) {
            $this->logConexion("ERROR FALLBACK: Exception - " . $e->getMessage());
            $resultado['error'] = "Error al consultar datos centralizados";
        }
        
        $this->logConexion("-----------------------------------------------------------------------------------------");
        $this->logConexion("=== FIN obtenerStockYVentasLocal ===");
        $this->logConexion("  -> Fuente: " . ($resultado['fuente'] ?? 'NULL'));
        $this->logConexion("  -> Error: " . ($resultado['error'] ?? 'NINGUNO'));
        $this->logConexion("  -> Stock obtenido: " . count($resultado['stock']) . " artículos");
        $this->logConexion("  -> Ventas obtenidas: " . count($resultado['ventas']) . " artículos");
        $this->logConexion("=========================================================================================");
        $this->logConexion("");
        
        return $resultado;
    }



    // ========= CARRITO DE COMPRAS =========

    private function crearTablaCarritoSiNoExiste($cid) {
        $sql = "IF NOT EXISTS (
                    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_NAME = 'SOF_CARRITO_PEDIDO'
                )
                CREATE TABLE SOF_CARRITO_PEDIDO (
                    ID          INT IDENTITY(1,1) PRIMARY KEY,
                    NRO_SUC     VARCHAR(10)    NOT NULL,
                    COD_CLIENT  VARCHAR(20)    NOT NULL,
                    TIPO_PEDIDO VARCHAR(20)    NOT NULL,
                    COD_ARTICU  VARCHAR(30)    NOT NULL,
                    CANTIDAD    INT            NOT NULL DEFAULT 0,
                    RUBRO       VARCHAR(100)   NULL,
                    PRECIO      DECIMAL(18,2)  NOT NULL DEFAULT 0,
                    FECHA_MODIF DATETIME       NOT NULL DEFAULT GETDATE(),
                    CONSTRAINT UQ_CARRITO UNIQUE (NRO_SUC, COD_CLIENT, TIPO_PEDIDO, COD_ARTICU)
                )";
        sqlsrv_query($cid, $sql);
    }

    public function guardarItemCarrito($suc, $codClient, $tipoPedido, $codArt, $cantidad, $rubro, $precio) {
        $cid = $this->conn->conectar('central');
        if (!$cid) return false;
        // Forzar strings para evitar mismatch INT vs VARCHAR en SQLSRV
        $suc        = strval($suc);
        $codClient  = strval($codClient);
        $tipoPedido = strval($tipoPedido);
        $codArt     = strval($codArt);
        $cantidad   = intval($cantidad);
        $rubro      = strval($rubro);
        $precio     = floatval($precio);

        if ($cantidad <= 0) {
            $sql = "DELETE FROM SOF_CARRITO_PEDIDO
                    WHERE NRO_SUC=? AND COD_CLIENT=? AND TIPO_PEDIDO=? AND COD_ARTICU=?";
            $params = [$suc, $codClient, $tipoPedido, $codArt];
            $stmt = sqlsrv_query($cid, $sql, $params);
            return $stmt !== false;
        }

        $sql = "MERGE SOF_CARRITO_PEDIDO AS target
                USING (SELECT ? AS NRO_SUC, ? AS COD_CLIENT, ? AS TIPO_PEDIDO, ? AS COD_ARTICU) AS source
                    ON target.NRO_SUC=source.NRO_SUC AND target.COD_CLIENT=source.COD_CLIENT
                       AND target.TIPO_PEDIDO=source.TIPO_PEDIDO AND target.COD_ARTICU=source.COD_ARTICU
                WHEN MATCHED THEN
                    UPDATE SET CANTIDAD=?, RUBRO=?, PRECIO=?, FECHA_MODIF=GETDATE()
                WHEN NOT MATCHED THEN
                    INSERT (NRO_SUC, COD_CLIENT, TIPO_PEDIDO, COD_ARTICU, CANTIDAD, RUBRO, PRECIO)
                    VALUES (?, ?, ?, ?, ?, ?, ?);";
        $params = [$suc, $codClient, $tipoPedido, $codArt, $cantidad, $rubro, $precio,
                   $suc, $codClient, $tipoPedido, $codArt, $cantidad, $rubro, $precio];
        $stmt = sqlsrv_query($cid, $sql, $params);
        return $stmt !== false;
    }

    public function obtenerCarrito($suc, $codClient, $tipoPedido) {
        $cid = $this->conn->conectar('central');
        if (!$cid) return [];
        $suc        = strval($suc);
        $codClient  = strval($codClient);
        $tipoPedido = strval($tipoPedido);

        $sql = "SELECT c.COD_ARTICU, c.CANTIDAD, c.RUBRO, c.PRECIO,
                       ISNULL(LTRIM(RTRIM(a.DESCRIPCIO)), c.COD_ARTICU) AS DESCRIPCION,
                       ISNULL((SELECT SUM(CANT_STOCK) FROM STA19 WHERE COD_ARTICU = c.COD_ARTICU AND COD_DEPOSI = '01'), 0) AS STOCK
                FROM SOF_CARRITO_PEDIDO c
                LEFT JOIN STA11 a ON a.COD_ARTICU = c.COD_ARTICU
                WHERE c.NRO_SUC = ? AND c.COD_CLIENT = ? AND c.TIPO_PEDIDO = ?
                ORDER BY c.FECHA_MODIF ASC";
        $params = [$suc, $codClient, $tipoPedido];
        $stmt = sqlsrv_query($cid, $sql, $params);
        if (!$stmt) {
            $err = sqlsrv_errors();
            error_log("obtenerCarrito SQL error: " . json_encode($err));
            // Fallback: solo SOF_CARRITO_PEDIDO sin JOINs
            $sqlSimple = "SELECT COD_ARTICU, CANTIDAD, RUBRO, PRECIO,
                                 COD_ARTICU AS DESCRIPCION, 0 AS STOCK
                          FROM SOF_CARRITO_PEDIDO
                          WHERE NRO_SUC = ? AND COD_CLIENT = ? AND TIPO_PEDIDO = ?
                          ORDER BY FECHA_MODIF ASC";
            $stmt = sqlsrv_query($cid, $sqlSimple, $params);
            if (!$stmt) {
                error_log("obtenerCarrito fallback error: " . json_encode(sqlsrv_errors()));
                return [];
            }
        }
        $items = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $items[] = $row;
        }
        sqlsrv_free_stmt($stmt);
        return $items;
    }

    /**
     * Stock disponible en lote para validar envío (franquicias).
     *
     * 1) Si existe SJ_STOCK_DISPONIBLE_VALIDACION_LOTE en central, lo usa (1 ida,
     *    incluye linked server de ubicaciones en set-based).
     * 2) Si no, fallback: STA19 dep 01 − reservado en pedidos pendientes
     *    (misma fórmula que el carrito / obtenerStockDisponible). Evita el
     *    N× EXEC SJ_STOCK_DISPONIBLE_VALIDACION que provoca 504 en nginx.
     *
     * @return array Mapa [COD_ARTICU => stock] + clave especial '_fuente'
     */
    public function obtenerStockDisponibleValidacionLote(
        array $codigos,
        int $suc,
        string $codClient,
        int $tipoSp
    ): array {
        $codigos = array_values(array_unique(array_filter(
            array_map(static function ($c) {
                return trim((string) $c);
            }, $codigos),
            static function ($c) {
                return $c !== '';
            }
        )));
        if (empty($codigos)) {
            return ['_fuente' => 'vacio'];
        }

        $cid = $this->conn->conectar('central');
        if (!$cid) {
            $out = array_fill_keys($codigos, 0);
            $out['_fuente'] = 'sin_conexion';
            return $out;
        }

        // Intentar SP lote (si está desplegado)
        $csv = implode(',', $codigos);
        $spLoteExiste = false;
        $stmtOid = sqlsrv_query(
            $cid,
            "SELECT OBJECT_ID('dbo.SJ_STOCK_DISPONIBLE_VALIDACION_LOTE', 'P') AS OID"
        );
        if ($stmtOid) {
            $rowOid = sqlsrv_fetch_array($stmtOid, SQLSRV_FETCH_ASSOC);
            $spLoteExiste = !empty($rowOid['OID']);
            sqlsrv_free_stmt($stmtOid);
        }

        if ($spLoteExiste && strlen($csv) < 100000) {
            $stmtLote = sqlsrv_query(
                $cid,
                'EXEC SJ_STOCK_DISPONIBLE_VALIDACION_LOTE ?, ?, ?, ?',
                [$csv, $suc, $codClient, $tipoSp]
            );
            if ($stmtLote !== false) {
                $map = array_fill_keys($codigos, 0);
                $filas = 0;
                while ($row = sqlsrv_fetch_array($stmtLote, SQLSRV_FETCH_ASSOC)) {
                    $cod = trim((string) ($row['COD_ARTICU'] ?? ''));
                    if ($cod === '') {
                        continue;
                    }
                    $map[$cod] = floatval($row['CANT_STOCK'] ?? 0);
                    $filas++;
                }
                sqlsrv_free_stmt($stmtLote);
                if ($filas > 0) {
                    $map['_fuente'] = 'sp_lote';
                    return $map;
                }
            }
        }

        $map = $this->obtenerStockDisponible($codigos);
        $map['_fuente'] = 'batch_local';
        return $map;
    }

    /**
     * Stock disponible (físico − reservado en pedidos pendientes) en lote.
     * Fórmula local: STA19 dep '01' menos GVA21/GVA03 (ESTADO=2, TALON 96/97).
     * Usada por carrito y como fallback del envío si no hay SP _LOTE.
     *
     * @param array $codigos Lista de COD_ARTICU
     * @return array Mapa [COD_ARTICU => int stock_disponible]
     */
    public function obtenerStockDisponible(array $codigos): array {
        $codigos = array_values(array_unique(array_filter(array_map('strval', $codigos), fn($c) => $c !== '')));
        if (empty($codigos)) return [];

        $cid = $this->conn->conectar('central');
        if (!$cid) return array_fill_keys($codigos, 0);

        $resultado = array_fill_keys($codigos, 0);
        $fisico = array_fill_keys($codigos, 0);
        $reservado = array_fill_keys($codigos, 0);
        $chunkSize = 400;

        foreach (array_chunk($codigos, $chunkSize) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $sqlFisico = "SELECT RTRIM(COD_ARTICU) AS COD_ARTICU, ISNULL(SUM(CANT_STOCK), 0) AS STOCK_FISICO
                          FROM STA19
                          WHERE COD_DEPOSI = '01' AND COD_ARTICU IN ($placeholders)
                          GROUP BY RTRIM(COD_ARTICU)";
            $stmt = sqlsrv_query($cid, $sqlFisico, $chunk);
            if ($stmt !== false) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $cod = trim($row['COD_ARTICU']);
                    $fisico[$cod] = floatval($row['STOCK_FISICO']);
                }
                sqlsrv_free_stmt($stmt);
            } else {
                error_log("obtenerStockDisponible físico error: " . json_encode(sqlsrv_errors()));
            }

            $sqlReserv = "SELECT RTRIM(B.COD_ARTICU) AS COD_ARTICU, ISNULL(SUM(B.CANT_PEDID), 0) AS STOCK_RESERVADO
                          FROM GVA21 A
                          INNER JOIN GVA03 B ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
                          WHERE A.ESTADO = 2 AND A.TALON_PED IN (96, 97) AND B.COD_ARTICU IN ($placeholders)
                          GROUP BY RTRIM(B.COD_ARTICU)";
            $stmt = sqlsrv_query($cid, $sqlReserv, $chunk);
            if ($stmt !== false) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $cod = trim($row['COD_ARTICU']);
                    $reservado[$cod] = floatval($row['STOCK_RESERVADO']);
                }
                sqlsrv_free_stmt($stmt);
            } else {
                error_log("obtenerStockDisponible reservado error: " . json_encode(sqlsrv_errors()));
            }
        }

        foreach ($codigos as $cod) {
            $resultado[$cod] = max(0, intval($fisico[$cod] - $reservado[$cod]));
        }

        return $resultado;
    }

    public function contarItemsCarrito($suc, $codClient, $tipoPedido) {
        $cid = $this->conn->conectar('central');
        if (!$cid) return 0;
        $suc        = strval($suc);
        $codClient  = strval($codClient);
        $tipoPedido = strval($tipoPedido);

        $sql = "SELECT COUNT(*) FROM SOF_CARRITO_PEDIDO
                WHERE NRO_SUC=? AND COD_CLIENT=? AND TIPO_PEDIDO=?";
        $params = [$suc, $codClient, $tipoPedido];
        $stmt = sqlsrv_query($cid, $sql, $params);
        if (!$stmt) return 0;
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC);
        sqlsrv_free_stmt($stmt);
        return $row ? intval($row[0]) : 0;
    }

    public function limpiarCarrito($suc, $codClient, $tipoPedido) {
        $cid = $this->conn->conectar('central');
        if (!$cid) return false;
        $suc        = strval($suc);
        $codClient  = strval($codClient);
        $tipoPedido = strval($tipoPedido);
        $sql = "DELETE FROM SOF_CARRITO_PEDIDO
                WHERE NRO_SUC=? AND COD_CLIENT=? AND TIPO_PEDIDO=?";
        $params = [$suc, $codClient, $tipoPedido];
        $stmt = sqlsrv_query($cid, $sql, $params);
        return $stmt !== false;
    }
}
