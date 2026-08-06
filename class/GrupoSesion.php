<?php

if (class_exists('GrupoSesion')) {
    return;
}

class GrupoSesion
{
    public static function iniciar()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function requiereLogin(string $loginUrl = '../login.php')
    {
        self::iniciar();
        if (!isset($_SESSION['username'])) {
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    public static function redirigirSiCargaPendiente(string $cargaUrl = 'controller/cargaPedido.php')
    {
        if (!empty($_SESSION['bloquear_reintento_carga_automatico'])) {
            return;
        }

        if (!empty($_SESSION['sucursalesGrupo'])
            && isset($_SESSION['cargaPedido']) && (int) $_SESSION['cargaPedido'] === 1
            && empty($_SESSION['pedido_grupo_cargado'])) {
            header('Location: ' . $cargaUrl);
            exit;
        }
    }

    /**
     * Al entrar al dashboard (index.php) se invalida la carga previa para forzar
     * un nuevo chequeo de conexión a cada sucursal.
     * Tras completar cargaPedido.php se setea omitir_rechequeo_index para no re-entrar en bucle.
     */
    public static function solicitarRechequeoConexionesEnDashboard(): void
    {
        self::iniciar();

        if (!empty($_SESSION['omitir_rechequeo_index'])) {
            unset($_SESSION['omitir_rechequeo_index']);
            return;
        }

        unset($_SESSION['bloquear_reintento_carga_automatico']);

        if (!empty($_SESSION['sucursalesGrupo'])
            && isset($_SESSION['cargaPedido']) && (int) $_SESSION['cargaPedido'] === 1
            && !empty($_SESSION['pedido_grupo_cargado'])) {
            unset($_SESSION['pedido_grupo_cargado']);
        }
    }

    /**
     * Lee y limpia alertas flash de cargaPedido.php.
     * @return array{fallidas: array, error: string|null}
     */
    public static function consumirAlertasCarga()
    {
        $alertas = [
            'fallidas' => $_SESSION['sucursales_conexion_fallidas'] ?? [],
            'error'    => $_SESSION['carga_pedido_error'] ?? null,
        ];
        unset($_SESSION['sucursales_conexion_fallidas'], $_SESSION['carga_pedido_error']);

        return $alertas;
    }

    /**
     * Bootstrap del dashboard grupo (auth, carga inicial, datos de vista).
     */
    public static function prepararDashboard(
        string $loginUrl = '../login.php',
        string $cargaUrl = 'controller/cargaPedido.php'
    ) {
        self::requiereLogin($loginUrl);
        self::solicitarRechequeoConexionesEnDashboard();
        self::redirigirSiCargaPendiente($cargaUrl);

        $alertas = self::consumirAlertasCarga();

        if (!isset($_SESSION['numsuc'])) {
            $_SESSION['numsuc'] = 100;
        }

        return [
            'local'                      => $_SESSION['descLocal'] ?? '',
            'nombreEmpresa'              => $_SESSION['NOMBRE_FRANQUICIA'] ?? ($_SESSION['descLocal'] ?? 'Grupo empresario'),
            'sucursalesConexionFallidas' => $alertas['fallidas'],
            'cargaPedidoError'           => $alertas['error'],
            'mostrarModalCarga'          => !empty($alertas['fallidas']) || !empty($alertas['error']),
        ];
    }

    /** Base central o Uruguay según sesión. */
    public static function obtenerBaseDatos()
    {
        self::iniciar();
        return (!empty($_SESSION['usuarioUy']) && (int) $_SESSION['usuarioUy'] === 1) ? 'uy' : 'central';
    }

    /** Exige que la carga inicial del grupo haya finalizado. */
    public static function requiereCargaGrupoCompletada(string $cargaUrl = '../controller/cargaPedido.php')
    {
        self::iniciar();
        if (empty($_SESSION['pedido_grupo_cargado']) && empty($_SESSION['sucursales_activas'])) {
            header('Location: ' . $cargaUrl);
            exit;
        }
    }

    /**
     * Mensaje legible para el operador ante fallos de conexión/carga de sucursales.
     * El detalle técnico queda en logs.
     */
    public static function mensajeConexionAmigable(string $tipo, string $errorTecnico = ''): string
    {
        switch ($tipo) {
            case 'sin_config':
                return 'Este local no tiene configuración de conexión en el sistema.';
            case 'consulta_stock':
                return 'Se conectó al local pero no se pudieron obtener stock y ventas. Intente nuevamente más tarde.';
            case 'insert_central':
                return 'No se pudieron guardar los datos en la base central. Contacte a soporte si el problema persiste.';
            case 'conexion_central':
                return 'No se pudo conectar con la base de datos central. Contacte a soporte.';
        }

        $err = strtolower($errorTecnico);

        if ($err === '') {
            return 'No se pudo conectar con este local. Intente nuevamente más tarde.';
        }

        if (strpos($err, 'login timeout') !== false
            || strpos($err, 'timed out') !== false
            || strpos($err, 'hyt00') !== false
            || strpos($err, 'tiempo de espera') !== false
            || strpos($err, 'wait operation timed out') !== false) {
            return 'El local no respondió a tiempo. Puede estar apagado, sin internet o con conexión lenta.';
        }

        if (strpos($err, 'network-related') !== false
            || strpos($err, 'not accessible') !== false
            || strpos($err, '08001') !== false
            || strpos($err, 'server is not found') !== false
            || strpos($err, 'could not open a connection') !== false) {
            return 'No se pudo acceder al servidor del local. Verifique que el equipo esté encendido y conectado.';
        }

        if (strpos($err, 'login failed') !== false
            || strpos($err, 'authentication') !== false
            || strpos($err, '18456') !== false) {
            return 'No se pudo iniciar sesión en la base del local (credenciales incorrectas).';
        }

        if (strpos($err, 'characterset') !== false || strpos($err, 'imssp [-33]') !== false) {
            return 'Error de configuración interna al conectar. Contacte a soporte.';
        }

        if (strpos($err, 'imssp [-1]') !== false || strpos($err, 'invalid option') !== false) {
            return 'Configuración de conexión incompleta para este local.';
        }

        return 'No se pudo conectar con este local. Intente nuevamente más tarde.';
    }
}
