<?php

if (class_exists('CrearPedido')) {
    return;
}

/**
 * Creación unificada de pedidos para todas las variantes del ecosistema XL.
 *
 * Variantes soportadas:
 *  - sucursal   (local propio, numsuc <= 100)  → FU_PEDIDOS + kits en SP
 *  - franquicia (COD_CLIENT FR*, numsuc > 100) → FU_PEDIDOS + validación crédito/stock
 *  - cliente    (mayoristas, exterior, etc.)   → FU_PEDIDOS + validación crédito/stock
 *  - grupo      (grupo empresario multi-suc.)  → GVA21/GVA03 manual + explosión kits PHP
 *
 * Tipos: GENERAL, ACCESORIOS (+ OUTLET, DESABASTECIMIENTO en motor manual)
 *
 * Ejemplo sucursal/franquicia/cliente:
 *   $cp = new CrearPedido('central');
 *   $result = $cp->crear([
 *       'contexto'    => CrearPedido::CONTEXTO_FRANQUICIA,
 *       'tipo'        => CrearPedido::TIPO_GENERAL,
 *       'num_suc'     => 812,
 *       'cod_client'  => 'FRBAUD',
 *       'depo'        => '01',
 *       'articulos'   => [
 *           ['cod_articu' => 'ABC123', 'cantidad' => 2, 'rubro' => 'CARTERAS', 'stock' => 10],
 *       ],
 *   ]);
 *
 * Ejemplo grupo empresario (varios clientes en un submit):
 *   $resultados = $cp->crearMultiplesGrupo($pedidosPorCliente, [
 *       'tipo' => CrearPedido::TIPO_GENERAL,
 *       'depo' => '01',
 *   ]);
 */
class CrearPedido
{
    const CONTEXTO_SUCURSAL   = 'sucursal';
    const CONTEXTO_FRANQUICIA = 'franquicia';
    const CONTEXTO_CLIENTE    = 'cliente';
    const CONTEXTO_GRUPO      = 'grupo';

    const TIPO_GENERAL          = 'GENERAL';
    const TIPO_ACCESORIOS       = 'ACCESORIOS';
    const TIPO_OUTLET           = 'OUTLET';
    const TIPO_DESABASTECIMIENTO = 'DESABASTECIMIENTO';

    const MOTOR_FU_PEDIDOS = 'fu_pedidos';
    const MOTOR_MANUAL     = 'manual';

    const TALON_PED_DEFAULT = 97;
    const TALON_DETALLE     = 120;

    /** @var Conexion */
    private $conexion;

    /** @var resource|false */
    private $cid;

    /** @var string */
    private $db;

    /** @var string|null */
    private $logFile;

    public function __construct(string $db = 'central', ?string $logFile = null)
    {
        require_once __DIR__ . '/conexion.php';
        require_once __DIR__ . '/sqlsrv.php';

        $this->db = $db;
        $this->conexion = new Conexion();
        $this->cid = $this->conexion->conectar($db);
        $this->logFile = $logFile;
    }

    /**
     * @return resource|false
     */
    public function getConexion()
    {
        return $this->cid;
    }

    /**
     * Detecta contexto a partir de sucursal y código de cliente.
     */
    public static function detectarContexto(int $numSuc, string $codClient): string
    {
        if ($numSuc <= 100) {
            return self::CONTEXTO_SUCURSAL;
        }
        if (strtoupper(substr($codClient, 0, 2)) === 'FR') {
            return self::CONTEXTO_FRANQUICIA;
        }
        return self::CONTEXTO_CLIENTE;
    }

    /**
     * Convierte la matriz legacy del front (array indexado) a líneas normalizadas.
     *
     * @param array<int, array<int, mixed>> $matriz
     * @return array<int, array{cod_articu: string, cantidad: int, rubro: string, stock: float}>
     */
    public static function normalizarDesdeMatriz(
        array $matriz,
        int $indiceCantidad = 8,
        int $indiceRubro = 9,
        int $indiceStock = 10
    ): array {
        $articulos = [];
        foreach ($matriz as $fila) {
            if (empty($fila[1])) {
                continue;
            }
            $cantidad = isset($fila[$indiceCantidad]) ? (int) $fila[$indiceCantidad] : 0;
            if ($cantidad <= 0) {
                continue;
            }
            $articulos[] = [
                'cod_articu' => trim((string) $fila[1]),
                'cantidad'   => $cantidad,
                'rubro'      => trim((string) ($fila[$indiceRubro] ?? '')),
                'stock'      => (float) ($fila[$indiceStock] ?? 0),
            ];
        }
        return $articulos;
    }

    /**
     * Arma pedidos por cliente desde POST de grupo empresario.
     *
     * @param array<string, mixed> $post
     * @param array<string, array{codClient?: string}> $sucursalesInfo clave = num_suc
     * @return array<int, array{cod_client: string, num_suc: string, articulos: array}>
     */
    public static function normalizarDesdePostGrupo(array $post, array $sucursalesInfo): array
    {
        $codArts = $post['codArt'] ?? [];
        $rubros  = $post['rubro'] ?? [];
        $stocks  = $post['stock'] ?? [];
        $pedidos = [];

        foreach ($sucursalesInfo as $numSuc => $info) {
            $campo = 'cantPed_' . $numSuc;
            $cantidades = $post[$campo] ?? [];
            $articulos = [];

            foreach ($codArts as $clave => $codArticu) {
                $cantidad = isset($cantidades[$clave]) ? max(0, (int) $cantidades[$clave]) : 0;
                if ($cantidad <= 0) {
                    continue;
                }
                $articulos[] = [
                    'cod_articu' => trim((string) $codArticu),
                    'cantidad'   => $cantidad,
                    'rubro'      => trim((string) ($rubros[$clave] ?? '')),
                    'stock'      => (float) ($stocks[$clave] ?? 0),
                ];
            }

            if (empty($articulos)) {
                continue;
            }

            $codClient = $info['codClient'] ?? '';
            if ($codClient === '') {
                continue;
            }

            $pedidos[] = [
                'cod_client' => $codClient,
                'num_suc'    => (string) $numSuc,
                'articulos'  => $articulos,
            ];
        }

        return $pedidos;
    }

    /**
     * Valida cantidades enteras >= 0 (formulario grupo).
     */
    public static function validarCantidadesEnteras(array $cantidades): bool
    {
        foreach ($cantidades as $cantidad) {
            if (!is_numeric($cantidad) || $cantidad < 0 || floor($cantidad) != $cantidad) {
                return false;
            }
        }
        return true;
    }

    /**
     * Crea un pedido según contexto y motor.
     *
     * @param array{
     *   contexto?: string,
     *   tipo: string,
     *   num_suc: int|string,
     *   cod_client: string,
     *   depo?: string,
     *   talon_ped?: int,
     *   articulos: array,
     *   motor?: string,
     *   validar_credito?: bool,
     *   validar_stock?: bool,
     *   comprometer_stock?: bool
     * } $config
     * @return array{success: bool, nro_pedido?: string, cod_client?: string, motor?: string, error?: string, mensaje?: string, detalles?: mixed}
     */
    public function crear(array $config): array
    {
        if ($this->cid === false) {
            return $this->error('conexion', 'No se pudo conectar a la base central.');
        }

        $tipo       = strtoupper(trim($config['tipo'] ?? ''));
        $numSuc     = (int) ($config['num_suc'] ?? 0);
        $codClient  = trim($config['cod_client'] ?? '');
        $depo       = $config['depo'] ?? '01';
        $talonPed   = (int) ($config['talon_ped'] ?? self::TALON_PED_DEFAULT);
        $articulos  = $config['articulos'] ?? [];
        $contexto   = $config['contexto'] ?? self::detectarContexto($numSuc, $codClient);
        $motor      = $this->resolverMotor($contexto, $config['motor'] ?? null);

        if ($codClient === '' || empty($articulos)) {
            return $this->error('datos', 'Faltan código de cliente o artículos.');
        }

        if (!$this->validarTipo($tipo, $motor)) {
            return $this->error('tipo', 'Tipo de pedido inválido: ' . $tipo);
        }

        $validarCredito = $config['validar_credito'] ?? ($numSuc > 100);
        $validarStock   = $config['validar_stock'] ?? ($numSuc > 100);
        $comprometer    = $config['comprometer_stock'] ?? true;

        if ($validarCredito && $motor === self::MOTOR_FU_PEDIDOS) {
            $credito = $this->validarCredito($codClient, $articulos);
            if (!$credito['success']) {
                return $credito;
            }
        }

        if ($validarStock && $motor === self::MOTOR_FU_PEDIDOS) {
            $stock = $this->validarStock($numSuc, $codClient, $tipo, $articulos);
            if (!$stock['success']) {
                return $stock;
            }
        }

        if ($motor === self::MOTOR_FU_PEDIDOS) {
            return $this->crearViaFuPedidos($numSuc, $codClient, $tipo, $depo, $talonPed, $articulos);
        }

        return $this->crearViaManual($codClient, $tipo, $depo, $articulos, $comprometer);
    }

    /**
     * Crea varios pedidos (grupo empresario: un pedido por sucursal/cliente).
     *
     * @param array<int, array{cod_client: string, num_suc?: string, articulos: array}> $pedidos
     * @param array{tipo: string, depo?: string, comprometer_stock?: bool} $configBase
     * @return array{success: bool, procesados: int, resultados: array, error?: string, mensaje?: string}
     */
    public function crearMultiplesGrupo(array $pedidos, array $configBase): array
    {
        if (empty($pedidos)) {
            return [
                'success'    => false,
                'procesados' => 0,
                'resultados' => [],
                'error'      => 'sin_articulos',
                'mensaje'    => 'No hay cantidades en ninguna sucursal.',
            ];
        }

        $resultados = [];
        foreach ($pedidos as $pedido) {
            $config = array_merge($configBase, [
                'contexto'   => self::CONTEXTO_GRUPO,
                'motor'      => self::MOTOR_MANUAL,
                'cod_client' => $pedido['cod_client'],
                'num_suc'    => $pedido['num_suc'] ?? 0,
                'articulos'  => $pedido['articulos'],
            ]);
            $resultados[] = $this->crear($config);
        }

        $procesados = count(array_filter($resultados, function ($r) {
            return !empty($r['success']);
        }));

        return [
            'success'    => $procesados > 0,
            'procesados' => $procesados,
            'resultados' => $resultados,
            'mensaje'    => $procesados > 0
                ? "Se procesaron $procesados pedido(s)."
                : 'No se pudo registrar ningún pedido.',
        ];
    }

    private function resolverMotor(string $contexto, ?string $motor): string
    {
        if ($motor !== null && in_array($motor, [self::MOTOR_FU_PEDIDOS, self::MOTOR_MANUAL], true)) {
            return $motor;
        }
        return $contexto === self::CONTEXTO_GRUPO ? self::MOTOR_MANUAL : self::MOTOR_FU_PEDIDOS;
    }

    private function validarTipo(string $tipo, string $motor): bool
    {
        $tiposFu = [self::TIPO_GENERAL, self::TIPO_ACCESORIOS, self::TIPO_OUTLET];
        $tiposManual = array_merge($tiposFu, [self::TIPO_DESABASTECIMIENTO]);
        $permitidos = $motor === self::MOTOR_MANUAL ? $tiposManual : $tiposFu;
        return in_array($tipo, $permitidos, true);
    }

    /**
     * @param array<int, array{cod_articu: string, cantidad: int, rubro?: string, stock?: float}> $articulos
     */
    private function crearViaFuPedidos(
        int $numSuc,
        string $codClient,
        string $tipo,
        string $depo,
        int $talonPed,
        array $articulos
    ): array {
        $stringSql = $this->construirStringFuPedidos($articulos);
        $query = "EXEC FU_PEDIDOS $numSuc, ?, ?, ?, $talonPed, ?";

        $this->log("FU_PEDIDOS suc:$numSuc client:$codClient tipo:$tipo");

        $maxIdAntes = 0;
        $stmtMax = sqlsrv_query(
            $this->cid,
            'SELECT ISNULL(MAX(ID_GVA21), 0) AS MAX_ID FROM GVA21 WHERE COD_CLIENT = ?',
            [$codClient]
        );
        if ($stmtMax) {
            $rowMax = sqlsrv_fetch_array($stmtMax, SQLSRV_FETCH_ASSOC);
            $maxIdAntes = (int) ($rowMax['MAX_ID'] ?? 0);
            sqlsrv_free_stmt($stmtMax);
        }

        $stmt = sqlsrv_query($this->cid, $query, [$codClient, $tipo, $depo, $stringSql]);
        if ($stmt === false) {
            return $this->error('sp', 'Error al ejecutar FU_PEDIDOS.', sqlsrv_errors());
        }

        do {
            $errs = sqlsrv_errors(SQLSRV_ERR_ERRORS);
            if ($errs) {
                $this->log('FU_PEDIDOS errors: ' . print_r($errs, true));
            }
        } while (sqlsrv_next_result($stmt) !== false);
        sqlsrv_free_stmt($stmt);

        $nroPedido = $this->obtenerNroPedidoReciente($codClient, $maxIdAntes);
        if ($nroPedido === null) {
            return $this->error('sp_silencioso', 'El pedido no se registró en la base de datos.');
        }

        return [
            'success'    => true,
            'nro_pedido' => $nroPedido,
            'cod_client' => $codClient,
            'motor'      => self::MOTOR_FU_PEDIDOS,
        ];
    }

    /**
     * Inserción directa GVA21/GVA03 con explosión de kits (STA03).
     *
     * @param array<int, array{cod_articu: string, cantidad: int, rubro?: string, stock?: float}> $articulos
     */
    private function crearViaManual(
        string $codClient,
        string $tipo,
        string $depo,
        array $articulos,
        bool $comprometerStock
    ): array {
        $fecha = date('Y-m-d');
        $numPedData = $this->obtenerProximoNumeroPedido();
        if ($numPedData === null) {
            return $this->error('numeracion', 'No se pudo obtener el próximo número de pedido.');
        }

        $numPed = $numPedData['num_ped'];

        $sqlEnc = "
        SET DATEFORMAT YMD
        INSERT INTO GVA21
        (
        CIRCUITO, COD_CLIENT, COD_SUCURS,
        COD_TRANSP, COD_VENDED, COMP_STK,
        COND_VTA, COTIZ, ESTADO,
        EXPORTADO, FECHA_APRU, FECHA_ENTR,
        FECHA_PEDI, LEYENDA_1, MON_CTE,
        N_LISTA, N_REMITO, NRO_PEDIDO,
        NRO_SUCURS, ORIGEN, PORC_DESC,
        REVISO_FAC, REVISO_PRE, REVISO_STK,
        TALONARIO, TALON_PED, TOTAL_PEDI,
        TIPO_ASIEN, ID_ASIENTO_MODELO_GV, TAL_PE_ORI,
        FECHA_INGRESO, FECHA_ULTIMA_MODIFICACION, ID_DIRECCION_ENTREGA,
        ES_PEDIDO_WEB, FECHA_O_COMP,  TOTAL_DESC_TIENDA, PORCEN_DESC_TIENDA,
        HORA_INGRESO
        )
        VALUES
        (
        1, ?, ?,
        (SELECT COD_TRANSP FROM GVA14 WHERE COD_CLIENT = ?), 'ZZ', 1,
        (SELECT COND_VTA FROM GVA14 WHERE COD_CLIENT = ?), 1, 2,
        0, '1800-01-01', '1800-01-01',
        ?, ?, 1,
        (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = ?), ' 000000000000', ' ' + ?,
        0, 'E', (SELECT PORC_DESC FROM GVA14 WHERE COD_CLIENT = ?),
        'A', 'A', 'A',
        0, ?, 0,
        '', 3, 0,
        '1800-01-01', '1800-01-01', (SELECT ID_DIRECCION_ENTREGA FROM DIRECCION_ENTREGA WHERE COD_CLIENTE = ?),
        0, '1800-01-01', 0, 0,
        (SELECT LEFT((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 2)+SUBSTRING((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 4, 2)+RIGHT((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 2))
        )
        ";

        Sqlsrv::ejecutar($this->cid, $sqlEnc, [
            $codClient, $depo, $codClient, $codClient,
            $fecha, 'PEDIDO ' . $tipo, $codClient, $numPed, $codClient,
            self::TALON_DETALLE, $codClient,
        ]);

        $nroRenglon = 1;
        foreach ($articulos as $item) {
            $codArticu = $item['cod_articu'];
            $cantArt   = (int) $item['cantidad'];
            $rubro     = $item['rubro'] ?? '';
            $stock     = (float) ($item['stock'] ?? 0);

            if ($cantArt <= 0) {
                continue;
            }

            $cantArt = $this->aplicarLimitesCantidad($cantArt, $rubro, $stock);
            if ($cantArt <= 0) {
                continue;
            }

            $esKit = Sqlsrv::tieneFilas(
                Sqlsrv::ejecutar($this->cid, 'SELECT 1 FROM STA03 WHERE COD_ARTICU = ?', [$codArticu])
            );

            if (!$esKit) {
                $this->insertarRenglonSimple($codClient, $codArticu, $cantArt, $numPed, $nroRenglon);
                $nroRenglon++;
                if ($comprometerStock && function_exists('comp_stock')) {
                    comp_stock($cantArt, $codArticu, $depo);
                }
            } else {
                $nroRenglon = $this->insertarRenglonKit(
                    $codClient, $codArticu, $cantArt, $numPed, $nroRenglon, $depo, $comprometerStock, $rubro
                );
            }
        }

        return [
            'success'    => true,
            'nro_pedido' => trim($numPed),
            'cod_client' => $codClient,
            'motor'      => self::MOTOR_MANUAL,
        ];
    }

    private function insertarRenglonSimple(
        string $codClient,
        string $codArticu,
        int $cantArt,
        string $numPed,
        int $nroRenglon
    ): void {
        $sql = "
        INSERT INTO GVA03
        (
        CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE,
        PRECIO, TALON_PED,
        CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
        PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM
        )
        VALUES
        (
        1, ?, ?, ?, ?, ?, ?, 0, ?, ' ' + ?, 0, 0,
        (SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = ? AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = ?)), ?,
        0, 0, 0, 0, 0, 0, 7, 7, 'V', 0,
        0, 0, 0, 0, 0, 0, 0
        )
        ";
        Sqlsrv::ejecutar($this->cid, $sql, [
            $cantArt, $cantArt, $cantArt, $cantArt, $cantArt,
            $codArticu, $nroRenglon, $numPed, $codArticu, $codClient, self::TALON_DETALLE,
        ]);
    }

    private function insertarRenglonKit(
        string $codClient,
        string $codArticuKit,
        int $cantArt,
        string $numPed,
        int $nroRenglon,
        string $depo,
        bool $comprometerStock,
        string $rubro
    ): int {
        if ($rubro !== 'PACKAGING' && $cantArt > 15) {
            $cantArt = 15;
        }

        $sqlKitPadre = "
        INSERT INTO GVA03
        (
        CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE,
        PRECIO, TALON_PED,
        CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
        PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM, COD_ARTICU_KIT
        )
        VALUES
        (
        1, ?, ?, ?, ?, ?, ?, 0, ?, ' ' + ?, 0, 0,
        (SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = ? AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = ?)), ?,
        0, 0, 0, 0, 0, 0, 7, 7, 'P', 0,
        1, 0, 1, 0, 0, 0, 0, ?
        )
        ";
        Sqlsrv::ejecutar($this->cid, $sqlKitPadre, [
            $cantArt, $cantArt, $cantArt, $cantArt, $cantArt,
            $codArticuKit, $nroRenglon, $numPed, $codArticuKit, $codClient, self::TALON_DETALLE, $codArticuKit,
        ]);

        $ultRenglon = $nroRenglon;
        $nroRenglon++;

        $resultExplota = Sqlsrv::ejecutar(
            $this->cid,
            'SELECT COD_INSUMO, CANTIDAD FROM STA03 WHERE COD_ARTICU = ?',
            [$codArticuKit]
        );

        while ($v = Sqlsrv::fetch($resultExplota)) {
            $codInsumo  = $v['COD_INSUMO'];
            $cantInsumo = (float) $v['CANTIDAD'];
            $cantArt2   = (int) ($cantInsumo * $cantArt);

            $sqlInsumo = "
            INSERT INTO GVA03
            (
            CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE,
            PRECIO, TALON_PED,
            CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
            PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM, COD_ARTICU_KIT
            )
            VALUES
            (
            1, ?, ?, ?, ?, ?, ?, 0, ?, ' ' + ?, 0, 0,
            (SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = ? AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = ?)), ?,
            0, 0, 0, 0, 0, 0, 7, 7, 'P', ?,
            0, 0, 1, 0, 0, 0, 0, ?
            )
            ";
            Sqlsrv::ejecutar($this->cid, $sqlInsumo, [
                $cantArt2, $cantArt2, $cantArt2, $cantArt2, $cantArt2,
                $codInsumo, $nroRenglon, $numPed, $codInsumo, $codClient, self::TALON_DETALLE, $ultRenglon, $codArticuKit,
            ]);
            $nroRenglon++;

            if ($comprometerStock && function_exists('comp_stock')) {
                comp_stock($cantInsumo, $codInsumo, $depo);
            }
        }

        return $nroRenglon;
    }

    private function aplicarLimitesCantidad(int $cantArt, string $rubro, float $stock): int
    {
        if ($rubro !== 'PACKAGING' && $cantArt > 15) {
            $cantArt = 15;
        } elseif ($rubro === 'PACKAGING' && $cantArt > 100) {
            $cantArt = 100;
        }
        if ($stock > 0 && $cantArt > $stock) {
            $cantArt = (int) $stock;
        }
        return max(0, $cantArt);
    }

    /**
     * @return array{num_ped: string, prox_enc: string}|null
     */
    private function obtenerProximoNumeroPedido(): ?array
    {
        $resultProx = Sqlsrv::ejecutar(
            $this->cid,
            "SET DATEFORMAT YMD SELECT PROXIMO, SUCURSAL FROM GVA43 WHERE TALONARIO = '97'"
        );
        $prox = null;
        $ptoVta = null;
        while ($v = Sqlsrv::fetch($resultProx)) {
            $prox = $v['PROXIMO'];
            $ptoVta = $v['SUCURSAL'];
        }
        if ($prox === null) {
            return null;
        }

        $resultProxDes = Sqlsrv::ejecutar(
            $this->cid,
            'SET DATEFORMAT YMD SELECT DBO.Fn_obtenerproximonumero(?) proxDes',
            [$prox]
        );
        $proxDes = null;
        while ($v = Sqlsrv::fetch($resultProxDes)) {
            $proxDes = $v['proxDes'];
        }
        if ($proxDes === null) {
            return null;
        }

        $numPed  = (string) $ptoVta . (string) $proxDes;
        $proxPed = substr((string) ('0000000') . (string) ($proxDes + 1), -8);

        $resultEnc = Sqlsrv::ejecutar(
            $this->cid,
            'SET DATEFORMAT YMD SELECT DBO.Fn_encryptarproximonumero(?) proxEnc',
            [$proxPed]
        );
        $proxEnc = null;
        while ($v = Sqlsrv::fetch($resultEnc)) {
            $proxEnc = $v['proxEnc'];
        }

        Sqlsrv::ejecutar(
            $this->cid,
            "SET DATEFORMAT YMD UPDATE GVA43 SET PROXIMO = ? WHERE TALONARIO = '97'",
            [$proxEnc]
        );

        return ['num_ped' => $numPed, 'prox_enc' => $proxEnc];
    }

    private function obtenerNroPedidoReciente(string $codClient, int $maxIdAntes): ?string
    {
        $sql = 'SELECT TOP 1 NRO_PEDIDO FROM GVA21 WHERE COD_CLIENT = ? AND ID_GVA21 > ? ORDER BY ID_GVA21 DESC';

        for ($intento = 1; $intento <= 3; $intento++) {
            if ($intento > 1) {
                usleep(400000);
            }
            $stmt = sqlsrv_query($this->cid, $sql, [$codClient, $maxIdAntes]);
            if ($stmt === false) {
                continue;
            }
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
            if ($row && !empty($row['NRO_PEDIDO'])) {
                return trim((string) $row['NRO_PEDIDO']);
            }
        }
        return null;
    }

    /**
     * @param array<int, array{cod_articu: string, cantidad: int, rubro?: string, stock?: float}> $articulos
     */
    private function construirStringFuPedidos(array $articulos): string
    {
        $items = [];
        foreach ($articulos as $a) {
            $items[] = sprintf(
                '"%s","%d","%s","%d"',
                str_replace('"', '', $a['cod_articu']),
                (int) $a['cantidad'],
                str_replace('"', '', $a['rubro'] ?? ''),
                (int) ($a['stock'] ?? 0)
            );
        }
        return '(' . implode(';', $items) . ')';
    }

    /**
     * @param array<int, array{cod_articu: string, cantidad: int}> $articulos
     */
    private function validarCredito(string $codClient, array $articulos): array
    {
        $stmtGrupo = sqlsrv_query(
            $this->cid,
            'SELECT GRUPO_EMPR, NRO_LISTA FROM GVA14 WHERE COD_CLIENT = ?',
            [$codClient]
        );
        if ($stmtGrupo === false) {
            return $this->error('credito', 'Error al consultar datos del cliente.');
        }
        $datos = sqlsrv_fetch_array($stmtGrupo, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtGrupo);
        if (!$datos) {
            return $this->error('credito', 'No se encontraron datos para el cliente.');
        }

        $claveCredito = trim($datos['GRUPO_EMPR'] ?? '') !== '' ? trim($datos['GRUPO_EMPR']) : $codClient;
        $numeroLista  = $datos['NRO_LISTA'] ?? 1;

        $stmtSP = sqlsrv_query(
            $this->cid,
            'EXEC RO_PPP_FRANQ_PEDIDOS_GREAT @COD_CLIENTE = ?',
            [$claveCredito]
        );
        if ($stmtSP === false) {
            return $this->error('credito', 'Error al ejecutar SP de crédito.');
        }
        $rowSP = sqlsrv_fetch_array($stmtSP, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtSP);

        $totalDisponible = $rowSP ? (float) $rowSP['TOTAL_DISPONIBLE'] : PHP_FLOAT_MAX;

        $montoPedido = 0.0;
        foreach ($articulos as $a) {
            $stmtPrecio = sqlsrv_query(
                $this->cid,
                'SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = ? AND NRO_DE_LIS = ?',
                [$a['cod_articu'], $numeroLista]
            );
            if ($stmtPrecio) {
                $precioRow = sqlsrv_fetch_array($stmtPrecio, SQLSRV_FETCH_ASSOC);
                $montoPedido += ((float) ($precioRow['PRECIO'] ?? 0)) * (int) $a['cantidad'];
                sqlsrv_free_stmt($stmtPrecio);
            }
        }

        if ($totalDisponible - $montoPedido < 0) {
            return [
                'success'  => false,
                'error'    => 'credito_insuficiente',
                'mensaje'  => sprintf(
                    'Crédito insuficiente. Disponible: $%s. El pedido requiere: $%s',
                    number_format($totalDisponible, 2, ',', '.'),
                    number_format($montoPedido, 2, ',', '.')
                ),
                'detalles' => [
                    'credito_disponible' => round($totalDisponible, 2),
                    'pedido_monto'       => round($montoPedido, 2),
                ],
            ];
        }

        return ['success' => true];
    }

    /**
     * @param array<int, array{cod_articu: string, cantidad: int}> $articulos
     */
    private function validarStock(int $numSuc, string $codClient, string $tipo, array $articulos): array
    {
        $tipoSpMap = [
            self::TIPO_GENERAL    => 1,
            self::TIPO_ACCESORIOS => 2,
            self::TIPO_OUTLET     => 3,
        ];
        $tipoSp = $tipoSpMap[$tipo] ?? 1;

        $solicitudes = [];
        foreach ($articulos as $a) {
            $cod = $a['cod_articu'];
            $solicitudes[$cod] = ($solicitudes[$cod] ?? 0) + (int) $a['cantidad'];
        }

        $sinStock = [];
        foreach ($solicitudes as $codigo => $cantidad) {
            $stmt = sqlsrv_query(
                $this->cid,
                'EXEC SJ_STOCK_DISPONIBLE_VALIDACION ?, ?, ?, ?',
                [$codigo, $numSuc, $codClient, $tipoSp]
            );
            if ($stmt === false) {
                return $this->error('stock', "Error al consultar stock para $codigo.");
            }
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
            $disponible = (float) ($row['CANT_STOCK'] ?? 0);

            if ($cantidad > $disponible) {
                $sinStock[] = [
                    'articulo'            => $codigo,
                    'stock_disponible'    => (int) $disponible,
                    'cantidad_solicitada' => (int) $cantidad,
                ];
            }
        }

        if (!empty($sinStock)) {
            $n = count($sinStock);
            return [
                'success'               => false,
                'error'                 => 'stock_insuficiente',
                'mensaje'               => sprintf(
                    '%d artículo%s sin stock suficiente.',
                    $n,
                    $n === 1 ? '' : 's'
                ),
                'articulos_sin_stock'   => $sinStock,
            ];
        }

        return ['success' => true];
    }

    private function error(string $codigo, string $mensaje, $detalles = null): array
    {
        $this->log("Error [$codigo]: $mensaje");
        $out = [
            'success' => false,
            'error'   => $codigo,
            'mensaje' => $mensaje,
        ];
        if ($detalles !== null) {
            $out['detalles'] = $detalles;
        }
        return $out;
    }

    private function log(string $message): void
    {
        if ($this->logFile === null) {
            return;
        }
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        error_log($message . PHP_EOL, 3, $this->logFile);
    }
}
