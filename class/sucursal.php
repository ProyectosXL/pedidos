<?php

class Sucursal {

    function __construct(){
        require_once __DIR__.'/conexion.php';
        $this->conn = new Conexion;
    }

    /**
     * Obtiene la lista de sucursales de Córdoba disponibles
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array Array asociativo con las sucursales o array vacío en caso de error
     */
    public function listarSucursalesCordoba($db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            // Verificar conexión
            if ($cid === false) {
                error_log("Error: No se pudo conectar a la base de datos");
                return [];
            }

            $sql = "
            SELECT N_IMPUESTO, A.COD_CLIENT, NOM_COM, B.DSN
            FROM GVA14 A
            INNER JOIN SOF_USUARIOS B
            ON CAST(A.N_IMPUESTO AS INT) = B.NRO_SUCURS
            WHERE A.COD_CLIENT IN
            (
                'FRBAUD', 
                'FRORCE',
                'FRORIG',
                'FRORNC',
                'FRORSJ',
                'FRPASJ',
                'FRPRIN'
            )
            ORDER BY A.N_IMPUESTO
            ";

            // Para debug: Imprimir la consulta SQL
            error_log("SQL Query Sucursales Córdoba: " . $sql);
            
            ini_set('max_execution_time', 300);
            
            // Ejecutar la consulta
            $stmt = sqlsrv_query($cid, $sql);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar la consulta de sucursales:\n";
                foreach ($errors as $error) {
                    $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
                    $errorMessage .= "Code: " . $error['code'] . "\n";
                    $errorMessage .= "Message: " . $error['message'] . "\n";
                }
                error_log($errorMessage);
                return [];
            }
    
            $sucursales = [];
            
            // Obtener los resultados
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $sucursales[] = $row;
            }
    
            // Liberar recursos
            if ($stmt) {
                sqlsrv_free_stmt($stmt);
            }
    
            return $sucursales;
        }
        catch (Exception $e) {
            error_log("Error en listarSucursalesCordoba: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene información de una sucursal específica por número
     * @param int $nroSucursal Número de sucursal
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array|null Array asociativo con la información de la sucursal o null si no se encuentra
     */
    public function obtenerSucursalPorNumero($nroSucursal, $db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            if ($cid === false) {
                return null;
            }

            $sql = "
            SELECT N_IMPUESTO, A.COD_CLIENT, NOM_COM, B.DSN
            FROM GVA14 A
            INNER JOIN SOF_USUARIOS B
            ON CAST(A.N_IMPUESTO AS INT) = B.NRO_SUCURS
            WHERE A.N_IMPUESTO = ?
            AND A.COD_CLIENT IN
            (
                'FRBAUD', 
                'FRORCE',
                'FRORIG',
                'FRORNC',
                'FRORSJ',
                'FRPASJ',
                'FRPRIN'
            )
            ";

            $params = array($nroSucursal);
            $stmt = sqlsrv_query($cid, $sql, $params);
            
            if ($stmt === false) {
                error_log("Error al ejecutar consulta de sucursal: " . print_r(sqlsrv_errors(), true));
                return null;
            }
    
            if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                sqlsrv_free_stmt($stmt);
                return $row;
            }
    
            sqlsrv_free_stmt($stmt);
            return null;
        }
        catch (Exception $e) {
            error_log("Error en obtenerSucursalPorNumero: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene la lista de sucursales de una franquicia específica usando las nuevas tablas
     * @param int $idFranquicia ID de la franquicia
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array Array asociativo con las sucursales o array vacío en caso de error
     */
    public function listarSucursalesPorFranquicia($idFranquicia, $db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            if ($cid === false) {
                error_log("Error: No se pudo conectar a la base de datos");
                return [];
            }

            $sql = "
            SELECT 
                S.NRO_SUCURSAL AS N_IMPUESTO,
                S.COD_CLIENTE AS COD_CLIENT,
                S.NOMBRE_SUCURSAL AS NOM_COM,
                S.DSN
            FROM SJ_SUCURSALES_FRANQUICIA S
            WHERE S.ID_FRANQUICIA = ? 
                AND S.ACTIVO = 1
            ORDER BY S.NRO_SUCURSAL
            ";

            $params = array($idFranquicia);
            $stmt = sqlsrv_query($cid, $sql, $params);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar la consulta de sucursales por franquicia:\n";
                foreach ($errors as $error) {
                    $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
                    $errorMessage .= "Code: " . $error['code'] . "\n";
                    $errorMessage .= "Message: " . $error['message'] . "\n";
                }
                error_log($errorMessage);
                return [];
            }
    
            $sucursales = [];
            
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $sucursales[] = $row;
            }
    
            if ($stmt) {
                sqlsrv_free_stmt($stmt);
            }
    
            return $sucursales;
        }
        catch (Exception $e) {
            error_log("Error en listarSucursalesPorFranquicia: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la lista de sucursales de una franquicia por código de franquicia
     * @param string $codigoFranquicia Código de la franquicia (ej: 'CORDOBA')
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array Array asociativo con las sucursales o array vacío en caso de error
     */
    public function listarSucursalesPorCodigoFranquicia($codigoFranquicia, $db = 'central') {
        try {
            require_once __DIR__.'/Franquicia.php';
            $franquicia = new Franquicia();
            $franquiciaData = $franquicia->obtenerFranquiciaPorCodigo($codigoFranquicia, $db);
            
            if ($franquiciaData === null) {
                error_log("Error: No se encontró la franquicia con código: " . $codigoFranquicia);
                return [];
            }
            
            return $this->listarSucursalesPorFranquicia($franquiciaData['ID_FRANQUICIA'], $db);
        }
        catch (Exception $e) {
            error_log("Error en listarSucursalesPorCodigoFranquicia: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene información de una sucursal por número usando las nuevas tablas
     * Busca en todas las franquicias activas
     * @param int $nroSucursal Número de sucursal
     * @param int|null $idFranquicia ID de franquicia (opcional, para filtrar)
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array|null Array asociativo con la información de la sucursal o null si no se encuentra
     */
    public function obtenerSucursalPorNumeroMultiFranquicia($nroSucursal, $idFranquicia = null, $db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            if ($cid === false) {
                return null;
            }

            if ($idFranquicia !== null) {
                $sql = "
                SELECT 
                    S.NRO_SUCURSAL AS N_IMPUESTO,
                    S.COD_CLIENTE AS COD_CLIENT,
                    S.NOMBRE_SUCURSAL AS NOM_COM,
                    S.DSN
                FROM SJ_SUCURSALES_FRANQUICIA S
                WHERE S.NRO_SUCURSAL = ? 
                    AND S.ID_FRANQUICIA = ?
                    AND S.ACTIVO = 1
                ";
                $params = array($nroSucursal, $idFranquicia);
            } else {
                $sql = "
                SELECT 
                    S.NRO_SUCURSAL AS N_IMPUESTO,
                    S.COD_CLIENTE AS COD_CLIENT,
                    S.NOMBRE_SUCURSAL AS NOM_COM,
                    S.DSN
                FROM SJ_SUCURSALES_FRANQUICIA S
                INNER JOIN SJ_FRANQUICIAS F ON S.ID_FRANQUICIA = F.ID_FRANQUICIA
                WHERE S.NRO_SUCURSAL = ? 
                    AND S.ACTIVO = 1
                    AND F.ACTIVO = 1
                ";
                $params = array($nroSucursal);
            }

            $stmt = sqlsrv_query($cid, $sql, $params);
            
            if ($stmt === false) {
                error_log("Error al ejecutar consulta de sucursal multi-franquicia: " . print_r(sqlsrv_errors(), true));
                return null;
            }
    
            if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                sqlsrv_free_stmt($stmt);
                return $row;
            }
    
            sqlsrv_free_stmt($stmt);
            return null;
        }
        catch (Exception $e) {
            error_log("Error en obtenerSucursalPorNumeroMultiFranquicia: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene la lista de números de sucursal de una franquicia como string separado por comas
     * Útil para usar en funciones SQL que requieren lista de números
     * @param int|null $idFranquicia ID de franquicia (usa sesión si no se proporciona)
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return string String con números de sucursal separados por comas (ej: "813,814,815")
     */
    public function obtenerListaNumerosSucursal($idFranquicia = null, $db = 'central') {
        try {
            if ($idFranquicia === null && isset($_SESSION['ID_FRANQUICIA'])) {
                $idFranquicia = $_SESSION['ID_FRANQUICIA'];
            }

            if ($idFranquicia === null) {
                // Fallback: valores hardcodeados de Córdoba
                return '813,814,815,816,876,940';
            }

            $sucursales = $this->listarSucursalesPorFranquicia($idFranquicia, $db);
            
            if (empty($sucursales)) {
                // Fallback: valores hardcodeados de Córdoba
                return '813,814,815,816,876,940';
            }

            $numeros = [];
            foreach ($sucursales as $suc) {
                $numeros[] = (int)$suc['N_IMPUESTO'];
            }
            
            return implode(',', $numeros);
        }
        catch (Exception $e) {
            error_log("Error en obtenerListaNumerosSucursal: " . $e->getMessage());
            // Fallback: valores hardcodeados de Córdoba
            return '813,814,815,816,876,940';
        }
    }

    /**
     * Obtiene la lista de códigos de cliente de una franquicia como array
     * @param int|null $idFranquicia ID de franquicia (usa sesión si no se proporciona)
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array Array con códigos de cliente (ej: ['FRBAUD', 'FRORCE', ...])
     */
    public function obtenerListaCodigosCliente($idFranquicia = null, $db = 'central') {
        try {
            if ($idFranquicia === null && isset($_SESSION['ID_FRANQUICIA'])) {
                $idFranquicia = $_SESSION['ID_FRANQUICIA'];
            }

            if ($idFranquicia === null) {
                // Fallback: valores hardcodeados de Córdoba
                return ['FRBAUD', 'FRORCE', 'FRORIG', 'FRORNC', 'FRORSJ', 'FRPASJ', 'FRPRIN'];
            }

            $sucursales = $this->listarSucursalesPorFranquicia($idFranquicia, $db);
            
            if (empty($sucursales)) {
                // Fallback: valores hardcodeados de Córdoba
                return ['FRBAUD', 'FRORCE', 'FRORIG', 'FRORNC', 'FRORSJ', 'FRPASJ', 'FRPRIN'];
            }

            $codigos = [];
            foreach ($sucursales as $suc) {
                $codigos[] = $suc['COD_CLIENT'];
            }
            
            return array_unique($codigos);
        }
        catch (Exception $e) {
            error_log("Error en obtenerListaCodigosCliente: " . $e->getMessage());
            // Fallback: valores hardcodeados de Córdoba
            return ['FRBAUD', 'FRORCE', 'FRORIG', 'FRORNC', 'FRORSJ', 'FRPASJ', 'FRPRIN'];
        }
    }

    /**
     * Obtiene un mapeo de número de sucursal a código de cliente
     * @param int|null $idFranquicia ID de franquicia (usa sesión si no se proporciona)
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array Array asociativo [numero_sucursal => codigo_cliente]
     */
    public function obtenerMapeoSucursalCodCliente($idFranquicia = null, $db = 'central') {
        try {
            if ($idFranquicia === null && isset($_SESSION['ID_FRANQUICIA'])) {
                $idFranquicia = $_SESSION['ID_FRANQUICIA'];
            }

            if ($idFranquicia === null) {
                // Fallback: valores hardcodeados de Córdoba
                return [
                    '812' => 'FRBAUD',
                    '813' => 'FRORCE',
                    '814' => 'FRORIG',
                    '815' => 'FRORNC',
                    '816' => 'FRORSJ',
                    '876' => 'FRPASJ',
                    '940' => 'FRPRIN'
                ];
            }

            $sucursales = $this->listarSucursalesPorFranquicia($idFranquicia, $db);
            
            if (empty($sucursales)) {
                // Fallback: valores hardcodeados de Córdoba
                return [
                    '812' => 'FRBAUD',
                    '813' => 'FRORCE',
                    '814' => 'FRORIG',
                    '815' => 'FRORNC',
                    '816' => 'FRORSJ',
                    '876' => 'FRPASJ',
                    '940' => 'FRPRIN'
                ];
            }

            $mapeo = [];
            foreach ($sucursales as $suc) {
                $mapeo[(string)$suc['N_IMPUESTO']] = $suc['COD_CLIENT'];
            }
            
            return $mapeo;
        }
        catch (Exception $e) {
            error_log("Error en obtenerMapeoSucursalCodCliente: " . $e->getMessage());
            // Fallback: valores hardcodeados de Córdoba
            return [
                '812' => 'FRBAUD',
                '813' => 'FRORCE',
                '814' => 'FRORIG',
                '815' => 'FRORNC',
                '816' => 'FRORSJ',
                '876' => 'FRPASJ',
                '940' => 'FRPRIN'
            ];
        }
    }
}


