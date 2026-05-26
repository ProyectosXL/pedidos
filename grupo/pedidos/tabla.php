<?php
if (substr($v['DESCRIPCIO'], -11) == '-- SALE! --') {
?>
    <tr style="font-size:smaller;font-weight:bold;color:#FE2E2E">
    <?php
} else {
    ?>
    <tr style="font-size:smaller">
    <?php
}
    ?>

    <td style="width: 4%">
        <a target="_blank" href="../../../Imagenes/<?= substr($v['COD_ARTICU'], 0, 13); ?>.jpg"><img src="../../../Imagenes/<?= substr($v['COD_ARTICU'], 0, 13); ?>.jpg" alt="" height="40" width="40"></a>
    </td>
    <td style="width: 8%" class="small"><?= $v['COD_ARTICU']; ?> </td>
    <td style="width: 1%"><input name="codArt[]" value="<?= $v['COD_ARTICU']; ?>" hidden> </td>
    <td style="width: 10%" class="small"><?= $v['DESCRIPCIO']; ?> </td>
    <td style="width: 10%" class="small" align="left"><?= $v['RUBRO']; ?> </td>
    <td style="width: 1%"><input name="rubro[]" value="<?= $v['RUBRO']; ?>" hidden> </td>
    <td style="width: 3%" id="stock"><?= (int)($v['CANT_STOCK']); ?> </td>
    <td style="width: 1% ; border-left: 1px solid black"><input name="stock[]" value="<?= $v['CANT_STOCK']; ?>" hidden> </td>
   
    <?php 
    // Usar las variables definidas en accesorios.php o general.php
    // Si no están definidas, usar valores por defecto
    if (!isset($sucursalesActivasInfo)) {
        $sucursalesActivas = isset($_SESSION['sucursales_activas']) ? $_SESSION['sucursales_activas'] : ['812', '813', '814', '815', '816', '876', '940'];
        $sucursalesInfo = [
            '812' => ['nombre' => 'BAULERA', 'codClient' => 'FRBAUD', 'nombreCompleto' => 'BAULERA'],
            '813' => ['nombre' => 'VELEZ', 'codClient' => 'FRORCE', 'nombreCompleto' => 'VELEZ'],
            '814' => ['nombre' => 'DINO', 'codClient' => 'FRORIG', 'nombreCompleto' => 'DINO'],
            '815' => ['nombre' => 'NVO CENTRO', 'codClient' => 'FRORNC', 'nombreCompleto' => 'NVO CENTRO'],
            '816' => ['nombre' => 'SAN JUAN', 'codClient' => 'FRORSJ', 'nombreCompleto' => 'SAN JUAN'],
            '876' => ['nombre' => 'JOCKEY', 'codClient' => 'FRPASJ', 'nombreCompleto' => 'JOCKEY'],
            '940' => ['nombre' => 'RIVERA', 'codClient' => 'FRPRIN', 'nombreCompleto' => 'RIVERA']
        ];
        $sucursalesActivasInfo = [];
        foreach ($sucursalesActivas as $suc) {
            if (isset($sucursalesInfo[$suc])) {
                $sucursalesActivasInfo[$suc] = $sucursalesInfo[$suc];
            }
        }
    }
    
    $firstSucursal = true;
    foreach ($sucursalesActivasInfo as $suc => $info): 
        $borderLeft = $firstSucursal ? '' : 'border-left: 1px solid black';
        $firstSucursal = false;
    ?>
        <td style="width: 2% ; <?= $borderLeft ?>"><?= (int)($v[$suc . '_STOCK'] ?? 0); ?> </td>
        <td style="width: 2%"><?= (int)($v[$suc . '_VENDIDO'] ?? 0); ?> </td>
        <td style="width: 4%"><input type="number" name="cantPed_<?= $suc ?>[]" id="cantPed" value="0" min="0" step="1" pattern="\d+" onkeyup="total();precioTotal()" onblur="validarInputCantidad(this)" size="1" tabindex="1" class="form-control form-control-sm <?= $info['codClient'] ?>"> </td>
    <?php endforeach; ?>
    <td style="width: 4%" id="precio"><?= (int)($v['PRECIO']); ?> </td>
    </tr>

    <?
    $result = odbc_exec($cid, $sql) or die(exit("Error en odbc_exec"));

    ?>