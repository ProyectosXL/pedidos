
<?php

class PedidoManager {
    private $conn;
    private $cid;
    
    public function __construct() {
        require_once __DIR__ . '/../../../sistemas/class/conexion.php';
        $this->conn = new Conexion();
        $this->cid = $this->conn->conectar('central');
    }
    
    /**
     * Genera el próximo número de pedido disponible usando talonario 94
     * @param string $talon Tipo de pedido (no usado, siempre usa talonario 94)
     * @return string Número de pedido generado
     */
    public function generarNumeroPedido($talon) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        // Obtener el próximo número y punto de venta del talonario 94
        $sqlProx = "SET DATEFORMAT YMD
                    SELECT PROXIMO, SUCURSAL FROM GVA43 WHERE TALONARIO = '94'";
        
        $resultProx = sqlsrv_query($this->cid, $sqlProx);
        
        if($resultProx === false){
            throw new Exception("Error al obtener próximo número: " . print_r(sqlsrv_errors(), true));
        }
        
        $row = sqlsrv_fetch_array($resultProx, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($resultProx);
        
        if(!$row){
            throw new Exception("No se pudo obtener el próximo número de talonario 94");
        }
        
        $prox = $row['PROXIMO'];
        $ptoVta = $row['SUCURSAL'];
        
        // Descifrar próximo número
        $sqlProxDes = "SET DATEFORMAT YMD
                       SELECT DBO.Fn_obtenerproximonumero(?) AS proxDes";
        
        $resultProxDes = sqlsrv_query($this->cid, $sqlProxDes, array($prox));
        
        if($resultProxDes === false){
            throw new Exception("Error al descifrar próximo número: " . print_r(sqlsrv_errors(), true));
        }
        
        $row = sqlsrv_fetch_array($resultProxDes, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($resultProxDes);
        
        if(!$row){
            throw new Exception("No se pudo descifrar el próximo número");
        }
        
        $proxDes = $row['proxDes'];
        
        // Número de pedido para cargar en Tango
        $numPed = (string)($ptoVta) . (string)($proxDes);
        
        // Número de pedido para cifrar y actualizar tabla GVA43
        $proxPed = substr((string)('0000000') . (string)($proxDes + 1), -8);
        
        // Cifrar el próximo número
        $sqlActuaProx = "SET DATEFORMAT YMD
                         SELECT DBO.Fn_encryptarproximonumero(?) AS proxEnc";
        
        $resultActua = sqlsrv_query($this->cid, $sqlActuaProx, array($proxPed));
        
        if($resultActua === false){
            throw new Exception("Error al cifrar próximo número: " . print_r(sqlsrv_errors(), true));
        }
        
        $row = sqlsrv_fetch_array($resultActua, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($resultActua);
        
        if(!$row){
            throw new Exception("No se pudo cifrar el próximo número");
        }
        
        $proxPedDes = $row['proxEnc'];
        
        // Actualizar tabla con el próximo número
        $sqlActuaProxDes = "SET DATEFORMAT YMD
                            UPDATE GVA43 SET PROXIMO = ? WHERE TALONARIO = '94'";
        
        $resultUpdate = sqlsrv_query($this->cid, $sqlActuaProxDes, array($proxPedDes));
        
        if($resultUpdate === false){
            throw new Exception("Error al actualizar próximo número: " . print_r(sqlsrv_errors(), true));
        }
        
        sqlsrv_free_stmt($resultUpdate);
        
        // Retornar número de pedido con espacio adelante
        return ' ' . $numPed;
    }
    
    /**
     * Crea el encabezado del pedido en GVA21
     * @param array $datos Datos del encabezado
     * @return int ID_GVA21 generado
     */
    public function crearEncabezadoPedido($datos) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $codClient = $datos['codCliente'];
        $vendedor = $datos['codVendedor'] ?? '0';
        $depo = $datos['codSucursal'] ?? '01';
        $numPed = $datos['nroPedido'];
        $tipoPedido = $datos['tipoPedido'] ?? 'GENERAL';
        
        $sql = "SET DATEFORMAT YMD
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
        ES_PEDIDO_WEB, FECHA_O_COMP, WEB_ORDER_ID, 
        TOTAL_DESC_TIENDA, PORCEN_DESC_TIENDA, 
        HORA_INGRESO,
        APLICA_DESCUENTO_CLIENTE,
        ES_CLOUD,
        IMPORTE_DESCUENTO_CLIENTE,
        IMPORTE_DESCUENTO_GENERAL,
        IMPORTE_DESCUENTO_GENERAL_SIN_IMPUESTOS,
        IMPORTE_FLETE,
        IMPORTE_FLETE_SIN_IMPUESTOS,
        IMPORTE_INTERES,
        IMPORTE_INTERES_SIN_IMPUESTOS,
        IMPORTE_RECARGO_GENERAL,
        IMPORTE_RECARGO_GENERAL_SIN_IMPUESTOS,
        PORCENTAJE_DESCUENTO_CLIENTE,
        PORCENTAJE_FLETE,
        PORCENTAJE_INTERES,
        PORCENTAJE_RECARGO_GENERAL,
        TOTAL_EXENTO,
        ID_MONEDA
        )
        VALUES
        (
        1, ?, ?, 
        (SELECT COD_TRANSP FROM GVA14 WHERE COD_CLIENT = ?), (SELECT COD_VENDED FROM GVA14 WHERE COD_CLIENT = ?), 1, 
        (SELECT COND_VTA FROM GVA14 WHERE COD_CLIENT = ?), 1, 2, 
        0, '1800-01-01', '1800-01-01', 
        GETDATE(), ?, 1, 
        (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = ?), ' 000000000000', ?, 
        0, 'E', (SELECT PORC_DESC FROM GVA14 WHERE COD_CLIENT = ?), 
        'A', 'A', 'A', 
        0, 94, 0, 
        '', 3, 0,
        '1800-01-01', '1800-01-01', (SELECT TOP 1 ID_DIRECCION_ENTREGA FROM DIRECCION_ENTREGA WHERE COD_CLIENTE = ?), 
        0, '1800-01-01', 0, 
        0, 0, 
        (SELECT LEFT((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 2)+SUBSTRING((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 4, 2)+RIGHT((CAST((CONVERT(TIME, GETDATE()  )) AS VARCHAR(8))), 2)),
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        1
        );
        SELECT CAST(SCOPE_IDENTITY() AS INT) AS ID_GVA21;";
        
        $params = array(
            $codClient,  // COD_CLIENT
            $depo,       // COD_SUCURS
            $codClient,  // Para COD_TRANSP (SELECT)
            $codClient,  // Para COD_VENDED (SELECT)
            $codClient,  // Para COND_VTA (SELECT)
            'PEDIDO ' . $tipoPedido . ' - MAYORISTAS', // LEYENDA_1
            $codClient,  // Para NRO_LISTA (SELECT)
            $numPed,     // NRO_PEDIDO
            $codClient,  // Para PORC_DESC (SELECT)
            $codClient   // Para ID_DIRECCION_ENTREGA (SELECT)
        );
        
        $stmt = sqlsrv_query($this->cid, $sql, $params);
        if($stmt === false){
            throw new Exception("Error al crear encabezado del pedido: " . print_r(sqlsrv_errors(), true));
        }

        // Avanzar por posibles resultados intermedios hasta encontrar ID_GVA21
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        while(($row === false || $row === null || !isset($row['ID_GVA21'])) && sqlsrv_next_result($stmt) === true){
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        }
        $idGva21 = null;
        if(is_array($row) && array_key_exists('ID_GVA21', $row) && $row['ID_GVA21'] !== null){
            $idGva21 = (int)$row['ID_GVA21'];
        }
        sqlsrv_free_stmt($stmt);

        // Fallback: en algunas instalaciones SCOPE_IDENTITY() no devuelve ID_GVA21 útil.
        // Recuperamos el encabezado recién insertado por NRO_PEDIDO + TALON_PED + COD_CLIENT.
        if($idGva21 === null){
            $sqlBuscaId = "SELECT TOP 1 ID_GVA21
                           FROM GVA21
                           WHERE NRO_PEDIDO = ? AND TALON_PED = 94 AND COD_CLIENT = ?
                           ORDER BY ID_GVA21 DESC";
            $stmtBuscaId = sqlsrv_query($this->cid, $sqlBuscaId, array($numPed, $codClient));

            if($stmtBuscaId === false){
                throw new Exception("Error al buscar ID_GVA21 del encabezado: " . print_r(sqlsrv_errors(), true));
            }

            $rowBuscaId = sqlsrv_fetch_array($stmtBuscaId, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmtBuscaId);

            if(!$rowBuscaId || !isset($rowBuscaId['ID_GVA21'])){
                throw new Exception("No se pudo obtener ID_GVA21 del encabezado");
            }

            $idGva21 = (int)$rowBuscaId['ID_GVA21'];
        }

        return $idGva21;
    }
    
    /**
     * Agrega un artículo al detalle del pedido en GVA03
     * @param array $datos Datos del artículo
     * @return bool
     */
    public function agregarArticuloPedido($datos) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $codClient = $datos['codCliente'];
        $numPed = $datos['nroPedido'];
        $codArt = $datos['codArticulo'];
        $cantidad = $datos['cantidad'];
        $renglon = $datos['renglon'];
        $esKit = $datos['esKit'] ?? false;
        $codKit = $datos['codArticuloKit'] ?? '';
        $renglonPadre = $datos['renglonPadre'] ?? 0;
        $idGva21 = $datos['idGva21'] ?? null;

        if($idGva21 === null){
            throw new Exception("Falta ID_GVA21 para insertar detalle en GVA03");
        }
        
        if($esKit && $renglonPadre > 0) {
            // Insertar componente de kit
            $promocion = 0;
            $kitCompleto = 1;
            $sql = "INSERT INTO GVA03
            (
            CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE, 
            PRECIO, TALON_PED, 
            CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
            PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM, COD_ARTICU_KIT, NRO_ORDEN,
            IMPORTE_CON_IMPUESTOS, IMPORTE_PROPORCIONADO, IMPORTE_SIN_IMPUESTOS, ID_GVA21
            )
            VALUES
            (
            1, ?, ?, ?, ?, ?, ?, 0, ?, ?, 0, 0, 
            (SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = ? AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = ?)), 94,
            0, 0, 0, 0, 0, 0, 7, 7, 'P', ?,
            $promocion, 0, $kitCompleto, 0, 0, 0, 0, ?, 0,
            0, 0, 0, ?
            )";
            
            $params = array(
                $cantidad, $cantidad, $cantidad, $cantidad, $cantidad, // CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F
                $codArt,   // COD_ARTICU
                $renglon,  // N_RENGLON
                $numPed,   // NRO_PEDIDO
                $codArt,   // Para SELECT PRECIO
                $codClient, // Para SELECT NRO_LISTA
                $renglonPadre, // RENGL_PADR
                $codKit,   // COD_ARTICU_KIT
                $idGva21   // ID_GVA21
            );
        } elseif($esKit) {
            // Insertar renglón padre de kit
            $promocion = 1;
            $kitCompleto = 1;
            $sql = "INSERT INTO GVA03
            (
            CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE, 
            PRECIO, TALON_PED, 
            CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
            PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM, COD_ARTICU_KIT, NRO_ORDEN,
            IMPORTE_CON_IMPUESTOS, IMPORTE_PROPORCIONADO, IMPORTE_SIN_IMPUESTOS, ID_GVA21
            )
            VALUES
            (
            1, ?, ?, ?, ?, ?, ?, 0, ?, ?, 0, 0, 
            (SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = ? AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = ?)), 94,
            0, 0, 0, 0, 0, 0, 7, 7, 'V', 0,
            $promocion, 0, $kitCompleto, 0, 0, 0, 0, ?, 0,
            0, 0, 0, ?
            )";
            
            $params = array(
                $cantidad, $cantidad, $cantidad, $cantidad, $cantidad, // CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F
                $codArt,  
                $renglon,  
                $numPed,   
                $codArt,
                $codClient,
                $codArt,
                $idGva21
            );
        } else {
            // Insertar artículo normal
            $promocion = 0;
            $kitCompleto = 0;
            $sql = "INSERT INTO GVA03
            (
            CAN_EQUI_V, CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F, COD_ARTICU, DESCUENTO, N_RENGLON, NRO_PEDIDO, PEN_REM_FC, PEN_FAC_RE, 
            PRECIO, TALON_PED, 
            CANT_A_DES_2, CANT_A_FAC_2, CANT_PEDID_2, CANT_PEN_D_2, CANT_PEN_F_2, PEN_REM_FC_2, ID_MEDIDA_VENTAS, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, RENGL_PADR,
            PROMOCION, PRECIO_ADICIONAL_KIT, KIT_COMPLETO, INSUMO_KIT_SEPARADO, PRECIO_LISTA, PRECIO_BONIF, DESCUENTO_PARAM, NRO_ORDEN,
            IMPORTE_CON_IMPUESTOS, IMPORTE_PROPORCIONADO, IMPORTE_SIN_IMPUESTOS, ID_GVA21
            )
            VALUES
            (
            1, ?, ?, ?, ?, ?, ?, 0, ?, ?, 0, 0, 
            (SELECT PRECIO FROM GVA17 WHERE COD_ARTICU = ? AND NRO_DE_LIS = (SELECT NRO_LISTA FROM GVA14 WHERE COD_CLIENT = ?)), 94,
            0, 0, 0, 0, 0, 0, 7, 7, 'V', 0,
            $promocion, 0, $kitCompleto, 0, 0, 0, 0, 0,
            0, 0, 0, ?
            )";
            
            $params = array(
                $cantidad, $cantidad, $cantidad, $cantidad, $cantidad, // CANT_A_DES, CANT_A_FAC, CANT_PEDID, CANT_PEN_D, CANT_PEN_F
                $codArt,   // COD_ARTICU
                $renglon,  // N_RENGLON
                $numPed,   // NRO_PEDIDO
                $codArt,   // Para SELECT PRECIO
                $codClient, // Para SELECT NRO_LISTA
                $idGva21   // ID_GVA21
            );
        }
        
        $stmt = sqlsrv_query($this->cid, $sql, $params);
        
        if($stmt === false){
            throw new Exception("Error al agregar artículo al pedido: " . print_r(sqlsrv_errors(), true));
        }
        
        sqlsrv_free_stmt($stmt);
        return true;
    }
    
    /**
     * Filtra de una lista de productos aquellos que son componentes de kits
     * también presentes en la misma lista. Así se evita mostrar tanto el kit
     * como sus partes al mismo tiempo.
     * @param array $productos Array de productos con clave COD_ARTICU
     * @return array Productos sin los componentes de kits
     */
    public function filtrarComponentesKit($productos) {
        if(empty($productos) || $this->cid === false) return $productos;

        $codigos = array_map(function($p) { return trim($p['COD_ARTICU']); }, $productos);
        $codigos = array_unique($codigos);

        if(empty($codigos)) return $productos;

        $placeholders = implode(',', array_fill(0, count($codigos), '?'));

        $sql = "SELECT DISTINCT COD_INSUMO
                FROM STA03
                WHERE COD_ARTICU IN ($placeholders)
                AND COD_INSUMO IN ($placeholders)";

        $params = array_merge(array_values($codigos), array_values($codigos));
        $stmt = sqlsrv_query($this->cid, $sql, $params);

        if($stmt === false) return $productos;

        $componentesCodigos = array();
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $componentesCodigos[] = trim($row['COD_INSUMO']);
        }
        sqlsrv_free_stmt($stmt);

        if(empty($componentesCodigos)) return $productos;

        return array_values(array_filter($productos, function($p) use ($componentesCodigos) {
            return !in_array(trim($p['COD_ARTICU']), $componentesCodigos);
        }));
    }

    /**
     * Verifica si un artículo es un kit y obtiene sus componentes
     * @param string $codArticulo
     * @return array|false Array de componentes o false si no es kit
     */
    public function obtenerComponentesKit($codArticulo) {
        if($this->cid === false){
            return false;
        }
        
        $sql = "SELECT COD_INSUMO, CANTIDAD
                FROM STA03 
                WHERE COD_ARTICU = ?";
        
        $stmt = sqlsrv_query($this->cid, $sql, array($codArticulo));
        
        if($stmt === false){
            return false;
        }
        
        $componentes = array();
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $componentes[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);
        
        return count($componentes) > 0 ? $componentes : false;
    }
    
    /**
     * Compromete el stock del artículo usando el SP del sistema
     * @param float $cantidad
     * @param string $codArticulo
     * @param string $deposito
     * @return bool
     */
    public function comprometerStock($cantidad, $codArticulo, $deposito) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        // Ejecutar SP de compromiso de stock (actualiza CANT_COMP en STA19)
        $sql = "EXEC SJ_COMP_STOCK ?, ?, ?";
        $params = array($cantidad, $codArticulo, $deposito);
        
        $stmt = sqlsrv_query($this->cid, $sql, $params);
        
        if($stmt === false){
            throw new Exception("Error al comprometer stock: " . print_r(sqlsrv_errors(), true));
        }
        
        sqlsrv_free_stmt($stmt);
        
        return true;
    }
    
    /**
     * Recalcula GVA21.TOTAL_PEDI como SUM(CANT_PEDID * PRECIO) de sus renglones en GVA03.
     * Los componentes de kit (RENGL_PADR > 0) se excluyen: el precio del kit ya está en el renglón padre.
     * @param int $idGva21
     * @return bool
     */
    public function actualizarTotalPedido($idGva21) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $sql = "UPDATE GVA21
                SET TOTAL_PEDI = (
                    SELECT ISNULL(SUM(CANT_PEDID * ISNULL(PRECIO, 0)), 0)
                    FROM GVA03
                    WHERE ID_GVA21 = ? AND ISNULL(RENGL_PADR, 0) = 0
                )
                WHERE ID_GVA21 = ?";
        
        $stmt = sqlsrv_query($this->cid, $sql, array($idGva21, $idGva21));
        
        if($stmt === false){
            throw new Exception("Error al actualizar total del pedido: " . print_r(sqlsrv_errors(), true));
        }
        
        sqlsrv_free_stmt($stmt);
        return true;
    }
    
    /**
     * Crea un pedido completo con todos sus artículos
     * @param array $datosEncabezado Datos del encabezado
     * @param array $articulos Array de artículos con cantidad
     * @return string Número de pedido creado
     */
    public function crearPedidoCompleto($datosEncabezado, $articulos) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        try {
            // Iniciar transacción
            sqlsrv_begin_transaction($this->cid);
            
            // Generar número de pedido
            $nroPedido = $this->generarNumeroPedido($datosEncabezado['talon']);
            $datosEncabezado['nroPedido'] = $nroPedido;
            
            // Crear encabezado
            $idGva21 = $this->crearEncabezadoPedido($datosEncabezado);
            
            $codClient = $datosEncabezado['codCliente'];
            $depo = $datosEncabezado['codSucursal'] ?? '01';
            
            // Agregar artículos
            $renglon = 1;
            foreach($articulos as $articulo) {
                if($articulo['cantidad'] <= 0) {
                    continue; // Saltar artículos sin cantidad
                }
                
                // Verificar si es un kit
                $componentes = $this->obtenerComponentesKit($articulo['codArticulo']);
                
                if($componentes) {
                    // Es un kit - primero agregar el kit completo
                    $datosKit = array(
                        'nroPedido' => $nroPedido,
                        'codCliente' => $codClient,
                        'renglon' => $renglon,
                        'codArticulo' => $articulo['codArticulo'],
                        'cantidad' => $articulo['cantidad'],
                        'esKit' => true,
                        'codArticuloKit' => $articulo['codArticulo'],
                        'renglonPadre' => 0,
                        'idGva21' => $idGva21
                    );
                    
                    $this->agregarArticuloPedido($datosKit);
                    
                    // Comprometer stock del kit
                    $this->comprometerStock(
                        $articulo['cantidad'],
                        $articulo['codArticulo'],
                        $depo
                    );
                    
                    $renglonPadre = $renglon;
                    $renglon++;
                    
                    // Luego agregar los componentes
                    foreach($componentes as $componente) {
                        $datosComponente = array(
                            'nroPedido' => $nroPedido,
                            'codCliente' => $codClient,
                            'renglon' => $renglon++,
                            'codArticulo' => $componente['COD_INSUMO'],
                            'cantidad' => ((float)$componente['CANTIDAD']) * $articulo['cantidad'],
                            'esKit' => true,
                            'codArticuloKit' => $articulo['codArticulo'],
                            'renglonPadre' => $renglonPadre,
                            'idGva21' => $idGva21
                        );
                        
                        $this->agregarArticuloPedido($datosComponente);
                        
                        // Comprometer stock del componente
                        $this->comprometerStock(
                            ((float)$componente['CANTIDAD']) * $articulo['cantidad'],
                            $componente['COD_INSUMO'],
                            $depo
                        );
                    }
                } else {
                    // No es kit - agregar artículo normal
                    $datosArticulo = array(
                        'nroPedido' => $nroPedido,
                        'codCliente' => $codClient,
                        'renglon' => $renglon++,
                        'codArticulo' => $articulo['codArticulo'],
                        'cantidad' => $articulo['cantidad'],
                        'esKit' => false,
                        'idGva21' => $idGva21
                    );
                    
                    $this->agregarArticuloPedido($datosArticulo);
                    
                    // Comprometer stock
                    $this->comprometerStock(
                        $articulo['cantidad'],
                        $articulo['codArticulo'],
                        $depo
                    );
                }
            }
            
            $this->actualizarTotalPedido($idGva21);
            
            // Commit transacción
            sqlsrv_commit($this->cid);
            
            return $nroPedido;
            
        } catch(Exception $e) {
            // Rollback en caso de error
            sqlsrv_rollback($this->cid);
            throw $e;
        }
    }
    
    /**
     * Obtiene productos para el tipo de pedido especificado
     * @param int $tipoPedido 200=General, otros tipos según necesidad
     * @param string $codClient Código del cliente
     * @return array Array de productos
     */
    public function obtenerProductosPedido($tipoPedido, $codClient) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $sql = "SET DATEFORMAT YMD\n\nEXEC SJ_TIPO_PEDIDO_1 ?, ?";
        $stmt = sqlsrv_query($this->cid, $sql, array($tipoPedido, $codClient));
        
        if($stmt === false){
            throw new Exception("Error al obtener productos: " . print_r(sqlsrv_errors(), true));
        }
        
        // Necesario para stored procedures - avanzar al primer resultado
        sqlsrv_next_result($stmt);
        
        $productos = array();
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $productos[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);
        
        return $productos;
    }
    
    /**
     * Obtiene productos de accesorios (usa SJ_TIPO_PEDIDO_2)
     * @param int $tipoPedido 200=Accesorios
     * @param string $codClient Código del cliente
     * @return array Array de productos
     */
    public function obtenerProductosAccesorios($tipoPedido, $codClient) {
        if($this->cid === false){
            throw new Exception("Error al conectar con la base de datos");
        }
        
        $sql = "SET DATEFORMAT YMD\n\nEXEC SJ_TIPO_PEDIDO_2 ?, ?";
        $stmt = sqlsrv_query($this->cid, $sql, array($tipoPedido, $codClient));
        
        if($stmt === false){
            throw new Exception("Error al obtener productos de accesorios: " . print_r(sqlsrv_errors(), true));
        }
        
        // Necesario para stored procedures - avanzar al primer resultado
        sqlsrv_next_result($stmt);
        
        $productos = array();
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
            $productos[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);

        // Filtrar componentes de kits: si la lista contiene tanto el kit como
        // sus unidades individuales, se muestran solo los kits.
        $productos = $this->filtrarComponentesKit($productos);
        
        return $productos;
    }
    
    /**
     * Cierra la conexión
     */
    public function cerrarConexion() {
        if($this->cid !== false){
            sqlsrv_close($this->cid);
        }
    }
    
    public function __destruct() {
        $this->cerrarConexion();
    }
}

?>
