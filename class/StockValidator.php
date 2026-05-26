<?php

require_once __DIR__ . '/MultiConexion.php';

/**
 * Servicio para validar depósitos y stock disponible según país.
 */
class StockValidator
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
     * Define el país que determina la conexión utilizada.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function establecerPais(?string $pais): void
    {
        $paisNormalizado = $this->normalizarPais($pais ?? $this->detectarPaisPorDefecto());

        if ($paisNormalizado === null) {
            throw new InvalidArgumentException('No se pudo determinar el país para la validación de stock.');
        }

        $this->paisActual = $paisNormalizado;
        $this->conexionActual = $this->multiConexion->obtenerConexionPorPais($paisNormalizado);

        if ($this->conexionActual === false) {
            throw new RuntimeException(sprintf('No fue posible establecer una conexión con la base de datos para %s.', $paisNormalizado));
        }
    }

    /**
     * Verifica si el depósito existe y está habilitado.
     */
    public function depositoExiste(?string $deposito): bool
    {
        $deposito = $this->normalizarDeposito($deposito);

        if ($deposito === null) {
            return false;
        }

        // Depósitos especiales como OU se consideran válidos por defecto.
        if ($deposito === 'OU') {
            return true;
        }

        $sql = "SELECT 1 FROM STA22 WHERE COD_SUCURS = ? AND INHABILITA = 0";
        $stmt = sqlsrv_query($this->conexionActual, $sql, [$deposito]);

        if ($stmt !== false && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            return true;
        }

        // Fallback: revisar si existe stock histórico para ese depósito.
        $sqlFallback = "SELECT 1 FROM STA19 WHERE COD_DEPOSI = ? GROUP BY COD_DEPOSI";
        $stmtFallback = sqlsrv_query($this->conexionActual, $sqlFallback, [$deposito]);

        return $stmtFallback !== false && sqlsrv_fetch_array($stmtFallback, SQLSRV_FETCH_ASSOC);
    }

    /**
     * Obtiene el stock disponible de un artículo en un depósito.
     */
    public function obtenerStockDisponible(string $articulo, ?string $deposito): float
    {
        $articulo = trim($articulo);
        $deposito = $this->normalizarDeposito($deposito);

        if ($articulo === '' || $deposito === null) {
            return 0.0;
        }

        $sql = "SELECT CANT_STOCK FROM STA19 WHERE COD_ARTICU = ? AND COD_DEPOSI = ?";
        $stmt = sqlsrv_query($this->conexionActual, $sql, [$articulo, $deposito]);

        if ($stmt === false) {
            throw new RuntimeException('Error al obtener el stock disponible.');
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if (!$row || !isset($row['CANT_STOCK'])) {
            return 0.0;
        }

        return (float)$row['CANT_STOCK'];
    }

    /**
     * Devuelve el país actualmente configurado.
     */
    public function obtenerPaisActual(): string
    {

        return $this->paisActual;
    }

    /**
     * Detección automática del país según la sesión.
     */
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

        $deposito = strtoupper(trim($deposito));

        if ($deposito === '') {
            return null;
        }

        if ($deposito === 'OU') {
            return $deposito;
        }

        if (ctype_digit($deposito)) {
            return str_pad($deposito, 2, '0', STR_PAD_LEFT);
        }

        return $deposito;
    }
}


