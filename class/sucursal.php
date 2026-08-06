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

        $resultado = null;

        $desdeCarga = $this->construirInfoDesdeSesionCarga();
        if ($desdeCarga !== null) {
            $resultado = $desdeCarga;
        } else {
            $numerosGrupo = $this->obtenerNumerosGrupoEmpresario();
            if (!empty($numerosGrupo)) {
                $resultado = $this->construirInfoDesdeNumeros($numerosGrupo, $db);
            } elseif (isset($_SESSION['ID_FRANQUICIA'])) {
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
                $resultado = ['activas' => array_keys($info), 'info' => $info];
            }
        }

        if ($resultado === null) {
            return ['activas' => [], 'info' => []];
        }

        $resultado['info'] = $this->calcularNombresCortos($resultado['info']);
        $resultado         = $this->aplicarPreferenciasSucursales($resultado);

        return $resultado;
    }

    /**
     * Calcula un nombre corto automático (<=20 caracteres) por sucursal para
     * mostrar en el encabezado de la matriz de pedido, sin modificar
     * 'nombre'/'codClient'/'nombreCompleto'. Este cálculo es el *placeholder*
     * en el modal de preferencias; el texto final del encabezado ('nombreCorto')
     * lo resuelve aplicarPreferenciasSucursales() combinándolo con el alias
     * que haya definido el usuario.
     *
     * Criterio: parte de 'nombreCompleto', quita sufijos societarios sueltos
     * (S.A., SRL, etc.) y detecta el prefijo de tokens compartido por TODAS las
     * sucursales del grupo (p. ej. "ORIGINAL PRODUCTS" en "ORIGINAL PRODUCTS
     * BAULERA" / "ORIGINAL PRODUCTS NUEVO CENTRO") para quedarse solo con la
     * parte distintiva ("BAULERA" / "NUEVO CENTRO"). Repite el mismo criterio
     * con el sufijo común de tokens finales. La evaluación es por sucursal: si
     * a una sucursal el prefijo/sufijo común le consume el nombre entero (p. ej.
     * una sucursal llamada igual que la razón social del grupo), esa sucursal
     * conserva su nombre completo y las demás igual quedan acortadas.
     *
     * @param array<string, array> $info
     * @return array<string, array> mismo array con 'nombreCortoAuto' agregado
     */
    private function calcularNombresCortos(array $info): array {
        $sufijosSocietarios = ['S.A.', 'SA', 'S.R.L.', 'SRL', 'S.A.S.', 'SAS'];

        $tokensPorSucursal = [];
        foreach ($info as $suc => $data) {
            $nombre = preg_replace('/\s+/', ' ', trim((string) ($data['nombreCompleto'] ?? '')));
            $nombre = $this->quitarSufijoSocietario($nombre, $sufijosSocietarios);
            $tokensPorSucursal[$suc] = $nombre === '' ? [] : explode(' ', $nombre);
        }

        $tokensPorSucursal = $this->quitarPrefijoComun($tokensPorSucursal);
        $tokensPorSucursal = $this->quitarSufijoComun($tokensPorSucursal);

        foreach ($info as $suc => $data) {
            $corto = trim(implode(' ', $tokensPorSucursal[$suc] ?? []));
            $corto = $this->limpiarSeparadoresSueltos($corto);
            $corto = $this->truncarConLimitePalabra($corto, 20);

            if ($corto === '') {
                $corto = $this->truncarConLimitePalabra(trim((string) ($data['nombreCompleto'] ?? '')), 20);
            }
            if ($corto === '') {
                $corto = trim((string) ($data['codClient'] ?? ''));
            }

            $info[$suc]['nombreCortoAuto'] = $corto;
        }

        return $info;
    }

    /**
     * Normaliza para comparar: mayúsculas y sin acentos (equivalente a la
     * collation Modern_Spanish_CI_AI usada en SQL).
     */
    private function normalizarParaComparar(string $s): string {
        $s = mb_strtoupper(trim($s), 'UTF-8');
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        return $translit !== false ? trim($translit) : $s;
    }

    /**
     * @param string[] $sufijos
     */
    private function quitarSufijoSocietario(string $nombre, array $sufijos): string {
        if ($nombre === '') {
            return $nombre;
        }
        $tokens = explode(' ', $nombre);
        $ultimo = rtrim(end($tokens), '.,');
        foreach ($sufijos as $sufijo) {
            $sufijoNorm = rtrim($sufijo, '.,');
            if ($this->normalizarParaComparar($ultimo) === $this->normalizarParaComparar($sufijoNorm)) {
                array_pop($tokens);
                break;
            }
        }
        return trim(implode(' ', $tokens));
    }

    /**
     * Quita el prefijo de tokens común a todas las sucursales. La guarda de
     * seguridad es por sucursal: si a alguna el prefijo le consume el nombre
     * entero (nombre == prefijo), esa sucursal conserva su nombre completo y
     * las demás igual quedan acortadas. Solo se descarta el paso para TODAS
     * si, tras eso, quedan menos de 2 sucursales con remanente no vacío (el
     * prefijo detectado no sería informativo).
     * @param array<string, string[]> $tokensPorSucursal
     * @return array<string, string[]>
     */
    private function quitarPrefijoComun(array $tokensPorSucursal): array {
        if (count($tokensPorSucursal) < 2) {
            return $tokensPorSucursal;
        }

        $listas = array_values($tokensPorSucursal);
        $minLen = min(array_map('count', $listas));
        $prefijoLen = 0;

        for ($i = 0; $i < $minLen; $i++) {
            $tokenNorm = $this->normalizarParaComparar($listas[0][$i]);
            $coincide = true;
            foreach ($listas as $tokens) {
                if ($this->normalizarParaComparar($tokens[$i]) !== $tokenNorm) {
                    $coincide = false;
                    break;
                }
            }
            if (!$coincide) {
                break;
            }
            $prefijoLen++;
        }

        if ($prefijoLen === 0) {
            return $tokensPorSucursal;
        }

        $remanentes = [];
        $conRemanente = 0;
        foreach ($tokensPorSucursal as $suc => $tokens) {
            $recorte = array_slice($tokens, $prefijoLen);
            $remanentes[$suc] = $recorte;
            if (!empty($recorte)) {
                $conRemanente++;
            }
        }

        if ($conRemanente < 2) {
            return $tokensPorSucursal;
        }

        $resultado = [];
        foreach ($tokensPorSucursal as $suc => $tokens) {
            $resultado[$suc] = !empty($remanentes[$suc]) ? $remanentes[$suc] : $tokens;
        }

        return $resultado;
    }

    /**
     * Analogo a quitarPrefijoComun() pero con el sufijo de tokens finales.
     * Misma guarda por sucursal.
     * @param array<string, string[]> $tokensPorSucursal
     * @return array<string, string[]>
     */
    private function quitarSufijoComun(array $tokensPorSucursal): array {
        if (count($tokensPorSucursal) < 2) {
            return $tokensPorSucursal;
        }

        $listas = array_values($tokensPorSucursal);
        $minLen = min(array_map('count', $listas));
        $sufijoLen = 0;

        for ($i = 0; $i < $minLen; $i++) {
            $idx0 = count($listas[0]) - 1 - $i;
            $tokenNorm = $this->normalizarParaComparar($listas[0][$idx0]);
            $coincide = true;
            foreach ($listas as $tokens) {
                $idx = count($tokens) - 1 - $i;
                if ($this->normalizarParaComparar($tokens[$idx]) !== $tokenNorm) {
                    $coincide = false;
                    break;
                }
            }
            if (!$coincide) {
                break;
            }
            $sufijoLen++;
        }

        if ($sufijoLen === 0) {
            return $tokensPorSucursal;
        }

        $remanentes = [];
        $conRemanente = 0;
        foreach ($tokensPorSucursal as $suc => $tokens) {
            $recorte = array_slice($tokens, 0, count($tokens) - $sufijoLen);
            $remanentes[$suc] = $recorte;
            if (!empty($recorte)) {
                $conRemanente++;
            }
        }

        if ($conRemanente < 2) {
            return $tokensPorSucursal;
        }

        $resultado = [];
        foreach ($tokensPorSucursal as $suc => $tokens) {
            $resultado[$suc] = !empty($remanentes[$suc]) ? $remanentes[$suc] : $tokens;
        }

        return $resultado;
    }

    private function limpiarSeparadoresSueltos(string $s): string {
        return trim($s, " \t\n\r\0\x0B-/.,");
    }

    private function truncarConLimitePalabra(string $s, int $limite): string {
        $s = trim($s);
        if ($s === '' || mb_strlen($s, 'UTF-8') <= $limite) {
            return $s;
        }

        $cortado = mb_substr($s, 0, $limite, 'UTF-8');
        $ultimoEspacio = mb_strrpos($cortado, ' ', 0, 'UTF-8');
        if ($ultimoEspacio !== false && $ultimoEspacio > 0) {
            $cortado = mb_substr($cortado, 0, $ultimoEspacio, 'UTF-8');
        }

        return rtrim($cortado) . '…';
    }

    /**
     * Orden y alias de sucursales preferidos por el usuario: siempre en 'central',
     * porque no existen grupos empresarios en Uruguay.
     */
    private function obtenerCodUsuarioOrden(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return trim((string) ($_SESSION['username'] ?? ''));
    }

    /**
     * Saneamiento único del alias editable de sucursal, usado tanto al leer
     * (blindaje ante datos cargados a mano en la tabla) como al guardar.
     * Colapsa espacios, quita caracteres de control, permite letras (con
     * acentos/ñ), números, espacios y '. - / & ( )', y recorta a 20 caracteres
     * (mismo tope que el encabezado y el maxlength del input del modal).
     * @param mixed $alias
     */
    private function sanitizarAlias($alias): string {
        $alias = (string) $alias;
        $alias = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $alias);
        $alias = preg_replace('/[^\p{L}\p{N} .\-\/&()]/u', '', (string) $alias);
        $alias = preg_replace('/\s+/', ' ', (string) $alias);
        $alias = trim((string) $alias);

        if ($alias !== '' && mb_strlen($alias, 'UTF-8') > 20) {
            $alias = rtrim(mb_substr($alias, 0, 20, 'UTF-8'));
        }

        return $alias;
    }

    /**
     * @return array<int, array{orden: int, alias: string}> NRO_SUCURSAL => preferencias, saneado el alias
     */
    public function obtenerPreferenciasSucursales(): array {
        $codUsuario = $this->obtenerCodUsuarioOrden();
        if ($codUsuario === '') {
            return [];
        }

        try {
            $cid = $this->conn->conectar('central');
            if ($cid === false) {
                return [];
            }

            $sql = "
                SELECT NRO_SUCURSAL, ORDEN, ALIAS
                FROM RO_T_PEDIDOS_ORDEN_SUCURSAL
                WHERE COD_USUARIO COLLATE Modern_Spanish_CI_AI = ?
                ORDER BY ORDEN
            ";
            $stmt = sqlsrv_query($cid, $sql, [$codUsuario]);
            if ($stmt === false) {
                error_log('Error en obtenerPreferenciasSucursales: ' . print_r(sqlsrv_errors(), true));
                return [];
            }

            $preferencias = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $preferencias[(int) $row['NRO_SUCURSAL']] = [
                    'orden' => (int) $row['ORDEN'],
                    'alias' => $this->sanitizarAlias($row['ALIAS'] ?? ''),
                ];
            }
            sqlsrv_free_stmt($stmt);

            return $preferencias;
        } catch (Exception $e) {
            error_log('Error en obtenerPreferenciasSucursales: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Completa 'alias' y 'nombreCorto' (alias si no está vacío, si no
     * 'nombreCortoAuto') en cada elemento de 'info', y reordena 'activas'/'info'
     * según el orden guardado por el usuario. Las sucursales sin fila guardada
     * van al final por número ascendente, para que una sucursal nueva del grupo
     * nunca desaparezca ni rompa el orden previo. Sin preferencias guardadas
     * (p. ej. filas con ALIAS en NULL o usuario sin fila), el orden queda
     * intacto y 'nombreCorto' cae al automático.
     * @param array{activas: string[], info: array<string, array>} $resolucion
     * @return array{activas: string[], info: array<string, array>}
     */
    private function aplicarPreferenciasSucursales(array $resolucion): array {
        $preferencias = $this->obtenerPreferenciasSucursales();

        $info = $resolucion['info'];
        foreach ($info as $nroSuc => $data) {
            $alias = $preferencias[(int) $nroSuc]['alias'] ?? '';
            $info[$nroSuc]['alias'] = $alias;
            $info[$nroSuc]['nombreCorto'] = $alias !== '' ? $alias : ($data['nombreCortoAuto'] ?? '');
        }

        if (empty($preferencias)) {
            return ['activas' => array_keys($info), 'info' => $info];
        }

        $conOrden = [];
        $sinOrden = [];

        foreach ($info as $nroSuc => $data) {
            $nro = (int) $nroSuc;
            if (isset($preferencias[$nro]['orden'])) {
                $conOrden[$nroSuc] = $preferencias[$nro]['orden'];
            } else {
                $sinOrden[] = $nroSuc;
            }
        }

        asort($conOrden);
        usort($sinOrden, function ($a, $b) {
            return (int) $a <=> (int) $b;
        });

        $infoOrdenada = [];
        foreach (array_merge(array_keys($conOrden), $sinOrden) as $nroSuc) {
            $infoOrdenada[$nroSuc] = $info[$nroSuc];
        }

        return ['activas' => array_keys($infoOrdenada), 'info' => $infoOrdenada];
    }

    /**
     * Persiste orden y alias del usuario de sesión en una sola operación
     * transaccional: borra las filas anteriores e inserta las nuevas con
     * ORDEN correlativo desde 1. Un solo POST, un DELETE+INSERT — llamarlo dos
     * veces pisaría lo guardado en la primera llamada.
     * @param array<int, array{nro: int|string, alias?: string}> $items en el orden deseado
     */
    public function guardarPreferenciasSucursales(array $items): bool {
        $codUsuario = $this->obtenerCodUsuarioOrden();
        if ($codUsuario === '') {
            return false;
        }

        $filas = [];
        foreach ($items as $item) {
            $nro = isset($item['nro']) ? (int) $item['nro'] : 0;
            if ($nro <= 0) {
                continue;
            }
            $filas[] = [
                'nro'   => $nro,
                'alias' => $this->sanitizarAlias($item['alias'] ?? ''),
            ];
        }

        $numeros = array_column($filas, 'nro');
        if (empty($filas) || count($numeros) !== count(array_unique($numeros))) {
            return false;
        }

        try {
            $cid = $this->conn->conectar('central');
            if ($cid === false) {
                return false;
            }

            if (sqlsrv_begin_transaction($cid) === false) {
                error_log('Error al iniciar transacción en guardarPreferenciasSucursales: ' . print_r(sqlsrv_errors(), true));
                return false;
            }

            $sqlDelete = "DELETE FROM RO_T_PEDIDOS_ORDEN_SUCURSAL WHERE COD_USUARIO COLLATE Modern_Spanish_CI_AI = ?";
            $stmtDelete = sqlsrv_query($cid, $sqlDelete, [$codUsuario]);
            if ($stmtDelete === false) {
                error_log('Error en DELETE de guardarPreferenciasSucursales: ' . print_r(sqlsrv_errors(), true));
                sqlsrv_rollback($cid);
                return false;
            }
            sqlsrv_free_stmt($stmtDelete);

            $sqlInsert = "
                INSERT INTO RO_T_PEDIDOS_ORDEN_SUCURSAL (COD_USUARIO, NRO_SUCURSAL, ORDEN, ALIAS, FEC_ALTA, FEC_MODIF)
                VALUES (?, ?, ?, ?, GETDATE(), GETDATE())
            ";

            $orden = 1;
            foreach ($filas as $fila) {
                $aliasParam = $fila['alias'] !== '' ? $fila['alias'] : null;
                $stmtInsert = sqlsrv_query($cid, $sqlInsert, [$codUsuario, $fila['nro'], $orden, $aliasParam]);
                if ($stmtInsert === false) {
                    error_log('Error en INSERT de guardarPreferenciasSucursales: ' . print_r(sqlsrv_errors(), true));
                    sqlsrv_rollback($cid);
                    return false;
                }
                sqlsrv_free_stmt($stmtInsert);
                $orden++;
            }

            if (sqlsrv_commit($cid) === false) {
                error_log('Error al confirmar transacción en guardarPreferenciasSucursales: ' . print_r(sqlsrv_errors(), true));
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log('Error en guardarPreferenciasSucursales: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Borra el orden y el alias guardados del usuario de sesión. Sin filas,
     * aplicarPreferenciasSucursales() deja el orden natural (por número de
     * sucursal) y 'nombreCorto' cae al automático.
     */
    public function restaurarPreferenciasPorDefecto(): bool {
        $codUsuario = $this->obtenerCodUsuarioOrden();
        if ($codUsuario === '') {
            return false;
        }

        try {
            $cid = $this->conn->conectar('central');
            if ($cid === false) {
                return false;
            }

            $sql = "DELETE FROM RO_T_PEDIDOS_ORDEN_SUCURSAL WHERE COD_USUARIO COLLATE Modern_Spanish_CI_AI = ?";
            $stmt = sqlsrv_query($cid, $sql, [$codUsuario]);
            if ($stmt === false) {
                error_log('Error en restaurarPreferenciasPorDefecto: ' . print_r(sqlsrv_errors(), true));
                return false;
            }
            sqlsrv_free_stmt($stmt);

            return true;
        } catch (Exception $e) {
            error_log('Error en restaurarPreferenciasPorDefecto: ' . $e->getMessage());
            return false;
        }
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
