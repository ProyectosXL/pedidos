<?php

class Franquicia {

    private $conn;

    function __construct(){
        require_once __DIR__.'/conexion.php';
        $this->conn = new Conexion;
    }

    /**
     * Obtiene una franquicia por su ID
     * @param int $idFranquicia ID de la franquicia
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array|null Array asociativo con la información de la franquicia o null si no se encuentra
     */
    public function obtenerFranquiciaPorId($idFranquicia, $db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            if ($cid === false) {
                error_log("Error: No se pudo conectar a la base de datos");
                return null;
            }

            $sql = "
            SELECT ID_FRANQUICIA, NOMBRE, CODIGO, ACTIVO, PERMITE_MAYORISTAS, POWERBI_URL
            FROM SJ_FRANQUICIAS
            WHERE ID_FRANQUICIA = ?
            ";

            $params = array($idFranquicia);
            $stmt = sqlsrv_query($cid, $sql, $params);
            
            if ($stmt === false) {
                error_log("Error al ejecutar consulta de franquicia: " . print_r(sqlsrv_errors(), true));
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
            error_log("Error en obtenerFranquiciaPorId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene una franquicia por su código
     * @param string $codigo Código de la franquicia (ej: 'CORDOBA')
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array|null Array asociativo con la información de la franquicia o null si no se encuentra
     */
    public function obtenerFranquiciaPorCodigo($codigo, $db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            if ($cid === false) {
                error_log("Error: No se pudo conectar a la base de datos");
                return null;
            }

            $sql = "
            SELECT ID_FRANQUICIA, NOMBRE, CODIGO, ACTIVO, PERMITE_MAYORISTAS, POWERBI_URL
            FROM SJ_FRANQUICIAS
            WHERE CODIGO = ? AND ACTIVO = 1
            ";

            $params = array($codigo);
            $stmt = sqlsrv_query($cid, $sql, $params);
            
            if ($stmt === false) {
                error_log("Error al ejecutar consulta de franquicia: " . print_r(sqlsrv_errors(), true));
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
            error_log("Error en obtenerFranquiciaPorCodigo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene todas las franquicias activas
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array Array asociativo con las franquicias activas
     */
    public function listarFranquiciasActivas($db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            if ($cid === false) {
                error_log("Error: No se pudo conectar a la base de datos");
                return [];
            }

            $sql = "
            SELECT ID_FRANQUICIA, NOMBRE, CODIGO, ACTIVO, PERMITE_MAYORISTAS, POWERBI_URL
            FROM SJ_FRANQUICIAS
            WHERE ACTIVO = 1
            ORDER BY NOMBRE
            ";

            $stmt = sqlsrv_query($cid, $sql);
            
            if ($stmt === false) {
                error_log("Error al ejecutar consulta de franquicias: " . print_r(sqlsrv_errors(), true));
                return [];
            }
    
            $franquicias = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $franquicias[] = $row;
            }
    
            sqlsrv_free_stmt($stmt);
            return $franquicias;
        }
        catch (Exception $e) {
            error_log("Error en listarFranquiciasActivas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene todas las franquicias (activas e inactivas) - Para panel de administración
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array Array asociativo con todas las franquicias
     */
    public function listarTodasFranquicias($db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            if ($cid === false) {
                error_log("Error: No se pudo conectar a la base de datos");
                return [];
            }

            $sql = "
            SELECT ID_FRANQUICIA, NOMBRE, CODIGO, ACTIVO, PERMITE_MAYORISTAS, POWERBI_URL
            FROM SJ_FRANQUICIAS
            ORDER BY ACTIVO DESC, NOMBRE
            ";

            $stmt = sqlsrv_query($cid, $sql);
            
            if ($stmt === false) {
                error_log("Error al ejecutar consulta de franquicias: " . print_r(sqlsrv_errors(), true));
                return [];
            }
    
            $franquicias = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $franquicias[] = $row;
            }
    
            sqlsrv_free_stmt($stmt);
            return $franquicias;
        }
        catch (Exception $e) {
            error_log("Error en listarTodasFranquicias: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la franquicia asociada a un código de cliente
     * Útil para determinar la franquicia basándose en el código de cliente del ERP
     * @param string $codCliente Código de cliente del ERP (ej: 'FRBAUD', 'FRORCE')
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return array|null Array asociativo con la información de la franquicia o null si no se encuentra
     */
    public function obtenerFranquiciaPorCodCliente($codCliente, $db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            if ($cid === false) {
                error_log("Error: No se pudo conectar a la base de datos");
                return null;
            }

            $sql = "
            SELECT DISTINCT 
                F.ID_FRANQUICIA, 
                F.NOMBRE, 
                F.CODIGO, 
                F.ACTIVO, 
                F.PERMITE_MAYORISTAS, 
                F.POWERBI_URL
            FROM SJ_FRANQUICIAS F
            INNER JOIN SJ_SUCURSALES_FRANQUICIA S
                ON F.ID_FRANQUICIA = S.ID_FRANQUICIA
            WHERE S.COD_CLIENTE = ? 
                AND F.ACTIVO = 1 
                AND S.ACTIVO = 1
            ";

            $params = array($codCliente);
            $stmt = sqlsrv_query($cid, $sql, $params);
            
            if ($stmt === false) {
                error_log("Error al ejecutar consulta de franquicia por código cliente: " . print_r(sqlsrv_errors(), true));
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
            error_log("Error en obtenerFranquiciaPorCodCliente: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Carga la configuración de franquicia en la sesión
     * @param int $idFranquicia ID de la franquicia
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return bool True si se cargó correctamente, false en caso contrario
     */
    public function cargarConfiguracionEnSesion($idFranquicia, $db = 'central') {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            $franquicia = $this->obtenerFranquiciaPorId($idFranquicia, $db);
            
            if ($franquicia === null) {
                error_log("Error: No se encontró la franquicia con ID: " . $idFranquicia);
                return false;
            }

            // Cargar datos de franquicia en sesión
            $_SESSION['ID_FRANQUICIA'] = $franquicia['ID_FRANQUICIA'];
            $_SESSION['NOMBRE_FRANQUICIA'] = $franquicia['NOMBRE'];
            $_SESSION['CODIGO_FRANQUICIA'] = $franquicia['CODIGO'];
            $_SESSION['PERMITE_MAYORISTAS'] = $franquicia['PERMITE_MAYORISTAS'] == 1 ? true : false;
            $_SESSION['POWERBI_URL'] = $franquicia['POWERBI_URL'];

            return true;
        }
        catch (Exception $e) {
            error_log("Error en cargarConfiguracionEnSesion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Carga la configuración de franquicia en la sesión por código de cliente
     * Útil cuando se conoce el código de cliente pero no el ID de franquicia
     * @param string $codCliente Código de cliente del ERP
     * @param string $db Base de datos a usar ('central' por defecto)
     * @return bool True si se cargó correctamente, false en caso contrario
     */
    public function cargarConfiguracionPorCodCliente($codCliente, $db = 'central') {
        try {
            $franquicia = $this->obtenerFranquiciaPorCodCliente($codCliente, $db);
            
            if ($franquicia === null) {
                error_log("Error: No se encontró franquicia para el código de cliente: " . $codCliente);
                return false;
            }

            return $this->cargarConfiguracionEnSesion($franquicia['ID_FRANQUICIA'], $db);
        }
        catch (Exception $e) {
            error_log("Error en cargarConfiguracionPorCodCliente: " . $e->getMessage());
            return false;
        }
    }
}
