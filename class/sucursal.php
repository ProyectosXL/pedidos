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
     * Obtiene sucursales por lista de números (p. ej. $_SESSION['sucursalesGrupo']).
     * @param array $numeros
     * @param string $db
     * @return array
     */
    public function listarSucursalesPorNumeros(array $numeros, $db = 'central') {
        try {
            $numeros = array_values(array_unique(array_filter(array_map('intval', $numeros))));
            if (empty($numeros)) {
                return [];
            }

            $cid = $this->conn->conectar($db);
            if ($cid === false) {
                return [];
            }

            $collate = 'Modern_Spanish_CI_AI';
            $placeholders = implode(',', array_fill(0, count($numeros), '?'));
            $sql = "
            SELECT
                CAST(S.NRO_SUCURSAL AS INT) AS N_IMPUESTO,
                COALESCE(
                    NULLIF(LTRIM(RTRIM(S.COD_CLIENTE COLLATE $collate)), ''),
                    NULLIF(LTRIM(RTRIM(L.COD_CLIENT COLLATE $collate)), ''),
                    LTRIM(RTRIM(A.COD_CLIENT COLLATE $collate))
                ) AS COD_CLIENT,
                COALESCE(
                    NULLIF(LTRIM(RTRIM(S.NOMBRE_SUCURSAL COLLATE $collate)), ''),
                    NULLIF(LTRIM(RTRIM(L.DESC_SUCURSAL COLLATE $collate)), ''),
                    NULLIF(LTRIM(RTRIM(A.NOM_COM COLLATE $collate)), '')
                ) AS NOM_COM,
                COALESCE(
                    NULLIF(LTRIM(RTRIM(S.DSN COLLATE $collate)), ''),
                    NULLIF(LTRIM(RTRIM(B.DSN COLLATE $collate)), ''),
                    ''
                ) AS DSN
            FROM SJ_SUCURSALES_FRANQUICIA S
            LEFT JOIN [LAKERBIS].locales_lakers.dbo.SUCURSALES_LAKERS L
                ON S.NRO_SUCURSAL = L.NRO_SUCURSAL AND L.NRO_SUC_MADRE IS NULL
            LEFT JOIN GVA14 A
                ON LTRIM(RTRIM(A.COD_CLIENT COLLATE $collate)) = LTRIM(RTRIM(S.COD_CLIENTE COLLATE $collate))
            LEFT JOIN SOF_USUARIOS B
                ON S.NRO_SUCURSAL = B.NRO_SUCURS
            WHERE S.NRO_SUCURSAL IN ($placeholders) AND S.ACTIVO = 1
            ORDER BY S.NRO_SUCURSAL
            ";

            $sucursales = [];
            $encontrados = [];

            $stmt = sqlsrv_query($cid, $sql, $numeros);
            if ($stmt === false) {
                error_log('Error en listarSucursalesPorNumeros: ' . print_r(sqlsrv_errors(), true));
            } else {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $nro = (int) $row['N_IMPUESTO'];
                    $encontrados[$nro] = true;
                    if (empty(trim((string) ($row['NOM_COM'] ?? '')))) {
                        $row['NOM_COM'] = 'Sucursal ' . $nro;
                    }
                    $sucursales[] = $row;
                }
                sqlsrv_free_stmt($stmt);
            }

            $faltantes = array_values(array_filter($numeros, function ($n) use ($encontrados) {
                return !isset($encontrados[$n]);
            }));

            if (!empty($faltantes)) {
                $ph2 = implode(',', array_fill(0, count($faltantes), '?'));
                $sql2 = "
                SELECT
                    CAST(L.NRO_SUCURSAL AS INT) AS N_IMPUESTO,
                    LTRIM(RTRIM(A.COD_CLIENT COLLATE $collate)) AS COD_CLIENT,
                    COALESCE(
                        NULLIF(LTRIM(RTRIM(L.DESC_SUCURSAL COLLATE $collate)), ''),
                        NULLIF(LTRIM(RTRIM(A.NOM_COM COLLATE $collate)), '')
                    ) AS NOM_COM,
                    COALESCE(NULLIF(LTRIM(RTRIM(B.DSN COLLATE $collate)), ''), '') AS DSN
                FROM [LAKERBIS].locales_lakers.dbo.SUCURSALES_LAKERS L
                LEFT JOIN GVA14 A
                    ON LTRIM(RTRIM(A.COD_CLIENT COLLATE $collate)) = LTRIM(RTRIM(L.COD_CLIENT COLLATE $collate))
                LEFT JOIN SOF_USUARIOS B
                    ON L.NRO_SUCURSAL = B.NRO_SUCURS
                WHERE L.NRO_SUCURSAL IN ($ph2)
                  AND L.NRO_SUC_MADRE IS NULL
                  AND L.HABILITADO = 1
                ORDER BY L.NRO_SUCURSAL
                ";
                $stmt2 = sqlsrv_query($cid, $sql2, $faltantes);
                if ($stmt2 === false) {
                    error_log('Error en listarSucursalesPorNumeros (Lakers): ' . print_r(sqlsrv_errors(), true));
                } else {
                    while ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
                        $nro = (int) $row['N_IMPUESTO'];
                        $encontrados[$nro] = true;
                        if (empty(trim((string) ($row['NOM_COM'] ?? '')))) {
                            $row['NOM_COM'] = 'Sucursal ' . $nro;
                        }
                        $sucursales[] = $row;
                    }
                    sqlsrv_free_stmt($stmt2);
                }
            }

            usort($sucursales, function ($a, $b) {
                return (int) $a['N_IMPUESTO'] <=> (int) $b['N_IMPUESTO'];
            });

            return $sucursales;
        } catch (Exception $e) {
            error_log('Error en listarSucursalesPorNumeros: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Sucursales del grupo empresario definidas en validar.php (GRUPO_EMPR → sucursalesGrupo).
     * @return int[]
     */
    public function obtenerNumerosGrupoEmpresario() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['sucursalesGrupo']) || !is_array($_SESSION['sucursalesGrupo'])) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $_SESSION['sucursalesGrupo']))));
    }

    /**
     * Resuelve sucursales para pantallas de pedido usando sucursalesGrupo / cargaPedido.
     * @param string $db
     * @return array{activas: string[], info: array<string, array>}
     */
    public function construirSucursalesActivasParaPedido($db = 'central') {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $desdeCarga = $this->construirInfoDesdeSesionCarga();
        if ($desdeCarga !== null) {
            return $desdeCarga;
        }

        $numerosGrupo = $this->obtenerNumerosGrupoEmpresario();
        if (!empty($numerosGrupo)) {
            return $this->construirInfoDesdeNumeros($numerosGrupo, $db);
        }

        if (isset($_SESSION['ID_FRANQUICIA'])) {
            $sucursalesLista = $this->listarSucursalesPorFranquicia($_SESSION['ID_FRANQUICIA'], $db);
            $info = [];
            foreach ($sucursalesLista as $suc) {
                $nroSuc = (string) $suc['N_IMPUESTO'];
                $info[$nroSuc] = [
                    'nombre' => $suc['NOM_COM'],
                    'codClient' => $suc['COD_CLIENT'],
                    'nombreCompleto' => $suc['NOM_COM'],
                ];
            }
            return ['activas' => array_keys($info), 'info' => $info];
        }

        return ['activas' => [], 'info' => []];
    }

    private function construirInfoDesdeSesionCarga() {
        if (empty($_SESSION['sucursales_activas']) || empty($_SESSION['sucursales_info'])) {
            return null;
        }

        $numeros = array_map('intval', $_SESSION['sucursales_activas']);
        $nombresDb = $this->mapaNombresPorNumeros($numeros);

        $info = [];
        foreach ($_SESSION['sucursales_activas'] as $nro) {
            $nro = (string) $nro;
            if (!isset($_SESSION['sucursales_info'][$nro])) {
                continue;
            }
            $s = $_SESSION['sucursales_info'][$nro];
            $nombreDb = $nombresDb[(int) $nro] ?? null;
            $nombreSesion = trim((string) ($s['nombre'] ?? ''));
            $nombre = $nombreDb
                ?? ($nombreSesion !== '' && !preg_match('/^Sucursal\s+\d+$/i', $nombreSesion) ? $nombreSesion : null)
                ?? ('Sucursal ' . $nro);

            $info[$nro] = [
                'nombre'         => $nombre,
                'codClient'      => $s['codClient'] ?? '',
                'nombreCompleto' => $nombre,
            ];
        }

        return empty($info) ? null : ['activas' => array_keys($info), 'info' => $info];
    }

    /**
     * @param array<int|string> $numeros
     * @return array<int, string> num_suc => nombre
     */
    private function mapaNombresPorNumeros(array $numeros): array {
        $mapa = [];
        foreach ($this->listarSucursalesPorNumeros($numeros) as $suc) {
            $nro = (int) $suc['N_IMPUESTO'];
            $nombre = trim((string) ($suc['NOM_COM'] ?? ''));
            if ($nombre !== '' && !preg_match('/^Sucursal\s+\d+$/i', $nombre)) {
                $mapa[$nro] = $nombre;
            }
        }
        return $mapa;
    }

    private function construirInfoDesdeNumeros(array $numeros, $db = 'central') {
        $lista = $this->listarSucursalesPorNumeros($numeros, $db);
        $info = [];
        foreach ($lista as $suc) {
            $nro = (string) $suc['N_IMPUESTO'];
            $info[$nro] = [
                'nombre' => $suc['NOM_COM'],
                'codClient' => $suc['COD_CLIENT'],
                'nombreCompleto' => $suc['NOM_COM'],
            ];
        }
        return ['activas' => array_keys($info), 'info' => $info];
    }

    private function obtenerMapeoDesdeSesionGrupo($db = 'central') {
        $mapeo = $this->resolverMapeoCodigosGrupo($db);
        return !empty($mapeo) ? $mapeo : null;
    }

    /**
     * Números de sucursal del grupo: sucursalesGrupo o sucursales_activas en sesión.
     * @return int[]
     */
    private function obtenerNumerosResolucionGrupo() {
        $numeros = $this->obtenerNumerosGrupoEmpresario();
        if (!empty($numeros)) {
            return $numeros;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['sucursales_activas']) && is_array($_SESSION['sucursales_activas'])) {
            return array_values(array_unique(array_filter(array_map('intval', $_SESSION['sucursales_activas']))));
        }
        return [];
    }

    /**
     * Resuelve COD_CLIENT por número de sucursal (grupo empresario).
     * @return array<string, string> num_suc => cod_client
     */
    public function resolverMapeoCodigosGrupo($db = 'central') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $mapeo = [];

        if (!empty($_SESSION['sucursales_info']) && is_array($_SESSION['sucursales_info'])) {
            foreach ($_SESSION['sucursales_info'] as $nro => $info) {
                $cod = trim((string) ($info['codClient'] ?? ''));
                if ($cod !== '') {
                    $mapeo[(string) $nro] = $cod;
                }
            }
        }

        // Misma lógica que validar.php (GRUPO_EMPR → GVA14 + SUCURSALES_LAKERS)
        $codGrupo = trim((string) ($_SESSION['codClient'] ?? ''));
        if ($codGrupo !== '') {
            foreach ($this->buscarCodigosPorGrupoEmpresario($codGrupo, $db) as $row) {
                $nro = (int) $row['N_IMPUESTO'];
                $cod = trim((string) ($row['COD_CLIENT'] ?? ''));
                if ($nro > 0 && $cod !== '') {
                    $mapeo[(string) $nro] = $cod;
                }
            }
        }

        $numeros = $this->obtenerNumerosResolucionGrupo();
        if (empty($numeros)) {
            return $mapeo;
        }

        $faltantes = array_values(array_filter($numeros, function ($n) use ($mapeo) {
            return !isset($mapeo[(string) $n]);
        }));

        if (!empty($faltantes)) {
            foreach ($this->buscarCodigosPorSucursalesLakers($faltantes, $db) as $row) {
                $nro = (int) $row['N_IMPUESTO'];
                $cod = trim((string) ($row['COD_CLIENT'] ?? ''));
                if ($cod !== '') {
                    $mapeo[(string) $nro] = $cod;
                }
            }
        }

        $faltantes = array_values(array_filter($numeros, function ($n) use ($mapeo) {
            return !isset($mapeo[(string) $n]);
        }));

        if (!empty($faltantes)) {
            foreach ($this->listarSucursalesPorNumeros($faltantes, $db) as $suc) {
                $cod = trim((string) ($suc['COD_CLIENT'] ?? ''));
                if ($cod !== '') {
                    $mapeo[(string) $suc['N_IMPUESTO']] = $cod;
                }
            }
        }

        return $mapeo;
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function ejecutarConsultaLakersCodigos(string $sqlTemplate, array $params, $db = 'central') {
        $servidores = [
            '[XL-LAKERBIS].LOCALES_LAKERS.DBO.SUCURSALES_LAKERS',
            '[LAKERBIS].locales_lakers.dbo.SUCURSALES_LAKERS',
        ];

        $cid = $this->conn->conectar($db);
        if ($cid === false) {
            return [];
        }

        foreach ($servidores as $lakers) {
            $sql = str_replace('{{SUCURSALES_LAKERS}}', $lakers, $sqlTemplate);
            $stmt = sqlsrv_query($cid, $sql, $params);
            if ($stmt === false) {
                error_log('Error consulta Lakers (' . $lakers . '): ' . print_r(sqlsrv_errors(), true));
                continue;
            }

            $rows = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
            sqlsrv_free_stmt($stmt);

            if (!empty($rows)) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * COD_CLIENT + NRO_SUCURSAL del grupo empresario (validar.php / GRUPO_EMPR).
     * @return array<int, array{N_IMPUESTO: int, COD_CLIENT: string, NOM_COM: string}>
     */
    private function buscarCodigosPorGrupoEmpresario(string $grupoEmpr, $db = 'central') {
        $grupoEmpr = trim($grupoEmpr);
        if ($grupoEmpr === '') {
            return [];
        }

        $sql = "
            SELECT
                CAST(C.NRO_SUCURSAL AS INT) AS N_IMPUESTO,
                LTRIM(RTRIM(A.COD_CLIENT)) AS COD_CLIENT,
                LTRIM(RTRIM(C.DESC_SUCURSAL)) AS NOM_COM
            FROM GVA14 A WITH (NOLOCK)
            LEFT JOIN GVA62 B WITH (NOLOCK) ON A.GRUPO_EMPR = B.GRUPO_EMPR
            LEFT JOIN {{SUCURSALES_LAKERS}} C
                ON A.COD_CLIENT = C.COD_CLIENT COLLATE Modern_Spanish_CI_AI
            WHERE A.COD_CLIENT LIKE 'FR%'
              AND C.HABILITADO = 1
              AND C.NRO_SUC_MADRE IS NULL
              AND B.GRUPO_EMPR = ?
            ORDER BY C.NRO_SUCURSAL
        ";

        return $this->ejecutarConsultaLakersCodigos($sql, [$grupoEmpr], $db);
    }

    /**
     * NRO_SUCURSAL (Lakers) → COD_CLIENT en GVA14.
     * @param int[] $numeros
     * @return array<int, array{N_IMPUESTO: int, COD_CLIENT: string, NOM_COM: string}>
     */
    private function buscarCodigosPorSucursalesLakers(array $numeros, $db = 'central') {
        $numeros = array_values(array_unique(array_filter(array_map('intval', $numeros))));
        if (empty($numeros)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($numeros), '?'));
        $sql = "
            SELECT
                CAST(C.NRO_SUCURSAL AS INT) AS N_IMPUESTO,
                LTRIM(RTRIM(A.COD_CLIENT)) AS COD_CLIENT,
                LTRIM(RTRIM(C.DESC_SUCURSAL)) AS NOM_COM
            FROM GVA14 A WITH (NOLOCK)
            INNER JOIN {{SUCURSALES_LAKERS}} C
                ON A.COD_CLIENT = C.COD_CLIENT COLLATE Modern_Spanish_CI_AI
            WHERE CAST(C.NRO_SUCURSAL AS INT) IN ($placeholders)
              AND C.HABILITADO = 1
              AND C.NRO_SUC_MADRE IS NULL
              AND A.COD_CLIENT LIKE 'FR%'
            ORDER BY C.NRO_SUCURSAL
        ";

        return $this->ejecutarConsultaLakersCodigos($sql, $numeros, $db);
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
                $numerosGrupo = $this->obtenerNumerosGrupoEmpresario();
                return !empty($numerosGrupo) ? implode(',', $numerosGrupo) : '';
            }

            $sucursales = $this->listarSucursalesPorFranquicia($idFranquicia, $db);
            
            if (empty($sucursales)) {
                $numerosGrupo = $this->obtenerNumerosGrupoEmpresario();
                return !empty($numerosGrupo) ? implode(',', $numerosGrupo) : '';
            }

            $numeros = [];
            foreach ($sucursales as $suc) {
                $numeros[] = (int)$suc['N_IMPUESTO'];
            }
            
            return implode(',', $numeros);
        }
        catch (Exception $e) {
            error_log("Error en obtenerListaNumerosSucursal: " . $e->getMessage());
            $numerosGrupo = $this->obtenerNumerosGrupoEmpresario();
            return !empty($numerosGrupo) ? implode(',', $numerosGrupo) : '';
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
                $mapeoGrupo = $this->resolverMapeoCodigosGrupo($db);
                return !empty($mapeoGrupo) ? array_values(array_unique($mapeoGrupo)) : [];
            }

            $sucursales = $this->listarSucursalesPorFranquicia($idFranquicia, $db);
            
            if (empty($sucursales)) {
                $mapeoGrupo = $this->resolverMapeoCodigosGrupo($db);
                return !empty($mapeoGrupo) ? array_values(array_unique($mapeoGrupo)) : [];
            }

            $codigos = [];
            foreach ($sucursales as $suc) {
                $codigos[] = $suc['COD_CLIENT'];
            }
            
            return array_unique($codigos);
        }
        catch (Exception $e) {
            error_log("Error en obtenerListaCodigosCliente: " . $e->getMessage());
            $mapeoGrupo = $this->resolverMapeoCodigosGrupo($db);
            return !empty($mapeoGrupo) ? array_values(array_unique($mapeoGrupo)) : [];
        }
    }

    /**
     * Mapeo número de sucursal → COD_CLIENT (grupo empresario o franquicia).
     * @param int|null $idFranquicia
     * @param string $db
     * @return array<string, string>
     */
    public function obtenerMapeoSucursalCodCliente($idFranquicia = null, $db = 'central') {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if ($idFranquicia === null && isset($_SESSION['ID_FRANQUICIA'])) {
            $idFranquicia = $_SESSION['ID_FRANQUICIA'];
        }

        if ($idFranquicia === null) {
            $mapeoGrupo = $this->obtenerMapeoDesdeSesionGrupo($db);
            return $mapeoGrupo !== null ? $mapeoGrupo : [];
        }

        try {
            $sucursales = $this->listarSucursalesPorFranquicia($idFranquicia, $db);
            if (empty($sucursales)) {
                $mapeoGrupo = $this->obtenerMapeoDesdeSesionGrupo($db);
                return $mapeoGrupo !== null ? $mapeoGrupo : [];
            }

            $mapeo = [];
            foreach ($sucursales as $suc) {
                $mapeo[(string) $suc['N_IMPUESTO']] = $suc['COD_CLIENT'];
            }
            return $mapeo;
        } catch (Exception $e) {
            error_log("Error en obtenerMapeoSucursalCodCliente: " . $e->getMessage());
            $mapeoGrupo = $this->obtenerMapeoDesdeSesionGrupo($db);
            return $mapeoGrupo !== null ? $mapeoGrupo : [];
        }
    }

    /**
     * Opciones para selectores (historial, filtros): codigo + nombre visible.
     * @return array<int, array{codigo: string, nombre: string}>
     */
    public function obtenerSucursalesParaSelector($db = 'central') {
        $resolucion = $this->construirSucursalesActivasParaPedido($db);
        $opciones = [];

        foreach ($resolucion['info'] as $data) {
            if (empty($data['codClient'])) {
                continue;
            }
            $opciones[] = [
                'codigo' => $data['codClient'],
                'nombre' => $data['nombreCompleto'] ?? $data['nombre'] ?? $data['codClient'],
            ];
        }

        if (!empty($opciones)) {
            return $opciones;
        }

        foreach ($this->obtenerListaCodigosCliente(null, $db) as $codigo) {
            $opciones[] = ['codigo' => $codigo, 'nombre' => $codigo];
        }

        return $opciones;
    }
}
