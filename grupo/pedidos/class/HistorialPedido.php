<?php

class HistorialPedido
{
    /**
     * Talonarios de pedidos visibles en el historial, por tipo.
     * Quedan afuera los de ecommerce (98 MercadoLibre, 99 VTEX) y 5 (recodificaciones).
     */
    const TALONARIOS_REPOSICION = [96, 97, 120];

    /** Talonarios de distribución */
    const TALONARIOS_DISTRIBUCION = [1];

    /** Talonarios de rotación (2 = PEDIDOS DE ACCESORIOS) */
    const TALONARIOS_ROTACION = [2];

    private $cid;
    public $cid_central;

    /** @var array<int, string> */
    private static $debugTrace = [];

    function __construct()
    {
        require_once __DIR__ . '/../../../class/conexion.php';
        require_once __DIR__ . '/../../../class/GrupoSesion.php';
        GrupoSesion::iniciar();
        $this->cid = new Conexion();
        $this->cid_central = $this->cid->conectar(GrupoSesion::obtenerBaseDatos());
        $this->logDebug('constructor', [
            'db'        => GrupoSesion::obtenerBaseDatos(),
            'conectado' => $this->cid_central !== false,
        ]);
    }

    /** @return array<int, string> */
    public static function getDebugTrace()
    {
        return self::$debugTrace;
    }

    /**
     * Tipo de pedido según el talonario (mismo criterio que sistemas/pedidosNew).
     * @param mixed $talonPed
     */
    public static function tipoDePedido($talonPed): string
    {
        $talon = (int) $talonPed;

        if (in_array($talon, self::TALONARIOS_DISTRIBUCION, true)) {
            return 'Distribución';
        }
        if (in_array($talon, self::TALONARIOS_REPOSICION, true)) {
            return 'Reposición';
        }
        if (in_array($talon, self::TALONARIOS_ROTACION, true)) {
            return 'Rotación';
        }

        return 'Otro';
    }

    /**
     * Talonarios visibles en el historial (mismos en central y Uruguay).
     * @return int[]
     */
    public static function talonariosPermitidos(): array
    {
        return array_merge(
            self::TALONARIOS_DISTRIBUCION,
            self::TALONARIOS_ROTACION,
            self::TALONARIOS_REPOSICION
        );
    }

    /** Lista de talonarios lista para interpolar en SQL. */
    private function talonariosSQL(): string
    {
        return implode(', ', array_map('intval', self::talonariosPermitidos()));
    }

  /**
     * @param array<string, mixed> $context
     */
    private function logDebug(string $paso, array $context = [])
    {
        $line = '[HistorialPedido][' . $paso . '] ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        error_log($line);
        if (!empty($_GET['debug']) && $_GET['debug'] === '1') {
            self::$debugTrace[] = $line;
        }
    }

    /**
     * Trae el historial de pedidos para una sucursal específica
     * @param string $sucursal Código de la sucursal (ej: FRBAUD, FRORCE, etc.)
     * @param string $desde Fecha desde (formato Y-m-d)
     * @param string $hasta Fecha hasta (formato Y-m-d)
     * @return array Array con los resultados
     */
    public function traerHistorialPorSucursal($sucursal, $desde, $hasta)
    {
        $this->logDebug('traerHistorialPorSucursal_inicio', compact('sucursal', 'desde', 'hasta'));

        if (!$this->cid_central) {
            $this->logDebug('traerHistorialPorSucursal_abort', ['motivo' => 'sin_conexion_central']);
            return [];
        }

        require_once __DIR__.'/../../../class/sucursal.php';
        $sucursalObj = new Sucursal();
        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $sucursalesValidas = $sucursalObj->obtenerListaCodigosCliente($idFranquicia, GrupoSesion::obtenerBaseDatos());

        $this->logDebug('traerHistorialPorSucursal_sucursales', [
            'id_franquicia'      => $idFranquicia,
            'sucursales_validas' => $sucursalesValidas,
            'sucursales_grupo'   => $_SESSION['sucursalesGrupo'] ?? null,
            'sucursales_activas' => $_SESSION['sucursales_activas'] ?? null,
        ]);

        if (!in_array($sucursal, $sucursalesValidas, true)) {
            $this->logDebug('traerHistorialPorSucursal_abort', [
                'motivo'   => 'sucursal_no_valida',
                'sucursal' => $sucursal,
            ]);
            return [];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $this->logDebug('traerHistorialPorSucursal_abort', ['motivo' => 'fechas_invalidas', 'desde' => $desde, 'hasta' => $hasta]);
            return [];
        }

        // Escapar valores para SQL (aunque ya están validados)
        $sucursal = str_replace("'", "''", $sucursal);
        $desde = str_replace("'", "''", $desde);
        $hasta = str_replace("'", "''", $hasta);

        // Construir lista de códigos para SQL
        $codigosSQL = "'" . implode("', '", array_map(function($cod) { return str_replace("'", "''", $cod); }, $sucursalesValidas)) . "'";

        $talonariosSQL = $this->talonariosSQL();

        $sql = "
            SET DATEFORMAT YMD

            SELECT CAST(FECHA_PEDI AS DATE) FECHA, A.COD_CLIENT, A.NRO_PEDIDO, A.TALON_PED, LEYENDA_1, CAST(B.CANT AS INT) CANT,
            CASE WHEN ESTADO = 5 THEN 'ANULADO' ELSE 'APROBADO' END ESTADO
            FROM GVA21 A
            INNER JOIN
            (
                SELECT ID_GVA21, CAST(SUM(CANT_PEDID) AS FLOAT) CANT
                FROM GVA03
                WHERE TALON_PED IN ($talonariosSQL)
                GROUP BY ID_GVA21
            ) B
            ON A.ID_GVA21 = B.ID_GVA21
            WHERE COD_CLIENT IN ($codigosSQL)
            AND A.TALON_PED IN ($talonariosSQL)
            AND FECHA_PEDI > (GETDATE()-60)
            AND (FECHA_PEDI BETWEEN '$desde' AND '$hasta')
            AND A.COD_CLIENT = '$sucursal'
            ORDER BY 1 desc, 2 desc
        ";

        $this->logDebug('traerHistorialPorSucursal_sql', [
            'codigos_count' => count($sucursalesValidas),
            'talonarios'    => $talonariosSQL,
            'desde'         => $desde,
            'hasta'         => $hasta,
        ]);

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $this->logDebug('traerHistorialPorSucursal_sql_error', ['errors' => $errors]);
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (isset($v['FECHA']) && $v['FECHA'] instanceof DateTime) {
                    $v['FECHA'] = $v['FECHA']->format('Y-m-d');
                }
                $rows[] = $v;
            }

            $this->logDebug('traerHistorialPorSucursal_fin', ['filas' => count($rows)]);

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerHistorialPorSucursal: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae el historial de pedidos para todas las sucursales
     * @param string $desde Fecha desde (formato Y-m-d)
     * @param string $hasta Fecha hasta (formato Y-m-d)
     * @return array Array con los resultados
     */
    public function traerHistorialTodasSucursales($desde, $hasta)
    {
        $this->logDebug('traerHistorialTodasSucursales_inicio', compact('desde', 'hasta'));

        if (!$this->cid_central) {
            $this->logDebug('traerHistorialTodasSucursales_abort', ['motivo' => 'sin_conexion_central']);
            return [];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $this->logDebug('traerHistorialTodasSucursales_abort', ['motivo' => 'fechas_invalidas', 'desde' => $desde, 'hasta' => $hasta]);
            return [];
        }

        $desdeSql = str_replace("'", "''", $desde);
        $hastaSql = str_replace("'", "''", $hasta);

        require_once __DIR__.'/../../../class/sucursal.php';
        $sucursalObj = new Sucursal();
        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $db = GrupoSesion::obtenerBaseDatos();
        $sucursalesValidas = $sucursalObj->obtenerListaCodigosCliente($idFranquicia, $db);
        $mapeoGrupo = $sucursalObj->resolverMapeoCodigosGrupo($db);

        $this->logDebug('traerHistorialTodasSucursales_sucursales', [
            'db'                 => $db,
            'id_franquicia'      => $idFranquicia,
            'cod_client_sesion'  => $_SESSION['codClient'] ?? null,
            'sucursales_validas' => $sucursalesValidas,
            'mapeo_grupo'        => $mapeoGrupo,
            'sucursales_grupo'   => $_SESSION['sucursalesGrupo'] ?? null,
            'sucursales_activas' => $_SESSION['sucursales_activas'] ?? null,
        ]);

        if (empty($sucursalesValidas)) {
            $this->logDebug('traerHistorialTodasSucursales_abort', [
                'motivo' => 'lista_codigos_cliente_vacia',
            ]);
            return [];
        }

        $codigosSQL = "'" . implode("', '", array_map(function ($cod) {
            return str_replace("'", "''", $cod);
        }, $sucursalesValidas)) . "'";

        $talonariosSQL = $this->talonariosSQL();

        $sql = "
            SET DATEFORMAT YMD

            SELECT CAST(FECHA_PEDI AS DATE) FECHA, A.COD_CLIENT, A.NRO_PEDIDO, A.TALON_PED, LEYENDA_1, CAST(B.CANT AS INT) CANT,
            CASE WHEN ESTADO = 5 THEN 'ANULADO' ELSE 'APROBADO' END ESTADO
            FROM GVA21 A
            INNER JOIN
            (
                SELECT ID_GVA21, CAST(SUM(CANT_PEDID) AS FLOAT) CANT
                FROM GVA03
                WHERE TALON_PED IN ($talonariosSQL)
                GROUP BY ID_GVA21
            ) B
            ON A.ID_GVA21 = B.ID_GVA21
            WHERE COD_CLIENT IN ($codigosSQL)
            AND A.TALON_PED IN ($talonariosSQL)
            AND FECHA_PEDI > (GETDATE()-60)
            AND (FECHA_PEDI BETWEEN '$desdeSql' AND '$hastaSql')
            ORDER BY 1 desc, 2 desc
        ";

        $this->logDebug('traerHistorialTodasSucursales_sql', [
            'codigos'     => $sucursalesValidas,
            'talonarios'  => $talonariosSQL,
            'desde'       => $desde,
            'hasta'       => $hasta,
            'sql_preview' => preg_replace('/\s+/', ' ', substr($sql, 0, 500)),
        ]);

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $this->logDebug('traerHistorialTodasSucursales_sql_error', ['errors' => $errors]);
                return [];
            }

            $rows = array();
            while ($v = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (isset($v['FECHA']) && $v['FECHA'] instanceof DateTime) {
                    $v['FECHA'] = $v['FECHA']->format('Y-m-d');
                }
                $rows[] = $v;
            }

            $this->logDebug('traerHistorialTodasSucursales_fin', ['filas' => count($rows)]);

            return $rows;
        } catch (\Throwable $th) {
            error_log("Error en traerHistorialTodasSucursales: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    /**
     * Trae el detalle de un pedido específico
     * @param string $pedido Número de pedido
     * @param string $sucursal Código de la sucursal (ej: FRBAUD, FRORCE, etc.)
     * @param string|int|null $talon Talonario del pedido (opcional; el mismo número puede repetirse entre talonarios)
     * @return array Array con los resultados del detalle del pedido
     */
    public function traerDetallePedido($pedido, $sucursal, $talon = null)
    {
        if (!$this->cid_central) {
            return [];
        }

        require_once __DIR__.'/../../../class/sucursal.php';
        $sucursalObj = new Sucursal();
        $idFranquicia = isset($_SESSION['ID_FRANQUICIA']) ? $_SESSION['ID_FRANQUICIA'] : null;
        $sucursalesValidas = $sucursalObj->obtenerListaCodigosCliente($idFranquicia, GrupoSesion::obtenerBaseDatos());

        if (!in_array($sucursal, $sucursalesValidas)) {
            return [];
        }

        // Escapar valores para SQL
        $pedido = str_replace("'", "''", $pedido);
        $sucursal = str_replace("'", "''", $sucursal);

        // Si viene el talonario se acota a ese; si no, a los talonarios habilitados
        $talonInt = (int) $talon;
        $filtroTalon = in_array($talonInt, self::talonariosPermitidos(), true)
            ? "AND A.TALON_PED = $talonInt"
            : 'AND A.TALON_PED IN (' . $this->talonariosSQL() . ')';

        $sql = "
            SET DATEFORMAT YMD

            SELECT CAST(A.FECHA_PEDI AS DATE) FECHA, A.TALON_PED, B.COD_ARTICU, C.DESCRIPCIO, CAST(B.CANT_PEDID AS FLOAT) CANT
            FROM GVA03 B
            INNER JOIN GVA21 A
            ON A.ID_GVA21 = B.ID_GVA21
            INNER JOIN STA11 C
            ON B.COD_ARTICU = C.COD_ARTICU
            WHERE A.NRO_PEDIDO = '$pedido'
            AND A.COD_CLIENT = '$sucursal'
            $filtroTalon
        ";

        ini_set('max_execution_time', 300);

        try {
            $stmt = sqlsrv_query($this->cid_central, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error en traerDetallePedido: " . print_r($errors, true));
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
            error_log("Error en traerDetallePedido: " . $th->getMessage());
            return [];
        } finally {
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }
}
?>

