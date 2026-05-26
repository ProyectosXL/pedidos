<?php

class Extralarge {

    function __construct(){

        require_once __DIR__.'/conexion.php';
        $this->conn = new Conexion;
        
    }

    
    public function login($user, $pass, $db = 'central'){

        $cid = $this->conn->conectar($db);
        

        $sql = "EXEC SJ_APP_LOGIN '$user', '$pass'";

        $stmt = sqlsrv_query($cid, $sql);

        try {

            $next_result = sqlsrv_next_result($stmt);

            while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v[0];

        } catch (\Throwable $th) {

            print_r($th);

        }



    }
    
    public function traerDatosDeConexionPorLocal($local){

        $cid = $this->conn->conectar('central');
        

        $sql = "SELECT CONEXION_DNS,BASE_NOMBRE FROM [LAKERBIS].locales_lakers.dbo.SUCURSALES_LAKERS WHERE NRO_SUC_MADRE is NULL AND NRO_SUCURSAL = '$local'";

        $stmt = sqlsrv_query($cid, $sql);

        try {

            while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }



    }

    public function deletePedidos($numSuc){

        $cid = $this->conn->conectar('central');

        $sql = "DELETE FROM SOF_PEDIDOS_CARGA WHERE NUM_SUC = $numSuc";
        try {

            $stmt = sqlsrv_prepare($cid,$sql);
            $stmt = sqlsrv_execute($stmt);
            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }


    }

    public function insertarPedidos($codArticu, $cantStock, $cantVend, $suc){

        $cid = $this->conn->conectar('central');

        $sql=
			"
			INSERT INTO SOF_PEDIDOS_CARGA (NUM_SUC, COD_ARTICU, CANT_STOCK, VENDIDO) VALUES ($suc, '$codArticu', $cantStock, $cantVend);
			"
			;
        try {

            $stmt = sqlsrv_prepare($cid,$sql);
            $stmt = sqlsrv_execute($stmt);
            return true;

        } catch (\Throwable $th) {

            print_r($th);

        }


    }

    public function traerDatosArticulos($localName){

        $cid = $this->conn->conectar($localName);

        
        if($cid == false){
            return false;    
        }
        
        

        $sql = "
        SET DATEFORMAT YMD
        
            ;WITH S AS (
                SELECT 
                    COD_ARTICU,
                    SUM(CANT_STOCK) AS STOCK_TOTAL
                FROM STA19
                WHERE CANT_STOCK > 0
                GROUP BY COD_ARTICU
            ),
            V AS (
                SELECT 
                    COD_ARTICU,
                    SUM(CASE T_COMP WHEN 'NCR' THEN -CANTIDAD ELSE CANTIDAD END) AS VENDIDO_30D
                FROM GVA53
                WHERE FECHA_MOV >= DATEADD(DAY, -30, CAST(GETDATE() AS date))
                GROUP BY COD_ARTICU
            )
            SELECT
                COALESCE(S.COD_ARTICU, V.COD_ARTICU) AS COD_ARTICU,
                CAST(COALESCE(S.STOCK_TOTAL, 0) AS INT) CANT_STOCK,
                CAST(COALESCE(V.VENDIDO_30D, 0) AS INT) VENDIDO
            FROM S
            FULL JOIN V ON V.COD_ARTICU = S.COD_ARTICU
            
        ORDER BY COD_ARTICU;
        ";

        $stmt = sqlsrv_query($cid, $sql);

        try {

            $v = [];

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            sqlsrv_close($cid);
    
            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }



    }

    public function traerDatosMayorista($codClient, $ven){

        $cid = $this->conn->conectar('central');
        

        $sql = "EXEC SJ_TRAER_DATOS_MAYORISTA '$codClient', '$ven'";

        $stmt = sqlsrv_query($cid, $sql);

        try {

            $next_result = sqlsrv_next_result($stmt);

            while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }
    
            return $v[0];

        } catch (\Throwable $th) {

            print_r($th);

        }

        
    }

    public function getEnv(){

        $envVars = $this->conn->envVars;
        return $envVars['ENV'];


    }

    public function limitePedidos($cliente){

        $cid = $this->conn->conectar('central');

        $tiposPedidos = ['PEDIDO GENERAL', 'PEDIDO ACCESORIOS', 'PEDIDO OUTLET'];

        $sql = "SELECT LEYENDA_1 AS TIPO, COD_CLIENT, COUNT(NRO_PEDIDO) CANT_PEDIDOS 
        FROM GVA21
        WHERE COD_CLIENT = 'GTSHSO' 
        AND FECHA_PEDI BETWEEN DATEADD(wk,DATEDIFF(wk,0,GETDATE()),0) AND DATEADD(wk,DATEDIFF(wk,0,GETDATE()),6) 
        AND TALON_PED = '97' 
        AND LEYENDA_1 IN ('PEDIDO GENERAL', 'PEDIDO ACCESORIOS', 'PEDIDO OUTLET')
        GROUP BY COD_CLIENT, LEYENDA_1 ;";

        $stmt = sqlsrv_query($cid, $sql);

        $v = [];

        try {

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

                $v[] = $row;

            }

            sqlsrv_close($cid);

            return $v;

        } catch (\Throwable $th) {

            print_r($th);

        }

        sqlsrv_close($cid);

        return $result;

    }

}