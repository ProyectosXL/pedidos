<?php

require_once __DIR__ . '/../../../sistemas/class/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../../sistemas/class/PHPMailer/SMTP.php';
require_once __DIR__ . '/../../../sistemas/class/PHPMailer/Exception.php';
require_once __DIR__ . '/../../../sistemas/class/classEnv.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Clase EmailPedido
 * Envía notificación automática por email cuando se registra un nuevo pedido.
 */
class EmailPedido {

    // Destinatario fijo para notificaciones de pedidos
    private const EMAIL_DESTINO = 'pedidos@xl.com.ar';

    // Nombre del remitente (no es credencial)
    private const EMAIL_FROM_NAME = 'Sistema de Pedidos XL';

    // Modo desarrollo: si TRUE, todos los mails van a EMAIL_DEVELOP en lugar del destinatario real
    private const DEVELOP      = false;
    private const EMAIL_DEVELOP = 'ramiro.orozco@xl.com.ar';

    private $envVars = [];

    public function __construct() {
        try {
            // locales/class -> htdocs/.env
            $vars = new DotEnv(__DIR__ . '/../../../.env');
            $this->envVars = $vars->listVars();
        } catch (\Throwable $e) {
            error_log("EmailPedido::__construct — no se pudo cargar .env: " . $e->getMessage());
            $this->envVars = [];
        }
    }

    /**
     * Envía la notificación de nuevo pedido.
     *
     * @param string $nroPedido   Número de pedido asignado
     * @param string $sucursal    Nombre o código de la sucursal
     * @param string $codClient   Código del cliente
     * @param string $tipoPedido  Tipo de pedido (GENERAL, ACCESORIOS, OUTLET)
     * @return bool
     */
    public function notificarNuevoPedido(string $nroPedido, string $sucursal, string $codClient, string $tipoPedido): bool {
        try {
            $fechaHora   = date('d/m/Y H:i:s');
            $destinatario = self::DEVELOP ? self::EMAIL_DEVELOP : self::EMAIL_DESTINO;

            $asunto  = "Nuevo Pedido #{$nroPedido} — {$sucursal}";
            $cuerpo  = $this->generarHtml($nroPedido, $sucursal, $codClient, $tipoPedido, $fechaHora);

            return $this->enviarEmail($destinatario, $asunto, $cuerpo);

        } catch (\Throwable $e) {
            error_log("EmailPedido::notificarNuevoPedido — ERROR: " . $e->getMessage());
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function generarHtml(string $nroPedido, string $sucursal, string $codClient, string $tipoPedido, string $fechaHora): string {
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <title>Nuevo Pedido #{$nroPedido}</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>

            <div style='background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
                <h1 style='margin: 0; font-size: 22px;'>Nuevo Pedido Recibido</h1>
            </div>

            <div style='background-color: #ffffff; padding: 30px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px;'>

                <div style='background-color: #d1e7dd; padding: 15px; border-radius: 5px; margin-bottom: 25px; border-left: 4px solid #198754;'>
                    <p style='margin: 0; color: #0f5132; font-size: 16px;'>
                        ✅ Se registró un nuevo pedido en el sistema.
                    </p>
                </div>

                <table style='width: 100%; border-collapse: collapse;'>
                    <tr style='border-bottom: 1px solid #dee2e6;'>
                        <td style='padding: 12px 8px; font-weight: bold; width: 180px; color: #555;'>Número de pedido:</td>
                        <td style='padding: 12px 8px;'>
                            <code style='background-color: #e9ecef; padding: 4px 10px; border-radius: 4px; font-size: 15px;'>{$nroPedido}</code>
                        </td>
                    </tr>
                    <tr style='border-bottom: 1px solid #dee2e6;'>
                        <td style='padding: 12px 8px; font-weight: bold; color: #555;'>Sucursal:</td>
                        <td style='padding: 12px 8px;'>{$sucursal}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #dee2e6;'>
                        <td style='padding: 12px 8px; font-weight: bold; color: #555;'>Código cliente:</td>
                        <td style='padding: 12px 8px;'>{$codClient}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #dee2e6;'>
                        <td style='padding: 12px 8px; font-weight: bold; color: #555;'>Tipo de pedido:</td>
                        <td style='padding: 12px 8px;'>
                            <span style='background-color: #0d6efd; color: white; padding: 4px 12px; border-radius: 12px; font-size: 13px;'>{$tipoPedido}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 8px; font-weight: bold; color: #555;'>Fecha y hora de carga:</td>
                        <td style='padding: 12px 8px;'>{$fechaHora}</td>
                    </tr>
                </table>

            </div>

            <div style='text-align: center; margin-top: 20px; color: #6c757d; font-size: 12px;'>
                <p>Mensaje automático del Sistema de Pedidos XL. Por favor no responda este correo.</p>
                <p>&copy; " . date('Y') . " XL Extralarge — Todos los derechos reservados</p>
            </div>

        </body>
        </html>";
    }

    private function enviarEmail(string $destinatario, string $asunto, string $cuerpo): bool {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = getenv('HOST_EMAIL_EGRESOS') ?: 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('USER_EMAIL_PEDIDOS');
            $mail->Password   = getenv('PASS_EMAIL_PEDIDOS');
            $mail->SMTPSecure = (getenv('PORT_EMAIL_EGRESOS') == '465') ? 'ssl' : 'tls';
            $mail->Port       = getenv('PORT_EMAIL_EGRESOS') ?: 587;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]
            ];
            $mail->CharSet   = 'UTF-8';
            $mail->Encoding  = 'base64';
            $mail->SMTPDebug = 0;

            $mail->setFrom($mail->Username, self::EMAIL_FROM_NAME);

            // Modo desarrollo: redirigir a email de prueba
            if (self::DEVELOP) {
                error_log("EmailPedido [DEVELOP]: redirigido de [{$destinatario}] a [" . self::EMAIL_DEVELOP . "]");
                $destinatario = self::EMAIL_DEVELOP;
            }

            $mail->addAddress($destinatario);

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpo;
            $mail->AltBody = strip_tags($cuerpo);

            $resultado = $mail->send();
            if ($resultado) {
                error_log("EmailPedido: mail enviado a {$destinatario} — {$asunto}");
            }
            return $resultado;

        } catch (Exception $e) {
            error_log("EmailPedido: ERROR enviando a {$destinatario} — " . $mail->ErrorInfo);
            return false;
        }

        // Modo desarrollo: redirigir a email de prueba
        if (self::DEVELOP) {
            error_log("EmailPedido [DEVELOP]: redirigido de [{$destinatario}] a [" . self::EMAIL_DEVELOP . "]");
            $destinatario = self::EMAIL_DEVELOP;
        }

        $ultimoError = '';
        foreach ($smtpConfigs as $idx => $smtp) {
            $mail = new PHPMailer(true);
            try {
                if ($smtp['username'] === '' || $smtp['password'] === '') {
                    throw new Exception('credenciales vacias (USER/PASS)');
                }

                $mail->isSMTP();
                $mail->Host       = $smtp['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtp['username'];
                $mail->Password   = $smtp['password'];
                $mail->SMTPSecure = $smtp['secure'];
                $mail->Port       = $smtp['port'];
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ]
                ];
                $mail->CharSet   = 'UTF-8';
                $mail->Encoding  = 'base64';
                $mail->SMTPDebug = self::DEVELOP ? 2 : 0;
                $mail->Debugoutput = 'error_log';

                $mail->setFrom($smtp['username'], self::EMAIL_FROM_NAME);
                $mail->addAddress($destinatario);

                $mail->isHTML(true);
                $mail->Subject = $asunto;
                $mail->Body    = $cuerpo;
                $mail->AltBody = strip_tags($cuerpo);

                $resultado = $mail->send();
                if ($resultado) {
                    $fuente = $smtp['source'] ?? 'SMTP';
                    error_log("EmailPedido: mail enviado a {$destinatario} — {$asunto} [{$fuente}]");
                    return true;
                }
            } catch (Exception $e) {
                $ultimoError = $mail->ErrorInfo ?: $e->getMessage();
                $fuente = $smtp['source'] ?? ('SMTP_' . ($idx + 1));
                error_log("EmailPedido: intento fallido [{$fuente}] para {$destinatario} — {$ultimoError}");
            }
        }

        error_log("EmailPedido: ERROR final enviando a {$destinatario} — {$ultimoError}");
        return false;
    }

    private function obtenerConfigSmtp(): array {
        // Mantener compatibilidad del nombre del metodo: pedidos usa solo BASE
        return $this->obtenerConfigSmtpBase();
    }

    private function obtenerConfigSmtpBase(): array {
        $host = $this->leerEnv(['HOST_EMAIL'], 'smtp.gmail.com');
        $username = $this->leerEnv(['USER_EMAIL_PEDIDOS'], '');
        $password = $this->leerEnv(['PASS_EMAIL_PEDIDOS'], '');
        $port = intval($this->leerEnv(['PORT_EMAIL'], '587'));
        if ($port <= 0) $port = 587;

        $secure = strtolower($this->leerEnv(['SMTP_SECURE'], ''));
        if ($secure !== 'ssl' && $secure !== 'tls') {
            $secure = ($port === 465) ? 'ssl' : 'tls';
        }

        return [
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'port' => $port,
            'secure' => $secure,
            'source' => 'BASE',
        ];
    }

    private function obtenerConfigsSmtp(): array {
        // Requerimiento operativo: NO intentar *_EGRESOS en pedidos.
        // Solo credenciales BASE (HOST_EMAIL / USER_EMAIL / PASS_EMAIL).
        $base = $this->obtenerConfigSmtpBase();
        if ($base['username'] === '' || $base['password'] === '') {
            return [];
        }
        return [$base];
    }

    private function leerEnv(array $keys, string $default = ''): string {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value === false || $value === null || trim((string)$value) === '') {
                $value = $this->envVars[$key] ?? '';
            }
            if (trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }
        return $default;
    }
}
