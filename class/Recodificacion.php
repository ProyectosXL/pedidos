<?php


class Recodificacion
{

    private $consulta;
    
    function __construct() 
    {
        
        require_once $_SERVER['DOCUMENT_ROOT'] .'/sistemas/class/conexion.php';
        
        $conn = new Conexion();
        $this->cid = $conn->conectar('central');

        $this->cidUy = $conn->conectar('uy');
        
        $this->cidLocal = $conn->conectar('local');
        

    }
    
    public function traerNumSolicitud() 
    {   
        $sql = "SELECT MAX(ID) AS ultimo_id FROM sj_reco_locales_enc";
        try {

            $result = sqlsrv_query($this->cid, $sql); 
            
            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }
        

    }

    public function insertarEncabezado($numSolicitud, $nroSucursal, $fecha, $usuario, $estado ,$borrador = false) 
    {   

        if($borrador != "false"){

            $sql = "UPDATE sj_reco_locales_enc SET ESTADO = '$estado', UPDATED_AT = GETDATE() WHERE ID = '$numSolicitud' AND ESTADO = '4';";

            try {

                $result = sqlsrv_query($this->cid, $sql); 

                return $numSolicitud;

            } catch (\Throwable $th) {

                print_r($th);

            }   

        }else{

            $sql = "EXEC FU_INSERT_ENC_FALLAS '$nroSucursal', '$fecha', '$usuario', '$estado', '$numSolicitud' ";
            
   
            try {

                $result = sqlsrv_query($this->cid, $sql); 
                $next_result = sqlsrv_next_result($result);
                $v = [];
                
                while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                    $v[] = $row;

                }
                return $v[0]['ID'];


            } catch (\Throwable $th) {

                print_r($th);

            }

        }

    }


    public function insertarDetalle($valores) 
    {   

        
        $valores = str_replace("'", "''", $valores);

        
        $sql = "EXEC FU_INSERT_DET_FALLAS N'$valores'";
    
 
        
        try {

            $result = sqlsrv_query($this->cid, $sql); 

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }
        

    }

    public function borrarDetalle($idEnc) 
    {   

        $sql = "DELETE FROM sj_reco_locales_det WHERE ID_ENC = $idEnc";

        try {

            $result = sqlsrv_query($this->cid, $sql); 

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }
        

    }

    public function buscarBorradorEnc($numSolicitud,$estado) 
    {   
        $sql = "SELECT * FROM sj_reco_locales_enc where ID = $numSolicitud AND ESTADO = '$estado'";

        try {

            $result = sqlsrv_query($this->cid, $sql); 

            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th); 

        }
    }

    public function buscarBorradorDet($numSolicitud) 
    {   
        $sql = "SELECT * FROM sj_reco_locales_det where ID_ENC = $numSolicitud";
        try {

            $result = sqlsrv_query($this->cid, $sql); 

            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th); 

        }
    }


    public function traerSolicitudes($nroSucursal = null, $desde, $hasta, $estado, $sup = null, $destino = null) 
    {   
        $sql = "
        SET DATEFORMAT YMD
        SELECT 
        enc.ID ,
        enc.FECHA,
        enc.USUARIO_EMISOR,
        enc.ESTADO,
        enc.NUM_SUC,
        enc.UPDATED_AT,
        SUM(det.cantidad) AS cantidad_total_articulos,
        det.N_COMP
        FROM sj_reco_locales_enc AS enc
        JOIN sj_reco_locales_det AS det ON enc.id = det.ID_ENC
        WHERE enc.FECHA BETWEEN '$desde' AND '$hasta'";
  
            
        $sql .= "AND enc.ESTADO LIKE '%$estado%'";

        if($destino != null){
            $sql .= "AND det.DESTINO = '$destino'";
        }

        if($nroSucursal != null){
            $sql .= "AND enc.NUM_SUC = '$nroSucursal'";
        }

        if($sup == 1){
            $sql .= "AND enc.ESTADO != '4'";
        }

        $sql .= "
        GROUP BY enc.ID, enc.FECHA, enc.USUARIO_EMISOR, enc.ESTADO, enc.NUM_SUC, enc.UPDATED_AT, det.N_COMP
        ORDER BY enc.ID DESC";
  

        try {

            $result = sqlsrv_query($this->cid, $sql); 
            
            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }
        

    }


    public function comprobarIngresada ($nroRemito, $esSupervisor = null) {

        $sql ="SELECT CASE WHEN EXISTS (
            SELECT 1
            FROM sta14 where NCOMP_ORIG = '$nroRemito'
        ) THEN 'true' ELSE 'false' END AS remitoExiste;";

        if($esSupervisor != null){

            $sql = "
            set dateformat ymd
            SELECT CASE WHEN EXISTS (
                select * from 
                (
                    select a.fecha, a.estado, a.num_suc, b.*
                    from sj_reco_locales_enc a
                    inner join sj_reco_locales_det b on a.ID = b.ID_ENC 
                ) a
                left join 
                (
                    select cod_pro_cl, estado_mov, fecha_mov, fecha_impo, hora_impo, n_comp, ncomp_orig, nro_sucurs suc_origen, suc_Destin, observacio from [LAKERBIS].[locales_lakers].dbo.cta09 
                    where fecha_mov >= cast(getdate()-100 as date)
                    and suc_destin != 0
                ) b
                on a.n_comp collate Latin1_General_BIN = b.ncomp_orig
                WHERE b.NCOMP_ORIG = '$nroRemito'
            ) THEN 'true' ELSE 'false' END AS remitoExiste";

        }
        
        $cid = $esSupervisor != null ?  $this->cid  : $this->cidLocal;

        $stmt = sqlsrv_query($cid, $sql);

        if ($stmt === false) {
            die("Error en la consulta: " . sqlsrv_errors());
        }

        $row = sqlsrv_fetch_array($stmt);

        if( $row == null || $row['remitoExiste'] == 'false'){


            return false ;
            
        }else{
            
            return true ;
        }


    }

    
    public function traerLocales($outlet = true) 
    {   

        $sql = "

        SELECT NRO_SUCURSAL, DESC_SUCURSAL, COD_CLIENT, OUTLET FROM LAKERBIS.LOCALES_LAKERS.DBO.SUCURSALES_LAKERS WHERE CANAL in ('PROPIOS','EXTERIOR')  AND HABILITADO = 1 ";

     
        if($outlet == true){

            $sql .= "AND OUTLET = 1 ";
        }

        $sql  .=" UNION ALL
        SELECT NRO_SUCURSAL, DESC_SUCURSAL, COD_CLIENT, OUTLET FROM LAKERBIS.LOCALES_LAKERS.DBO.SUCURSALES_LAKERS WHERE NRO_SUCURSAL = '16' OR COD_CLIENT = 'GTCENT'
        ORDER BY DESC_SUCURSAL
        ";
       
        try {

            $result = sqlsrv_query($this->cid, $sql); 
            
            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }
        

    }

    public function traerEncabezado ($numSolicitud) {

        $sql = "SELECT * FROM sj_reco_locales_enc where ID = $numSolicitud";

        try {

            $result = sqlsrv_query($this->cid, $sql); 

            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th); 

        }
        
    }

    public function traerDetalle ($numSolicitud, $numSucursal = null) {
            
        $sql = "SELECT a.*, b.DESCRIPCIO as DESCSTA11 FROM sj_reco_locales_det as a
        left join sta11 as b on a.NUEVO_CODIGO = b.COD_ARTICU collate Latin1_General_BIN
        where a.ID_ENC = $numSolicitud";

        if($numSucursal != null){

            $sql .= " AND DESTINO = '$numSucursal'";

        }


        try {

            $result = sqlsrv_query($this->cid, $sql); 

            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th); 

        }
                
    }

    public function traerCodigoRecodificacion($valor, $codArticulo, $numSucursal) 
    {   
        $sql = "EXEC RO_SP_RECODIFICAR_OUTLET '$codArticulo', $valor";
 
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            if((int)$numSucursal >= 201){

                $result = sqlsrv_query($this->cidUy, $sql);

            }else{
           
                $result = sqlsrv_query($this->cid, $sql); 
                
            }
            
            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }
        

    }

    public function traerRemitos($nroSucursal, $destino) 
    {   

        $conn = new Conexion();
        $cid = $conn->conectar('local');


        $sql = "SET DATEFORMAT YMD 
		SELECT FECHA_MOV, NRO_SUCURS, N_COMP, COD_PRO_CL FROM STA14 
        WHERE T_COMP = 'REM' AND COD_PRO_CL LIKE '[GU]%'
        AND FECHA_MOV >= GETDATE()-30 AND COD_PRO_CL LIKE '%$destino%'";
 
        try {

            $result = sqlsrv_query($cid, $sql); 
            
            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }
        

    }

    public function autorizar ($numSolicitud)
    {
        $sql = "UPDATE sj_reco_locales_enc SET ESTADO = '2' WHERE ID = $numSolicitud";

        try {
    
            $result = sqlsrv_query($this->cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th); 

        }
    }

    public function ingresar ($numSolicitud)
    {
        $sql = "UPDATE sj_reco_locales_enc SET ESTADO = '5' WHERE ID = $numSolicitud";

        try {
    
            $result = sqlsrv_query($this->cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th); 

        }
    }


    public function actualizarDetalle ($precio, $nuevoCodigo, $destino, $observaciones, $id)
    {
      
        $sql = "UPDATE sj_reco_locales_det SET PRECIO = '$precio', NUEVO_CODIGO = '$nuevoCodigo', UPDATED_AT = GETDATE(), DESTINO = '$destino', OBSERVACIONES = '$observaciones' WHERE ID = $id";
        
        try {

    
            $result = sqlsrv_query($this->cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th); 

        }
    }

    public function enviar ($numSolicitud)
    {
        $sql = "UPDATE sj_reco_locales_enc SET ESTADO = '3' WHERE ID = $numSolicitud";

        try {
    
            $result = sqlsrv_query($this->cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th); 

        }
    }
    public function finalizar ($numSolicitud)
    {
        $sql = "UPDATE sj_reco_locales_enc SET ESTADO = '7' WHERE ID = $numSolicitud";

        try {
    
            $result = sqlsrv_query($this->cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th); 

        }
    }

    public function cargarRemito ($id, $numRemito)
    {
      
        $sql = "UPDATE sj_reco_locales_det SET N_COMP = '$numRemito' WHERE ID = $id";
        
        try {

    
            $result = sqlsrv_query($this->cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th); 

        }
    }

    
    public function traerUsuariosNotificaSolicitud () 
    {
        $sql = " SELECT DESCRIPCION, MAIL FROM SOF_USUARIOS WHERE TIPO = 'SUPERVISION' AND NOTIFICA = 1";

        try {

            $result = sqlsrv_query($this->cid, $sql); 
            
            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }

    }

    public function traerMailAutorizaSolicitud ($numSucursal) 
    {

        $sql = "select MAIL from RO_MAILS_LOCALES_PROPIOS WHERE NRO_SUCURS = '$numSucursal'";

        try {

            $result = sqlsrv_query($this->cid, $sql); 
            
            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }


    }

    public function darBajaArticuloOriginal ($codArticulo, $cantidad){

        $codDeposito = "(SELECT COD_SUCURS FROM STA22 WHERE COD_SUCURS LIKE '[0-9]%' AND INHABILITA = 0)";

        $sqlBaja = "UPDATE sta19 SET CANT_STOCK = CANT_STOCK - $cantidad WHERE COD_ARTICU = '$codArticulo' AND COD_DEPOSI = $codDeposito";

        sqlsrv_query($this->cidLocal, $sqlBaja);

        return true;
        
    }

    public function darAltaEnDepositoOu($codArticulo, $cantidad){


        $sqlAlta = "IF EXISTS (
            SELECT 1 
            FROM STA19 
            WHERE COD_ARTICU = '$codArticulo' AND COD_DEPOSI = 'OU'
        )
        BEGIN
            UPDATE STA19 
            SET CANT_STOCK = $cantidad
            WHERE COD_ARTICU = '$codArticulo' AND COD_DEPOSI = 'OU';
        END
        ELSE
        BEGIN
            INSERT INTO STA19 (CANT_STOCK, COD_ARTICU, COD_DEPOSI)
            VALUES ($cantidad, '$codArticulo', 'OU');
        END;";
        
    

        $result = sqlsrv_query($this->cidLocal, $sqlAlta);

        return true;

    }

    public function comprobarStock ($articulo) 
    {


        $sql="SELECT CASE 
                    WHEN A.CANT_STOCK >= $articulo[cantidad] THEN 'True'
                    ELSE 'False'
                END AS TieneStock
            FROM STA19 A
            INNER JOIN (
            SELECT COD_SUCURS 
            FROM STA22 
            WHERE COD_SUCURS LIKE '[0-9]%' 
                AND INHABILITA = 0
            ) B ON A.COD_DEPOSI = B.COD_SUCURS
            WHERE A.COD_ARTICU = '$articulo[articulo]' ;";



            $stmt = sqlsrv_query($this->cidLocal, $sql);

            if ($stmt === false) {
                die("Error en la consulta: " . sqlsrv_errors());
            }
        
            $row = sqlsrv_fetch_array($stmt);
    
            if( $row == null || $row['TieneStock'] == 'False'){


                $tieneStock = false ;
                
            }else{
                
                $tieneStock = true ;
            }
        
            return $tieneStock;
    }


    public function validarCodigosOulet ($articulo, $numSucursal)
    {

        $sql="SELECT CASE WHEN EXISTS(
            SELECT TOP 1 COD_ARTICU 
            FROM STA11 
            WHERE COD_ARTICU = '$articulo') 
        THEN 'true' ELSE 'false' END AS ArticuloExiste;";


        if($numSucursal >= 201){

            $stmt = sqlsrv_query($this->cidUy, $sql);

        }else{
            
            $stmt = sqlsrv_query($this->cid, $sql);
        }


        if ($stmt === false) {
            die("Error en la consulta: " . sqlsrv_errors());
        }
    
        $row = sqlsrv_fetch_array($stmt);
 
        if( $row == null || $row['ArticuloExiste'] == 'false'){


            $existeArticulo = false ;
            
        }else{
            
            $existeArticulo = true ;
        }
    
        return $existeArticulo;
    }


    public function comprobarArticuloEnRemito ($nComp, $articulo) 
    {

        $conn = new Conexion();
        $cid = $conn->conectar('local');

        $sql="SELECT CASE WHEN EXISTS (
            SELECT 1 FROM STA14 A 
            INNER JOIN STA20 B 
            ON A.ID_STA14 = B.ID_STA14
            WHERE A.FECHA_MOV >= GETDATE()-30 
            AND T_COMP = 'REM'
            AND A.COD_PRO_CL LIKE '[GU]%'
            AND A.N_COMP ='$nComp'
            AND B.COD_ARTICU ='$articulo'
        ) THEN 'true' ELSE 'false' END AS ArticuloExiste;";
    

        $stmt = sqlsrv_query($cid, $sql);

        if ($stmt === false) {
            die("Error en la consulta: " . sqlsrv_errors());
        }

        $row = sqlsrv_fetch_array($stmt);
   
        if( $row == null || $row['ArticuloExiste'] == 'false'){


            $existeArticulo = false ;
            
        }else{
            
            $existeArticulo = true ;
        }

        return $existeArticulo;

    }

    public function comprobarArticuloRecodifica ($nComp, $articulo, $cantidad) 
    {

        $conn = new Conexion();
        $cid = $conn->conectar('local');

        $sql="SELECT CASE WHEN (
          	 SELECT SUM(B.CANTIDAD) FROM STA14 A 
            INNER JOIN STA20 B ON A.ID_STA14 = B.ID_STA14
            WHERE A.NCOMP_ORIG ='$nComp'
            AND B.COD_ARTICU ='$articulo'
        ) >=  $cantidad THEN 'true' ELSE 'false' END AS ArticuloExiste;";
    
         
        $stmt = sqlsrv_query($cid, $sql);

        if ($stmt === false) {
            die("Error en la consulta: " . sqlsrv_errors());
        }

        $row = sqlsrv_fetch_array($stmt);
   
        if( $row == null || $row['ArticuloExiste'] == 'false'){


            $existeArticulo = false ;
            
        }else{
            
            $existeArticulo = true ;
        }

        return $existeArticulo;

    }

    


    public function traerRecodificacionDeArticulos ($numSolicitud = null,$numSucursal)
    {

        $sql = "SELECT B.ID_ENC,A.NUM_SUC, CAST(A.FECHA AS DATE) FECHA, B.N_COMP, B.COD_ARTICU, B.DESCRIPCION, B.CANTIDAD, B.NUEVO_CODIGO, B.DESTINO FROM sj_reco_locales_enc A
        INNER JOIN sj_reco_locales_det B ON A.ID = B.ID_ENC
        WHERE B.AJUSTADO IS NULL OR AJUSTADO = 0 AND B.NUEVO_CODIGO != ''
        AND B.DESTINO = '$numSucursal'";

        if($numSolicitud != null){

            $sql .= " AND A.ID = $numSolicitud";

        }
    

        try {

            $result = sqlsrv_query($this->cid, $sql); 
            
            $v = [];
            
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }

    }
    
    public function traerRemitosEnElLocal($remitos)
    {
        $conn = new Conexion();
        $cid = $conn->conectar('local');

        $remitosList = implode("', '", $remitos);

        $sql = "SELECT NCOMP_ORIG FROM STA14 WHERE NCOMP_ORIG IN ('$remitosList')";
        $stmt = sqlsrv_query($cid, $sql);
    
        $resultados = [];
    
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

                $resultados[] = $row["NCOMP_ORIG"];

            }
        } else {

            die(print_r(sqlsrv_errors(), true));
        }
    
        return $resultados;
    }
    
    public function ajustarArticulos ($id, $codArticulo) {

        $sql = "UPDATE sj_reco_locales_det SET AJUSTADO = '1' WHERE ID_ENC = '$id' AND COD_ARTICU = '$codArticulo'";

        try {
    
            $result = sqlsrv_query($this->cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th); 

        }
    }

    public function comprobarStockMaestroDeArticulos($codArticulo) {
    
        $sql = "SELECT 1 FROM STA11 WHERE COD_ARTICU = '$codArticulo' ";
    
        $stmt = sqlsrv_query($this->cidLocal, $sql);
    
        if ($stmt === false) {
            die("Error en la consulta: " . sqlsrv_errors());
        }
    
    
        if (sqlsrv_has_rows($stmt)) {
        
            return true;
        } else {

            return false;
        }
        
    }

    public function comprobarStockDepositoLocal ($codArticulo, $cantidad) {

        $codDeposito = '\'OU\'';

        $sql = "SELECT CANT_STOCK FROM STA19 WHERE COD_ARTICU = '$codArticulo' AND COD_DEPOSI = $codDeposito" ;

        $stmt = sqlsrv_query($this->cidLocal, $sql);

        if ($stmt === false) {
            die("Error en la consulta: " . sqlsrv_errors());
        }

        $row = sqlsrv_fetch_array($stmt);

        
        if( $row == null || $row['CANT_STOCK'] < $cantidad){

            return false;
        }else{

            return true;
        }


    }

    public function insertarDetalleEntradaOu($cant, $nuevo, $fecha, $proxInterno) 
    {
        $sqlDetEntrada = "
        INSERT INTO STA20
        (
        CAN_EQUI_V, CANT_DEV, CANT_OC, CANT_PEND, CANT_SCRAP, CANTIDAD, COD_ARTICU, COD_DEPOSI, DEPOSI_DDE, EQUIVALENC, 
        FECHA_MOV, N_RENGL_OC, N_RENGL_S, NCOMP_IN_S, PLISTA_REM, PPP_EX, PPP_LO, PRECIO, PRECIO_REM, TCOMP_IN_S, TIPO_MOV,
        CANT_FACTU, DCTO_FACTU, CANT_DEV_2, CANT_PEND_2, CANTIDAD_2, CANT_FACTU_2, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, 
        PRECIO_REMITO_VENTAS, CANT_OC_2, RENGL_PADR, PROMOCION, PRECIO_ADICIONAL_KIT, TALONARIO_OC
        )
        VALUES
        (
        1, 0, 0, 0, 0, '$cant', '$nuevo', 'OU','', 1, 
        '$fecha', 0, 2, '$proxInterno', 0, 0, 0, 0, 0, 'TI', 'E', 0, 0, 0, 0, 0, 0, 6, 'P', 0, 0, 0, 0, 0, 0
        );";
  
        try {
            
            $resultDetEntrada = sqlsrv_query($this->cidLocal, $sqlDetEntrada);

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }

    }

    public function insertarDetalleSalidaOu($cant, $codigo, $fecha, $proxInterno, $esNuevoAjuste = null) 
    {


        $codDeposito = "(SELECT COD_SUCURS FROM STA22 WHERE COD_SUCURS LIKE '[0-9]%' AND INHABILITA = 0)";

       
        $sqlDetSalida = "
        INSERT INTO STA20
        (
        CAN_EQUI_V, CANT_DEV, CANT_OC, CANT_PEND, CANT_SCRAP, CANTIDAD, COD_ARTICU, COD_DEPOSI, DEPOSI_DDE, EQUIVALENC, 
        FECHA_MOV, N_RENGL_OC, N_RENGL_S, NCOMP_IN_S, PLISTA_REM, PPP_EX, PPP_LO, PRECIO, PRECIO_REM, TCOMP_IN_S, TIPO_MOV,
        CANT_FACTU, DCTO_FACTU, CANT_DEV_2, CANT_PEND_2, CANTIDAD_2, CANT_FACTU_2, ID_MEDIDA_STOCK, UNIDAD_MEDIDA_SELECCIONADA, 
        PRECIO_REMITO_VENTAS, CANT_OC_2, RENGL_PADR, PROMOCION, PRECIO_ADICIONAL_KIT, TALONARIO_OC
        )
        VALUES
        (
        1, 0, 0, 0, 0, '$cant', '$codigo', $codDeposito,'', 1, '$fecha', 0, 1, '$proxInterno', 0, 0, 0, 0, 0, 'TI', 
        'S', 0, 0, 0, 0, 0, 0, 6, 'P', 0, 0, 0, 0, 0, 0
        );";
        

        try {
            
            $resultDetSalida = sqlsrv_query($this->cidLocal, $sqlDetSalida);

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }

    }

    public function insertarEncabezadoTango($fecha, $proximo, $proxInterno, $hora) 
    {
        $sqlEncabezado = "
        INSERT INTO STA14 
        (
        COD_PRO_CL, COTIZ, EXPORTADO, EXP_STOCK, FECHA_ANU, FECHA_MOV, HORA, 
        LISTA_REM, LOTE, LOTE_ANU, MON_CTE, N_COMP, NCOMP_IN_S, 
        NRO_SUCURS, T_COMP, TALONARIO, TCOMP_IN_S, USUARIO, HORA_COMP,
        ID_A_RENTA, DOC_ELECTR, IMP_IVA, IMP_OTIMP, IMPORTE_BO, IMPORTE_TO, 
        DIFERENCIA, SUC_DESTIN, DCTO_CLIEN, FECHA_INGRESO, HORA_INGRESO, 
        USUARIO_INGRESO, TERMINAL_INGRESO, IMPORTE_TOTAL_CON_IMPUESTOS, 
        CANTIDAD_KILOS
        )
        VALUES
        (
        '', 4.5, 0, 0, '1800/01/01', '$fecha', '0000', 0, 0, 0, 1, '$proximo', '$proxInterno', 0, 'TRA', 850, 'TI', 'AJUSTES', 
        '$hora', 0, 0, 0, 0, 0, 0, 'N', 0, 0, '$fecha', '$hora', 'AJUSTES', (SELECT host_name()), 0, 0
        )
        ;";
 
        try {

            $resultEncabezado = sqlsrv_query($this->cidLocal, $sqlEncabezado);

            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }

    }



    public function realizarMovimientoOu($cadena){
        $sql = "EXEC SJ_SP_TRANSFERENCIA_ARTICULOS '$cadena'";
      
        try {
            $result = sqlsrv_query($this->cidLocal, $sql);
    
            if ($result === false) {
                // Capturar detalles del error
                $errors = sqlsrv_errors();
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        // Aquí puedes registrar o manejar el error como desees
                        echo "SQLSTATE: ".$error['SQLSTATE']."<br />";
                        echo "Code: ".$error['code']."<br />";
                        echo "Message: ".$error['message']."<br />";
                    }
                }
                return false;
            }
    
            return true;
        } catch (\Throwable $th) {
            // Manejo de excepciones generales de PHP
            echo "Error en la ejecución del procedimiento almacenado: ".$th->getMessage();
            return false;
        }
    }
    
    public function realizarMovimientoDepositoCentral($cadena){

        $sql = "EXEC SJ_SP_TRANSFERENCIA_ARTICULOS_DC '$cadena'";
      
        try {
            $result = sqlsrv_query($this->cidLocal, $sql);
    
            if ($result === false) {
                // Capturar detalles del error
                $errors = sqlsrv_errors();
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        // Aquí puedes registrar o manejar el error como desees
                        echo "SQLSTATE: ".$error['SQLSTATE']."<br />";
                        echo "Code: ".$error['code']."<br />";
                        echo "Message: ".$error['message']."<br />";
                    }
                }
                return false;
            }
    
            return true;
        } catch (\Throwable $th) {
            // Manejo de excepciones generales de PHP
            echo "Error en la ejecución del procedimiento almacenado: ".$th->getMessage();
            return false;
        }

    }
    

 
    public function altaArticulo ($articulo, $numSucursal) {
       
        sqlsrv_configure("QueryTimeout", 60); 
    
        $sql = "EXEC EB_altaCodigoOutlet '$articulo'";

    
        try {

            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            if($numSucursal >= 201){

                $result = sqlsrv_query($this->cidUy, $sql);

            }else{
                
                $result = sqlsrv_query($this->cid, $sql); 

            }
       
            if ($result) {
                return true;
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            print_r($th);
        }

    }


    public function setearAjustadoEnc ($idEnc) {

        $sql = "UPDATE sj_reco_locales_enc SET ESTADO = '6' WHERE ID = $idEnc";

        try {
    
            $result = sqlsrv_query($this->cid, $sql);

            return true;

        } catch (\Throwable $th) {

            print_r($th); 

        }
    }

    public function conexionLocal ($numSucursal) {

        $sql = "SELECT CONEXION_DNS, BASE_NOMBRE from [LAKERBIS].LOCALES_LAKERS.DBO.SUCURSALES_LAKERS WHERE NRO_SUCURSAL = $numSucursal";

        $stmt = sqlsrv_query($this->cid, $sql);

        if ($stmt === false) {
            die("Error en la consulta: " . sqlsrv_errors());
        }

        $row = sqlsrv_fetch_array($stmt);

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

     
        $result = [];
    
        
        if(isset($row['CONEXION_DNS'])){

          $_SESSION['conexion_dns'] = $row['CONEXION_DNS'];
      
        }

        if(isset($row['BASE_NOMBRE'])){

            $_SESSION['base_nombre'] = $row['BASE_NOMBRE'];

        }


    }

}