<?php

class Pedido {

    function __construct(){

        require_once __DIR__.'/conexion.php';
        $this->conn = new Conexion;
        
    }

    public function listarPedido($tipoPedido, $tipo_cli, $suc, $codClient, $esOutlet = null, $db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            // Verificar conexión
            if ($cid === false) {
                return [];
            }
    
            switch ($tipoPedido) {
                case 1:
                    $_SESSION['tipo_pedido'] = 'GENERAL';
                    break;
                case 2:
                    $_SESSION['tipo_pedido'] = 'ACCESORIOS';
                    break;
                case 3:
                    $_SESSION['tipo_pedido'] = 'OUTLET';
                    break;
            }
    
            // Construir la consulta SQL
            if ($esOutlet != null && $tipoPedido == 1) {
                $sql = "EXEC SJ_TIPO_PEDIDO_{$tipoPedido}_OUTLET '{$suc}', '{$codClient}'";
            } else {
                $sql = "EXEC SJ_TIPO_PEDIDO_{$tipoPedido} '{$suc}', '{$codClient}'";
            }
    
            // Para debug: Imprimir la consulta SQL
            error_log("SQL Query: " . $sql);
            
            ini_set('max_execution_time', 300);
            
            // Ejecutar la consulta
            $stmt = sqlsrv_query($cid, $sql);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar la consulta:\n";
                foreach ($errors as $error) {
                    $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
                    $errorMessage .= "Code: " . $error['code'] . "\n";
                    $errorMessage .= "Message: " . $error['message'] . "\n";
                }
                throw new Exception($errorMessage);
            }
    
            $v = [];
            
            // Si no es Uruguay y hay resultados, obtener el siguiente conjunto
            if ($db != 'uy' && $stmt !== false) {
                $next_result = sqlsrv_next_result($stmt);
                if ($next_result === false) {
                    $errors = sqlsrv_errors();
                    error_log("Error en next_result: " . print_r($errors, true));
                }
            }
    
            // Obtener los resultados
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $v[] = $row;
            }
    
            return $v;
        }
        catch (Exception $e) {
            error_log("Error en listarPedido: " . $e->getMessage());
            return [];
        }
        finally {
            // Liberar recursos
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    public function listarPedidoCordoba($db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            // Verificar conexión
            if ($cid === false) {
                return [];
            }
    
            $_SESSION['tipo_pedido'] = 'GENERAL';
            
            $sql = "
            SET DATEFORMAT YMD
            
            EXEC SJ_TIPO_PEDIDO_CORDOBA_1bis
            
            ";
    
            // Para debug: Imprimir la consulta SQL
            error_log("SQL Query Córdoba: " . $sql);
            
            ini_set('max_execution_time', 300);
            
            // Ejecutar la consulta
            $stmt = sqlsrv_query($cid, $sql);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar la consulta Córdoba:\n";
                foreach ($errors as $error) {
                    $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
                    $errorMessage .= "Code: " . $error['code'] . "\n";
                    $errorMessage .= "Message: " . $error['message'] . "\n";
                }
                throw new Exception($errorMessage);
            }
    
            $v = [];
            
            // Si no es Uruguay y hay resultados, obtener el siguiente conjunto
            if ($db != 'uy' && $stmt !== false) {
                $next_result = sqlsrv_next_result($stmt);
                if ($next_result === false) {
                    $errors = sqlsrv_errors();
                    error_log("Error en next_result Córdoba: " . print_r($errors, true));
                }
            }
            
            // Obtener los resultados
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $v[] = $row;
            }
    
            return $v;
        }
        catch (Exception $e) {
            error_log("Error en listarPedidoCordoba: " . $e->getMessage());
            return [];
        }
        finally {
            // Liberar recursos
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    public function listarPedidoCordobaAccesorios($db = 'central') {
        try {
            $cid = $this->conn->conectar($db);
            
            // Verificar conexión
            if ($cid === false) {
                return [];
            }
    
            $_SESSION['tipo_pedido'] = 'ACCESORIOS';
            
            $sql = "
            SET DATEFORMAT YMD
            
            EXEC SJ_TIPO_PEDIDO_CORDOBA_2_bis
            
            ";
    
            // Para debug: Imprimir la consulta SQL
            error_log("SQL Query Córdoba Accesorios: " . $sql);
            
            ini_set('max_execution_time', 300);
            
            // Ejecutar la consulta
            $stmt = sqlsrv_query($cid, $sql);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = "Error al ejecutar la consulta Córdoba Accesorios:\n";
                foreach ($errors as $error) {
                    $errorMessage .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
                    $errorMessage .= "Code: " . $error['code'] . "\n";
                    $errorMessage .= "Message: " . $error['message'] . "\n";
                }
                throw new Exception($errorMessage);
            }
    
            $v = [];
            
            // Si no es Uruguay y hay resultados, obtener el siguiente conjunto
            if ($db != 'uy' && $stmt !== false) {
                $next_result = sqlsrv_next_result($stmt);
                if ($next_result === false) {
                    $errors = sqlsrv_errors();
                    error_log("Error en next_result Córdoba Accesorios: " . print_r($errors, true));
                }
            }
            
            // Obtener los resultados
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $v[] = $row;
            }
    
            return $v;
        }
        catch (Exception $e) {
            error_log("Error en listarPedidoCordobaAccesorios: " . $e->getMessage());
            return [];
        }
        finally {
            // Liberar recursos
            if (isset($stmt) && $stmt !== false) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }

    public function traerHistorial($codClient, $usuarioUy = 0){



        if($usuarioUy == 1){

            $cid = $this->conn->conectar('uy');
            $codClient = '000000';
            $talonarios = '98';

        }else{

            $cid = $this->conn->conectar('central');
            $talonarios = '96, 97';
        }

    
        
        $sql=
        "
        SET DATEFORMAT YMD

        SELECT CAST(FECHA_PEDI AS DATE)FECHA, A.NRO_PEDIDO, LEYENDA_1, B.CANT FROM GVA21 A
        INNER JOIN
        (
            SELECT NRO_PEDIDO, CAST(SUM(CANT_PEDID) AS FLOAT) CANT FROM GVA03 WHERE TALON_PED IN ($talonarios) GROUP BY NRO_PEDIDO
        )B
        ON A.NRO_PEDIDO = B.NRO_PEDIDO
        WHERE COD_CLIENT = '$codClient' AND FECHA_PEDI > (GETDATE()-42) AND A.TALON_PED IN ($talonarios)
        ORDER BY 1 desc, 2 desc

        ";
            
   
        try {
            $stmt = sqlsrv_query($cid, $sql);
            $v = [];
            while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        }
        catch (\Throwable $th) {
            die("Error en sqlsrv_exec");
        };


        
    }

    public function traerDetallePedido($pedido, $suc, $usuarioUy = 0){

        
       

        if($usuarioUy == 1){

            $cid = $this->conn->conectar('uy');
            $suc = '000000';
            $talonarios = '98';

        }else{

            $cid = $this->conn->conectar('central');
            $talonarios = '96, 97';
        }

        
        $sql=
        "
        SET DATEFORMAT YMD

        SELECT CAST(A.FECHA_PEDI AS DATE)FECHA, B.COD_ARTICU, C.DESCRIPCIO, CAST(B.CANT_PEDID AS FLOAT) CANT FROM GVA03 B
        INNER JOIN GVA21 A
        ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
        INNER JOIN STA11 C
        ON B.COD_ARTICU = C.COD_ARTICU
        WHERE A.TALON_PED IN ($talonarios) AND A.NRO_PEDIDO = '$pedido'
        AND A.COD_CLIENT = '$suc'
        ";


        try{

            $stmt = sqlsrv_query($cid, $sql);
            
            while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;
        } 
        catch(\Throwable $th){
            die("Error en sqlsrv_exec");
        };
    }

    public function traerHistorialRubro($codClient, $usuarioUy = 0){



        if($usuarioUy == 1){

            $cid = $this->conn->conectar('uy');
            $codClient = '000000';
            $talonarios = '98';

        }else{

            $cid = $this->conn->conectar('central');
            $talonarios = '1, 96, 97';
        }

    
        
        $sql=
        "
        SET DATEFORMAT YMD

        SELECT CAST(FECHA_PEDI AS DATE)FECHA, RUBRO, B.CANT FROM GVA21 A
        INNER JOIN
        (
            SELECT NRO_PEDIDO, TALON_PED, CAST(SUM(CANT_PEDID) AS FLOAT) CANT, RUBRO FROM GVA03 A
			LEFT JOIN SOF_RUBROS_TANGO B ON A.COD_ARTICU = B.COD_ARTICU
			WHERE TALON_PED IN ($talonarios) 
			GROUP BY NRO_PEDIDO, TALON_PED, RUBRO
        )B
        ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
        WHERE COD_CLIENT = '$codClient' AND FECHA_PEDI > (GETDATE()-42) AND A.TALON_PED IN ($talonarios)
        ORDER BY 1 desc, 2 desc

        ";
            
   
        try {
            $stmt = sqlsrv_query($cid, $sql);
            $v = [];
            while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        }
        catch (\Throwable $th) {
            die("Error en sqlsrv_exec");
        };


        
    }

    public function traerRubros($codClient, $usuarioUy = 0) {
        if($usuarioUy == 1){
            $cid = $this->conn->conectar('uy');
            $codClient = '000000';
            $talonarios = '98';
        } else {
            $cid = $this->conn->conectar('central');
            $talonarios = '96, 97';
        }
        
        $sql = "
            SET DATEFORMAT YMD
            SELECT DISTINCT(RUBRO) RUBRO FROM GVA21 A
            INNER JOIN
            (
                SELECT NRO_PEDIDO, TALON_PED, CAST(SUM(CANT_PEDID) AS FLOAT) CANT, RUBRO FROM GVA03 A
                LEFT JOIN SOF_RUBROS_TANGO B ON A.COD_ARTICU = B.COD_ARTICU
                WHERE TALON_PED IN ($talonarios) 
                GROUP BY NRO_PEDIDO, TALON_PED, RUBRO
            )B
            ON A.NRO_PEDIDO = B.NRO_PEDIDO AND A.TALON_PED = B.TALON_PED
            WHERE COD_CLIENT = '$codClient' AND FECHA_PEDI > (GETDATE()-42) AND A.TALON_PED IN ($talonarios)
            ORDER BY RUBRO
        ";
        
        try {
            $stmt = sqlsrv_query($cid, $sql);
            $rubros = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                if (!empty($row['RUBRO'])) {
                    $rubros[] = trim($row['RUBRO']);
                }
            }
            return $rubros;
        }
        catch (\Throwable $th) {
            error_log("Error en traerRubros: " . $th->getMessage());
            return [];
        }
    }

    public function traerVentasRubro($codClient, $suc, $usuarioUy = 0) {
        if($usuarioUy == 1){
            $cid = $this->conn->conectar('uy');
        } else {
            $cid = $this->conn->conectar('central');
        }
        
        $sql = "
            SELECT CAST(FECHA_EMIS AS DATE) FECHA, SUM(CANTIDAD) CANT_VENTAS, RUBRO FROM 
            (
                SELECT A.FECHA_EMIS, 
                       CAST(CASE WHEN B.T_COMP LIKE 'NC%' THEN B.CANTIDAD *-1 ELSE B.CANTIDAD END AS FLOAT) CANTIDAD, 
                       C.RUBRO 
                FROM [LAKERBIS].LOCALES_LAKERS.DBO.CTA02 A          
                INNER JOIN [LAKERBIS].LOCALES_LAKERS.DBO.CTA03 B ON A.FECHA_EMIS = B.FECHA_MOV AND A.N_COMP = B.N_COMP AND A.T_COMP = B.T_COMP   
                INNER JOIN SOF_RUBROS_TANGO C ON B.COD_ARTICU = C.COD_ARTICU
                WHERE A.FECHA_EMIS >= GETDATE()-42 AND A.NRO_SUCURS = '$suc'
            ) A
            GROUP BY FECHA_EMIS, RUBRO
            ORDER BY FECHA_EMIS
        ";
        
        try {
            $stmt = sqlsrv_query($cid, $sql);
            $ventas = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $ventas[] = [
                    'FECHA' => $row['FECHA'],
                    'CANT_VENTAS' => (float)$row['CANT_VENTAS'],
                    'RUBRO' => trim($row['RUBRO'])
                ];
            }
            return $ventas;
        }
        catch (\Throwable $th) {
            error_log("Error en traerVentasRubro: " . $th->getMessage());
            return [];
        }
    }

}