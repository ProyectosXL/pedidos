<?php

/**
 * Gestor de conexiones multipaís para control de auditoría.
 *
 * Esta clase permite solicitar conexiones reutilizando la clase `Conexion`
 * existente, pero resolviendo automáticamente el origen según el país indicado.
 *
 * - AR -> base "central"
 * - UY -> base "uy"
 *
 * Se puede ampliar el mapeo pasando un arreglo asociativo en el constructor:
 *   $gestor = new MultiConexion(['BR' => 'suc_uy']);
 */
class MultiConexion
{
    /**
     * @var Conexion
     */
    private $conexion;

    /**
     * @var array<string,string> Mapeo país -> identificador de base (central, uy, etc.)
     */
    private $mapaServidores = [
        'AR' => 'central',
        'UY' => 'uy',
    ];

    /**
     * @var array<string,mixed> Cache de conexiones abiertas por país normalizado.
     */
    private $cacheConexiones = [];

    /**
     * @param array<string,string> $mapaPersonalizado
     */
    public function __construct(array $mapaPersonalizado = [])
    {
        require_once __DIR__ . '/conexion.php';

        $this->conexion = new Conexion();
        $this->establecerMapeo($mapaPersonalizado);
    }

    /**
     * Devuelve una conexión para el país indicado.
     *
     * @param string $pais Código ISO-2 o identificador (AR, UY, etc.)
     * @return mixed Recurso de conexión de sqlsrv_connect o false si falla.
     */
    public function obtenerConexionPorPais(string $pais)
    {
        $paisNormalizado = $this->normalizarPais($pais);

        if ($paisNormalizado === null) {
            throw new InvalidArgumentException('El código de país proporcionado es inválido.');
        }

        if (!isset($this->mapaServidores[$paisNormalizado])) {
            throw new InvalidArgumentException(sprintf('No existe mapeo de conexión para el país "%s".', $paisNormalizado));
        }

        if (isset($this->cacheConexiones[$paisNormalizado])) {
            return $this->cacheConexiones[$paisNormalizado];
        }

        $conexion = $this->conexion->conectar($this->mapaServidores[$paisNormalizado]);
        $this->cacheConexiones[$paisNormalizado] = $conexion;

        return $conexion;
    }

    /**
     * Obtiene el identificador de servidor configurado para el país.
     *
     * @param string $pais
     * @return string
     */
    public function obtenerIdentificadorServidor(string $pais): string
    {
        $paisNormalizado = $this->normalizarPais($pais);

        if ($paisNormalizado === null) {
            throw new InvalidArgumentException('El código de país proporcionado es inválido.');
        }

        if (!isset($this->mapaServidores[$paisNormalizado])) {
            throw new InvalidArgumentException(sprintf('No existe mapeo de conexión para el país "%s".', $paisNormalizado));
        }

        return $this->mapaServidores[$paisNormalizado];
    }

    /**
     * Actualiza o agrega un mapeo de país -> identificador de servidor.
     *
     * @param string $pais
     * @param string $identificadorServidor
     * @return void
     */
    public function setMapeo(string $pais, string $identificadorServidor): void
    {
        $paisNormalizado = $this->normalizarPais($pais);

        if ($paisNormalizado === null) {
            throw new InvalidArgumentException('El código de país proporcionado es inválido.');
        }

        $this->mapaServidores[$paisNormalizado] = $identificadorServidor;
        unset($this->cacheConexiones[$paisNormalizado]);
    }

    /**
     * Devuelve el mapeo completo actualmente configurado.
     *
     * @return array<string,string>
     */
    public function obtenerMapeo(): array
    {
        return $this->mapaServidores;
    }

    /**
     * Normaliza un código de país a dos letras mayúsculas.
     *
     * @param string|null $pais
     * @return string|null
     */
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

    /**
     * Mezcla el mapeo por defecto con el personalizado recibido.
     *
     * @param array<string,string> $mapaPersonalizado
     * @return void
     */
    private function establecerMapeo(array $mapaPersonalizado): void
    {
        foreach ($mapaPersonalizado as $pais => $servidor) {
            $paisNormalizado = $this->normalizarPais($pais);

            if ($paisNormalizado === null || !is_string($servidor) || trim($servidor) === '') {
                continue;
            }

            $this->mapaServidores[$paisNormalizado] = trim($servidor);
        }
    }
}


