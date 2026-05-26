<?php


require_once $_SERVER["DOCUMENT_ROOT"].'/sistemas/class/PHPMailer/PHPMailer.php';
require_once $_SERVER["DOCUMENT_ROOT"].'/sistemas/class/PHPMailer/SMTP.php';
require_once $_SERVER["DOCUMENT_ROOT"].'/sistemas/class/PHPMailer/Exception.php';

require_once $_SERVER["DOCUMENT_ROOT"].'/sistemas/class/classEnv.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Email{

    private $Host;
    private $SMTPAuth;
    private $Username;
    private $Password;
    private $SMTPSecure;
    private $Port;
    private $email;
    private $sender;
    private $vars;
    private $envVars;
    private $body;
    private $message;


    public function __construct() {

        try {

            $this->vars = new DotEnv($_SERVER["DOCUMENT_ROOT"].'/sistemas/.env');
            $this->envVars = $this->vars->listVars();

            $this->mail = new PHPMailer(true);

            $this->mail->isSMTP();
            $this->mail->Host = 'smtp.gmail.com'; 
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->envVars['USER_EMAIL'];
            $this->mail->Password = $this->envVars['PASS_EMAIL'];
            $this->mail->SMTPSecure = 'tls'; 
            $this->mail->Port = 587; 
            $this->mail->setFrom($this->envVars['USER_EMAIL'], 'XL Extralarge');

        } catch (Exception $e) {
            echo "Error al instanciar la clase Email: {$this->mail->ErrorInfo}";
        }
        
    }


    
    public function enviarEmail($email, $asunto, $arrayData){

        try {
            
            $this->mail->addAddress($email);
            $this->mail->Subject = $asunto;
            $this->mail->Body = $this->emailBody($arrayData);
            $this->mail->isHTML(true);
            $this->mail->send();

            return true;

        } catch (\Throwable $th) {
            echo "Error al enviar el correo: {$mail->ErrorInfo}";
        }
        
    }
    
    private function emailBody ($arrayData) {

        switch ($arrayData['tipo']) {

            case '1':
                $this->body = $this->solicitudConfirmada($arrayData['descSucursal'], $arrayData['numSolicitud']);
                break;

            case '2':
                $this->body = $this->solicitudAutorizada($arrayData['descSucursal'], $arrayData['numSolicitud'], $arrayData['nombreSup']);
                break;

            case '3':
                $this->body = $this->solicitudEnviada($arrayData['descSucursal'], $arrayData['numSolicitud']);
                break;

            case '4':
                $this->body = $this->notificarCodigosOulet($arrayData['descSucursal'], $arrayData['numSolicitud'], $arrayData['data']);
                break;
                
        }

        return $this->body;

    }

    private function solicitudConfirmada($descSucursal, $numSolicitud) {

        $this->body = '

            <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
            <html lang="en" xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Document</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

                <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap4.min.css" class="rel">
                <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css" class="rel">

                <!-- Bootstrap Icons -->
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">

                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

                
            </head>
            <body>
                
                <div style="height:57rem;padding-left: 0px;padding-right: 0px;font-size:16px">'.$descSucursal.' - Cambio de estado en Solicitud Nro. '.$numSolicitud.'
                
                    <div style="height: 25rem; width:100%;display: flex; justify-content: center; align-items: center; ">
                        <div>
                            <div class="card" style="width: 800px; height: 28rem; margin-top:16rem; background-color: #dc3545; color: #FFFFFF">
                                <br>
                                <div style="text-align:center"><h1 style="margin-top:2rem;font-size:60px">XL Fallas</h1></div>
                                <div style="margin-top:2rem;text-align: center; font-size:22px">Hola,</div>
                                <br>
                                <div style="text-align: center; font-size:22px">Le informamos que la solicitud con el numero '.$numSolicitud.' ha sido <br> confirmada.</div>
                                <br>
                                <div style="text-align: center; font-size:14px">Este correo es informativo, favor no responder a esta direccion de correo, ya que no se <br> encuentra habilitada para recibir mensajes</div>
                                <br>
                                <div style="text-align: center; font-size:15px;">Extra Large, ARGENTINA</div>
                                <br><br>
                            </div>
                        </div>
                    </div>

                </div>

                
            </body>
            </html>

            ';

        return $this->body;
    
    }

    private function solicitudAutorizada($desc_sucursal, $numSolicitud, $nombreSup) {

        $this->body = ' 

            <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Document</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
                
                    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.css"> -->
                    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap4.min.css" class="rel">
                    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css" class="rel">
                
                    <!-- Bootstrap Icons -->
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
                
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                
                    <body>
                    <!-- <div style="background-color:#E43D3D;height: 300px;"></div>
                
                    <div style="background-color:yellow">asasd</div> -->
                    
                    <div style="height:57rem;padding-left: 0px;padding-right: 0px;font-size:16px">'.$desc_sucursal.' - Cambio de estado en Solicitud Nro. '.$numSolicitud.'
                
                            <div style="height: 25rem; width:100%;display: flex; justify-content: center; align-items: center; ">
                                <div>
                                    <div class="card" style="width: 800px; height: 28rem; margin-top:16rem; background-color: #dc3545; color: #FFFFFF">
                                    <br>
                                    <div style="text-align:center"><h1 style="margin-top:2rem;font-size:60px">XL Fallas</h1></div>
                                    <div style="margin-top:2rem;text-align: center; font-size:22px">Hola,</div>
                                    <br>
                                    <div style="text-align: center; font-size:22px">Le informamos que la solicitud con el numero '.$numSolicitud.' ha sido <br> autorizada por '.$nombreSup.'.</div>
                                    <br>
                                    <div style="text-align: center; font-size:14px">Este correo es informativo, favor no responder a esta direccion de correo, ya que no se <br> encuentra habilitada para recibir mensajes</div>
                                    <br>
                                    <div style="text-align: center; font-size:15px;">Extra Large, ARGENTINA</div>
                                    <br><br>
                                    </div>
                                </div>
                            </div>
                    </div>
                
                    
                </body>
            </html>';

        return $this->body;

    }

    private function solicitudEnviada($desc_sucursal, $numSolicitud){

        $this->body = '

            <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Document</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

                    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.css"> -->
                    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap4.min.css" class="rel">
                    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css" class="rel">

                    <!-- Bootstrap Icons -->
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">

                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

                    <body>
                    <!-- <div style="background-color:#E43D3D;height: 300px;"></div>

                    <div style="background-color:yellow">asasd</div> -->
                    
                    <div style="height:57rem;padding-left: 0px;padding-right: 0px;font-size:16px">'.$desc_sucursal.' - Cambio de estado en Solicitud Nro. '.$numSolicitud.'
                
                            <div style="height: 25rem; width:100%;display: flex; justify-content: center; align-items: center; ">
                                <div>
                                    <div class="card" style="width: 800px; height: 28rem; margin-top:16rem; background-color: #dc3545; color: #FFFFFF">
                                    <br>
                                    <div style="text-align:center"><h1 style="margin-top:2rem;font-size:60px">XL Fallas</h1></div>
                                    <div style="margin-top:2rem;text-align: center; font-size:22px">Hola,</div>
                                    <br>
                                    <div style="text-align: center; font-size:22px">Le informamos que la solicitud con el numero '.$numSolicitud.' ha sido <br> enviada.</div>
                                    <br>
                                    <div style="text-align: center; font-size:14px">Este correo es informativo, favor no responder a esta direccion de correo, ya que no se <br> encuentra habilitada para recibir mensajes</div>
                                    <br>
                                    <div style="text-align: center; font-size:15px;">Extra Large, ARGENTINA</div>
                                    <br><br>
                                    </div>
                                </div>
                            </div>
                    </div>

                    
                </body>
            </html>

        ' ;
        return $this->body;


    }

    private function notificarCodigosOulet ($desc_sucursal, $numSolicitud, $data) {
            $count = count($data);

            $alturaExtra = $count * 2 ;

            $altura = 32 + $alturaExtra;

            $this->body = '

            <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Document</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

                    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.css"> -->
                    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap4.min.css" class="rel">
                    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css" class="rel">

                    <!-- Bootstrap Icons -->
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">

                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

                    <body>
                    <!-- <div style="background-color:#E43D3D;height: 300px;"></div>

                    <div style="background-color:yellow">asasd</div> -->
                    
                    <div style="height:57rem;padding-left: 0px;padding-right: 0px;font-size:16px">'.$desc_sucursal.' - Alta de articulos para solicitud Nro. '.$numSolicitud.'
                
                            <div style="height: 25rem; width:100%;display: flex; justify-content: center; align-items: center; ">
                                <div>
                                    <div class="card" style="width: 800px; height: '.$altura.'rem; margin-top:16rem; background-color: #dc3545; color: #FFFFFF">
                                        <br>
                                        <div style="text-align:center"><h1 style="margin-top:2rem;font-size:60px">XL Fallas</h1></div>
                                        <div style="margin-top:2rem;text-align: center; font-size:22px">Hola,</div>
                                        <br>
                                        <div style="text-align: center; font-size:22px">Le informamos que para procesar el ajuste  de la solicitud <br> con el numero '.$numSolicitud.',se requiere el alta de los siguiente articulos.</div>
                                        <br>';
                                        foreach ($data as $key => $articulo) {
                                            $this->body .= '<div  style="text-align: center; font-size:22px">'.$articulo.'<div>';
                                        };

                                    $this->body .= '<br>
                                        <div style="text-align: center; font-size:14px">Este correo es informativo, favor no responder a esta direccion de correo, ya que no se <br> encuentra habilitada para recibir mensajes</div>
                                        <br>
                                        <div style="text-align: center; font-size:15px;">Extra Large, ARGENTINA</div>
                                        <br><br>
                                    </div>
                                </div>
                            </div>
                    </div>

                    
                </body>
            </html>

        ' ;
        return $this->body;

    }
    
}
