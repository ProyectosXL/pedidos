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
        if (!empty($_SESSION['sucursalesGrupo'])
            && isset($_SESSION['cargaPedido']) && (int) $_SESSION['cargaPedido'] === 1
            && empty($_SESSION['pedido_grupo_cargado'])) {
            header('Location: ' . $cargaUrl);
            exit;
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
}
