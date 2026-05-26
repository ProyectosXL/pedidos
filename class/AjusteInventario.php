<?php

require_once __DIR__ . '/MultiConexion.php';
require_once __DIR__ . '/conexion.php';

/**
 * Clase para gestionar la generación de ajustes de inventario
 * relacionados con el control de remitos y diferencias de conteo.
 */
class AjusteInventario
{
    /**
     * @var MultiConexion
     */
    private $multiConexion;

    /**
     * @var Conexion
     */
    private $conexion;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->multiConexion = new MultiConexion();
        $this->conexion = new Conexion();
    }

    /**
     * Genera ajustes de inventario para uno o más remitos
     * 
     * @param array $remitosPayload Array con los datos de los remitos a ajustar
     * @param string $observaciones Observaciones opcionales para enviar a Tango
     * @return array Array con resultados y errores
     */
    public function generarAjustes(array $remitosPayload, string $observaciones = ''): array
    {
        $resultados = [];
        $errores = [];

        $observaciones = $this->sanitizarTexto($observaciones);

        foreach ($remitosPayload as $remitoData) {
            $numeroRemito = isset($remitoData['numero']) ? trim((string)$remitoData['numero']) : '';
            $deposito = isset($remitoData['deposito']) ? trim((string)$remitoData['deposito']) : '01';
            $pais = isset($remitoData['pais']) ? strtoupper(trim((string)$remitoData['pais'])) : 'AR';
            $sucursalDestino = isset($remitoData['sucursalDestino']) ? trim((string)$remitoData['sucursalDestino']) : '';
            $items = isset($remitoData['items']) && is_array($remitoData['items']) ? $remitoData['items'] : [];

            // Validaciones backend
            if (empty($numeroRemito)) {
                $errores[] = "Remito: Número de remito vacío o inválido.";
                continue;
            }

            if (!preg_match('/^[A-Z0-9\-_]+$/i', $numeroRemito)) {
                $errores[] = "Remito $numeroRemito: Formato de número de remito inválido.";
                continue;
            }

            if (!in_array($pais, ['AR', 'UY'])) {
                $errores[] = "Remito $numeroRemito: País inválido. Debe ser AR o UY.";
                continue;
            }

            if (empty($items) || !is_array($items)) {
                $errores[] = "Remito $numeroRemito: No hay items para procesar.";
                continue;
            }

            // Validar items
            $itemsValidos = [];
            foreach ($items as $item) {
                $codigoArticulo = isset($item['codigo']) ? trim((string)$item['codigo']) : '';
                $diferencia = isset($item['diferencia']) ? (float)$item['diferencia'] : 0;

                if (empty($codigoArticulo)) {
                    continue;
                }

                // Validar formato de código de artículo
                if (!preg_match('/^[A-Z0-9\-_]+$/i', $codigoArticulo)) {
                    $errores[] = "Remito $numeroRemito: Código de artículo inválido: $codigoArticulo";
                    continue;
                }

                // Validar diferencia
                if ($diferencia == 0) {
                    continue;
                }

                if (!is_numeric($diferencia) || abs($diferencia) > 999999) {
                    $errores[] = "Remito $numeroRemito: Diferencia inválida para artículo $codigoArticulo";
                    continue;
                }

                $itemsValidos[] = [
                    'codigo' => $codigoArticulo,
                    'diferencia' => (float)$diferencia
                ];
            }

            if (empty($itemsValidos)) {
                $errores[] = "Remito $numeroRemito: No hay items válidos para procesar.";
                continue;
            }

            try {
                $resultado = $this->procesarAjusteRemito($numeroRemito, $deposito, $pais, $itemsValidos, $observaciones, $sucursalDestino);
                $resultados[] = $resultado;
            } catch (Throwable $th) {
                $errores[] = "Remito $numeroRemito: " . $th->getMessage();
                error_log("Error generando ajuste para remito $numeroRemito: " . $th->getMessage());
            }
        }

        return [
            'resultados' => $resultados,
            'errores' => $errores
        ];
    }

    /**
     * Sanitiza texto para prevenir inyección SQL
     * 
     * @param string $texto
     * @return string
     */
    private function sanitizarTexto(string $texto): string
    {
        // Escapar comillas simples para SQL
        $texto = str_replace("'", "''", $texto);
        // Remover caracteres de control
        $texto = preg_replace('/[\x00-\x1F\x7F]/', '', $texto);
        // Limitar longitud
        return substr($texto, 0, 500);
    }

    /**
     * Procesa el ajuste para un remito individual
     * 
     * @param string $numeroRemito Número del remito
     * @param string $deposito Código del depósito
     * @param string $pais País (AR o UY)
     * @param array $items Array con los items a ajustar
     * @param string $observaciones Observaciones opcionales
     * @param string $sucursalDestino Número de sucursal destino
     * @return array Resultado del procesamiento
     * @throws Exception Si hay algún error en el proceso
     */
    private function procesarAjusteRemito(string $numeroRemito, string $deposito, string $pais, array $items, string $observaciones, string $sucursalDestino = ''): array
    {
        $cid = $this->multiConexion->obtenerConexionPorPais($pais);
        if ($cid === false) {
            throw new Exception("No se pudo conectar a la base de datos para el país $pais.");
        }

        // Verificar si hay diferencias negativas que requieren salida desde depósito del local
        $tieneEntradasAjuste = false;
        $cidLocal = null;
        $depositoLocal = '';
        
        foreach ($items as $item) {
            if (isset($item['diferencia']) && (float)$item['diferencia'] < 0) {
                $tieneEntradasAjuste = true;
                break;
            }
        }

        // Si hay entradas al depósito 05, obtener conexión a la base del local
        if ($tieneEntradasAjuste && !empty($sucursalDestino)) {
            $depositoLocal = trim($sucursalDestino);
            $depositoLocal = preg_replace('/[^0-9]/', '', $depositoLocal);
            $depositoLocal = str_pad($depositoLocal, 2, '0', STR_PAD_LEFT);
            
            if (!empty($depositoLocal)) {
                $cidLocal = $this->obtenerConexionPorDeposito($cid, $depositoLocal);
                if ($cidLocal === false) {
                    throw new Exception("No se pudo obtener la conexión para el depósito del local $depositoLocal.");
                }
            }
        }

        // Iniciar transacciones
        sqlsrv_begin_transaction($cid);
        if ($cidLocal !== null) {
            sqlsrv_begin_transaction($cidLocal);
        }

        try {
            $fecha = date('Y/m/d');
            $hora = date('His');

            $proximo = $this->obtenerProximoNumeroAjuste($cid);
            if (empty($proximo)) {
                throw new Exception("No se pudo obtener el próximo número de ajuste.");
            }

            $proxInterno = $this->obtenerProximoNumeroInterno($cid);
            if (empty($proxInterno)) {
                throw new Exception("No se pudo obtener el próximo número interno de ajuste.");
            }

            $this->actualizarTalonario($cid);

            $this->insertarEncabezado($cid, $fecha, $proximo, $proxInterno, $hora, $sucursalDestino);

            $depositoOrigen = ($pais === 'UY') ? '82' : '01'; 
            $depositoDestino = '05';
            $renglon = 1;
            foreach ($items as $item) {
                $codigoArticulo = isset($item['codigo']) ? trim((string)$item['codigo']) : '';
                $diferencia = isset($item['diferencia']) ? (float)$item['diferencia'] : 0;

                if (empty($codigoArticulo) || $diferencia == 0) {
                    continue;
                }

                $cantidadAjuste = abs($diferencia);

                if ($diferencia < 0) {
                    // 2. Entrada al depósito 05 (en base según país)
                    $this->procesarEntrada($cid, $codigoArticulo, $cantidadAjuste, $depositoDestino, $fecha, $proxInterno, $renglon);
                } else {
                    // Salida: solo un movimiento desde el depósito origen (en base según país)
                    $this->procesarSalida($cid, $codigoArticulo, $cantidadAjuste, $depositoOrigen, $fecha, $proxInterno, $renglon);
                }

                $renglon++;
            }

            // Actualizar tabla de control
            $this->actualizarControlRemitos($cid, $numeroRemito, $proximo, $observaciones);

            // Commit todas las transacciones
            sqlsrv_commit($cid);
            if ($cidLocal !== null) {
                sqlsrv_commit($cidLocal);
            }

            return [
                'remito' => $numeroRemito,
                'ajuste' => $proximo,
                'success' => true
            ];

        } catch (Throwable $th) {
            // Rollback todas las transacciones si hay error
            if (function_exists('sqlsrv_in_transaction') && sqlsrv_in_transaction($cid)) {
                sqlsrv_rollback($cid);
            }
            if ($cidLocal !== null && function_exists('sqlsrv_in_transaction') && sqlsrv_in_transaction($cidLocal)) {
                sqlsrv_rollback($cidLocal);
            }
            throw $th;
        }
    }

    /**
     * Obtiene la conexión a la base de datos según el depósito ingresado
     * Usa el método setearDnsBaseName() de Conexion para obtener las credenciales
     * 
     * @param resource $cidCentral Conexión a la base central (no se usa directamente, pero se mantiene para compatibilidad)
     * @param string $deposito Número de depósito (ej: '07', '48')
     * @return resource|false Conexión a la base del depósito o false si falla
     * @throws Exception Si no se encuentra el depósito o hay error al conectar
     */
    private function obtenerConexionPorDeposito($cidCentral, string $deposito)
    {
        // Sanitizar depósito
        $deposito = trim($deposito);
        $deposito = preg_replace('/[^0-9]/', '', $deposito);
        $deposito = str_pad($deposito, 2, '0', STR_PAD_LEFT);

        if (empty($deposito)) {
            throw new Exception("Depósito vacío o inválido.");
        }

        // Convertir depósito a número para usar con setearDnsBaseName
        $nroSucursal = (int)$deposito;

        // Usar el método setearDnsBaseName() que obtiene CONEXION_DNS, BASE_NOMBRE, USUARIO_DNS y CLAVE_DNS
        // y los guarda en la sesión
        $resultado = $this->conexion->setearDnsBaseName($nroSucursal);
        
        if ($resultado === false) {
            throw new Exception("No se encontró información de conexión para el depósito $deposito.");
        }

        // Conectar usando Conexion con un identificador único (no 'central', 'uy', 'local', etc.)
        // para que use los valores de sesión (incluyendo USUARIO_DNS y CLAVE_DNS si están disponibles)
        $cidDeposito = $this->conexion->conectar('deposito_' . $deposito);

        if ($cidDeposito === false) {
            throw new Exception("No se pudo establecer la conexión para el depósito $deposito.");
        }

        return $cidDeposito;
    }

    /**
     * Obtiene el próximo número de ajuste
     * 
     * @param resource $cid Conexión a la base de datos
     * @return string Número de ajuste generado
     * @throws Exception Si hay error al obtener el número
     */
    private function obtenerProximoNumeroAjuste($cid): string
    {
        $sqlProx = "SELECT ' ' + RIGHT('00000' + CAST((SELECT SUCURSAL FROM STA17 WHERE TALONARIO = 850)AS VARCHAR), 5) + RIGHT(('00000000'+ CAST((SELECT PROXIMO FROM STA17 WHERE TALONARIO = 850)AS VARCHAR)),8) PROXIMO";
        $resultProx = sqlsrv_query($cid, $sqlProx);
        
        if ($resultProx === false) {
            throw new Exception("Error al obtener próximo número de ajuste.");
        }
        
        $rowProx = sqlsrv_fetch_array($resultProx, SQLSRV_FETCH_ASSOC);
        return $rowProx['PROXIMO'] ?? '';
    }

    /**
     * Obtiene el próximo número interno de ajuste
     * 
     * @param resource $cid Conexión a la base de datos
     * @return string Número interno generado
     * @throws Exception Si hay error al obtener el número
     */
    private function obtenerProximoNumeroInterno($cid): string
    {
        $sqlProxInterno = "SELECT RIGHT(('00100'+CAST((SELECT MAX(NCOMP_IN_S)+1 NCOMP_IN_S FROM STA14 WHERE TALONARIO = 850)AS VARCHAR)),8) PROXINTERNO";
        $resultProxInterno = sqlsrv_query($cid, $sqlProxInterno);
        
        if ($resultProxInterno === false) {
            throw new Exception("Error al obtener próximo número interno de ajuste.");
        }
        
        $rowProxInterno = sqlsrv_fetch_array($resultProxInterno, SQLSRV_FETCH_ASSOC);
        return $rowProxInterno['PROXINTERNO'] ?? '';
    }

    /**
     * Actualiza el talonario incrementando el próximo número
     * 
     * @param resource $cid Conexión a la base de datos
     * @throws Exception Si hay error al actualizar
     */
    private function actualizarTalonario($cid): void
    {
        $sqlUpdateProx = "UPDATE STA17 SET PROXIMO = PROXIMO+1 WHERE TALONARIO = 850";
        $resultUpdateProx = sqlsrv_query($cid, $sqlUpdateProx);
        
        if ($resultUpdateProx === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al actualizar el talonario: " . print_r($errors, true));
        }
    }

    /**
     * Inserta el encabezado del ajuste en STA14
     * 
     * @param resource $cid Conexión a la base de datos
     * @param string $fecha Fecha del ajuste
     * @param string $proximo Número de ajuste
     * @param string $proxInterno Número interno
     * @param string $hora Hora del ajuste
     * @param string $sucursalDestino Número de sucursal destino
     * @throws Exception Si hay error al insertar
     */
    private function insertarEncabezado($cid, string $fecha, string $proximo, string $proxInterno, string $hora, string $sucursalDestino = ''): void
    {
        // Sanitizar inputs
        $proximo = $this->sanitizarTexto($proximo);
        $proxInterno = $this->sanitizarTexto($proxInterno);
        // Validar formato de fecha
        if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $fecha)) {
            throw new Exception("Formato de fecha inválido: $fecha");
        }
        // Validar formato de hora
        if (!preg_match('/^\d{6}$/', $hora)) {
            throw new Exception("Formato de hora inválido: $hora");
        }
        
        // Sanitizar y validar sucursal destino
        $sucursalDestino = trim($sucursalDestino);
        $sucursalDestino = preg_replace('/[^0-9]/', '', $sucursalDestino);
        // Si está vacío o no es numérico, usar 0
        if (empty($sucursalDestino) || !is_numeric($sucursalDestino)) {
            $sucursalDestino = 0;
        } else {
            $sucursalDestino = (int)$sucursalDestino;
        }

        $sqlEncabezado = "
            INSERT INTO STA14 
            (COD_PRO_CL, COTIZ, EXPORTADO, EXP_STOCK, FECHA_ANU, FECHA_MOV, HORA, 
            LISTA_REM, LOTE, LOTE_ANU, MON_CTE, N_COMP, NCOMP_IN_S, 
            NRO_SUCURS, T_COMP, TALONARIO, TCOMP_IN_S, USUARIO, HORA_COMP,
            ID_A_RENTA, DOC_ELECTR, IMP_IVA, IMP_OTIMP, IMPORTE_BO, IMPORTE_TO, 
            DIFERENCIA, SUC_DESTIN, DCTO_CLIEN, FECHA_INGRESO, HORA_INGRESO, 
            USUARIO_INGRESO, TERMINAL_INGRESO, IMPORTE_TOTAL_CON_IMPUESTOS, 
            CANTIDAD_KILOS)
            VALUES
            ('', 4.5, 0, 1, '1800/01/01', ?, '0000', 0, 0, 0, 1, ?, ?, 0, 'AJU', 850, 'AJ', 'AJUSTES', 
            ?, 0, 0, 0, 0, 0, 0, 'N', ?, 0, ?, ?, 'AJUSTES', (SELECT host_name()), 0, 0)
        ";
        
        $paramsEncabezado = [$fecha, $proximo, $proxInterno, $hora, $sucursalDestino, $fecha, $hora];
        $stmtEncabezado = sqlsrv_prepare($cid, $sqlEncabezado, $paramsEncabezado);
        if ($stmtEncabezado === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al preparar encabezado: " . print_r($errors, true));
        }

        $resultEncabezado = sqlsrv_execute($stmtEncabezado);
        if ($resultEncabezado === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al insertar encabezado: " . print_r($errors, true));
        }
    }

    /**
     * Procesa una salida de stock (diferencia negativa)
     * 
     * @param resource $cid Conexión a la base de datos
     * @param string $codigoArticulo Código del artículo
     * @param float $cantidadAjuste Cantidad a ajustar
     * @param string $deposito Código del depósito
     * @param string $fecha Fecha del movimiento
     * @param string $proxInterno Número interno
     * @param int $renglon Número de renglón
     * @throws Exception Si hay error al procesar
     */
    private function procesarSalida($cid, string $codigoArticulo, float $cantidadAjuste, string $deposito, string $fecha, string $proxInterno, int $renglon): void
    {
        // Sanitizar inputs
        $codigoArticulo = $this->sanitizarCodigoArticulo($codigoArticulo);
        $deposito = $this->sanitizarDeposito($deposito);
        $cantidadAjuste = abs((float)$cantidadAjuste);
        $renglon = (int)$renglon;

        // Insertar detalle de salida usando parámetros preparados
        $sqlDetSalida = "
            INSERT INTO STA20
            (CAN_EQUI_V, CANT_DEV, CANT_OC, CANT_PEND, CANT_SCRAP, CANTIDAD, COD_ARTICU, COD_DEPOSI, DEPOSI_DDE, EQUIVALENC, 
            FECHA_MOV, N_RENGL_OC, N_RENGL_S, NCOMP_IN_S, PLISTA_REM, PPP_EX, PPP_LO, PRECIO, PRECIO_REM, TCOMP_IN_S, TIPO_MOV,
            CANT_FACTU, DCTO_FACTU, CANT_DEV_2, CANT_PEND_2, CANTIDAD_2, CANT_FACTU_2, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, 
            PRECIO_REMITO_VENTAS, CANT_OC_2, RENGL_PADR, PROMOCION, PRECIO_ADICIONAL_KIT, TALONARIO_OC)
            VALUES
            (1, 0, 0, 0, 0, ?, ?, ?, '', 1, ?, 0, ?, ?, 0, 0, 0, 0, 0, 'AJ', 
            'S', 0, 0, 0, 0, 0, 0, 6, 'P', 0, 0, 0, 0, 0, 0)
        ";
        
        $paramsDetSalida = [
            $cantidadAjuste,
            $codigoArticulo,
            $deposito,
            $fecha,
            $renglon,
            $proxInterno
        ];

        $stmtDetSalida = sqlsrv_prepare($cid, $sqlDetSalida, $paramsDetSalida);
        if ($stmtDetSalida === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al preparar detalle de salida para artículo $codigoArticulo: " . print_r($errors, true));
        }

        $resultDetSalida = sqlsrv_execute($stmtDetSalida);
        if ($resultDetSalida === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al insertar detalle de salida para artículo $codigoArticulo: " . print_r($errors, true));
        }

        // Restar del stock usando parámetros preparados
        $sqlResta = "UPDATE STA19 SET CANT_STOCK = (CANT_STOCK - ?) WHERE COD_ARTICU = ? AND COD_DEPOSI = ?";
        $paramsResta = [$cantidadAjuste, $codigoArticulo, $deposito];
        
        $stmtResta = sqlsrv_prepare($cid, $sqlResta, $paramsResta);
        if ($stmtResta === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al preparar actualización de stock para artículo $codigoArticulo: " . print_r($errors, true));
        }

        $resultResta = sqlsrv_execute($stmtResta);
        if ($resultResta === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al restar stock para artículo $codigoArticulo: " . print_r($errors, true));
        }
    }

    /**
     * Procesa una entrada de stock (diferencia positiva)
     * 
     * @param resource $cid Conexión a la base de datos
     * @param string $codigoArticulo Código del artículo
     * @param float $cantidadAjuste Cantidad a ajustar
     * @param string $deposito Código del depósito
     * @param string $fecha Fecha del movimiento
     * @param string $proxInterno Número interno
     * @param int $renglon Número de renglón
     * @throws Exception Si hay error al procesar
     */
    private function procesarEntrada($cid, string $codigoArticulo, float $cantidadAjuste, string $deposito, string $fecha, string $proxInterno, int $renglon): void
    {
        // Sanitizar inputs
        $codigoArticulo = $this->sanitizarCodigoArticulo($codigoArticulo);
        $deposito = $this->sanitizarDeposito($deposito);
        $cantidadAjuste = abs((float)$cantidadAjuste);
        $renglon = (int)$renglon;

        // Insertar detalle de entrada usando parámetros preparados
        $sqlDetEntrada = "
            INSERT INTO STA20
            (CAN_EQUI_V, CANT_DEV, CANT_OC, CANT_PEND, CANT_SCRAP, CANTIDAD, COD_ARTICU, COD_DEPOSI, DEPOSI_DDE, EQUIVALENC, 
            FECHA_MOV, N_RENGL_OC, N_RENGL_S, NCOMP_IN_S, PLISTA_REM, PPP_EX, PPP_LO, PRECIO, PRECIO_REM, TCOMP_IN_S, TIPO_MOV,
            CANT_FACTU, DCTO_FACTU, CANT_DEV_2, CANT_PEND_2, CANTIDAD_2, CANT_FACTU_2, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, 
            PRECIO_REMITO_VENTAS, CANT_OC_2, RENGL_PADR, PROMOCION, PRECIO_ADICIONAL_KIT, TALONARIO_OC)
            VALUES
            (1, 0, 0, 0, 0, ?, ?, ?, '', 1, 
            ?, 0, ?, ?, 0, 0, 0, 0, 0, 'AJ', 'E', 0, 0, 0, 0, 0, 0, 6, 'P', 0, 0, 0, 0, 0, 0)
        ";
        
        $paramsDetEntrada = [
            $cantidadAjuste,
            $codigoArticulo,
            $deposito,
            $fecha,
            $renglon,
            $proxInterno
        ];

        $stmtDetEntrada = sqlsrv_prepare($cid, $sqlDetEntrada, $paramsDetEntrada);
        if ($stmtDetEntrada === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al preparar detalle de entrada para artículo $codigoArticulo: " . print_r($errors, true));
        }

        $resultDetEntrada = sqlsrv_execute($stmtDetEntrada);
        if ($resultDetEntrada === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al insertar detalle de entrada para artículo $codigoArticulo: " . print_r($errors, true));
        }

        // Sumar al stock usando parámetros preparados
        $sqlSumaStock = "UPDATE STA19 SET CANT_STOCK = (CANT_STOCK + ?) WHERE COD_ARTICU = ? AND COD_DEPOSI = ?";
        $paramsSumaStock = [$cantidadAjuste, $codigoArticulo, $deposito];
        
        $stmtSumaStock = sqlsrv_prepare($cid, $sqlSumaStock, $paramsSumaStock);
        if ($stmtSumaStock === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al preparar actualización de stock para artículo $codigoArticulo: " . print_r($errors, true));
        }

        $resultSumaStock = sqlsrv_execute($stmtSumaStock);
        if ($resultSumaStock === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al sumar stock para artículo $codigoArticulo: " . print_r($errors, true));
        }
    }

    /**
     * Actualiza la tabla de control de remitos con el número de ajuste y estado
     * 
     * @param resource $cid Conexión a la base de datos
     * @param string $numeroRemito Número del remito
     * @param string $proximo Número de ajuste generado
     * @param string $observaciones Observaciones opcionales
     * @throws Exception Si hay error al actualizar
     */
    private function actualizarControlRemitos($cid, string $numeroRemito, string $proximo, string $observaciones): void
    {
        // Sanitizar inputs
        $numeroRemito = $this->sanitizarCodigoArticulo($numeroRemito);
        $proximo = trim($proximo);
        
       
        $proximoNumerico = preg_replace('/\D/', '', $proximo);
        
       
        if (strlen($proximoNumerico) > 9) {
            $proximoNumerico = substr($proximoNumerico, -9);
        }
        
    
        $nroAjusteInt = (int)$proximoNumerico;
        

        if ($nroAjusteInt > 2147483647) {
            $nroAjusteInt = 2147483647; 
        }
        
        $sqlUpdateControl = "
            UPDATE SJ_CONTROL_AUDITORIA 
                SET OBSERVAC_LOGISTICA = 'PENDIENTE' WHERE NRO_REMITO = ?
        ";
        
        $paramsUpdateControl = [$numeroRemito];


        $stmtUpdateControl = sqlsrv_prepare($cid, $sqlUpdateControl, $paramsUpdateControl);
        if ($stmtUpdateControl === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al preparar actualización de control_remitos: " . print_r($errors, true));
        }

        $resultUpdateControl = sqlsrv_execute($stmtUpdateControl);
        if ($resultUpdateControl === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al actualizar control_remitos: " . print_r($errors, true));
        }

        // Guardar observaciones si existen usando parámetros preparados
        if (!empty($observaciones)) {
            $observacionesSanitizadas = $this->sanitizarTexto($observaciones);
            
            $sqlObservaciones = "
                UPDATE SJ_CONTROL_AUDITORIA 
                SET OBSERVACIONES = ?
                WHERE NRO_REMITO = ?
            ";
            
            $paramsObs = [$observacionesSanitizadas, $numeroRemito];
            $stmtObs = sqlsrv_prepare($cid, $sqlObservaciones, $paramsObs);
            if ($stmtObs === false) {
                error_log("Advertencia: No se pudieron preparar las observaciones para el remito $numeroRemito.");
            } else {
                $resultObs = sqlsrv_execute($stmtObs);
                if ($resultObs === false) {
                    error_log("Advertencia: No se pudieron guardar las observaciones para el remito $numeroRemito.");
                }
            }

            // TODO: Enviar observaciones a Tango usando stored procedure
            // Similar a como se hace en EB_Recodificacion2 con EB_InsertarEncMovimiento2
            // Ejemplo:
            // $idUnico = uniqid('AJUSTE_', true);
            // $sqlTangoObs = "EXEC EB_InsertarEncMovimiento2 'admin', 1, 'AJUSTE_INVENTARIO', ?, ?";
            // $paramsTango = [$idUnico, $observacionesSanitizadas];
            // $stmtTango = sqlsrv_prepare($cid, $sqlTangoObs, $paramsTango);
            // sqlsrv_execute($stmtTango);
        }
    }

    /**
     * Sanitiza código de artículo
     * 
     * @param string $codigo
     * @return string
     */
    private function sanitizarCodigoArticulo(string $codigo): string
    {
        $codigo = trim($codigo);
        // Solo permitir alfanuméricos, guiones y guiones bajos
        $codigo = preg_replace('/[^A-Z0-9\-_]/i', '', $codigo);
        // Limitar longitud
        return substr($codigo, 0, 50);
    }

    /**
     * Sanitiza código de depósito
     * 
     * @param string $deposito
     * @return string
     */
    private function sanitizarDeposito(string $deposito): string
    {
        $deposito = trim($deposito);
        // Solo permitir números y letras, máximo 2 caracteres
        $deposito = preg_replace('/[^A-Z0-9]/i', '', $deposito);
        return str_pad(substr($deposito, 0, 2), 2, '0', STR_PAD_LEFT);
    }
}

