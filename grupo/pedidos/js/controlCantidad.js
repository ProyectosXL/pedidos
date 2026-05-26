/* window.addEventListener('DOMContentLoaded', deshabilitarPedidoXSucursal);

let pagina=document.title.split('-');
pagina=pagina.map((x)=>{return x.toUpperCase()})

function deshabilitarPedidoXSucursal()
{
    infoCordoba=JSON.parse(localStorage.getItem("infoCordoba"));
    if(pagina[1]==='GENERAL')
   {
    infoCordoba = infoCordoba.filter(info =>info!==null && info.TIPO.includes('GENERAL'));
   }else
   {

   }
}
 */