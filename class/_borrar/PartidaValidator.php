<?php

require_once __DIR__ . '/MultiConexion.php';

/**
 * Servicio para validar artículos que requieren partidas y obtener su saldo disponible.
 */
class PartidaValidator
{
    /**
     * @var MultiConexion
     */
    private $multiConexion;

    /**
     * @var mixed|null
     */
    private $conexionActual;

    /**
     * @var string
     */
    private $paisActual;

    public function __construct(?string $pais = null)
    {
        $this->multiConexion = new MultiConexion();
        $this->establecerPais($pais);
    }

    /**
     * Establece el país (AR, UY, etc.) a utilizar para las consultas.
     *
     * @param string|null $pais
     * @return void
     */
    public function establecerPais(?string $pais): void
    {
        $paisNormalizado = $this->normalizarPais($pais ?? $this->detectarPaisPorDefecto());

        if ($paisNormalizado === null) {
            throw new InvalidArgumentException('No se pudo determinar el país para la validación de partidas.');
        }

        $this->paisActual = $paisNormalizado;
        $this->conexionActual = $this->multiConexion->obtenerConexionPorPais($paisNormalizado);

        if ($this->conexionActual === false) {
            throw new RuntimeException(sprintf('No fue posible establecer una conexión con la base de datos para %s.', $paisNormalizado));
        }
    }

    /**
     * Indica si el artículo utiliza control de partidas.
     *
     * @param string $articulo
     * @return bool
     */
    public function usaSaldoPartidas(string $articulo): bool
    {
        $articulo = trim($articulo);

        if ($articulo === '') {
            return false;
        }

        $sql = "SELECT USA_PARTID FROM STA11 WHERE COD_ARTICU = ?";
        $stmt = sqlsrv_query($this->conexionActual, $sql, [$articulo]);

        if ($stmt === false) {
            throw new RuntimeException('Error al consultar si el artículo utiliza partidas.');
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($row === null || $row === false) {
            return false;
        }

        return isset($row['USA_PARTID']) && (string)$row['USA_PARTID'] === '1';
    }

    /**
     * Calcula el saldo disponible por partidas para un artículo en un depósito concreto.
     *
     * @param string $codArticulo
     * @param float|int $cantidadRequerida
     * @param string|null $deposito
     * @return array{suficiente: bool, saldo: float, partidas: array<int, array<string, mixed>>}
     */
    public function calcularSaldoPartidas(string $codArticulo, $cantidadRequerida, ?string $deposito = null): array
    {
        $codArticulo = trim($codArticulo);
        $cantidad = (float)$cantidadRequerida;
        $deposito = $this->normalizarDeposito($deposito);
    
        if ($codArticulo === '') {
            return [
                'suficiente' => false,
                'saldo' => 0.0,
                'partidas' => [],
            ];
        }


        $parametros = [$codArticulo];
        $sql = "SELECT N_PARTIDA, CANTIDAD, COD_DEPOSI, FECHA, FECHA_VTO
                FROM STA10
                WHERE COD_ARTICU = ?";

        if ($deposito !== null) {
            $sql .= " AND COD_DEPOSI = ?";
            $parametros[] = $deposito;
        }

        $sql .= " ORDER BY FECHA_VTO ASC, N_PARTIDA ASC";

        $stmt = sqlsrv_query($this->conexionActual, $sql, $parametros);

        if ($stmt === false) {
            throw new RuntimeException('Error al obtener el saldo de partidas.');
        }

        $partidas = [];
        $saldoTotal = 0.0;


        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $cantidadPartida = isset($row['CANTIDAD']) ? (float)$row['CANTIDAD'] : 0.0;
            $saldoTotal += $cantidadPartida;

            $partidas[] = [
                'partida' => $row['N_PARTIDA'] ?? '',
                'cantidad' => $cantidadPartida,
                'deposito' => $row['COD_DEPOSI'] ?? $deposito,
                'fecha_ingreso' => isset($row['FECHA']) ? $this->formatearFecha($row['FECHA']) : null,
                'fecha_vencimiento' => isset($row['FECHA_VTO']) ? $this->formatearFecha($row['FECHA_VTO']) : null,
            ];
        }

        return [
            'suficiente' => ($saldoTotal - $cantidad) >= 0,
            'saldo' => $saldoTotal,
            'partidas' => $partidas,
        ];
    }

    /**
     * Obtiene el país actualmente configurado.
     */
    public function obtenerPaisActual(): string
    {
        return $this->paisActual;
    }

    private function detectarPaisPorDefecto(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['usuarioUy']) && $_SESSION['usuarioUy'] === '1') {
            return 'UY';
        }

        return 'AR';
    }

    private function normalizarPais(?string $pais): ?string
    {
        if ($pais === null) {
            return null;
        }

        $pais = strtoupper(trim($pais));

        if ($pais === '') {
            return null;
        }

        return substr($pais, 0, 2);
    }

    private function normalizarDeposito(?string $deposito): ?string
    {
        if ($deposito === null) {
            return null;
        }

        $deposito = trim($deposito);

        if ($deposito === '') {
            return null;
        }

        if (ctype_digit($deposito)) {
            if (strlen($deposito) === 1) {
                return '0' . $deposito;
            }

            return $deposito;
        }

        return $deposito;
    }

    private function formatearFecha($fecha): ?string
    {
        if ($fecha instanceof DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        if ($fecha instanceof DateTime) {
            return $fecha->format('Y-m-d');
        }

        if (is_object($fecha) && isset($fecha->date)) {
            try {
                $dt = new DateTime($fecha->date);
                return $dt->format('Y-m-d');
            } catch (Throwable $th) {
                return null;
            }
        }

        return null;
    }
}


