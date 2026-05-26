<?php

class Remito {

    function __construct(){

        require_once __DIR__.'/conexion.php';
        $this->conn = new Conexion;
        
    }

    private function getArray($sql){

        $cid = $this->conn->conectar('central');

        try {
            $stmt = sqlsrv_query($cid, $sql);

            $v = [];

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;
                
            }

            return $v;

        }
        catch (\Throwable $th) {
            return ("Error en sqlsrv_exec");
        };

    }

    private function insertDatos($sql){
        $cid = $this->conn->conectar('central');
        try{
            sqlsrv_exec($cid,$sql);
        } catch (\Throwable $th) {
            die("Error en sqlsrv_exec");
        }
    }
    
    /**
     * Prueba la conexión con la base de datos
     * @param string $db Base de datos a testear ('central', 'uy', o 'local')
     * @return bool True si la conexión es exitosa, False si falla
     */
    public function testConnection($db = 'central'){
        try {
            $cid = $this->conn->conectar($db);
            
            if(!$cid) {
                return false;
            }

            // Hacer una consulta simple para verificar que la conexión funciona
            $testSql = "SELECT 1 as test";
            
            // Para conexiones SQL Server (central, uy, local)
            if($db == 'central' || $db == 'uy' || $db == 'local') {
                $result = sqlsrv_query($cid, $testSql);
                
                if ($result === false) {
                    return false;
                }

                // Verificar que podemos obtener el resultado
                $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                
                if ($row && isset($row['test']) && $row['test'] == 1) {
                    return true;
                }
            }
            // Para otras conexiones (si las hubiera)
            else {
                // Aquí podrías agregar lógica para otros tipos de BD si es necesario
                return false;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error en testConnection para $db: " . $e->getMessage());
            return false;
        } catch (Error $e) {
            error_log("Error fatal en testConnection para $db: " . $e->getMessage());
            return false;
        }
    }

    public function listarUsuarios($nroSucurs){

        $sql = "SELECT CASE 
                    WHEN CHARINDEX(' -', NOMBRE_VEN) > 0 THEN LEFT(NOMBRE_VEN, CHARINDEX(' -', NOMBRE_VEN) - 1)
                    WHEN CHARINDEX('-', NOMBRE_VEN) > 0 THEN LEFT(NOMBRE_VEN, CHARINDEX('-', NOMBRE_VEN) - 1)
                    ELSE NOMBRE_VEN
                END AS NOMBRE_VEN, A.BLOQUE, B.DESC_SUCURSAL, A.XML_CA_1118_NUM_SUCURSAL NRO_SUCURSAL FROM 
                (
                SELECT COD_VENDED BLOQUE, NOMBRE_VEN,
                GVA23_CAMPOS_ADICIONALES.XML_CA.value('CA_1118_NUM_SUCURSAL', 'VARCHAR(6)') XML_CA_1118_NUM_SUCURSAL FROM GVA23
                OUTER APPLY GVA23.CAMPOS_ADICIONALES.nodes('CAMPOS_ADICIONALES') as GVA23_CAMPOS_ADICIONALES(XML_CA)
                WHERE INHABILITA = 0
                ) A
                INNER JOIN (SELECT * FROM [LAKERBIS].LOCALES_LAKERS.DBO.SUCURSALES_LAKERS WHERE CANAL = 'PROPIOS') B ON A.XML_CA_1118_NUM_SUCURSAL = B.NRO_SUCURSAL
                WHERE XML_CA_1118_NUM_SUCURSAL IS NOT NULL AND XML_CA_1118_NUM_SUCURSAL = $nroSucurs
                
                    UNION ALL
                
                SELECT CASE 
                    WHEN CHARINDEX(' -', NOMBRE_VEN) > 0 THEN LEFT(NOMBRE_VEN, CHARINDEX(' -', NOMBRE_VEN) - 1)
                    WHEN CHARINDEX('-', NOMBRE_VEN) > 0 THEN LEFT(NOMBRE_VEN, CHARINDEX('-', NOMBRE_VEN) - 1)
                    ELSE NOMBRE_VEN
                END AS NOMBRE_VEN, A.BLOQUE, B.DESC_SUCURSAL, A.XML_CA_1118_NUM_SUCURSAL NRO_SUCURSAL FROM 
                (
                SELECT COD_VENDED BLOQUE, NOMBRE_VEN,
                GVA23_CAMPOS_ADICIONALES.XML_CA.value('CA_1118_NUM_SUCURSAL', 'VARCHAR(6)') XML_CA_1118_NUM_SUCURSAL FROM TASKY_SA.DBO.GVA23
                OUTER APPLY GVA23.CAMPOS_ADICIONALES.nodes('CAMPOS_ADICIONALES') as GVA23_CAMPOS_ADICIONALES(XML_CA)
                WHERE INHABILITA = 0 AND COD_VENDED LIKE '6%'
                ) A
                INNER JOIN (SELECT * FROM [LAKERBIS].LOCALES_LAKERS.DBO.SUCURSALES_LAKERS WHERE CANAL = 'EXTERIOR') B ON A.XML_CA_1118_NUM_SUCURSAL = B.NRO_SUCURSAL
                WHERE XML_CA_1118_NUM_SUCURSAL IS NOT NULL
                AND XML_CA_1118_NUM_SUCURSAL = $nroSucurs
                ORDER BY 1
        ";

        $result = $this->getArray($sql);
        return $result;

    }

    public function traerHistoricos($codClient, $desde, $hasta){
       
        $sql=

        "
        SET DATEFORMAT YMD

        SELECT FECHA_CONTROL, DESC_SUCURSAL, FECHA_REM, NOMBRE_VEN, NRO_REMITO, SUM(CANT_CONTROL) CANT_CONTROL, SUM(CANT_REM) CANT_REM, SUM(DIFERENCIA) DIFERENCIA, OBSERVAC_LOGISTICA, NRO_AJUSTE, ULTIMO_CHAT ,AJUSTAR FROM 
		(
			SELECT CAST(STUFF(STUFF(CONVERT(VARCHAR(10), FECHA_CONTROL, 112),5,0,'-'),8,0,'-') AS DATE) FECHA_CONTROL, D.DESC_SUCURSAL, CAST(A.FECHA_REM AS DATE) FECHA_REM, NOMBRE_VEN, A.NRO_REMITO, A.SUC_ORIG, A.SUC_DESTIN, 
			A.CANT_CONTROL, A.CANT_REM, A.CANT_CONTROL-A.CANT_REM DIFERENCIA, A.USUARIO_LOCAL, A.OBSERVAC_LOGISTICA, NRO_AJUSTE,
			ISNULL((CASE WHEN C.USER_CHAT IN ('ramiro','eduardo','Agustinal') THEN 0 WHEN C.USER_CHAT NOT IN ('ramiro','eduardo','Agustinal') THEN 1 END), 2) ULTIMO_CHAT, AJUSTAR
			FROM SJ_CONTROL_AUDITORIA A
			INNER JOIN RO_V_GVA23 B ON A.USUARIO_LOCAL COLLATE Latin1_General_BIN = B.COD_VENDED
			LEFT JOIN SJ_CONTROL_AUDIRTORIA_CHAT_ULTIMO_MSG C ON A.NRO_REMITO = C.NRO_REMITO COLLATE Latin1_General_BIN
			LEFT JOIN SUCURSAL D ON A.SUC_ORIG = D.NRO_SUCURSAL
			WHERE A.COD_CLIENT LIKE '$codClient' --AND A.NRO_REMITO = 'R0014500024535'
		) A
		WHERE FECHA_REM >= GETDATE()-180 AND (CAST( A.FECHA_CONTROL AS DATE) BETWEEN '$desde' AND '$hasta')
		GROUP BY FECHA_CONTROL, DESC_SUCURSAL, FECHA_REM, NOMBRE_VEN, NRO_REMITO, OBSERVAC_LOGISTICA, NRO_AJUSTE, ULTIMO_CHAT, AJUSTAR
		ORDER BY FECHA_CONTROL

        ";

        $array = $this->getArray($sql);    

        return $array;

    }

    public function traerHistoricosDetalle($numRem){

        $sql=
            "
            SET DATEFORMAT YMD

            SELECT A.*, B.DESCRIPCIO FROM
            (
                SELECT ISNULL(COD_CLIENT, COD_PRO_CL) COD_CLIENT, ISNULL(A.SUC_DESTIN, B.SUC_DESTIN) SUC_DESTIN, FECHA_CONTROL, ISNULL(FECHA_REM, B.FECHA_MOV) FECHA_REM, NOMBRE_VEN, A.NRO_REMITO, ISNULL(A.COD_ARTICU, B.COD_ARTICULO) COD_ARTICU, ISNULL(A.CANT_CONTROL, 0) CANT_CONTROL, 
                ISNULL(A.CANT_REM, B.CANTIDAD) CANT_REM, ISNULL(DIFERENCIA, 0-B.CANTIDAD) DIFERENCIA, ISNULL(PARTIDA, '') PARTIDA, CASE WHEN A.COD_ARTICU IS NULL THEN 0 ELSE 1 END AUDITADO  FROM 
                (
                    SELECT COD_CLIENT, SUC_DESTIN, FECHA_CONTROL, FECHA_REM, NOMBRE_VEN, NRO_REMITO, COD_ARTICU, MAX(CANT_CONTROL) CANT_CONTROL, SUM(CANT_REM) CANT_REM, MAX(CANT_CONTROL) - SUM(CANT_REM) DIFERENCIA, MAX(PARTIDA) PARTIDA
                    FROM
                    (
                        SELECT 
                        COD_CLIENT, SUC_DESTIN, 
                        FECHA_CONTROL, CAST(A.FECHA_REM AS DATE) FECHA_REM, 
                        NOMBRE_VEN, A.NRO_REMITO, 
                        A.COD_ARTICU, 
                        A.CANT_CONTROL, A.CANT_REM, A.CANT_CONTROL - A.CANT_REM DIFERENCIA, ISNULL(E.ULTIMA_PARTIDA, '') PARTIDA
                                                                
                        FROM SJ_CONTROL_AUDITORIA A
                                    
                        INNER JOIN RO_V_GVA23 D
                        ON A.USUARIO_LOCAL COLLATE Latin1_General_BIN = D.COD_VENDED
                        LEFT JOIN SOF_PARTIDAS E
                        ON A.COD_ARTICU = E.COD_ARTICU COLLATE Latin1_General_BIN
                        WHERE A.NRO_REMITO = '$numRem' 
                        AND A.COD_ARTICU LIKE '[XO]%'
                    ) A
                    GROUP BY COD_CLIENT, SUC_DESTIN, FECHA_CONTROL, FECHA_REM, NOMBRE_VEN, NRO_REMITO, COD_ARTICU
                ) A
                FULL OUTER JOIN 
                (
                    SELECT DISTINCT B.FECHA_MOV, A.N_COMP, A.COD_PRO_CL, A.SUC_DESTIN, B.COD_ARTICU COD_ARTICULO, B.CANTIDAD from STA14 A
                    INNER JOIN STA20 B ON A.TCOMP_IN_S = B.TCOMP_IN_S AND A.NCOMP_IN_S = B.NCOMP_IN_S
                    INNER JOIN (SELECT COD_ARTICU, DESCRIPCIO FROM STA11 WHERE PROMO_MENU != 'P') C ON B.COD_ARTICU = C.COD_ARTICU
                    WHERE A.N_COMP = '$numRem'
                    AND B.COD_ARTICU LIKE '[XO]%'
                ) B
                ON A.COD_ARTICU COLLATE Latin1_General_BIN = B.COD_ARTICULO COLLATE Latin1_General_BIN 
            ) A
                LEFT JOIN STA11 B
            ON A.COD_ARTICU COLLATE Latin1_General_BIN = B.COD_ARTICU COLLATE Latin1_General_BIN
            ";

        $array = $this->getArray($sql);    

        return $array;
    }    

    public function verificacion($user, $db = 'central'){

        $cid = $this->conn->conectar('central');

        
        $sql=
        "
        SELECT ISNULL(SUM(CANT_CONTROL), 0)VERIFICACION FROM SJ_CONTROL_LOCAL 
        WHERE COD_CLIENT = '$user'
        ";

        try {
            $stmt = sqlsrv_query($cid, $sql);

            $v = [];

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;
                
            }

            return $v;

        }
        catch (\Throwable $th) {
            return ("Error en sqlsrv_exec");
        };

    } 
    
    public function buscarSinonimo($codigo){

        $sql=
        "
        SET DATEFORMAT YMD
	    SELECT COD_ARTICU FROM STA11 WHERE COD_ARTICU = '$codigo' OR SINONIMO = '$codigo'
        ";

        $array = $this->getArray($sql);    

        return $array;
    } 

    public function insertarControlLocal($user, $rem, $codigo, $user_local){

        $sql=
        "
        SET DATEFORMAT YMD
        INSERT INTO SJ_CONTROL_LOCAL
        (FECHA_CONTROL, COD_CLIENT, NRO_REMITO, COD_ARTICU, CANT_CONTROL, USUARIO_LOCAL)
        VALUES (GETDATE(), '$user', '$rem', '$codigo', 1, '$user_local')
        ";
        $this->insertDatos($sql);
        
    } 

    public function wrongCode($codigo){
        
        $cid = $this->conn->conectar('central');

        $sqlValida =
            "
            SET DATEFORMAT YMD
            SELECT COD_ARTICU FROM STA11 WHERE COD_ARTICU = '$codigo'
            ";

       
        $resultValida = sqlsrv_exec($cid, $sqlValida);

        if (sqlsrv_num_rows($resultValida) == 0) {
            return '
            <audio src="Wrong.ogg" autoplay></audio>
            </br></br>
            <div class="alert alert-danger" role="alert" style="margin-left:15%; margin-right:15%">
            ATENCION!! El codigo <strong>' . strtoupper($codigo) . '</strong> no existe
            </div>';
        }else{
            return '';
        }
    } 

    public function traerControladoTemporal($user){

        $sql=
            "
            SET DATEFORMAT YMD

				SELECT A.COD_ARTICU, A.CANT_CONTROL, B.DESCRIPCIO 
				FROM
				(
					SELECT COD_ARTICU, SUM(CANT_CONTROL)CANT_CONTROL FROM SJ_CONTROL_LOCAL 
					WHERE COD_CLIENT = '$user'
					GROUP BY COD_ARTICU
				)A
				INNER JOIN STA11 B
				ON A.COD_ARTICU COLLATE Latin1_General_BIN= B.COD_ARTICU COLLATE Latin1_General_BIN
            ";
        $array = $this->getArray($sql);    

        return $array;
    }  

    public function traerHistorial($user){

        $sql=
            "
            SET DATEFORMAT YMD
            SELECT COD_ARTICU FROM SJ_CONTROL_LOCAL 
            WHERE COD_CLIENT = '$user'
            AND COD_ARTICU IN (SELECT COD_ARTICU COLLATE Latin1_General_BIN FROM STA11)
            ORDER BY ID DESC
            ";

        $array = $this->getArray($sql);    

        return $array;
    } 

    // NEWS

    public function buscarRemitoPorLocal($rem){
        $cid = $this->conn->conectar('local');

        if(!$cid) return false;

        $sql = "SELECT * FROM CTA115 WHERE N_COMP = '$rem'";

        try {
            $stmt = sqlsrv_query($cid, $sql);

            try {
    
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
                    $v[] = $row;
    
                }
    
                sqlsrv_close($cid);
        
                return $v;
    
            } catch (\Throwable $th) {
    
                print_r($th);
    
            }
    
        }
        catch (\Throwable $th) {
            return ("Error en sqlsrv_exec");
        };
    }

    /**
     * Verifica si un remito ya fue controlado consultando la tabla de auditoría
     * @param string $rem Número de remito a verificar
     * @param string $db Base de datos ('central' o 'uy')
     * @return bool True si el remito ya fue controlado, False si no
     */
    public function verificarRemitoControlado($rem, $db = 'central'){
        try {
            $cid = $this->conn->conectar($db);
            
            if(!$cid) {
                throw new Exception("Error de conexión con la base de datos $db");
            }

            $sql = "SELECT NRO_REMITO FROM SJ_CONTROL_AUDITORIA WHERE NRO_REMITO = '$rem'";
            
            $result = sqlsrv_query($cid, $sql);
            
            if ($result === false) {
                $errors = sqlsrv_errors();
                $errorMessage = isset($errors[0]['message']) ? $errors[0]['message'] : 'Error desconocido en la consulta';
                throw new Exception("Error en la consulta SQL: " . $errorMessage);
            }

            // Si encuentra al menos un registro, el remito ya fue controlado
            $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
            
            if ($row && isset($row['NRO_REMITO'])) {
                return true; // Remito ya controlado
            }
            
            return false; // Remito no controlado
            
        } catch (Exception $e) {
            error_log("Error en verificarRemitoControlado para remito $rem en BD $db: " . $e->getMessage());
            return false;
        }
    }

    public function deleteControlRemitoTables($user, $db = 'central'){

        $cid = $this->conn->conectar($db);
        

        $sql = "EXEC SJ_DELETE_CONTROL_LOCAL_TABLES '$user'";

        try {

            $stmt = sqlsrv_prepare($cid, $sql);
            $stmt = sqlsrv_execute($stmt);

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }
    
            
    }

    public function traerMaestroDeArticulos($db = 'central'){
        $cid = $this->conn->conectar($db);

        $sql = "SELECT COD_ARTICU, SINONIMO, DESCRIPCIO 
                FROM STA11 
                WHERE COD_ARTICU LIKE '[XO]%'
                AND USA_ESC != 'B'
                AND PERFIL != 'N'
                ";

        try {
            $stmt = sqlsrv_query($cid, $sql);

            try {
    
                $rows = array();

                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
                    $rows[] = array(
                        'COD_ARTICU' => $row['COD_ARTICU'],
                        'SINONIMO' => $row['SINONIMO'],
                        'DESCRIPCIO' => str_replace('"','',$row['DESCRIPCIO']),
                    );
    
                }
    
                sqlsrv_close($cid);
        
                return json_encode($rows);
    
            } catch (\Throwable $th) {
    
                print_r($th);
    
            }
    
        }
        catch (\Throwable $th) {
            return ("Error en sqlsrv_exec");
        };
    }

    // CONTROL REMITOS

    public function marcarRemitoRegistrado($rem){

        $cid = $this->conn->conectar('local');
        

        $sql = "UPDATE CTA115 SET TALONARIO = 1 WHERE N_COMP = '$rem'";

        try {

            $stmt = sqlsrv_prepare($cid, $sql);
            $stmt = sqlsrv_execute($stmt);

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }
    
            
    }

    public function traerTodosLosArticulosRemito($rem){

        $cid = $this->conn->conectar('local');
        

        $sql = "SELECT B.COD_ARTICU, CAST(SUM(B.CANTIDAD) AS FLOAT) CANTIDAD FROM STA14 A 
                INNER JOIN STA20 B ON A.ID_STA14 = B.ID_STA14
                INNER JOIN STA11 C ON B.COD_ARTICU = C.COD_ARTICU
                WHERE A.NCOMP_ORIG = '$rem' AND C.PROMO_MENU != 'P'
                GROUP BY B.COD_ARTICU
        ";

        try {

            $stmt = sqlsrv_query($cid, $sql);

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
                $v[] = $row;

            }

            sqlsrv_close($cid);
    
            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }
    
            
    }

    public function insertarAuditoria($fechaRem, $codClient, $rem, $sucOrig, $sucDestin, $codArticu, $cantRem, $cantControl, $vendedor, $status, $db = 'central'){
        $cid = $this->conn->conectar('central');

        $sql = " SET DATEFORMAT YMD
         INSERT INTO SJ_CONTROL_AUDITORIA
        (
            FECHA_CONTROL, COD_CLIENT, FECHA_REM, NRO_REMITO, SUC_ORIG, 
            SUC_DESTIN, COD_ARTICU, CANT_REM, CANT_CONTROL, USUARIO_LOCAL, 
            OBSERVAC_LOGISTICA
        )
        VALUES
        (
            getdate(), '$codClient', '$fechaRem', '$rem', $sucOrig,
            $sucDestin, '$codArticu', $cantRem, $cantControl, '$vendedor', 
            '$status'
        )";

        try {
    
    
            
            $stmt = sqlsrv_prepare($cid, $sql);
            $stmt = sqlsrv_execute($stmt);

            return true;

        } catch (\Throwable $th) {
            print_r($th);

        }

        
    } 

    public function traerHistoricosAuditoria($desde, $hasta, $estado){

        $cid = $this->conn->conectar('central');
        

        $sql = 
        "
        SELECT FECHA_CONTROL, MAX(HORA) HORA, COD_CLIENT, SUC_ORIG, SUC_DESTIN, FECHA_REM, NOMBRE_VEN, NRO_REMITO, SUM(CANT_CONTROL) CANT_CONTROL, SUM(CANT_REM) CANT_REM, SUM(DIFERENCIA) DIFERENCIA, OBSERVAC_LOGISTICA,
		NRO_AJUSTE, ULTIMO_CHAT,AJUSTAR FROM 
		(
			SELECT CAST(STUFF(STUFF(CONVERT(VARCHAR(10), FECHA_CONTROL, 112),5,0,'-'),8,0,'-') AS DATE) FECHA_CONTROL, CONVERT(CHAR(5), FECHA_CONTROL, 108) HORA,
			A.COD_CLIENT, A.SUC_ORIG, A.SUC_DESTIN,  CAST(A.FECHA_REM AS DATE) FECHA_REM, NOMBRE_VEN, A.NRO_REMITO,
			A.CANT_CONTROL, A.CANT_REM, A.CANT_CONTROL-A.CANT_REM DIFERENCIA, A.OBSERVAC_LOGISTICA, NRO_AJUSTE,
			ISNULL((CASE WHEN C.USER_CHAT IN ('ramiro','eduardo','Agustinal') THEN 0 WHEN C.USER_CHAT NOT IN ('ramiro','eduardo','Agustinal') THEN 1 END), 2) ULTIMO_CHAT, AJUSTAR
			FROM SJ_CONTROL_AUDITORIA A
			INNER JOIN RO_V_GVA23 B ON A.USUARIO_LOCAL COLLATE Latin1_General_BIN = B.COD_VENDED
			LEFT JOIN SJ_CONTROL_AUDIRTORIA_CHAT_ULTIMO_MSG C ON A.NRO_REMITO = C.NRO_REMITO COLLATE Latin1_General_BIN
			--WHERE A.NRO_REMITO = 'R0014500024543'
		) A
		WHERE OBSERVAC_LOGISTICA LIKE '$estado' AND FECHA_REM >= GETDATE()-365 AND (CAST( A.FECHA_CONTROL AS DATE) BETWEEN '$desde' AND '$hasta')
		GROUP BY FECHA_CONTROL, COD_CLIENT, SUC_ORIG, SUC_DESTIN, FECHA_REM, NOMBRE_VEN, NRO_REMITO, OBSERVAC_LOGISTICA, NRO_AJUSTE, ULTIMO_CHAT, AJUSTAR
		ORDER BY FECHA_CONTROL, HORA  
        ";
   
        try {

            $v = array();

            $stmt = sqlsrv_query($cid, $sql);

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
                $v[] = $row;

            }

            sqlsrv_close($cid);
    
            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }
    }   

    // HISTORIAL REMITOS

    public function ajusteRemitoNumero($ajuste, $ncomp){

        $cid = $this->conn->conectar('central');
       

        $sql = "	
        SET DATEFORMAT YMD
        UPDATE SJ_CONTROL_AUDITORIA SET NRO_AJUSTE = '$ajuste' WHERE NRO_REMITO = '$ncomp'
        ";

        try {

            $stmt = sqlsrv_prepare($cid, $sql);
            $stmt = sqlsrv_execute($stmt);

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }

        
    } 

    public function ajusteRemitoStatus($ncomp, $db = 'central'){

        $cid = $this->conn->conectar('central');


        $sql = 
        "
        SELECT distinct OBSERVAC_LOGISTICA FROM SJ_CONTROL_AUDITORIA where NRO_REMITO = '$ncomp'
        ";

        try {

            $data = array();

            $stmt = sqlsrv_query($cid, $sql);

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
                $data[] = $row;

            }


        } catch (\Throwable $th) {

            print_r($th);

        }

        $estados = array();

        foreach($data as $value){
            $estados[] = $value['OBSERVAC_LOGISTICA'];
        } 
       
        if(in_array("PENDIENTE", $estados)){

            $sql = "	
            SET DATEFORMAT YMD
    
            UPDATE SJ_CONTROL_AUDITORIA SET OBSERVAC_LOGISTICA = 'PENDIENTE' WHERE NRO_REMITO = '$ncomp'
            ";
    
            try {
    
                $stmt = sqlsrv_prepare($cid, $sql);
                $stmt = sqlsrv_execute($stmt);
    
            } catch (\Throwable $th) {
    
                print_r($th);
    
            }

        }

        $sql2 = "	
        DELETE FROM SJ_CONTROL_AUDITORIA
        WHERE ID IN
        (
            SELECT MAX(ID) ID
            FROM SJ_CONTROL_AUDITORIA
            WHERE NRO_REMITO = '$ncomp'
            GROUP BY NRO_REMITO, SUC_ORIG, SUC_DESTIN, COD_ARTICU 
            HAVING COUNT(COD_ARTICU) > 1
        )
        ";

        try {

            $stmt = sqlsrv_prepare($cid, $sql2);
            $stmt = sqlsrv_execute($stmt);

        } catch (\Throwable $th) {

            print_r($th);

        }

      

        
    } 

    public function ajusteRemitoStatusDirecto($status, $ncomp){

        $cid = $this->conn->conectar('central');

        $sql = "	
        UPDATE SJ_CONTROL_AUDITORIA SET OBSERVAC_LOGISTICA = '$status' WHERE NRO_REMITO = '$ncomp'
        ";


        try {

            $stmt = sqlsrv_prepare($cid, $sql);
            $stmt = sqlsrv_execute($stmt);

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }
        
    } 

    public function borrarRemitoControlado($numRemito){

        $cid = $this->conn->conectar('central');

        $sql = "
        DELETE FROM SJ_CONTROL_AUDITORIA WHERE NRO_REMITO = $numRemito
        ";

        try {

            $stmt = sqlsrv_query($cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }

    }

    public function actualizarAjustar ($value, $remito) {

        $cid = $this->conn->conectar('central');
        $remito = trim($remito);
        $sql = "UPDATE SJ_CONTROL_AUDITORIA SET AJUSTAR = '$value' WHERE NRO_REMITO like '%$remito%'";
      
        try {

            $stmt = sqlsrv_query($cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }

    }

    public function rechazarRemito ($remito) {

        $cid = $this->conn->conectar('central');
        $remito = trim($remito);
        $sql = "UPDATE SJ_CONTROL_AUDITORIA SET OBSERVAC_LOGISTICA = 'RECHAZADO' WHERE NRO_REMITO like '%$remito%'";
      
        try {

            $stmt = sqlsrv_query($cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }

    }

    public function listarUsuariosTodos(){

        $sql = "SELECT CASE 
                    WHEN CHARINDEX(' -', NOMBRE_VEN) > 0 THEN LEFT(NOMBRE_VEN, CHARINDEX(' -', NOMBRE_VEN) - 1)
                    WHEN CHARINDEX('-', NOMBRE_VEN) > 0 THEN LEFT(NOMBRE_VEN, CHARINDEX('-', NOMBRE_VEN) - 1)
                    ELSE NOMBRE_VEN
                END AS NOMBRE_VEN, A.BLOQUE, B.DESC_SUCURSAL, A.XML_CA_1118_NUM_SUCURSAL NRO_SUCURSAL FROM 
                (
                SELECT COD_VENDED BLOQUE, NOMBRE_VEN,
                GVA23_CAMPOS_ADICIONALES.XML_CA.value('CA_1118_NUM_SUCURSAL', 'VARCHAR(6)') XML_CA_1118_NUM_SUCURSAL FROM GVA23
                OUTER APPLY GVA23.CAMPOS_ADICIONALES.nodes('CAMPOS_ADICIONALES') as GVA23_CAMPOS_ADICIONALES(XML_CA)
                WHERE INHABILITA = 0
                ) A
                INNER JOIN (SELECT * FROM [LAKERBIS].LOCALES_LAKERS.DBO.SUCURSALES_LAKERS WHERE CANAL = 'PROPIOS') B ON A.XML_CA_1118_NUM_SUCURSAL = B.NRO_SUCURSAL
                WHERE XML_CA_1118_NUM_SUCURSAL IS NOT NULL 
                
                    UNION ALL
                
                SELECT CASE 
                    WHEN CHARINDEX(' -', NOMBRE_VEN) > 0 THEN LEFT(NOMBRE_VEN, CHARINDEX(' -', NOMBRE_VEN) - 1)
                    WHEN CHARINDEX('-', NOMBRE_VEN) > 0 THEN LEFT(NOMBRE_VEN, CHARINDEX('-', NOMBRE_VEN) - 1)
                    ELSE NOMBRE_VEN
                END AS NOMBRE_VEN, A.BLOQUE, B.DESC_SUCURSAL, A.XML_CA_1118_NUM_SUCURSAL NRO_SUCURSAL FROM 
                (
                SELECT COD_VENDED BLOQUE, NOMBRE_VEN,
                GVA23_CAMPOS_ADICIONALES.XML_CA.value('CA_1118_NUM_SUCURSAL', 'VARCHAR(6)') XML_CA_1118_NUM_SUCURSAL FROM TASKY_SA.DBO.GVA23
                OUTER APPLY GVA23.CAMPOS_ADICIONALES.nodes('CAMPOS_ADICIONALES') as GVA23_CAMPOS_ADICIONALES(XML_CA)
                WHERE INHABILITA = 0 AND COD_VENDED LIKE '6%' 
                ) A
                INNER JOIN (SELECT * FROM [LAKERBIS].LOCALES_LAKERS.DBO.SUCURSALES_LAKERS WHERE CANAL = 'EXTERIOR') B ON A.XML_CA_1118_NUM_SUCURSAL = B.NRO_SUCURSAL
                WHERE XML_CA_1118_NUM_SUCURSAL IS NOT NULL
                ORDER BY 1
        ";

        $result = $this->getArray($sql);
        return $result;

    }

    /**
     * Inserta un registro en SJ_CONTROL_AUDITORIA usando una conexión transaccional existente
     * @param resource $cid Conexión de base de datos activa con transacción
     * @param string $fechaRem Fecha del remito
     * @param string $codClient Código del cliente
     * @param string $rem Número de remito
     * @param string $sucOrig Sucursal origen
     * @param string $sucDestin Sucursal destino
     * @param string $codArticu Código del artículo
     * @param int $cantRem Cantidad en remito
     * @param int $cantControl Cantidad controlada
     * @param string $codVen Código del vendedor
     * @param string $status Estado (PENDIENTE/ACEPTADO)
     * @return bool True si se insertó correctamente, False si hubo error
     */
    public function insertarAuditoriaTransaccion($cid, $fechaRem, $codClient, $rem, $sucOrig, $sucDestin, $codArticu, $cantRem, $cantControl, $codVen, $status){
        try {
            if (!$cid) {
                throw new Exception("Conexión de base de datos no válida");
            }

            $sql = "INSERT INTO SJ_CONTROL_AUDITORIA 
                    (FECHA_CONTROL, COD_CLIENT, NRO_REMITO, SUC_ORIG, SUC_DESTIN, COD_ARTICU, CANT_REM, CANT_CONTROL, USUARIO_LOCAL, OBSERVAC_LOGISTICA, FECHA_REM) 
                    VALUES (GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = array(
                $codClient,
                $rem,
                $sucOrig,
                $sucDestin,
                $codArticu,
                $cantRem,
                $cantControl,
                $codVen,
                $status,
                $fechaRem
            );
            
            $stmt = sqlsrv_prepare($cid, $sql, $params);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = isset($errors[0]['message']) ? $errors[0]['message'] : 'Error en preparación de consulta';
                throw new Exception("Error preparando inserción en auditoría: " . $errorMessage);
            }

            $result = sqlsrv_execute($stmt);
            
            if ($result === false) {
                $errors = sqlsrv_errors();
                $errorMessage = isset($errors[0]['message']) ? $errors[0]['message'] : 'Error en ejecución de consulta';
                throw new Exception("Error ejecutando inserción en auditoría: " . $errorMessage);
            }

            // Verificar que se insertó al menos una fila
            $rowsAffected = sqlsrv_rows_affected($stmt);
            if ($rowsAffected === false || $rowsAffected === 0) {
                throw new Exception("No se insertó ninguna fila en auditoría para artículo: $codArticu");
            }

            return true;
            
        } catch (Exception $e) {
            error_log("Error en insertarAuditoriaTransaccion para artículo $codArticu: " . $e->getMessage());
            throw $e; // Re-lanzar para que la transacción haga rollback
        }
    }

    /**
     * Actualiza el status del remito usando una conexión transaccional existente
     * @param resource $cid Conexión de base de datos activa con transacción
     * @param string $rem Número de remito
     * @return bool True si se actualizó correctamente, False si hubo error
     */
    public function ajusteRemitoStatusTransaccion($cid, $rem){
        try {
            if (!$cid) {
                throw new Exception("Conexión de base de datos no válida");
            }

            // Query para actualizar el status del remito
            // Ajustar según la estructura real de tu base de datos
            $sql = "UPDATE SJ_REMITOS_CONTROL SET STATUS = 'PROCESADO', FECHA_PROCESADO = GETDATE() WHERE NRO_REMITO = ?";
            
            $stmt = sqlsrv_prepare($cid, $sql, array($rem));
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = isset($errors[0]['message']) ? $errors[0]['message'] : 'Error en preparación de consulta';
                error_log("Error preparando actualización de status: " . $errorMessage);
                return false; // No crítico, no lanzar excepción
            }

            $result = sqlsrv_execute($stmt);
            
            if ($result === false) {
                $errors = sqlsrv_errors();
                $errorMessage = isset($errors[0]['message']) ? $errors[0]['message'] : 'Error en ejecución de consulta';
                error_log("Error ejecutando actualización de status para remito $rem: " . $errorMessage);
                return false; // No crítico, no lanzar excepción
            }

            return true;
            
        } catch (Exception $e) {
            error_log("Excepción en ajusteRemitoStatusTransaccion para remito $rem: " . $e->getMessage());
            return false; // No crítico, no lanzar excepción
        }
    }

    /**
     * Ejecuta un control completo de remito de forma atómica (todo o nada)
     * @param string $rem Número de remito
     * @param array $articulosControlados Array con artículos controlados
     * @param string $db Base de datos ('central' o 'uy')
     * @return array Resultado con success/error y detalles
     */
    public function procesarRemitoAtomico($rem, $articulosControlados, $db = 'central'){
        $cid = null;
        
        try {
            // Conectar a la base de datos
            $cid = $this->conn->conectar($db);
            if (!$cid) {
                throw new Exception("Error de conexión con la base de datos $db");
            }

            // Iniciar transacción
            if (sqlsrv_begin_transaction($cid) === false) {
                throw new Exception("No se pudo iniciar la transacción");
            }

            $articulosProcesados = 0;
            
            // Procesar cada artículo dentro de la transacción
            foreach($articulosControlados as $articulo) {
                // Aquí iría la lógica de procesamiento usando insertarAuditoriaTransaccion
                // ... (código de procesamiento)
                $articulosProcesados++;
            }

            // Confirmar transacción
            if (sqlsrv_commit($cid) === false) {
                throw new Exception("Error al confirmar la transacción");
            }

            return [
                'success' => true,
                'message' => 'Remito procesado correctamente',
                'articulos_procesados' => $articulosProcesados
            ];

        } catch (Exception $e) {
            // Rollback en caso de error
            if ($cid && sqlsrv_rollback($cid) === false) {
                error_log("Error adicional: No se pudo hacer rollback - " . print_r(sqlsrv_errors(), true));
            }

            return [
                'success' => false,
                'message' => 'Error al procesar remito: ' . $e->getMessage(),
                'error_code' => 'TRANSACTION_ERROR'
            ];
        }
    }

    /**
     * Obtiene los artículos que fueron controlados para un remito específico desde SJ_CONTROL_AUDITORIA
     * @param string $rem Número de remito
     * @param string $db Base de datos ('central' o 'uy')
     * @return array Array con los artículos controlados
     */
    public function traerArticulosControlados($rem, $db = 'central'){
        try {
            $cid = $this->conn->conectar($db);
            
            if(!$cid) {
                throw new Exception("Error de conexión con la base de datos $db");
            }

            $sql = "SELECT COD_ARTICU, CANT_CONTROL, CANT_REM, OBSERVAC_LOGISTICA 
                    FROM SJ_CONTROL_AUDITORIA 
                    WHERE NRO_REMITO = ?
                    ORDER BY COD_ARTICU";
            
            $stmt = sqlsrv_prepare($cid, $sql, array($rem));
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $errorMessage = isset($errors[0]['message']) ? $errors[0]['message'] : 'Error en preparación de consulta';
                throw new Exception("Error preparando consulta traerArticulosControlados: " . $errorMessage);
            }

            $result = sqlsrv_execute($stmt);
            
            if ($result === false) {
                $errors = sqlsrv_errors();
                $errorMessage = isset($errors[0]['message']) ? $errors[0]['message'] : 'Error en ejecución de consulta';
                throw new Exception("Error ejecutando consulta traerArticulosControlados: " . $errorMessage);
            }

            $articulos = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $articulos[] = array(
                    'COD_ARTICU' => $row['COD_ARTICU'],
                    'CANT_CONTROL' => (int)$row['CANT_CONTROL'],
                    'CANT_REM' => (int)$row['CANT_REM'],
                    'STATUS' => $row['OBSERVAC_LOGISTICA']
                );
            }

            error_log("Artículos controlados obtenidos para remito $rem: " . count($articulos) . " artículos");
            return $articulos;
            
        } catch (Exception $e) {
            error_log("Error en traerArticulosControlados para remito $rem en BD $db: " . $e->getMessage());
            return []; // Devolver array vacío en caso de error
        }
    }

    /**
     * Obtiene el detalle completo de un control (remito vs controlado) para mostrar diferencias
     * @param string $rem Número de remito
     * @param string $db Base de datos ('central' o 'uy')
     * @return array Array con el detalle completo del control
     */
    public function traerDetalleControl($rem, $db = 'central'){
        try {
            $cid = $this->conn->conectar($db);
            
            if(!$cid) {
                throw new Exception("Error de conexión con la base de datos $db");
            }

            // Query que combina datos del remito original con los controlados
            $sql = "SELECT 
                        A.COD_ARTICU,
                        B.DESCRIPCIO,
                        A.CANT_REM,
                        A.CANT_CONTROL,
                        ABS(A.CANT_REM - A.CANT_CONTROL) AS DIFERENCIA,
                        A.OBSERVAC_LOGISTICA AS STATUS,
                        A.FECHA_CONTROL
                    FROM SJ_CONTROL_AUDITORIA A
                    LEFT JOIN STA11 B ON A.COD_ARTICU = B.COD_ARTICU COLLATE Latin1_General_BIN
                    WHERE A.NRO_REMITO = ?
                    ORDER BY A.COD_ARTICU";
            
            $stmt = sqlsrv_prepare($cid, $sql, array($rem));
            
            if ($stmt === false) {
                throw new Exception("Error preparando consulta detalle control");
            }

            $result = sqlsrv_execute($stmt);
            
            if ($result === false) {
                throw new Exception("Error ejecutando consulta detalle control");
            }

            $detalle = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $detalle[] = array(
                    'COD_ARTICU' => $row['COD_ARTICU'],
                    'DESCRIPCIO' => $row['DESCRIPCIO'] ? $row['DESCRIPCIO'] : 'DESCRIPCIÓN NO ENCONTRADA',
                    'CANT_REM' => (int)$row['CANT_REM'],
                    'CANT_CONTROL' => (int)$row['CANT_CONTROL'],
                    'DIFERENCIA' => (int)$row['DIFERENCIA'],
                    'STATUS' => $row['STATUS'],
                    'FECHA_CONTROL' => $row['FECHA_CONTROL']
                );
            }

            return $detalle;
            
        } catch (Exception $e) {
            error_log("Error en traerDetalleControl para remito $rem: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica si un remito tiene datos de control disponibles
     * @param string $rem Número de remito
     * @param string $db Base de datos ('central' o 'uy')
     * @return bool True si tiene datos, False si no
     */
    public function tieneControlCompleto($rem, $db = 'central'){
        try {
            $cid = $this->conn->conectar($db);
            
            if(!$cid) {
                return false;
            }

            $sql = "SELECT COUNT(*) as total FROM SJ_CONTROL_AUDITORIA WHERE NRO_REMITO = ?";
            $stmt = sqlsrv_prepare($cid, $sql, array($rem));
            
            if ($stmt === false) {
                return false;
            }

            $result = sqlsrv_execute($stmt);
            
            if ($result === false) {
                return false;
            }

            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            return isset($row['total']) && $row['total'] > 0;
            
        } catch (Exception $e) {
            error_log("Error en tieneControlCompleto: " . $e->getMessage());
            return false;
        }
    }


}
