
<?php

$codClient = $_SESSION['codClient'];

class Fecha
{

    private function retornarArray($sqlEnviado)
    {

        require_once 'Conexion.php';

        $cid = new Conexion();
        $cid_central = $cid->conectar('central');
        $sql = $sqlEnviado;

        $stmt = sqlsrv_query($cid_central, $sql);

        $rows = array();

        while ($v = sqlsrv_fetch_array($stmt)) {
            $rows[] = $v;
        }


        return $rows;
    }


    public function traerFechaEntrega($codClient)
    {

        $sql = "SELECT COD_CLIENT, FECHA_DESPACHO FECHA FROM RO_FECHA_DESPACHO_ACTUAL WHERE COD_CLIENT = '$codClient'
        ";

        $rows = $this->retornarArray($sql);
        
        $myJSON = json_encode($rows);

        return $myJSON;

    }

}