
<?php

if (class_exists('Conexion')) return;

class Conexion{

    function __construct(){
        require_once(__DIR__.'/classEnv.php');

        $vars = new DotEnv(DotEnv::resolveEnvPath());
        $this->envVars = $vars->listVars();
        
        $this->host_central = $this->envVars['HOST_CENTRAL'];
        $this->database_central = $this->envVars['DATABASE_CENTRAL'];
        $this->database_uy = $this->envVars['DATABASE_UY'];
        $this->host_locales = $this->envVars['HOST_LOCALES'];
        $this->database_locales = $this->envVars['DATABASE_LOCALES'];
        $this->database_sucUy = $this->envVars['DATABASE_SUC_UY'];
        $this->user = $this->envVars['USER'];
        $this->pass = $this->envVars['PASS'];
        $this->pass_locales = $this->envVars['PASS_LOCALES'];
        $this->character = $this->envVars['CHARACTER'];
        $this->env = $this->envVars['ENV'];
        $this->prefix = ($this->env == 'DEV') ? '[LAKERBIS].locales_lakers.dbo.' : '';
    }

    /** @var array<int, array<string, mixed>>|null */
    private $ultimoErrorSqlsrv = null;

    /** @var array<string, mixed>|null */
    private $ultimoIntentoConexion = null;

    /**
     * Parámetros del último intento de sqlsrv_connect (diagnóstico).
     * @return array<string, mixed>|null
     */
    public function obtenerUltimoIntentoConexion() {
        return $this->ultimoIntentoConexion;
    }

    /**
     * Último error sqlsrv (conexión o consulta) del objeto.
     * @return array<int, array<string, mixed>>|null
     */
    public function obtenerUltimoErrorSqlsrv() {
        return $this->ultimoErrorSqlsrv;
    }

    /**
     * @param array<int, array<string, mixed>>|null $errors
     */
    private function guardarUltimoErrorSqlsrv($errors) {
        $this->ultimoErrorSqlsrv = is_array($errors) ? $errors : null;
    }

    /**
     * Texto legible del último error sqlsrv.
     */
    public function formatearUltimoErrorSqlsrv(): string {
        return self::formatearErroresSqlsrv($this->ultimoErrorSqlsrv);
    }

    /**
     * @param array<int, array<string, mixed>>|null $errors
     */
    public static function formatearErroresSqlsrv($errors): string {
        if (empty($errors) || !is_array($errors)) {
            return '';
        }
        $partes = [];
        foreach ($errors as $e) {
            $partes[] = trim(
                ($e['SQLSTATE'] ?? '') . ' [' . ($e['code'] ?? '') . '] ' . ($e['message'] ?? '')
            );
        }
        return implode(' | ', array_filter($partes));
    }

    private function servidor($nameServer) {
        if($nameServer == 'central'){
            return array($this->host_central, $this->database_central);
        }elseif($nameServer == 'locales'){
            return array($this->host_locales, $this->database_locales);
        }elseif($nameServer == 'uy'){
            return array($this->host_central, $this->database_uy);
        }elseif($nameServer == 'suc_uy'){
            return array($this->host_locales, $this->database_sucUy);
        }else{
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            return array($_SESSION['conexion_dns'], $_SESSION['base_nombre']);
        }

    }

    public function setearDnsBaseName($nroSucursal) {
        // La consulta ahora incluye USUARIO_DNS y CLAVE_DNS
        $sql = "SELECT CONEXION_DNS, BASE_NOMBRE, USUARIO_DNS, CLAVE_DNS
                FROM [LAKERBIS].locales_lakers.dbo.SUCURSALES_LAKERS 
                WHERE NRO_SUC_MADRE IS NULL 
                AND NRO_SUCURSAL = ?";

        $conn = $this->conectar('central');

        if (!$conn) {
            // En un entorno de producción, es mejor registrar el error que detener la ejecución.
            error_log("Error de conexión a la base de datos central en setearDnsBaseName.");
            return false;
        }

        $params = array($nroSucursal);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            error_log("Error en la consulta de setearDnsBaseName: " . print_r(sqlsrv_errors(), true));
            return false;
        }

        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Guardamos los 4 valores en la sesión para ser usados por el método conectar()
            $_SESSION['conexion_dns'] = $row['CONEXION_DNS'];
            $_SESSION['base_nombre'] = $row['BASE_NOMBRE'];
            $_SESSION['usuario_dns'] = $row['USUARIO_DNS']; // Puede ser NULL
            $_SESSION['clave_dns'] = $row['CLAVE_DNS'];     // Puede ser NULL
            return true;
        } else {
            // No se encontró configuración para esta sucursal
            return false;
        }
    }

    /**
     * Obtiene configuración de conexión de varias sucursales en una sola consulta a central.
     * @param int[] $nroSucursales
     * @return array<int, array>
     */
    public function obtenerConfiguracionesSucursales(array $nroSucursales) {
        $nroSucursales = array_values(array_unique(array_filter(array_map('intval', $nroSucursales))));
        if (empty($nroSucursales)) {
            return [];
        }

        $conn = $this->conectar('central');
        if (!$conn) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($nroSucursales), '?'));
        $sql = "SELECT NRO_SUCURSAL, CONEXION_DNS, BASE_NOMBRE, USUARIO_DNS, CLAVE_DNS
                FROM [LAKERBIS].locales_lakers.dbo.SUCURSALES_LAKERS
                WHERE NRO_SUC_MADRE IS NULL
                AND NRO_SUCURSAL IN ($placeholders)";

        $stmt = sqlsrv_query($conn, $sql, $nroSucursales);
        if ($stmt === false) {
            error_log('Error en obtenerConfiguracionesSucursales: ' . print_r(sqlsrv_errors(), true));
            return [];
        }

        $configs = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $configs[(int) $row['NRO_SUCURSAL']] = $row;
        }
        sqlsrv_free_stmt($stmt);

        return $configs;
    }

    public function aplicarConfiguracionSucursal(array $row) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['conexion_dns'] = $row['CONEXION_DNS'];
        $_SESSION['base_nombre'] = $row['BASE_NOMBRE'];
        $_SESSION['usuario_dns'] = $row['USUARIO_DNS'];
        $_SESSION['clave_dns'] = $row['CLAVE_DNS'];
    }

    public function conectar($nameServer = null) {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
           
            $serverDB = $this->servidor($nameServer);

            // --- LÓGICA DE CREDENCIALES DINÁMICAS ---

            $usuario_final = $this->user;
            $clave_final = $this->pass;
            $origen_usuario = '.env USER';
            $origen_clave = '.env PASS';

            // Si es una conexión a una sucursal local (sin nombre de servidor específico)
            if (empty($nameServer)) {
                if (isset($_SESSION['usuario_dns']) && !empty($_SESSION['usuario_dns'])) {
                    $usuario_final = $_SESSION['usuario_dns'];
                    $origen_usuario = 'SUCURSALES_LAKERS.USUARIO_DNS';
                }
                if (isset($_SESSION['clave_dns']) && !empty($_SESSION['clave_dns'])) {
                    $clave_final = $_SESSION['clave_dns'];
                    $origen_clave = 'SUCURSALES_LAKERS.CLAVE_DNS';
                }
            }
            elseif ($nameServer == 'locales' || $nameServer == 'suc_uy') {
                $clave_final = $this->pass_locales;
                $origen_clave = '.env PASS_LOCALES';
            }
            elseif ($nameServer == 'central' || $nameServer == 'uy') {
                $usuario_final = 'sa';
                $clave_final = 'Axoft1988';
                $origen_usuario = 'hardcoded central/uy';
                $origen_clave = 'hardcoded central/uy';
            }
            elseif ($nameServer != 'central' && $nameServer != 'uy'
                    && $nameServer != 'suc_uy' && $nameServer != 'locales') {
                if (isset($_SESSION['usuario_dns']) && !empty($_SESSION['usuario_dns'])) {
                    $usuario_final = $_SESSION['usuario_dns'];
                    $origen_usuario = 'SUCURSALES_LAKERS.USUARIO_DNS';
                }
                if (isset($_SESSION['clave_dns']) && !empty($_SESSION['clave_dns'])) {
                    $clave_final = $_SESSION['clave_dns'];
                    $origen_clave = 'SUCURSALES_LAKERS.CLAVE_DNS';
                }
            }

            $characterSet = (is_string($this->character) && $this->character !== '')
                ? $this->character
                : 'UTF-8';

            $this->ultimoIntentoConexion = [
                'tipo'           => $nameServer === null || $nameServer === '' ? 'sucursal' : (string) $nameServer,
                'servidor'       => $serverDB[0],
                'base_datos'     => $serverDB[1],
                'usuario'        => $usuario_final,
                'clave'          => $clave_final,
                'origen_usuario' => $origen_usuario,
                'origen_clave'   => $origen_clave,
                'login_timeout'  => 10,
                'character_set'  => $characterSet,
            ];
            
            // --- FIN DE LA LÓGICA ---

            $params = array( 
                "Database" => $serverDB[1], 
                "UID" => $usuario_final, 
                "PWD" => $clave_final, 
                "CharacterSet" => $characterSet,
                "LoginTimeout" => 10,
                "ConnectionPooling" => 0,
            );
        
            $cid = sqlsrv_connect($serverDB[0], $params);

            if(!$cid) {
                $this->guardarUltimoErrorSqlsrv(sqlsrv_errors(SQLSRV_ERR_ALL));
                if (is_array($this->ultimoIntentoConexion)) {
                    $this->ultimoIntentoConexion['resultado'] = 'fallo';
                    $this->ultimoIntentoConexion['error_sql'] = $this->formatearUltimoErrorSqlsrv();
                }
                error_log("Error de conexión SQL Server: " . print_r($this->ultimoErrorSqlsrv, true));
                return false;
            }

            if (is_array($this->ultimoIntentoConexion)) {
                $this->ultimoIntentoConexion['resultado'] = 'ok';
            }
            $this->guardarUltimoErrorSqlsrv(null);

            $_SESSION['cid'] = $cid;
            return $cid;
            
        } catch (Exception $e) {
            $this->guardarUltimoErrorSqlsrv([['message' => $e->getMessage()]]);
            error_log("Error de conexión: " . $e->getMessage()); 
            return false;
        }
    }

    private function buscarLocal($nameLocal){

        $prefix = ($this->env == 'DEV') ? '[LAKERBIS].locales_lakers.dbo.' : '';

        if($this->env == 'DEV'){
            $database = $this->database_central;
            $pass = $this->pass;
        } else {
            $database = $this->database_locales;
            $pass = $this->pass_locales;
        }

        $sql = "select * from ".$prefix."sucursales_lakers where cod_client = '$nameLocal'";

        $params = array( 
            "Database" => $this->database_central, 
            "UID" => $this->user, 
            "PWD" => $this->pass, 
            "CharacterSet" => $this->character
        );

        $cid = sqlsrv_connect($this->host_central, $params);

        $stmt = sqlsrv_query($cid, $sql);

        try {

            // $next_result = sqlsrv_next_result($stmt);

            while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }
    
            return $v[0];

        } catch (\Throwable $th) {

            print_r($th);

        }
    }
    
}
