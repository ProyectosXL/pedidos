
<?php

class Conexion{

    function __construct(){
        require_once(__DIR__.'/classEnv.php');

        $vars = new DotEnv(__DIR__ . '/../.env');
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

    private function servidor($nameServer) {
        if($nameServer == 'central'){
            return array('servidor', 'LAKER_SA');
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

    public function conectar($nameServer = null) {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
           
            $serverDB = $this->servidor($nameServer);

            // --- LÓGICA DE CREDENCIALES DINÁMICAS ---

            $usuario_final = $this->user;
            $clave_final = $this->pass; 

            // Si es una conexión a una sucursal local (sin nombre de servidor específico)
            if (empty($nameServer)) {
                // Usamos las credenciales de la sesión SI existen y NO están vacías.
                // Si son NULL o vacías en la BD, se usarán los valores por defecto del .env.
                if (isset($_SESSION['usuario_dns']) && !empty($_SESSION['usuario_dns'])) {
                    $usuario_final = $_SESSION['usuario_dns'];
                }
                if (isset($_SESSION['clave_dns']) && !empty($_SESSION['clave_dns'])) {
                    $clave_final = $_SESSION['clave_dns'];
                }
            } 
            elseif ($nameServer == 'locales' || $nameServer == 'suc_uy') {
                $clave_final = $this->pass_locales; 
            }
            elseif ($nameServer == 'central' || $nameServer == 'uy') {
                // Para central y uy, usar credenciales por defecto
                $usuario_final = 'sa';
                $clave_final = 'Axoft1988';
            }
            elseif ($nameServer != 'central' && $nameServer != 'uy' 
                    && $nameServer != 'suc_uy' && $nameServer != 'locales') {
                // Para otras conexiones (sucursales específicas), usar credenciales de sesión si existen
                if (isset($_SESSION['usuario_dns']) && !empty($_SESSION['usuario_dns'])) {
                    $usuario_final = $_SESSION['usuario_dns'];
                }
                if (isset($_SESSION['clave_dns']) && !empty($_SESSION['clave_dns'])) {
                    $clave_final = $_SESSION['clave_dns'];
                }
            }
            
            // --- FIN DE LA LÓGICA ---

            $params = array( 
                "Database" => $serverDB[1], 
                "UID" => $usuario_final, 
                "PWD" => $clave_final, 
                "CharacterSet" => $this->character
            );
        
            $cid = sqlsrv_connect($serverDB[0], $params);

            if(!$cid) {
                error_log("Error de conexión SQL Server: " . print_r(sqlsrv_errors(), true));
                return false;
            }

            $_SESSION['cid'] = $cid;
            return $cid;
            
        } catch (Exception $e) {
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
