<?php
class ConexionMulti {
    private $conexiones = [];
    private $timeout = 5;
    private $envVars = [];
    private $env; // Declarar la propiedad
    
    public function __construct() {
        require_once(__DIR__ . '/../../../sistemas/class/classEnv.php');
        
        $vars = new DotEnv(__DIR__ . '/../../../.env');
        $this->envVars = $vars->listVars();
        
        $this->env = $this->envVars['ENV'] ?? 'PROD';
    }
    
    public function conectar($servidor, $database) {
        // Mapear servidores desde .env (sin fallbacks hardcodeados)
        $servidores = [
            'apps' => $this->envVars['HOST_APPS'] ?? null,
            'locales' => $this->envVars['HOST_LOCALES'] ?? null,
            'central' => $this->envVars['HOST_CENTRAL'] ?? null
        ];
        
        $passwords = [
            'apps' => $this->envVars['PASS'] ?? null,
            'locales' => $this->envVars['PASS_LOCALES'] ?? null,
            'central' => $this->envVars['PASS'] ?? null
        ];
        
        $key = $servidor . '_' . $database;
        if (isset($this->conexiones[$key])) {
            return $this->conexiones[$key];
        }
        
        $pwd = $passwords[$servidor] ?? null;
        $host = $servidores[$servidor] ?? null;
        $uid = $this->envVars['USER'] ?? null;
        
        if (!$host || !$pwd || !$uid) {
            error_log("ConexionMulti: credenciales incompletas para '$servidor'. Revise HOST/USER/PASS en .env");
            return null;
        }
        
        try {
            $connectionInfo = [
                "Database" => $database,
                "UID" => $uid,
                "PWD" => $pwd,
                "CharacterSet" => $this->envVars['CHARACTER'] ?? 'UTF-8',
                "ReturnDatesAsStrings" => true,
                "LoginTimeout" => $this->timeout,
                "ConnectionPooling" => 0
            ];
            
            $conn = @sqlsrv_connect($host, $connectionInfo);
            
            if ($conn === false) {
                $errors = sqlsrv_errors();
                error_log("Error conexión $servidor > $database: " . json_encode($errors));
                return null;
            }
            
            $this->conexiones[$key] = $conn;
            return $conn;
        } catch (Exception $e) {
            error_log("Excepción conexión $servidor: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Conectar a la base de datos específica de un local
     * @param string $nombreLocal - Nombre del local (ej: "UNICENTER", "ALTO PALERMO", "ABASTO")
     * @return array ['conexion' => resource|false, 'error' => string|null, 'info' => array]
     */
    public function conectarLocal($nombreLocal) {
        try {
            // 1. Conectar al servidor de locales para obtener datos de conexión
            $connLocales = $this->conectar('locales', $this->envVars['DATABASE_LOCALES'] ?? null);
            if (!$connLocales) {
                return ['conexion' => false, 'error' => 'No se pudo conectar al servidor de locales para obtener datos', 'info' => null];
            }
            
            // 2. Buscar datos de conexión del local en SUCURSALES_LAKERS
            // Columnas reales: DESC_SUCURSAL, CONEXION_DNS, BASE_NOMBRE, USUARIO_DNS, CLAVE_DNS
            $sql = "SELECT CONEXION_DNS, BASE_NOMBRE, USUARIO_DNS, CLAVE_DNS, NRO_SUCURSAL, COD_CLIENT
                    FROM SUCURSALES_LAKERS 
                    WHERE DESC_SUCURSAL = ?";
            
            $params = [$nombreLocal];
            $stmt = @sqlsrv_query($connLocales, $sql, $params);
            
            if (!$stmt) {
                $errors = sqlsrv_errors();
                $errorMsg = $errors ? json_encode($errors) : 'Error desconocido';
                return ['conexion' => false, 'error' => "Error al consultar SUCURSALES_LAKERS: $errorMsg", 'info' => null];
            }
            
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
            
            if (!$row) {
                return ['conexion' => false, 'error' => "Local '$nombreLocal' no encontrado en SUCURSALES_LAKERS", 'info' => null];
            }
            
            $servidor = $row['CONEXION_DNS'] ? trim($row['CONEXION_DNS']) : null;
            $base = $row['BASE_NOMBRE'] ? trim($row['BASE_NOMBRE']) : null;
            $usuario = $row['USUARIO_DNS'] ? trim($row['USUARIO_DNS']) : ($this->envVars['USER'] ?? null);
            $password = $row['CLAVE_DNS'] ? trim($row['CLAVE_DNS']) : ($this->envVars['PASS'] ?? null);
            $nroSucursal = $row['NRO_SUCURSAL'];
            $codClient = $row['COD_CLIENT'] ? trim($row['COD_CLIENT']) : '';
            
            // Validar que tengamos los datos mínimos
            if (!$servidor || !$base || !$password) {
                return ['conexion' => false, 'error' => "Local '$nombreLocal' no tiene datos de conexión completos (servidor, base o password faltante)", 'info' => null];
            }
            
            // 3. Intentar conectar al servidor del local
            $connectionInfo = [
                "Database" => $base,
                "UID" => $usuario,
                "PWD" => $password,
                "CharacterSet" => $this->envVars['CHARACTER'] ?? 'UTF-8',
                "ReturnDatesAsStrings" => true,
                "LoginTimeout" => $this->timeout,
                "ConnectionPooling" => 0,
                "ConnectRetryCount" => 1,
                "ConnectRetryInterval" => 1
            ];
            
            $conn = @sqlsrv_connect($servidor, $connectionInfo);
            
            if ($conn === false) {
                $errors = sqlsrv_errors();
                $errorMsg = $errors ? $errors[0]['message'] : 'Error desconocido';
                return [
                    'conexion' => false, 
                    'error' => "No se pudo conectar al servidor del local $servidor/$base: $errorMsg",
                    'info' => ['servidor' => $servidor, 'base' => $base, 'nro_sucursal' => $nroSucursal, 'cod_client' => $codClient]
                ];
            }
            
            // Guardar conexión en cache
            $key = "local_" . strtolower(str_replace(' ', '_', $nombreLocal));
            $this->conexiones[$key] = $conn;
            
            return [
                'conexion' => $conn, 
                'error' => null,
                'info' => ['servidor' => $servidor, 'base' => $base, 'nro_sucursal' => $nroSucursal, 'cod_client' => $codClient]
            ];
            
        } catch (Exception $e) {
            return ['conexion' => false, 'error' => 'Excepción al conectar al local: ' . $e->getMessage(), 'info' => null];
        }
    }
    
    public function cerrarTodas() {
        foreach ($this->conexiones as $conn) {
            if ($conn) sqlsrv_close($conn);
        }
        $this->conexiones = [];
    }
}
