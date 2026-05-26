<?php
require_once '../../Controlador/db.php';

if(strpos($_GET['title'],"Accesorios")!==false)
{
  $query ='EXEC SJ_TIPO_PEDIDO_CORDOBA_2';
}else {
  $query ='EXEC SJ_TIPO_PEDIDO_CORDOBA_1';
}

$stmt = sqlsrv_query($cid, $query);

$next_result = sqlsrv_next_result($stmt);

while ($row = sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)) {
  /* echo json_encode($row); */
  $RESULT[] = $row;
}
sqlsrv_close($cid);
echo json_encode($RESULT);
